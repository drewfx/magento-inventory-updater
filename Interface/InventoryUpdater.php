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
        $this->logger        = $logger;
    }

    public function run() {
        // start message

        // check if file exists

        // if it does open it (handle errors)

        // read data, make sure there's headers of sku and qty

        // update according and check if the sku exists before updating to avoid errors.
        // log when item is updated

        // finish
    }

    private function openFile() {

    }

    private function checkForFile() {

    }
}