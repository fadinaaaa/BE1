<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Vendor; // --- PERUBAHAN: Import model Vendor ---
use App\Models\Dokumen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ItemsExport;
use App\Imports\ItemsImport;
use Dom\Document;
use Maatwebsite\Excel\Validators\ValidationException;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use App\Models\ItemFile;
use Symfony\Component\HttpFoundation\Response;

class ItemController extends Controller
{
    /**
     * Ambil semua data Item dengan filter.
     */
    public function index(Request $request)
    {
        $request->validate([
            'provinsi' => 'nullable|string|max:255',
            'kab'      => 'nullable|string|max:255',
            'tahun'    => 'nullable|integer',
            'item_no'  => 'nullable|string',
        ]);

        $query = Item::with([
            'vendor',
            'gambar',   // morphMany file_type = gambar
            'dokumen',  // morphMany file_type = dokumen
        ]);

        $query->when($request->filled('provinsi'), function ($q) use ($request) {
            $q->where('provinsi', $request->provinsi);
        });

        $query->when($request->filled('kab'), function ($q) use ($request) {
            $q->where('kab', $request->kab);
        });

        $query->when($request->filled('tahun'), function ($q) use ($request) {
            $q->where('tahun', $request->tahun);
        });

        $query->when($request->filled('item_no'), function ($q) use ($request) {
            $q->where('item_no', 'like', '%' . $request->item_no . '%');
        });

        return response()->json([
            'success' => true,
            'data'    => $query->get(),
        ]);
    }
    public function store(Request $request)
    {
        try {
            // VALIDASI
            $validated = $request->validate([
                'ahs'              => 'required|string',
                'deskripsi'        => 'required|string',
                'merek'            => 'nullable|string',
                'satuan'           => 'required|string',

                'hpp'              => 'required|numeric',

                'vendor_no'        => 'nullable|string|exists:vendors,vendor_no',

                'provinsi'         => 'nullable|string',
                'kab'              => 'nullable|string',
                'tahun'            => 'required|integer',

                'produk_foto'      => 'nullable|array',
                'produk_foto.*'    => 'file|mimes:jpg,jpeg,png|max:2048',

                'produk_dokumen'   => 'nullable|array',
                'produk_dokumen.*' => 'file|mimes:pdf,doc,docx|max:5120',

                'produk_deskripsi' => 'nullable|string',
                'spesifikasi'      => 'nullable|string',
            ]);

            // Konversi vendor_no -> vendor_id
            $vendorId = null;
            if ($request->filled('vendor_no')) {
                $vendor = Vendor::where('vendor_no', $validated['vendor_no'])->first();
                if ($vendor) {
                    $vendorId = $vendor->vendor_id;
                }
            }

            // Data Item
            $dataToCreate = $validated;
            unset($dataToCreate['item_id']);
            $dataToCreate['vendor_id'] = $vendorId;
            unset($dataToCreate['vendor_no']);

            // Nomor item otomatis
            $dataToCreate['item_no'] = $this->generateNewItemNo();

            // SIMPAN ITEM
            $item = Item::create($dataToCreate);

            // SIMPAN DOKUMEN
            if ($request->hasFile('produk_dokumen')) {
                foreach ($request->file('produk_dokumen') as $doc) {

                    $path = $doc->store('uploads/dokumen', 'public');

                    ItemFile::create([
                        'fileable_id'   => $item->item_id,   // FIXED !!!
                        'fileable_type' => Item::class,
                        'file_path'     => $path,
                        'file_type'     => 'dokumen'
                    ]);
                }
            }

            // SIMPAN FOTO
            if ($request->hasFile('produk_foto')) {
                foreach ($request->file('produk_foto') as $foto) {

                    $path = $foto->store('uploads/gambar', 'public');

                    ItemFile::create([
                        'fileable_id'   => $item->item_id,   // FIXED !!!
                        'fileable_type' => Item::class,
                        'file_path'     => $path,
                        'file_type'     => 'gambar'            // FIXED !!!
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Item berhasil ditambahkan',
                'data'    => $item->load('files'),
            ], 201);
        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid.',
                'errors'  => $e->errors()
            ], 422);
        } catch (Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $item = $this->findItem($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item tidak ditemukan',
            ], 404);
        }

