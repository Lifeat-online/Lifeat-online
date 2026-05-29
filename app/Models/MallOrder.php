<?php

namespace App\Models;

use App\Support\Mall\MallMoney;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class MallOrder extends Model
{
    protected $fillable = [
        'user_id',
        'mall_store_id',
        'order_number',
        'status',
        'subtotal',
        'total',
        'platform_fee',
        'vendor_amount',
        'customer_notes',
        'payfast_payment_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'vendor_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

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
        return $this->hasMany(MallOrderItem::class);
    }

    public function payment(): HasMany
    {
        return $this->hasMany(MallPayment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(MallPayment::class);
    }

    public function fulfillment(): HasOne
    {
        return $this->hasOne(MallFulfillment::class, 'mall_order_id');
    }

    public static function createFromCart(MallCart $cart, User $user, ?string $notes = null, array $deliveryQuote = []): self
    {
        $cart->loadMissing('items.product', 'store');
        $subtotal = MallMoney::add($cart->items->map(fn (MallCartItem $item) => $item->line_total));
        $productPlatformFee = MallMoney::percent($subtotal, config('mall.platform_fee_percent', '10'));
        $deliveryFee = (string) ($deliveryQuote['delivery_fee'] ?? '0.00');
        $deliveryPlatformFee = (string) ($deliveryQuote['platform_fee'] ?? '0.00');
        $platformFee = MallMoney::add([$productPlatformFee, $deliveryPlatformFee]);
        $vendorAmount = MallMoney::subtract($subtotal, $productPlatformFee);
        $total = MallMoney::add([$subtotal, $deliveryFee]);

        $order = self::create([
            'user_id' => $user->id,
            'mall_store_id' => $cart->mall_store_id,
            'order_number' => self::nextOrderNumber(),
            'status' => 'pending',
            'subtotal' => $subtotal,
            'total' => $total,
            'platform_fee' => $platformFee,
            'vendor_amount' => $vendorAmount,
            'customer_notes' => $notes,
        ]);

        foreach ($cart->items as $item) {
            $product = $item->product;
            $order->items()->create([
                'mall_product_id' => $product?->id,
                'product_name' => $product?->name ?? 'Deleted product',
                'product_sku' => $product?->sku,
                'quantity' => $item->quantity,
                'parcel_weight_kg' => $product?->parcel_weight_kg,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ]);
        }

        return $order;
    }

    private static function nextOrderNumber(): string
    {
        do {
            $number = 'LM-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (self::where('order_number', $number)->exists());

        return $number;
    }
}
