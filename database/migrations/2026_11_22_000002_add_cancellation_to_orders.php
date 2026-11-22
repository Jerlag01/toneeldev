<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('cancel_reason', 30)->nullable()->after('closed_by');
            $table->string('cancel_note', 500)->nullable()->after('cancel_reason');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_note');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancel_reason', 'cancel_note', 'cancelled_at', 'refunded_at']);
        });
    }
};
