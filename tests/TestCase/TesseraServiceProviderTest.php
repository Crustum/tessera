<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase;

use Cake\Core\Configure;
use Cake\Core\Container;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use Crustum\Tessera\ApiTokenCookieFactory;
use Crustum\Tessera\Bridge\ClientRepository as BridgeClientRepository;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\PersonalAccessTokenFactory;
use Crustum\Tessera\ServiceProvider\TesseraServiceProvider;
use Crustum\Tessera\Tessera;
use Crustum\Tessera\TesseraPlugin;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use OpenSSLAsymmetricKey;

/**
 * TesseraServiceProvider DI and crypt-key wiring tests.
 */
class TesseraServiceProviderTest extends TestCase
{
    /**
     * @var string
     */
    protected string $keysPath;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Security::setSalt(str_repeat('b', 32));
        $this->keysPath = TESTS . 'keys';
        if (!is_dir($this->keysPath)) {
            mkdir($this->keysPath, 0777, true);
        }

        Tessera::loadKeysFrom($this->keysPath);
        Configure::write('Tessera.private_key');
        Configure::write('Tessera.public_key');
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $privateKey = $this->keysPath . DIRECTORY_SEPARATOR . 'oauth-private.key';
        $publicKey = $this->keysPath . DIRECTORY_SEPARATOR . 'oauth-public.key';
        if (is_file($privateKey)) {
            unlink($privateKey);
        }

        if (is_file($publicKey)) {
            unlink($publicKey);
        }

        Configure::delete('Tessera.private_key');
        Configure::delete('Tessera.public_key');

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testCanUseCryptoKeysFromConfig(): void
    {
        $privateKey = openssl_pkey_new();
        $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $privateKey);

        openssl_pkey_export($privateKey, $privateKeyString);
        Configure::write('Tessera.private_key', $privateKeyString);

        $provider = new TesseraServiceProvider();
        $cryptKey = (fn(): \League\OAuth2\Server\CryptKey => $this->makeCryptKey('private'))->call($provider);

        $this->assertSame($privateKeyString, $cryptKey->getKeyContents());
    }

    /**
     * @return void
     */
    public function testCanUseCryptoKeysFromLocalDisk(): void
    {
        $privateKey = openssl_pkey_new();
        $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $privateKey);

        $keyFile = $this->keysPath . DIRECTORY_SEPARATOR . 'oauth-private.key';
        openssl_pkey_export_to_file($privateKey, $keyFile);
        openssl_pkey_export($privateKey, $privateKeyString);
        chmod($keyFile, 0600);

        Configure::write('Tessera.private_key');

        $provider = new TesseraServiceProvider();
        $cryptKey = (fn(): \League\OAuth2\Server\CryptKey => $this->makeCryptKey('private'))->call($provider);

        $this->assertSame($privateKeyString, file_get_contents($cryptKey->getKeyPath()));
    }

    /**
     * @return void
     */
    public function testPluginRegistersServiceProviderAndResolvesServers(): void
    {
        $this->writeKeyPair();

        $container = new Container();
        (new TesseraPlugin())->services($container);

        $this->assertInstanceOf(ClientRepository::class, $container->get(ClientRepository::class));
        $this->assertInstanceOf(BridgeClientRepository::class, $container->get(BridgeClientRepository::class));
        $this->assertInstanceOf(AuthorizationServer::class, $container->get(AuthorizationServer::class));
        $this->assertInstanceOf(ResourceServer::class, $container->get(ResourceServer::class));
        $this->assertInstanceOf(PersonalAccessTokenFactory::class, $container->get(PersonalAccessTokenFactory::class));
        $this->assertInstanceOf(ApiTokenCookieFactory::class, $container->get(ApiTokenCookieFactory::class));
    }

    /**
     * Write a temporary RSA key pair for DI resolution tests.
     *
     * @return void
     */
    protected function writeKeyPair(): void
    {
        $privateKey = openssl_pkey_new();
        $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $privateKey);

        $privateFile = $this->keysPath . DIRECTORY_SEPARATOR . 'oauth-private.key';
        $publicFile = $this->keysPath . DIRECTORY_SEPARATOR . 'oauth-public.key';

        openssl_pkey_export_to_file($privateKey, $privateFile);
        $details = openssl_pkey_get_details($privateKey);
        $this->assertIsArray($details);
        $this->assertArrayHasKey('key', $details);
        file_put_contents($publicFile, $details['key']);
        chmod($privateFile, 0600);
        chmod($publicFile, 0600);

        Configure::write('Tessera.private_key');
        Configure::write('Tessera.public_key');
    }
}
