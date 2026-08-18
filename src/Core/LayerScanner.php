<?php

namespace Mkaram\Snap\Core;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class LayerScanner
{
    public function __construct(
        protected Filesystem $files,
        protected string $basePath
    ) {}

    /**
     * Scan the project and discover all files related to the pattern.
     */
    public function scan(string $patternName): array
    {
        $studly = Str::studly($patternName);   // Otp
        $snake  = Str::snake($patternName);    // otp
        $plural = Str::plural($snake);         // otps

        return [
            'database'      => $this->scanDatabase($snake, $plural),
            'domain'        => $this->scanDomain($studly),
            'http'          => $this->scanHttp($studly),
            'notifications' => $this->scanNotifications($studly),
            'async'         => $this->scanAsync($studly),
            'security'      => $this->scanSecurity($studly),
            'config'        => $this->scanConfig($snake),
        ];
    }

    protected function scanDatabase(string $snake, string $plural): array
    {
        $files = [];
        $migrationPath = $this->basePath . '/database/migrations';

        if ($this->files->isDirectory($migrationPath)) {
            foreach ($this->files->files($migrationPath) as $file) {
                if (str_contains($file->getFilename(), $snake) || str_contains($file->getFilename(), $plural)) {
                    $files[] = [
                        'type' => 'migration',
                        'relative_path' => 'database/migrations/' . $file->getFilename(),
                        'filename' => $file->getFilename(),
                    ];
                }
            }
        }

        return $files;
    }

    protected function scanDomain(string $studly): array
    {
        return $this->scanDirectories([
            'app/Models'    => 'model',
            'app/Services'  => 'service',
            'app/Actions'   => 'action',
            'app/Contracts' => 'contract',
            'app/DTOs'      => 'dto',
            'app/Events'    => 'event',
        ], $studly);
    }

    protected function scanHttp(string $studly): array
    {
        return $this->scanDirectories([
            'app/Http/Controllers' => 'controller',
            'app/Http/Requests'    => 'request',
            'app/Http/Resources'   => 'resource',
            'app/Http/Middleware'  => 'middleware',
        ], $studly);
    }

    protected function scanNotifications(string $studly): array
    {
        return $this->scanDirectories([
            'app/Notifications' => 'notification',
            'app/Mail'          => 'mail',
        ], $studly);
    }

    protected function scanAsync(string $studly): array
    {
        return $this->scanDirectories([
            'app/Jobs'      => 'job',
            'app/Listeners' => 'listener',
        ], $studly);
    }

    protected function scanSecurity(string $studly): array
    {
        return $this->scanDirectories([
            'app/Policies' => 'policy',
            'app/Rules'    => 'rule',
        ], $studly);
    }

    protected function scanConfig(string $snake): array
    {
        $files = [];
        $configPath = $this->basePath . "/config/{$snake}.php";

        if ($this->files->exists($configPath)) {
            $files[] = [
                'type' => 'config',
                'relative_path' => "config/{$snake}.php",
                'filename' => "{$snake}.php",
            ];
        }

        return $files;
    }

    /**
     * Walk directories and collect files whose names match the search term.
     */
    protected function scanDirectories(array $directories, string $search): array
    {
        $files = [];

        foreach ($directories as $path => $type) {
            $fullPath = $this->basePath . '/' . $path;
            if ($this->files->isDirectory($fullPath)) {
                foreach ($this->files->allFiles($fullPath) as $file) {
                    if (str_contains($file->getFilename(), $search)) {
                        $files[] = [
                            'type' => $type,
                            'relative_path' => $path . '/' . $file->getRelativePathname(),
                            'filename' => $file->getFilename(),
                        ];
                    }
                }
            }
        }

        return $files;
    }
}