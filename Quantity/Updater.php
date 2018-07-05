<?php

namespace Drewsauce\StockSync\Quantity;

class Updater
{
    const SKU = 'sku';
    const QTY = 'qty';

    /**
     * Minimum required columns for our CSV.
     * @var int
     */
    protected $requiredColumns = 2;

    /**
     * File location for our csv. (TBD)
     * @var string
     */
    protected $fileLocation;

    /**
     * @var \Drewsauce\StockSync\Aws\Handler
     */
    protected $aws;

    /**
     * @var \Magento\CatalogInventory\Api\StockRegistryInterface
     */
    protected $stockRegistry;

    /**
     * Dynamically set indexes for our CSV.
     */
    protected $skuIndex;
    protected $qtyIndex;

    /**
     * Dynamically set headers for our CSV.
     */
    protected $headers;

    /**
     * Dynamically set file object.
     */
    private $file;

    /**
     * @var \Drewsauce\StockSync\Logger\Logger
     */
    private $logger;

    /**
     * @var \Drewsauce\StockSync\Loader\Config
     */
    private $config;

    /**
     * Updater constructor.
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param \Drewsauce\StockSync\Logger\Logger $logger
     * @param \Drewsauce\StockSync\Loader\Config $config
     * @param \Drewsauce\StockSync\Aws\Handler $aws
     * @throws \Drewsauce\StockSync\Exception\EmptyConfigException
     */
    public function __construct(
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Drewsauce\StockSync\Logger\Logger $logger,
        \Drewsauce\StockSync\Loader\Config $config,
        \Drewsauce\StockSync\Aws\Handler $aws
    ) {
        $this->stockRegistry = $stockRegistry;
        $this->logger        = $logger;
        $this->config        = $config;
        $this->aws           = $aws;
        $this->fileLocation  = $this->config->getIncomingFolder();
    }

    /**
     * Main execution of our stock quantity updater module.
     *  - Verifies the file exists, sets the file if it's not empty.
     *  - Verifies structure and existence of headers and sets the index dynamically.
     *  - Updates products stock quantity based on SKU.
     *  - Logs to stock_updater.log
     *
     * @throws \Drewsauce\StockSync\Exception\InvalidBucketException
     */
    public function run()
    {
        $this->downloadFile();

        $this->checkForFile();
        die;
        if ($this->checkForFile()) {
            $this->setFile();
            $this->setHeaders();

            if ($this->verifyHeaders()) {
                $this->updateStock();
            }
        } else {
            $this->logger->info('Stock file not found: ' . $this->fileLocation);
        }
    }

    /**
     * @throws \Drewsauce\StockSync\Exception\InvalidBucketException
     */
    protected function downloadFile()
    {
        $this->aws->run();
    }


    /**
     * Opens and sets the file if all requirements are met.
     *  - Checks to ensure the file is not empty.
     *  - Dies upon failure.
     */
    protected function setFile()
    {
        try {
            // Check file is not empty.
            if ($this->fileNotEmpty()) {
                $this->file = fopen($this->fileLocation, 'r');
            } else {
                $this->logger->info('Stock file is empty: ' . $this->fileLocation);
                die();
            }
        } catch (\Exception $e) {
            $this->logger->info('Stock file unable to be opened: ' . $this->fileLocation);
            $this->logger->info($e->getMessage());
            die();
        }
    }

    /**
     * Checks a file exists.
     *
     * @return bool
     */
    protected function checkForFile()
    {
        return file_exists($this->fileLocation);
    }

    /**
     * Checks to see if the file is empty.
     *
     * @return bool
     */
    protected function fileNotEmpty()
    {
        return (filesize($this->fileLocation) !== 0 && !empty(trim(file_get_contents($this->fileLocation))));
    }

    /**
     * Sets the headers from the CSV.
     */
    protected function setHeaders()
    {
        $this->headers = fgetcsv($this->file);
    }

    /**
     * This function will verify the headers are an array,
     * and the values 'qty' and 'sku' exist in our header array.
     *  - Dies upon failure.
     *
     * @return bool
     */
    protected function verifyHeaders()
    {
        // Check if headers are an array.
        if (is_array($this->headers)) {
            // Ensure our headers are all lower case to match our constants.
            foreach ($this->headers as $index => $value) {
                $this->headers[$index] = strtolower($value);
            }

            // Check for our SKU headers (required).
            if (!$this->checkForHeader(self::SKU)) {
                $this->logger->info('Stock Updater is missing the sku header.');
                die();
            }

            // Check for our QTY headers (required).
            if (!$this->checkForHeader(self::QTY)) {
                $this->logger->info('Stock Updater is missing the qty header.');
                die();
            }

            //  All requirements met, return true.
            return true;
        } else {
            $this->logger->info('Stock Updater parsing error, headers not read as an array.');
            die();
        }
    }

    /**
     * Checks the headers for a designated value.
     *
     * @param string $field
     * @return bool
     */
    protected function checkForHeader($field = '')
    {
        // Check if field exists in the headers
        if ($field && in_array($field, $this->headers, true)) {
            // If the field is set set the index for said header.
            $this->{$field . 'Index'} = array_search($field, $this->headers);
            return true;
        }

        return false;
    }

    protected function updateStock()
    {
        $start = microtime(true);

        $rowCount       = 0;
        $updateSuccess  = 0;
        $updateFailure  = 0;
        $noSku          = 0;
        $incorrectRowFormat = 0;

        while ($row = fgetcsv($this->file, 2000, ',')) {
            ++$rowCount;
            // Check our row meets the required columns count.
            $row = $this->removeEmptyValues($row);

            if (count($row) < $this->requiredColumns) {
                $this->logger->info('Stock Updater skipping row ' . $rowCount . ' lack of data columns.');
                continue;
            }

            // Initialize our local vars.
            $stockItem  = null;
            $sku        = (int)trim($row[$this->skuIndex]);
            $qty        = (int)trim($row[$this->qtyIndex]);

            // Get stock item by the sku number for updating procedure.
            try {
                $stockItem = $this->stockRegistry->getStockItemBySku($sku);
            } catch (\Exception $e) {
                $this->logger->info('Stock Updater invalid SKU: ' . $sku);
                $noSku++;
                continue;
            }

            // Update quantities.
            if (is_object($stockItem)) {
                $stockItem->setQty($qty);

                // If the qty is > 0 let's set the InStock flag to true, otherwise false.
                if ($qty > 0) {
                    $stockItem->setIsInStock(true);
                } else {
                    $stockItem->setIsInStock(false);
                }

                // Update stock item by sku.
                try {
                    $this->stockRegistry->updateStockItemBySku($sku, $stockItem);
                    $updateSuccess++;
                } catch (\Exception $e) {
                    $this->logger->info('Stock Updater was unable to update SKU: ' . $sku);
                    $this->logger->info($e->getMessage());
                    $updateFailure++;
                }
            }
        }

        $end = microtime(true);
        $this->logger->info('------- Stock Updater took ' . (($end - $start) / 60) . ' seconds -------');
        $this->logger->info('Number of successful updates: ' . $updateSuccess);
        $this->logger->info('Number of failures: ' . $updateFailure);
        $this->logger->info('Number of no sku items: ' . $noSku);
        $this->logger->info('Number of incorrect row format: '. $incorrectRowFormat);
    }

    /**
     * Removes empty values from the array so we can properly check the columns in a row.
     *
     * @param array $row
     * @return array
     */
    protected function removeEmptyValues(array $row)
    {
        if (!empty($row)) {
            return array_filter($row, function($value) {
                return $value !== '';}
            );
        }
        return [];
    }
}