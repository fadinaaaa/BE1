<?php

namespace App\Http\Controllers;

use App\Models\Ahs;
use App\Models\AhsItem;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AhsExport;
use App\Exports\AhsImportTemplateExport;
use App\Imports\AhsImport;
use App\Models\Vendor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage; // Tambahkan ini untuk delete file
use App\Models\ItemFile;

class AhsWithItemsController extends Controller
{
    public function get_data_ahs()
    {
        try {
            $data = Ahs::with([
                'items',  // relasi AhsItem → Item
                'files',       // foto & dokumen (polymorphic)
                'vendor'
            ])
                ->orderBy('ahs_id', 'desc')
                ->get()
                ->map(function ($ahs) {

                    // Ambil item utama yang mewakili AHS (item_no = ahs)
                    $itemAhs = Item::where('item_no', $ahs->ahs)->first();

                    return [
                        'ahs_id'     => $ahs->ahs_id,
                        'ahs_no'     => $ahs->ahs,
                        'deskripsi'  => $ahs->deskripsi,
                        'satuan'     => $ahs->satuan,
                        'provinsi'   => $ahs->provinsi,
                        'kab'        => $ahs->kab,
                        'tahun'      => $ahs->tahun,
                        'harga_pokok_total' => $ahs->harga_pokok_total,
                        'merek' => $ahs->merek,
                        'produk_deskripsi' => $ahs->produk_deskripsi,
                        'spesifikasi' => $ahs->spesifikasi,
                        'vendor' => $ahs->vendor,
                        // === ITEM HEADER AHS ===
                        'item_ahs' => $itemAhs ? [
                            'item_id'     => $itemAhs->item_id,
                            'item_no'     => $itemAhs->item_no,
                            'merek'       => $itemAhs->merek,
                            'vendor_id'   => $itemAhs->vendor_id,
                            'produk_deskripsi' => $itemAhs->produk_deskripsi,
                            'spesifikasi'       => $itemAhs->spesifikasi,
                        ] : null,

                        // === FOTO ===
                        'gambar' => $ahs->files
                            ->where('file_type', 'gambar')
                            ->map(fn($f) => asset('storage/' . $f->file_path))
                            ->values(),

                        // === DOKUMEN ===
                        'dokumen' => $ahs->files
                            ->where('file_type', 'dokumen')
                            ->map(fn($f) => asset('storage/' . $f->file_path))
                            ->values(),

                        // === DETAIL ITEMS ===
                        'items' => $ahs->items->map(function ($it) {
                            return [
                                'item_id' => $it->item_id,
                                'item_no' => $it->kategori == 'item' ? $it->item?->item_no : $it->item_form_ahs?->ahs,
                                'uraian'  => $it->uraian,
                                'satuan'  => $it->satuan,
                                'volume'  => (float)    $it->volume,
                                'hpp'     => $it->hpp,
                                'jumlah'  => $it->jumlah,
                            ];
                        }),

                        'created_at' => $ahs->created_at,
                        'updated_at' => $ahs->updated_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Data AHS berhasil diambil',
                'data'    => $data
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data AHS',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function generateNoAhs()
    {
        return DB::transaction(function () {

            $lastNumber = Ahs::selectRaw(
                "MAX(CAST(REGEXP_REPLACE(ahs, '[^0-9]', '') AS UNSIGNED)) as max_no"
            )
                ->lockForUpdate()
                ->value('max_no');

            $nextNumber = ($lastNumber ?? 0) + 1;

            return 'AHS' . $nextNumber;
        });
    }

    public function getOptionItem(Request $request)
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'provinsi' => 'required|string',
                'kab'      => 'required|string',
            ], [
                'provinsi.required' => 'Masukkan provinsi dahulu',
                'kab.required' => 'Masukkan kab dahulu',
            ]);

            $data_option = Item::where('provinsi', $request->provinsi)
                ->where('kab', $request->kab)
                ->select('item_id', 'item_no', 'deskripsi', 'satuan', 'hpp')
                ->get();

            if ($data_option->isEmpty()) {
                throw new \Exception('Data item untuk ' . $request->provinsi . ', ' . $request->kab . ' tidak tersedia');
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data option item',
                'data'    => $data_option
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->errors(), 'data' => []], 422);
        }
    }

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

