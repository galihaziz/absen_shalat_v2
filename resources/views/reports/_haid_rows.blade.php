@forelse($students as $index => $student)
    <tr><td class="table-row-number">{{ $startIndex + $index + 1 }}</td><td>{{ $student['induk'] }}</td><td class="sticky-name">{{ $student['nama'] }}</td><td>{{ $student['kelas'] }}</td><td>{{ \Illuminate\Support\Carbon::parse($student['start_date'])->format('d/m/Y') }}</td><td>{{ \Illuminate\Support\Carbon::parse($student['last_haid_date'])->format('d/m/Y') }}</td><td><strong>{{ $student['days'] }} hari</strong></td></tr>
@empty
    <tr><td colspan="7" class="empty">Tidak ada siswi dengan catatan haid lebih dari 15 hari kalender.</td></tr>
@endforelse
