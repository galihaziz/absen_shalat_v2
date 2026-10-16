@extends('layouts.app')

@section('title', 'Backup & Log · Rekap Absensi Solat')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">KEAMANAN DATA</p>
        <h1>Backup &amp; Log</h1>
        <p>Buat salinan database dan pulihkan data sebelumnya bila diperlukan.</p>
    </div>
</div>

<section class="panel">
    <div class="notice warning">
        Backup mencakup data database aplikasi, termasuk data siswa, absensi, akun, pengaturan, dan registrasi wajah. Foto siswa yang tersimpan sebagai file tidak termasuk. File backup dienkripsi dan disimpan di penyimpanan privat aplikasi.
    </div>
    <div class="actions">
        <form method="post" action="{{ route('backups.store') }}">
            @csrf
            <button class="button primary" type="submit">Buat backup sekarang</button>
        </form>
    </div>
</section>

<section class="panel">
    <div class="page-heading">
        <div><p class="eyebrow">ARSIP</p><h2>Backup database</h2></div>
    </div>
    <p class="warning-text">Memulihkan backup akan mengganti data database saat ini. Sistem otomatis membuat backup pengaman sebelum pemulihan. Pemulihan hanya tersedia untuk admin.</p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Waktu</th><th>Dibuat oleh</th><th>Jumlah data</th><th>File</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                @forelse($archives as $archive)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($archive->created_at)->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $archive->creator_name ?? 'Pengguna tidak tersedia' }}</td>
                        <td>{{ number_format((int) $archive->record_count, 0, ',', '.') }} baris</td>
                        <td><code>{{ $archive->filename }}</code></td>
                        <td>
                            @if(auth()->user()->getAttribute('role') === 'admin')
                                <form method="post" action="{{ route('backups.restore', $archive->id) }}" onsubmit="return confirm('Pemulihan akan mengganti data database saat ini. Backup pengaman akan dibuat otomatis. Lanjutkan?')">
                                    @csrf
                                    <button class="button danger small" type="submit">Pulihkan</button>
                                </form>
                            @else
                                <span class="muted">Restore khusus admin</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Belum ada backup. Buat backup pertama sebelum melakukan perubahan besar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $archives->links() }}
</section>

<section class="panel">
    <div class="page-heading">
        <div><p class="eyebrow">RIWAYAT AKTIVITAS</p><h2>Log backup &amp; pemulihan</h2></div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Status</th><th>Detail</th></tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($log->created_at)->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $log->actor_name ?? 'Pengguna tidak tersedia' }}</td>
                        <td>
                            @switch($log->action)
                                @case('backup') Membuat backup @break
                                @case('pre_restore_backup') Membuat backup pengaman @break
                                @case('restore') Memulihkan backup @break
                                @default {{ $log->action }}
                            @endswitch
                        </td>
                        <td>{{ $log->status === 'success' ? 'Berhasil' : 'Gagal' }}</td>
                        <td>{{ $log->summary }} @if($log->filename)<code>{{ $log->filename }}</code>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Belum ada aktivitas backup atau pemulihan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</section>
@endsection