    public function addDataAhs(Request $request)
    {
        DB::beginTransaction();

        // track uploaded file paths to cleanup on rollback jika error
        $uploadedPaths = [];

        try {
            $request->validate([
                'deskripsi' => 'required|string',
                'merek'     => 'nullable|string',
                'satuan'    => 'required|string',
                'provinsi'  => 'required|string',
                'kab'       => 'required|string',
                'tahun'     => 'required|string',
                'vendor_id' => 'nullable|string|exists:vendors,vendor_id',

                // ubah ke array agar mendukung multiple file; tetap kompatibel jika user hanya kirim 1 file
                'produk_foto'        => 'nullable|array',
                'produk_foto.*'      => 'file|mimes:jpg,jpeg,png|max:2048',

                'produk_dokumen'     => 'nullable|array',
                'produk_dokumen.*'   => 'file|mimes:pdf,doc,docx,xls,xlsx|max:5120',

                'produk_deskripsi' => 'nullable|string',
                'spesifikasi' => 'nullable|string',

                'items'     => 'required|array',
                'items.*.item_no' => [
                    'required',
                    'string',
                    function ($attr, $value, $fail) {
                        $isItem = Item::where('item_no', $value)->exists();
                        $isAhs  = Ahs::where('ahs', $value)->exists();

                        if (!$isItem && !$isAhs) {
                            $fail("Item atau AHS ($value) tidak ditemukan");
                        }
                    }
                ],
                'items.*.volume'  => 'required|numeric|min:0.01',
            ], [
                'deskripsi.required' => 'Masukkan deskripsi!',
                'satuan.required' => 'Masukkan satuan!',
                'provinsi.required' => 'Pilih provinsi!',
                'kab.required' => 'Pilih kabupaten!',
                'tahun.required' => 'Masukkan tahun!',
                'items.required' => 'Tambahkan item minimal satu!',
                'items.*.item_no.required' => 'Pilih item!',
                'items.*.item_no.exists' => 'Item tidak ditemukan dalam database!',
                'items.*.volume.required' => 'Masukkan volume!',
                'items.*.volume.numeric' => 'Volume harus berupa angka!',
                'items.*.volume.min' => 'Volume minimal 0.01!',
            ]);

            $vendorId = null;
            if ($request->filled('vendor_id')) {
                $vendor = Vendor::where('vendor_id', $request['vendor_id'])->first();
                if ($vendor) {
                    $vendorId = $vendor->vendor_id;
                }
            }

            $noAhs = $this->generateNoAhs();
            // 1) buat AHS dulu
            $add_ahs = Ahs::create([
                'ahs'       => $noAhs, // atau boleh 'TEMP'
                'deskripsi' => $request->deskripsi,
                'merek'     => $request->merek,
                'satuan'    => $request->satuan,
                'provinsi'  => $request->provinsi,
                'kab'       => $request->kab,
                'tahun'     => $request->tahun,
                'spesifikasi' => $request->spesifikasi,
                'produk_deskripsi' => $request->produk_deskripsi,
                'vendor_id' => $vendorId,
                'harga_pokok_total' => 0
            ]);

            // 2) simpan detail AHS (AhsItem)
            $totalHppAhs = 0;

            foreach ($request->items as $inputItem) {

                $volume = $inputItem['volume'];
                $itemNo = $inputItem['item_no'];

                // =========================
                // JIKA ITEM BIASA
                // =========================
                if (Item::where('item_no', $itemNo)->exists()) {

                    $item = Item::where('item_no', $itemNo)->first();

                    $hpp     = $item->hpp;
                    $uraian = $item->deskripsi;
                    $satuan = $item->satuan;
                    $jumlah = $volume * $hpp;

                    AhsItem::create([
                        'ahs_id'  => $add_ahs->ahs_id,
                        'item_id' => $item->item_id,
                        'uraian'  => $uraian,
                        'kategori'  => 'item',
                        'satuan'  => $satuan,
                        'volume'  => $volume,
                        'hpp'     => $hpp,
                        'jumlah'  => $jumlah,
                    ]);

                    $totalHppAhs += $jumlah;

                    // =========================
                    // JIKA AHS LAIN
                    // =========================
                } else {

                    $childAhs = Ahs::where('ahs', $itemNo)->firstOrFail();

                    $hpp     = $childAhs->harga_pokok_total;
                    $uraian = '[AHS] ' . $childAhs->deskripsi;
                    $satuan = $childAhs->satuan;
                    $jumlah = $volume * $hpp;

                    AhsItem::create([
                        'ahs_id'  => $add_ahs->ahs_id,
                        'item_id' => $childAhs->ahs_id, // 👈 PENTING
                        'uraian'  => $uraian,
                        'kategori'  => 'ahs',
                        'satuan'  => $satuan,
                        'volume'  => $volume,
                        'hpp'     => $hpp,
                        'jumlah'  => $jumlah,
                    ]);

                    $totalHppAhs += $jumlah;
                }
            }
            $add_ahs->update(['harga_pokok_total' => $totalHppAhs]);

            // 4) UPLOAD FILES -> simpan ke tabel item_files (polymorphic)
            // FOTO
            // --- FOTO ---
            if ($request->hasFile('produk_foto')) {
                foreach ($request->file('produk_foto') as $file) {

                    $filename = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
                    $path = $file->storeAs('uploads/gambar', $filename, 'public');

                    $uploadedPaths[] = $path;

                    // 🔹 SIMPAN KE AHS
                    ItemFile::create([
                        'fileable_id'   => $add_ahs->ahs_id,
                        'fileable_type' => Ahs::class,
                        'file_path'     => $path,
                        'file_type'     => 'gambar',
                    ]);
                }
            }

            // --- DOKUMEN ---
            if ($request->hasFile('produk_dokumen')) {
                foreach ($request->file('produk_dokumen') as $file) {

                    $filename = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
                    $path = $file->storeAs('uploads/dokumen', $filename, 'public');

                    $uploadedPaths[] = $path;

                    // 🔹 KE AHS
                    ItemFile::create([
                        'fileable_id'   => $add_ahs->ahs_id,
                        'fileable_type' => Ahs::class,
                        'file_path'     => $path,
                        'file_type'     => 'dokumen',
                    ]);
                }
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Data AHS berhasil ditambahkan']);
        } catch (ValidationException $e) {
            DB::rollBack();

            // jika ada file sudah terupload, hapus agar bersih
            foreach ($uploadedPaths as $p) {
                Storage::disk('public')->delete($p);
            }

            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            foreach ($uploadedPaths as $p) {
                Storage::disk('public')->delete($p);
            }

            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $ahs_id)
    {
        DB::beginTransaction();
        $uploadedPaths = [];

        try {
            // 1. VALIDASI
            $request->validate([
                'deskripsi' => 'required|string',
                // ... validasi lainnya ...
                'items'     => 'required|array',
            ]);

            $vendorId = null;
            if ($request->filled('vendor_id')) {
                $vendor = Vendor::where('vendor_id', $request['vendor_id'])->first();
                if ($vendor) {
                    $vendorId = $vendor->vendor_id;
                }
            }

            // 2. UPDATE HEADER
            $ahs = Ahs::findOrFail($ahs_id);
            $ahs->update([
                'deskripsi' => $request->deskripsi,
                'satuan'    => $request->satuan,
                'provinsi'  => $request->provinsi,
                'kab'       => $request->kab,
                'tahun'     => $request->tahun,
                'merek'    => $request->merek,
                'produk_deskripsi'    => $request->produk_deskripsi,
                'spesifikasi'    => $request->spesifikasi,
                'vendor_id'    => $vendorId,
                // field lain...
            ]);

            // ==========================================================
            // PERBAIKAN: SESUAIKAN NAMA PRIMARY KEY DI BAWAH INI
            // ==========================================================
            $primaryKey = 'ahs_item_id'; // <--- GANTI 'ahs_item_id' DENGAN NAMA KOLOM ASLI DI TABEL ahs_items ANDA
            // ==========================================================

            // A. Ambil ID yang ada di DB (Gunakan nama kolom yang benar)
            $existingIds = $ahs->items()->pluck($primaryKey)->toArray();

            // B. Ambil ID dari Form
            $submittedIds = [];
            if ($request->has('items')) {
                foreach ($request->items as $item) {
                    // Pastikan Frontend mengirim key 'id' yang berisi nilai Primary Key tersebut
                    if (isset($item['id']) && $item['id']) {
                        $submittedIds[] = $item['id'];
                    }
                }
            }

            // C. HAPUS Data
            $idsToDelete = array_diff($existingIds, $submittedIds);
            if (!empty($idsToDelete)) {
                // Kita gunakan whereIn delete manual agar lebih aman dari nama PK yang beda
                AhsItem::whereIn($primaryKey, $idsToDelete)->delete();
            }

            // D. LOOPING UPDATE / INSERT
            $totalHppAhs = 0;

            if ($request->has('items')) {
                foreach ($request->items as $inputItem) {

                    $volume = $inputItem['volume'] ?? 0;
                    $itemNo = $inputItem['item_no'] ?? null;

                    if (!$itemNo) continue;

                    // ... (Logika Hitung HPP / Cari Material sama seperti sebelumnya) ...
                    $hpp = 0;
                    $uraian = '';
                    $satuan = '';
                    $kategori = '';
                    $itemId = null;
                    $materialItem = Item::where('item_no', $itemNo)->first();

                    if ($materialItem) {
                        $hpp     = $materialItem->hpp;
                        $uraian  = $materialItem->deskripsi;
                        $satuan  = $materialItem->satuan;
                        $itemId  = $materialItem->item_id;
                        $kategori = 'item';
                    } else {
                        $childAhs = Ahs::where('ahs', $itemNo)->first();
                        if ($childAhs) {
                            $hpp     = $childAhs->harga_pokok_total;
                            $uraian  = '[AHS] ' . $childAhs->deskripsi;
                            $satuan  = $childAhs->satuan;
                            $itemId  = $childAhs->ahs_id;
                            $kategori = 'ahs';
                        }
                    }

                    $jumlah = $volume * $hpp;
                    $totalHppAhs += $jumlah;

                    // EKSEKUSI SIMPAN
                    // Cek apakah ini update (punya ID) atau insert (tidak punya ID)
                    if (isset($inputItem['id']) && $inputItem['id']) {
                        // --- UPDATE ITEM LAMA ---
                        // Gunakan variabel $primaryKey yang kita set di atas
                        $existingItem = AhsItem::where('ahs_id', $ahs->ahs_id)
                            ->where($primaryKey, $inputItem['id']) // <--- Perubahan Disini
                            ->first();

                        if ($existingItem) {
                            $existingItem->update([
                                'item_id' => $itemId,
                                'uraian'  => $uraian,
                                'kategori' => $kategori,
                                'satuan'  => $satuan,
                                'volume'  => $volume,
                                'hpp'     => $hpp,
                                'jumlah'  => $jumlah,
                            ]);
                        }
                    } else {
                        // --- INSERT ITEM BARU ---
                        AhsItem::create([
                            'ahs_id'  => $ahs->ahs_id,
                            'item_id' => $itemId,
                            'uraian'  => $uraian,
                            'kategori' => $kategori,
                            'satuan'  => $satuan,
                            'volume'  => $volume,
                            'hpp'     => $hpp,
                            'jumlah'  => $jumlah,
                        ]);
                    }
                }
            }

            $ahs->update(['harga_pokok_total' => $totalHppAhs]);

            // ... (Kode File Foto/Dokumen lanjutkan seperti biasa) ...
            // ================= FOTO =================
            $existingFotos = $request->input('existing_foto', []); // 🔥 JANGAN json_decode

            $oldFotos = ItemFile::where('fileable_id', $ahs->ahs_id)
                ->where('fileable_type', Ahs::class)
                ->where('file_type', 'gambar')
                ->get();

            foreach ($oldFotos as $old) {

                $oldFileName = basename($old->file_path); // 🔥 BANDINKAN NAMA FILE

                if (!in_array($oldFileName, $existingFotos)) {

                    if (Storage::disk('public')->exists($old->file_path)) {
                        Storage::disk('public')->delete($old->file_path);
                    }

                    $old->delete();
                }
            }

            // 🔹 SIMPAN FOTO BARU
            if ($request->hasFile('produk_foto')) {
                foreach ($request->file('produk_foto') as $file) {
                    $filename = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
                    $path = $file->storeAs('uploads/gambar', $filename, 'public');

                    $uploadedPaths[] = $path;

                    ItemFile::create([
                        'fileable_id'   => $ahs->ahs_id,
                        'fileable_type' => Ahs::class,
                        'file_path'     => $path,
                        'file_type'     => 'gambar',
                    ]);
                }
            }

            // ================= DOKUMEN =================
            $existingDocs = $request->input('existing_produk_dokumen', []);

            $oldDocs = ItemFile::where('fileable_id', $ahs->ahs_id)
                ->where('fileable_type', Ahs::class)
                ->where('file_type', 'dokumen')
                ->get();

            foreach ($oldDocs as $old) {

                $oldFileName = basename($old->file_path);

                if (!in_array($oldFileName, $existingDocs)) {

                    if (Storage::disk('public')->exists($old->file_path)) {
                        Storage::disk('public')->delete($old->file_path);
                    }

                    $old->delete();
                }
            }

            // 🔹 SIMPAN DOKUMEN BARU
            if ($request->hasFile('produk_dokumen')) {
                foreach ($request->file('produk_dokumen') as $file) {
                    $filename = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
                    $path = $file->storeAs('uploads/dokumen', $filename, 'public');

                    $uploadedPaths[] = $path;

                    ItemFile::create([
                        'fileable_id'   => $ahs->ahs_id,
                        'fileable_type' => Ahs::class,
                        'file_path'     => $path,
                        'file_type'     => 'dokumen',
                    ]);
                }
            }


            DB::commit();
            return response()->json(['success' => true, 'message' => 'Berhasil']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($ahs_id)
    {
        DB::beginTransaction();

        try {
            $ahs = Ahs::find($ahs_id);
            if (!$ahs) throw new \Exception('Data AHS tidak ditemukan');

            AhsItem::where('ahs_id', $ahs->ahs_id)->delete();
            Item::where('item_no', $ahs->ahs)->delete();
            $ahs->delete();

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Data AHS berhasil dihapus']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data AHS', 'error' => $e->getMessage()], 500);
        }
    }

    public function export()
    {
        try {
            return Excel::download(new AhsExport(), 'data_ahs.xlsx');
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal melakukan export', 'error' => $e->getMessage()], 500);
        }
    }

    public function downloadImportTemplate()
    {
        try {
            return Excel::download(new AhsImportTemplateExport(), 'template_import_ahs.xlsx');
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mengunduh template', 'error' => $e->getMessage()], 500);
        }
    }

    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,xls'
            ]);

            DB::beginTransaction();
            Excel::import(new AhsImport(), $request->file('file'));
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Data AHS berhasil di-import.']);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Validasi file gagal', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan saat import data', 'error' => $e->getMessage()], 500);
        }
    }
}
