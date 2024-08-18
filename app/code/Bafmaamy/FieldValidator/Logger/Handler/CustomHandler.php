<?php

namespace Bafmaamy\FieldValidator\Logger\Handler;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class CustomHandler extends StreamHandler
{
    public function __construct()
    {
        $logFile = BP . '/var/log/custom_order.log'; // Ensure the path is correct
        parent::__construct($logFile, Logger::INFO); // Adjust log level if necessary
    }
}