        // ===== VALIDASI =====
        $validated = $request->validate([
            'item_no'          => ['sometimes', 'string', Rule::unique('items', 'item_no')->ignore($item->item_id, 'item_id')],
            'ahs'              => 'sometimes|string',
            'deskripsi'        => 'sometimes|string',
            'merek'            => 'nullable|string',
            'satuan'           => 'sometimes|string',
            'hpp'              => 'sometimes|numeric',

            // vendor
            'vendor_no'        => 'nullable|string|exists:vendors,vendor_no',
            'vendor_id'        => 'nullable|integer|exists:vendors,vendor_id',

            'provinsi'         => 'nullable|string',
            'kab'              => 'nullable|string',
            'tahun'            => 'sometimes|integer',

            // Foto
            'produk_foto'      => 'nullable|array',
            'produk_foto.*'    => 'file|mimes:jpg,jpeg,png|max:2048',

            // Dokumen
            'produk_dokumen'   => 'nullable|array',
            'produk_dokumen.*' => 'file|mimes:pdf,doc,docx|max:5120',

            // Hapus file lama
            'hapus_file'       => 'nullable|array',
            'hapus_file.*'     => 'integer|exists:item_files,file_id',

            'spesifikasi'      => 'nullable|string',
        ]);

        $dataToUpdate = $validated;


        // ==========================
        // KONVERSI vendor_no → vendor_id
        // ==========================
        if ($request->filled('vendor_no')) {
            $vendor = Vendor::where('vendor_no', $request->vendor_no)->first();
            if ($vendor) {
                $dataToUpdate['vendor_id'] = $vendor->vendor_id;
            }
        }

        // Hilangkan vendor_no supaya tidak ikut update
        unset($dataToUpdate['vendor_no']);


        // ==========================
        // HAPUS FILE LAMA
        // ==========================
        if ($request->filled('hapus_file')) {
            foreach ($request->hapus_file as $fileId) {

                $file = ItemFile::find($fileId);

                if ($file) {
                    Storage::disk('public')->delete($file->file_path);
                    $file->delete();
                }
            }
        }


        // ==========================
        // UPLOAD FOTO BARU
        // ==========================
        if ($request->hasFile('produk_foto')) {

            foreach ($request->file('produk_foto') as $foto) {

                $path = $foto->store('uploads/gambar', 'public');

                ItemFile::create([
                    'fileable_id'   => $item->item_id,   // FIXED !!!
                    'fileable_type' => Item::class,
                    'file_path'     => $path,
                    'file_type'     => 'gambar'
                ]);
            }
        }


        // ==========================
        // UPLOAD DOKUMEN BARU
        // ==========================
        if ($request->hasFile('produk_dokumen')) {

            foreach ($request->file('produk_dokumen') as $doc) {

                $path = $doc->store('uploads/dokumen', 'public');

                ItemFile::create([
                    'fileable_id'   => $item->item_id,   // FIXED !!!
                    'fileable_type' => Item::class,
                    'file_path'     => $path,
                    'file_type'     => 'dokumen'
                ]);
            }
        }


        // Hapus key agar tidak mengganggu update
        unset(
            $dataToUpdate['produk_foto'],
            $dataToUpdate['produk_dokumen'],
            $dataToUpdate['hapus_file']
        );


        // ==========================
        // UPDATE DATA ITEM
        // ==========================
        $item->update($dataToUpdate);


