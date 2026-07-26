<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Support;

use Chr15k\SchemaAudit\Contracts\AuditRule;
use Illuminate\Config\Repository;
use InvalidArgumentException;

final readonly class Config
{
    public const string KEY = 'schema-audit';

    public function __construct(private Repository $config) {}

    /**
     * @return array<string>
     */
    public function paths(): array
    {
        $config = $this->config->array(self::KEY.'.paths', [database_path('migrations')]);

        foreach ($config as $path) {
            if (! is_string($path)) {
                throw new InvalidArgumentException(
                    sprintf('Configuration value for key [%s.paths] must be a string, %s given.', self::KEY, gettype($path))
                );
            }

            if (! is_dir($path)) {
                throw new InvalidArgumentException(
                    sprintf('Configuration value for key [%s.paths] is not a directory, %s given.', self::KEY, $path)
                );
            }
        }

        return $config;
    }

    public function driver(): string
    {
        return $this->config->string(self::KEY.'.driver', 'mysql');
    }

    /**
     * @return list<AuditRule>
     */
    public function rules(): array
    {
        $rules = [];
        $config = $this->config->array(self::KEY.'.rules', []);

        foreach ($config as $class) {
            if (! is_string($class)) {
                throw new InvalidArgumentException(
                    sprintf('Configuration value for key [%s.rules] must be a string, %s given.', self::KEY, gettype($class))
                );
            }

            if (! class_exists($class)) {
                throw new InvalidArgumentException(
                    sprintf('Configuration value for key [%s.rules] must be a defined class, %s given.', self::KEY, $class)
                );
            }

            if (! is_subclass_of($class, AuditRule::class)) {
                throw new InvalidArgumentException(
                    sprintf('Configuration value for key [%s.rules] does not implement %s, %s given.', self::KEY, AuditRule::class, $class)
                );
            }

            $rules[] = app($class);
        }

        return $rules;
    }
}
