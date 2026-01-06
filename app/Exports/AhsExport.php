<?php

namespace App\Exports;

use App\Models\Ahs; // Ambil data dari Model AHS (parent)
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;

class AhsExport implements FromView, WithTitle
{
    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function view(): View
    {
        // ... (Kode di dalam fungsi view() ini tidak perlu diubah) ...
        $allAhs = Ahs::with('items.item')
                    ->orderBy('ahs')
                    ->get();

        return view('exports.ahs', [
            'allAhs' => $allAhs
        ]);
    }

    /**
     * @return string
     */
    public function title(): string
    {
        // ... (Kode ini juga tidak perlu diubah) ...
        return 'Data AHS';
    }
}