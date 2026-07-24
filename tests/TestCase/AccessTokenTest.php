<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase;

use Cake\TestSuite\TestCase;
use Crustum\Tessera\AccessToken;
use Crustum\Tessera\Tessera;

/**
 * AccessToken unit tests.
 */
class AccessTokenTest extends TestCase
{
    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Tessera::$withInheritedScopes = false;

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testTokenAttributesAreAccessible(): void
    {
        $token = new AccessToken([
            'oauth_user_id' => 1,
            'oauth_client_id' => 2,
            'oauth_access_token_id' => 'token',
            'oauth_scopes' => ['*'],
            'foo' => 'bar',
        ]);

        $this->assertFalse($token->transient());

        $this->assertSame(1, $token->oauth_user_id);
        $this->assertSame(2, $token->oauth_client_id);
        $this->assertSame('token', $token->oauth_access_token_id);
        $this->assertSame(['*'], $token->oauth_scopes);
        $this->assertSame('bar', $token->foo);

        $this->assertTrue(isset($token->oauth_user_id));
        $this->assertTrue(isset($token->oauth_client_id));
        $this->assertTrue(isset($token->oauth_access_token_id));
        $this->assertTrue(isset($token->oauth_scopes));
        $this->assertTrue(isset($token->foo));
    }

    /**
     * @return void
     */
    public function testTokenCanDetermineIfItHasScopes(): void
    {
        $token = new AccessToken(['oauth_scopes' => ['user']]);

        $this->assertTrue($token->can('user'));
        $this->assertFalse($token->can('something'));
        $this->assertTrue($token->cant('something'));
        $this->assertFalse($token->cant('user'));

        $this->assertTrue($token->cant('user:read'));

        $token = new AccessToken(['oauth_scopes' => ['*']]);
        $this->assertTrue($token->can('user'));
        $this->assertTrue($token->can('something'));
    }

    /**
     * @return void
     */
    public function testTokenCanDetermineIfItHasInheritedScopes(): void
    {
        Tessera::$withInheritedScopes = true;

        $token = new AccessToken([
            'oauth_scopes' => [
                'user',
                'group',
                'admin:webhooks:read',
            ],
        ]);

        $this->assertTrue($token->can('user'));
        $this->assertTrue($token->can('group'));
        $this->assertTrue($token->can('user:read'));
        $this->assertTrue($token->can('group:read'));
        $this->assertTrue($token->can('admin:webhooks:read'));

        $this->assertTrue($token->cant('admin:webhooks'));

        $this->assertFalse($token->can('something'));

        $token = new AccessToken(['oauth_scopes' => ['*']]);
        $this->assertTrue($token->can('user'));
        $this->assertTrue($token->can('something'));
        $this->assertTrue($token->can('admin:webhooks:write'));
    }

    /**
     * @return void
     */
    public function testTokenResolvesInheritedScopes(): void
    {
        $token = new AccessToken();

        $inheritedScopes = (fn (): array => $this->resolveInheritedScopes('admin:webhooks:read'))->call($token);

        $this->assertSame([
            'admin',
            'admin:webhooks',
            'admin:webhooks:read',
        ], $inheritedScopes);
    }
}
