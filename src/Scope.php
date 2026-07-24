<?php
declare(strict_types=1);

namespace Crustum\Tessera;

use JsonSerializable;

/**
 * OAuth scope definition.
 */
class Scope implements JsonSerializable
{
    /**
     * Define an OAuth scope identifier and human-readable description.
     *
     * @param string $id Scope identifier
     * @param string $description Human-readable description
     */
    public function __construct(
        public string $id,
        public string $description,
    ) {
    }

    /**
     * Get the instance as an array.
     *
     * @return array{id: string, description: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
        ];
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options json_encode flags
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return (string)json_encode($this->toArray(), $options);
    }

    /**
     * JSON-serializable id and description pair.
     *
     * @return array{id: string, description: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
