<?php

declare(strict_types=1);

namespace Sandbox\Controller;

use Psr\Log\LoggerInterface;
use Slim\App;

abstract class AbstractController
{
    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    abstract public function register(App $app): void;
}
