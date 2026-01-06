<?php

namespace App\Imports;

use App\Models\Ahs;
use App\Models\AhsItem;
use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Exception;

class AhsImport implements ToCollection, WithHeadingRow
{
    /**
     * Method ini akan dipanggil oleh Excel::import
     * Kita bungkus semua proses dalam satu transaksi DB di controller
     */
    public function collection(Collection $rows)
    {
        // 1. Kelompokkan baris berdasarkan 'group_key'
        $grouped = $rows->groupBy('group_key');

        if ($grouped->isEmpty()) {
            throw new Exception('File kosong atau format salah.');
        }

        // 2. Iterasi setiap grup (setiap grup adalah 1 AHS baru)
        foreach ($grouped as $groupKey => $items) {
            if (empty($groupKey)) {
                throw new Exception('Ditemukan data tanpa group_key. Setiap AHS harus memiliki group_key yang unik.');
            }

            $firstItem = $items->first();

            // 3. Validasi data header (diambil dari baris pertama grup)
            $ahsData = $this->validateAhsHeader($firstItem->toArray());

            // 4. Buat Header AHS
            $add_ahs = Ahs::create([
                'ahs'       => 'no ahs sementara-' . $groupKey, // 'ahs' akan di-update nanti
                'deskripsi' => $ahsData['ahs_deskripsi'],
                'satuan'    => $ahsData['ahs_satuan'],
                'provinsi'  => $ahsData['ahs_provinsi'], // Diubah dari wilayah
                'kab'       => $ahsData['ahs_kab'],      // Diubah dari wilayah
                'tahun'     => $ahsData['ahs_tahun'],
                'harga_pokok_total' => 0
            ]);

            $totalHppAhs = 0;

            // 5. Iterasi setiap item dalam grup untuk membuat AhsItem
            foreach ($items as $itemRow) {
                // Kirim provinsi dan kab ke validator item
                $itemData = $this->validateAhsItem(
                    $itemRow->toArray(),
                    $ahsData['ahs_provinsi'],
                    $ahsData['ahs_kab']
                );

                // Ambil data item dari DB (Source of Truth)
                $item = $itemData['item_model']; // Didapat dari validateAhsItem
                $volume = $itemData['item_volume'];
                $hpp = $item->hpp;
                $jumlah = $volume * $hpp;

                AhsItem::create([
                    'ahs_id'  => $add_ahs->ahs_id,
                    'item_id' => $item->item_id,
                    'uraian'  => $item->deskripsi,
                    'satuan'  => $item->satuan,
                    'volume'  => $volume,
                    'hpp'     => $hpp,
                    'jumlah'  => $jumlah,
                ]);

                $totalHppAhs += $jumlah;
            }

            // 6. Update total HPP di header AHS
            $add_ahs->update(['harga_pokok_total' => $totalHppAhs]);

            // 7. Buat AHS ini sebagai Item baru di tabel Item
            $newAhsNo = $this->generateNoAhs(); // Panggil fungsi generator

            $add_ahs_to_item = Item::create([
                'item_no'   => $newAhsNo,
                'ahs'       => 'AHS',
                'deskripsi' => $add_ahs->deskripsi,
                'satuan'    => $add_ahs->satuan,
                'hpp'       => $add_ahs->harga_pokok_total,
                'provinsi'  => $add_ahs->provinsi, // Diubah dari wilayah
                'kab'       => $add_ahs->kab,      // Diubah dari wilayah
                'merek'     => '',
                'tahun'     => $add_ahs->tahun
            ]);

            // 8. Update nomor AHS final di header AHS
            $add_ahs->update(['ahs' => $newAhsNo]);
        }
    }

    /**
     * Validasi data header AHS dari Excel
     */
    private function validateAhsHeader(array $data)
    {
        $validator = Validator::make($data, [
            'ahs_deskripsi' => 'required|string',
            'ahs_satuan'    => 'required|string',
            'ahs_provinsi'  => 'required|string', // Diubah dari wilayah
            'ahs_kab'       => 'required|string', // Diubah dari wilayah
            'ahs_tahun'     => 'required|integer',
        ], [
            'ahs_deskripsi.required' => 'ahs_deskripsi wajib diisi',
            'ahs_provinsi.required'  => 'ahs_provinsi wajib diisi', // Diubah
            'ahs_kab.required'       => 'ahs_kab wajib diisi',      // Diubah
        ]);

        if ($validator->fails()) {
            throw new Exception('Validasi Header AHS Gagal: ' . $validator->errors()->first());
        }

        return $validator->validated();
    }

    /**
     * Validasi setiap baris item AHS dari Excel
     */
    private function validateAhsItem(array $data, string $provinsi, string $kab) // Diubah
    {
        $validator = Validator::make($data, [
            'item_no'     => 'required|string',
            'item_volume' => 'required|numeric|min:0.001',
        ]);

        if ($validator->fails()) {
            throw new Exception('Validasi Item AHS Gagal: ' . $validator->errors()->first());
        }

        $validated = $validator->validated();

        // Cek apakah item_no ada di DB untuk provinsi dan kab tsb
        $item = Item::where('item_no', $validated['item_no'])
            ->where('provinsi', $provinsi) // Diubah
            ->where('kab', $kab)           // Diubah
            ->first();

        if (!$item) {
            throw new Exception("Item dengan kode '{$validated['item_no']}' tidak ditemukan di provinsi '{$provinsi}', kab '{$kab}'."); // Diubah
        }

        // Kembalikan data tervalidasi + model Item yg ditemukan
        return [
            'item_no'     => $validated['item_no'],
            'item_volume' => $validated['item_volume'],
            'item_model'  => $item,
        ];
    }

    /**
     * Salin fungsi generateNoAhs dari controller Anda
     * agar bisa diakses di dalam class Import ini.
     */
    public function generateNoAhs()
    {
        $prefix = 'AHS';

        $last = DB::table('items')
            ->where('item_no', 'like', "$prefix%")
            ->orderBy('item_id', 'desc')
            ->value('item_no');

        if ($last) {
            preg_match('/(\d+)$/', $last, $matches);
            $lastNumber = isset($matches[1]) ? (int) $matches[1] : 0;
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = '1';
        }

        return $prefix . $nextNumber;
    }
}