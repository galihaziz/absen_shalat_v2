<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172a2c; font: 6pt "DejaVu Sans", sans-serif; }
        html, body { width: 297mm; margin: 0; padding: 0; }
        .sheet { position: relative; width: 297mm; height: 210mm; page-break-inside: avoid; }
        .grid { position: absolute; top: 14.4mm; left: 34.54mm; width: 227.92mm; height: 181.2mm; margin: 0; table-layout: fixed; border-collapse: collapse; }
        .grid td { width: 113.96mm; height: 90.6mm; padding: 2.5mm; text-align: center; vertical-align: middle; }
        .pair-cell { width: 108.96mm; height: 85.6mm; margin: 0 auto; }
        .page-break { width: 0; height: 0; page-break-after: always; }
    </style>
    <?php echo '<style>' . file_get_contents(resource_path('css/qr-card-pdf.css')) . '</style>'; ?>
</head>
<body>
@foreach(array_chunk($cards, 4) as $sheetCards)
    @php($rows = array_map(static fn (array $row): array => array_pad($row, 2, null), array_chunk($sheetCards, 2)))
    <section class="sheet">
        <table class="grid"><tbody>
        @foreach($rows as $row)
            <tr>
                @foreach($row as $card)
                    @if($card)
                        <td><div class="pair-cell"><x-student-qr-card
                            :student="$card['student']"
                            :photo-src="$card['photoDataUri']"
                            :qr-src="$card['qrDataUri']"
                            :pdf="true"
                            :template-src="$card['templateDataUri']"
                        /></div></td>
                    @else
                        <td></td>
                    @endif
                @endforeach
            </tr>
        @endforeach
        </tbody></table>
    </section>
    @if(! $loop->last)<div class="page-break"></div>@endif
@endforeach
</body>
</html>