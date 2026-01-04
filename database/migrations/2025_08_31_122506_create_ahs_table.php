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
        Schema::create('ahs', function (Blueprint $table) {
            $table->id('ahs_id');
            $table->string('ahs');
            $table->string('deskripsi');
            $table->string('merek')->nullable();
            $table->string('satuan');
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('provinsi');
            $table->string('kab');
            $table->year('tahun');
            $table->decimal('harga_pokok_total', 15, 2);
            $table->string('produk_foto')->nullable();
            $table->text('produk_deskripsi')->nullable();
            $table->string('produk_dokumen')->nullable();
            $table->string('spesifikasi')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')
                ->references('vendor_id')
                ->on('vendors')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ahs');
    }
};
