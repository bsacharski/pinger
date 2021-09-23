<?php

declare(strict_types=1);

namespace Sandbox\Util\Validator;

class UrlComponents
{
    public function __construct(private string $scheme, private string $host)
    {
        // empty by design thanks to promoted attributes
    }

    /**
     * @return string
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }
}
