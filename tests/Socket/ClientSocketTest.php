<?php

namespace Wrench\Socket;

use InvalidArgumentException;
use stdClass;
use TypeError;
use Wrench\Exception\SocketException;
use Wrench\Protocol\Rfc6455Protocol;
use Wrench\Test\ServerTestHelper;

class ClientSocketTest extends UriSocketBaseTest
{
    public function testConstructor(): void
    {
        $instance = self::getInstance('ws://localhost:8000');
        $this->assertInstanceOfClass($instance);

        $socket = null;

        $this->assertInstanceOfClass(
            new ClientSocket('ws://localhost/'),
            'ws:// scheme, default port'
        );

        $this->assertInstanceOfClass(
            new ClientSocket('ws://localhost/some-arbitrary-path'),
            'with path'
        );

        $this->assertInstanceOfClass(
            new ClientSocket('wss://localhost/test', []),
            'empty options'
        );

        $this->assertInstanceOfClass(
            new ClientSocket('ws://localhost:8000/foo'),
            'specified port'
        );
    }

    public function testOptions(): void
    {
        $socket = null;

        $this->assertInstanceOfClass(
            $socket = new ClientSocket(
                'ws://localhost:8000/foo',
                [
                    'timeout_connect' => 10,
                ]
            ),
            'connect timeout'
        );

        $this->assertInstanceOfClass(
            $socket = new ClientSocket(
                'ws://localhost:8000/foo',
                [
                    'timeout_socket' => 10,
                ]
            ),
            'socket timeout'
        );

        $this->assertInstanceOfClass(
            $socket = new ClientSocket(
                'ws://localhost:8000/foo',
                [
                    'protocol' => new Rfc6455Protocol(),
                ]
            ),
            'protocol'
        );
    }

    public function testProtocolTypeError(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ClientSocket(
            'ws://localhost:8000/foo',
            [
                'protocol' => new stdClass(),
            ]
        );
    }

    public function testConstructorUriEmpty(): void
    {
        $this->expectException(TypeError::class);

        new ClientSocket(null);
    }

    public function testConstructorUriInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ClientSocket('Bad argument');
    }

    public function testSendTooEarly(): void
    {
        $instance = self::getInstance('ws://localhost:8000');

        $this->expectException(SocketException::class);

        $instance->send('foo');
    }

    /**
     * Test the connect, send, receive method.
     */
    public function testConnect(): void
    {
        try {
            $helper = new ServerTestHelper();
            $helper->setUp();

            $instance = self::getInstance($helper->getConnectionString());
            $success = $instance->connect();

            self::assertTrue($success, 'Client socket can connect to test server');

            $sent = $instance->send("GET /echo HTTP/1.1\r
Host: localhost\r
Upgrade: websocket\r
Connection: Upgrade\r
Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r
Origin: http://localhost\r
Sec-WebSocket-Version: 13\r\n\r\n");
            self::assertNotEquals(false, $sent, 'Client socket can send to test server');

            $response = $instance->receive();
            self::assertStringStartsWith('HTTP', $response, 'Response looks like HTTP handshake response');
        } catch (\Exception $e) {
            $helper->tearDown();
            throw $e;
        }

        $helper->tearDown();
    }

    /**
     * Test the read data without blocking.
     */
    public function testReceiveNonBlocking(): void
    {
        try {
            $helper = new ServerTestHelper();
            $helper->setUp();

            $instance = self::getInstance($helper->getConnectionString());
            $success = $instance->connect();

            self::assertTrue($success, 'Client socket can connect to test server');

            $sent = $instance->send("GET /echo HTTP/1.1\r
Host: localhost\r
Upgrade: websocket\r
Connection: Upgrade\r
Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r
Origin: http://localhost\r
Sec-WebSocket-Version: 13\r\n\r\n");
            self::assertNotEquals(false, $sent, 'Client socket can send to test server');

            $response = $instance->receive(AbstractSocket::DEFAULT_RECEIVE_LENGTH);
            self::assertStringStartsWith('HTTP', $response, 'Response looks like HTTP handshake response');

            $response = $instance->receive(AbstractSocket::DEFAULT_RECEIVE_LENGTH, false);
            $this->assertEmpty($response, 'No more data for reading');

        } catch (\Exception $e) {
            $helper->tearDown();
            throw $e;
        }

        $helper->tearDown();
    }
}
