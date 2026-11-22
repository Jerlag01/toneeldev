<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A website page built from blocks (see App\Filament\Resources\Pages\PageResource::blocks()).
 * The page with slug "home" is the story on the front page.
 */
class Page extends Model
{
    use HasFactory;

    public const HOME_SLUG = 'home';

    protected $fillable = ['title', 'slug', 'seo_title', 'meta_description', 'share_image', 'blocks', 'is_published', 'show_in_menu', 'menu_order'];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'is_published' => 'boolean',
            'show_in_menu' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function isHome(): bool
    {
        return $this->slug === self::HOME_SLUG;
    }

    public function url(): string
    {
        return $this->isHome() ? route('home') : route('page', $this->slug);
    }

    public function hasBlock(string $type): bool
    {
        return collect($this->blocks ?? [])->contains(fn (array $block): bool => ($block['type'] ?? null) === $type);
    }

    /**
     * The section headings with an anchor, for the "jump to" navigation of the story.
     *
     * @return list<array{anchor: string, title: string}>
     */
    public function chapters(): array
    {
        return collect($this->blocks ?? [])
            ->filter(fn (array $block): bool => ($block['type'] ?? null) === 'chapter' && filled($block['data']['anchor'] ?? null) && ($block['data']['in_nav'] ?? true))
            ->map(fn (array $block): array => ['anchor' => $block['data']['anchor'], 'title' => ($block['data']['nav_title'] ?? null) ?: ($block['data']['title'] ?? $block['data']['anchor'])])
            ->values()
            ->all();
    }
}
