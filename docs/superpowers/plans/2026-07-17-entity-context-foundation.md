# Explicit Entity Context Foundation Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace implicit `Auth::user()->entity` coupling with a strict, scoped, stack-based entity context while retaining an opt-in Auth compatibility resolver.

**Architecture:** A container-scoped `EntityContext` owns an explicit stack of `Entity` objects and delegates to a configured `EntityResolver` only when the stack is empty. Entity-bound scopes, model hooks, transactions, balances, periods, currencies, recycling, and reports resolve through this service and fail closed when no entity exists.

**Tech Stack:** PHP 8.2+, Laravel/Illuminate 11–13, Eloquent, Orchestra Testbench, PHPUnit 11–12.

---

## File structure

**Create**

- `src/Context/EntityContext.php` — public context contract.
- `src/Context/EntityResolver.php` — fallback resolver contract.
- `src/Context/StackEntityContext.php` — scoped stack implementation.
- `src/Context/NullEntityResolver.php` — strict default resolver.
- `src/Context/AuthEntityResolver.php` — opt-in legacy compatibility.
- `src/Exceptions/MissingEntityContext.php` — missing-context failure.
- `src/Exceptions/EntityContextMismatch.php` — cross-entity mismatch failure.
- `tests/Unit/EntityContextTest.php` — stack, fallback, and restoration behavior.
- `tests/Unit/EntityContextScopeTest.php` — query and creation isolation.
- `tests/Feature/ExplicitEntityTransactionTest.php` — transaction resolution without Auth.
- `tests/Feature/ExplicitEntityReportTest.php` — reports without Auth coupling.

**Modify**

- `src/IFRSServiceProvider.php` — scoped context and resolver bindings.
- `config/ifrs.php` — strict default resolver configuration.
- `src/Scopes/EntityScope.php` — fail-closed context-based filtering.
- `src/Traits/Segregating.php` — context assignment and mismatch protection.
- `src/Traits/Recycling.php` — context-based audit ownership.
- `src/Models/Transaction.php` — explicit entity-derived transaction state.
- `src/Models/Balance.php` — context-based fallback.
- `src/Models/Account.php` — context-based aggregate/report helpers.
- `src/Models/Currency.php` — context-based creation.
- `src/Models/ReportingPeriod.php` — context-based fallback.
- `src/Reports/FinancialStatement.php` — context-based report entity.
- `src/Reports/IncomeStatement.php` — context-based static helpers.
- `src/Reports/AgingSchedule.php` — context-based report entity.
- `tests/TestCase.php` — explicitly establish context for legacy upstream tests.
- `README.md` — strict and compatibility usage.
- `CHANGELOG.md` — 6.1 context foundation entry.

## Chunk 1: Context core

### Task 1: Define strict context contracts and failures

**Files:**

- Create: `src/Context/EntityContext.php`
- Create: `src/Context/EntityResolver.php`
- Create: `src/Exceptions/MissingEntityContext.php`
- Create: `src/Exceptions/EntityContextMismatch.php`
- Test: `tests/Unit/EntityContextTest.php`

- [ ] **Step 1: Write failing interface/exception API tests**

Test that the four types autoload and expose the intended public methods:

```php
public function testEntityContextContractExposesRequiredOperations(): void
{
    $reflection = new \ReflectionClass(\IFRS\Context\EntityContext::class);

    $this->assertTrue($reflection->isInterface());
    $this->assertTrue($reflection->hasMethod('current'));
    $this->assertTrue($reflection->hasMethod('id'));
    $this->assertTrue($reflection->hasMethod('requireEntity'));
    $this->assertTrue($reflection->hasMethod('runForEntity'));
}
```

- [ ] **Step 2: Run the focused test and verify RED**

Run:

```bash
vendor/bin/phpunit tests/Unit/EntityContextTest.php
```

Expected: failure because `IFRS\Context\EntityContext` does not exist.

- [ ] **Step 3: Add minimal contracts and typed exceptions**

`EntityContext::runForEntity()` accepts `Entity|int` only if integer lookup is intentionally retained; prefer `Entity` for this milestone to avoid hidden database access.

- [ ] **Step 4: Run the focused test and verify GREEN**

- [ ] **Step 5: Commit**

```bash
git add src/Context src/Exceptions tests/Unit/EntityContextTest.php
git commit -m "feat: define entity context contracts"
```

