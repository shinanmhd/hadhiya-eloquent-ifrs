# Explicit Entity Context Foundation Design

**Date:** 2026-07-17
**Status:** Approved
**Target branch:** `hadhiya/entity-context-foundation`
**Target release:** 6.1

## Purpose

Replace Eloquent IFRS's implicit dependency on `Auth::user()->entity` with an explicit, fail-closed accounting entity context suitable for Hadhiya's multi-entity operations, queue workers, webhooks, console commands, tests, and long-running Laravel processes.

This milestone changes entity-context resolution only. It deliberately does not redesign journal posting, transaction numbering, idempotency, fixed-precision arithmetic, ledger immutability, or the database schema.

## Goals

- Make the active accounting entity explicit and deterministic.
- Fail closed when no accounting entity is available.
- Support safe nested entity contexts for cross-entity business operations.
- Restore the previous context after normal returns and exceptions.
- Remove direct authentication dependencies from package accounting internals.
- Retain an explicitly enabled Auth compatibility resolver for migration.
- Preserve existing accounting calculations and posting behavior.
- Support Laravel 11, 12, and 13.

## Non-goals

- Authorizing whether an actor may enter an entity context.
- Coordinating multi-entity business transactions.
- Adding business-event idempotency.
- Correcting an application's chart of accounts.
- Making journal posting immutable or internally atomic.
- Replacing floating-point arithmetic.
- Certifying regulatory or accounting compliance.

Those controls belong to later package milestones or the consuming application.

## Design decisions

### Strict by default

The package will require an explicit accounting entity context by default. It must not silently derive context from the authenticated user unless the application explicitly enables the legacy Auth resolver.

When neither an explicit context nor an enabled fallback can resolve an entity, entity-bound package operations throw `MissingEntityContext`. They must never return records across all entities.

### Scoped, stack-based context

An `EntityContext` contract will expose the active entity and support scoped execution:

```php
$context->runForEntity($merchant, function () {
    // IFRS queries and creations are restricted to the merchant entity.
});
```

The implementation maintains a stack rather than a single mutable identifier. Nested contexts therefore restore their parent correctly:

```php
$context->runForEntity($platform, function () use ($context, $merchant) {
    // Platform context

    $context->runForEntity($merchant, function () {
        // Merchant context
    });

    // Platform context restored
});
```

Restoration occurs in a `finally` block so exceptions cannot leave the process in the nested context.

The context service is scoped through Laravel's container. It must not use static mutable state, global configuration mutation, or authentication impersonation.

### Resolver precedence

Resolution order is:

1. Explicit stack context.
2. Configured fallback resolver, if enabled.
3. `MissingEntityContext`.

The optional Auth resolver is disabled by default. Enabling it is an explicit migration choice and does not change the precedence of an explicit context.

### Entity mismatch protection

An entity-bound model carrying an `entity_id` different from the active context throws `EntityContextMismatch` before persistence or posting.

The package must not silently overwrite a conflicting explicit entity identifier. This prevents a caller from entering one entity context while writing accounts, transactions, balances, or ledger-related records for another.

### Global scope behavior

`EntityScope` resolves the active entity through `EntityContext`, not `Auth`.

- With context: it applies a qualified `entity_id` predicate.
- Without context: it throws `MissingEntityContext`.
- It never omits the entity predicate as a fallback.

Explicit cross-entity work is performed by entering the target context. Broad `withoutGlobalScopes()` calls are not part of the supported context API.

### Model creation

The `Segregating` trait assigns the active context's entity ID when a new entity-bound model has no `entity_id`.

If the model already has a different entity ID, creation fails with `EntityContextMismatch`.

### Transaction and reporting resolution

Transactions, accounts, balances, currencies, reporting periods, reports, and recycling behavior will resolve entity ownership through the context abstraction or an explicitly supplied `Entity`.

When a transaction has an explicit entity, all entity-derived values—including reporting period, default exchange rate, currency, and transaction numbering—must resolve from that entity rather than authentication state.

Reports may accept an explicit entity as they do today. When omitted, they use the active context and fail closed if it is absent.

## Proposed components

### `IFRS\Context\EntityContext`

Contract for:

- Getting the current entity ID.
- Getting the current `Entity`.
- Requiring an active entity.
- Executing a callback inside an entity context.

### `IFRS\Context\StackEntityContext`

Scoped implementation holding the nested entity stack and delegating to an optional fallback resolver only when the explicit stack is empty.

### `IFRS\Context\EntityResolver`

Small fallback-resolution contract. It returns an entity or `null`; it does not mutate context.

### `IFRS\Context\AuthEntityResolver`

Compatibility resolver that reads the configured authentication user model. It is registered only when enabled in configuration.

### `IFRS\Context\NullEntityResolver`

Default resolver returning `null`, ensuring strict behavior.

### `IFRS\Exceptions\MissingEntityContext`

Raised when an entity-bound operation is attempted without a resolvable entity.

### `IFRS\Exceptions\EntityContextMismatch`

Raised when a model's explicit entity ID conflicts with the active context.

## Configuration

The published configuration will expose context behavior similar to:

```php
'entity_context' => [
    'resolver' => IFRS\Context\NullEntityResolver::class,
    'fallback_to_auth' => false,
    'missing_context' => 'throw',
],
```

`fallback_to_auth` remains disabled by default. The final implementation should avoid duplicate configuration switches if the resolver class alone can express the behavior clearly.

## Error handling

- Missing context is a typed exception, not an empty query and not an unscoped query.
- Entity mismatch is a typed exception raised before a write.
- Callback exceptions propagate unchanged after context restoration.
- Resolver failures must not silently broaden access.
- Invalid resolver configuration fails during application boot or first resolution with an actionable configuration exception.

## Compatibility

The PHP namespace remains `IFRS\` for this milestone.

Existing consumers may restore legacy resolution deliberately by enabling `AuthEntityResolver`. This compatibility path is transitional and will be documented as unsuitable for Hadhiya's strict production configuration.

No database migration is required for this milestone.

## Hadhiya migration

After adopting the context-enabled release, Hadhiya can replace:

- Proxy `User` objects.
- `Auth::setUser()` entity impersonation.
- Mutation of authenticated users' `entity_id`.
- Reflection-based deletion of Eloquent global scopes.
- `config(['ifrs.entity_id' => ...])` context switching.

Hadhiya remains responsible for authorization before entering a target entity and for coordinating platform, merchant, and user journal legs.

## Testing strategy

Tests will be written before implementation and cover:

1. Missing context fails closed.
2. Explicit context restricts queries to one entity.
3. Explicit context assigns entity ID during creation.
4. Conflicting entity ID raises `EntityContextMismatch`.
5. Nested contexts restore their parent.
6. Exceptions restore the previous context.
7. Sequential simulated jobs do not leak context.
8. Strict configuration ignores authenticated users.
9. Enabled Auth fallback preserves legacy behavior.
10. Explicit context takes precedence over Auth fallback.
11. Transactions resolve currency, exchange rate, reporting period, and numbering from their explicit entity.
12. Reports use explicit or active context without depending directly on Auth.
13. The existing upstream package suite remains green on supported Laravel versions.

## Acceptance criteria

- No entity-bound query becomes unscoped because authentication is absent.
- Entity accounting internals no longer directly read `Auth::user()->entity`, except inside the opt-in compatibility resolver.
- Nested and exceptional execution restores context deterministically.
- Mismatched entity writes are rejected.
- Strict mode is the default.
- The complete existing package test suite passes.
- New context tests pass on Laravel 11, 12, and 13.
