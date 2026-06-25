<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'plan_name', 'price', 'billing_cycle', 'status',
        'started_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'started_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
