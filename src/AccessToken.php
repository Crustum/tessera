<?php
declare(strict_types=1);

namespace Crustum\Tessera;

use Cake\ORM\Locator\LocatorAwareTrait;
use Crustum\Tessera\Contracts\ScopeAuthorizable;
use Crustum\Tessera\Model\Entity\Token;
use Crustum\Tessera\Model\Table\TokensTable;
use Crustum\Tessera\Trait\ResolvesInheritedScopesTrait;
use JsonSerializable;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * Runtime access token wrapper.
 *
 * @property string $oauth_access_token_id
 * @property string $oauth_client_id
 * @property string|null $oauth_user_id
 * @property list<string> $oauth_scopes
 * @phpstan-consistent-constructor
 */
class AccessToken implements ScopeAuthorizable, JsonSerializable
{
    use LocatorAwareTrait;
    use ResolvesInheritedScopesTrait;

    /**
     * @var \Crustum\Tessera\Model\Entity\Token|null
     */
    protected ?Token $token = null;

    /**
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * Build a runtime access token from request/attribute data.
     *
     * @param array<string, mixed> $attributes Attributes
     */
    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Create from an incoming PSR-7 request.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return static
     */
    public static function fromPsrRequest(ServerRequestInterface $request): static
    {
        return new static($request->getAttributes());
    }

    /**
     * @inheritDoc
     */
    public function can(string $scope): bool
    {
        $scopes = $this->attributes['oauth_scopes'] ?? [];
        if (!is_array($scopes) || $scopes === []) {
            return false;
        }

        /** @var list<string> $scopes */
        return in_array('*', $scopes, true)
            || $this->scopeExistsIn($scope, $scopes);
    }

    /**
     * @inheritDoc
     */
    public function cant(string $scope): bool
    {
        return !$this->can($scope);
    }

    /**
     * Whether this is a transient JWT token.
     *
     * @return bool
     */
    public function transient(): bool
    {
        return false;
    }

    /**
     * Revoke the underlying access token.
     *
     * @return bool
     */
    public function revoke(): bool
    {
        $tokens = $this->tokensTable();
        $token = $this->getToken();
        if ($token instanceof Token) {
            return $tokens->revoke($token);
        }

        if (isset($this->attributes['oauth_access_token_id'])) {
            return (bool)$tokens->updateAll(
                ['revoked' => true],
                ['id' => $this->attributes['oauth_access_token_id']],
            );
        }

        return false;
    }

    /**
     * Load the persisted token entity for this access token id, if any.
     *
     * @return \Crustum\Tessera\Model\Entity\Token|null
     */
    protected function getToken(): ?Token
    {
        if ($this->token instanceof Token) {
            return $this->token;
        }

        if (isset($this->attributes['oauth_access_token_id'])) {
            $found = $this->tokensTable()->find()
                ->where(['id' => $this->attributes['oauth_access_token_id']])
                ->first();
            $this->token = $found instanceof Token ? $found : null;

            return $this->token;
        }

        return null;
    }

    /**
     * Resolve the OAuth access tokens table.
     *
     * @return \Crustum\Tessera\Model\Table\TokensTable
     */
    protected function tokensTable(): TokensTable
    {
        $table = $this->fetchTable('Tessera.Tokens');
        if (!$table instanceof TokensTable) {
            throw new RuntimeException('Tessera.Tokens must resolve to TokensTable.');
        }

        return $table;
    }

    /**
     * Export attribute values as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * JSON-serializable representation of the attributes.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Encode attributes as JSON.
     *
     * @param int $options json_encode flags
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return (string)json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Whether an attribute exists on the wrapper or loaded token entity.
     *
     * @param string $key Attribute
     * @return bool
     */
    public function __isset(string $key): bool
    {
        if (isset($this->attributes[$key])) {
            return true;
        }

        $token = $this->getToken();

        return $token instanceof Token && isset($token->{$key});
    }

    /**
     * Read an attribute from the wrapper or loaded token entity.
     *
     * @param string $key Attribute
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        return $this->getToken()?->{$key};
    }
}
