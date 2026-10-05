<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Stored SMS records were visible to staff and held one-time codes in clear
 * text (sign-up/PIN OTPs, WhatsApp verification and guarantor confirmation
 * codes). New messages store a redacted copy; this removes codes already held.
 * Redaction is intentionally irreversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sms_messages')->select(['id', 'message'])->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                $redacted = preg_replace('/((?:otp|code|pin)\b[^0-9]{0,20}?)\d{4,8}\b/i', '$1******', (string) $row->message);
                if ($redacted !== null && $redacted !== $row->message) {
                    DB::table('sms_messages')->where('id', $row->id)->update(['message' => $redacted]);
                }
            }
        });
    }

    public function down(): void
    {
        // Redacted codes cannot be restored.
    }
};
