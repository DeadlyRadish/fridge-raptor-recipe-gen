<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneratedRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'ingredients',
        'instructions',
        'cooking_time',
        'difficulty',
        'preferences',
        'products_used',
    ];

    protected $casts = [
        'ingredients' => 'array',
        'instructions' => 'array',
        'preferences' => 'array',
        'products_used' => 'array',
    ];
}