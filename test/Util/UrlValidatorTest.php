<?php

declare(strict_types=1);

namespace Sandbox\Test\Util;

use PHPUnit\Framework\TestCase;
use Sandbox\Util\Validator\UrlValidator;

class UrlValidatorTest extends TestCase
{
    public function invalidExternalUrlProvider(): iterable
    {
        yield 'empty url' => [ 'url' => '', ];
        yield 'just a string' => [ 'url' => 'foo' ];
        yield 'non-http url' => [ 'url' => 'ftp://localhost' ];
        yield 'url that points to a file' => [ 'url' => 'file:///etc/passwd' ];
    }

    public function validExternalUrlProvider(): iterable
    {
        yield 'sample url accessible via http' => [ 'url' => 'http://foo.bar' ];
        yield 'sample url accessible via https' => [ 'url' => 'https://foo.bar' ];
        yield 'https + IPv6' => [ 'url' => 'https://[c6a1:8f54:0270:e5cd:f3b7:2af4:4788:dbcd]' ];
    }

    public function internalUrlProvider(): iterable
    {
        yield 'localhost over https' => ['url' => 'https://localhost'];
        yield 'loopback IPv4 over http' => [ 'url' => 'http://127.0.0.1' ];
        yield 'loopback IPv6 over http' => [ 'url' => 'http://[::1]' ];
        yield 'private IPv4 address over http' => [ 'url' => 'http://192.168.1.1' ];
        yield 'private IPv4 address over https' => [ 'url' => 'https://192.168.1.1' ];
        yield 'local IPv6 prefix address over https' => ['url' => 'https://[fe80::]'];
        yield 'local IPv6 address with dropped leading zeros over http' => ['url' => 'http://[fe80:0:0:0:204:61ff:fe9d:f156]'];
    }

    /**
     * @test
     * @dataProvider invalidExternalUrlProvider
     */
    public function shouldRejectInvalidExternalUrl(string $url): void
    {
        $validator = new UrlValidator();
        $actual = $validator->isValid($url);
        $this->assertFalse($actual);
    }

    /**
     * @test
     * @dataProvider validExternalUrlProvider
     */
    public function shouldAcceptValidExternalUrl(string $url): void
    {
        $validator = new UrlValidator();
        $actual = $validator->isValid($url);
        $this->assertTrue($actual);
    }

    /**
     * @test
     * @dataProvider internalUrlProvider
     */
    public function shouldAcceptInternalUrlsByDefault(string $url): void
    {
        $validator = new UrlValidator();
        $actual = $validator->isValid($url);
        $this->assertTrue($actual);
    }

    /**
     * @test
     * @dataProvider internalUrlProvider
     */
    public function shouldRejectInternalUrlsIfSpecifiedInConstructor(string $url): void
    {
        $validator = new UrlValidator(true);
        $actual = $validator->isValid($url);
        $this->assertFalse($actual);
    }
}
