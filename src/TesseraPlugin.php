<?php
declare(strict_types=1);

namespace Crustum\Tessera;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Routing\RouteBuilder;
use Crustum\PluginManifest\Manifest\ManifestInterface;
use Crustum\PluginManifest\Manifest\ManifestTrait;
use Crustum\Tessera\Command\ClientCommand;
use Crustum\Tessera\Command\HashCommand;
use Crustum\Tessera\Command\KeysCommand;
use Crustum\Tessera\Command\PurgeCommand;
use Crustum\Tessera\ServiceProvider\TesseraServiceProvider;

/**
 * CakePHP plugin bootstrap for Crustum Tessera.
 *
 * @uses \Crustum\PluginManifest\Manifest\ManifestTrait
 */
class TesseraPlugin extends BasePlugin implements ManifestInterface
{
    use ManifestTrait;

    /**
     * Load plugin bootstrap logic.
     *
     * Host app loads `config/tessera.php` via `Configure::load('tessera', 'default')`.
     * This plugin reads Configure; it does not load config files itself.
     *
     * @param \Cake\Core\PluginApplicationInterface $app Host application
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        if (Configure::read('debug') !== true) {
            Tessera::allowActingAs(false);
            Tessera::clearActingAs();
        }
    }

    /**
     * Register Tessera console commands.
     *
     * @param \Cake\Console\CommandCollection $commands Command collection
     * @return \Cake\Console\CommandCollection
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands = parent::console($commands);
        $commands->add(KeysCommand::defaultName(), KeysCommand::class);
        $commands->add(HashCommand::defaultName(), HashCommand::class);
        $commands->add(PurgeCommand::defaultName(), PurgeCommand::class);
        $commands->add(ClientCommand::defaultName(), ClientCommand::class);

        return $commands;
    }

    /**
     * Register OAuth authorize/token (and optional device) routes.
     *
     * @param \Cake\Routing\RouteBuilder $routes Route builder
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        if (!Tessera::$registersRoutes) {
            return;
        }

        $path = '/' . trim((string)(Configure::read('Tessera.path') ?? 'oauth'), '/');

        $routes->plugin(
            $this->getName(),
            ['path' => $path],
            function (RouteBuilder $builder): void {
                $builder->post(
                    '/token',
                    ['controller' => 'AccessToken', 'action' => 'issueToken'],
                    'tessera:token',
                );
                $builder->get(
                    '/authorize',
                    ['controller' => 'Authorization', 'action' => 'authorize'],
                    'tessera:authorizations.authorize',
                );
                $builder->post(
                    '/authorize',
                    ['controller' => 'ApproveAuthorization', 'action' => 'approve'],
                    'tessera:authorizations.approve',
                );
                $builder->delete(
                    '/authorize',
                    ['controller' => 'DenyAuthorization', 'action' => 'deny'],
                    'tessera:authorizations.deny',
                );
                $builder->post(
                    '/token/refresh',
                    ['controller' => 'TransientToken', 'action' => 'refresh'],
                    'tessera:token.refresh',
                );

                if (Tessera::$deviceCodeGrantEnabled) {
                    $builder->get(
                        '/device',
                        ['controller' => 'DeviceUserCode', 'action' => 'index'],
                        'tessera:device',
                    );
                    $builder->post(
                        '/device/code',
                        ['controller' => 'DeviceCode', 'action' => 'index'],
                        'tessera:device.code',
                    );
                    $builder->get(
                        '/device/authorize',
                        ['controller' => 'DeviceAuthorization', 'action' => 'index'],
                        'tessera:device.authorizations.authorize',
                    );
                    $builder->post(
                        '/device/authorize',
                        ['controller' => 'ApproveDeviceAuthorization', 'action' => 'index'],
                        'tessera:device.authorizations.approve',
                    );
                    $builder->delete(
                        '/device/authorize',
                        ['controller' => 'DenyDeviceAuthorization', 'action' => 'index'],
                        'tessera:device.authorizations.deny',
                    );
                }
            },
        );
    }

    /**
     * Attach Tessera DI via Cake ServiceProvider.
     *
     * @param \Cake\Core\ContainerInterface $container DI container
     * @return void
     */
    public function services(ContainerInterface $container): void
    {
        $container->addServiceProvider(new TesseraServiceProvider());
    }

    /**
     * Plugin install assets (config + migrations) via crustum/plugin-manifest.
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
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'tessera.php',
                CONFIG . 'tessera.php',
                false,
            ),
            static::manifestBootstrapAppend(
                "if (file_exists(CONFIG . 'tessera.php')) {\n    Configure::load('tessera', 'default');\n}",
                '// Tessera Plugin Configuration',
            ),
            static::manifestStarRepo('Crustum/Tessera'),
        );
    }
}
