<?php

namespace Mkaram\Snap\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Mkaram\Snap\Core\LayerScanner;
use Mkaram\Snap\Core\SnapshotInstaller;
use Mkaram\Snap\Core\SnapshotPacker;
use Throwable;

class SnapMcpCommand extends Command
{
    protected $signature = 'snap:mcp';
    protected $description = 'Start the Model Context Protocol (MCP) JSON-RPC stdio server for AI tools';

    public function handle(Filesystem $files): int
    {
        // Suppress normal console output to keep the JSON-RPC stdio connection clean
        while (! feof(STDIN)) {
            $line = fgets(STDIN);
            if (! $line || trim($line) === '') {
                continue;
            }

            $request = json_decode($line, true);
            if (! $request || ! isset($request['method'])) {
                continue;
            }

            $response = $this->routeMcpRequest($request, $files);
            if ($response !== null) {
                fwrite(STDOUT, json_encode($response, JSON_UNESCAPED_SLASHES) . "\n");
                fflush(STDOUT);
            }
        }

        return self::SUCCESS;
    }

    protected function routeMcpRequest(array $req, Filesystem $files): ?array
    {
        $id = $req['id'] ?? null;
        $method = $req['method'];

        return match ($method) {
            'initialize' => [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'protocolVersion' => '2024-11-05',
                    'serverInfo' => ['name' => 'laravel-snap', 'version' => '1.0.0'],
                    'capabilities' => ['tools' => new \stdClass()],
                ],
            ],
            'tools/list' => [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'tools' => $this->getToolsSchema(),
                ],
            ],
            'tools/call' => $this->handleToolCall($id, $req['params'] ?? [], $files),
            default => [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => ['code' => -32601, 'message' => "Method [{$method}] not found"],
            ],
        };
    }

    protected function handleToolCall($id, array $params, Filesystem $files): array
    {
        $toolName = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];
        $storagePath = config('snap.storage_path');

        try {
            $resultText = match ($toolName) {
                'snap_list_patterns' => $this->toolListPatterns($files, $storagePath),
                'snap_install_pattern' => $this->toolInstallPattern($files, $storagePath, $args),
                'snap_capture_pattern' => $this->toolCapturePattern($files, $storagePath, $args),
                default => throw new \InvalidArgumentException("Unknown tool [{$toolName}]"),
            };

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [['type' => 'text', 'text' => $resultText]],
                ],
            ];
        } catch (Throwable $e) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'isError' => true,
                    'content' => [['type' => 'text', 'text' => 'Error: ' . $e->getMessage()]],
                ],
            ];
        }
    }

    protected function toolListPatterns(Filesystem $files, string $storagePath): string
    {
        if (! $files->isDirectory($storagePath)) {
            return 'No patterns storage found.';
        }

        $patterns = [];
        foreach ($files->directories($storagePath) as $dir) {
            $manifestPath = $dir . '/manifest.json';
            if ($files->exists($manifestPath)) {
                $patterns[] = json_decode($files->get($manifestPath), true);
            }
        }

        return json_encode($patterns, JSON_PRETTY_PRINT);
    }

    protected function toolInstallPattern(Filesystem $files, string $storagePath, array $args): string
    {
        $name = $args['pattern_name'] ?? '';
        $force = (bool) ($args['force'] ?? false);
        $layers = isset($args['layers']) ? (array) $args['layers'] : [];

        $installer = new SnapshotInstaller($files, base_path(), $storagePath);
        $results = $installer->install($name, $layers, $force);

        return json_encode(['status' => 'success', 'installed' => $results], JSON_PRETTY_PRINT);
    }

    protected function toolCapturePattern(Filesystem $files, string $storagePath, array $args): string
    {
        $name = $args['pattern_name'] ?? '';
        $skeleton = (bool) ($args['skeleton'] ?? false);

        $scanner = new LayerScanner($files, base_path());
        $layers = $scanner->scan($name);

        $packer = new SnapshotPacker($files, base_path(), $storagePath);
        $savedPath = $packer->pack($name, $layers, $skeleton);

        return json_encode(['status' => 'success', 'saved_to' => $savedPath, 'layers' => array_keys($layers)], JSON_PRETTY_PRINT);
    }

    protected function getToolsSchema(): array
    {
        return [
            [
                'name' => 'snap_list_patterns',
                'description' => 'List all architectural patterns, blueprints, and snapshots saved in local repository.',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name' => 'snap_install_pattern',
                'description' => 'Install and scaffold an architectural pattern into the active Laravel project.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'pattern_name' => ['type' => 'string', 'description' => 'Name of the pattern (e.g. wallet, otp, auth)'],
                        'force' => ['type' => 'boolean', 'description' => 'Overwrite existing project files'],
                        'layers' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Specific layers to install (e.g. ["domain", "database"])'],
                    ],
                    'required' => ['pattern_name'],
                ],
            ],
            [
                'name' => 'snap_capture_pattern',
                'description' => 'Scan and snapshot a feature from the active Laravel project into a reusable pattern.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'pattern_name' => ['type' => 'string', 'description' => 'Feature keyword to snapshot'],
                        'skeleton' => ['type' => 'boolean', 'description' => 'Strip method bodies into empty blueprints/stubs'],
                    ],
                    'required' => ['pattern_name'],
                ],
            ],
        ];
    }
}