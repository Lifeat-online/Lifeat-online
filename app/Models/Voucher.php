<?php

namespace App\Models;

use App\Models\Concerns\HasContentTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Voucher extends Model
{
    use HasFactory, HasContentTranslations;

    protected array $translatable = [
        'title',
        'description',
        'terms',
    ];

    public const TYPE_DISCOUNT_AMOUNT = 'discount_amount';
    public const TYPE_DISCOUNT_PERCENT = 'discount_percent';
    public const TYPE_FIXED_PRICE = 'fixed_price';
    public const TYPE_PROMO_OFFER = 'promo_offer';

    protected $fillable = [
        'listing_id',
        'created_by_user_id',
        'title',
        'slug',
        'description',
        'voucher_type',
        'discount_amount',
        'discount_percent',
        'currency',
        'usage_limit',
        'redemptions_count',
        'start_at',
        'end_at',
        'terms',
        'status',
        'published_at',
        'last_usage_threshold_notified',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'published_at' => 'datetime',
            'meta_json' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'voucher_category')->withTimestamps();
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(VoucherRedemption::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->published()
            ->where(function (Builder $query) {
                $query->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function (Builder $query) {
                $query->whereNull('end_at')->orWhere('end_at', '>=', now());
            })
            ->whereColumn('redemptions_count', '<', 'usage_limit');
    }

    public function remainingUses(): int
    {
        return max(0, (int) $this->usage_limit - (int) $this->redemptions_count);
    }

    public function isCurrentlyActive(): bool
    {
        if ($this->status !== 'published') {
            return false;
        }

        if ($this->start_at && $this->start_at->isFuture()) {
            return false;
        }

        if ($this->end_at && $this->end_at->isPast()) {
            return false;
        }

        return $this->remainingUses() > 0;
    }

    public static function uniqueSlugForListing(int $listingId, string $title, ?Voucher $voucher = null): string
    {
        $base = Str::slug($title);
        $slug = $base !== '' ? $base : 'voucher';
        $suffix = 1;

        while (
            self::query()
                ->where('listing_id', $listingId)
                ->where('slug', $slug)
                ->when($voucher, fn (Builder $query) => $query->whereKeyNot($voucher->id))
                ->exists()
        ) {
            $slug = ($base !== '' ? $base : 'voucher').'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public function formattedValue(): ?string
    {
        $currency = $this->currency ?: 'ZAR';

        return match ($this->voucher_type) {
            self::TYPE_DISCOUNT_PERCENT => $this->discount_percent !== null ? rtrim(rtrim(number_format((float) $this->discount_percent, 2, '.', ''), '0'), '.').'%' : null,
            self::TYPE_DISCOUNT_AMOUNT, self::TYPE_FIXED_PRICE => $this->discount_amount !== null ? $currency.' '.number_format((float) $this->discount_amount, 2) : null,
            default => null,
        };
    }
}
