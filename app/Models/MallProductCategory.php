<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MallProductCategory extends Model
{
    protected $fillable = [
        'mall_store_id',
        'name',
        'slug',
        'sort_order',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MallStore::class, 'mall_store_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(MallProduct::class, 'mall_product_mall_product_category');
    }
}
