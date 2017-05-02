<?php
/**
 * Created by PhpStorm.
 * User: bsa
 * Date: 02.05.17
 * Time: 12:11
 */

namespace Sandbox\Test;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sandbox\Util\Pinger;

class PingerTest extends TestCase
{
    const OK_RESPONSE = 'HTTP/1.1 200 OK';
    const NOT_FOUND_RESPONSE = 'HTTP/1.1 404 NOT FOUND';

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

    public function testHttpLocalhost()
    {
        $url = 'http://localhost:8080';
        $pinger = $this->stubPingerWithSuccessCalls();
        $this->assertFalse($pinger->check($url), "Pinger should reject calls to {$url}");
    }

    public function testHttpsLocalhost()
    {
        $url = 'https://localhost:8080';
        $pinger = $this->stubPingerWithSuccessCalls();
        $this->assertFalse($pinger->check($url), "Pinger should reject calls to {$url}");
    }

    public function testHttpPrivateIpAddress()
    {
        $url = 'http://192.168.1.1';
        $pinger = $this->stubPingerWithSuccessCalls();
        $this->assertFalse($pinger->check($url), "Pinger should reject calls to {$url}");
    }

    public function testHttpsPrivateIpAddress()
    {
        $url = 'https://192.168.1.1';
        $pinger = $this->stubPingerWithSuccessCalls();
        $this->assertFalse($pinger->check($url), "Pinger should reject calls to {$url}");
    }

    public function testValidHttpUrl()
    {
        $url = 'http://somevalidurl.com';
        $pinger = $this->stubPingerWithSuccessCalls();
        $this->assertTrue($pinger->check($url), "Pinger should accept calls to {$url}");
    }

    public function testValidHttpsUrl()
    {
        $url = 'https://somevalidurl.com';
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

    public function testValidFileUrl()
    {
        $url = 'file:///etc/passwd';
        $pinger = $this->stubPingerWithSuccessCalls();
        $this->assertFalse($pinger->check($url), "Pinger should reject calls to non http/https protocols");
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

    public function testFullHttpIPv6Address()
    {
        $url = 'http://[2001:0db8:0000:0000:0000:0000:1428:57ab]';
        $pinger = $this->stubPingerWithSuccessCalls();
        $this->assertFalse($pinger->check($url), "Pinger should reject IPv6 address {$url}");
    }

    // Uncomment tests below if you have an idea on how to process IPv6 address. I dunno.
//    public function testHttpIPv6LoopbackAddress()
//    {
//        $url = 'http://[::1/128]';
//        $pinger = $this->stubPingerWithSuccessCalls();
//        $this->assertFalse($pinger->check($url), "Pinger should reject IPv6 address {$url}");
//    }
//
//    public function testHttpIPv6AddressWithPrefix()
//    {
//        $url = 'http://[2001:db8:a0b:12f0::1/64]';
//        $pinger = $this->stubPingerWithSuccessCalls();
//        $this->assertFalse($pinger->check($url), "Pinger should reject IPv6 address {$url}");
//    }
}
