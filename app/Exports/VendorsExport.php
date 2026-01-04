<?php

namespace App\Exports;

use App\Models\Vendor;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VendorsExport implements FromCollection, WithHeadings
{
    protected $vendors;

    // 1. Constructor untuk menerima data dari Controller
    public function __construct($vendors)
    {
        $this->vendors = $vendors;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    // 2. Method ini mengambil data yang akan diexport
    public function collection()
    {
        // Kita hanya akan mengambil kolom yang diperlukan saja agar rapi
        return $this->vendors->map(function ($vendor) {
            return [
                'vendor_id'    => $vendor->vendor_id,
                'vendor_no'    => $vendor->vendor_no, // Ditambahkan
                'vendor_name'  => $vendor->vendor_name,
                'contact_name' => $vendor->contact_name,
                'contact_no'   => $vendor->contact_no,
                'email'        => $vendor->email,
                'provinsi'     => $vendor->provinsi,
                'kab'          => $vendor->kab,
                'tahun'        => $vendor->tahun,
            ];
        });
    }

    /**
     * @return array
     */
    // 3. Method ini untuk membuat judul kolom (header) di file Excel
    public function headings(): array
    {
        return [
            'ID',
            'Vendor No', // Ditambahkan
            'Vendor Name',
            'Contact Name',
            'Contact No',
            'Email',
            'Provinsi', // Diubah
            'Kab',
            'Tahun',
        ];
    }
}
