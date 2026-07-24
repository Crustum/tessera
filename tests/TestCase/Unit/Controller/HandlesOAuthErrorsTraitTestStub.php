<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\TestCase\Unit\Controller;

use Crustum\Tessera\Controller\Trait\HandlesOAuthErrorsTrait;

/**
 * Exposes withErrorHandling for unit testing.
 */
class HandlesOAuthErrorsTraitTestStub
{
    use HandlesOAuthErrorsTrait;

    /**
     * Run a callback through withErrorHandling.
     *
     * @param \Closure(): mixed $callback Callback to execute
     * @return mixed
     */
    public function test(\Closure $callback): mixed
    {
        return $this->withErrorHandling($callback);
    }
}
