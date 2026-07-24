<?php
declare(strict_types=1);

namespace Crustum\Tessera\Test\Factory;

use CakephpFixtureFactories\Factory\BaseFactory;
use Faker\Generator;

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
    protected function setDefaultTemplate(): void
    {
        $this->setDefaultData(fn(Generator $faker): array => [
            'owner_id' => null,
            'owner_type' => null,
            'name' => $faker->company(),
            'secret' => $faker->sha1(),
            'redirect_uris' => [$faker->url()],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);
    }

    /**
     * Public (non-confidential) client.
     *
     * @return $this
     */
    public function asPublic()
    {
        return $this->patchData([
            'secret' => null,
        ]);
    }

    /**
     * Password grant client.
     *
     * @return $this
     */
    public function asPasswordClient()
    {
        return $this->patchData([
            'grant_types' => ['password', 'refresh_token'],
            'redirect_uris' => [],
        ]);
    }

    /**
     * Personal access token client.
     *
     * @return $this
     */
    public function asPersonalAccessTokenClient()
    {
        return $this->patchData([
            'grant_types' => ['personal_access'],
            'redirect_uris' => [],
        ]);
    }

    /**
     * Implicit grant client.
     *
     * @return $this
     */
    public function asImplicitClient()
    {
        return $this->patchData([
            'grant_types' => ['implicit'],
            'secret' => null,
        ]);
    }

    /**
     * Client credentials grant client.
     *
     * @return $this
     */
    public function asClientCredentials()
    {
        return $this->patchData([
            'grant_types' => ['client_credentials'],
            'redirect_uris' => [],
        ]);
    }

    /**
     * Device code grant client.
     *
     * @return $this
     */
    public function asDeviceCodeClient()
    {
        return $this->patchData([
            'grant_types' => ['urn:ietf:params:oauth:grant-type:device_code', 'refresh_token'],
            'redirect_uris' => [],
        ]);
    }
}
