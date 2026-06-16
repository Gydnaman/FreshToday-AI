<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'usage_purpose', 'dietary_habits', 'goals',
        'allergies', 'household_size', 'cooking_skill', 'budget_hkd',
    ];

    protected $casts = [
        'allergies' => 'array',
        'budget_hkd' => 'decimal:2',
        'household_size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
