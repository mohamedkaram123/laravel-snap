<?php

use Mkaram\Snap\Core\PathResolver;

return [
    /*
    |--------------------------------------------------------------------------
    | Global Patterns Storage Path
    |--------------------------------------------------------------------------
    |
    | Global system path for storing and sharing patterns between all Laravel projects on the machine.
    | بين جميع مشاريع لارفيل الموجودة على الجهاز.
    |
    */
    'storage_path' => PathResolver::resolveGlobalStoragePath(),
];