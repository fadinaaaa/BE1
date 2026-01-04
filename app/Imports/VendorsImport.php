<?php

namespace App\Imports;

use App\Models\Vendor;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class VendorsImport implements ToModel, WithHeadingRow, WithValidation
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Sesuaikan key ini agar cocok dengan header di file Excel
        return new Vendor([
            'vendor_no'    => $row['vendor_no'], // Ditambahkan
            'vendor_name'  => $row['vendor_name'],
            'contact_name' => $row['contact_name'],
            'contact_no'   => (string) $row['contact_no'],
            'email'        => $row['email'],
            'provinsi'     => $row['provinsi'],
            'kab'          => $row['kab'],
            'tahun'        => $row['tahun'],
        ]);
    }

    public function rules(): array
    {
        // Sesuaikan juga key validasi ini
        return [
            '*.vendor_no' => 'required|string|unique:vendors,vendor_no', // Ditambahkan
            '*.vendor_name'  => 'required|string|max:255',
            '*.contact_name'  => 'required|string|max:255',
            '*.contact_no' => 'required|max:20',
            '*.email'        => 'required|email|unique:vendors,email',
            '*.provinsi'     => 'required|string',
            '*.kab'          => 'required|string',
            '*.tahun'        => 'required|integer',
        ];
    }
}
