# lifeat Online Mall — Complete Implementation Specification
### For AI Agent Use | Laravel 11 | Multi-Vendor | PayFast Split Payments

---

## CRITICAL RULES — READ FIRST

1. **Cart is ALWAYS scoped to a single store.** There is NO global multi-vendor cart. A customer pays at each store's till before leaving, exactly like a real mall.
2. **Every PayFast transaction is single-vendor.** One checkout = one store = one PayFast payment with a 90/10 split.
3. **Never decrement stock on cart add.** Stock is decremented ONLY on successful PayFast ITN (payment confirmed).
4. **The PayFast ITN route MUST be excluded from CSRF middleware.**
5. **Prices are snapshotted** at the moment a product is added to the cart and again at order creation.
6. **Guest users can browse and add to cart.** Auth is required only at checkout.
7. **All monetary values use `decimal(10,2)`.** Never use float for money.

---

## 1. PROJECT CONTEXT

- **Application:** lifeat (lifeat.online) — Laravel 11, MySQL
- **Feature:** Multi-vendor Online Mall with window shopping UX
- **Auth:** Laravel Breeze or Jetstream (already installed — do not reinstall)
- **Payment Gateway:** PayFast (South Africa)
- **Commission:** Platform = 10%, Vendor = 90% of every transaction

### The Mall Metaphor (drives all UX decisions)

| Real Mall | Application Equivalent |
|---|---|
| Walking past shop windows | Public browsing — no auth, see store front + 6 featured products |
| Looking through the glass | Quick product preview (name, price, image) |
| Entering the store | Full product catalog view (no auth required) |
| Adding items to your basket | Cart — per-store scoped (guest or authenticated) |
| Paying at the store's till | Checkout → PayFast payment for that store only |
| Carrying bags into next store | Separate active carts for each store visited |

---

## 2. TECHNOLOGY STACK

- **Framework:** Laravel 11
- **Database:** MySQL 8.0+
- **Frontend:** Blade + Alpine.js (no Livewire unless already installed)
- **File Storage:** Laravel's local public disk (`storage/app/public/mall/`)
- **Payment:** PayFast (direct integration — no third-party package)
- **Queue:** Laravel's database queue driver (for emails)
- **Session:** Database or file driver (either fine)

### Required Packages (install in order)
```bash
composer require intervention/image:^3.0
php artisan storage:link
```

### Environment Variables to Add to .env
```env
PAYFAST_MERCHANT_ID=your_merchant_id
PAYFAST_MERCHANT_KEY=your_merchant_key
PAYFAST_PASSPHRASE=your_passphrase_or_leave_empty
PAYFAST_TESTMODE=true
MALL_PLATFORM_FEE_PERCENT=10
APP_URL=https://lifeat.online
```

---

## 3. DATABASE SCHEMA — ALL MIGRATIONS

Create migrations in this exact order (foreign keys depend on it).

### Migration 1: `create_store_categories_table`
```php
Schema::create('store_categories', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100);
    $table->string('slug', 120)->unique();
    $table->string('icon', 50)->nullable()->comment('emoji or CSS class');
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

### Migration 2: `create_stores_table`
```php
Schema::create('stores', function (Blueprint $table) {
    $table->id();
    $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
    $table->string('name', 100);
    $table->string('slug', 120)->unique();
    $table->string('tagline', 255)->nullable();
    $table->text('description')->nullable();
    $table->string('logo_path')->nullable();
    $table->string('banner_path')->nullable();
    $table->string('primary_color', 7)->default('#3B82F6')->comment('hex colour');
    $table->string('payfast_merchant_id', 20)->nullable()->comment('vendor PayFast merchant ID for splits');
    $table->string('payfast_merchant_key', 20)->nullable();
    $table->enum('status', ['pending', 'active', 'suspended', 'closed'])->default('pending');
    $table->boolean('is_featured')->default(false);
    $table->json('meta')->nullable();
    $table->timestamps();
});
```

### Migration 3: `create_store_category_store_table` (pivot)
```php
Schema::create('store_category_store', function (Blueprint $table) {
    $table->foreignId('store_id')->constrained()->cascadeOnDelete();
    $table->foreignId('store_category_id')->constrained()->cascadeOnDelete();
    $table->primary(['store_id', 'store_category_id']);
});
```

### Migration 4: `create_products_table`
```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('store_id')->constrained()->cascadeOnDelete();
    $table->string('name', 200);
    $table->string('slug', 220);
    $table->string('short_description', 500)->nullable();
    $table->longText('description')->nullable();
    $table->decimal('price', 10, 2);
    $table->decimal('compare_price', 10, 2)->nullable()->comment('original price before sale');
    $table->string('sku', 100)->nullable();
    $table->integer('stock_qty')->default(0)->unsigned();
    $table->boolean('manage_stock')->default(true);
    $table->boolean('is_featured')->default(false)->comment('shows in window view');
    $table->boolean('is_active')->default(true);
    $table->json('images')->nullable()->comment('array of storage paths');
    $table->json('meta')->nullable();
    $table->timestamps();
    $table->unique(['store_id', 'slug']);
    $table->index('store_id');
    $table->index('is_featured');
    $table->index('is_active');
});
```

### Migration 5: `create_product_categories_table`
```php
Schema::create('product_categories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('store_id')->constrained()->cascadeOnDelete();
    $table->string('name', 100);
    $table->string('slug', 120);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
    $table->unique(['store_id', 'slug']);
});

