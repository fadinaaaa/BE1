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
        Schema::create('items', function (Blueprint $table) {
            $table->id('item_id'); // ID ITEM
            $table->string('item_no')->unique(); // Nomor Item (ditambahkan)
            $table->string('ahs'); // AHS
            $table->string('deskripsi'); // Deskripsi
            $table->string('merek')->nullable(); // Merek
            $table->string('satuan'); // Satuan
            $table->decimal('hpp', 15, 2); // HPP (Harga Pokok Produksi)
            $table->unsignedBigInteger('vendor_id')->nullable(); // Relasi ke vendor
            $table->string('provinsi'); // Diubah dari wilayah
            $table->string('kab');
            $table->year('tahun'); // Tahun

            // Produk Info (foto bisa lebih dari 1 → json)
            $table->json('produk_foto')->nullable();
            $table->text('produk_deskripsi')->nullable();
            $table->json('produk_dokumen')->nullable(); // untuk file dokumen (SNI dll)
            // Spesifikasi
            $table->string('spesifikasi')->nullable(); // contoh: "SNI No. 1234/2025"

            $table->timestamps();

            // Foreign Key
            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
