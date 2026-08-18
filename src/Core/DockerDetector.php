<?php

namespace Mkaram\Snap\Core;

class DockerDetector
{
    /**
     * Check if the code is running inside a Docker container or Laravel Sail
     */
    public static function isRunningInDocker(): bool
    {
        // 1. Check for the default Docker internal file
        if (file_exists('/.dockerenv')) {
            return true;
        }

        // 2. Check for Laravel Sail or Docker Compose environment variables
        if (getenv('LARAVEL_SAIL') === '1' || getenv('IS_DOCKER') === 'true') {
            return true;
        }

        // 3. Check for the cgroup in Linux
        if (file_exists('/proc/1/cgroup')) {
            $cgroup = @file_get_contents('/proc/1/cgroup');
            if ($cgroup && (str_contains($cgroup, 'docker') || str_contains($cgroup, 'containerd'))) {
                return true;
            }
        }

        return false;
    }
}