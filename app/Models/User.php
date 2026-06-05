<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'preferred_locale',
        'username',
        'phone',
        'bio',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function classifieds(): HasMany
    {
        return $this->hasMany(Classified::class);
    }

    public function approvedArticles(): HasMany
    {
        return $this->hasMany(Article::class, 'editor_user_id');
    }

    public function articleWordLedgers(): HasMany
    {
        return $this->hasMany(ArticleWordLedger::class, 'writer_user_id');
    }

    public function writerPaymentBatches(): HasMany
    {
        return $this->hasMany(WriterPaymentBatch::class, 'created_by_user_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function writerApplications(): HasMany
    {
        return $this->hasMany(WriterApplication::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function mallStore(): HasOne
    {
        return $this->hasOne(MallStore::class, 'owner_user_id');
    }

    public function mallCarts(): HasMany
    {
        return $this->hasMany(MallCart::class);
    }

    public function mallOrders(): HasMany
    {
        return $this->hasMany(MallOrder::class);
    }

    public function isMallVendor(): bool
    {
        return $this->mallStore()->exists();
    }

    public function transportRequests(): HasMany
    {
        return $this->hasMany(TransportRequest::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function browserPushSubscriptions(): HasMany
    {
        return $this->hasMany(BrowserPushSubscription::class);
    }

    public function councillorProfile(): HasOne
    {
        return $this->hasOne(Councillor::class);
    }

    public function transportDriver(): HasOne
    {
        return $this->hasOne(TransportDriver::class);
    }

    public function managedTransportDrivers(): HasMany
    {
        return $this->hasMany(TransportDriver::class, 'manager_user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function hasRole(string ...$roles): bool
    {
        $aliases = [
            'admin' => 'super_admin',
            'editor' => 'content_manager',
            'staff' => 'sales_staff',
            'member' => 'registered_user',
            'dev' => 'developer',
            'developer' => 'dev',
            'dev_owner' => 'dev',
        ];

        $requested = collect($roles)
            ->flatMap(fn (string $role) => [$role, $aliases[$role] ?? null])
            ->filter()
            ->unique()
            ->values();

        if ($requested->contains((string) $this->role)) {
            return true;
        }

        if (! static::roleTablesExist()) {
            return false;
        }

        try {
            return $this->roles()
                ->whereIn('slug', $requested->all())
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    protected static function roleTablesExist(): bool
    {
        return Cache::driver('array')->rememberForever('users.role_tables_exist', function (): bool {
            return Schema::hasTable('roles') && Schema::hasTable('role_user');
        });
    }
}
