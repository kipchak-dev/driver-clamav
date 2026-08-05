<?php

declare(strict_types=1);

namespace Kipchak\Driver\ClamAV;

/**
 * A malware scanner. Implemented by the clamd Client, but declared as an
 * interface so consumers can inject fakes in tests without a running clamd.
 */
interface ScannerInterface
{
    /**
     * Scan a blob of bytes. Throws Exception\ConnectionException when clamd
     * cannot be reached — callers decide whether that fails open or closed.
     */
    public function scan(string $bytes): ScanResult;

    /**
     * True when clamd answers a PING with PONG.
     */
    public function ping(): bool;
}
