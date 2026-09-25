<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_books', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('financial_space_id')->constrained('financial_spaces');
            $table->char('currency', 3);
            $table->string('ownership_model', 24);
            $table->string('status', 24)->default('draft');
            $table->date('cutover_date');
            $table->bigInteger('initial_unit_price_minor')->nullable();
            $table->unsignedInteger('unit_scale')->default(1000000);
            $table->unsignedInteger('policy_version')->default(1);
            $table->unsignedBigInteger('journal_sequence')->default(0);
            $table->char('last_journal_hash', 64)->nullable();
            $table->date('closed_through')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->json('policy');
            $table->timestamps();
            $table->unique(['financial_space_id', 'currency']);
        });
        Schema::create('club_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained('club_books');
            $table->string('code', 80);
            $table->string('name', 160);
            $table->string('kind', 20);
            $table->boolean('controlled')->default(false);
            $table->timestamps();
            $table->unique(['book_id', 'code']);
        });
        Schema::create('club_treasury_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained('club_books');
            $table->foreignId('treasury_account_id')->unique()->constrained('financial_space_treasury_accounts');
            $table->foreignId('account_id')->unique()->constrained('club_accounts');
            $table->timestamps();
        });
        Schema::create('club_instructions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('book_id')->constrained('club_books');
            $table->string('type', 48);
            $table->date('business_date');
            $table->string('status', 24)->default('pending');
            $table->string('idempotency_key', 160);
            $table->char('payload_hash', 64);
            $table->json('payload');
            $table->foreignId('maker_id')->constrained('users');
            $table->foreignId('checker_id')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_reason', 500)->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
            $table->unique(['book_id', 'idempotency_key']);
            $table->index(['book_id', 'status', 'business_date']);
        });
        Schema::create('club_journals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('book_id')->constrained('club_books');
            $table->foreignId('instruction_id')->unique()->constrained('club_instructions');
            $table->unsignedBigInteger('sequence');
            $table->date('business_date');
            $table->string('event_type', 48);
            $table->string('description', 300);
            $table->string('evidence_reference', 255);
            $table->bigInteger('total_debits_minor');
            $table->bigInteger('total_credits_minor');
            $table->char('previous_hash', 64)->nullable();
            $table->char('content_hash', 64);
            $table->timestamp('posted_at');
            $table->unique(['book_id', 'sequence']);
            $table->index(['book_id', 'business_date']);
        });
        Schema::create('club_journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_id')->constrained('club_journals');
            $table->foreignId('book_id')->constrained('club_books');
            $table->foreignId('account_id')->constrained('club_accounts');
            $table->foreignId('member_user_id')->nullable()->constrained('users');
            $table->string('direction', 6);
            $table->bigInteger('amount_minor');
            $table->index(['book_id', 'account_id']);
            $table->index(['book_id', 'member_user_id']);
        });
        Schema::create('club_treasury_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained('club_books');
            $table->foreignId('treasury_transaction_id')->unique()->constrained('financial_space_transactions');
            $table->foreignId('journal_id')->constrained('club_journals');
            $table->timestamp('created_at');
        });
        Schema::create('club_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained('club_books');
            $table->foreignId('user_id')->constrained('users');
            $table->bigInteger('units_micro')->default(0);
            $table->bigInteger('capital_minor')->default(0);
            $table->timestamps();
            $table->unique(['book_id', 'user_id']);
        });
        Schema::create('club_member_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained('club_books');
            $table->foreignId('member_id')->constrained('club_members');
            $table->foreignId('instruction_id')->constrained('club_instructions');
            $table->foreignId('journal_id')->nullable()->constrained('club_journals');
            $table->string('type', 32);
            $table->date('business_date');
            $table->bigInteger('units_delta_micro')->default(0);
            $table->bigInteger('capital_delta_minor')->default(0);
            $table->bigInteger('cash_flow_minor')->default(0);
            $table->json('evidence');
            $table->timestamp('created_at');
            $table->unique(['instruction_id', 'member_id']);
        });
        Schema::create('club_contribution_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained('club_books');
            $table->foreignId('member_user_id')->constrained('users');
            $table->foreignId('instruction_id')->unique()->constrained('club_instructions');
            $table->bigInteger('amount_minor');
            $table->unsignedTinyInteger('due_day');
            $table->date('next_due_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
        Schema::create('club_capital_calls', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('book_id')->constrained('club_books');
            $table->foreignId('member_user_id')->constrained('users');
            $table->foreignId('instruction_id')->nullable()->constrained('club_instructions');
            $table->foreignId('plan_id')->nullable()->constrained('club_contribution_plans');
            $table->date('due_date');
            $table->bigInteger('amount_minor');
            $table->bigInteger('paid_minor')->default(0);
            $table->string('status', 24)->default('open');
            $table->timestamps();
            $table->unique(['plan_id', 'due_date']);
            $table->unique(['instruction_id', 'member_user_id']);
        });
        Schema::create('club_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('book_id')->constrained('club_books');
            $table->foreignId('account_id')->unique()->constrained('club_accounts');
            $table->string('name', 160);
            $table->string('asset_class', 32);
            $table->bigInteger('quantity_micro')->default(0);
            $table->bigInteger('cost_minor')->default(0);
            $table->bigInteger('carrying_value_minor')->default(0);
            $table->date('valuation_date')->nullable();
            $table->string('valuation_reference', 255)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
        Schema::create('club_asset_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained('club_books');
            $table->foreignId('asset_id')->constrained('club_assets');
            $table->foreignId('instruction_id')->unique()->constrained('club_instructions');
            $table->foreignId('journal_id')->constrained('club_journals');
            $table->string('type', 24);
            $table->date('business_date');
            $table->bigInteger('quantity_delta_micro');
            $table->bigInteger('cost_delta_minor');
            $table->bigInteger('carrying_delta_minor');
            $table->bigInteger('cash_flow_minor');
            $table->timestamp('created_at');
        });
        Schema::create('club_distributions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('book_id')->constrained('club_books');
            $table->foreignId('instruction_id')->unique()->constrained('club_instructions');
            $table->foreignId('journal_id')->constrained('club_journals');
            $table->date('record_date');
            $table->bigInteger('amount_minor');
            $table->string('allocation_basis', 32);
            $table->timestamps();
        });
        Schema::create('club_distribution_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('distribution_id')->constrained('club_distributions');
            $table->foreignId('member_id')->constrained('club_members');
            $table->bigInteger('amount_minor');
            $table->bigInteger('paid_minor')->default(0);
            $table->bigInteger('weight');
            $table->timestamps();
            $table->unique(['distribution_id', 'member_id']);
        });
        Schema::create('club_nav_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained('club_books');
            $table->foreignId('instruction_id')->unique()->constrained('club_instructions');
            $table->date('business_date');
            $table->bigInteger('net_assets_before_minor');
            $table->bigInteger('net_assets_after_minor');
            $table->bigInteger('units_before_micro');
            $table->bigInteger('units_after_micro');
            $table->bigInteger('external_flow_minor');
            $table->timestamp('created_at');
        });
        Schema::create('club_period_closures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained('club_books');
            $table->foreignId('instruction_id')->unique()->constrained('club_instructions');
            $table->date('closed_through');
            $table->json('trial_balance');
            $table->char('content_hash', 64);
            $table->timestamp('created_at');
            $table->unique(['book_id', 'closed_through']);
        });
        Schema::create('club_statements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('book_id')->constrained('club_books');
            $table->foreignId('member_user_id')->nullable()->constrained('users');
            $table->foreignId('issued_by')->constrained('users');
            $table->date('period_start');
            $table->date('period_end');
            $table->json('snapshot');
            $table->char('content_hash', 64);
            $table->timestamp('created_at');
        });

        $immutable = [
            'club_journals', 'club_journal_entries', 'club_treasury_posts',
            'club_member_movements', 'club_asset_movements', 'club_nav_snapshots',
            'club_period_closures', 'club_statements',
        ];
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE FUNCTION opfin_club_immutable() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Issued club accounting evidence is immutable; post a correcting entry' USING ERRCODE = '23514'; END; $$");
            foreach ($immutable as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION opfin_club_immutable()");
            }
            DB::statement('ALTER TABLE club_journals ADD CONSTRAINT club_journal_balanced CHECK (total_debits_minor > 0 AND total_debits_minor = total_credits_minor)');
            DB::statement("ALTER TABLE club_journal_entries ADD CONSTRAINT club_entry_positive CHECK (amount_minor > 0 AND direction IN ('debit', 'credit'))");
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach ($immutable as $table) {
                foreach (['UPDATE', 'DELETE'] as $operation) {
                    $trigger = $table.'_immutable_'.strtolower($operation);
                    DB::unprepared("CREATE TRIGGER {$trigger} BEFORE {$operation} ON {$table} BEGIN SELECT RAISE(ABORT, 'Issued club accounting evidence is immutable; post a correcting entry'); END");
                }
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'club_statements', 'club_period_closures', 'club_nav_snapshots',
            'club_distribution_allocations', 'club_distributions', 'club_asset_movements',
            'club_assets', 'club_capital_calls', 'club_contribution_plans',
            'club_member_movements', 'club_members', 'club_treasury_posts',
            'club_journal_entries', 'club_journals', 'club_instructions',
            'club_treasury_links', 'club_accounts', 'club_books',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS opfin_club_immutable()');
        }
    }
};
