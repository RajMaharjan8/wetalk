<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Download extends Model
{
    /** Cover type => human label, for dashboards. */
    public const COVER_LABELS = [
        'tu' => 'TU',
        'london_met' => 'London Met',
        'custom' => 'Custom',
    ];

    protected $fillable = [
        'report_id',
        'user_id',
        'cover_type',
        'paid',
    ];

    protected function casts(): array
    {
        return [
            'paid' => 'boolean',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
