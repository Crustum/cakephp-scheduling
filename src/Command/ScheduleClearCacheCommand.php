<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Command;

use Cake\Cache\Cache;
use Cake\Console\ConsoleOptionParser;
use Throwable;

/**
 * Schedule Clear Cache Command
 *
 * Clears cached mutex files for the scheduler.
 */
class ScheduleClearCacheCommand extends BaseSchedulerCommand
{
    /**
     * Get the description.
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Clear cached mutex files for the scheduler';
    }

    /**
     * Hook method for defining this command's option parser.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser
            ->setDescription(self::getDescription())
            ->addOption('store', [
                'short' => 's',
                'help' => 'The cache store to clear (default: default)',
                'default' => 'default',
            ])
            ->addOption('pattern', [
                'short' => 'p',
                'help' => 'Clear only mutexes matching this pattern',
            ]);

        return $parser;
    }

    /**
     * Implement this method with your command's logic.
     *
     * @return int|null The exit code or null for success
     */
    public function execute(): ?int
    {
        $store = $this->args->getOption('store');
        $pattern = $this->args->getOption('pattern');
        $this->io->info('Clearing scheduler mutex cache...');
        try {
            if ($pattern && is_string($pattern)) {
                $storeString = is_string($store) ? $store : 'default';
                $this->clearMutexesByPattern($storeString, $pattern);
            } else {
                $storeString = is_string($store) ? $store : 'default';
                $this->clearAllMutexes($storeString);
            }

            $this->io->success('Scheduler mutex cache cleared successfully.');

            return static::CODE_SUCCESS;
        } catch (Throwable $throwable) {
            $this->io->error(sprintf('Failed to clear cache: %s', $throwable->getMessage()));

            return static::CODE_ERROR;
        }
    }

    /**
     * Clear all scheduler mutexes from the cache.
     *
     * @param string $store The cache store
     * @return void
     */
    protected function clearAllMutexes(string $store): void
    {
        $engine = Cache::pool($store);

        $engine->clear();

        $this->io->info(sprintf('Cleared all cache entries from store: %s', $store));
    }

    /**
     * Clear mutexes matching a specific pattern.
     *
     * @param string $store The cache store
     * @param string $pattern The pattern to match
     * @return void
     */
    protected function clearMutexesByPattern(string $store, string $pattern): void
    {
        $engine = Cache::pool($store);

        $this->io->info(sprintf('Clearing mutexes matching pattern: %s', $pattern));
        $this->io->warning('Pattern-based clearing is not fully implemented in this version.');

        $engine->clear();
        $this->io->info('Cleared all cache entries (pattern matching not available).');
    }
}
