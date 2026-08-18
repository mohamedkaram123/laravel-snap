<?php

namespace Mkaram\Snap\Core;

use Illuminate\Filesystem\Filesystem;

class SnapshotPacker
{
    public function __construct(
        protected Filesystem $files,
        protected string $basePath,
        protected string $storagePath,
        protected ?CodeSkeletonizer $skeletonizer = null,
        protected ?DependencyResolver $dependencyResolver = null
    ) {
        $this->skeletonizer = $skeletonizer ?? new CodeSkeletonizer();
        $this->dependencyResolver = $dependencyResolver ?? new DependencyResolver($files, $basePath);
    }

    public function pack(string $patternName, array $layers, bool $skeleton = false): string
    {
        $targetDir = rtrim($this->storagePath, '/') . '/' . strtolower($patternName);

        if ($this->files->isDirectory($targetDir)) {
            $this->files->deleteDirectory($targetDir);
        }
        $this->files->makeDirectory($targetDir . '/files', 0755, true);

        $manifestLayers = [];
        $allScannedPaths = [];

        foreach ($layers as $layerName => $files) {
            $manifestLayers[$layerName] = [];

            foreach ($files as $file) {
                $allScannedPaths[] = $file['relative_path'];
                $sourceFullPath = $this->basePath . '/' . $file['relative_path'];
                $destinationRelative = 'files/' . $file['relative_path'];
                $destinationFullPath = $targetDir . '/' . $destinationRelative;

                $this->files->ensureDirectoryExists(dirname($destinationFullPath));

                $content = $this->files->get($sourceFullPath);

                if ($skeleton && ! in_array($file['type'], ['migration', 'config'])) {
                    $content = $this->skeletonizer->skeletonize($content);
                }

                $tokenizedContent = $this->tokenizeContent($content);
                $this->files->put($destinationFullPath, $tokenizedContent);

                $manifestLayers[$layerName][] = [
                    'type' => $file['type'],
                    'original_path' => $file['relative_path'],
                    'storage_path' => $destinationRelative,
                    'filename' => $file['filename'],
                ];
            }
        }

        // Automatically detect external Composer dependencies
        $dependencies = $this->dependencyResolver->detectDependencies($allScannedPaths);

        $manifest = [
            'pattern' => strtolower($patternName),
            'version' => '1.0.0',
            'is_skeleton' => $skeleton,
            'dependencies' => [
                'composer' => $dependencies,
            ],
            'created_at' => date('Y-m-d H:i:s'),
            'layers' => $manifestLayers,
        ];

        $this->files->put(
            $targetDir . '/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $targetDir;
    }

    protected function tokenizeContent(string $content): string
    {
        $appNamespace = rtrim(app()->getNamespace(), '\\');
        
        return str_replace(
            "namespace {$appNamespace}",
            'namespace {{rootNamespace}}',
            str_replace("use {$appNamespace}\\", 'use {{rootNamespace}}\\', $content)
        );
    }
}