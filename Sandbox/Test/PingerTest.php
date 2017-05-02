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

    /**
     * Prepares a Pinger instance that will always reply successfully to a GET request
     */
    private function stubPingerWithSuccessCalls()
    {
        $requestStub = new \HTTP_Request2_Adapter_Mock();
        $requestStub->addResponse(self::OK_RESPONSE);
        return new Pinger(new NullLogger(), $requestStub);
    }

    private function stubPingerWithFailedCalls()
    {
        $requestStub = new \HTTP_Request2_Adapter_Mock();
        $requestStub->addResponse(self::NOT_FOUND_RESPONSE);
        return new Pinger(new NullLogger(), $requestStub);
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
}
