<?php

use Illuminate\Support\Facades\Storage;

if (! function_exists('app_public_base_path')) {
    /**
     * Web path prefix to the Laravel public directory (no trailing slash).
     *
     * Example for XAMPP: /laravel/myApp/public
     */
    function app_public_base_path(): string
    {
        $configured = trim(str_replace('\\', '/', (string) config('app.public_path_url', '')), '/');

        if ($configured !== '') {
            return '/'.$configured;
        }

        if (! app()->bound('request')) {
            return '';
        }

        $request = request();
        $baseUrl = trim((string) $request->getBaseUrl(), '/');

        if ($baseUrl !== '') {
            return '/'.$baseUrl;
        }

        $scriptName = str_replace('\\', '/', (string) $request->getScriptName());

        if ($scriptName !== '' && str_ends_with($scriptName, '/index.php')) {
            $derived = trim(substr($scriptName, 0, -strlen('/index.php')), '/');

            if ($derived !== '') {
                return '/'.$derived;
            }
        }

        return '';
    }
}

if (! function_exists('public_storage_url')) {
    /**
     * Build a browser URL for a file on the public storage disk.
     *
     * Uses the current request host and public path so images work when the app
     * runs in a subdirectory (e.g. XAMPP) or when opened from another device via LAN IP.
     */
    function public_storage_url(?string $path, bool $checkExists = true): ?string
    {
        $path = trim(str_replace('\\', '/', (string) $path));

        if ($path === '') {
            return null;
        }

        if ($checkExists && ! Storage::disk('public')->exists($path)) {
            return null;
        }

        if (app()->bound('router') && \Illuminate\Support\Facades\Route::has('storage.media')) {
            $absolute = app()->bound('request') && request()->getHost() !== '';

            return route('storage.media', ['path' => $path], $absolute);
        }

        $storagePath = 'storage/'.$path;

        if (app()->bound('request') && request()->getHost() !== '') {
            $root = rtrim(request()->getSchemeAndHttpHost().app_public_base_path(), '/');

            return $root.'/'.ltrim($storagePath, '/');
        }

        return asset($storagePath);
    }
}