### Task 2: Implement stack and fallback resolution

**Files:**

- Create: `src/Context/StackEntityContext.php`
- Create: `src/Context/NullEntityResolver.php`
- Create: `src/Context/AuthEntityResolver.php`
- Modify: `tests/Unit/EntityContextTest.php`

- [ ] **Step 1: Add failing tests for strict resolution**

Cover:

- Empty strict context returns `null` from `current()`.
- `requireEntity()` throws `MissingEntityContext`.
- `runForEntity()` exposes the entity inside its callback.
- Context is empty after the callback.

- [ ] **Step 2: Run and verify RED**

- [ ] **Step 3: Implement the minimal stack**

Use an instance array and `try/finally`:

```php
public function runForEntity(Entity $entity, callable $callback): mixed
{
    $this->stack[] = $entity;

    try {
        return $callback($entity);
    } finally {
        array_pop($this->stack);
    }
}
```

- [ ] **Step 4: Run and verify GREEN**

- [ ] **Step 5: Add failing nested-context and exception-restoration tests**

Assert outer → inner → outer ordering and restoration after an exception.

- [ ] **Step 6: Implement only what those tests require**

- [ ] **Step 7: Add failing fallback tests**

Cover strict null resolution, enabled Auth resolution, and explicit-context precedence over Auth.

- [ ] **Step 8: Implement `NullEntityResolver` and `AuthEntityResolver`**

Keep every direct `Auth` dependency inside `AuthEntityResolver`.

- [ ] **Step 9: Run focused tests**

- [ ] **Step 10: Commit**

```bash
git add src/Context tests/Unit/EntityContextTest.php
git commit -m "feat: add scoped entity context stack"
```

### Task 3: Bind context safely through the service provider

**Files:**

- Modify: `src/IFRSServiceProvider.php`
- Modify: `config/ifrs.php`
- Modify: `tests/Unit/EntityContextTest.php`

- [ ] **Step 1: Write failing container tests**

Assert:

- `EntityContext` resolves to the same instance within one container scope.
- Default resolver is `NullEntityResolver`.
- Configuring `AuthEntityResolver::class` selects the compatibility resolver.
- A nonexistent or incompatible resolver class produces an actionable exception.

- [ ] **Step 2: Run and verify RED**

- [ ] **Step 3: Add strict configuration**

Prefer one source of truth:

```php
'entity_context' => [
    'resolver' => IFRS\Context\NullEntityResolver::class,
],
```

- [ ] **Step 4: Register resolver and scoped context bindings**

Use `$this->app->scoped()` where available. Confirm the Testbench versions for Laravel 11–13 all support it.

- [ ] **Step 5: Run and verify GREEN**

- [ ] **Step 6: Commit**

```bash
git add src/IFRSServiceProvider.php config/ifrs.php tests/Unit/EntityContextTest.php
git commit -m "feat: register strict entity context"
```

## Chunk 2: Fail-closed model isolation

### Task 4: Replace Auth-based global scope

**Files:**

- Modify: `src/Scopes/EntityScope.php`
- Create: `tests/Unit/EntityContextScopeTest.php`

- [ ] **Step 1: Write a failing no-context query test**

Log out, clear explicit context, and query an entity-bound model. Expect `MissingEntityContext`, not records and not an empty silent result.

- [ ] **Step 2: Run and verify RED**

Confirm the current package returns an unscoped result or otherwise fails for the wrong reason.

- [ ] **Step 3: Make `EntityScope` require `EntityContext`**

Apply exactly one qualified predicate:

```php
$builder->where(
    $model->qualifyColumn('entity_id'),
    app(EntityContext::class)->requireEntity()->getKey(),
);
```

- [ ] **Step 4: Run and verify GREEN**

- [ ] **Step 5: Add two-entity isolation test**

Create records in two explicit contexts and assert each context sees only its own records.

- [ ] **Step 6: Commit**

```bash
git add src/Scopes/EntityScope.php tests/Unit/EntityContextScopeTest.php
git commit -m "fix: make entity scope fail closed"
```

### Task 5: Enforce entity ownership during creation

**Files:**

- Modify: `src/Traits/Segregating.php`
- Modify: `tests/Unit/EntityContextScopeTest.php`

- [ ] **Step 1: Write failing creation-assignment test**

Inside entity A, create an account without `entity_id`; assert entity A is assigned.

