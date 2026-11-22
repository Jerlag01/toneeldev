<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A play (toneelstuk) with its performances, cover, gallery and a page built from blocks.
 * Upcoming = the last performance day is today or later.
 */
class Production extends Model
{
    protected $fillable = [
        'title', 'slug', 'topic', 'summary', 'cover_image', 'starts_on', 'ends_on', 'date_label', 'performances', 'venue',
        'ticket_url', 'charity', 'gallery', 'blocks', 'meta_description', 'is_published',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'performances' => 'array',
            'gallery' => 'array',
            'blocks' => 'array',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // The overview sorts on starts_on/ends_on, derived from the performances when there are any.
        static::saving(function (Production $production): void {
            $dates = collect($production->performances ?? [])->pluck('date')->filter()->map(fn ($date) => Carbon::parse($date))->sort()->values();

            if ($dates->isNotEmpty()) {
                $production->starts_on = $dates->first();
                $production->ends_on = $dates->last();
            } elseif ($production->starts_on && ! $production->ends_on) {
                $production->ends_on = $production->starts_on;
            }
        });
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->whereDate('ends_on', '>=', today())->orderBy('starts_on');
    }

    public function scopePast(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->whereDate('ends_on', '<', today())->orWhereNull('ends_on'))->orderByDesc('starts_on');
    }

    public function isUpcoming(): bool
    {
        return $this->ends_on !== null && $this->ends_on->endOfDay()->isFuture();
    }

    public function url(): string
    {
        return route('productions.show', $this);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function coverUrl(): ?string
    {
        return media_url($this->cover_image) ?? media_url(($this->gallery ?? [])[0] ?? null);
    }

    /**
     * "15 - 17 maart 2027", "maart 2027" or "2019".
     */
    public function dateLabel(): string
    {
        if (filled($this->date_label)) {
            return $this->date_label;
        }

        if (! $this->starts_on) {
            return '';
        }

        $start = $this->starts_on;
        $end = $this->ends_on ?? $start;

        if ($start->isSameDay($end)) {
            return $start->translatedFormat('j F Y');
        }

        if ($start->isSameMonth($end)) {
            return $start->format('j').' - '.$end->translatedFormat('j F Y');
        }

        return $start->translatedFormat('j F').' - '.$end->translatedFormat('j F Y');
    }

    /**
     * Performances sorted by date, as Carbon moments with venue and note.
     *
     * @return list<array{when: CarbonInterface, has_time: bool, venue: ?string, note: ?string}>
     */
    public function performanceList(): array
    {
        return collect($this->performances ?? [])
            ->filter(fn (array $row): bool => filled($row['date'] ?? null))
            ->map(fn (array $row): array => [
                'when' => Carbon::parse($row['date'].' '.($row['time'] ?? '00:00')),
                'has_time' => filled($row['time'] ?? null),
                'venue' => ($row['venue'] ?? null) ?: $this->venue,
                'note' => $row['note'] ?? null,
            ])
            ->sortBy('when')
            ->values()
            ->all();
    }
}
