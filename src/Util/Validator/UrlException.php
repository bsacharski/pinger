<?php

declare(strict_types=1);

namespace Sandbox\Util\Validator;

class UrlException extends \Exception
{
    public function __construct(string $url, string $message)
    {
        $exceptionMessage = "Failed to parse url '{$url}', reason: {$message}";
        parent::__construct($exceptionMessage);
    }
}
