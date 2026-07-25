<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\Schema;

final readonly class LaravelConventions
{
    public function tableNameFromModel(string $model): string
    {
        $baseName = $this->normalizeModelClass($model);

        return str($baseName)->pluralStudly()->snake()->toString();
    }

    public function foreignKeyColumnFromModel(string $model): string
    {
        $baseName = $this->normalizeModelClass($model);

        return str($baseName)->singular()->snake()->append('_id')->toString();
    }

    public function tableNameFromForeignKey(string $column): string
    {
        return str($column)->beforeLast('_id')->plural()->toString();
    }

    /** @param  list<string>  $columns */
    public function indexName(string $table, array $columns, string $type): string
    {
        $index = mb_strtolower($table.'_'.implode('_', $columns).'_'.$type);

        return str_replace(['-', '.'], '_', $index);
    }

    private function normalizeModelClass(string $modelClass): string
    {
        return class_basename(str_replace('::class', '', $modelClass));
    }
}
