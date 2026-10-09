<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase;

use Cake\TestSuite\TestCase;
use Crustum\Tessera\AccessToken;
use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;

/**
 * HasApiTokens trait unit tests.
 */
class HasApiTokensTest extends TestCase
{
    use VerifiesDoubles;

    /**
     * @return void
     */
    public function testTokenCanIndicatesIfTokenHasGivenScope(): void
    {
        $user = new HasApiTokensTestStub();
        $token = Double::for(AccessToken::class);
        $token->expects('can')->with('scope')->returns(true);
        $token->expects('can')->with('another-scope')->returns(false);

        $this->assertTrue($user->withAccessToken($token)->tokenCan('scope'));
        $this->assertFalse($user->withAccessToken($token)->tokenCan('another-scope'));
    }
}
