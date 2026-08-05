<?php

declare(strict_types=1);

namespace Kipchak\Driver\ClamAV;

/**
 * The outcome of a single clamd scan. Clean when clamd returned `OK`, infected
 * when it returned `<signature> FOUND` (the signature name is preserved).
 */
final class ScanResult
{
    private function __construct(
        public readonly bool $clean,
        public readonly ?string $signature,
    ) {
    }

    public static function clean(): self
    {
        return new self(true, null);
    }

    public static function infected(string $signature): self
    {
        return new self(false, $signature);
    }

    public function isClean(): bool
    {
        return $this->clean;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }
}
