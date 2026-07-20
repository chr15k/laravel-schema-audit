## Flow

**1. Entry point — `Console/Commands/AuditSchemaCommand.php`**
Artisan resolves this via the container (per the service provider). It reads `--path`, `--driver`, `--schema-only`, then hands off to `MigrationParser`/`SchemaBuilder` and `SchemaAuditor`. This file owns nothing about *how* parsing or rules work — it's just the CLI-facing shell.

**2. File discovery + fold orchestration — `SchemaBuilder.php`**
`buildFromDirectory()` globs every `.php` file in the migrations path, sorts them (relying on timestamp-prefixed filenames for chronological order), and for each file calls `MigrationParser::parseFile()` to get that file's operations, then folds each operation into a running `array<string, TableSchema>`. This is the class that owns the whitelist of real Blueprint column methods and the dispatch logic (`applyChain`, `applyColumnDefinition`, `applyOldStyleForeign`, etc.) — the actual "what does this method call mean" knowledge lives here.

**3. Per-file parsing — `MigrationParser.php`**
Thin entrypoint: reads a file's source, hands it to PHP-Parser to get an AST, constructs a **fresh** `Parsers/SchemaCallVisitor` (per the state-leak fix — never injected), traverses, and returns whatever operations that visitor collected. One call in, one file's `list<Data\SchemaOperation>` out.

**4. AST walking — `Parsers/`**
- `SchemaCallVisitor.php` — the actual `NodeVisitorAbstract` implementation. Walks the AST looking for `Schema::create/table/drop/dropIfExists/rename`, skips `down()` method bodies entirely, and for `create`/`table` calls delegates the closure body to...
- `ChainExtractor.php` — walks each `$table->x()->y()->z()` statement inside that closure and turns it into a `Data\ColumnChain` (root call first, modifiers after) — this is what lets `SchemaBuilder` later tell apart `$table->string('x')->unique()` from `$table->unique('a','b')`.
- `ArgReader.php` — tiny stateless helpers both of the above use to pull string/closure args off PHP-Parser nodes.

**5. Data shapes — `Data/`**
Pure DTOs, no behavior beyond simple getters: `ColumnCall` (one raw `$table->method(args)` call), `ColumnChain` (an ordered list of `ColumnCall`s), `SchemaOperation` (one `Schema::` statement — type + table + chains), `Index`, `ForeignKey`, and `Finding` (a rule's output — this is why it lives in `Data/` now rather than inside `Rules/`, since it's a shape, not a rule).

**6. The folded result — `TableSchema.php`**
What `SchemaBuilder` actually mutates and hands back per table — columns, indexes, foreign keys, primary-key tracking — plus query methods like `isIndexed()` and `hasPrimaryKey()` that the rules read from.

**7. Rules — `Contracts/Rule.php` + `Rules/`**
`Contracts/Rule.php` is the interface (`check(array $tables): list<Finding>`) — the one genuine extension point, meant to be implementable by package consumers, which is why it's separated from the concrete implementations. `Rules/` holds the five implementations, each reading the folded `TableSchema[]` and emitting `Finding`s.

**8. Running them — `SchemaAuditor.php`**
Takes a `list<Rule>` (resolved from the container via the `Rule::class` tag, per the DI wiring), runs each one against the folded tables, flattens all `Finding`s into one list. This is what the command actually calls for the default (non-`--schema-only`) path.

**9. Wiring — `SchemaAuditServiceProvider.php`**
Binds/singletons everything above, tags the `Rule` implementations, resolves the driver-dependent `UnindexedForeignKeyRule` from config, and registers the Artisan command.

**End-to-end for one run:** `AuditSchemaCommand` → `SchemaBuilder::buildFromDirectory()` → (per file) `MigrationParser::parseFile()` → `SchemaCallVisitor` + `ChainExtractor` → `list<SchemaOperation>` → folded into `array<string, TableSchema>` → `SchemaAuditor::audit()` → `list<Finding>` → JSON out.