<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyMenu extends Model
{
    protected $fillable = ['user_id', 'menu_content', 'date', 'source', 'tokens_used'];

    protected $casts = [
        'date'        => 'date',
        'tokens_used' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
