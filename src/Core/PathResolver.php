<?php

namespace Mkaram\Snap\Core;

class PathResolver
{
    /**
     * الحصول على المسار العام الموحد لتخزين الباترنز بناءً على نظام التشغيل
     */
    public static function resolveGlobalStoragePath(): string
    {
        // 1. الأولوية للمسار المخصص عبر متغير البيئة إذا وُجد
        $customPath = env('SNAP_STORAGE_PATH');
        if ($customPath) {
            return self::normalizePath($customPath);
        }

        // 2. فحص بيئة Windows
        if (self::isWindows()) {
            $userProfile = getenv('USERPROFILE') 
                ?: (getenv('HOMEDRIVE') && getenv('HOMEPATH') ? getenv('HOMEDRIVE') . getenv('HOMEPATH') : null);

            if ($userProfile) {
                return self::normalizePath($userProfile) . '/.laravel-snap/patterns';
            }
        }

        // 3. بيئات Linux / macOS / Unix
        $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? null);
        if ($home) {
            return self::normalizePath($home) . '/.laravel-snap/patterns';
        }

        // 4. مسار احتياطي عام (Fallback)
        return self::normalizePath(sys_get_temp_dir()) . '/.laravel-snap/patterns';
    }

    /**
     * فحص هل نظام التشغيل هو Windows
     */
    public static function isWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * توحيد الفواصل (Slashes) لتجنب أخطاء المسارات بين الأنظمة
     */
    public static function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}