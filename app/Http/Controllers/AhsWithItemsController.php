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
                'items.item',  // relasi AhsItem → Item
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
                        'vendor' => $ahs->vendor ?? [],


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
                                'item_no' => $it->item->item_no ?? null,
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
        $prefix = 'AHS';

        // Fungsi ini sudah benar menggunakan item_no
        $last = DB::table('items')
            ->where('item_no', 'like', "$prefix%")
            ->orderBy('item_id', 'desc')
            ->value('item_no');

        if ($last) {
            preg_match('/(\d+)$/', $last, $matches);
            $lastNumber = isset($matches[1]) ? (int) $matches[1] : 0;
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . $nextNumber;
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

    // public function store(Request $request)
    // {
    //     DB::beginTransaction();

    //     try {
    //         $validated = $request->validate([
    //             'ahs'       => 'required|string',
    //             'deskripsi' => 'required|string',
    //             'merek' => 'required|string',
    //             'satuan'    => 'required|string',
    //             'vendor_id' => 'required|integer',
    //             'provinsi'  => 'required|string',
    //             'kab'       => 'required|string',
    //             'tahun'     => 'required|integer',
    //             'produk_gambar' => 'required|file',
    //             'produk_deskripsi' => 'required|string',
    //             'produk_dokumen' => 'required|file',
    //             'spesifikasi' => 'required|string',

    //             'items'              => 'required|array',
    //             'items.*.item_no'    => 'required|string|exists:items,item_no',
    //             'items.*.volume'     => 'required|numeric|min:0.01',
    //         ]);

    //         $ahs = Ahs::create([
    //             'ahs'     => $validated['ahs'],
    //             'deskripsi' => $validated['deskripsi'],
    //             'satuan'    => $validated['satuan'],
    //             'provinsi'  => $validated['provinsi'],
    //             'kab'       => $validated['kab'],
    //             'tahun'     => $validated['tahun'],
    //             'harga_pokok_total' => 0,
    //         ]);

    //         $totalHarga = 0;

    //         foreach ($validated['items'] as $inputItem) {
    //             // Cari item berdasarkan item_no, bukan item_id
    //             $item = Item::where('item_no', $inputItem['item_no'])->firstOrFail();

    //             $volume = $inputItem['volume'];
    //             $hpp = $item->hpp;
    //             $jumlah = $volume * $hpp;

    //             $ahs->items()->create([
    //                 'item_id' => $item->item_id, // Foreign key tetap menggunakan item_id
    //                 'uraian'  => $item->deskripsi,
    //                 'satuan'  => $item->satuan,
    //                 'volume'  => $volume,
    //                 'hpp'     => $hpp,
    //                 'jumlah'  => $jumlah,
    //             ]);

    //             $totalHarga += $jumlah;
    //         }

    //         $ahs->update(['harga_pokok_total' => $totalHarga]);

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'AHS dan detail berhasil disimpan',
    //             'data'    => $ahs->load('items.item')
    //         ]);
    //     } catch (ValidationException $e) {
    //         DB::rollBack();
    //         return response()->json(['message' => 'Validasi gagal', 'errors' => $e->errors()], 422);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json(['message' => 'Terjadi kesalahan', 'error' => $e->getMessage()], 500);
    //     }
    // }

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
                'vendor_no' => 'nullable|string|exists:vendors,vendor_no',


                // ubah ke array agar mendukung multiple file; tetap kompatibel jika user hanya kirim 1 file
                'produk_foto'        => 'nullable|array',
                'produk_foto.*'      => 'file|mimes:jpg,jpeg,png|max:2048',

                'produk_dokumen'     => 'nullable|array',
                'produk_dokumen.*'   => 'file|mimes:pdf,doc,docx,xls,xlsx|max:5120',

                'produk_deskripsi' => 'nullable|string',
                'spesifikasi' => 'nullable|string',

                'items'     => 'required|array',
                'items.*.item_no' => 'required|string|exists:items,item_no',
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

            $vendorId = $request->vendor_id ?? null;

            // 1) buat AHS
            $add_ahs = Ahs::create([
                'ahs'       => 'no ahs sementara',
                'deskripsi' => $request->deskripsi,
                'merek'     => $request->merek,
                'satuan'    => $request->satuan,
                'provinsi'  => $request->provinsi,
                'kab'       => $request->kab,
                'tahun'     => $request->tahun,
                'spesifikasi' => $request->spesifikasi,
                'produk_deskripsi' => $request->produk_deskripsi,
                'harga_pokok_total' => 0
            ]);

            // 2) simpan detail AHS (AhsItem)
            $totalHppAhs = 0;

            foreach ($request->items as $inputItem) {
                $item = Item::where('item_no', $inputItem['item_no'])->firstOrFail();
                $volume = $inputItem['volume'];
                $hpp = $item->hpp;
                $uraian = $item->deskripsi;
                $satuan = $item->satuan;
                $jumlah = $volume * $hpp;

                AhsItem::create([
                    'ahs_id'  => $add_ahs->ahs_id,
                    'item_id' => $item->item_id,
                    'uraian'  => $uraian,
                    'satuan'  => $satuan,
                    'volume'  => $volume,
                    'hpp'     => $hpp,
                    'jumlah'  => $jumlah,
                ]);

                $totalHppAhs += $jumlah;
            }

            $add_ahs->update(['harga_pokok_total' => $totalHppAhs]);

            // 3) create Item yang mewakili AHS
            $add_ahs_to_item = Item::create([
                'item_no'   => $this->generateNoAhs(),
                'ahs'       => 'AHS',
                'deskripsi' => $add_ahs->deskripsi,
                'satuan'    => $add_ahs->satuan,
                'hpp'       => $add_ahs->harga_pokok_total,
                'provinsi'  => $add_ahs->provinsi,
                'kab'       => $add_ahs->kab,
                'tahun'     => $add_ahs->tahun,
                'merek' => $add_ahs->merek ?? null,
                'vendor_id' => $vendorId,
                // jangan simpan produk_foto/produk_dokumen di kolom item lagi
                'produk_deskripsi' => $add_ahs->produk_deskripsi ?? null,
                'spesifikasi' => $add_ahs->spesifikasi ?? null
            ]);

            // update nilai ahs (item_no) di table ahs
            $add_ahs->update(['ahs' => $add_ahs_to_item->item_no]);

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

                    // 🔹 SIMPAN KE ITEM AHS
                    ItemFile::create([
                        'fileable_id'   => $add_ahs_to_item->item_id,
                        'fileable_type' => Item::class,
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

                    // 🔹 KE ITEM
                    ItemFile::create([
                        'fileable_id'   => $add_ahs_to_item->item_id,
                        'fileable_type' => Item::class,
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

        $uploadedPaths = []; // Untuk rollback file jika error

        try {
            // VALIDASI
            $request->validate([
                'deskripsi' => 'required|string',
                'satuan'    => 'required|string',
                'provinsi'  => 'required|string',
                'kab'       => 'required|string',
                'tahun'     => 'required|string',

                'merek'            => 'nullable|string',
                'vendor_id'        => 'nullable|integer|exists:vendors,vendor_id',
                'spesifikasi'      => 'nullable|string',
                'produk_deskripsi' => 'nullable|string',

                // MULTIPLE FILE (foto)
                'produk_foto'   => 'nullable|array',
                'produk_foto.*' => 'file|mimes:jpg,jpeg,png|max:2048',

                // MULTIPLE FILE (dokumen)
                'produk_dokumen'   => 'nullable|array',
                'produk_dokumen.*' => 'file|mimes:pdf,doc,docx,xls,xlsx|max:5120',

                'items'     => 'required|array',
                'items.*.item_no' => 'required|string|exists:items,item_no',
                'items.*.volume'  => 'required|numeric|min:0.01',
            ], [
                'deskripsi.required' => 'Masukkan Deskripsi AHS',
                'satuan.required'    => 'Masukkan Satuan AHS',
                'provinsi.required'  => 'Masukkan Provinsi',
                'kab.required'       => 'Masukkan Kabupaten / Kota',
                'tahun.required'     => 'Masukkan Tahun',
            ]);

            // AMBIL DATA AHS
            $ahs = Ahs::find($ahs_id);
            if (!$ahs) throw new \Exception('Data AHS tidak ditemukan');

            // UPDATE DATA AHS (header)
            $ahs->update([
                'deskripsi' => $request->deskripsi,
                'satuan'    => $request->satuan,
                'provinsi'  => $request->provinsi,
                'kab'       => $request->kab,
                'tahun'     => $request->tahun,
                'vendor_id'     => $request->vendor_id,
                'merek'     => $request->merek,
            ]);

            // HAPUS DETAIL LAMA
            AhsItem::where('ahs_id', $ahs->ahs_id)->delete();

            // BUAT DETAIL BARU
            $totalHppAhs = 0;

            foreach ($request->items as $inputItem) {
                $item = Item::where('item_no', $inputItem['item_no'])->firstOrFail();
                $volume = $inputItem['volume'];

                $jumlah = $volume * $item->hpp;

                AhsItem::create([
                    'ahs_id'  => $ahs->ahs_id,
                    'item_id' => $item->item_id,
                    'uraian'  => $item->deskripsi,
                    'satuan'  => $item->satuan,
                    'volume'  => $volume,
                    'hpp'     => $item->hpp,
                    'jumlah'  => $jumlah,
                ]);

                $totalHppAhs += $jumlah;
            }

            $ahs->update(['harga_pokok_total' => $totalHppAhs]);

            // AMBIL ITEM YANG MEWAKILI AHS (item_no = nilai ahs)
            $item_ahs = Item::where('item_no', $ahs->ahs)->first();
            if (!$item_ahs) throw new \Exception('Item AHS tidak ditemukan');

            // UPDATE FIELDS BIASA (selain file)
            $item_ahs->update([
                'deskripsi'        => $ahs->deskripsi,
                'satuan'           => $ahs->satuan,
                'hpp'              => $totalHppAhs,
                'provinsi'         => $ahs->provinsi,
                'kab'              => $ahs->kab,
                'tahun'            => $ahs->tahun,

                'merek'            => $request->merek,
                'vendor_id'        => $request->vendor_id,
                'spesifikasi'      => $request->spesifikasi,
                'produk_deskripsi' => $request->produk_deskripsi,
            ]);

            /*
        |--------------------------------------------------------------------------
        | ⭐ UPDATE FILE – MULTIPLE FOTO & DOKUMEN
        |--------------------------------------------------------------------------
        | Disimpan ke tabel item_files POLYMORPHIC
        |--------------------------------------------------------------------------
        */

            // (Opsional) Hapus file lama jika ingin replace total
            if ($request->replace_files == '1') {
                foreach ($item_ahs->files as $f) {
                    Storage::disk('public')->delete($f->file_path);
                    $f->delete();
                }
            }

            // FOTO
            if ($request->hasFile('produk_foto')) {
                foreach ($request->file('produk_foto') as $file) {
                    $filename = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
                    $path = $file->storeAs('uploads/gambar', $filename, 'public');

                    $uploadedPaths[] = $path;

                    ItemFile::create([
                        'fileable_id'   => $item_ahs->item_id,
                        'fileable_type' => Item::class,
                        'file_path'     => $path,
                        'file_type'     => 'gambar',
                    ]);
                }
            }

            // DOKUMEN
            if ($request->hasFile('produk_dokumen')) {
                foreach ($request->file('produk_dokumen') as $file) {
                    $filename = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
                    $path = $file->storeAs('uploads/dokumen', $filename, 'public');

                    $uploadedPaths[] = $path;

                    ItemFile::create([
                        'fileable_id'   => $item_ahs->item_id,
                        'fileable_type' => Item::class,
                        'file_path'     => $path,
                        'file_type'     => 'dokumen',
                    ]);
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Data AHS berhasil diperbarui']);
        } catch (\Throwable $e) {
            DB::rollBack();

            foreach ($uploadedPaths as $p) {
                Storage::disk('public')->delete($p);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
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
