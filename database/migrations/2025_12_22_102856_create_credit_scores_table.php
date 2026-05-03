<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_scores', function (Blueprint $table) {
            $table->id();
            // CRB Scoring fields
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('score')->nullable();
            $table->string('band')->nullable();
            $table->string('rating')->nullable();
            $table->decimal('probability_of_default_percent', 5, 2)->nullable();
            $table->string('likelihood_to_default')->nullable();

            // Full raw response
            $table->json('data');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_scores');
    }
};
