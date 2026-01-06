<table>
    <tbody>
        @foreach ($allAhs as $ahs)
            
            <tr style="background-color: #DDEBF7;">
                <td style="font-weight: bold;">{{ $ahs->ahs }}</td>
                <td style="font-weight: bold;">{{ $ahs->deskripsi }}</td>
                <td></td>
                <td style="font-weight: bold;">{{ $ahs->satuan }}</td>
                <td style="font-weight: bold;">{{ $ahs->wilayah }}</td>
                <td style="font-weight: bold;">{{ $ahs->tahun }}
                <td></td> <td style="font-weight: bold; text-align: right;">HARGA POKOK TOTAL</td>
                <td style="font-weight: bold; text-align: right;">{{ $ahs->harga_pokok_total }}</td>
            </tr>
            
            <tr style="background-color: #E2EFDA;">
                <td></td> <td style="font-weight: bold;">ITEM_ID</td>
                <td style="font-weight: bold;">URAIAN</td>
                <td style="font-weight: bold;">SATUAN</td>
                <td style="font-weight: bold; text-align: right;">VOLUME</td>
                <td style="font-weight: bold; text-align: right;">HPP</td>
                <td style="font-weight: bold; text-align: right;">JUMLAH</td>
            </tr>

            @foreach ($ahs->items as $item)
                <tr>
                    <td></td> <td>{{ $item->item->item_no ?? 'N/A' }}</td>
                    
                    <td>{{ $item->uraian }}</td>
                    <td>{{ $item->satuan }}</td>
                    <td style="text-align: right;">{{ $item->volume }}</td>
                    <td style="text-align: right;">{{ $item->hpp }}</td>
                    <td style="text-align: right;">{{ $item->jumlah }}</td>
                </tr>
            @endforeach

            <tr>
                <td colspan="7"></td>
            </tr>
        @endforeach
    </tbody>
</table>