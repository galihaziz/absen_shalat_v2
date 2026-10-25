@extends('layouts.app')

@section('title', 'Haid Lebih dari 15 Hari · Rekap Absensi Solat')

@section('content')
<div class="page-heading"><div><p class="eyebrow">TINDAK LANJUT KEHADIRAN</p><h1>Daftar haid lebih dari 15 hari</h1></div><a class="button" href="{{ route('attendance.index') }}">Kembali ke absensi</a></div>
<section class="panel">
    <p>Daftar ini menampilkan siswi yang catatan haidnya masih berlanjut lebih dari 15 hari kalender. Sabtu dan Minggu ikut dihitung; rangkaian dicatat dari hari haid pertama sampai hari ini berdasarkan status haid pada hari sekolah.</p>
    <div class="table-wrap"><table><thead><tr><th class="table-row-number">No.</th><th>Induk</th><th class="sticky-name">Nama</th><th>Kelas</th><th>Mulai haid</th><th>Haid terakhir tercatat</th><th>Durasi kalender</th></tr></thead><tbody>
    @forelse($students as $index => $student)
        <tr><td class="table-row-number">{{ $index + 1 }}</td><td>{{ $student['induk'] }}</td><td class="sticky-name">{{ $student['nama'] }}</td><td>{{ $student['kelas'] }}</td><td>{{ \Illuminate\Support\Carbon::parse($student['start_date'])->format('d/m/Y') }}</td><td>{{ \Illuminate\Support\Carbon::parse($student['last_haid_date'])->format('d/m/Y') }}</td><td><strong>{{ $student['days'] }} hari</strong></td></tr>
    @empty
        <tr><td colspan="7" class="empty">Tidak ada siswi dengan catatan haid lebih dari 15 hari kalender.</td></tr>
    @endforelse
    </tbody></table></div>
</section>
@endsection
