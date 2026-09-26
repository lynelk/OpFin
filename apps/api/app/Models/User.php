<?php

namespace App\Models;

use App\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    public const ROLE_PLATFORM_ADMIN = 'platform_admin';

    public const ROLE_OPERATIONS = 'operations';

    public const ROLE_CUSTOMER = 'customer';

    public const ROLE_EMPLOYER_ADMIN = 'employer_admin';

    public const ROLE_PROGRAMME_PARTNER = 'programme_partner';

    public const ROLE_PARTNER_API = 'partner_api';

    public const ROLE_SUPPORT = 'support';

    public const ROLES = [
        self::ROLE_PLATFORM_ADMIN,
        self::ROLE_OPERATIONS,
        self::ROLE_CUSTOMER,
        self::ROLE_EMPLOYER_ADMIN,
        self::ROLE_PROGRAMME_PARTNER,
        self::ROLE_PARTNER_API,
        self::ROLE_SUPPORT,
    ];

    public const ROLE_PERMISSIONS = [
        self::ROLE_PLATFORM_ADMIN => ['*'],
        self::ROLE_OPERATIONS => ['profile.view', 'operations.view', 'audit.view', 'kyc.review', 'credit.review', 'reconciliation.manage', 'support.manage', 'compliance.report'],
        self::ROLE_CUSTOMER => ['profile.view', 'kyc.submit', 'consent.manage'],
        self::ROLE_EMPLOYER_ADMIN => ['profile.view', 'employer.view'],
        self::ROLE_PROGRAMME_PARTNER => ['profile.view', 'programme.view'],
        self::ROLE_PARTNER_API => ['profile.view', 'partner.essentials'],
        self::ROLE_SUPPORT => ['profile.view', 'support.view', 'kyc.review', 'support.manage'],
    ];

    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'first_name',
        'other_name',
        'last_name',
        'phone',
        'password',
        'role',
        'is_admin',
        'institution_id',
        'email',
        'phone_verified_at',
        'email_verified_at',
        'national_id',
        'date_of_birth',
        'nin_status',
        'api_status',
        'validated_at',
        'preferred_language',
        'accessibility_preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'accessibility_preferences' => 'array',
            'can_manage_platform_credit' => 'boolean',
        ];
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new InstitutionScope);
    }

    public function outstandingAmount()
    {
        return round(LoanSchedule::where('user_id', $this->id)->sum('total_outstanding'));
    }

    public function creditScore()
    {
        return CreditScore::where('user_id', $this->id)
            ->latest('created_at')
            ->first();
    }

    public function creditProfile()
    {
        return $this->hasOne(CreditProfile::class);
    }

    public function phoneNumbers()
    {
        return $this->hasMany(CustomerPhoneNumber::class);
    }

    public function wallets()
    {
        return $this->hasMany(CustomerWallet::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function permissions(): array
    {
        return self::ROLE_PERMISSIONS[$this->role] ?? [];
    }
}
