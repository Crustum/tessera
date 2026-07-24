<?php
declare(strict_types=1);

namespace Crustum\Tessera\Controller\Trait;

use Crustum\Tessera\Tessera;

/**
 * Maps League scope entities to Tessera scope definitions.
 */
trait ResolvesTesseraScopesTrait
{
    /**
     * Transform League scope entities into Tessera scope definitions.
     *
     * @param iterable<\League\OAuth2\Server\Entities\ScopeEntityInterface> $scopes League scopes
     * @return list<\Crustum\Tessera\Scope>
     */
    protected function tesseraScopesFrom(iterable $scopes): array
    {
        $ids = [];
        foreach ($scopes as $scope) {
            $ids[] = $scope->getIdentifier();
        }

        return Tessera::scopesFor(array_values(array_unique($ids)));
    }
}
