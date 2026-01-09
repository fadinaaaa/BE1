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
        Schema::table('ahs_items', function (Blueprint $table) {
            $table->string('kategori')->nullable()->after('item_id');

            $table->dropForeign(['item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ahs_items', function (Blueprint $table) {

            $table->foreign('item_id')
                ->references('item_id')
                ->on('items')
                ->onDelete('set null');

            $table->dropColumn('kategori');
        });
    }
};
