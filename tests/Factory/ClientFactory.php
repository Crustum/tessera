<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\Factory;

use CakephpFixtureFactories\Factory\BaseFactory;
use CakephpFixtureFactories\Generator\GeneratorInterface;

/**
 * Test factory for OAuth clients.
 *
 * Uses vierge-noire/cakephp-fixture-factories (require-dev only).
 */
class ClientFactory extends BaseFactory
{
    /**
     * @return string
     */
    protected function getRootTableRegistryName(): string
    {
        return 'Crustum/Tessera.Clients';
    }

    /**
     * @return void
     */
    public function definition(GeneratorInterface $generator): array
    {
        return [
            'owner_id' => null,
            'owner_type' => null,
            'name' => $generator->company(),
            'secret' => $generator->sha1(),
            'redirect_uris' => [$generator->url()],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ];
    }

    /**
     * Public (non-confidential) client.
     *
     * @return $this
     */
    public function asPublic(): static
    {
        return $this->state([
            'secret' => null,
        ]);
    }

    /**
     * Password grant client.
     *
     * @return $this
     */
    public function asPasswordClient(): static
    {
        return $this->state([
            'grant_types' => ['password', 'refresh_token'],
            'redirect_uris' => [],
        ]);
    }

    /**
     * Personal access token client.
     *
     * @return $this
     */
    public function asPersonalAccessTokenClient(): static
    {
        return $this->state([
            'grant_types' => ['personal_access'],
            'redirect_uris' => [],
        ]);
    }

    /**
     * Implicit grant client.
     *
     * @return $this
     */
    public function asImplicitClient(): static
    {
        return $this->state([
            'grant_types' => ['implicit'],
            'secret' => null,
        ]);
    }

    /**
     * Client credentials grant client.
     *
     * @return $this
     */
    public function asClientCredentials(): static
    {
        return $this->state([
            'grant_types' => ['client_credentials'],
            'redirect_uris' => [],
        ]);
    }

    /**
     * Device code grant client.
     *
     * @return $this
     */
    public function asDeviceCodeClient(): static
    {
        return $this->state([
            'grant_types' => ['urn:ietf:params:oauth:grant-type:device_code', 'refresh_token'],
            'redirect_uris' => [],
        ]);
    }
}