- [ ] **Step 2: Run and verify RED**

- [ ] **Step 3: Replace the Auth creating hook with context assignment**

- [ ] **Step 4: Run and verify GREEN**

- [ ] **Step 5: Write failing mismatch test**

Inside entity A, attempt to create a model with entity B. Expect `EntityContextMismatch` before SQL persistence.

- [ ] **Step 6: Implement mismatch validation and verify GREEN**

- [ ] **Step 7: Commit**

```bash
git add src/Traits/Segregating.php tests/Unit/EntityContextScopeTest.php
git commit -m "fix: enforce active entity on model creation"
```

### Task 6: Adapt the upstream test harness without hiding strict behavior

**Files:**

- Modify: `tests/TestCase.php`

- [ ] **Step 1: Run the complete existing suite under strict defaults**

Run:

```bash
vendor/bin/phpunit
```

Expected: widespread failures where existing tests rely on automatic Auth context.

- [ ] **Step 2: Update test setup to establish explicit context**

After creating the test entity, enter or seed a context in a way that is reset per test. Do not globally enable `AuthEntityResolver`, because that would stop the suite from exercising strict mode.

- [ ] **Step 3: Run the complete suite**

Expected: remaining failures identify direct Auth dependencies to migrate in Chunk 3.

- [ ] **Step 4: Commit**

```bash
git add tests/TestCase.php
git commit -m "test: establish explicit entity context"
```

## Chunk 3: Remove internal Auth coupling

### Task 7: Make transactions resolve from explicit entity

**Files:**

- Modify: `src/Models/Transaction.php`
- Create: `tests/Feature/ExplicitEntityTransactionTest.php`

- [ ] **Step 1: Write a failing unauthenticated transaction test**

Create entity, currency, rate, period, and accounts explicitly. Log out. Run inside `runForEntity()` and post a journal. Assert transaction number, currency, exchange rate, period, and ledger entity all belong to the explicit entity.

- [ ] **Step 2: Run and verify RED**

Expected: failure at a direct `Auth::user()->entity` lookup.

- [ ] **Step 3: Remove transaction Auth fallbacks**

Resolve once:

```php
$entity = $this->entity_id
    ? $this->entity
    : app(EntityContext::class)->requireEntity();
```

Use the resolved entity consistently for default rate, currency, period, and numbering.

- [ ] **Step 4: Run and verify GREEN**

- [ ] **Step 5: Add mismatch tests for main account and line-item accounts**

If these require behavior beyond the approved milestone, record them for the atomic-posting milestone rather than broadening this PR.

- [ ] **Step 6: Run relevant transaction tests**

```bash
vendor/bin/phpunit tests/Feature/ExplicitEntityTransactionTest.php tests/Unit/TransactionTest.php tests/Unit/JournalEntryTest.php
```

- [ ] **Step 7: Commit**

```bash
git add src/Models/Transaction.php tests/Feature/ExplicitEntityTransactionTest.php
git commit -m "fix: resolve transactions from entity context"
```

### Task 8: Migrate model helpers and recycling

**Files:**

- Modify: `src/Models/Account.php`
- Modify: `src/Models/Balance.php`
- Modify: `src/Models/Currency.php`
- Modify: `src/Models/ReportingPeriod.php`
- Modify: `src/Traits/Recycling.php`
- Modify: relevant existing unit tests

- [ ] **Step 1: Locate and list every remaining direct Auth entity read**

Run:

```bash
rg -n "Auth::user\\(\\)->entity|Auth::user\\(\\)" src
```

- [ ] **Step 2: Write one failing test per distinct behavior**

Group only genuinely identical resolution behavior. Cover account aggregate helpers, balance save, currency creation, reporting-period helpers, and recycling metadata.

- [ ] **Step 3: Run each test to verify RED**

- [ ] **Step 4: Replace each fallback with explicit entity or `EntityContext`**

Do not change financial calculations.

- [ ] **Step 5: Run focused tests after each model**

- [ ] **Step 6: Commit**

```bash
git add src/Models src/Traits/Recycling.php tests
git commit -m "fix: remove auth context from ledger models"
```

### Task 9: Migrate reports to explicit context

**Files:**

- Modify: `src/Reports/FinancialStatement.php`
- Modify: `src/Reports/IncomeStatement.php`
- Modify: `src/Reports/AgingSchedule.php`
- Create: `tests/Feature/ExplicitEntityReportTest.php`

