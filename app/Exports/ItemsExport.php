<?php

namespace App\Exports;

use App\Models\Item;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ItemsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    /**
     * @return \Illuminate\Database\Query\Builder
     */
    public function query()
    {
        // Query sekarang hanya mengambil semua item beserta relasi vendor-nya.
        return Item::query()->with('vendor');
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        // Bagian ini tidak perlu diubah.
        return [
            'item_id',
            'item_no',
            'ahs',
            'deskripsi',
            'merek',
            'satuan',
            'hpp',
            'vendor_no',
            'provinsi',
            'kab',
            'tahun',
            'produk_deskripsi',
            'spesifikasi',
        ];
    }

    /**
     * @param mixed $item
     * @return array
     */
    public function map($item): array
    {
        // Bagian ini juga tidak perlu diubah.
        return [
            $item->item_id,
            $item->item_no,
            $item->ahs,
            $item->deskripsi,
            $item->merek,
            $item->satuan,
            $item->hpp,
            $item->vendor ? $item->vendor->vendor_no : null,
            $item->provinsi,
            $item->kab,
            $item->tahun,
            $item->spesifikasi,
            $item->produk_deskripsi,
        ];
    }
}
