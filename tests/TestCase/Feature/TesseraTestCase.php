<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\UnauthorizedException;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use Cake\Utility\Text;
use Crustum\Tessera\Tessera;
use OpenSSLAsymmetricKey;
use TestApp\Application;
use TestApp\Model\Entity\User;

/**
 * Feature test base: OAuth keys, TestApp, IntegrationTestTrait.
 */
abstract class TesseraTestCase extends TestCase
{
    use IntegrationTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        \Crustum\Tessera\Test\Fixture\UsersFixture::class,
        \Crustum\Tessera\Test\Fixture\OauthClientsFixture::class,
        \Crustum\Tessera\Test\Fixture\OauthAuthCodesFixture::class,
        \Crustum\Tessera\Test\Fixture\OauthAccessTokensFixture::class,
        \Crustum\Tessera\Test\Fixture\OauthRefreshTokensFixture::class,
        \Crustum\Tessera\Test\Fixture\OauthDeviceCodesFixture::class,
    ];

    public const KEYS = TESTS . 'keys';

    public const PUBLIC_KEY = self::KEYS . DIRECTORY_SEPARATOR . 'oauth-public.key';

    public const PRIVATE_KEY = self::KEYS . DIRECTORY_SEPARATOR . 'oauth-private.key';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->configApplication(Application::class, [CONFIG]);
        $this->disableErrorHandlerMiddleware();

        Security::setSalt(str_repeat('c', 32));
        Configure::write('App.fullBaseUrl', 'http://localhost');
        Configure::write('Tessera.usersTable', 'Users');

        if (!is_dir(self::KEYS)) {
            mkdir(self::KEYS, 0777, true);
        }

        Tessera::loadKeysFrom(self::KEYS);
        Tessera::$validateKeyPermissions = false;
        $this->unlinkKeys();
        $this->generateKeyPair();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->unlinkKeys();
        Configure::delete('Tessera.private_key');
        Configure::delete('Tessera.public_key');
        Tessera::$validateKeyPermissions = true;
        Tessera::clearActingAs();
        Tessera::$withInheritedScopes = false;
        Tessera::$authorizationServerResponseType = null;

        parent::tearDown();
    }

    /**
     * Write a temporary RSA key pair for League CryptKey.
     *
     * @return void
     */
    protected function generateKeyPair(): void
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if (!$privateKey instanceof OpenSSLAsymmetricKey) {
            $this->fail('Unable to generate OpenSSL key pair for Feature tests.');
        }

        $privatePem = '';
        if (!openssl_pkey_export($privateKey, $privatePem) || $privatePem === '') {
            $this->fail('Unable to export private key for Feature tests.');
        }

        $details = openssl_pkey_get_details($privateKey);
        if (!is_array($details) || !isset($details['key']) || !is_string($details['key']) || $details['key'] === '') {
            $this->fail('Unable to export public key for Feature tests.');
        }

        file_put_contents(self::PRIVATE_KEY, $privatePem);
        file_put_contents(self::PUBLIC_KEY, $details['key']);
        chmod(self::PRIVATE_KEY, 0600);
        chmod(self::PUBLIC_KEY, 0600);

        Configure::write('Tessera.private_key', $privatePem);
        Configure::write('Tessera.public_key', $details['key']);
    }

    /**
     * Remove generated key files.
     *
     * @return void
     */
    protected function unlinkKeys(): void
    {
        if (is_file(self::PRIVATE_KEY)) {
            unlink(self::PRIVATE_KEY);
        }

        if (is_file(self::PUBLIC_KEY)) {
            unlink(self::PUBLIC_KEY);
        }
    }

    /**
     * Assert the next request is rejected with HTTP 403.
     *
     * @param callable(): void $request Request callback (e.g. fn () => $this->get('/path'))
     * @return void
     */
    protected function assertRequestForbidden(callable $request): void
    {
        try {
            $request();
            $this->fail('Expected a forbidden response.');
        } catch (ForbiddenException $forbiddenException) {
            $this->assertSame(403, $forbiddenException->getCode());
        }
    }

    /**
     * Assert the next request is rejected with HTTP 401.
     *
     * @param callable(): void $request Request callback (e.g. fn () => $this->get('/path'))
     * @return void
     */
    protected function assertRequestUnauthorized(callable $request): void
    {
        try {
            $request();
            $this->fail('Expected an unauthorized response.');
        } catch (UnauthorizedException $unauthorizedException) {
            $this->assertSame(401, $unauthorizedException->getCode());
        }
    }

    /**
     * Persist a test user.
     *
     * @param string $email Email
     * @param string $password Plain password
     * @return \TestApp\Model\Entity\User
     */
    protected function createUser(string $email = '', string $password = 'secret'): User
    {
        if ($email === '') {
            $email = 'user-' . Text::uuid() . '@example.com';
        }

        $users = TableRegistry::getTableLocator()->get('Users');
        /** @var \TestApp\Model\Entity\User $user */
        $user = $users->saveOrFail($users->newEntity([
            'email' => $email,
            'password' => $password,
        ]));

        return $user;
    }

    /**
     * Establish a session-authenticated identity for subsequent requests.
     *
     * @param \TestApp\Model\Entity\User $user User
     * @return void
     */
    protected function loginAs(User $user): void
    {
        $this->session(['Auth' => $user]);
    }

    /**
     * Copy the last request's session into the IntegrationTestTrait bag for the next request.
     *
     * @return void
     */
    protected function syncSession(): void
    {
        $data = $this->getSession()->read();
        if (is_array($data) && $data !== []) {
            $this->session($data);
        }
    }

    /**
     * Drop pending IntegrationTestTrait session data (e.g. before Bearer-only API calls).
     *
     * @return void
     */
    protected function clearSession(): void
    {
        $this->_session = [];
    }
}
