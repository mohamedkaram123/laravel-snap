<?php

namespace Mkaram\Snap\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Mkaram\Snap\Core\LayerScanner;
use Mkaram\Snap\Core\SnapshotPacker;

class SnapPatternCommand extends Command
{
    protected $signature = 'snap:pattern 
                            {name : The name of the pattern to snapshot}
                            {--all : Snapshot all layers of the pattern}
                            {--skeleton : Strip method bodies and create blueprint stubs}
                            {--only= : Comma-separated layers to include (e.g. database,domain)}
                            {--without= : Comma-separated layers to exclude}';

    protected $description = 'Take a granular snapshot of a pattern or feature in your Laravel application';

    public function handle(Filesystem $files): int
    {
        $name = $this->argument('name');
        $isSkeleton = (bool) $this->option('skeleton');

        $scanner = new LayerScanner($files, base_path());

        $this->info("🔍 Scanning project for pattern: [{$name}]...");
        $layers = $scanner->scan($name);

        $layers = $this->filterLayers($layers);

        $totalFiles = collect($layers)->flatten(1)->count();

        if ($totalFiles === 0) {
            $this->warn("No files found matching pattern [{$name}].");
            return self::FAILURE;
        }

        if ($isSkeleton) {
            $this->comment('⚡ Skeleton Mode: Stripping method bodies into clean blueprints.');
        }

        $this->displayDiscoveredFiles($layers);

        // Pack and persist the snapshot
        $storagePath = config('snap.storage_path');
        $packer = new SnapshotPacker($files, base_path(), $storagePath);

        $savedPath = $packer->pack($name, $layers, $isSkeleton);

        $this->newLine();
        $this->info(" Snapshot successfully packaged and saved to:");
        $this->comment("👉 {$savedPath}");

        return self::SUCCESS;
    }

    protected function filterLayers(array $layers): array
    {
        if ($only = $this->option('only')) {
            $allowed = array_map('trim', explode(',', $only));
            return array_intersect_key($layers, array_flip($allowed));
        }

        if ($without = $this->option('without')) {
            $excluded = array_map('trim', explode(',', $without));
            return array_diff_key($layers, array_flip($excluded));
        }

        return $layers;
    }

    protected function displayDiscoveredFiles(array $layers): void
    {
        $rows = [];
        foreach ($layers as $layerName => $files) {
            foreach ($files as $file) {
                $rows[] = [
                    strtoupper($layerName),
                    $file['type'],
                    $file['relative_path'],
                ];
            }
        }

        $this->table(['Layer', 'Type', 'File Path'], $rows);
    }
}