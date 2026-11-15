<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityHour extends Model
{
    protected $fillable = [
        'zone',
        'date',
        'hour',
        'samples',
        'armed_samples',
        'max_persons',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'hour' => 'integer',
            'samples' => 'integer',
            'armed_samples' => 'integer',
            'max_persons' => 'integer',
        ];
    }
}

