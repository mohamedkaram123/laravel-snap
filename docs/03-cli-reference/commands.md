# CLI Reference

Laravel Snap ships three primary Artisan commands: capture, install, and list. Run them from the Laravel application root.

Patterns are stored on the machine (or a path you set with `SNAP_STORAGE_PATH`) so they can be reused across projects.

---

## `snap:pattern`

Extract a feature from the current application into a reusable snapshot.

```bash
php artisan snap:pattern {name} [--all] [--skeleton] [--only=] [--without=]
```

| Argument / option | Description |
| --- | --- |
| `{name}` | Feature keyword to search for (e.g. `wallet`, `otp`, `invoice`). Snap matches studly, snake, and plural forms across Laravel layers. |
| `--all` | Include every discovered layer of the pattern. |
| `--skeleton` | Strip method bodies into typed blueprint stubs. See [AST Skeleton Mode](../02-core-concepts/ast-skeleton-mode.md). |
| `--only=` | Comma-separated layers to include (`database`, `domain`, `http`, `notifications`, `async`, `security`, `config`). |
| `--without=` | Comma-separated layers to exclude. |

### Examples

```bash
# Capture every layer of the Wallet feature
php artisan snap:pattern wallet --all

# Capture OTP as a typed blueprint (no method bodies)
php artisan snap:pattern otp --skeleton

# Capture only domain + database for Invoicing
php artisan snap:pattern invoice --only=domain,database
```

Snap prints a table of discovered files (layer, type, path), packages the snapshot, and writes it to storage with a `manifest.json`.

---

## `snap:install`

Plant a saved snapshot into the current Laravel application.

```bash
php artisan snap:install {name} [--force] [--only=]
```

| Argument / option | Description |
| --- | --- |
| `{name}` | Pattern name as stored (lowercase, e.g. `wallet`). |
| `--force` | Overwrite files that already exist in the target project. Without this flag, existing files are skipped. |
| `--only=` | Install a subset of layers (e.g. `domain,database`). |

### Examples

```bash
# Install the Wallet pattern into this app
php artisan snap:install wallet

# Overwrite files if they already exist
php artisan snap:install wallet --force
```

Install does more than copy files:

1. Reads `manifest.json` and [resolves missing Composer packages](../02-core-concepts/dependency-resolution.md), offering `composer require` interactively.
2. Rebinds `{{rootNamespace}}` tokens to the target app namespace.
3. Regenerates migration timestamps so schema runs in a safe order.
4. Writes files into the matching Laravel directories and prints a result table.

---

## `snap:list`

View every blueprint saved in local Snap storage.

```bash
php artisan snap:list
```

Output includes:

- Pattern name and version
- Type (`Full` or `Skeleton`)
- Layers available
- File count
- Created timestamp
- Storage location

Use this to confirm a capture succeeded before installing into another project, or to see which blueprints an [MCP-connected agent](../04-ai-and-mcp/cursor-integration.md) can work with.

```bash
php artisan snap:list
```

---

## Related

The MCP stdio server is started separately with `php artisan snap:mcp`. It is intended for AI tools, not interactive terminal use. See [Cursor & MCP Integration](../04-ai-and-mcp/cursor-integration.md).
