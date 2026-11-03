<?php

// Ensure serverless storage folders exist in /tmp
if (isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL']) || getenv('VERCEL')) {
    $paths = [
        '/tmp/storage',
        '/tmp/storage/app',
        '/tmp/storage/app/public',
        '/tmp/storage/framework',
        '/tmp/storage/framework/cache',
        '/tmp/storage/framework/cache/data',
        '/tmp/storage/framework/sessions',
        '/tmp/storage/framework/views',
        '/tmp/storage/logs',
    ];

    foreach ($paths as $path) {
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
    }
}

// Forward to standard Laravel entry point
require __DIR__ . '/../public/index.php';
