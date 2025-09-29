<?php

declare(strict_types=1);

namespace Ihasan\DualAgent\Contracts;

use Ihasan\DualAgent\DTOs\MetricDto;

interface DataTransformerContract
{
    public function transform(array $record): MetricDto;

    public function supports(array $record): bool;
}