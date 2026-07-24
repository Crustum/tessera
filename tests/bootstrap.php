<?php
declare(strict_types=1);

$findRoot = function (): ?string {
    $root = dirname(__DIR__);
    if (is_dir($root . '/vendor/cakephp/cakephp')) {
        return $root;
    }

    $root = dirname(__DIR__, 2);
    if (is_dir($root . '/vendor/cakephp/cakephp')) {
        return $root;
    }

    $root = dirname(__DIR__, 3);
    if (is_dir($root . '/vendor/cakephp/cakephp')) {
        return $root;
    }

    return null;
};

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

define('ROOT', $findRoot());
define('APP_DIR', 'TestApp');
define('WEBROOT_DIR', 'webroot');
define('APP', ROOT . '/tests/TestApp/');
define('CONFIG', ROOT . '/tests/TestApp/config/');
define('WWW_ROOT', ROOT . DS . WEBROOT_DIR . DS);
define('TESTS', ROOT . DS . 'tests' . DS);
define('TMP', ROOT . DS . 'tmp' . DS);
define('LOGS', TMP . 'logs' . DS);
define('CACHE', TMP . 'cache' . DS);
define('CAKE_CORE_INCLUDE_PATH', ROOT . '/vendor/cakephp/cakephp');
define('CORE_PATH', CAKE_CORE_INCLUDE_PATH . DS);
define('CAKE', CORE_PATH . 'src' . DS);

require ROOT . '/vendor/cakephp/cakephp/src/functions.php';
require ROOT . '/vendor/autoload.php';

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Error\ErrorTrap;
use Cake\TestSuite\Fixture\SchemaLoader;
use Crustum\Tessera\Tessera;
use Crustum\Tessera\TesseraPlugin;

if (!class_exists(\App\Controller\AppController::class, false)) {
    class_alias(\TestApp\Controller\AppController::class, \App\Controller\AppController::class);
}

Configure::write('App', ['namespace' => 'TestApp']);
Configure::write('debug', true);

/**
 * Ensure a directory exists.
 *
 * @param string $path Directory path
 * @return void
 */
function ensureDirectoryExists(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

ensureDirectoryExists(TMP . 'cache/models');
ensureDirectoryExists(TMP . 'cache/persistent');
ensureDirectoryExists(TMP . 'cache/views');
ensureDirectoryExists(TMP . 'sessions');
ensureDirectoryExists(TMP . 'tests');
ensureDirectoryExists(LOGS);

$cache = [
    'default' => [
        'engine' => 'File',
        'path' => CACHE,
    ],
    '_cake_translations_' => [
        'className' => 'File',
        'prefix' => 'tessera_test_cake_core_',
        'path' => CACHE . 'persistent/',
        'serialize' => true,
        'duration' => '+10 seconds',
    ],
    '_cake_model_' => [
        'className' => 'File',
        'prefix' => 'tessera_test_cake_model_',
        'path' => CACHE . 'models/',
        'serialize' => 'File',
        'duration' => '+10 seconds',
    ],
];

Cache::setConfig($cache);
Configure::write('Session', [
    'defaults' => 'php',
]);

Configure::write('App.encoding', 'utf8');

if (!getenv('db_dsn')) {
    putenv('db_dsn=sqlite:///:memory:');
}

ConnectionManager::setConfig('test', [
    'url' => getenv('db_dsn'),
    'timezone' => 'UTC',
]);

ConnectionManager::alias('test', 'default');

Configure::write('Authentication', [
    'unauthenticatedRedirect' => false,
    'queryParam' => 'redirect',
]);

Configure::write('FixtureFactories', [
    'testFixtureNamespace' => 'Crustum\\Tessera\\Test\\Factory',
]);

Plugin::getCollection()->add(new TesseraPlugin());

Tessera::allowActingAs(true);

if (file_exists(dirname(__DIR__) . '/config/tessera.php')) {
    Configure::write(require dirname(__DIR__) . '/config/tessera.php');
}

$loader = new SchemaLoader();
$loader->loadInternalFile(TESTS . 'schema.php');

$error = [
    'errorLevel' => E_ALL,
    'skipLog' => [],
    'log' => true,
    'trace' => true,
    'ignoredDeprecationPaths' => [],
];
(new ErrorTrap($error))->register();

if (file_exists(CONFIG . 'bootstrap.php')) {
    require CONFIG . 'bootstrap.php';
}
