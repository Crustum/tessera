<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Model\Entity;

use Cake\TestSuite\TestCase;
use Crustum\Tessera\Model\Entity\Token;
use Crustum\Tessera\Tessera;

/**
 * Token entity unit tests.
 */
class TokenTest extends TestCase
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
    public function testTokenCanDetermineIfItHasScopes(): void
    {
        $token = new Token(['scopes' => ['user']]);

        $this->assertTrue($token->can('user'));
        $this->assertFalse($token->can('something'));
        $this->assertTrue($token->cant('something'));
        $this->assertFalse($token->cant('user'));
        $this->assertTrue($token->cant('user:read'));

        $token = new Token(['scopes' => ['*']]);

        $this->assertTrue($token->can('user'));
        $this->assertTrue($token->can('something'));
    }

    /**
     * @return void
     */
    public function testTokenCanDetermineIfItHasInheritedScopes(): void
    {
        Tessera::$withInheritedScopes = true;

        $token = new Token([
            'scopes' => [
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

        $token = new Token(['scopes' => ['*']]);

        $this->assertTrue($token->can('user'));
        $this->assertTrue($token->can('something'));
        $this->assertTrue($token->can('admin:webhooks:write'));
    }

    /**
     * @return void
     */
    public function testTokenResolvesInheritedScopes(): void
    {
        $token = new Token();

        $inheritedScopes = (fn (): array => $this->resolveInheritedScopes('admin:webhooks:read'))->call($token);

        $this->assertSame([
            'admin',
            'admin:webhooks',
            'admin:webhooks:read',
        ], $inheritedScopes);
    }
}
