<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreparationRecipe extends Model
{
    protected $fillable = ['output_ingredient_id', 'yield_quantity'];

    protected function casts(): array
    {
        return ['yield_quantity' => 'decimal:3'];
    }

    public function outputIngredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'output_ingredient_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PreparationRecipeLine::class);
    }
}
