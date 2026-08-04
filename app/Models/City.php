<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'country', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public const KOSOVO_PRIORITY_ORDER = [
        'Prishtinë',
        'Prizren',
        'Ferizaj',
        'Gjilan',
        'Pejë',
        'Gjakovë',
        'Mitrovicë',
        'Vushtrri',
        'Podujevë',
    ];

    public const KOSOVO_SECONDARY_ORDER = [
        'Deçan',
        'Drenas',
        'Fushë Kosovë',
        'Klinë',
        'Lipjan',
        'Malishevë',
        'Obiliq',
        'Shtime',
        'Suharekë',
        'Viti',
    ];

    public const HIDDEN_PUBLIC_OPTIONS = [
        'Gjithë Kosovën',
        'Jashtë Vendit',
    ];

    /** @return array<int, string> */
    public static function kosovoSelectorNames(): array
    {
        return array_merge(self::KOSOVO_PRIORITY_ORDER, self::KOSOVO_SECONDARY_ORDER);
    }

    public function scopeForSelector(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereIn('name', self::kosovoSelectorNames());
    }

    public function scopeInKosovoSelectorOrder(Builder $query): Builder
    {
        $prioritySql = collect(self::KOSOVO_PRIORITY_ORDER)
            ->map(fn (string $city, int $index) => 'WHEN ? THEN '.$index)
            ->implode(' ');

        return $query
            ->orderByRaw("CASE name {$prioritySql} ELSE 100 END", self::KOSOVO_PRIORITY_ORDER)
            ->orderBy('name');
    }

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }

    public function footballFields(): HasMany
    {
        return $this->hasMany(FootballField::class);
    }
}
