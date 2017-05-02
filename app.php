<?php
namespace Sandbox;

use Sandbox\Controller\UrlController;
use Slim\App;

require_once(__DIR__ . '/vendor/autoload.php');

$app = new App([
    'logger' => [
        'name' => 'slim',
        'level' => \Monolog\Logger::DEBUG,
        'path' => __DIR__ . '/log/slim.log'
    ]
]);

$container = $app->getContainer();

$container['logger'] = function ($c) {
    $logger = new \Monolog\Logger('sandbox');
    $logger->pushHandler(new \Monolog\Handler\ErrorLogHandler());
    $logger->pushHandler(new \Monolog\Handler\StreamHandler(__DIR__ . '/log/sandbox.log'));

    return $logger;
};

$urlController = new UrlController($container->get('logger'));
$urlController->register($app);

$app->run();
