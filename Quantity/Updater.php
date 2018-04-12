<?php

namespace Drewsauce\StockUpdater\Quantity;

use \Magento\CatalogInventory\Api\StockRegistryInterface;

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
    protected $fileLocation = __DIR__ . '/../inventory.csv'; // TODO: replace with actual file location

    /**
     * @var StockRegistryInterface
     */
    protected $stockRegistry;

    /**
     * @var \Drewsauce\StockUpdater\Logger\Logger
     */
    protected $logger;

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
    protected $file;

    /**
     * Handler constructor.
     *
     * @param StockRegistryInterface                $stockRegistry
     * @param \Drewsauce\StockUpdater\Logger\Logger $logger
     */
    public function __construct(StockRegistryInterface $stockRegistry, \Drewsauce\StockUpdater\Logger\Logger $logger)
    {
        $this->stockRegistry = $stockRegistry;
        $this->logger        = $logger;
    }

    public function run()
    {
        $this->startMessage();

        if ($this->checkForFile()) {
            $this->setFile();
            $this->setHeaders();

            if ($this->verifyHeaders()) {
                $this->updateStock();
            }
        } else {
            $this->logger->alert('Stock file not found: ' . $this->fileLocation);
        }

        // finish
        $this->completeMessage();
    }

    /**
     * Opens and sets the file if all requirements are met.
     *  - Checks to ensure the file is not empty.
     *
     * Dies upon failure.
     */
    protected function setFile()
    {
        try {
            // Check file is not empty.
            if ($this->fileNotEmpty()) {
                $this->file = fopen($this->fileLocation, 'r');
            } else {
                $this->logger->error('Stock file is empty: ' . $this->fileLocation);
                die();
            }
        } catch (\Exception $e) {
            $this->logger->error('Stock file unable to be opened: ' . $this->fileLocation);
            $this->logger->error($e->getMessage());
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
    protected function fileNotEmpty() {
        return (filesize($this->fileLocation) !== 0 && !empty(trim(file_get_contents($this->fileLocation))));
    }

    /**
     * Gets and sets the headers from the top of the CSV.
     */
    protected function setHeaders() {
        $this->headers = fgetcsv($this->file);
    }

    /**
     * This function will verify the headers are an array,
     * and the values 'qty' and 'sku' exist in our header array.
     *
     * Dies upon failure.
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
                $this->logger->error('Stock Updater is missing the sku header.');
                die();
            }

            // Check for our QTY headers (required).
            if (!$this->checkForHeader(self::QTY)) {
                $this->logger->error('Stock Updater is missing the qty header.');
                die();
            }

            //  All requirements met, return true.
            return true;
        } else {
            $this->logger->error('Stock Updater parsing error, headers not read as an array.');
            die();
        }
    }

    /**
     * Checks the headers for a designated value.
     *
     * @param string $field
     *
     * @return bool
     */
    protected function checkForHeader($field ='') {
        // Check if field exists in the headers
        if ($field && in_array($field, $this->headers, true)) {
            // If the field is set set the index for said header.
            $this->{$field . 'Index'} = array_search($field, $this->headers);
            return true;
        }

        return false;
    }

    protected function updateStock() {
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
                $this->logger->error('Stock Updater skipping row ' . $rowCount . ' lack of data columns.');

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

                if ($qty > 0) {
                    $stockItem->setIsInStock(true);
                }

                try {
                    $this->stockRegistry->updateStockItemBySku($sku, $stockItem);
                    $this->logger->info('Stock item ' . $sku . ' successfully updated to quantity ' . $qty);
                    $updateSuccess++;
                } catch (\Exception $e) {
                    $this->logger->error('Stock Updater was unable to update SKU: ' . $sku);
                    $this->logger->error($e->getMessage());
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

    protected function removeEmptyValues($row) {
        if (!empty($row)) {
            return array_filter($row, function($value) {
                return $value !== '';}
            );
        }
    }

    /**
     * Signifies the start of our stock update procedures in the log file.
     */
    protected function startMessage()
    {
        $this->logger->info('Stock Updater is starting.');
    }

    /**
     * Signifies the end of our stock update procedures in the log file.
     */
    protected function completeMessage()
    {
        $this->logger->info('Stock Updater is complete.');
    }
}