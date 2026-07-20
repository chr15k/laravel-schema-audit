<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Support;

use Chr15k\SchemaAudit\Contracts\Rule;
use Illuminate\Config\Repository;
use InvalidArgumentException;

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
     * @return list<Rule>
     *
     * @throws InvalidArgumentException
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

            if (! is_subclass_of($class, Rule::class)) {
                throw new InvalidArgumentException(
                    sprintf('Configuration value for key [%s.rules] does not implement %s, %s given.', self::KEY, Rule::class, $class)
                );
            }

            $rules[] = app($class);
        }

        return $rules;
    }
}
