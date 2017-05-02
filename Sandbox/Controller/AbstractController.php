<?php
namespace Sandbox\Controller;

use \Monolog\Logger;
use Slim\App;

abstract class AbstractController
{
    protected $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    abstract public function register(App $app);
}
