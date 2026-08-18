# Introduction

**Laravel Snap** is a granular pattern capture and scaffolding engine for Laravel. It snapshots a feature from one application — models, services, HTTP layer, migrations, notifications, and more — then transplants that architecture into another project as a reusable blueprint.

Think of it as a surgical alternative to copying folders by hand. If you have a `Wallet`, `OTP`, `Subscription`, or `Invoicing` feature that you keep rebuilding, Snap packages it once and plants it wherever you need it.

## The problem it solves

Moving a feature between Laravel apps is usually messy:

| Pain | What actually happens |
| --- | --- |
| **Scattered files** | A single feature lives across `app/Models`, `app/Services`, `app/Http`, `database/migrations`, `app/Jobs`, `config/`, and more. Missing one file breaks the rest. |
| **Namespace collisions** | Source projects use `App\...` or a custom root namespace. Pasting files into a new app leaves stale imports and broken PSR-4 autoloading. |
| **Broken migrations** | Copied migration filenames keep the original timestamps, so they collide or run in the wrong order. |
| **Missing Composer dependencies** | Third-party packages (Stripe, Twilio, QR libraries, and so on) are imported via `use` but never declared in the target `composer.json`. You discover that as `Class not found`. |

Laravel Snap addresses each of these at capture time and again at install time.

## What Snap does

1. **Discovers** every file that belongs to a named pattern by scanning Laravel layers (domain, HTTP, database, notifications, async, security, config).
2. **Packages** those files into a portable snapshot with a `manifest.json`.
3. **Tokenizes** the application root namespace so the blueprint is not locked to `App`.
4. **Records** external Composer packages required by the captured code.
5. **Installs** the pattern into a target Laravel app: files are written, namespaces are rebound, migration timestamps are regenerated, and missing packages can be installed interactively.

You can capture a **full** snapshot (logic included) or a **skeleton** blueprint (signatures only) that is ideal for scaffolding and AI-assisted implementation.

## Typical workflow

```bash
# In the source application: capture the Wallet feature
php artisan snap:pattern wallet --all

# Inspect what is stored locally
php artisan snap:list

# In the target application: plant the same architecture
php artisan snap:install wallet
```

For AI-driven workflows, Snap also ships a native Model Context Protocol (MCP) server so Cursor, Windsurf, and similar tools can list, capture, and install patterns over `stdio`.

## Next steps

- [AST Skeleton Mode](../02-core-concepts/ast-skeleton-mode.md) — strip method bodies while keeping a typed class surface
- [Dependency Resolution](../02-core-concepts/dependency-resolution.md) — how Composer packages travel with a snapshot
- [CLI Reference](../03-cli-reference/commands.md) — `snap:pattern`, `snap:install`, and `snap:list`
- [Cursor & MCP Integration](../04-ai-and-mcp/cursor-integration.md) — expose Snap to AI agents
