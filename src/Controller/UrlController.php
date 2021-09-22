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
use Slim\Psr7\Stream;
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

    public function getThumbnail(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        // TODO: Read settings from files
        /** @var string $url */
        $url = $req->getQueryParams()['url'];
        $this->logger->debug('Grabbing thumbnail', [ 'url' => $url ]);

        $isOnline = $this->pinger->check($url);
        if (! $isOnline) {
            $this->logger->debug('URL is not online - not grabbing', [ 'url' => $url ]);
            return $res->withStatus(404);
        }

        $wkOpts = [
            'width' => 1024,
            'height' => 768,
            'quality' => 50,
            'disable-local-file-access' => true,
            'type' => 'jpg'
        ];

        $file = tempnam('/tmp', 'thumb_') . '.jpg';
        $this->logger->debug('Calling wkhtmltoimage', [ 'url' => $url, 'options' => $wkOpts, 'out' => $file ]);

        $webkit = new Image($url);
        $webkit->setOptions($wkOpts);
        $result = $webkit->saveAs($file);

        if (!$result) {
            $this->logger->debug(
                "Couldn't generate thumbnail",
                [ 'url' => $url, 'out' => $file, 'errMsg' => $webkit->getError()]
            );
            return $res->withStatus(404);
        }

        $this->logger->debug('Thumbnail generated. Sending response.', [ 'url' => $url, 'out' => $file ]);
        $fh = fopen($file, 'rb');
        $stream = new Stream($fh);

        return $res->withHeader('Content-Type', 'application/force-download')
                ->withHeader('Content-Type', 'application/octet-stream')
                ->withHeader('Content-Type', 'application/download')
                ->withHeader('Content-Type', 'image/png')
                ->withHeader('Content-Description', 'File Transfer')
                ->withHeader('Content-Transfer-Encoding', 'binary')
                ->withHeader('Content-Disposition', 'attachment; filename="' . basename($file) . '"')
                ->withHeader('Expires', '0')
                ->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
                ->withHeader('Pragma', 'public')
                ->withBody($stream);
    }

    public function validateRequestMiddleware(ServerRequestInterface $req, RequestHandlerInterface $handler): ResponseInterface
    {
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
            $group->get('/thumbnail', [$self, 'getThumbnail']);
        })
        ->add([$this, 'validateRequestMiddleware']);
    }
}
