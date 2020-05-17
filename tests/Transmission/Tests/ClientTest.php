<?php

namespace Transmission\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Transmission\Client;

class ClientTest extends \PHPUnit\Framework\TestCase
{
    protected $client;

    protected $curlMock;

    protected function setUp(): void
    {
        $this->client = new Client();

        $this->curlMock = $this->getMockBuilder("Buzz\Client\Curl")
                               ->setConstructorArgs([new Psr17Factory()])
                               ->getMock();
        $this->client->setClient($this->curlMock);
    }

    public function testShouldHaveDefaultHost()
    {
        $this->assertEquals('localhost', $this->client->getHost());
    }

    public function testSetHost()
    {
        $expected = 'domain.com';

        $this->client->setHost($expected);
        $this->assertEquals($expected, $this->client->getHost());
    }

    public function testShouldHaveDefaultPort()
    {
        $this->assertEquals(9091, $this->client->getPort());
    }

    public function testSetPort()
    {
        $expected = 80;

        $this->client->setPort($expected);
        $this->assertEquals($expected, $this->client->getPort());
    }

    public function testSetPath()
    {
        $expected = '/foo/bar';

        $this->client->setPath($expected);
        $this->assertEquals($expected, $this->client->getPath());
    }

    public function testShouldHaveNoTokenOnInstantiation()
    {
        $this->assertEmpty($this->client->getToken());
    }

    public function testShouldHaveDefaultClient()
    {
        $this->assertInstanceOf('Buzz\Client\Curl', $this->client->getClient());
    }

    public function testShouldGenerateDefaultUrl()
    {
        $this->assertEquals('http://localhost:9091', $this->client->getUrl());
    }

    public function testShouldMakeApiCall()
    {
        $this->curlMock->expects($this->once())
            ->method('sendRequest')
            ->with($this->isInstanceOf('Nyholm\Psr7\Request'))
            ->willReturn(new \Nyholm\Psr7\Response(200, [], '{}'));

        $response = $this->client->call('foo', ['bar' => 'baz']);

        $this->assertInstanceOf('stdClass', $response);
    }

    public function testShouldAuthenticate()
    {
        $this->curlMock->expects($this->once())
            ->method('sendRequest')
            ->with($this->isInstanceOf('Nyholm\Psr7\Request'))
            ->willReturn(new \Nyholm\Psr7\Response(200, [], '{}'));

        $this->client->authenticate('foo', 'bar');
        $response = $this->client->call('foo', ['bar' => 'baz']);

        $this->assertInstanceOf('stdClass', $response);
    }

    public function testShouldThrowExceptionOnExceptionDuringApiCall()
    {
        $this->curlMock->method('sendRequest')
                       ->will($this->throwException(new \Exception()));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not connect to Transmission');

        $this->client->call('foo', []);
    }

    public function testShouldThrowExceptionOnUnexpectedStatusCode()
    {
        $this->curlMock->expects($this->once())
            ->method('sendRequest')
            ->willReturn(new \Nyholm\Psr7\Response(500));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response received from Transmission');

        $this->client->call('foo', []);
    }

    public function testShouldThrowExceptionOnAccessDenied()
    {
        $this->curlMock->expects($this->once())
            ->method('sendRequest')
            ->willReturn(new \Nyholm\Psr7\Response(401));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access to Transmission requires authentication');
        $this->client->call('foo', []);
    }

    public function testShouldHandle409ResponseWhenMakingAnApiCall()
    {
        $this->curlMock->expects($this->at(0))
            ->method('sendRequest')
            ->willReturn(new \Nyholm\Psr7\Response(409, ['X-Transmission-Session-Id' => 'foo']));

        $this->curlMock->expects($this->at(1))
            ->method('sendRequest')
            ->willReturn(new \Nyholm\Psr7\Response(200, [], '{}'));

        $this->client->call('foo', []);
    }
}