Schema::create('product_product_category', function (Blueprint $table) {
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
    $table->primary(['product_id', 'product_category_id']);
});
```

### Migration 6: `create_carts_table`
```php
Schema::create('carts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('store_id')->constrained()->cascadeOnDelete();
    $table->string('session_token', 64)->nullable()->index()->comment('for guest carts');
    $table->timestamps();
    $table->unique(['user_id', 'store_id'])->comment('one cart per user per store');
    $table->index('store_id');
});
```

### Migration 7: `create_cart_items_table`
```php
Schema::create('cart_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->integer('quantity')->unsigned()->default(1);
    $table->decimal('unit_price', 10, 2)->comment('price snapshot at time of add');
    $table->timestamps();
    $table->unique(['cart_id', 'product_id']);
});
```

### Migration 8: `create_orders_table`
```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('store_id')->constrained()->cascadeOnDelete();
    $table->string('order_number', 30)->unique()->comment('e.g. LM-20240523-00001');
    $table->enum('status', [
        'pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled', 'refunded'
    ])->default('pending');
    $table->decimal('subtotal', 10, 2);
    $table->decimal('total', 10, 2);
    $table->decimal('platform_fee', 10, 2)->comment('10% of total');
    $table->decimal('vendor_amount', 10, 2)->comment('90% of total');
    $table->text('customer_notes')->nullable();
    $table->string('payfast_payment_id', 50)->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();
    $table->index('user_id');
    $table->index('store_id');
    $table->index('status');
});
```

### Migration 9: `create_order_items_table`
```php
Schema::create('order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
    $table->string('product_name', 200)->comment('snapshot — product may be deleted later');
    $table->string('product_sku', 100)->nullable()->comment('snapshot');
    $table->integer('quantity')->unsigned();
    $table->decimal('unit_price', 10, 2)->comment('snapshot');
    $table->decimal('line_total', 10, 2)->comment('quantity * unit_price');
    $table->timestamps();
    $table->index('order_id');
});
```

### Migration 10: `create_payments_table`
```php
Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained()->cascadeOnDelete();
    $table->string('m_payment_id', 100)->unique()->comment('our payment reference (order_number)');
    $table->string('payfast_payment_id', 50)->nullable()->comment('PayFast pf_payment_id from ITN');
    $table->decimal('amount', 10, 2);
    $table->enum('status', ['initiated', 'complete', 'failed', 'cancelled'])->default('initiated');
    $table->json('itn_payload')->nullable()->comment('full raw ITN data from PayFast');
    $table->decimal('payfast_fee', 10, 2)->nullable()->comment('pf_fee from ITN');
    $table->decimal('net_amount', 10, 2)->nullable()->comment('amount minus payfast_fee');
    $table->timestamps();
    $table->index('order_id');
});
```

### Migration 11: `create_vendor_profiles_table`
```php
Schema::create('vendor_profiles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('contact_name', 100);
    $table->string('contact_email', 150);
    $table->string('contact_phone', 20)->nullable();
    $table->string('business_reg', 50)->nullable()->comment('SA business registration number');
    $table->string('bank_name', 50)->nullable();
    $table->string('bank_account', 30)->nullable();
    $table->string('bank_branch_code', 10)->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

### Add `is_admin` to users table
```php
Schema::table('users', function (Blueprint $table) {
    $table->boolean('is_admin')->default(false)->after('email');
});
```

---

## 4. ELOQUENT MODELS

### `app/Models/Store.php`
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Store extends Model
{
    protected $fillable = [
        'owner_user_id', 'name', 'slug', 'tagline', 'description',
        'logo_path', 'banner_path', 'primary_color',
        'payfast_merchant_id', 'payfast_merchant_key',
        'status', 'is_featured', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_featured' => 'boolean',
    ];

    // Route model binding by slug
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // Relationships
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function vendorProfile(): HasOne
    {
        return $this->hasOne(VendorProfile::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(StoreCategory::class, 'store_category_store');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // Helpers
    public function getFeaturedProducts(int $limit = 6)
    {
        return $this->products()
            ->where('is_featured', true)
            ->where('is_active', true)
            ->limit($limit)
            ->get();
    }

    public function hasPayFastSplit(): bool
    {
        return !empty($this->payfast_merchant_id) && !empty($this->payfast_merchant_key);
    }

    public function getLogoUrlAttribute(): string
    {
        return $this->logo_path
            ? asset('storage/' . $this->logo_path)
            : asset('images/mall/default-store-logo.png');
    }

    public function getBannerUrlAttribute(): string
    {
        return $this->banner_path
            ? asset('storage/' . $this->banner_path)
            : asset('images/mall/default-store-banner.jpg');
    }
}
```

### `app/Models/Product.php`
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    protected $fillable = [
        'store_id', 'name', 'slug', 'short_description', 'description',
        'price', 'compare_price', 'sku', 'stock_qty', 'manage_stock',
        'is_featured', 'is_active', 'images', 'meta',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'images' => 'array',
        'meta' => 'array',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'manage_stock' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'product_product_category');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function isInStock(): bool
    {
        if (!$this->manage_stock) return true;
        return $this->stock_qty > 0;
    }

    public function getMainImageUrlAttribute(): string
    {
        $images = $this->images ?? [];
        if (!empty($images)) {
            return asset('storage/' . $images[0]);
        }
        return asset('images/mall/default-product.png');
    }

    public function isOnSale(): bool
    {
        return $this->compare_price !== null && $this->compare_price > $this->price;
    }
}
```

### `app/Models/Cart.php`
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = ['user_id', 'store_id', 'session_token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function getTotalAttribute(): float
    {
        return $this->items->sum(fn ($item) => $item->quantity * $item->unit_price);
    }

    public function getItemCountAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    public function isEmpty(): bool
    {
        return $this->items()->count() === 0;
    }

    /**
     * Find or create a cart for an authenticated user scoped to a store.
     * ALWAYS use this method — never create carts manually.
     */
    public static function findOrCreateForUser(int $userId, int $storeId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId, 'store_id' => $storeId],
            ['session_token' => null]
        );
    }

    /**
     * Find or create a guest cart using session token, scoped to a store.
     */
    public static function findOrCreateForGuest(string $sessionToken, int $storeId): self
    {
        return self::firstOrCreate(
            ['session_token' => $sessionToken, 'store_id' => $storeId, 'user_id' => null],
        );
    }

    /**
     * Called on login — merge guest carts into the authenticated user's carts.
     * One cart per store; quantities are summed for duplicate products.
     */
    public static function mergeGuestCarts(string $sessionToken, int $userId): void
    {
        $guestCarts = self::where('session_token', $sessionToken)
            ->whereNull('user_id')
            ->with('items')
            ->get();

        foreach ($guestCarts as $guestCart) {
            $userCart = self::findOrCreateForUser($userId, $guestCart->store_id);

            foreach ($guestCart->items as $guestItem) {
                $existingItem = $userCart->items()->where('product_id', $guestItem->product_id)->first();

                if ($existingItem) {
                    $existingItem->increment('quantity', $guestItem->quantity);
                } else {
                    $userCart->items()->create([
                        'product_id' => $guestItem->product_id,
                        'quantity' => $guestItem->quantity,
                        'unit_price' => $guestItem->unit_price,
                    ]);
                }
            }

            $guestCart->delete();
        }
    }
}
```

### `app/Models/CartItem.php`
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = ['cart_id', 'product_id', 'quantity', 'unit_price'];

    protected $casts = [
        'unit_price' => 'decimal:2',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getLineTotalAttribute(): float
    {
        return round($this->quantity * $this->unit_price, 2);
    }
}
```

### `app/Models/Order.php`
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'store_id', 'order_number', 'status',
        'subtotal', 'total', 'platform_fee', 'vendor_amount',
        'customer_notes', 'payfast_payment_id', 'paid_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'vendor_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Create an order from a cart. Snapshots all prices.
     * Does NOT decrement stock — that happens on payment confirmation.
     */
    public static function createFromCart(Cart $cart, User $user, ?string $notes = null): self
    {
        $cart->load('items.product');

        $subtotal = $cart->total;
        $total = $subtotal; // extend here for tax/shipping later
        $platformFeePercent = config('mall.platform_fee_percent', 10);
        $platformFee = round($total * ($platformFeePercent / 100), 2);
        $vendorAmount = round($total - $platformFee, 2);

        $order = self::create([
            'user_id' => $user->id,
            'store_id' => $cart->store_id,
            'order_number' => self::generateOrderNumber(),
            'status' => 'pending',
            'subtotal' => $subtotal,
            'total' => $total,
            'platform_fee' => $platformFee,
            'vendor_amount' => $vendorAmount,
            'customer_notes' => $notes,
        ]);

        foreach ($cart->items as $item) {
            $order->items()->create([
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'product_sku' => $item->product->sku,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ]);
        }

        return $order;
    }

    public static function generateOrderNumber(): string
    {
        do {
            $number = 'LM-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
        } while (self::where('order_number', $number)->exists());

        return $number;
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
```

### `app/Models/Payment.php`
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'm_payment_id', 'payfast_payment_id',
        'amount', 'status', 'itn_payload', 'payfast_fee', 'net_amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payfast_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'itn_payload' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function isComplete(): bool
    {
        return $this->status === 'complete';
    }
}
```

---

## 5. SERVICES

### `app/Services/PayFastService.php`
```php
<?php
namespace App\Services;

use App\Models\Order;
use App\Models\Payment;

class PayFastService
{
    private string $merchantId;
    private string $merchantKey;
    private ?string $passphrase;
    private bool $testMode;
    private string $processUrl;
    private string $validateUrl;

    public function __construct()
    {
        $this->merchantId = config('payfast.merchant_id');
        $this->merchantKey = config('payfast.merchant_key');
        $this->passphrase = config('payfast.passphrase') ?: null;
        $this->testMode = config('payfast.testmode', false);
        $this->processUrl = $this->testMode
            ? 'https://sandbox.payfast.co.za/eng/process'
            : 'https://www.payfast.co.za/eng/process';
        $this->validateUrl = $this->testMode
            ? 'https://sandbox.payfast.co.za/eng/query/validate'
            : 'https://www.payfast.co.za/eng/query/validate';
    }

    /**
     * Build the PayFast payment data array for a given order.
     * Returns the URL and the hidden form fields.
     */
    public function buildPaymentData(Order $order): array
    {
        $order->load('user', 'store');

        $data = [
            // Merchant
            'merchant_id'   => $this->merchantId,
            'merchant_key'  => $this->merchantKey,

            // URLs
            'return_url'    => route('checkout.return', $order->store->slug),
            'cancel_url'    => route('checkout.cancel', $order->store->slug),
            'notify_url'    => route('payment.itn'),

            // Transaction
            'm_payment_id'  => $order->order_number,
            'amount'        => number_format($order->total, 2, '.', ''),
            'item_name'     => substr($order->store->name . ' — Order ' . $order->order_number, 0, 100),
            'item_description' => substr('Purchase from ' . $order->store->name . ' on lifeat.online', 0, 255),

            // Customer
            'email_address' => $order->user->email,
            'name_first'    => $order->user->name,
        ];

        // Add split payment if vendor has their own PayFast account
        // This tells PayFast to automatically send 90% to vendor
        if ($order->store->hasPayFastSplit()) {
            $data['merchant_receiver'] = json_encode([
                'merchant_id'  => $order->store->payfast_merchant_id,
                'merchant_key' => $order->store->payfast_merchant_key,
                'percentage'   => 90,
                'min'          => 0,
                'max'          => 0,
            ]);
        }

        // Add signature
        $data['signature'] = $this->generateSignature($data);

        return [
            'url'    => $this->processUrl,
            'fields' => $data,
        ];
    }

    /**
     * Generate the PayFast MD5 signature.
     * IMPORTANT: field order matters — do NOT sort alphabetically.
     */
    public function generateSignature(array $data, ?string $passphrase = null): string
    {
        unset($data['signature']);

        $queryParts = [];
        foreach ($data as $key => $value) {
            if ($value !== '' && $value !== null) {
                $queryParts[] = $key . '=' . urlencode(trim((string) $value));
            }
        }
        $queryString = implode('&', $queryParts);

        $phrase = $passphrase ?? $this->passphrase;
        if ($phrase !== null) {
            $queryString .= '&passphrase=' . urlencode(trim($phrase));
        }

        return md5($queryString);
    }

    /**
     * Validate an ITN (Instant Transaction Notification) from PayFast.
     * Returns true only if ALL validations pass.
     *
     * Call this from PaymentController::itn() BEFORE updating any data.
     */
    public function validateItn(array $data): bool
    {
        // Step 1: Validate signature
        $postedSignature = $data['signature'] ?? '';
        $calculatedSignature = $this->generateSignature($data);
        if ($postedSignature !== $calculatedSignature) {
            \Log::error('PayFast ITN: Signature mismatch', [
                'posted' => $postedSignature,
                'calculated' => $calculatedSignature,
            ]);
            return false;
        }

        // Step 2: Validate with PayFast server (anti-phishing)
        $validationData = $data;
        unset($validationData['signature']);

        $queryString = http_build_query($validationData);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->validateUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $queryString,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $result = curl_exec($ch);
        curl_close($ch);

        if (strtolower(trim($result)) !== 'valid') {
            \Log::error('PayFast ITN: Server validation failed', ['result' => $result]);
            return false;
        }

        // Step 3: Check payment status
        if (($data['payment_status'] ?? '') !== 'COMPLETE') {
            return false;
        }

        return true;
    }

    /**
     * Process a validated ITN — update order and payment records.
     * Only call this AFTER validateItn() returns true.
     */
    public function processSuccessfulItn(array $data): bool
    {
        $mPaymentId = $data['m_payment_id'] ?? null;
        if (!$mPaymentId) {
            \Log::error('PayFast ITN: Missing m_payment_id');
            return false;
        }

        $payment = Payment::where('m_payment_id', $mPaymentId)->first();
        if (!$payment) {
            \Log::error('PayFast ITN: Payment record not found', ['m_payment_id' => $mPaymentId]);
            return false;
        }

        // Prevent duplicate processing
        if ($payment->isComplete()) {
            return true;
        }

        $order = $payment->order;

        // Verify amount matches (tolerance of 0.01 for float issues)
        $paidAmount = (float) ($data['amount_gross'] ?? 0);
        if (abs($paidAmount - (float) $order->total) > 0.01) {
            \Log::error('PayFast ITN: Amount mismatch', [
                'expected' => $order->total,
                'received' => $paidAmount,
            ]);
            return false;
        }

        // Update payment record
        $payment->update([
            'status' => 'complete',
            'payfast_payment_id' => $data['pf_payment_id'] ?? null,
            'itn_payload' => $data,
            'payfast_fee' => $data['amount_fee'] ?? null,
            'net_amount' => $data['amount_net'] ?? null,
        ]);

        // Update order
        $order->update([
            'status' => 'paid',
            'payfast_payment_id' => $data['pf_payment_id'] ?? null,
            'paid_at' => now(),
        ]);

        // Decrement stock (ONLY happens here, not on cart add)
        foreach ($order->items as $item) {
            if ($item->product && $item->product->manage_stock) {
                $item->product->decrement('stock_qty', $item->quantity);
            }
        }

        // Delete the customer's cart for this store
        Cart::where('user_id', $order->user_id)
            ->where('store_id', $order->store_id)
            ->delete();

        // Dispatch notification events
        event(new \App\Events\OrderPaid($order));

        return true;
    }
}
```

### `app/Services/CartService.php`
```php
<?php
namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;

class CartService
{
    /**
     * Get or create the cart for the current user/guest, scoped to a store.
     */
    public function getCart(Store $store): Cart
    {
        if (Auth::check()) {
            return Cart::findOrCreateForUser(Auth::id(), $store->id);
        }

        $sessionToken = session()->get('cart_token') ?? $this->generateSessionToken();
        return Cart::findOrCreateForGuest($sessionToken, $store->id);
    }

    /**
     * Add a product to a store's cart. Returns error string or null on success.
     */
    public function addItem(Store $store, Product $product, int $quantity = 1): ?string
    {
        // Validation
        if (!$product->is_active) {
            return 'This product is not available.';
        }
        if ($product->store_id !== $store->id) {
            return 'Product does not belong to this store.';
        }
        if ($product->manage_stock && $product->stock_qty < $quantity) {
            return 'Insufficient stock.';
        }
        if ($quantity < 1) {
            return 'Quantity must be at least 1.';
        }

        $cart = $this->getCart($store);
        $existingItem = $cart->items()->where('product_id', $product->id)->first();

        if ($existingItem) {
            $newQty = $existingItem->quantity + $quantity;
            if ($product->manage_stock && $product->stock_qty < $newQty) {
                return 'Cannot add more — insufficient stock.';
            }
            $existingItem->increment('quantity', $quantity);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $product->price, // snapshot current price
            ]);
        }

        return null; // null = success
    }

    /**
     * Update item quantity. Pass 0 to remove.
     */
    public function updateItem(CartItem $item, int $quantity): ?string
    {
        if ($quantity <= 0) {
            $item->delete();
            return null;
        }

        $product = $item->product;
        if ($product && $product->manage_stock && $product->stock_qty < $quantity) {
            return 'Insufficient stock.';
        }

        $item->update(['quantity' => $quantity]);
        return null;
    }

    private function generateSessionToken(): string
    {
        $token = \Str::random(64);
        session()->put('cart_token', $token);
        session()->save();
        return $token;
    }
}
```

---

## 6. CONFIG FILES

### `config/payfast.php` — CREATE THIS FILE
```php
<?php
return [
    'merchant_id'  => env('PAYFAST_MERCHANT_ID'),
    'merchant_key' => env('PAYFAST_MERCHANT_KEY'),
    'passphrase'   => env('PAYFAST_PASSPHRASE'),
    'testmode'     => env('PAYFAST_TESTMODE', true),
];
```

### `config/mall.php` — CREATE THIS FILE
```php
<?php
return [
    'platform_fee_percent' => env('MALL_PLATFORM_FEE_PERCENT', 10),
    'featured_products_per_window' => 6,
    'products_per_page' => 24,
    'max_store_logo_size_kb' => 500,
    'max_product_image_size_kb' => 2048,
    'allowed_image_types' => ['jpg', 'jpeg', 'png', 'webp'],
    'storage_paths' => [
        'store_logos'   => 'mall/stores/logos',
        'store_banners' => 'mall/stores/banners',
        'products'      => 'mall/products',
    ],
];
```

---

## 7. ROUTES — `routes/web.php` additions

```php
<?php
// Add inside web.php — do NOT replace existing routes

use App\Http\Controllers\Mall\MallController;
use App\Http\Controllers\Mall\StoreController;
use App\Http\Controllers\Mall\CartController;
use App\Http\Controllers\Mall\CheckoutController;
use App\Http\Controllers\Mall\PaymentController;
use App\Http\Controllers\Mall\AccountOrderController;
use App\Http\Controllers\Vendor\VendorDashboardController;
use App\Http\Controllers\Vendor\VendorProductController;
use App\Http\Controllers\Vendor\VendorOrderController;
use App\Http\Controllers\Vendor\VendorStoreController;
use App\Http\Controllers\Vendor\VendorEarningsController;
use App\Http\Controllers\Vendor\VendorRegistrationController;
use App\Http\Controllers\Admin\AdminStoreController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminCommissionController;

// -------------------------------------------------------
// PUBLIC MALL ROUTES (no auth required)
// -------------------------------------------------------
Route::prefix('mall')->name('mall.')->group(function () {
    Route::get('/', [MallController::class, 'index'])->name('index');

    // {store} resolves by slug via getRouteKeyName
    Route::get('/{store}', [MallController::class, 'window'])->name('window');
    Route::get('/{store}/shop', [StoreController::class, 'index'])->name('store.index');
    Route::get('/{store}/shop/{product:slug}', [StoreController::class, 'product'])->name('store.product');
});

// -------------------------------------------------------
// CART ROUTES (guests + auth users both allowed)
// -------------------------------------------------------
Route::prefix('cart')->name('cart.')->group(function () {
    Route::get('/{store:slug}', [CartController::class, 'show'])->name('show');
    Route::post('/{store:slug}/add', [CartController::class, 'add'])->name('add');
    Route::patch('/{store:slug}/items/{item}', [CartController::class, 'update'])->name('update');
    Route::delete('/{store:slug}/items/{item}', [CartController::class, 'remove'])->name('remove');
    Route::delete('/{store:slug}', [CartController::class, 'clear'])->name('clear');
});

// -------------------------------------------------------
// CHECKOUT ROUTES (auth required)
// -------------------------------------------------------
Route::prefix('checkout')->name('checkout.')->middleware('auth')->group(function () {
    Route::get('/{store:slug}', [CheckoutController::class, 'show'])->name('show');
    Route::post('/{store:slug}', [CheckoutController::class, 'initiate'])->name('initiate');
    Route::get('/{store:slug}/return', [CheckoutController::class, 'return'])->name('return');
    Route::get('/{store:slug}/cancel', [CheckoutController::class, 'cancel'])->name('cancel');
});

// -------------------------------------------------------
// PAYFAST ITN WEBHOOK — NO CSRF, NO AUTH
// -------------------------------------------------------
Route::post('/payment/itn', [PaymentController::class, 'itn'])
    ->name('payment.itn')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// -------------------------------------------------------
// CUSTOMER ACCOUNT
// -------------------------------------------------------
Route::prefix('account/orders')->name('account.orders.')->middleware('auth')->group(function () {
    Route::get('/', [AccountOrderController::class, 'index'])->name('index');
    Route::get('/{order:order_number}', [AccountOrderController::class, 'show'])->name('show');
});

// -------------------------------------------------------
// VENDOR AREA
// -------------------------------------------------------
Route::get('/vendor/register', [VendorRegistrationController::class, 'create'])->name('vendor.register.create');
Route::post('/vendor/register', [VendorRegistrationController::class, 'store'])->middleware('auth')->name('vendor.register.store');

Route::prefix('vendor')->name('vendor.')->middleware(['auth', 'vendor.active'])->group(function () {
    Route::get('/dashboard', [VendorDashboardController::class, 'index'])->name('dashboard');

    // Store management
    Route::get('/store/edit', [VendorStoreController::class, 'edit'])->name('store.edit');
    Route::patch('/store', [VendorStoreController::class, 'update'])->name('store.update');

    // Product management
    Route::resource('products', VendorProductController::class);

    // Order management
    Route::get('/orders', [VendorOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order:order_number}', [VendorOrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order:order_number}/status', [VendorOrderController::class, 'updateStatus'])->name('orders.status');

    // Earnings
    Route::get('/earnings', [VendorEarningsController::class, 'index'])->name('earnings');
});

// -------------------------------------------------------
// ADMIN AREA
// -------------------------------------------------------
Route::prefix('admin/mall')->name('admin.mall.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/stores', [AdminStoreController::class, 'index'])->name('stores.index');
    Route::get('/stores/{store:slug}', [AdminStoreController::class, 'show'])->name('stores.show');
    Route::patch('/stores/{store:slug}/approve', [AdminStoreController::class, 'approve'])->name('stores.approve');
    Route::patch('/stores/{store:slug}/suspend', [AdminStoreController::class, 'suspend'])->name('stores.suspend');
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/commissions', [AdminCommissionController::class, 'index'])->name('commissions.index');
});
```

---

## 8. MIDDLEWARE

### `app/Http/Middleware/EnsureVendorIsActive.php`
```php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureVendorIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $store = $user->store ?? null;

        if (!$store) {
            return redirect()->route('vendor.register.create')
                ->with('info', 'You need to register a store first.');
        }

        if ($store->status === 'pending') {
            return response()->view('vendor.pending', compact('store'));
        }

        if ($store->status === 'suspended') {
            abort(403, 'Your store has been suspended. Contact support.');
        }

        return $next($request);
    }
}
```

### `app/Http/Middleware/EnsureIsAdmin.php`
```php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->is_admin) {
            abort(403);
        }
        return $next($request);
    }
}
```

### Register middleware in `bootstrap/app.php`
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'vendor.active' => \App\Http\Middleware\EnsureVendorIsActive::class,
        'admin' => \App\Http\Middleware\EnsureIsAdmin::class,
    ]);
})
```

---

## 9. CONTROLLERS

### `app/Http/Controllers/Mall/MallController.php`
```php
<?php
namespace App\Http\Controllers\Mall;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreCategory;

