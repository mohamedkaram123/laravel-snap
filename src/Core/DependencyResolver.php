<?php

namespace Mkaram\Snap\Core;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class DependencyResolver
{
    public function __construct(
        protected Filesystem $files,
        protected string $basePath
    ) {}

    /**
     * Detect external Composer packages used by a set of files.
     */
    public function detectDependencies(array $fileRelativePaths): array
    {
        $installedMap = $this->buildNamespaceToPackageMap();
        if (empty($installedMap)) {
            return [];
        }

        $detectedPackages = [];

        foreach ($fileRelativePaths as $relativePath) {
            $fullPath = $this->basePath . '/' . $relativePath;
            if (! $this->files->exists($fullPath)) {
                continue;
            }

            $content = $this->files->get($fullPath);
            $importedNamespaces = $this->extractImportedNamespaces($content);

            foreach ($importedNamespaces as $ns) {
                foreach ($installedMap as $prefix => $packageName) {
                    if (str_starts_with($ns, $prefix)) {
                        // Exclude Snap itself and Laravel core
                        if (! in_array($packageName, ['mkaram/laravel-snap', 'laravel/framework'], true)) {
                            $detectedPackages[$packageName] = true;
                        }
                    }
                }
            }
        }

        return array_keys($detectedPackages);
    }

    /**
     * Return required packages that are not installed in the current project.
     */
    public function getMissingPackages(array $requiredPackages): array
    {
        if (empty($requiredPackages)) {
            return [];
        }

        $composerJsonPath = $this->basePath . '/composer.json';
        if (! $this->files->exists($composerJsonPath)) {
            return $requiredPackages;
        }

        $composerData = json_decode($this->files->get($composerJsonPath), true) ?? [];
        $installed = array_merge(
            array_keys($composerData['require'] ?? []),
            array_keys($composerData['require-dev'] ?? [])
        );

        return array_values(array_diff($requiredPackages, $installed));
    }

    /**
     * Build a map of PSR-4 namespace prefixes to Composer package names from installed.json.
     */
    protected function buildNamespaceToPackageMap(): array
    {
        $installedJsonPath = $this->basePath . '/vendor/composer/installed.json';
        if (! $this->files->exists($installedJsonPath)) {
            return [];
        }

        $data = json_decode($this->files->get($installedJsonPath), true);
        $packages = $data['packages'] ?? $data ?? [];

        $map = [];

        foreach ($packages as $pkg) {
            $name = $pkg['name'] ?? null;
            if (! $name) {
                continue;
            }

            $autoload = $pkg['autoload'] ?? [];
            $psr4 = $autoload['psr-4'] ?? [];
            foreach ($psr4 as $namespacePrefix => $path) {
                $cleanPrefix = rtrim($namespacePrefix, '\\') . '\\';
                $map[$cleanPrefix] = $name;
            }
        }

        return $map;
    }

    /**
     * Extract namespaces imported via `use` statements.
     */
    protected function extractImportedNamespaces(string $code): array
    {
        $matches = [];
        preg_match_all('/^\s*use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+[A-Za-z0-9_]+)?\s*;/m', $code, $matches);

        $namespaces = [];
        $appNamespace = rtrim(app()->getNamespace(), '\\');

        foreach ($matches[1] ?? [] as $ns) {
            $trimmed = trim($ns, '\\');
            // Exclude application classes and Laravel/Symfony core
            if (! str_starts_with($trimmed, $appNamespace . '\\') &&
                ! str_starts_with($trimmed, 'App\\') &&
                ! str_starts_with($trimmed, 'Illuminate\\') &&
                ! str_starts_with($trimmed, 'Symfony\\')) {
                $namespaces[] = $trimmed;
            }
        }

        return array_unique($namespaces);
    }
}