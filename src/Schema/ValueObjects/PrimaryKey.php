<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, array|bool|string|null>
 */
final readonly class PrimaryKey implements Arrayable, JsonSerializable
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        public array $columns
    ) {}

    /**
     * @return array{
     *     columns: list<string>
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array{
     *     columns: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'columns' => $this->columns,
        ];
    }
}
