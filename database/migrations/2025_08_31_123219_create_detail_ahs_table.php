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
        Schema::create('ahs_items', function (Blueprint $table) {
            $table->id('ahs_item_id'); // Primary key detail
            $table->unsignedBigInteger('ahs_id'); // Relasi ke AHS
            $table->unsignedBigInteger('item_id') -> nullable(); // ID item (opsional relasi ke tabel items)
            $table->string('uraian'); // Uraian item
            $table->string('satuan'); // Satuan
            $table->decimal('volume', 15, 2); // Volume
            $table->decimal('hpp', 15, 2); // Harga Pokok Produksi
            $table->decimal('jumlah', 15, 2); // Jumlah total
            $table->timestamps();

            // Foreign Key ke tabel AHS
            $table->foreign('ahs_id')->references('ahs_id')->on('ahs')->onDelete('cascade');

            // Foreign Key opsional ke tabel Items
            $table->foreign('item_id')->references('item_id')->on('items')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ahs_items');
    }
};
