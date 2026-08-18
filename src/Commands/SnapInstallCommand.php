<?php

namespace Mkaram\Snap\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Mkaram\Snap\Core\DependencyResolver;
use Mkaram\Snap\Core\SnapshotInstaller;
use Symfony\Component\Process\Process;

class SnapInstallCommand extends Command
{
    protected $signature = 'snap:install 
                            {name : The name of the pattern to install}
                            {--only= : Comma-separated layers to install (e.g. domain,database)}
                            {--force : Overwrite existing files without asking}';

    protected $description = 'Install and integrate a captured pattern into your Laravel application';

    public function handle(Filesystem $files): int
    {
        $name = strtolower($this->argument('name'));
        $storagePath = config('snap.storage_path');
        $patternDir = rtrim($storagePath, '/') . '/' . $name;
        $manifestPath = $patternDir . '/manifest.json';

        if (! $files->exists($manifestPath)) {
            $this->error("Pattern [{$name}] not found in [{$storagePath}].");
            return self::FAILURE;
        }

        $manifest = json_decode($files->get($manifestPath), true);

        // 1. فحص التبعات الخارجية
        $requiredPackages = $manifest['dependencies']['composer'] ?? [];
        $resolver = new DependencyResolver($files, base_path());
        $missingPackages = $resolver->getMissingPackages($requiredPackages);

        if (! empty($missingPackages)) {
            $this->newLine();
            $this->warn('⚠️  External Composer Dependencies Required:');
            foreach ($missingPackages as $pkg) {
                $this->line("   • <comment>{$pkg}</comment>");
            }

            if ($this->confirm('Would you like Snap to install missing packages via Composer now?', true)) {
                $this->info('⏳ Running composer require...');
                $process = new Process(array_merge(['composer', 'require'], $missingPackages), base_path());
                $process->setTimeout(300);
                $process->run(function ($type, $buffer) {
                    $this->output->write($buffer);
                });

                if (! $process->isSuccessful()) {
                    $this->error('Failed to install Composer packages. You can install them manually.');
                }
            } else {
                $this->comment('💡 You can install them manually later using:');
                $this->line('   composer require ' . implode(' ', $missingPackages));
            }
        }

        // 2. التثبيت والزرع في المشروع
        $this->newLine();
        $this->info("🚀 Installing pattern [{$name}] into the application...");

        $layers = $this->option('only') ? array_map('trim', explode(',', $this->option('only'))) : [];
        $force = (bool) $this->option('force');

        $installer = new SnapshotInstaller($files, base_path(), $storagePath);
        $installedFiles = $installer->install($name, $layers, $force);

        if (empty($installedFiles)) {
            $this->warn('No files were installed (files may already exist. Use --force to overwrite).');
            return self::SUCCESS;
        }

        // بناء صفوف الجدول بشكل آمن ومرن
        $rows = [];
        foreach ($installedFiles as $file) {
            if (is_array($file)) {
                $filePath = $file['path'] 
                    ?? $file['relative_path'] 
                    ?? $file['original_path'] 
                    ?? $file['target_path'] 
                    ?? 'N/A';

                $rows[] = [
                    $file['layer'] ?? 'General',
                    $filePath,
                    '<info>Installed</info>',
                ];
            } else {
                $rows[] = [
                    'General',
                    (string) $file,
                    '<info>Installed</info>',
                ];
            }
        }

        $this->table(['Layer', 'Target File Path', 'Status'], $rows);
        $this->info("✔ Pattern [{$name}] installed successfully!");

        return self::SUCCESS;
    }
}