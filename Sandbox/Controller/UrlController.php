<?php
/**
 * Created by PhpStorm.
 * User: bsa
 * Date: 28.04.17
 * Time: 15:26
 */

namespace Sandbox\Controller;

use mikehaertl\wkhtmlto\Image;
use Monolog\Logger;
use Sandbox\Util\Pinger;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

class UrlController extends AbstractController
{
    /** @var Pinger */
    private $pinger;

    public function __construct(Logger $logger)
    {
        parent::__construct($logger);
        $this->pinger = new Pinger($logger);
    }

    public function checkUrl(Request $req, Response $res)
    {
        $url = $req->getQueryParam('url');

        $result = $this->pinger->check($url);
        if ($result) {
            return $res->withStatus(200);
        } else {
            return $res->withStatus(404);
        }
    }

    public function getThumbnail(Request $req, Response $res)
    {
        // TODO: Read settings from files
        $url = $req->getQueryParam('url');
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
        $stream = new \Slim\Http\Stream($fh);

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

    public function validateRequestMiddleware(Request $req, Response $res, $next)
    {
        $url = $req->getQueryParam('url');
        if (!$url) {
            $this->logger->debug('Invalid request received - missing query params', ['request' => $req]);
            $errorRes = $res->withStatus(400);
            $errorRes->getBody()
                ->write('Missing url param');

            return $errorRes;
        }

        // pass to next function if is ok
        return $next($req, $res);
    }

    public function register(App $app)
    {
        $this->logger->debug('Registering UrlController');
        $self = $this;

        $app->group('/url', function () use ($self) {
            /** @var App $this */
            $this->add([$self, 'validateRequestMiddleware']);

            $this->get('/check', [$self, 'checkUrl']);
            $this->get('/thumbnail', [$self, 'getThumbnail']);
        });
    }
}
