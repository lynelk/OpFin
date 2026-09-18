<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_product_terms', function (Blueprint $table) {
            $table->string('umra_interest_approval_reference')->nullable()->after('guarantors_required');
            $table->string('umra_interest_approval_document_hash', 64)->nullable()->after('umra_interest_approval_reference');
            $table->timestamp('umra_interest_approved_at')->nullable()->after('umra_interest_approval_document_hash');
        });
    }

    public function down(): void
    {
        Schema::table('loan_product_terms', function (Blueprint $table) {
            $table->dropColumn([
                'umra_interest_approval_reference',
                'umra_interest_approval_document_hash',
                'umra_interest_approved_at',
            ]);
        });
    }
};
