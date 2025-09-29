<?php

declare(strict_types=1);

namespace Ihasan\DualAgent\Contracts;

interface CustomIngestContract
{
    public function write(array $record): void;

    public function writeNow(array $record): void;

    public function ping(): void;

    public function shouldDigest(bool $bool): void;

    public function digest(): void;

    public function flush(): void;

    public function isEnabled(): bool;

    public function getBufferSize(): int;

    public function getBufferCount(): int;
}