<?php
namespace Sandbox\Util;

use Psr\Log\LoggerInterface;

/**
 * Class Pinger
 * @package Sandbox\Util
 *
 * Pinger is a class that allows checking if given url responds correctly
 */
class Pinger
{
    /** @var  \HTTP_Request2_Adapter */
    private $adapter;
    /** @var LoggerInterface */
    private $logger;
    private $timeout = 10;
    /** @var string user agent string that ping will present when checking url */
    private $userAgent = UserAgent::MOZILLA;

    public function __construct(LoggerInterface $logger, \HTTP_Request2_Adapter $adapter = null)
    {
        if (!$adapter) {
            $adapter = new \HTTP_Request2_Adapter_Curl();
        }

        $this->adapter = $adapter;
        $this->logger = $logger;
    }

    /**
     * Checks if given url is online (responds correctly to GET request)
     * @param string $url
     * @return bool true if url responds, otherwise false
     */
    private function doCall($url)
    {
        /** @var \HTTP_Request2 $request */
        $request = new \HTTP_Request2($url, \HTTP_Request2::METHOD_GET, [
            'adapter' => $this->adapter,
            'timeout' => $this->timeout,
            'ssl_verify_peer' => false,
            'follow_redirects' => true
        ]);

        $this->logger->debug('Doing a call', ['url' => $url]);
        /** @var \HTTP_Request2_Response */
        try {
            $response = $request->setHeader('user-agent', $this->userAgent)->send();
            $statusCode = $response->getStatus();
            $isOk = $statusCode >= 200 && $statusCode < 400;
        } catch (\HTTP_Request2_Exception $e) {
            $isOk = false;
        }

        if ($isOk) {
            $this->logger->debug('Url response success', [ 'url' => $url ]);
        } else {
            $this->logger->debug('Url response failure', [ 'url' => $url, 'error' => $request->getLastEvent() ]);
        }

        return $isOk;
    }

    /**
     * @param string $protocol
     * @return bool
     */
    private function isHttpProtocol($protocol)
    {
        return in_array($protocol, ['http', 'https']);
    }

    /**
     * Checks if given hostname belongs to private/reserved IP class.
     * If hostname is not an ip, it will be translated to ip using <pre>gethostbyname</pre>.
     *
     * @param string $hostname either an ip address or hostname
     * @return bool true if hostname/ip belongs to private/reserved IP class, otherwise false
     */
    private function isPrivateIP($hostname)
    {
        $this->logger->debug('Check if hostname is private ip', [ 'hostname' => $hostname ]);

        $isIPv4 = filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        if ($isIPv4) {
            $ip = $hostname;
        } else {
            // get ip using hostname - this has to append the dot at end!
            $resolved = gethostbyname($hostname . '.');
            // failed to resolve the domain - should be fine
            if ($resolved === ($hostname . '.')) {
                return false;
            }

            $ip = $resolved;
        }

        // if we're dealing with IPv4 it shouldn't be in private nor in reserved range
        $isPrivIPv4 = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            (FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
        );

        // private/reserved ip detected
        if (!$isPrivIPv4) {
            $this->logger->debug('Private IPv4 detected', [ 'hostname' => $hostname, 'ip' => $ip ]);
            return true;
        }

        // not an private ip
        return false;
    }

    /**
     * Checks if url is valid
     *
     * @param string $url
     * @return bool true if url is valid, otherwise false
     */
    private function validateUrl($url)
    {
        if (strlen($url) === 0) {
            return false;
        }

        $urlData = parse_url($url);
        if (!$urlData || !isset($urlData['scheme']) || !isset($urlData['host'])) {
            return false;
        }

        $isValidProtocol = $this->isHttpProtocol($urlData['scheme']);
        $isValidIp = !$this->isPrivateIP($urlData['host']);

        return $isValidProtocol && $isValidIp;
    }

    /**
     * Checks if given url is responding to a GET call.
     * Please note that there are various restrictions like preventing calling private IP address.
     *
     * @param string $url
     * @return bool true if url responds correctly, otherwise false
     */
    public function check($url)
    {
        $this->logger->debug("Checking url", ['url' => $url]);

        $isValid = $this->validateUrl($url);
        if (! $isValid) {
            $this->logger->debug('Url is not valid', ['url' => $url]);
            return false;
        }

        $this->logger->debug('Url is valid', ['url' => $url]);

        return $this->doCall($url);
    }
}
