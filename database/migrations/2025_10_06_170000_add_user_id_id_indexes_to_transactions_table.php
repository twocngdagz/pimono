<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['sender_id', 'id'], 'transactions_sender_id_id_index');
            $table->index(['receiver_id', 'id'], 'transactions_receiver_id_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_sender_id_id_index');
            $table->dropIndex('transactions_receiver_id_id_index');
        });
    }
};
