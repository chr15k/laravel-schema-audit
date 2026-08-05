<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Parsers\ValueObjects;

final readonly class ColumnCall
{
    /**
     * @param  array<int|string, mixed>  $arguments
     */
    public function __construct(
        public string $method,
        public array $arguments = [],
        public ?SourceLocation $location = null
    ) {}

    public function argument(int|string $key, mixed $default = null): mixed
    {
        return $this->arguments[$key] ?? $default;
    }

    public function stringArgument(int|string $key, ?string $default = null): ?string
    {
        $value = $this->argument($key, $default);

        return is_string($value) ? $value : $default;
    }

    /**
     * @return list<string>|null
     */
    public function stringArrayArgument(int|string $key): ?array
    {
        $value = $this->argument($key);

        return is_array($value) && array_is_list($value)
            ? array_values(array_filter($value, is_string(...)))
            : null;
    }

    /**
     * @return string|list<string>|null
     */
    public function stringOrArrayArgument(
        int|string $key,
        mixed $default = null,
    ): string|array|null {
        $value = $this->argument($key, $default);

        if (is_string($value)) {
            return $value;
        }

        if (
            is_array($value)
            && array_is_list($value)
            && array_filter($value, is_string(...)) === $value
        ) {
            /** @var list<string> $value */
            return $value;
        }

        return null;
    }

    /**
     * Returns an argument that may be a single string or a list of strings
     * as a normalized list of strings.
     *
     * @return list<string>|null
     */
    public function stringListArgument(int|string $key, mixed $default = null): ?array
    {
        $value = $this->argument($key, $default);

        if (is_string($value)) {
            return [$value];
        }

        if (is_array($value) && array_is_list($value)) {
            return array_values(array_filter($value, is_string(...)));
        }

        return null;
    }
}
