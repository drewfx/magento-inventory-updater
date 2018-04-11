<?php

namespace GC\Inventory\Console\Command;

use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Bootstrap;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use GC\Inventory\InventoryUpdater as Handler;
use GC\Inventory\Logger\Logger;

class InventoryUpdaterCommand extends Command
{
    /**
     *
     */
    protected function configure()
    {
        $this->setName('gc:inventory-updater');
        $this->setDescription('Update inventory quantities via SKU.');

        parent::configure();
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $stockRegistry = $this->getStockRegistry();
        $logger        = $this->getLogger();

        $output->writeln('Starting inventory updater');

        $handler = new Handler($stockRegistry, $logger);
        $handler->run();

        $output->writeln('Finished inventory updater.');
    }

    /**
     * @return StockRegistryInterface
     */
    protected function getStockRegistry()
    {
        $bootstrap      = Bootstrap::create(BP, $_SERVER);
        $objectManager  = $bootstrap->getObjectManager();
        //$state          = $objectManager->get('Magento\Framework\App\State');
        return $objectManager->get('Magento\CatalogInventory\Api\StockRegistryInterface');
    }

    /**
     * @return Logger
     */
    protected function getLogger() {
        return new Logger('GC_Inventory_Updater');
    }
}