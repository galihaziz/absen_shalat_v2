@forelse($students as $student)
    <tr>
        @if($showingAlumni)<td><input type="checkbox" form="bulkAlumniForm" name="student_ids[]" value="{{ $student->id }}" aria-label="Pilih alumni {{ $student->nama }}" data-alumni-student></td>@endif
        <td>{{ $student->induk }}</td>
        <td class="sticky-name">{{ $student->nama }}</td>
        <td>{{ $student->jenis_kelamin }}</td>
        <td>{{ $student->classroom->nama }} @if($student->is_alumni) · Alumni @endif</td>
        <td>{{ $student->is_irma ? 'IRMA' : ($student->is_nonis ? 'Nonis' : 'Umum') }}</td>
        <td><div class="actions">
            @unless($student->is_alumni)<a class="button small" href="{{ route('students.qr', $student) }}" data-student-qr-link>QR</a>@endunless
            @if(! $student->is_alumni && auth()->user()?->getAttribute('role') === 'admin')<a class="button small" href="{{ route('students.face', $student) }}">{{ $student->faceRegistration ? 'Kelola wajah' : 'Daftarkan wajah' }}</a>@endif
            <a class="button small" href="{{ route('reports.index', ['cari' => $student->nama]) }}">Riwayat</a>
            <a class="button small" href="{{ route('students.index', ['edit' => $student->id]) }}">Ubah</a>
            @unless($student->is_alumni)<form method="post" action="{{ route('students.pin.reset', $student) }}" onsubmit="return confirm('Reset PIN {{ $student->nama }} ke PIN awal siswa?')">@csrf<button class="button quiet small" type="submit">Reset PIN</button></form>@endunless
            <form method="post" action="{{ route('students.destroy', $student) }}" onsubmit="return confirm('Hapus siswa ini dan seluruh absensinya?')">@csrf @method('DELETE')<button class="button danger small" type="submit">Hapus</button></form>
        </div></td>
    </tr>
@empty
    <tr><td colspan="{{ $showingAlumni ? 7 : 6 }}" class="empty">Belum ada {{ $showingAlumni ? 'siswa alumni' : 'siswa aktif' }} yang cocok. @if($search !== '')<a href="{{ route('students.index', ['status' => $showingAlumni ? 'alumni' : 'aktif']) }}">Hapus pencarian</a>@elseif(! $showingAlumni)<a href="#studentCreate" data-student-create-link>Tambah siswa pertama</a>@endif</td></tr>
@endforelse