<?php

declare(strict_types=1);

namespace Ihasan\DualAgent\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void write(array $record)
 * @method static void writeNow(array $record) 
 * @method static void ping()
 * @method static void shouldDigest(bool $bool)
 * @method static void digest()
 * @method static void flush()
 * @method static bool isEnabled()
 * @method static int getBufferSize()
 * @method static int getBufferCount()
 */
class DualAgent extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'dual-agent';
    }
}