<?php

namespace App\Exports;

use App\Models\Ahs;
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

        $allAhs = Ahs::with([
            'items.item',
            'items.ahsRef',
            'vendor'
        ])
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
        return 'Data AHS';
    }
}
