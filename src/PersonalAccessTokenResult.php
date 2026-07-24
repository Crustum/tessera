<?php
declare(strict_types=1);

namespace Crustum\Tessera;

use Cake\Utility\Inflector;
use Crustum\Tessera\Model\Entity\Token;
use JsonSerializable;

/**
 * Result of creating a personal access token.
 *
 * @property string $accessTokenId
 * @property string $accessToken
 * @property string $tokenType
 * @property int $expiresIn
 */
class PersonalAccessTokenResult implements JsonSerializable
{
    /**
     * @var \Crustum\Tessera\Model\Entity\Token|null
     */
    protected ?Token $token = null;

    /**
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * Normalize personal_access grant response attributes to camelCase keys.
     *
     * @param array<string, mixed> $attributes Response attributes
     */
    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[lcfirst(Inflector::camelize((string)$key))] = $value;
        }
    }

    /**
     * Load the persisted token entity for this result.
     *
     * @return \Crustum\Tessera\Model\Entity\Token|null
     */
    public function getToken(): ?Token
    {
        if ($this->token instanceof Token) {
            return $this->token;
        }

        $id = $this->attributes['accessTokenId'] ?? null;
        if ($id === null) {
            return null;
        }

        /** @var \Crustum\Tessera\Model\Entity\Token|null $token */
        $token = Tessera::tokensTable()->find()
            ->where(['id' => $id])
            ->first();

        return $this->token = $token;
    }

    /**
     * Export response attributes as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * JSON-serializable representation of the response attributes.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Encode response attributes as JSON.
     *
     * @param int $options json_encode flags
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return (string)json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Whether a response attribute exists.
     *
     * @param string $key Attribute
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Read a response attribute, or the token entity when key is `token`.
     *
     * @param string $key Attribute
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        if ($key === 'token') {
            return $this->getToken();
        }

        return $this->attributes[$key] ?? null;
    }
}
