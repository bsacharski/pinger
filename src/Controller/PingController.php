<?php

declare(strict_types=1);

namespace Sandbox\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

class PingController extends AbstractController
{
    /**
     * @param array<string, mixed> $args
     */
    public function pingAction(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $jsonResponse = $response->withHeader('Content-Type', 'application/json');
        $jsonResponse->getBody()->write(json_encode('pong'));

        return $jsonResponse;
    }

    public function register(App $app): void
    {
        $app->get('/ping', [$this, 'pingAction']);
    }
}
