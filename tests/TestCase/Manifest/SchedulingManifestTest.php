<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Test\TestCase\Manifest;

use Cake\TestSuite\TestCase;
use Crustum\PluginManifest\Manifest\ManifestResolver;
use Crustum\Scheduling\Manifest\SchedulingManifest;
use Crustum\Scheduling\SchedulingPlugin;

/**
 * Covers the standalone manifest provider: identical operations to the
 * former plugin-class manifest, discoverable without the interface.
 */
class SchedulingManifestTest extends TestCase
{
    /**
     * The provider emits migrations, config, bootstrap and star operations.
     *
     * @return void
     */
    public function testManifestOperations(): void
    {
        $ops = SchedulingManifest::manifest();
        $tags = array_column($ops, 'tag');

        $this->assertContains('migrations', $tags);
        $this->assertContains('config', $tags);
        $this->assertContains('bootstrap', $tags);
        $this->assertContains('star-repo', $tags);
    }

    /**
     * The resolver finds the provider for the plugin class.
     *
     * @return void
     */
    public function testResolverFindsProvider(): void
    {
        $this->assertSame(
            SchedulingManifest::class,
            ManifestResolver::providerClass(SchedulingPlugin::class),
        );
        $this->assertSame(SchedulingManifest::manifest(), ManifestResolver::manifestFor(SchedulingPlugin::class));
    }

    /**
     * The plugin class itself carries no manifest contract.
     *
     * @return void
     */
    public function testPluginClassHasNoManifestContract(): void
    {
        $this->assertFalse(method_exists(SchedulingPlugin::class, 'manifest'));
    }
}
