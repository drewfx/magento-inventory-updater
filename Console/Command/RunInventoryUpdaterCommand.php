<?php

namespace GC\InventoryUpdater\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunInventoryUpdaterCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('gc:run:inventory-updater')
             ->setDescription('Runs inventory updater to update quantities via SKU.');
        parent::configure();
    }

    protected function execute() {
    }
}