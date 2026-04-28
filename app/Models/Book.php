<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    /** @use HasFactory<\Database\Factories\BookFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'publisher',
        'author',
        'genre',
        'publication_date',
        'word_count',
        'price_usd',
    ];

    protected function casts(): array
    {
        return [
            'publication_date' => 'date',
            'word_count' => 'integer',
            'price_usd' => 'decimal:2',
        ];
    }
}
