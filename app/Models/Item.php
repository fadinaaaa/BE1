<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $table = 'items';
    protected $primaryKey = 'item_id'; // sesuaikan dengan migrasi kamu

    protected $fillable = [
        'item_no',
        'ahs',
        'deskripsi',
        'merek',
        'satuan',
        'hpp',
        'vendor_id',
        'provinsi', // Ditambahkan
        'kab', // Menggantikan 'wilayah'
        'tahun',
        'produk_foto',
        'produk_deskripsi',
        'produk_dokumen',
        'spesifikasi',
    ];

    protected $casts = [
        'hpp' => 'decimal:2',
        'tahun' => 'integer',
        'produk_foto' => 'array',
        'produk_dokumen' => 'array',
    ];

    /**
     * Relasi ke Vendor
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'vendor_id');
    }
    public function files()
    {
        return $this->morphMany(ItemFile::class, 'fileable');
    }

    public function gambar()
    {
        return $this->morphMany(ItemFile::class, 'fileable')
            ->where('file_type', 'gambar');
    }

    public function dokumen()
    {
        return $this->morphMany(ItemFile::class, 'fileable')
            ->where('file_type', 'dokumen');
    }
}