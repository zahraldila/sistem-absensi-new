<?php

if (! function_exists('supabase_url')) {
    function supabase_url(): ?string
    {
        $url = env('SUPABASE_URL');
        if (! $url) {
            return null;
        }

        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (! preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        return rtrim($url, '/');
    }
}

if (! function_exists('supabase_key')) {
    function supabase_key(): ?string
    {
        return env('SUPABASE_KEY');
    }
}

if (! function_exists('upload_path')) {
    function upload_path(string $path = ''): string
    {
        return storage_path('app/uploads/' . ltrim($path, '/'));
    }
}

if (! function_exists('getInitials')) {
    function getInitials(?string $name): string
    {
        $name = trim($name ?? '');
        if ($name === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $name);
        if (count($parts) === 1) {
            return strtoupper(substr($parts[0], 0, 2));
        }

        $first = strtoupper(substr($parts[0], 0, 1));
        $last = strtoupper(substr($parts[count($parts) - 1], 0, 1));

        return $first . $last;
    }
}

if (! function_exists('supabase_public_url')) {
    function supabase_public_url(?string $path, ?string $bucket = null): ?string
    {
        $path = trim($path ?? '');
        if ($path === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        $baseUrl = config('supabase.url') ?: env('SUPABASE_URL');
        if (empty(trim($baseUrl ?? ''))) {
            $projectRef = 'fxovkmcrdeezrotwqjhb'; // default fallback
            $dbUser = env('DB_USERNAME', '');
            if (str_contains($dbUser, '.')) {
                $parts = explode('.', $dbUser);
                $projectRef = end($parts);
            }
            $baseUrl = "https://{$projectRef}.supabase.co";
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'public/')) {
            $path = substr($path, strlen('public/'));
        }

        // Known buckets to auto-detect if no bucket parameter was provided
        $knownBuckets = [
            'company-assets',
            'profile-images',
            'submission-files',
            'attendance-selfies',
        ];

        if (! $bucket) {
            foreach ($knownBuckets as $known) {
                if (str_starts_with($path, $known . '/')) {
                    $bucket = $known;
                    $path = substr($path, strlen($known . '/'));
                    break;
                }
            }
        }

        $bucket = $bucket ?: config('supabase.bucket', 'profile-images');
        $bucket = trim($bucket ?: 'profile-images');

        if (str_starts_with($path, 'public/' . $bucket . '/')) {
            $path = substr($path, strlen('public/' . $bucket . '/'));
        }

        if (str_starts_with($path, $bucket . '/')) {
            $path = substr($path, strlen($bucket . '/'));
        }

        $path = ltrim($path, '/');
        if ($path === '') {
            return null;
        }

        $segments = array_map('rawurlencode', explode('/', $path));

        return rtrim($baseUrl, '/') . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . implode('/', $segments);
    }
}

if (! function_exists('supabase_submission_url')) {
    function supabase_submission_url(?string $path): ?string
    {
        $path = trim($path ?? '');
        if ($path === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        return supabase_public_url($path, 'submission-files');
    }
}

if (! function_exists('company_logo_url')) {
    function company_logo_url(?string $path = null): ?string
    {
        $path = $path ?? \App\Models\Setting::get('company_logo');
        if (! $path || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        if (str_starts_with($path, 'images/') || str_starts_with($path, 'assets/')) {
            return asset($path);
        }

        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        $bucket = config('supabase.assets_bucket', 'company-assets');
        return supabase_public_url($path, $bucket);
    }
}

