<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** One row per "please approve this payment on your phone" request sent to a payment provider. */
    public function up(): void
    {
        Schema::create('mobile_money_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30);                 // flutterwave | fake
            $table->string('tx_ref', 64)->unique();         // our reference, sent to the provider
            $table->string('network', 10);                  // MTN | AIRTEL
            $table->string('phone', 20);                    // normalised 256XXXXXXXXX
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('UGX');
            $table->string('status', 20)->default('pending')->index(); // pending | successful | failed | needs_review
            $table->string('provider_id', 64)->nullable();  // provider's transaction id
            $table->string('provider_ref', 100)->nullable(); // provider's reference (e.g. flw_ref)
            $table->string('redirect_url', 500)->nullable(); // test-mode authorisation page, if the provider returns one
            $table->string('message', 255)->nullable();      // last status / failure message
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_money_requests');
    }
};
