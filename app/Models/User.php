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
    public const ROLE_SUPPORT = 'support';

    public const ROLES = [
        self::ROLE_PLATFORM_ADMIN,
        self::ROLE_OPERATIONS,
        self::ROLE_CUSTOMER,
        self::ROLE_EMPLOYER_ADMIN,
        self::ROLE_SUPPORT,
    ];

    public const ROLE_PERMISSIONS = [
        self::ROLE_PLATFORM_ADMIN => ['*'],
        self::ROLE_OPERATIONS => ['profile.view', 'operations.view', 'audit.view'],
        self::ROLE_CUSTOMER => ['profile.view'],
        self::ROLE_EMPLOYER_ADMIN => ['profile.view', 'employer.view'],
        self::ROLE_SUPPORT => ['profile.view', 'support.view'],
    ];

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
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
        'validated_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
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
