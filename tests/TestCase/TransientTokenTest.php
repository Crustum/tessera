<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase;

use Cake\TestSuite\TestCase;
use Crustum\Tessera\TransientToken;

/**
 * TransientToken unit tests.
 */
class TransientTokenTest extends TestCase
{
    /**
     * @return void
     */
    public function testTransientTokenCanDoAnything(): void
    {
        $token = new TransientToken();
        $this->assertTrue($token->can('foo'));
        $this->assertFalse($token->cant('foo'));
    }
}
