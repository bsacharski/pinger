<?php

declare(strict_types=1);

namespace Sandbox\Controller;

use mikehaertl\wkhtmlto\Image;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sandbox\Util\Pinger;
use Slim\App;
use Slim\Psr7\Response;
use Slim\Routing\RouteCollectorProxy;

class UrlController extends AbstractController
{
    private Pinger $pinger;

    public function __construct(Logger $logger)
    {
        parent::__construct($logger);
        $this->pinger = new Pinger($logger);
    }

    public function checkUrl(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        /** @var string $url */
        $url = $req->getQueryParams()['url'];

        $result = $this->pinger->check($url);
        if ($result) {
            return $res->withStatus(200);
        } else {
            return $res->withStatus(404);
        }
    }

    public function validateRequestMiddleware(
        ServerRequestInterface $req,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $params = $req->getQueryParams();
        $url = $params['url'] ?? false;
        if (!$url) {
            $errorResponse = new Response(400);
            $this->logger->debug('Invalid request received - missing query params', ['request' => $req]);
            $errorResponse->getBody()
                ->write('Missing url param');

            return $errorResponse;
        }

        // pass to next function if is ok
        return $handler->handle($req);
    }

    public function register(App $app): void
    {
        $this->logger->debug('Registering UrlController');

        // App::group callable is being bound to another context, so we need to grab reference to $this
        $self = $this;
        $app->group('/url', function (RouteCollectorProxy $group) use ($self): void {
            $group->get('/check', [$self, 'checkUrl']);
        })
        ->add([$this, 'validateRequestMiddleware']);
    }
}
