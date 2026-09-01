<?php

namespace App\Models;

use App\Scopes\InstitutionScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class Loan extends Model
{
    use HasFactory, SoftDeletes;

    public $fillable = [
        'user_id', 'loan_product_id', 'loan_product_term_id', 'institution_id', 'loan_application_id',
        'amount', 'status', 'reason', 'disbursed_at', 'duration', 'repayment_amount', 'repayment_start_date',
    ];

    public $casts = [
        'repayment_start_date' => 'datetime',
        'disbursed_at' => 'datetime',
    ];

    public static function boot()
    {
        parent::boot();
        static::addGlobalScope(new InstitutionScope);
        static::created(function ($loan) {
            $loan->createLoanSchedule();
        });
    }

    public function createLoanSchedule(): void
    {
        if (! $this->repayment_amount || ! $this->duration || ! $this->repayment_start_date) {
            throw new InvalidArgumentException('Missing required loan details.');
        }

        $duration = (int) $this->duration;
        $loanAmount = self::minorUnit((float) $this->amount, 'loan amount');
        $targetRepayment = self::minorUnit((float) $this->repayment_amount, 'repayment amount');
        if ($targetRepayment < $loanAmount) {
            throw new InvalidArgumentException('Repayment amount cannot be lower than principal.');
        }

        $interestType = (string) $this->loanProductTerm->interest_type;
        $repaymentFrequency = (string) $this->loanProductTerm->repayment_frequency;
        $configuredRate = (float) $this->loanProductTerm->interest_rate / 100;
        $interestCycle = (string) $this->loanProductTerm->interest_cycle;
        $repaymentStartDate = Carbon::parse($this->repayment_start_date);
        $termRate = self::getTermInterestRate($interestCycle, $configuredRate, $duration);
        $numberOfInstallments = self::getInstallments($duration, $repaymentFrequency);
        $daysBetweenInstallments = self::getDays($repaymentFrequency);
        $expectedRepayment = self::getRepaymentAmount(
            $configuredRate,
            $loanAmount,
            $interestType,
            $numberOfInstallments,
            $interestCycle,
            $duration,
        );
        if ($targetRepayment !== $expectedRepayment) {
            throw new InvalidArgumentException("Legacy repayment amount {$targetRepayment} does not reconcile to the configured loan formula {$expectedRepayment}.");
        }

        $termStart = $this->disbursed_at
            ? Carbon::parse($this->disbursed_at)
            : $repaymentStartDate->copy()->subDays($daysBetweenInstallments);
        $termEndDate = $termStart->copy()->addDays($duration);

        if (strcasecmp($interestType, 'Flat') === 0) {
            $totalInterest = $targetRepayment - $loanAmount;
            for ($position = 1; $position <= $numberOfInstallments; $position++) {
                $principal = self::allocateMinor($loanAmount, $numberOfInstallments, $position);
                $interest = self::allocateMinor($totalInterest, $numberOfInstallments, $position);
                $dueDate = $this->boundedDueDate($repaymentStartDate, $termEndDate, $daysBetweenInstallments, $position);
                $this->createScheduleItem($dueDate, $principal, $interest);
            }

            return;
        }

        if (strcasecmp($interestType, 'Amortization') !== 0) {
            throw new InvalidArgumentException('Unsupported interest type: '.$interestType);
        }

        $periodRate = $termRate / $numberOfInstallments;
        $periodPayment = $periodRate == 0.0
            ? $loanAmount / $numberOfInstallments
            : ($loanAmount * $periodRate * pow(1 + $periodRate, $numberOfInstallments))
                / (pow(1 + $periodRate, $numberOfInstallments) - 1);

        $remainingPrincipal = $loanAmount;
        $schedule = [];
        $interestTotal = 0;
        for ($position = 1; $position <= $numberOfInstallments; $position++) {
            $interest = $periodRate == 0.0 ? 0 : (int) round($remainingPrincipal * $periodRate);
            if ($position === $numberOfInstallments) {
                $principal = $remainingPrincipal;
            } else {
                $principal = max(0, min($remainingPrincipal, (int) round($periodPayment) - $interest));
            }
            $remainingPrincipal -= $principal;
            $interestTotal += $interest;
            $schedule[] = ['principal' => $principal, 'interest' => $interest];
        }

        if ($remainingPrincipal !== 0) {
            throw new InvalidArgumentException('Amortization schedule did not allocate principal exactly.');
        }

        $targetInterest = $targetRepayment - $loanAmount;
        $interestAdjustment = $targetInterest - $interestTotal;
        $last = count($schedule) - 1;
        if ($schedule[$last]['interest'] + $interestAdjustment < 0) {
            throw new InvalidArgumentException('Amortization rounding adjustment would create negative interest.');
        }
        $schedule[$last]['interest'] += $interestAdjustment;

        foreach ($schedule as $index => $item) {
            $position = $index + 1;
            $dueDate = $this->boundedDueDate($repaymentStartDate, $termEndDate, $daysBetweenInstallments, $position);
            $this->createScheduleItem($dueDate, $item['principal'], $item['interest']);
        }
    }

    private function boundedDueDate(Carbon $startDate, Carbon $termEndDate, int $daysBetween, int $position): Carbon
    {
        $candidate = $startDate->copy()->addDays($daysBetween * ($position - 1));

        return $candidate->greaterThan($termEndDate) ? $termEndDate->copy() : $candidate;
    }

    private function createScheduleItem(Carbon $dueDate, int $principal, int $interest): void
    {
        if ($principal < 0 || $interest < 0 || ($principal + $interest) <= 0) {
            throw new InvalidArgumentException('Legacy schedule components must be non-negative and the instalment total must be positive.');
        }

        LoanSchedule::create([
            'loan_id' => $this->id,
            'user_id' => $this->user_id,
            'institution_id' => $this->institution_id,
            'due_date' => $dueDate,
            'principal' => $principal,
            'interest' => $interest,
            'principal_outstanding' => $principal,
            'interest_outstanding' => $interest,
            'total_outstanding' => $principal + $interest,
        ]);
    }

    public static function getTermInterestRate(string $interestCycle, float $interestRate, int $termInDays): float
    {
        if ($interestRate < 0 || $termInDays <= 0) {
            throw new InvalidArgumentException('Interest rate must be non-negative and term must be positive.');
        }

        $dailyRate = match (strtolower($interestCycle)) {
            'daily' => $interestRate,
            'weekly' => $interestRate / 7,
            'monthly' => $interestRate / 30,
            default => throw new InvalidArgumentException("Unsupported interest cycle: $interestCycle"),
        };

        return $dailyRate * $termInDays;
    }

    public static function getRepaymentAmount($interestRate, $loanAmount, $interestType, $numberOfInstallments, $interestCycle, $duration): int
    {
        $loanAmountMinor = self::minorUnit((float) $loanAmount, 'loan amount');
        $installments = (int) $numberOfInstallments;
        if ($installments <= 0) {
            throw new InvalidArgumentException('Number of instalments must be positive.');
        }

        $termRate = self::getTermInterestRate((string) $interestCycle, (float) $interestRate, (int) $duration);
        if (strcasecmp((string) $interestType, 'Flat') === 0) {
            return $loanAmountMinor + (int) round($loanAmountMinor * $termRate);
        }

        if (strcasecmp((string) $interestType, 'Amortization') === 0) {
            if ($termRate == 0.0) {
                return $loanAmountMinor;
            }
            $periodRate = $termRate / $installments;
            $periodPayment = ($loanAmountMinor * $periodRate * pow(1 + $periodRate, $installments))
                / (pow(1 + $periodRate, $installments) - 1);

            return (int) round($periodPayment * $installments);
        }

        throw new InvalidArgumentException('Unsupported interest type.');
    }

    public static function getRepaymentStartDate(string $repaymentFrequency)
    {
        return match (strtolower($repaymentFrequency)) {
            'daily' => now()->addDay(),
            'weekly' => now()->addDays(7),
            'fortnightly' => now()->addDays(14),
            'monthly' => now()->addDays(30),
            default => throw new InvalidArgumentException('Unsupported repayment frequency'),
        };
    }

    public static function getInstallments(int $duration, string $frequency): int
    {
        if ($duration <= 0) {
            throw new InvalidArgumentException('Loan duration must be positive.');
        }
        $daysInFrequency = self::getDaysInFrequency($frequency);

        return max(1, (int) ceil($duration / $daysInFrequency));
    }

    public static function getDays(string $frequency): int
    {
        return self::getDaysInFrequency($frequency);
    }

    public static function getDaysInFrequency(string $frequency): int
    {
        return match (strtolower($frequency)) {
            'daily' => 1,
            'weekly' => 7,
            'fortnightly' => 14,
            'monthly' => 30,
            default => throw new InvalidArgumentException('Unsupported repayment frequency'),
        };
    }

    private static function allocateMinor(int $total, int $count, int $position): int
    {
        if ($total < 0 || $count <= 0 || $position < 1 || $position > $count) {
            throw new InvalidArgumentException('Invalid monetary allocation parameters.');
        }
        $base = intdiv($total, $count);

        return $position === $count ? $base + ($total % $count) : $base;
    }

    private static function minorUnit(float $value, string $label): int
    {
        if (! is_finite($value)) {
            throw new InvalidArgumentException("{$label} must be finite.");
        }
        $rounded = (int) round($value);
        if (abs($value - $rounded) > 0.000001) {
            throw new InvalidArgumentException("{$label} contains fractional minor units.");
        }

        return $rounded;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function loanProduct()
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function loanProductTerm()
    {
        return $this->belongsTo(LoanProductTerm::class);
    }

    public function schedules()
    {
        return $this->hasMany(LoanSchedule::class);
    }

    public function getOutstandingBalanceAttribute()
    {
        return (int) round((float) $this->schedules()->sum('total_outstanding'));
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }
}
