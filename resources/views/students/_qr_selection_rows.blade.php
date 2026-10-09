@forelse($students as $student)
    <tr>
        <td><input class="student-qr-select" type="checkbox" name="student_ids[]" value="{{ $student->id }}" form="studentQrPrintForm" aria-label="Pilih kartu {{ $student->nama }}"></td>
        <td>{{ $student->induk }}</td>
        <td class="sticky-name">{{ $student->nama }}</td>
        <td>{{ $student->classroom->nama }}</td>
    </tr>
@empty
    <tr><td colspan="4" class="empty">Belum ada siswa yang cocok.</td></tr>
@endforelse
