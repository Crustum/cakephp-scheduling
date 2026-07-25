<?php
declare(strict_types=1);

namespace Crustum\Scheduling;

use Cake\Console\CommandCollection;
use Cake\Console\CommandFactoryInterface;
use Cake\Core\BasePlugin;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Event\EventManager;
use Crustum\PluginManifest\Manifest\ManifestInterface;
use Crustum\PluginManifest\Manifest\ManifestTrait;
use Crustum\Scheduling\Command\ScheduleClearCacheCommand;
use Crustum\Scheduling\Command\ScheduleFinishCommand;
use Crustum\Scheduling\Command\ScheduleInterruptCommand;
use Crustum\Scheduling\Command\ScheduleListCommand;
use Crustum\Scheduling\Command\ScheduleMonitorListCommand;
use Crustum\Scheduling\Command\ScheduleMonitorPruneCommand;
use Crustum\Scheduling\Command\ScheduleMonitorSyncCommand;
use Crustum\Scheduling\Command\SchedulePauseCommand;
use Crustum\Scheduling\Command\ScheduleResumeCommand;
use Crustum\Scheduling\Command\ScheduleRunCommand;
use Crustum\Scheduling\Command\ScheduleTestCommand;
use Crustum\Scheduling\Command\ScheduleWorkCommand;
use Crustum\Scheduling\Listener\ScheduleMonitorListener;

/**
 * Scheduling Plugin
 *
 * CakePHP Scheduler plugin.
 * Provides simplified cron management with second-based scheduling capabilities.
 *
 * @uses \Crustum\PluginManifest\Manifest\ManifestTrait
 */
class SchedulingPlugin extends BasePlugin implements ManifestInterface
{
    use ManifestTrait;

    /**
     * Load all plugin components and bootstrap
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        EventManager::instance()->on(new ScheduleMonitorListener());
    }

    /**
     * Add commands for the plugin.
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to update.
     * @return \Cake\Console\CommandCollection
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands = parent::console($commands);

        $commands->add('schedule run', ScheduleRunCommand::class);
        $commands->add('schedule work', ScheduleWorkCommand::class);
        $commands->add('schedule clear', ScheduleClearCacheCommand::class);
        $commands->add('schedule finish', ScheduleFinishCommand::class);
        $commands->add('schedule test', ScheduleTestCommand::class);
        $commands->add('schedule list', ScheduleListCommand::class);
        $commands->add('schedule pause', SchedulePauseCommand::class);
        $commands->add('schedule resume', ScheduleResumeCommand::class);
        $commands->add('schedule interrupt', ScheduleInterruptCommand::class);

        $commands->add('schedule monitor sync', ScheduleMonitorSyncCommand::class);
        $commands->add('schedule monitor list', ScheduleMonitorListCommand::class);
        $commands->add('schedule monitor prune', ScheduleMonitorPruneCommand::class);

        return $commands;
    }

    /**
     * Register application container services.
     *
     * @param \Cake\Core\ContainerInterface $container The Container to update.
     * @return void
     * @link https://book.cakephp.org/5/en/development/dependency-injection.html#dependency-injection
     */
    public function services(ContainerInterface $container): void
    {
        $container->addShared(Schedule::class);

        $container
            ->add(ScheduleListCommand::class)
            ->addArgument(Schedule::class)
            ->addArgument(CommandFactoryInterface::class);

        $container
            ->add(ScheduleRunCommand::class)
            ->addArgument(Schedule::class)
            ->addArgument(CommandFactoryInterface::class);

        $container
            ->add(ScheduleWorkCommand::class)
            ->addArgument(Schedule::class)
            ->addArgument(CommandFactoryInterface::class);

        $container
            ->add(ScheduleTestCommand::class)
            ->addArgument(Schedule::class)
            ->addArgument(CommandFactoryInterface::class);

        $container
            ->add(ScheduleClearCacheCommand::class)
            ->addArgument(Schedule::class)
            ->addArgument(CommandFactoryInterface::class);

        $container
            ->add(ScheduleFinishCommand::class)
            ->addArgument(Schedule::class)
            ->addArgument(CommandFactoryInterface::class);

        $container
            ->add(SchedulePauseCommand::class)
            ->addArgument(Schedule::class)
            ->addArgument(CommandFactoryInterface::class);

        $container
            ->add(ScheduleResumeCommand::class)
            ->addArgument(Schedule::class)
            ->addArgument(CommandFactoryInterface::class);

        $container
            ->add(ScheduleInterruptCommand::class)
            ->addArgument(Schedule::class)
            ->addArgument(CommandFactoryInterface::class);

        $container
            ->add(ScheduleMonitorSyncCommand::class)
            ->addArgument(Schedule::class)
            ->addArgument(CommandFactoryInterface::class);

        $container
            ->add(ScheduleMonitorListCommand::class)
            ->addArgument(Schedule::class)
            ->addArgument(CommandFactoryInterface::class);
    }

    /**
     * Get the manifest for the plugin.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function manifest(): array
    {
        $pluginPath = dirname(__DIR__);

        return array_merge(
            static::manifestMigrations(
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'Migrations',
            ),
            static::manifestConfig(
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'scheduling.php',
                CONFIG . 'scheduling.php',
                false,
            ),
            static::manifestBootstrapAppend(
                "if (file_exists(CONFIG . 'scheduling.php')) {\n    Configure::load('scheduling', 'default');\n}",
                '// Scheduling Plugin Configuration',
            ),
            static::manifestStarRepo('crustum/cakephp-scheduling'),
        );
    }
}
