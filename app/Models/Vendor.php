<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;

    protected $table = 'vendors';
    protected $primaryKey = 'vendor_id';

    /**
     * Kolom yang bisa diisi (mass assignment).
     */
    protected $fillable = [
        'vendor_no',
        'vendor_name',
        'contact_name',
        'contact_no',
        'email',
        'provinsi', // Ditambahkan
        'kab', // Menggantikan 'wilayah'
        'tahun',
    ];

    /**
     * Cast otomatis
     */
    protected $casts = [
        'tahun' => 'integer',
    ];

    /**
     * Relasi ke Item (1 Vendor bisa punya banyak Item)
     */
    public function items()
    {
        return $this->hasMany(Item::class, 'vendor_id', 'vendor_id');
    }
    
}
