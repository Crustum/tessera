<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Feature;

use Cake\Utility\Text;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Tessera;

/**
 * Authorization code grant with PKCE Feature coverage.
 */
class AuthorizationCodeGrantWithPkceTest extends TesseraTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Tessera::tokensCan([
            'create' => 'Create',
            'read' => 'Read',
            'update' => 'Update',
            'delete' => 'Delete',
        ]);

        Tessera::authorizationView(static fn(array $params): array => $params);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Tessera::$authorizationView = null;
        Tessera::tokensCan([]);

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testIssueAccessToken(): void
    {
        $client = $this->makePublicAuthCodeClient();
        $redirect = $client->redirect_uris[0];
        $state = Text::uuid();

        $codeVerifier = $this->makeCodeVerifier();
        $codeChallenge = $this->codeChallengeFor($codeVerifier);

        $user = $this->createUser();
        $this->loginAs($user);

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'create read',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]));

        $this->assertResponseOk();
        $json = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertEqualsCanonicalizing(
            ['client', 'user', 'scopes', 'request', 'authToken'],
            array_keys($json),
        );
        $this->assertSame(
            array_map(
                static fn($scope): array => $scope->toArray(),
                Tessera::scopesFor(['create', 'read']),
            ),
            $json['scopes'],
        );

        $this->syncSession();
        $this->post('/oauth/authorize', ['auth_token' => $json['authToken']]);
        $this->assertResponseCode(302);
        $this->assertNull($this->getSession()->read('authRequest'));
        $this->assertNull($this->getSession()->read('authToken'));

        $location = $this->_response->getHeaderLine('Location');
        parse_str((string)parse_url($location, PHP_URL_QUERY), $params);

        $this->assertStringStartsWith($redirect . '?', $location);
        $this->assertSame($state, $params['state']);
        $this->assertArrayHasKey('code', $params);

        $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'code' => $params['code'],
            'code_verifier' => $codeVerifier,
        ]);

        $this->assertResponseOk();
        $tokenJson = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($tokenJson);
        $this->assertArrayHasKey('access_token', $tokenJson);
        $this->assertArrayHasKey('refresh_token', $tokenJson);
        $this->assertSame('Bearer', $tokenJson['token_type']);
        $this->assertEqualsWithDelta(31536000, $tokenJson['expires_in'], 2);

        $refreshToken = $tokenJson['refresh_token'];

        $this->clearSession();
        $this->configRequest([
            'headers' => [
                'Authorization' => $tokenJson['token_type'] . ' ' . $tokenJson['access_token'],
            ],
        ]);
        $this->get('/token-info');
        $this->assertResponseOk();

        $info = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($info);
        $this->assertSame($client->id, $info['oauth_client_id']);
        $this->assertEquals($user->id, $info['oauth_user_id']);
        $this->assertSame(['create', 'read'], $info['oauth_scopes']);

        $this->clearSession();
        $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->id,
            'refresh_token' => $refreshToken,
            'scope' => 'create read',
        ]);
        $this->assertResponseOk();

        $newToken = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($newToken);
        $this->assertArrayHasKey('access_token', $newToken);
        $this->assertArrayHasKey('refresh_token', $newToken);
        $this->assertEqualsWithDelta(31536000, $newToken['expires_in'], 2);
        $this->assertSame('Bearer', $newToken['token_type']);
    }

    /**
     * @return void
     */
    public function testRequireCodeChallenge(): void
    {
        $client = $this->makePublicAuthCodeClient();
        $redirect = $client->redirect_uris[0];
        $state = Text::uuid();

        $this->loginAs($this->createUser());

        $query = http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'state' => $state,
        ]);

        $this->get('/oauth/authorize?' . $query);
        $this->assertResponseCode(400);

        $params = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($params);
        $this->assertSame('invalid_request', $params['error']);
        $this->assertSame('Code challenge must be provided for public clients', $params['hint']);
        $this->assertArrayHasKey('error_description', $params);

        $this->get('/oauth/authorize?' . $query . '&code_challenge=foo');
        $this->assertResponseCode(400);

        $params = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($params);
        $this->assertSame('invalid_request', $params['error']);
        $this->assertSame('Code challenge must follow the specifications of RFC-7636.', $params['hint']);
        $this->assertArrayHasKey('error_description', $params);
    }

    /**
     * @return void
     */
    public function testInvalidCodeChallengeMethod(): void
    {
        $client = $this->makePublicAuthCodeClient();
        $redirect = $client->redirect_uris[0];
        $state = Text::uuid();

        $codeVerifier = $this->makeCodeVerifier();
        $codeChallenge = $this->codeChallengeFor($codeVerifier);

        $this->loginAs($this->createUser());

        $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'foo',
        ]));
        $this->assertResponseCode(400);

        $params = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($params);
        $this->assertSame('invalid_request', $params['error']);
        $this->assertStringStartsWith('Code challenge method must be', $params['hint']);
        $this->assertArrayHasKey('error_description', $params);
    }

    /**
     * @return \Crustum\Tessera\Model\Entity\Client
     */
    protected function makePublicAuthCodeClient(): Client
    {
        return (new ClientRepository())->createAuthorizationCodeGrantClient(
            'Public Auth Code Client',
            ['http://localhost'],
            confidential: false,
        );
    }

    /**
     * @return string
     */
    protected function makeCodeVerifier(): string
    {
        return Text::uuid() . Text::uuid() . Text::uuid();
    }

    /**
     * @param string $codeVerifier Code verifier
     * @return string
     */
    protected function codeChallengeFor(string $codeVerifier): string
    {
        return strtr(rtrim(base64_encode(hash('sha256', $codeVerifier, true)), '='), '+/', '-_');
    }
}
