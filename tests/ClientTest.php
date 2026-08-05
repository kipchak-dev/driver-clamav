<?php

declare(strict_types=1);

namespace Kipchak\Driver\ClamAV\Tests;

use Kipchak\Driver\ClamAV\Client;
use Kipchak\Driver\ClamAV\Exception\ConnectionException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the INSTREAM request framing and the clamd response parsing without
 * a running daemon, by overriding the protected transact() with canned replies.
 */
final class ClientTest extends TestCase
{
    private function client(string $cannedResponse, ?callable $captureRequest = null): Client
    {
        return new class('clam.test', 3310, 5.0, $cannedResponse, $captureRequest) extends Client {
            public function __construct(
                string $host,
                int $port,
                float $timeout,
                private string $canned,
                private $capture,
            ) {
                parent::__construct($host, $port, $timeout);
            }

            protected function transact(string $request): string
            {
                if ($this->capture !== null) {
                    ($this->capture)($request);
                }
                return $this->canned;
            }
        };
    }

    public function testCleanResponse(): void
    {
        $result = $this->client("stream: OK\0")->scan('harmless bytes');

        $this->assertTrue($result->isClean());
        $this->assertNull($result->getSignature());
    }

    public function testInfectedResponsePreservesSignature(): void
    {
        $result = $this->client("stream: Eicar-Test-Signature FOUND\0")->scan('x5O!P%...');

        $this->assertFalse($result->isClean());
        $this->assertSame('Eicar-Test-Signature', $result->getSignature());
    }

    public function testInstreamFramingWrapsThePayload(): void
    {
        $captured = '';
        $this->client("stream: OK\0", function (string $req) use (&$captured) { $captured = $req; })
            ->scan('abc');

        // Command header, a 4-byte length (0x00000003) + payload, then a zero terminator.
        $this->assertStringStartsWith("zINSTREAM\0", $captured);
        $this->assertStringContainsString(pack('N', 3) . 'abc', $captured);
        $this->assertStringEndsWith(pack('N', 0), $captured);
    }

    public function testUnexpectedResponseThrows(): void
    {
        $this->expectException(ConnectionException::class);
        $this->client("stream: something ERROR\0")->scan('data');
    }

    public function testPingReturnsTrueOnPong(): void
    {
        $this->assertTrue($this->client("PONG\0")->ping());
    }

    public function testPingReturnsFalseWhenUnreachable(): void
    {
        $client = new class('clam.test', 3310, 5.0) extends Client {
            protected function transact(string $request): string
            {
                throw new ConnectionException('unreachable');
            }
        };

        $this->assertFalse($client->ping());
    }
}
