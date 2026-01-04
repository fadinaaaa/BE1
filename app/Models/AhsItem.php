<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AhsItem extends Model
{
    use HasFactory;

    protected $table = 'ahs_items';
    protected $primaryKey = 'ahs_item_id';

    /**
     * Kolom yang bisa diisi (mass assignment).
     */
    protected $fillable = [
        'ahs_id',
        'item_id',
        'uraian',
        'satuan',   
        'volume',
        'hpp',
        'jumlah',
    ];

    /**
     * Cast otomatis untuk angka
     */
    protected $casts = [
        'volume' => 'decimal:2',
        'hpp'    => 'decimal:2',
        'jumlah' => 'decimal:2',
    ];

    /**
     * Relasi ke Ahs (header)
     */
    public function ahs()
    {
        return $this->belongsTo(Ahs::class, 'ahs_id', 'ahs_id');
    }

    /**
     * Relasi ke Item (opsional)
     */
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }
}
