<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WriterApplication extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const ROLE_WRITER = 'writer';
    public const ROLE_STAFF = 'staff';

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'username',
        'profile_bio',
        'profile_photo_path',
        'available_on_whatsapp',
        'sample_article_title',
        'sample_article_body',
        'sample_advert_title',
        'sample_advert_body',
        'id_document_path',
        'banking_document_path',
        'proof_of_residence_path',
        'bank_name',
        'account_holder_name',
        'account_number',
        'branch_code',
        'status',
        'assigned_role',
        'submitted_at',
        'reviewed_at',
        'onboarded_at',
        'access_notified_at',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'available_on_whatsapp' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'access_notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function reviewStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
        ];
    }

    public static function decisionStatuses(): array
    {
        return [
            self::STATUS_UNDER_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
        ];
    }

    public static function onboardingRoles(): array
    {
        return [
            self::ROLE_WRITER,
            self::ROLE_STAFF,
        ];
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