class MallController extends Controller
{
    /**
     * Mall directory — list of all active stores (window view grid)
     */
    public function index()
    {
        $categories = StoreCategory::orderBy('sort_order')->get();
        $featuredStores = Store::active()->featured()->with('categories')->get();
        $allStores = Store::active()
            ->with(['categories', 'products' => fn($q) => $q->featured()->active()->limit(3)])
            ->paginate(24);

        return view('mall.index', compact('categories', 'featuredStores', 'allStores'));
    }

    /**
     * Individual store window — public preview (outside the store)
     */
    public function window(Store $store)
    {
        abort_unless($store->status === 'active', 404);

        $featuredProducts = $store->getFeaturedProducts(config('mall.featured_products_per_window', 6));

        return view('mall.window', compact('store', 'featuredProducts'));
    }
}
```

### `app/Http/Controllers/Mall/StoreController.php`
```php
<?php
namespace App\Http\Controllers\Mall;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __construct(private CartService $cartService) {}

    /**
     * Full store catalog — inside the store
     */
    public function index(Store $store, Request $request)
    {
        abort_unless($store->status === 'active', 404);

        $query = $store->products()->active();

        if ($request->filled('category')) {
            $query->whereHas('categories', fn($q) => $q->where('slug', $request->category));
        }
        if ($request->filled('q')) {
            $query->where(fn($q) => $q
                ->where('name', 'like', '%' . $request->q . '%')
                ->orWhere('short_description', 'like', '%' . $request->q . '%')
            );
        }
        if ($request->filled('sort')) {
            match($request->sort) {
                'price_asc'  => $query->orderBy('price'),
                'price_desc' => $query->orderByDesc('price'),
                'newest'     => $query->latest(),
                default      => $query->orderBy('name'),
            };
        }

        $products = $query->paginate(config('mall.products_per_page', 24));
        $storeCategories = $store->categories()->get(); // product categories for filter sidebar
        $cart = $this->cartService->getCart($store);

        return view('mall.store.index', compact('store', 'products', 'storeCategories', 'cart'));
    }

    /**
     * Product detail page — still inside the store
     */
    public function product(Store $store, Product $product)
    {
        abort_unless($store->status === 'active', 404);
        abort_unless($product->store_id === $store->id && $product->is_active, 404);

        $product->load('categories');
        $relatedProducts = $store->products()
            ->active()
            ->where('id', '!=', $product->id)
            ->limit(4)
            ->get();

        $cart = $this->cartService->getCart($store);

        return view('mall.store.product', compact('store', 'product', 'relatedProducts', 'cart'));
    }
}
```

### `app/Http/Controllers/Mall/CartController.php`
```php
<?php
namespace App\Http\Controllers\Mall;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $cartService) {}

    public function show(Store $store)
    {
        abort_unless($store->status === 'active', 404);
        $cart = $this->cartService->getCart($store);
        $cart->load('items.product');
        return view('mall.cart', compact('store', 'cart'));
    }

    public function add(Store $store, Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity'   => 'integer|min:1|max:99',
        ]);

        $product = Product::findOrFail($request->product_id);
        $error = $this->cartService->addItem($store, $product, $request->integer('quantity', 1));

        if ($error) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Item added to your basket!');
    }

    public function update(Store $store, CartItem $item, Request $request)
    {
        $request->validate(['quantity' => 'required|integer|min:0|max:99']);
        $this->cartService->updateItem($item, $request->integer('quantity'));
        return back()->with('success', 'Basket updated.');
    }

    public function remove(Store $store, CartItem $item)
    {
        $item->delete();
        return back()->with('success', 'Item removed.');
    }

    public function clear(Store $store)
    {
        $cart = $this->cartService->getCart($store);
        $cart->items()->delete();
        return back()->with('success', 'Basket cleared.');
    }
}
```

### `app/Http/Controllers/Mall/CheckoutController.php`
```php
<?php
namespace App\Http\Controllers\Mall;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CartService;
use App\Services\PayFastService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private PayFastService $payFastService
    ) {}

    /**
     * Show checkout summary page for a specific store
     */
    public function show(Store $store)
    {
        $cart = $this->cartService->getCart($store);
        $cart->load('items.product');

        if ($cart->isEmpty()) {
            return redirect()->route('mall.store.index', $store->slug)
                ->with('info', 'Your basket is empty.');
        }

        return view('mall.checkout', compact('store', 'cart'));
    }

    /**
     * Create order and redirect to PayFast
     */
    public function initiate(Store $store, Request $request)
    {
        $request->validate(['notes' => 'nullable|string|max:500']);

        $cart = $this->cartService->getCart($store);
        $cart->load('items.product');

        if ($cart->isEmpty()) {
            return redirect()->route('mall.store.index', $store->slug)
                ->with('error', 'Your basket is empty.');
        }

        // Validate stock one more time before creating order
        foreach ($cart->items as $item) {
            if ($item->product->manage_stock && $item->product->stock_qty < $item->quantity) {
                return back()->with('error', $item->product->name . ' is out of stock.');
            }
        }

        // Create order (snapshots prices, does NOT touch stock yet)
        $order = Order::createFromCart($cart, $request->user(), $request->input('notes'));

        // Create payment record
        Payment::create([
            'order_id'     => $order->id,
            'm_payment_id' => $order->order_number,
            'amount'       => $order->total,
            'status'       => 'initiated',
        ]);

        // Build PayFast form data
        $paymentData = $this->payFastService->buildPaymentData($order);

        // Render a self-submitting HTML form that redirects to PayFast
        return view('mall.payfast_redirect', [
            'paymentUrl'    => $paymentData['url'],
            'paymentFields' => $paymentData['fields'],
        ]);
    }

    /**
     * PayFast return URL — user lands here after payment (may not have been confirmed yet)
     */
    public function return(Store $store, Request $request)
    {
        return view('mall.checkout_return', [
            'store'   => $store,
            'message' => 'Thank you! Your payment is being confirmed. We will email you shortly.',
        ]);
    }

    /**
     * PayFast cancel URL — user cancelled the payment
     */
    public function cancel(Store $store)
    {
        return redirect()->route('checkout.show', $store->slug)
            ->with('info', 'Payment was cancelled. Your basket is saved.');
    }
}
```

### `app/Http/Controllers/Mall/PaymentController.php`
```php
<?php
namespace App\Http\Controllers\Mall;

