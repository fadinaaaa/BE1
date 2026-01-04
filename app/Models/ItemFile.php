<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ItemFile extends Model
{
    protected $fillable = [
        'fileable_id',
        'fileable_type',
        'file_path',
        'file_type',
        'original_name' // ⚠️ WAJIB kalau mau nama file terdeteksi
    ];

    protected $appends = ['file_url', 'file_name'];

    public function fileable()
    {
        return $this->morphTo();
    }

    public function getFileUrlAttribute()
    {
        return $this->file_path
            ? '/storage/' . $this->file_path
            : null;
    }
    public function getFileNameAttribute()
    {
        return $this->original_name
            ?? ($this->file_path ? basename($this->file_path) : null);
    }
}