<?php
declare(strict_types=1);

namespace Crustum\Scheduling;

/**
 * Cache Aware Interface
 *
 * Defines the contract for mutex implementations that can specify cache stores.
 */
interface CacheAwareInterface
{
    /**
     * Specify the cache store that should be used.
     *
     * @param string $store The cache store name
     * @return $this
     */
    public function useStore(string $store): self;
}
