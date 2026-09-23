<?php

namespace App\Services;

use App\Models\CustomerWallet;
use App\Models\Loan;
use App\Models\User;

class UssdJourneyService
{
    public function __construct(
        private readonly CustomerCreditProfileService $profiles,
        private readonly ProgrammeDeliveryService $programmes,
    ) {}

    public function handle(string $sessionId, string $phone, string $text): array
    {
        $user = User::query()->where('phone', $phone)->first();
        if (! $user) {
            return $this->end('This number is not registered with OpFin. Use the OpFin app or WhatsApp to register.');
        }

        $parts = $text === '' ? [] : explode('*', trim($text));
        if ($parts === []) {
            return $this->continue("OpFin\n1. My limit\n2. Borrow\n3. Repay\n4. My loan\n5. Complete profile\n6. Help\n7. Programme check-in");
        }

        return match ($parts[0]) {
            '1' => $this->limit($user),
            '2' => $this->borrow($user),
            '3' => $this->repay($user),
            '4' => $this->loan($user),
            '5' => $this->profile($user),
            '6' => $this->end('Help: use the OpFin app or WhatsApp support. Never share your PIN or OTP with anyone.'),
            '7' => $this->programme($user, $parts),
            default => $this->end('Invalid choice. Dial again and choose 1 to 7.'),
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

    private function programme(User $user, array $parts): array
    {
        try {
            $state = $this->programmes->dueInstruments($user, 'ussd', $user->preferred_language ?? 'en');
        } catch (\InvalidArgumentException $exception) {
            return $this->end($exception->getMessage());
        }

        $instruments = $state['instruments'] ?? [];
        if ($instruments === []) {
            return $this->end('You have no programme check-in due right now.');
        }

        if (count($parts) === 1) {
            $lines = ['Choose check-in:'];
            foreach (array_slice($instruments, 0, 5) as $index => $instrument) {
                $lines[] = ($index + 1).'. '.substr($instrument['name'], 0, 28);
            }

            return $this->continue(implode("\n", $lines));
        }

        $instrumentIndex = ((int) $parts[1]) - 1;
        if (! isset($instruments[$instrumentIndex])) {
            return $this->end('Invalid programme check-in choice.');
        }

        $instrument = $instruments[$instrumentIndex];
        $questions = array_values($instrument['questions'] ?? []);
        if ($questions === []) {
            return $this->end('This programme check-in has no active questions.');
        }

        $answerParts = array_slice($parts, 2);
        if (count($answerParts) < count($questions)) {
            $question = $questions[count($answerParts)];

            return $this->continue(
                $this->ussdQuestionPrompt($question, count($answerParts) + 1, count($questions))
            );
        }

        $answers = [];
        foreach ($questions as $index => $question) {
            try {
                $answers[] = [
                    'question_id' => $question['id'],
                    'value' => $this->parseUssdAnswer($question, $answerParts[$index] ?? ''),
                ];
            } catch (\InvalidArgumentException $exception) {
                return $this->end('Check-in answer '.($index + 1).' is invalid. Please dial again and retry.');
            }
        }

        try {
            $this->programmes->submitResponse(
                $user,
                (int) $instrument['id'],
                $answers,
                'ussd',
                $state['locale'] ?? 'en',
                isset($instrument['schedule']['id']) ? (int) $instrument['schedule']['id'] : null,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->end($exception->getMessage());
        }

        return $this->end('Programme check-in saved. Thank you.');
    }

    private function ussdQuestionPrompt(array $question, int $position, int $total): string
    {
        $prompt = $position.'/'.$total.' '.substr((string) ($question['prompt'] ?? 'Question'), 0, 120);
        $type = $question['answer_type'] ?? 'text';
        $options = $question['options'] ?? [];

        if ($type === 'boolean') {
            return $prompt."\n1. Yes\n2. No";
        }

        if ($type === 'single_choice') {
            $lines = [$prompt];
            foreach (array_slice($options, 0, 7) as $index => $option) {
                $lines[] = ($index + 1).'. '.substr((string) $option, 0, 25);
            }

            return implode("\n", $lines);
        }

        if ($type === 'multi_choice') {
            return $prompt."\nEnter choice numbers separated by commas.";
        }

        return $prompt;
    }

    private function parseUssdAnswer(array $question, string $raw): mixed
    {
        $type = $question['answer_type'] ?? 'text';
        $options = $question['options'] ?? [];
        $raw = trim($raw);

        if ($type === 'boolean') {
            return match ($raw) {
                '1' => true,
                '2' => false,
                default => throw new \InvalidArgumentException('Invalid yes/no answer.'),
            };
        }

        if ($type === 'single_choice') {
            $index = ((int) $raw) - 1;
            if (! isset($options[$index])) {
                throw new \InvalidArgumentException('Invalid programme choice.');
            }

            return $options[$index];
        }

        if ($type === 'multi_choice') {
            $selected = [];
            foreach (explode(',', $raw) as $choice) {
                $index = ((int) trim($choice)) - 1;
                if (! isset($options[$index])) {
                    throw new \InvalidArgumentException('Invalid programme choices.');
                }
                $selected[] = $options[$index];
            }

            return array_values(array_unique($selected));
        }

        return $raw;
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
