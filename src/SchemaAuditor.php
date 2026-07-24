<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Contracts\AuditRule;
use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Schema\Schema;
use Illuminate\Pipeline\Pipeline;

final readonly class SchemaAuditor
{
    /**
     * @param  list<AuditRule>  $rules
     */
    public function __construct(
        private array $rules,
        private Pipeline $pipeline
    ) {}

    public function audit(Schema $schema): SchemaAudit
    {
        /** @var AuditContext $context */
        $context = $this->pipeline
            ->send(new AuditContext(schema: $schema))
            ->through($this->rules)
            ->thenReturn();

        return $context->audit;
    }

    /**
     * @return list<AuditRule> $rules
     */
    public function rules(): array
    {
        return $this->rules;
    }
}
