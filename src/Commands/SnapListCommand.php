<?php

namespace Mkaram\Snap\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class SnapListCommand extends Command
{
    protected $signature = 'snap:list';
    protected $description = 'List all locally available snapshot patterns and blueprints';

    public function handle(Filesystem $files): int
    {
        $storagePath = config('snap.storage_path');

        if (! $files->isDirectory($storagePath)) {
            $this->warn("No snapshot storage directory found at [{$storagePath}].");
            return self::SUCCESS;
        }

        $directories = $files->directories($storagePath);

        if (empty($directories)) {
            $this->info("No patterns found in [{$storagePath}].");
            return self::SUCCESS;
        }

        $rows = [];

        foreach ($directories as $dir) {
            $manifestPath = $dir . '/manifest.json';
            if ($files->exists($manifestPath)) {
                $manifest = json_decode($files->get($manifestPath), true);
                $layers = array_keys($manifest['layers'] ?? []);
                $filesCount = collect($manifest['layers'] ?? [])->flatten(1)->count();
                $isSkeleton = ! empty($manifest['is_skeleton']) ? '⚡ Skeleton' : '📦 Full';

                $rows[] = [
                    $manifest['pattern'] ?? basename($dir),
                    $manifest['version'] ?? '1.0.0',
                    $isSkeleton,
                    implode(', ', $layers),
                    $filesCount,
                    $manifest['created_at'] ?? 'N/A',
                ];
            }
        }

        $this->info("📂 Storage Location: {$storagePath}");
        $this->newLine();
        $this->table(['Pattern', 'Version', 'Type', 'Layers Available', 'Files', 'Created At'], $rows);

        return self::SUCCESS;
    }
}