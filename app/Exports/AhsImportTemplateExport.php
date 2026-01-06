<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AhsImportTemplateExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    public function collection()
    {
        return collect([]);
    }

    public function headings(): array
    {
        return [
            // === IDENTITAS / GROUPING AHS ===
            'group_key',

            // === HEADER AHS ===
            'ahs_deskripsi',
            'ahs_satuan',
            'ahs_provinsi',
            'ahs_kab',
            'ahs_tahun',

            // === ATRIBUT TAMBAHAN AHS ===
            'merek',
            'vendor_no',
            'produk_deskripsi',
            'spesifikasi',

            // === DETAIL ITEM (BERULANG) ===
            'item_no',
            'volume',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('1:1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF4F81BD'],
            ],
        ]);

        return [];
    }
}
