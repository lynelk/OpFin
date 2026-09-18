<?php

namespace App\Services;

use App\Models\CustomerWallet;
use App\Models\Loan;
use App\Models\User;

class UssdJourneyService
{
    public function __construct(private readonly CustomerCreditProfileService $profiles) {}

    public function handle(string $sessionId, string $phone, string $text): array
    {
        $user = User::query()->where('phone', $phone)->first();
        if (! $user) {
            return $this->end('This number is not registered with OpFin. Use the OpFin app or WhatsApp to register.');
        }

        $parts = $text === '' ? [] : explode('*', trim($text));
        if ($parts === []) {
            return $this->continue("OpFin\n1. My limit\n2. Borrow\n3. Repay\n4. My loan\n5. Complete profile\n6. Help");
        }

        return match ($parts[0]) {
            '1' => $this->limit($user),
            '2' => $this->borrow($user),
            '3' => $this->repay($user),
            '4' => $this->loan($user),
            '5' => $this->profile($user),
            '6' => $this->end('Help: use the OpFin app or WhatsApp support. Never share your PIN or OTP with anyone.'),
            default => $this->end('Invalid choice. Dial again and choose 1 to 6.'),
        };
    }

    private function limit(User $user): array
    {
        $state = $this->profiles->status($user);
        $profile = $state['profile'];

        if (! $profile || $profile->status === 'pending') {
            return $this->end('Your limit is not ready. '.$state['next_action']['label'].'.');
        }

        return $this->end(
            'Score: '.round((float) $profile->composite_score)."/100\nAvailable: UGX ".number_format((int) $profile->available_to_borrow_minor),
        );
    }

    private function borrow(User $user): array
    {
        $state = $this->profiles->status($user);
        $profile = $state['profile'];

        if (! $profile || $profile->available_to_borrow_minor <= 0) {
            return $this->end('No amount is available to borrow now. '.$state['next_action']['label'].'.');
        }

        return $this->end(
            'Available: UGX '.number_format((int) $profile->available_to_borrow_minor).'. For your security, finish the loan request in the OpFin app or secure WhatsApp link.',
        );
    }

    private function repay(User $user): array
    {
        $state = $this->profiles->status($user);
        $profile = $state['profile'];

        if (! $profile || $profile->total_outstanding_minor <= 0) {
            return $this->end('You have no outstanding OpFin loan.');
        }

        $wallet = CustomerWallet::query()
            ->where('user_id', $user->id)
            ->where('is_default_repayment', true)
            ->first();

        return $this->end(
            'Outstanding: UGX '.number_format((int) $profile->total_outstanding_minor).
            '. Amount due now: UGX '.number_format((int) $profile->amount_due_minor).
            ($wallet
                ? '. Repayment wallet: '.substr($wallet->msisdn, -4)
                : '. Add a repayment wallet in OpFin.'),
        );
    }

    private function loan(User $user): array
    {
        $loan = Loan::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['Cleared', 'Cancelled', 'Rejected', 'Reversed'])
            ->latest()
            ->first();

        if (! $loan) {
            return $this->end('You have no active OpFin loan.');
        }

        return $this->end(
            'Loan status: '.$loan->status.'. Outstanding: UGX '.number_format((int) $loan->outstanding_balance).'.',
        );
    }

    private function profile(User $user): array
    {
        $state = $this->profiles->status($user);
        $next = $state['next_action'];

        if ($next['code'] === 'VERIFY_IDENTITY') {
            return $this->end('Complete National ID photos and selfie in the OpFin app or WhatsApp. USSD cannot take photos.');
        }

        return $this->end($next['label'].'.');
    }

    private function continue(string $message): array
    {
        return ['action' => 'CON', 'message' => $message];
    }

    private function end(string $message): array
    {
        return ['action' => 'END', 'message' => $message];
    }
}