- [ ] **Step 1: Write failing unauthenticated report tests**

Verify explicit entity arguments work without Auth and omitted entity arguments resolve the active context.

- [ ] **Step 2: Run and verify RED**

- [ ] **Step 3: Replace report Auth fallbacks with `EntityContext`**

- [ ] **Step 4: Run focused report suites**

```bash
vendor/bin/phpunit tests/Feature/ExplicitEntityReportTest.php tests/Feature/BalanceSheetTest.php tests/Feature/IncomeStatementTest.php tests/Feature/TrialBalanceTest.php tests/Feature/CashflowStatementTest.php
```

- [ ] **Step 5: Verify only `AuthEntityResolver` contains entity-resolution Auth calls**

```bash
rg -n "Auth::user\\(\\)->entity" src
```

Expected: only `src/Context/AuthEntityResolver.php`.

- [ ] **Step 6: Commit**

```bash
git add src/Reports tests/Feature/ExplicitEntityReportTest.php
git commit -m "fix: resolve reports through entity context"
```

## Chunk 4: Compatibility, documentation, and release verification

### Task 10: Prove legacy fallback is opt-in

**Files:**

- Modify: `tests/Unit/EntityContextTest.php`
- Modify: `tests/Unit/EntityContextScopeTest.php`

- [ ] **Step 1: Add strict-mode authenticated-user test**

Authenticate a user but leave explicit context empty with `NullEntityResolver`; expect `MissingEntityContext`.

- [ ] **Step 2: Verify RED if current binding incorrectly falls back**

- [ ] **Step 3: Add Auth-resolver compatibility test**

Configure `AuthEntityResolver`, rebuild the scoped binding, authenticate a user, and assert legacy queries resolve that user's entity.

- [ ] **Step 4: Add explicit-over-Auth precedence test**

- [ ] **Step 5: Run and verify GREEN**

- [ ] **Step 6: Commit**

```bash
git add tests/Unit/EntityContextTest.php tests/Unit/EntityContextScopeTest.php
git commit -m "test: verify opt-in auth compatibility"
```

### Task 11: Document migration and release behavior

**Files:**

- Modify: `README.md`
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Document strict setup**

Include container resolution, `runForEntity()`, nested contexts, exceptions, queue/webhook usage, and explicit authorization responsibility.

- [ ] **Step 2: Document temporary Auth compatibility**

Mark it transitional and inappropriate for Hadhiya production.

- [ ] **Step 3: Add migration examples replacing Auth impersonation and global-scope reflection**

- [ ] **Step 4: Add a 6.1 unreleased changelog section**

- [ ] **Step 5: Run documentation formatting checks if configured**

- [ ] **Step 6: Commit**

```bash
git add README.md CHANGELOG.md
git commit -m "docs: explain explicit entity contexts"
```

### Task 12: Complete verification

**Files:** No intended source changes.

- [ ] **Step 1: Install locked dependencies**

```bash
composer install
```

- [ ] **Step 2: Run style checks available in Composer scripts**

Inspect `composer.json` and run the configured lint command. Do not invent a new formatter in this milestone.

- [ ] **Step 3: Run the complete package suite**

```bash
vendor/bin/phpunit
```

Expected: all tests pass without warnings introduced by this branch.

- [ ] **Step 4: Run static analysis if configured**

```bash
vendor/bin/phpstan analyse
```

- [ ] **Step 5: Run context leak tests repeatedly**

```bash
vendor/bin/phpunit tests/Unit/EntityContextTest.php --repeat 20
```

If the installed PHPUnit version does not support `--repeat`, loop the command in CI or add a deterministic sequential test.

- [ ] **Step 6: Inspect the diff**

```bash
git diff master...HEAD --check
git status --short
rg -n "Auth::user\\(\\)->entity" src
```

- [ ] **Step 7: Update Hadhiya integration notes**

List each workaround that can be removed in the subsequent Hadhiya migration PR. Do not change Hadhiya in this package branch.

- [ ] **Step 8: Commit any verification-only documentation correction**

- [ ] **Step 9: Push and open a draft pull request**

PR title:

```text
feat: add strict explicit entity context
```

The PR description must include scope, compatibility mode, tests, known non-goals, and the follow-up atomic-posting milestone.
