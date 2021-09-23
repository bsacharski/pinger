<?php

namespace Sandbox\Util\Validator;

class UrlValidator
{
    private const VALID_PROTOCOLS = ['http', 'https'];

    public function __construct(private bool $rejectLocalHosts = false)
    {
        // empty by design
    }

    private function usesValidProtocol(string $protocol): bool
    {
        $isValid = in_array($protocol, self::VALID_PROTOCOLS);
        return $isValid;
    }

    /**
     * @throws UrlException
     */
    private function decomposeUrl(string $url): UrlComponents
    {
        $components = parse_url($url);
        if (!is_array($components)) {
            throw new UrlException($url, "failed to decode url");
        }

        if (empty($components['scheme'])) {
            throw new UrlException($url, "url does not contain protocol");
        }

        if (empty($components['host'])) {
            throw new UrlException($url, "url does not contain hostname");
        }

        $scheme = $components['scheme'];
        // parse_url seems to leave [ ] around IPv6 address - need to get rid of that
        $host = trim(preg_replace("/[\\[\\]]/", '', $components['host']));

        return new UrlComponents($scheme, $host);
    }

    public function isValid(string $url): bool
    {
        try {
            $urlComponents = $this->decomposeUrl($url);
            $protocol = $urlComponents->getScheme();
            if ($this->usesValidProtocol($protocol) === false) {
                return false;
            }

            if ($this->rejectLocalHosts) {
                if ($this->isLocal($urlComponents->getHost())) {
                    return false;
                }
            }

            return true;
        } catch (UrlException) {
            return false;
        }
    }

    public function isLocal(string $hostname): bool
    {
        $isIPAddress = !!filter_var($hostname, FILTER_VALIDATE_IP);
        if ($isIPAddress) {
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
        $isPublicIp = !!filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            (FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
        );

        // private/reserved ip detected
        if (!$isPublicIp) {
            return true;
        }

        // not an private ip
        return false;
    }

    private function isIPv6(string $hostname): bool
    {
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
        return !!$isIPv6;
    }
}
