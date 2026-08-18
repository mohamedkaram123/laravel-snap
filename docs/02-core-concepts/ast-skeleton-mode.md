# AST Skeleton Mode

The `--skeleton` flag turns a captured feature into an **architectural blueprint**: class structure without implementation.

This is powered by PHP's Abstract Syntax Tree (AST) via [`nikic/php-parser`](https://github.com/nikic/PHP-Parser). Snap does not use regex or string chopping. It parses the file, walks method nodes, and rewrites method bodies while leaving the surrounding type surface intact.

## When to use it

Use skeleton mode when you want the *shape* of a feature, not its business logic:

- Scaffolding the same architecture in a new domain (Wallet → Points, OTP → Magic Link)
- Handing a typed contract to an AI agent that will fill in method bodies
- Sharing an internal pattern library without leaking proprietary implementation
- Generating domain interfaces, services, and controllers that already match your team's layering

Capture a full snapshot (default) when you want to transplant working code as-is.

```bash
php artisan snap:pattern wallet --skeleton
```

## What is preserved

Skeletonization keeps the parts of a class that define its public contract:

- Class, interface, and trait declarations
- Constructor and method **signatures**
- Parameter types and default values
- Return types (including `void`, scalars, arrays, and nullable types)
- Docblocks and property declarations
- `use` imports and namespaced structure

Migrations and config files are **not** skeletonized. Schema and configuration are structural, not method-body logic, so they are copied as-is.

## What is stripped

For every concrete class method that has a body, Snap replaces the statements inside with a stub:

- Methods with no return type, or a `void` return type, become an empty body with a `TODO` docblock.
- Methods with a declared return type get a type-safe placeholder `return` (`false`, `0`, `0.0`, `''`, `[]`, or `null`).
- Nullable return types (`?Model`, `?string`, …) return `null`.
- Abstract methods and interface methods are left untouched — they have no body to strip.

Example **before**:

```php
public function credit(int $amount): Wallet
{
    $this->balance += $amount;
    $this->save();

    event(new WalletCredited($this, $amount));

    return $this;
}
```

Example **after** (`--skeleton`):

```php
/**
 * TODO: Implement method logic.
 */
public function credit(int $amount): Wallet
{
    return null;
}
```

The method name, parameters, and return type remain. The business rules do not.

## Why this is ideal for AI agents

A skeleton snapshot is a constrained prompt surface:

1. **Typed contracts** — agents see exact signatures and return types instead of inventing APIs.
2. **Layered architecture** — models, actions, controllers, jobs, and policies arrive in the right folders with the right names.
3. **Safe defaults** — stub returns compile; the agent can replace them method by method.
4. **No leaked logic** — you share structure, not proprietary algorithms.

Pair skeleton capture with the [MCP server](../04-ai-and-mcp/cursor-integration.md) so Cursor or Windsurf can install a blueprint and implement the TODOs in-place.

## How it works internally

1. `nikic/php-parser` builds an AST for the PHP file (`ParserFactory::createForHostVersion()`).
2. A node visitor targets `ClassMethod` nodes that have statements (`$node->stmts !== null`).
3. Method bodies are replaced with stub statements derived from the return type.
4. A pretty printer emits valid PHP. If parsing fails, the original source is kept unchanged.

The resulting files are tokenized for namespaces and stored with `"is_skeleton": true` in `manifest.json`.
