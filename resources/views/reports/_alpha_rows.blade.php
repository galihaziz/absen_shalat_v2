@forelse($rows as $index => $row)
    <tr @class(['irma-row' => $row->is_irma, 'nonis-row' => $row->is_nonis])><td class="table-row-number">{{ $startIndex + $index + 1 }}</td><td>{{ $row->induk }}</td><td class="sticky-name">{{ $row->nama }}</td><td>{{ $row->jenis_kelamin }}</td><td>{{ $row->kelas }}</td><td>{{ $row->total_alpha }}</td><td>{{ $row->tanggal_alpha }}</td></tr>
@empty
    <tr><td colspan="7" class="empty">Tidak ada siswa alpha pada periode ini.</td></tr>
@endforelse
