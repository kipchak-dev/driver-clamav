<?php

declare(strict_types=1);

namespace Kipchak\Driver\ClamAV;

use Kipchak\Driver\ClamAV\Exception\ConnectionException;

/**
 * A minimal clamd client speaking the INSTREAM protocol over TCP.
 *
 * INSTREAM sends the command `zINSTREAM\0`, then a sequence of chunks each
 * prefixed with a 4-byte network-order length, terminated by a zero-length
 * chunk. clamd replies `stream: OK\0` or `stream: <signature> FOUND\0`.
 *
 * The low-level socket exchange lives in transact(), which is protected so
 * tests can subclass and return canned clamd responses without a real daemon.
 */
class Client implements ScannerInterface
{
    /** Max bytes per INSTREAM chunk. Must stay below clamd StreamMaxLength. */
    private const CHUNK_SIZE = 8192;

    public function __construct(
        private readonly string $host,
        private readonly int $port = 3310,
        private readonly float $timeout = 30.0,
    ) {
    }

    public function scan(string $bytes): ScanResult
    {
        $response = $this->transact($this->buildInstream($bytes));

        return $this->parseScanResponse($response);
    }

    public function ping(): bool
    {
        try {
            return str_starts_with(trim($this->transact("zPING\0"), "\0\n "), 'PONG');
        } catch (ConnectionException) {
            return false;
        }
    }

    private function buildInstream(string $bytes): string
    {
        $request = "zINSTREAM\0";
        $offset = 0;
        $length = strlen($bytes);
        while ($offset < $length) {
            $chunk = substr($bytes, $offset, self::CHUNK_SIZE);
            $request .= pack('N', strlen($chunk)) . $chunk;
            $offset += self::CHUNK_SIZE;
        }
        // Zero-length chunk terminates the stream.
        $request .= pack('N', 0);

        return $request;
    }

    private function parseScanResponse(string $response): ScanResult
    {
        $clean = trim($response, "\0\n\r ");

        if (str_ends_with($clean, 'OK')) {
            return ScanResult::clean();
        }

        if (str_ends_with($clean, 'FOUND')) {
            // Format: "stream: <signature> FOUND".
            $signature = trim(substr($clean, (int) strpos($clean, ':') + 1));
            $signature = (string) preg_replace('/\s+FOUND$/', '', $signature);

            return ScanResult::infected($signature !== '' ? $signature : 'unknown');
        }

        throw new ConnectionException('Unexpected clamd response: ' . $clean);
    }

    /**
     * Perform one request/response exchange with clamd. Overridable in tests.
     */
    protected function transact(string $request): string
    {
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            sprintf('tcp://%s:%d', $this->host, $this->port),
            $errno,
            $errstr,
            $this->timeout,
        );

        if ($socket === false) {
            throw new ConnectionException(sprintf(
                'Cannot connect to clamd at %s:%d (%d %s)',
                $this->host,
                $this->port,
                $errno,
                $errstr !== '' ? $errstr : 'unknown error',
            ));
        }

        stream_set_timeout($socket, (int) $this->timeout);

        try {
            if (@fwrite($socket, $request) === false) {
                throw new ConnectionException('Failed writing to clamd socket');
            }

            $response = '';
            while (!feof($socket)) {
                $buffer = fread($socket, 4096);
                if ($buffer === false) {
                    break;
                }
                $response .= $buffer;
                // clamd null-terminates its reply — stop as soon as we have it.
                if (str_contains($response, "\0")) {
                    break;
                }
                $meta = stream_get_meta_data($socket);
                if ($meta['timed_out'] ?? false) {
                    throw new ConnectionException('Timed out reading from clamd');
                }
            }

            return $response;
        } finally {
            fclose($socket);
        }
    }
}
