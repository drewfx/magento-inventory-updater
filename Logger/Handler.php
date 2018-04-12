<?php

namespace Drewsauce\StockUpdater\Logger;

use Monolog\Logger;

class Handler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * File Name
     *
     * @var string
     */
    protected $fileName = '/var/log/stock_updater.log';

    /**
     * Logging Level
     *
     * @var int
     */
    protected $loggerType = Logger::INFO;
}