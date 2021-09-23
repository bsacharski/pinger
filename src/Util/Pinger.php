<?php

declare(strict_types=1);

namespace Sandbox\Util;

use Psr\Log\LoggerInterface;
use Sandbox\Util\Validator\UrlValidator;

/**
 * Class Pinger
 * @package Sandbox\Util
 *
 * Pinger is a class that allows checking if given url responds correctly.
 * PLEASE NOTE! IPv6 addresses are not handled correctly!
 *
 * // FIXME: This really should use some external library to validate and process IPv6 address
 */
class Pinger
{
    /** @var  \HTTP_Request2_Adapter */
    private $adapter;
    private LoggerInterface $logger;
    private int $maxRedirects = 5;
    private int $timeout = 10;
    /** @var string user agent string that ping will present when checking url */
    private string $userAgent = UserAgent::MOZILLA;
    private UrlValidator $urlValidator;

    public function __construct(LoggerInterface $logger, \HTTP_Request2_Adapter $adapter = null)
    {
        if (!$adapter) {
            $adapter = new \HTTP_Request2_Adapter_Curl();
        }

        $this->adapter = $adapter;
        $this->logger = $logger;
        $this->urlValidator = new UrlValidator(true);
    }

    private function prepareRequest(string $url): \HTTP_Request2
    {
        /** @var \HTTP_Request2 $request */
        $request = new \HTTP_Request2($url, \HTTP_Request2::METHOD_GET, [
            'adapter' => $this->adapter,
            'timeout' => $this->timeout,
            'ssl_verify_peer' => false,
            'follow_redirects' => false
        ]);
        $request->setHeader('user-agent', $this->userAgent);
        return $request;
    }

    /**
     * Checks if given url is online (responds correctly to GET request)
     * @param string $url
     * @return bool true if url responds, otherwise false
     */
    private function isOnline(string $url): bool
    {
        $originalUrl = $url;

        try {
            $redirectsLeft = $this->maxRedirects;

            // Manually follow redirects and prevent calling private hosts
            do {
                $this->logger->debug("Checking url", [ 'url' => $url, 'originalUrl' => $originalUrl ]);

                $isUrlValid = $this->validateUrl($url);
                if (!$isUrlValid) {
                    $this->logger->debug('Url is not valid ', [ 'url' => $url, 'originalUrl' => $originalUrl ]);
                    return false;
                }

                $this->logger->debug('Doing a call', ['url' => $url]);
                $response = $this->prepareRequest($url)->send();

                /** @var string $location */
                $location = $response->getHeader('location') ?? '';
                $locationUrl = trim($location);

                if ($locationUrl) {
                    $this->logger->debug(
                        'Redirect detected',
                        [
                            'url' => $url,
                            'newUrl' => $locationUrl,
                            'originalUrl' => $originalUrl
                        ]
                    );

                    $url = $locationUrl;
                }
                $redirectsLeft--;
            } while ($response->getHeader('location') && $redirectsLeft > 0);

            $statusCode = $response->getStatus();
            $isOk = $statusCode >= 200 && $statusCode < 400;
        } catch (\HTTP_Request2_Exception $e) {
            $this->logger->debug("GET call failed", [ 'url' => $url, 'message' => $e->getMessage() ]);
            $isOk = false;
        }

        if ($isOk) {
            $this->logger->debug('Url response success', [ 'url' => $url ]);
        } else {
            $this->logger->debug('Url response failure', [ 'url' => $url ]);
        }

        return $isOk;
    }

    /**
     * Checks if url is valid
     *
     * @param string $url
     * @return bool true if url is valid, otherwise false
     */
    private function validateUrl(string $url): bool
    {
        return $this->urlValidator->isValid($url);
    }

    /**
     * Checks if given url is responding to a GET call.
     * Please note that there are various restrictions like preventing calling private IP address.
     *
     * @param string $url
     * @return bool true if url responds correctly, otherwise false
     */
    public function check(string $url): bool
    {
        $this->logger->debug("Checking url", ['url' => $url]);
        return $this->isOnline($url);
    }
}
