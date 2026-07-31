<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Exceptions;

use Chr15k\SchemaAudit\Schema\Schema;
use Chr15k\SchemaAudit\SchemaAudit;
use JsonException;
use RuntimeException;

final class JsonEncodingException extends RuntimeException
{
    public static function forSchema(Schema $schema, JsonException $previous): self
    {
        return new self(
            sprintf(
                'Failed to encode schema to JSON: %s. Tables: %s',
                $previous->getMessage(),
                $schema->toJson(),
            ),
            previous: $previous,
        );
    }

    public static function forAudit(SchemaAudit $audit, JsonException $previous): self
    {
        return new self(
            sprintf(
                'Failed to encode audit to JSON: %s. Findings: %s',
                $previous->getMessage(),
                $audit->toJson()
            ),
            previous: $previous,
        );
    }
}
