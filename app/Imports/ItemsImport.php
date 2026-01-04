<?php

namespace App\Imports;

use App\Models\Item;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ItemsImport implements ToModel, WithHeadingRow, WithValidation, WithBatchInserts, WithChunkReading
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        // PERBAIKAN: item_no dihapus dari sini.
        // Controller akan menangani pembuatan item_no sebelum menyimpan.
        return new Item([
            'ahs'              => $row['ahs'],
            'deskripsi'        => $row['deskripsi'],
            'merek'            => $row['merek'] ?? null,
            'satuan'           => $row['satuan'],
            'hpp'              => $row['hpp'],
            'vendor_id'        => $row['vendor_id'] ?? null,
            'provinsi'         => $row['provinsi'] ?? null, // Diubahd
            'kab'              => $row['kab'] ?? null,
            'tahun'            => $row['tahun'],
            'spesifikasi'      => $row['spesifikasi'] ?? null,
            'produk_deskripsi' => $row['produk_deskripsi'] ?? null,
        ]);
    }

    /**
    * @return array
    */
    public function rules(): array
    {
        // Aturan validasi tidak berubah, sudah benar.
        return [
            'ahs'              => 'required|string',
            'deskripsi'        => 'required|string',
            'merek'            => 'nullable|string',
            'satuan'           => 'required|string',
            'hpp'              => 'required|numeric',
            'vendor_id'        => 'nullable|integer|exists:vendors,vendor_id',
            'provinsi'         => 'nullable|string',
            'kab'              => 'nullable|string',
            'tahun'            => 'required|integer',
            'spesifikasi'      => 'nullable|string',
            'produk_deskripsi' => 'nullable|string',
        ];
    }

    /**
     * Tentukan berapa banyak baris yang akan di-insert ke database dalam satu query.
     */
    public function batchSize(): int
    {
        return 100; // Optimal untuk kinerja
    }

    /**
     * Tentukan berapa banyak baris yang akan dibaca dari file Excel ke memori sekaligus.
     */
    public function chunkSize(): int
    {
        return 100; // Optimal untuk penggunaan memori
    }
}