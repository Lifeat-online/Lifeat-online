<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MallStoreCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'sort_order',
    ];

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(MallStore::class, 'mall_store_category_mall_store');
    }
}
