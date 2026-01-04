<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ahs extends Model
{
    use HasFactory;

    protected $table = 'ahs';
    protected $primaryKey = 'ahs_id';

    /**
     * Kolom yang bisa diisi (mass assignment).
     */
    protected $fillable = [
        'ahs',
        'deskripsi',
        'merek',
        'satuan',
        'vendor_id',
        'provinsi', // Ditambahkan
        'kab', // Menggantikan 'wilayah'
        'tahun',
        'harga_pokok_total',
        'produk_foto',
        'produk_deskripsi',
        'produk_dokumen',
        'spesifikasi',
    ];

    /**
     * Cast otomatis untuk angka
     */
    protected $casts = [
        'tahun' => 'integer',
        'harga_pokok_total' => 'decimal:2',
        'produk_foto' => 'array',
        'produk_dokumen' => 'array',
    ];

    /**
     * Relasi ke AhsItem (detail)
     */
    public function items()
    {
        return $this->hasMany(AhsItem::class, 'ahs_id', 'ahs_id');
    }
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'vendor_id');
    }
    public function files()
    {
        return $this->morphMany(ItemFile::class, 'fileable');
    }
}
