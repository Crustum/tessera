<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Manifest;

use Cake\TestSuite\TestCase;
use Crustum\PluginManifest\Manifest\ManifestResolver;
use Crustum\Tessera\Manifest\TesseraManifest;
use Crustum\Tessera\TesseraPlugin;

/**
 * Covers the standalone manifest provider: identical operations to the
 * former plugin-class manifest, discoverable without the interface.
 */
class TesseraManifestTest extends TestCase
{
    /**
     * The provider emits migrations, config, bootstrap and star operations.
     *
     * @return void
     */
    public function testManifestOperations(): void
    {
        $ops = TesseraManifest::manifest();
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
            TesseraManifest::class,
            ManifestResolver::providerClass(TesseraPlugin::class),
        );
        $this->assertSame(TesseraManifest::manifest(), ManifestResolver::manifestFor(TesseraPlugin::class));
    }

    /**
     * The plugin class itself carries no manifest contract.
     *
     * @return void
     */
    public function testPluginClassHasNoManifestContract(): void
    {
        $this->assertFalse(method_exists(TesseraPlugin::class, 'manifest'));
    }
}
