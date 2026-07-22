<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, array|bool|string|null>
 */
final readonly class Index implements Arrayable
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        public ?string $name,
        public array $columns,
        public bool $unique = false,
    ) {}

    /**
     * @return array{
     *     name: ?string,
     *     columns: list<string>,
     *     unique: bool,
     *     signature: string
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array{
     *     name: ?string,
     *     columns: list<string>,
     *     unique: bool,
     *     signature: string
     * }
     */
    public function toArray(): array
    {
        return [
            'name'      => $this->name,
            'columns'   => $this->columns,
            'unique'    => $this->unique,
            'signature' => $this->signature(),
        ];
    }

    public function signature(): string
    {
        return implode(',', $this->columns).'|'.($this->unique ? 'unique' : 'plain');
    }
}
