<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit\ValueObjects;

use Stringable;

final readonly class SourceLocation implements Stringable
{
    public function __construct(
        public string $path,
        public int $line,
    ) {}

    public function __toString(): string
    {
        return sprintf('%s:%d', $this->path, $this->line);
    }

    public function relative(): string
    {
        $str = str((string) $this);
        $separator = DIRECTORY_SEPARATOR;

        if (($cwd = getcwd()) === false) {
            return $str->explode($separator)->take(-5)->implode($separator);
        }

        return $str->after($cwd.$separator)->toString();
    }
}
