@forelse($students as $index => $student)
    <tr @class(['irma-row' => $student['is_irma'], 'nonis-row' => $student['is_nonis']])><td class="table-row-number">{{ $startIndex + $index + 1 }}</td><td>{{ $student['induk'] }}</td><td class="sticky-name">{{ $student['nama'] }}</td><td>{{ $student['kelas'] }}</td><td>{{ $student['alpha'] }}</td><td>{{ $student['izin'] }}</td><td>{{ $student['sakit'] }}</td><td>{{ $student['hadir'] }}</td><td>{{ $student['haid'] }}</td><td>{{ $student['scan_hadir_terakhir'] ?? '-' }}</td><td>{{ $student['percentage'] }}%</td></tr>
@empty
    <tr><td colspan="11" class="empty">Tidak ada data yang cocok. <a href="{{ route('reports.index') }}">Hapus filter laporan</a></td></tr>
@endforelse
