<?php

namespace GC\Inventory\Logger;

use Monolog\Logger;

class Handler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/inventory_updater.log';

    /**
     * @var int
     */
    protected $loggerType = Logger::DEBUG;
}