<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172a2c; font: 6pt "DejaVu Sans", sans-serif; }
        .sheet { width: 186mm; height: 267mm; page-break-inside: avoid; }
        .grid { width: 186mm; height: 267mm; table-layout: fixed; border-collapse: collapse; }
        .grid td { width: 62mm; height: 89mm; padding: 1mm; text-align: center; vertical-align: middle; }
        .page-break { height: 0; page-break-after: always; }
    </style>
    <?php echo '<style>' . file_get_contents(resource_path('css/qr-card-pdf.css')) . '</style>'; ?>
</head>
<body>
@foreach(array_chunk($cards, 9) as $sheetCards)
    <section class="sheet">
        <table class="grid"><tbody>
        @foreach(array_chunk($sheetCards, 3) as $row)
            <tr>
                @foreach($row as $card)
                    <td><x-student-qr-card
                        :student="$card['student']"
                        :photo-src="$card['photoDataUri']"
                        :qr-src="$card['qrDataUri']"
                        :pdf="true"
                        :template-src="$card['templateDataUri']"
                        :template-card-height-mm="null"
                    /></td>
                @endforeach
                @for($empty = count($row); $empty < 3; $empty++)<td></td>@endfor
            </tr>
        @endforeach
        </tbody></table>
    </section>
    @if(! $loop->last)<div class="page-break"></div>@endif
@endforeach
</body>
</html>