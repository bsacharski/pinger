<?php
namespace Sandbox\Controller;

use Psr\Log\LoggerInterface;
use Slim\App;

abstract class AbstractController
{
    /** @var LoggerInterface  */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    abstract public function register(App $app);
}
