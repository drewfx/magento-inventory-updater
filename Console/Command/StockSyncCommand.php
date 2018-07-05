<?php

namespace Drewsauce\StockSync\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StockSyncCommand extends Command
{
    /**
     * @var \Drewsauce\StockSync\Quantity\Updater
     */
    private $updater;

    /**
     * @var \Magento\Framework\App\State
     */
    private $state;

    /**
     * StockSyncCommand constructor.
     * @param \Drewsauce\StockSync\Quantity\Updater $updater
     * @param \Magento\Framework\App\State $state
     */
    public function __construct(
        \Drewsauce\StockSync\Quantity\Updater $updater,
        \Magento\Framework\App\State $state
    ) {
        $this->updater = $updater;
        $this->state = $state;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('drewsauce:update:stock-quantities')
            ->setDescription('Update inventory stock quantities via SKU given a CSV.');

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->updater->run();
    }
}