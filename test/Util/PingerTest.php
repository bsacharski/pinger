<?php

namespace Sandbox\Test\Util;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sandbox\Util\Pinger;

class PingerTest extends TestCase
{
    private const OK_RESPONSE = 'HTTP/1.1 200 OK';
    private const NOT_FOUND_RESPONSE = 'HTTP/1.1 404 NOT FOUND';

    private function stubPinger($response)
    {
        $requestStub = new \HTTP_Request2_Adapter_Mock();
        $requestStub->addResponse($response);
        return new Pinger(new NullLogger(), $requestStub);
    }

    /**
     * Prepares a Pinger instance that will always reply successfully to a GET request
     */
    private function stubPingerWithSuccessCalls()
    {
        return $this->stubPinger(self::OK_RESPONSE);
    }

    private function stubPingerWithFailedCalls()
    {
        return $this->stubPinger(self::NOT_FOUND_RESPONSE);
    }

    public function testValidHttpUrl()
    {
        $url = 'http://somevalidurl.com';
        $pinger = $this->stubPingerWithSuccessCalls();
        $this->assertTrue($pinger->check($url), "Pinger should accept calls to {$url}");
    }

    public function testHttpNotFound()
    {
        $url = 'http://somenonexistingurl.com';
        $pinger = $this->stubPingerWithFailedCalls();
        $this->assertFalse($pinger->check($url), "Pinger should receive 404 when calling {$url}");
    }

    public function testHttpsNotFound()
    {
        $url = 'https://somenonexistingurl.com';
        $pinger = $this->stubPingerWithFailedCalls();
        $this->assertFalse($pinger->check($url), "Pinger should receive 404 when calling {$url}");
    }

    public function testRedirectToHttpLocalhost()
    {
        $url = 'http://somerogueurl.com';
        $pinger = $this->stubPinger("HTTP/1.1 301 Moved Permanently\nLocation: http://localhost");
        $this->assertFalse($pinger->check($url), 'Pinger should not follow redirect calls to private/reserved IPs');
    }

    public function testRedirectToHttpGoogle()
    {
        $startUrl = 'http://redirectme.com/test';
        $requestMock = new \HTTP_Request2_Adapter_Mock();
        $requestMock->addResponse("HTTP/1.1 301 Moved Permanently\nLocation: http://google.pl", $startUrl);
        $requestMock->addResponse(self::OK_RESPONSE);

        $pinger = new Pinger(new NullLogger(), $requestMock);
        $this->assertTrue($pinger->check($startUrl), 'Pinger should follow redirect calls to valid hosts');
    }
}