use App\Http\Controllers\Controller;
use App\Services\PayFastService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PayFastService $payFastService) {}

    /**
     * PayFast ITN (Instant Transaction Notification) handler.
     *
     * CRITICAL NOTES:
     * - This route has NO CSRF protection (see routes/web.php)
     * - PayFast sends a POST to this URL from their server
     * - Must return HTTP 200 with empty body on success
     * - Must return HTTP 200 even on failure (PayFast retries on non-200)
     * - Log everything — debugging ITN issues is hard
     */
    public function itn(Request $request)
    {
        \Log::info('PayFast ITN received', $request->all());

        $data = $request->all();

        if (!$this->payFastService->validateItn($data)) {
            \Log::error('PayFast ITN validation failed', $data);
            return response('', 200); // Still return 200 to stop retries
        }

        $success = $this->payFastService->processSuccessfulItn($data);

        if (!$success) {
            \Log::error('PayFast ITN processing failed', $data);
        }

        return response('', 200); // Always return 200 to PayFast
    }
}
```

### `app/Http/Controllers/Vendor/VendorDashboardController.php`
```php
<?php
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VendorDashboardController extends Controller
{
    public function index(Request $request)
    {
        $store = $request->user()->store;
        $store->load('orders', 'products');

        $stats = [
            'total_orders'   => $store->orders()->count(),
            'paid_orders'    => $store->orders()->where('status', 'paid')->count(),
            'total_revenue'  => $store->orders()->where('status', '!=', 'pending')->sum('vendor_amount'),
            'total_products' => $store->products()->count(),
            'low_stock'      => $store->products()->where('manage_stock', true)->where('stock_qty', '<=', 5)->count(),
        ];

        $recentOrders = $store->orders()
            ->with('user', 'items')
            ->latest()
            ->limit(10)
            ->get();

        return view('vendor.dashboard', compact('store', 'stats', 'recentOrders'));
    }
}
```

---

## 10. VIEWS — KEY BLADE TEMPLATES

### `resources/views/mall/payfast_redirect.blade.php`
**CRITICAL:** This page auto-submits to PayFast within 1 second.
```html
<!DOCTYPE html>
<html>
<head>
    <title>Redirecting to payment...</title>
    <meta name="robots" content="noindex">
