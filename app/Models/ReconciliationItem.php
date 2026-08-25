<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReconciliationItem extends Model
{
    public const STATUS_REQUIRES_PROVIDER_MATCH = 'requires_provider_match';

    public const STATUS_MATCHED = 'matched';

    public const STATUS_EXCEPTION = 'exception';

    public const STATUS_WRITTEN_OFF = 'written_off';

    public const EXCEPTION_MISSING_PROVIDER_RECORD = 'missing_provider_record';

    public const EXCEPTION_MISSING_OPFIN_RECORD = 'missing_opfin_record';

    public const EXCEPTION_AMOUNT_MISMATCH = 'amount_mismatch';

    public const EXCEPTION_CURRENCY_MISMATCH = 'currency_mismatch';

    public const EXCEPTION_DIRECTION_MISMATCH = 'direction_mismatch';

    public const EXCEPTION_STATUS_MISMATCH = 'status_mismatch';

    public const EXCEPTION_DUPLICATE_PROVIDER_REFERENCE = 'duplicate_provider_reference';

    public const EXCEPTION_DUPLICATE_SYSTEM_REFERENCE = 'duplicate_system_reference';

    protected $fillable = [
        'reconciliation_run_id',
        'mobile_money_transaction_id',
        'provider_statement_record_id',
        'provider_reference',
        'internal_reference',
        'direction',
        'currency',
        'system_status',
        'provider_status',
        'exception_type',
        'system_amount_minor',
        'provider_amount_minor',
        'status',
        'notes',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'system_amount_minor' => 'integer',
            'provider_amount_minor' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function providerStatementRecord()
    {
        return $this->belongsTo(ProviderStatementRecord::class);
    }
}
