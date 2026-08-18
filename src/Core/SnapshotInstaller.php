<?php

namespace Mkaram\Snap\Core;

use Illuminate\Filesystem\Filesystem;

class SnapshotInstaller
{
    public function __construct(
        protected Filesystem $files,
        protected string $basePath,
        protected string $storagePath
    ) {}

    /**
     * تثبيت وزرع الباترن في مشروع لارفيل الهدف
     */
    public function install(string $patternName, array $selectedLayers = [], bool $force = false): array
    {
        $patternDir = rtrim($this->storagePath, '/') . '/' . strtolower($patternName);
        $manifestPath = $patternDir . '/manifest.json';

        if (! $this->files->exists($manifestPath)) {
            throw new \RuntimeException("Manifest not found for pattern [{$patternName}].");
        }

        $manifest = json_decode($this->files->get($manifestPath), true);
        $installed = [];

        foreach ($manifest['layers'] ?? [] as $layerName => $files) {
            // تصفية الطبقات إذا تم تحديد طبقات معينة عبر --only
            if (! empty($selectedLayers) && ! in_array(strtolower($layerName), array_map('strtolower', $selectedLayers), true)) {
                continue;
            }

            foreach ($files as $file) {
                $sourceFile = $patternDir . '/' . $file['storage_path'];
                
                // قراءة المسار الأصلي من المانيفيست بمرونة
                $targetRelativePath = $file['original_path'] 
                    ?? ($file['relative_path'] 
                    ?? ($file['path'] ?? null));

                if (! $targetRelativePath) {
                    continue;
                }

                // معالجة تواريخ الـ Migrations لتجنب تعارض الأسماء وترتيب التنفيذ
                if (($file['type'] ?? '') === 'migration') {
                    $cleanedFilename = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $file['filename'] ?? basename($targetRelativePath));
                    $targetRelativePath = 'database/migrations/' . date('Y_m_d_His') . '_' . $cleanedFilename;
                    sleep(1); // لضمان عدم تطابق التوقيت بالثواني بين ملفات الميجريشن المتتالية
                }

                $targetFullPath = $this->basePath . '/' . $targetRelativePath;

                // تخطي الملف إذا كان موجوداً مسبقاً ولم يتم استخدام --force
                if ($this->files->exists($targetFullPath) && ! $force) {
                    continue;
                }

                $this->files->ensureDirectoryExists(dirname($targetFullPath));

                $content = $this->files->get($sourceFile);
                $detokenizedContent = $this->detokenizeContent($content);

                $this->files->put($targetFullPath, $detokenizedContent);

                // إرجاع المسار والطبقة بوضوح لعرضها في الجدول
                $installed[] = [
                    'layer' => strtoupper($layerName),
                    'path' => $targetRelativePath,
                ];
            }
        }

        return $installed;
    }

    /**
     * إعادة تعيين الـ Namespaces لتطابق اسم المشروع الجديد
     */
    protected function detokenizeContent(string $content): string
    {
        $appNamespace = rtrim(app()->getNamespace(), '\\');

        return str_replace(
            ['namespace {{rootNamespace}}', 'use {{rootNamespace}}\\'],
            ["namespace {$appNamespace}", "use {$appNamespace}\\"],
            $content
        );
    }
}