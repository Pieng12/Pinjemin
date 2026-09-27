<?php

namespace App\Models;

use Database\Factories\ItemPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['path', 'is_primary', 'sort_order'])]
class ItemPhoto extends Model
{
    /** @use HasFactory<ItemPhotoFactory> */
    use HasFactory;

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
