<?php

declare(strict_types=1);

namespace Chr15k\SchemaAudit;

use Chr15k\SchemaAudit\Contracts\AuditRule;
use Chr15k\SchemaAudit\Data\AuditContext;
use Chr15k\SchemaAudit\Schema\Schema;
use Chr15k\SchemaAudit\Support\Config;
use Illuminate\Pipeline\Pipeline;

final readonly class SchemaAuditor
{
    public function __construct(
        private Config $config,
        private Pipeline $pipeline
    ) {}

    public function audit(Schema $schema): SchemaAudit
    {
        /** @var AuditContext $context */
        $context = $this->pipeline
            ->send(new AuditContext(schema: $schema))
            ->through($this->rules())
            ->thenReturn();

        return $context->audit;
    }

    /**
     * @return list<AuditRule> $rules
     */
    public function rules(): array
    {
        return $this->config->rules();
    }
}
