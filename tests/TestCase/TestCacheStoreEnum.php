<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Test\TestCase;

/**
 * Test cache store backed enum for Schedule::useCache().
 */
enum TestCacheStoreEnum: string
{
    case Custom = 'custom_store';
}
