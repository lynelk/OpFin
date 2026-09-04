<?php

namespace App\Models;

use App\Services\LoanService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoanSchedule extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'institution_id',
        'loan_id',
        'principal',
        'interest',
        'principal_outstanding',
        'interest_outstanding',
        'total_outstanding',
        'due_date',
    ];

    /**
     * Get the loan that owns the schedule.
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get the user that owns the schedule.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the institution that owns the schedule.
     */
    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Process payment against loan schedule
     * 
     * @param float $paymentAmount
     * @return array Result of the payment processing
     */
    public function applyPayment(float $paymentAmount)
    {
        // Initialize variables
        $remainingPayment = $paymentAmount;
        $interestPaid = 0;
        $principalPaid = 0;


        // First apply payment to interest
        if ($this->interest_outstanding > 0) {
            $interestPaid = min($this->interest_outstanding, $remainingPayment);
            $this->interest_outstanding -= $interestPaid;
            $remainingPayment -= $interestPaid;
        }

        // Then apply remaining payment to principal
        if ($remainingPayment > 0 && $this->principal_outstanding > 0) {
            $principalPaid = min($this->principal_outstanding, $remainingPayment);
            $this->principal_outstanding -= $principalPaid;
            $remainingPayment -= $principalPaid;
        }

        // Update the balance
        $this->total_outstanding = $this->principal_outstanding + $this->interest_outstanding;

        // Save the changes
        $this->save();
        return [
            'interestPaid' => $interestPaid,
            'principalPaid' => $principalPaid,
        ];
    }
}
