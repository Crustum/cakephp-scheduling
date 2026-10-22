<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Manifest;

use Crustum\PluginManifest\Manifest\ManifestOps;
use Crustum\Scheduling\SchedulingPlugin;

/**
 * Standalone manifest provider for the Scheduling plugin.
 *
 * Plain class without interface or trait dependencies, so the plugin loads
 * and runs without `crustum/plugin-manifest` installed. Discovered by the
 * installer through the provider convention when the plugin class itself
 * provides no manifest. All package references stay inside method bodies,
 * which only execute when the installer (and therefore the package) exists.
 */
final class SchedulingManifest
{
    /**
     * Plugin install assets via crustum/plugin-manifest.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function manifest(): array
    {
        $pluginPath = dirname(__DIR__, 2);
        $plugin = ManifestOps::pluginName(SchedulingPlugin::class);

        return array_merge(
            ManifestOps::manifestMigrations(
                $plugin,
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'Migrations',
            ),
            ManifestOps::manifestConfig(
                $plugin,
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'scheduling.php',
                CONFIG . 'scheduling.php',
                false,
            ),
            ManifestOps::manifestBootstrapAppend(
                $plugin,
                "if (file_exists(CONFIG . 'scheduling.php')) {\n    Configure::load('scheduling', 'default');\n}",
                '// Scheduling Plugin Configuration',
            ),
            ManifestOps::manifestStarRepo($plugin, 'crustum/cakephp-scheduling'),
        );
    }
}
