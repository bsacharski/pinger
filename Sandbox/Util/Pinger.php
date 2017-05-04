<?php
namespace Sandbox\Util;

use Psr\Log\LoggerInterface;

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
    /** @var LoggerInterface */
    private $logger;
    private $maxRedirects = 5;
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
     * @param $url
     * @return \HTTP_Request2
     */
    private function prepareRequest($url)
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
    private function doCall($url)
    {
        $originalUrl = $url;

        try {
            $redirectsLeft = $this->maxRedirects;

            // Manually follow redirects and prevent calling private hosts
            /** @var \HTTP_Request2_Response $response*/
            do {
                $this->logger->debug("Checking url", [ 'url' => $url, 'originalUrl' => $originalUrl ]);

                $isUrlValid = $this->validateUrl($url);
                if (!$isUrlValid) {
                    $this->logger->debug('Url is not valid ', [ 'url' => $url, 'originalUrl' => $originalUrl ]);
                    return false;
                }

                $this->logger->debug('Doing a call', ['url' => $url]);
                $response = $this->prepareRequest($url)->send();
                $locationUrl = trim($response->getHeader('location'));

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
            $this->logger->debug('Url response failure', [ 'url' => $url, 'status' => $response->getStatus() ]);
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
     * Checks if given hostname is an IPv6 address
     * @param string $hostname
     * @return bool true if $hostname is IPv6, otherwise false
     */
    private function isIPv6($hostname)
    {
        $this->logger->debug('Checking if hostname is IPv6 address', [ 'hostname' => $hostname ]);

        // parse_url seems to leave [ ] around IPv6 address - need to get rid of that
        $hostname = trim(preg_replace("/[\\[\\]]/", '', $hostname));

        $regex = '/^\\s*((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))'
            . '|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}'
            . '|((25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)(\\.(25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)){3})|:))'
            . '|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})'
            . '|:((25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)(\\.(25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)){3})|:))'
            . '|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})'
            . '|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)'
            . '(\\.(25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)){3}))'
            . '|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:'
            . '((25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)(\\.(25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)){3}))|:))'
            . '|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:'
            . '((25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)(\\.(25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)){3}))|:))'
            . '|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:'
            . '((25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)(\\.(25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)){3}))|:))'
            . '|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}'
            . ':((25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)(\\.(25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)){3}))|:)))(%.+)?\\s*$/';

        $isIPv6 = preg_match($regex, $hostname) > 0;

        if ($isIPv6) {
            $this->logger->debug('Detected IPv6 address', [ 'hostname' => $hostname ]);
        }

        return !!$isIPv6;
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
        $publicIp = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            (FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
        );

        // private/reserved ip detected
        if (!$publicIp) {
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

        /* IPv6 addresses are not supported!
         * If someone knows how to properly add this (with domain resolution) - feel free to add it.
         */
        if ($this->isIPv6($urlData['host'])) {
            $this->logger->debug('IPv6 address detected - marking as invalid', [ 'url' => $url ]);
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
        return $this->doCall($url);
    }
}
