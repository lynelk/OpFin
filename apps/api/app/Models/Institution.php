<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Institution extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'address', 'phone', 'email', 'status', 'lender_relationship', 'country', 'regulator_code', 'licence_class', 'authority_basis', 'authority_reference', 'authority_valid_until', 'rate_change_approval_required'];

    protected function casts(): array
    {
        return ['authority_valid_until' => 'date', 'rate_change_approval_required' => 'boolean'];
    }

    public function lenderDisclosure(): array
    {
        return [
            'institution_id' => $this->id,
            'legal_name' => $this->name,
            'licensed_entity_name' => $this->name,
            'lender_relationship' => $this->lender_relationship,
            'country' => $this->country,
            'regulator' => $this->regulator_code,
            'licence_class' => $this->licence_class,
            'authority_basis' => $this->authority_basis,
            'authority_reference' => $this->authority_reference,
            'umra_license_number' => $this->regulator_code === 'UMRA' ? $this->authority_reference : null,
            'business_address' => $this->address,
            'opfin_role' => 'infrastructure_and_orchestration',
        ];
    }

    public function loanProducts()
    {
        return $this->hasMany(LoanProduct::class);
    }

    public function loanApplications()
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function loanSchedules()
    {
        return $this->hasMany(LoanSchedule::class);
    }

    protected static function booted()
    {
        static::deleting(function ($institution) {
            if (! $institution->isForceDeleting()) {
                $institution->loanApplications->each->delete();
                $institution->loanProducts->each->delete();
                $institution->users->each->delete();
                $institution->transactions->each->delete();
                $institution->loanSchedules->each->delete();
            }
        });
    }
}
