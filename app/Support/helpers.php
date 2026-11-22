<?php

use App\Support\Settings;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

if (! function_exists('settings')) {
    function settings(): Settings
    {
        return app(Settings::class);
    }
}

if (! function_exists('media_url')) {
    /**
     * Seeded images live in public/images, uploads on the public disk.
     */
    function media_url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return str_starts_with($path, 'images/') ? asset($path) : Storage::disk('public')->url($path);
    }
}

if (! function_exists('rich_text')) {
    /**
     * Renders (and sanitises) HTML from Filament's rich editor.
     */
    function rich_text(?string $html): HtmlString
    {
        return \App\Support\RichContent::rich($html);
    }
}

if (! function_exists('youtube_id')) {
    function youtube_id(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        if (preg_match('~^[A-Za-z0-9_-]{11}$~', $url)) {
            return $url;
        }

        return preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:embed/|shorts/|live/|watch\?(?:.*&)?v=))([A-Za-z0-9_-]{11})~', $url, $m) ? $m[1] : null;
    }
}
