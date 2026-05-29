<?php

namespace App\Models;

use App\Support\Mall\MallMoney;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MallCart extends Model
{
    protected $fillable = [
        'user_id',
        'mall_store_id',
        'session_token',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MallStore::class, 'mall_store_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MallCartItem::class);
    }

    public function getTotalAttribute(): string
    {
        return MallMoney::add($this->items->map(fn (MallCartItem $item) => $item->line_total));
    }

    public function getItemCountAttribute(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function isEmpty(): bool
    {
        return ! $this->items()->exists();
    }

    public static function findOrCreateForUser(int $userId, int $storeId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId, 'mall_store_id' => $storeId],
            ['session_token' => null]
        );
    }

    public static function findOrCreateForGuest(string $sessionToken, int $storeId): self
    {
        return self::firstOrCreate([
            'session_token' => $sessionToken,
            'mall_store_id' => $storeId,
            'user_id' => null,
        ]);
    }

    public static function mergeGuestCarts(string $sessionToken, int $userId): void
    {
        $guestCarts = self::query()
            ->where('session_token', $sessionToken)
            ->whereNull('user_id')
            ->with('items')
            ->get();

        foreach ($guestCarts as $guestCart) {
            $userCart = self::findOrCreateForUser($userId, $guestCart->mall_store_id);

            foreach ($guestCart->items as $guestItem) {
                $existingItem = $userCart->items()
                    ->where('mall_product_id', $guestItem->mall_product_id)
                    ->first();

                if ($existingItem) {
                    $existingItem->increment('quantity', $guestItem->quantity);
                    continue;
                }

                $userCart->items()->create([
                    'mall_product_id' => $guestItem->mall_product_id,
                    'quantity' => $guestItem->quantity,
                    'unit_price' => $guestItem->unit_price,
                ]);
            }

            $guestCart->delete();
        }
    }
}
