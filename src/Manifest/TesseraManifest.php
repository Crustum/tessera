<?php
declare(strict_types=1);

namespace Crustum\Tessera\Manifest;

use Crustum\PluginManifest\Manifest\ManifestOps;
use Crustum\Tessera\TesseraPlugin;

/**
 * Standalone manifest provider for the Tessera plugin.
 *
 * Plain class without interface or trait dependencies, so the plugin loads
 * and runs without `crustum/plugin-manifest` installed. Discovered by the
 * installer through the provider convention when the plugin class itself
 * provides no manifest. All package references stay inside method bodies,
 * which only execute when the installer (and therefore the package) exists.
 */
final class TesseraManifest
{
    /**
     * Plugin install assets (config + migrations) via crustum/plugin-manifest.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function manifest(): array
    {
        $pluginPath = dirname(__DIR__, 2);
        $plugin = ManifestOps::pluginName(TesseraPlugin::class);

        return array_merge(
            ManifestOps::manifestMigrations(
                $plugin,
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'Migrations',
            ),
            ManifestOps::manifestConfig(
                $plugin,
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'tessera.php',
                CONFIG . 'tessera.php',
                false,
            ),
            ManifestOps::manifestBootstrapAppend(
                $plugin,
                "if (file_exists(CONFIG . 'tessera.php')) {\n    Configure::load('tessera', 'default');\n}",
                '// Tessera Plugin Configuration',
            ),
            ManifestOps::manifestStarRepo($plugin, 'Crustum/Tessera'),
        );
    }
}
