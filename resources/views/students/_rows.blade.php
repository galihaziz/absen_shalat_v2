@forelse($students as $student)
    <tr>
        <td>{{ $student->induk }}</td>
        <td class="sticky-name">{{ $student->nama }}</td>
        <td>{{ $student->jenis_kelamin }}</td>
        <td>{{ $student->classroom->nama }}</td>
        <td>{{ $student->is_irma ? 'IRMA' : ($student->is_nonis ? 'Nonis' : 'Umum') }}</td>
        <td><div class="actions"><a class="button small" href="{{ route('students.qr', $student) }}" data-student-qr-link>QR</a><a class="button small" href="{{ route('reports.index', ['cari' => $student->nama]) }}">Riwayat</a><a class="button small" href="{{ route('students.index', ['edit' => $student->id]) }}">Ubah</a><form method="post" action="{{ route('students.pin.reset', $student) }}" onsubmit="return confirm('Reset PIN {{ $student->nama }} ke PIN awal siswa?')">@csrf<button class="button quiet small" type="submit">Reset PIN</button></form><form method="post" action="{{ route('students.destroy', $student) }}" onsubmit="return confirm('Hapus siswa ini dan seluruh absensinya?')">@csrf @method('DELETE')<button class="button danger small" type="submit">Hapus</button></form></div></td>
    </tr>
@empty
    <tr><td colspan="6" class="empty">Belum ada siswa yang cocok. @if($search !== '')<a href="{{ route('students.index') }}">Hapus pencarian</a>@else<a href="#studentCreate" data-student-create>Tambah siswa pertama</a>@endif</td></tr>
@endforelse