        return response()->json([
            'success' => true,
            'message' => 'Item berhasil diupdate',
            'data'    => $item->load('files'),
        ]);
    }

    /**
     * Hapus Item beserta semua dokumen dan foto.
     */
    public function destroy($id)
    {
        $item = $this->findItem($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item tidak ditemukan',
            ], 404);
        }

        // =============================
        // HAPUS SEMUA FILE (foto + dokumen)
        // =============================
        foreach ($item->files as $file) {

            // Hapus file fisik
            Storage::disk('public')->delete($file->file_path);

            // Hapus record dari DB
            $file->delete();
        }

        // =============================
        // HAPUS ITEM
        // =============================
        $item->delete();

        Log::info("Item ID $id berhasil dihapus beserta seluruh file-nya.");

        return response()->json([
            'success' => true,
            'message' => 'Item berhasil dihapus',
        ]);
    }

    /**
     * Ekspor semua data Item ke Excel.
     */
    public function export()
    {
        // ... (Tidak ada perubahan di sini, pastikan ItemsExport Anda mengekspor $item->vendor->vendor_no)
        Log::info('Memulai proses ekspor semua item.');
        return Excel::download(new ItemsExport, 'item.xlsx');
    }

    /**
     * Impor data Item dari Excel.
     */
    public function import(Request $request)
    {
        // 1. Validasi File
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv']);

        DB::beginTransaction();

        try {
            // 2. Baca file Excel ke dalam Collection
            $collection = Excel::toCollection(new ItemsImport, $request->file('file'));
            $rows = $collection[0]; // Ambil sheet pertama

            // -------------------------------------------------------
            // OPTIMALISASI 1: MAPPING VENDOR
            // -------------------------------------------------------
            $vendorMap = [];

            // Cek apakah Excel menggunakan 'vendor_no'
            // Pluck hanya akan bekerja jika key 'vendor_no' ada di collection
            $hasVendorNo = $rows->first() && isset($rows->first()['vendor_no']);

            if ($hasVendorNo) {
                $vendorNos = $rows->pluck('vendor_no')->filter()->unique()->toArray();
                $vendors = Vendor::whereIn('vendor_no', $vendorNos)->get();
                // Buat Peta: 'V-001' => 15 (id)
                $vendorMap = $vendors->pluck('vendor_id', 'vendor_no');
            }

            // -------------------------------------------------------
            // OPTIMALISASI 2: LOGIKA NOMOR ITEM
            // -------------------------------------------------------
            $lastItem = DB::table('items')->orderBy('item_id', 'desc')->first();
            $lastNumber = 0;

            if ($lastItem && !empty($lastItem->item_no)) {
                $angkaSaja = preg_replace('/[^0-9]/', '', $lastItem->item_no);
                $lastNumber = intval($angkaSaja);
            }

            // -------------------------------------------------------
            // LOOPING DATA & SIMPAN
            // -------------------------------------------------------
            foreach ($rows as $row) {
                $vendorId = null;

                // --- PERBAIKAN LOGIKA VENDOR DI SINI ---

                // Prioritas 1: Gunakan mapping vendor_no jika ada di Excel dan Map
                if (isset($row['vendor_no']) && isset($vendorMap[$row['vendor_no']])) {
                    $vendorId = $vendorMap[$row['vendor_no']];
                }
                // Prioritas 2: Gunakan vendor_id langsung dari Excel (sesuai file Anda)
                elseif (isset($row['vendor_id'])) {
                    $vendorId = $row['vendor_id'];
                }

                // --- AKHIR PERBAIKAN ---

                // Generate Nomor Baru
                $lastNumber++;
                $newItemNo = 'M_' . str_pad($lastNumber, 3, '0', STR_PAD_LEFT);

                // Parsing Foto
                $fotoArray = null;
                if (!empty($row['produk_foto'])) {
                    $fotoArray = array_map('trim', explode(',', $row['produk_foto']));
                }

                // Parsing Dokumen
                $dokumenArray = null;
                if (!empty($row['produk_dokumen'])) {
                    $dokumenArray = array_map('trim', explode(',', $row['produk_dokumen']));
                }

                // Simpan ke Database
                Item::create([
                    'item_no'          => $newItemNo,
                    'ahs'              => $row['ahs'],
                    'deskripsi'        => $row['deskripsi'],
                    'merek'            => $row['merek'] ?? null,
                    'satuan'           => $row['satuan'],
                    'hpp'              => $row['hpp'],
                    'vendor_id'        => $vendorId, // Nilai ini sekarang sudah terisi
                    'provinsi'         => $row['provinsi'] ?? null,
                    'kab'              => $row['kab'] ?? null,
                    'tahun'            => $row['tahun'],
                    'spesifikasi'      => $row['spesifikasi'] ?? null,
                    'produk_deskripsi' => $row['produk_deskripsi'] ?? null,
                    'produk_foto'      => $fotoArray,
                    'produk_dokumen'   => $dokumenArray,
                ]);
            }

            DB::commit();
            Log::info('File item berhasil diimpor.');

            return response()->json([
                'success' => true,
                'message' => 'Data item berhasil diimpor',
            ], 200);
        } catch (ValidationException $e) {
            DB::rollBack();
            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $failure) {
                $errors[] = [
                    'row'       => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors'    => $failure->errors(),
                    'values'    => $failure->values(),
                ];
            }
            Log::error('Gagal impor item karena validasi: ', $errors);
            return response()->json([
                'success' => false,
                'message' => 'Terdapat kesalahan validasi pada file.',
                'errors'  => $errors,
            ], 422);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Gagal impor item: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat impor.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        // Header sesuai dengan perubahan
        $headers = [
            'ahs',
            'deskripsi',
            'merek',
            'satuan',
            'hpp',
            // --- PERUBAHAN: Ubah header template ---
            'vendor_no',
            // --- AKHIR PERUBAHAN ---
            'provinsi',
            'kab',
            'tahun',
            'produk_deskripsi',
            'spesifikasi',
        ];

        // Nama file
        $fileName = 'template_import_items.xlsx';

        // ... (Logika pembuatan file Excel tetap sama)
        $tempFile = tempnam(sys_get_temp_dir(), 'template_items_');
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $colIndex = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($colIndex, 1, $header);
            $colIndex++;
        }

        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }


    // ===================================================================
    // PRIVATE HELPER METHODS (Tidak ada perubahan di sini)
    // ===================================================================

    /**
     * Mencari item berdasarkan ID primary key atau item_no custom.
     */
    private function findItem($id)
    {
        $item = Item::with('vendor')->find($id);
        if (!$item) {
            $item = Item::with('vendor')->where('item_no', $id)->first();
        }
        return $item;
    }

    /**
     * Menghasilkan item_no baru secara berurutan.
     */
    private function generateNewItemNo()
    {
        $lastItemNo = Item::whereNotNull('item_no')
            ->orderByRaw("CAST(SUBSTRING(item_no, 3) AS UNSIGNED) DESC")
            ->value('item_no');

        $nextNumber = 1;

        if ($lastItemNo) {
            $nextNumber = (int) substr($lastItemNo, 2) + 1;
        }

        return 'M_' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
    /**
     * Mengelola upload file dan mengembalikan path-nya.
     */
    private function uploadFile(Request $request, string $fieldName, string $directory)
    {
        if ($request->hasFile($fieldName) && $request->file($fieldName)->isValid()) {
            $file = $request->file($fieldName);
            $originalFileName = $file->getClientOriginalName();
            Log::info("File '$originalFileName' diupload ke direktori '$directory'.");
            return $file->storeAs($directory, $originalFileName, 'public');
        }
        return null;
    }
    public function previewFoto($id)
    {
        $file = ItemFile::findOrFail($id);

        // Pastikan hanya file foto
        if ($file->file_type !== 'foto') {
            abort(404);
        }

        $path = storage_path('app/public/' . $file->file_path);

        if (!file_exists($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Disposition' => 'inline'
        ]);
    }

    public function previewDokumen($id)
    {
        $file = ItemFile::findOrFail($id);

        if ($file->file_type !== 'dokumen') {
            abort(404);
        }

        $path = storage_path('app/public/' . $file->file_path);

        if (!file_exists($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Disposition' => 'inline'
        ]);
    }
}
