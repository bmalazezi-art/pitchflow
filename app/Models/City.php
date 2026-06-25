<?php

namespace App\Models;

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

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }

    public function footballFields(): HasMany
    {
        return $this->hasMany(FootballField::class);
    }
}
