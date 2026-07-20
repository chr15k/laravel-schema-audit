<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Support;

use Illuminate\Config\Repository;

final readonly class Config
{
    public const string KEY = 'schema-audit';

    public function __construct(private Repository $config) {}

    public function path(): string
    {
        return $this->config->string(self::KEY.'.path', database_path('migrations'));
    }

    public function driver(): string
    {
        return $this->config->string(self::KEY.'.driver', 'mysql');
    }

    /**
     * @return array<array-key, mixed>
     */
    public function rules(): array
    {
        return $this->config->array(self::KEY.'.rules', []);
    }
}
