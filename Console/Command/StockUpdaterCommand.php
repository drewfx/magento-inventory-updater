<?php

namespace Drewsauce\StockUpdater\Console\Command;

use Magento\Framework\App\Bootstrap;
use Magento\CatalogInventory\Api\StockRegistryInterface;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Drewsauce\StockUpdater\Quantity\Updater;
use Drewsauce\StockUpdater\Logger\Logger;

class StockUpdaterCommand extends Command
{
    /**
     *
     */
    protected function configure()
    {
        $this->setName('drewsauce:update:stock-quantities');
        $this->setDescription('Update inventory stock quantities via SKU given a CSV.');

        parent::configure();
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stockRegistry = $this->getStockRegistry();
        $logger        = $this->getLogger();

        $output->writeln('Starting stock updater');

        $handler = new Updater($stockRegistry, $logger);
        $handler->run();

        $output->writeln('Finished stock updater.');
    }

    /**
     * @return StockRegistryInterface
     */
    protected function getStockRegistry()
    {
        $bootstrap      = Bootstrap::create(BP, $_SERVER);
        $objectManager  = $bootstrap->getObjectManager();
        $state          = $objectManager->get('Magento\Framework\App\State');
        $state->setAreaCode('adminhtml');

        return $objectManager->get('Magento\CatalogInventory\Api\StockRegistryInterface');
    }

    /**
     * @return Logger
     */
    protected function getLogger()
    {
        return new Logger('Drewsauce_StockUpdater');
    }
}