<?php

declare(strict_types=1);

namespace Sandbox;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Pimple\Psr11\Container;
use Sandbox\Controller\PingController;
use Sandbox\Controller\UrlController;
use Slim\Factory\AppFactory;

require_once(__DIR__ . '/vendor/autoload.php');

$pimpleContainer = new \Pimple\Container(['logger' => function ($c) {
    $logger = new Logger('sandbox');
    $logger->pushHandler(new ErrorLogHandler());
    $logger->pushHandler(new StreamHandler(__DIR__ . '/log/sandbox.log'));

    return $logger;
}]);

$container = new Container($pimpleContainer);

$app = AppFactory::createFromContainer($container);

$urlController = new UrlController($container->get('logger'));
$urlController->register($app);
$pingController = new PingController($container->get('logger'));
$pingController->register($app);

$app->run();
