<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('item_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fileable_id');
            $table->string('fileable_type'); // App\Models\Item atau App\Models\Ahs
            $table->string('file_path');
            $table->string('file_type')->nullable(); // foto / dokumen
            $table->string('original_name')->nullable();
            $table->timestamps();

            $table->index(['fileable_id', 'fileable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_files_tables');
    }
};