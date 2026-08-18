# Cursor & Windsurf Integration (MCP)

Laravel Snap includes a native **Model Context Protocol (MCP)** server. AI tools such as **Cursor**, **Windsurf**, and Claude Code can list, inspect, capture, and install architectural patterns without leaving the editor.

The server speaks JSON-RPC over **stdio**. There is no HTTP port to open and no extra daemon to install — Artisan is the process.

## Start the server

```bash
php artisan snap:mcp
```

This command is designed to be launched by the editor, not by you in a normal terminal session. It reads JSON-RPC requests from `STDIN` and writes responses to `STDOUT`. Regular console output is suppressed so the stream stays clean.

## Cursor setup

Add a server entry to your Cursor MCP config (`.cursor/mcp.json` in the project, or the global Cursor MCP settings):

```json
{
  "mcpServers": {
    "laravel-snap": {
      "command": "php",
      "args": ["artisan", "snap:mcp"],
      "cwd": "/absolute/path/to/your/laravel-app"
    }
  }
}
```

`cwd` must be the Laravel application that has `mkaram/laravel-snap` installed so `artisan` and `config('snap.storage_path')` resolve correctly.

Restart Cursor (or reload MCP servers) after saving the config. The `laravel-snap` server should appear as connected.

## Windsurf setup

Windsurf uses the same stdio contract. Point the MCP command at Artisan:

```json
{
  "mcpServers": {
    "laravel-snap": {
      "command": "php",
      "args": ["artisan", "snap:mcp"]
    }
  }
}
```

Run this from the Laravel project root (or set the working directory in the MCP host config).

## Exposed tools

Once connected, Snap advertises tools the agent can call. Conceptually they cover **list**, **inspect**, and **install**; the registered names are:

| Tool | Purpose |
| --- | --- |
| `snap_list_patterns` | **List / inspect** every saved blueprint. Returns each pattern's `manifest.json` (name, version, skeleton flag, Composer dependencies, layers, and file paths). |
| `snap_install_pattern` | **Install** a pattern into the active Laravel project. |
| `snap_capture_pattern` | Snapshot a feature from the current app into a reusable pattern (optional skeleton mode). |

### `snap_list_patterns`

No arguments. Use it to discover what is in Snap storage and to inspect a pattern's manifest (layers, files, dependencies) before installing.

### `snap_install_pattern`

| Argument | Type | Required | Description |
| --- | --- | --- | --- |
| `pattern_name` | string | yes | Pattern id (`wallet`, `otp`, `auth`, …) |
| `force` | boolean | no | Overwrite existing project files |
| `layers` | string[] | no | Limit install to specific layers, e.g. `["domain", "database"]` |

### `snap_capture_pattern`

| Argument | Type | Required | Description |
| --- | --- | --- | --- |
| `pattern_name` | string | yes | Feature keyword to scan and snapshot |
| `skeleton` | boolean | no | Strip method bodies into empty blueprints |

## Example agent flow

1. Agent calls `snap_list_patterns` and reads manifests (inspect).
2. You ask: “Install the wallet pattern, domain and database only.”
3. Agent calls `snap_install_pattern` with `pattern_name: "wallet"` and `layers: ["domain", "database"]`.
4. Snap writes files, rebinds namespaces, and regenerates migration timestamps in the active project.

For a new blueprint from the current codebase, ask the agent to capture with `snap_capture_pattern` (optionally `skeleton: true` so it implements method bodies against typed stubs). See [AST Skeleton Mode](../02-core-concepts/ast-skeleton-mode.md).

## Protocol details

- Transport: JSON-RPC 2.0 over stdio
- Protocol version: `2024-11-05`
- Server name: `laravel-snap`
- Methods handled: `initialize`, `tools/list`, `tools/call`

You do not need to speak this protocol by hand. Cursor and Windsurf issue the calls when the MCP server is configured.
