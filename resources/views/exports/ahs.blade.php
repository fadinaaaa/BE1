<table>
    <tbody>
        @foreach ($allAhs as $ahs)

        <tr style="background-color: #DDEBF7;">
            <td style="font-weight: bold;">{{ $ahs->ahs }}</td>
            <td style="font-weight: bold;">{{ $ahs->deskripsi }}</td>
            <td style="font-weight: bold;">
                {{ $ahs->vendor->vendor_no ?? '-' }}
            </td>
            <td style="font-weight: bold;">{{ $ahs->satuan }}</td>
            <td style="font-weight: bold;">{{ $ahs->provinsi }}</td>
            <td style="font-weight: bold;">{{ $ahs->kab }}</td>
            <td style="font-weight: bold;">{{ $ahs->tahun }}</td>
            <td></td>
            <td style="font-weight: bold; text-align: right;">HARGA POKOK TOTAL</td>
            <td style="font-weight: bold; text-align: right;">{{ $ahs->harga_pokok_total }}</td>
        </tr>

        <tr style="background-color: #E2EFDA;">
            <td></td>
            <td style="font-weight: bold;">ITEM_ID</td>
            <td style="font-weight: bold;">URAIAN</td>
            <td style="font-weight: bold;">SATUAN</td>
            <td style="font-weight: bold; text-align: right;">VOLUME</td>
            <td style="font-weight: bold; text-align: right;">HPP</td>
            <td style="font-weight: bold; text-align: right;">JUMLAH</td>
        </tr>

        @foreach ($ahs->items as $item)
        @php
        $isItemMaster = $item->item !== null;
        $isAhsRef = !$isItemMaster && $item->ahsRef !== null;

        $itemNo = $isItemMaster
        ? $item->item->item_no
        : ($isAhsRef ? $item->ahsRef->ahs : $item->item_id);

        $uraian = $isItemMaster
        ? $item->item->deskripsi
        : ($isAhsRef ? $item->ahsRef->deskripsi : $item->uraian);

        $satuan = $isItemMaster
        ? $item->item->satuan
        : ($isAhsRef ? $item->ahsRef->satuan : $item->satuan);

        $hpp = $item->hpp;
        $jumlah = $item->volume * $hpp;
        @endphp

        <tr>
            <td></td>
            <td>{{ $itemNo }}</td>
            <td>{{ $uraian }}</td>
            <td>{{ $satuan }}</td>
            <td style="text-align:right">{{ $item->volume }}</td>
            <td style="text-align:right">{{ $hpp }}</td>
            <td style="text-align:right">{{ $jumlah }}</td>
        </tr>
        @endforeach

        <tr>
            <td colspan="7"></td>
        </tr>
        @endforeach
    </tbody>
</table>