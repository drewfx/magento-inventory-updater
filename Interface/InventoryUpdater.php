<?php

namespace GC\InventoryUpdater;

use \Magento\CatalogInventory\Api\StockRegistryInterface;
use \GC\InventoryUpdater\Logger\Logger;

class InventoryUpdater
{
    protected $stockRegistry;
    protected $logger;

    public function __construct(StockRegistryInterface $stockRegistry, Logger $logger) {
        $this->stockRegistry = $stockRegistry;
        $this->logger           = $logger;
    }

    public function run() {
        // start message

        // check if file exists

        // if it does open it (handle errors)

        // read data, make sure there's headers of sku and qty

        // update according and check if the sku exists before updating to avoid errors.
        // log when item is updated
		$lines = [];

        foreach ($lines as $line) {
            $sku = $line[0];

            // verify it resembles a sku

            $product = $this->stockRegistry->getStockItemBySku($sku);
            if ($product && is_object($product)) {
                $qty = (int)$line[1];

                $product->setQty($qty);

                if ($qty > 0) {
                    $product->setIsInStock(true);
                } else {
                    $product->setIsInStock(false);
                }
                $product->save();
            }
        }

        // finish
    }

    private function openFile() {

    }

    private function checkForFile() {

    }
	
    protected function startMessage() {
        $this->logger->info('Inventory Updater is starting.');
    }

    protected function completeMessage() {
        $this->logger->info('Inventory Updater is complete.');
    }

    protected function error($message = '') {
        $this->logger->error($message);
    }
}