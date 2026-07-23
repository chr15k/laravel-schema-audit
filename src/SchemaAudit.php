<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Exceptions\JsonEncodingException;
use Chr15k\SchemaAudit\ValueObjects\Finding;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonException;
use JsonSerializable;

/**
 * @implements Arrayable<int, Finding>
 */
final readonly class SchemaAudit implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * @param  list<Finding>  $findings
     */
    public function __construct(
        public array $findings
    ) {}

    public function count(): int
    {
        return count($this->findings);
    }

    public function hasIssues(): bool
    {
        return $this->count() > 0;
    }

    public function toPrettyJson(int $options = 0): string
    {
        return $this->toJson(JSON_PRETTY_PRINT | $options);
    }

    public function toJson($options = 0)
    {
        try {
            return json_encode($this->findings, $options | JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw JsonEncodingException::forAudit($this, $jsonException);
        }
    }

    /**
     * @return list<Finding>
     */
    public function jsonSerialize(): array
    {
        return $this->findings;
    }

    /**
     * @return list<Finding>
     */
    public function toArray(): array
    {
        return $this->findings;
    }
}