</head>
<body>
    <p>Redirecting you to PayFast to complete payment. Please wait...</p>

    <form id="payfast-form" action="{{ $paymentUrl }}" method="POST">
        @foreach($paymentFields as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
    </form>

    <script>
        document.getElementById('payfast-form').submit();
    </script>
</body>
</html>
```

### `resources/views/mall/window.blade.php` — Store Window View
Key sections this view must contain:
1. Store banner image (full width)
2. Store logo + name + tagline
3. Grid of up to 6 featured products (name, image, price only — teaser view)
4. Prominent "Enter Store" button linking to `route('mall.store.index', $store->slug)`
5. Store categories as tag chips
6. "Back to Mall" link

### `resources/views/mall/store/index.blade.php` — Inside Store
Key sections:
1. Store header (smaller than window — user is now inside)
2. Category filter sidebar
3. Search bar
4. Sort dropdown
5. Product grid with add-to-cart buttons
6. Cart summary sidebar or sticky cart button showing item count
7. "Go to checkout" button linking to `route('checkout.show', $store->slug)`
8. Back link: "← Back to [Store Name] entrance"

### `resources/views/mall/cart.blade.php` — Store Cart
Key sections:
1. Store name in heading ("Your basket at [Store Name]")
2. Line items table: image, name, qty stepper, unit price, line total
3. Order summary: subtotal, any fees, total
4. "Proceed to Checkout" button (redirects to login if not authed)
5. "Continue Shopping" link back to store
6. Empty state with "Go back to [Store Name]" CTA

### `resources/views/mall/checkout.blade.php` — Checkout Summary
Key sections:
1. Order summary (read-only — from cart)
2. Optional notes textarea
3. PayFast logo + "Pay Securely" branding
4. Total amount prominently displayed
5. Submit button posts to `route('checkout.initiate', $store->slug)`
6. Note: "You will be redirected to PayFast to complete payment"

---

## 11. PAYFAST — COMPLETE REFERENCE

### Payment Fields Sent to PayFast
```
Required:
  merchant_id         — from config (platform's ID)
  merchant_key        — from config (platform's key)
  return_url          — route('checkout.return', $storeSlug)
  cancel_url          — route('checkout.cancel', $storeSlug)
  notify_url          — route('payment.itn') — must be HTTPS, publicly accessible
  m_payment_id        — order_number (your reference)
  amount              — "1234.56" (formatted with 2 decimals, no thousand separator)
  item_name           — max 100 chars, no special characters
  signature           — MD5 of all fields in order

Optional (recommended):
  email_address       — customer email (pre-fills PayFast form)
  name_first          — customer first name
  item_description    — max 255 chars

Split payment (include only if vendor has PayFast account):
  merchant_receiver   — JSON: {"merchant_id":"X","merchant_key":"Y","percentage":90,"min":0,"max":0}
```

### ITN Fields Received FROM PayFast
```
m_payment_id        — your order_number (use to find the Payment record)
pf_payment_id       — PayFast's internal payment ID (store in payments table)
payment_status      — "COMPLETE" | "FAILED" | "PENDING"
item_name           — what you sent
amount_gross        — full amount paid (verify against order.total)
amount_fee          — PayFast's commission fee
amount_net          — amount_gross - amount_fee (what vendor receives if no split)
signature           — verify this first
```

### ITN Validation Checklist (do all 5 steps in order)
1. ✅ POST data received — log it immediately
2. ✅ Validate signature — regenerate MD5 and compare with `$data['signature']`
3. ✅ Confirm with PayFast server — POST to validate URL, expect response "VALID"
4. ✅ Check `payment_status === 'COMPLETE'`
5. ✅ Check `amount_gross` matches `order.total` (within 0.01 tolerance)
6. ✅ Check `m_payment_id` exists in your `payments` table
7. ✅ Check payment is not already marked complete (idempotency)

### Sandbox vs Production
- Sandbox: `https://sandbox.payfast.co.za/eng/process`
- Production: `https://www.payfast.co.za/eng/process`
- Set `PAYFAST_TESTMODE=true` in `.env` for all development work
- ITN notifications from sandbox require your `notify_url` to be publicly reachable (use ngrok for local dev)

---

## 12. BUSINESS RULES — EXHAUSTIVE LIST

### Commission
- Platform fee = 10% of `order.total` rounded to 2 decimals
- Vendor amount = `order.total - platform_fee` (NOT calculated as 90% — subtraction avoids rounding drift)
- Both values stored on every order record

### Cart Rules
- ONE cart per user per store (unique constraint in database)
- Guest carts identified by `session_token` (stored in PHP session as `cart_token`)
- Guest cart merges into user cart on login (see `Cart::mergeGuestCarts`)
- Cart is deleted after successful payment confirmation (ITN COMPLETE)
- Prices in cart are snapshotted at `addItem` time
- Prices in order items are snapshotted at `createFromCart` time
- If product price changes after item added to cart, cart retains OLD price (by design — snapshot model)

### Stock Rules
- Stock is NOT decremented when adding to cart
- Stock IS decremented when ITN confirms payment as COMPLETE
- Stock is re-incremented if order is cancelled (implement in admin cancel action)
- Products with `manage_stock = false` are always "in stock"
- Products with `stock_qty <= 0` and `manage_stock = true` cannot be added to cart

### Order Status Flow
```
pending → paid → processing → shipped → completed
                                      → cancelled (re-stock)
              → cancelled (re-stock)
```
- `pending` = order created, payment not yet confirmed
- `paid` = PayFast ITN confirmed payment
- `processing` = vendor confirmed and is preparing order
- `shipped` = vendor has dispatched
- `completed` = customer confirmed receipt (or auto after 14 days)
- `cancelled` = cancelled before shipping; re-increment stock

### Vendor Rules
- New vendor registrations → status = `pending`
- Vendors cannot access vendor dashboard until admin sets status = `active`
- Suspended vendors: their stores disappear from mall (active scope)
- Vendor must provide PayFast merchant_id for auto-split. If absent, full payment goes to platform
- Platform must manually pay vendor their 90% if no PayFast split configured

### Checkout Rules
- Auth is required at checkout (guests must register or log in)
- If user logs in at checkout, their guest cart is merged automatically
- An order is created BEFORE redirecting to PayFast
- The order remains in `pending` status until ITN fires
- Abandoned pending orders (no ITN after 2 hours) can be auto-cancelled via scheduled job

---

## 13. USER MODEL — ADD RELATIONSHIPS

Add these to `app/Models/User.php`:
```php
// Add to User model
public function store(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(\App\Models\Store::class, 'owner_user_id');
}

public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(\App\Models\Order::class);
}

public function isAdmin(): bool
{
    return (bool) $this->is_admin;
}

public function isVendor(): bool
{
    return $this->store !== null;
}
```

---

## 14. EVENTS

### Create these event classes

**`app/Events/OrderPaid.php`**
```php
<?php
namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

class OrderPaid
{
    use Dispatchable;
    public function __construct(public Order $order) {}
}
```

**`app/Listeners/SendOrderConfirmationEmail.php`**
```php
<?php
namespace App\Listeners;

use App\Events\OrderPaid;
use App\Mail\OrderConfirmationMail;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationEmail
{
    public function handle(OrderPaid $event): void
    {
        $order = $event->order->load('user', 'store', 'items');
        Mail::to($order->user->email)->queue(new OrderConfirmationMail($order));
        Mail::to($order->store->vendorProfile->contact_email)->queue(new NewOrderNotificationMail($order));
    }
}
```

Register in `app/Providers/EventServiceProvider.php`:
```php
protected $listen = [
    \App\Events\OrderPaid::class => [
        \App\Listeners\SendOrderConfirmationEmail::class,
    ],
];
```

---

## 15. CART MERGE ON LOGIN

In `app/Http/Controllers/Auth/AuthenticatedSessionController.php`, after the `Auth::attempt()` or `Auth::login()` call:
```php
// After successful authentication:
if (session()->has('cart_token')) {
    \App\Models\Cart::mergeGuestCarts(
        session('cart_token'),
        auth()->id()
    );
    session()->forget('cart_token');
}
```

If using Laravel Breeze, the login happens in `AuthenticatedSessionController::store()`.

---

## 16. IMPLEMENTATION ORDER (follow exactly)

### Phase 1 — Database & Models
1. Create all 11 migrations listed in Section 3 (in order)
2. Run `php artisan migrate`
3. Create all models listed in Section 4
4. Create `Store`, `StoreCategory`, `ProductCategory`, `VendorProfile` models
5. Add store/orders relationships to User model (Section 13)
6. Create seeder: `StoreCategory` with sample categories (Fashion, Electronics, Food, Home, Health, Services)
7. Run seeders: `php artisan db:seed --class=StoreCategorySeeder`

### Phase 2 — Config & Services
8. Create `config/payfast.php` (Section 6)
9. Create `config/mall.php` (Section 6)
10. Add `.env` variables (Section 2)
11. Create `app/Services/PayFastService.php` (Section 5)
12. Create `app/Services/CartService.php` (Section 5)

### Phase 3 — Middleware & Routes
13. Create `EnsureVendorIsActive` middleware (Section 8)
14. Create `EnsureIsAdmin` middleware (Section 8)
15. Register middleware aliases in `bootstrap/app.php` (Section 8)
16. Add all routes to `routes/web.php` (Section 7)

### Phase 4 — Mall Controllers & Views
17. Create `MallController` (Section 9)
18. Create `StoreController` (Section 9)
19. Create `CartController` (Section 9)
20. Create `CheckoutController` (Section 9)
21. Create `PaymentController` (Section 9)
22. Create views: `mall/index`, `mall/window`, `mall/store/index`, `mall/store/product`
23. Create views: `mall/cart`, `mall/checkout`, `mall/payfast_redirect`, `mall/checkout_return`

### Phase 5 — Auth Integration
24. Add cart merge on login (Section 15)
25. Create `AccountOrderController` for customer order history
26. Create views: `account/orders/index`, `account/orders/show`

### Phase 6 — Vendor Dashboard
27. Create `VendorRegistrationController` with store creation form
28. Create `VendorDashboardController` (Section 9)
29. Create `VendorProductController` (CRUD for products with image upload)
30. Create `VendorOrderController` (order list + status updates)
31. Create `VendorStoreController` (edit store profile, upload logo/banner)
32. Create `VendorEarningsController` (earnings breakdown)
33. Create all vendor views under `resources/views/vendor/`

### Phase 7 — Admin
34. Create `AdminStoreController` (list, show, approve, suspend)
35. Create `AdminOrderController` (all orders across all stores)
36. Create `AdminCommissionController` (platform revenue report)
37. Create admin views under `resources/views/admin/mall/`

### Phase 8 — Events & Emails
38. Create `OrderPaid` event and `SendOrderConfirmationEmail` listener (Section 14)
39. Create `OrderConfirmationMail` mailable and email view
40. Create `NewOrderNotificationMail` mailable (to vendor)
41. Register event listeners
42. Configure queue driver in `.env` (use `database` queue for simplicity)
43. Run `php artisan queue:table && php artisan migrate`

---

## 17. FILE STORAGE SETUP

```bash
# Create storage directories
mkdir -p storage/app/public/mall/stores/logos
mkdir -p storage/app/public/mall/stores/banners
mkdir -p storage/app/public/mall/products

# Create symlink so public can access storage
php artisan storage:link

# Add placeholder images
# - public/images/mall/default-store-logo.png
# - public/images/mall/default-store-banner.jpg
# - public/images/mall/default-product.png
```

---

## 18. CRITICAL GOTCHAS

1. **PayFast `notify_url` must be HTTPS and publicly reachable.** It cannot be `localhost`. Use ngrok or deploy to a server for development testing of ITN.

2. **Never trust the `return_url` to confirm payment.** The return URL fires when the user clicks "back to merchant" — payment may or may not be complete. Only trust the ITN.

3. **The `merchant_receiver` field for split payments is a JSON string**, not a JSON object. Use `json_encode()` before putting it in the payment data array.

4. **Signature must be generated AFTER all fields are populated**, including `merchant_receiver` if present. The signature covers all fields.

5. **Cart route model binding uses `store:slug`**, not `{store}`. The column override in the route definition (`/{store:slug}`) is required because `Cart` scoping uses `store_id`, not `slug`.

6. **Guest users have no `user_id`.** All queries that filter by `user_id` on carts must handle null correctly. Use `whereNull('user_id')` for guest carts explicitly.

7. **The `orders` table has no global cart — `store_id` is mandatory.** Every order belongs to exactly one store.

8. **Stock decrement happens in `PayFastService::processSuccessfulItn()`**, not in the order creation or checkout flow. If you put it elsewhere, stock will be decremented before payment is confirmed.

9. **PayFast ITN can arrive before or after the return URL.** Design the `checkout_return` view to be a "we're processing" holding page, not a confirmed success page.

10. **The `VerifyCsrfToken` exclusion for the ITN route is critical.** Without it, PayFast's server POST will be rejected with a 419 error and you'll never receive payment confirmations.

---

*End of specification — lifeat Online Mall v1.0*
*Document prepared by Claude | lifeat.online | May 2026*
