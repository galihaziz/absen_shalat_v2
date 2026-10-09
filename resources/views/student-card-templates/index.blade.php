@extends('layouts.app')

@section('title', 'Template Kartu · Rekap Absensi Solat')

@section('content')
<div class="page-heading"><div><p class="eyebrow">PENGATURAN KARTU</p><h1>Template kartu</h1></div></div>
<section class="panel student-card-template-panel">
    <div class="section-heading"><h2>Template aktif</h2>@if($templateDataUri)<span class="template-active-label">Aktif</span>@endif</div>
    <div class="student-card-template-layout">
        <div class="student-card-template-controls">
            <form class="stack-form" method="post" action="{{ route('student-card-templates.store') }}" enctype="multipart/form-data">
                @csrf
                <label>Gambar template
                    <input id="studentCardTemplateFile" type="file" name="template" accept=".jpg,.jpeg,.png,.webp" @unless($templateDataUri) required @endunless>
                </label>
                <small>JPG, PNG, atau WebP · Maksimal 5 MB</small>
                <small>Agar pas tanpa distorsi atau pemotongan, gunakan desain portrait ukuran 53,98 × 85,6 mm.</small>
                <button class="button primary" type="submit">{{ $templateDataUri ? 'Simpan template' : 'Tambah template' }}</button>
            </form>
            @if($templateDataUri)
                <form method="post" action="{{ route('student-card-templates.destroy') }}" onsubmit="return confirm('Hapus template kartu aktif?')">
                    @csrf @method('DELETE')
                    <button class="button danger" type="submit">Hapus template</button>
                </form>
            @endif
        </div>
        <div id="studentCardTemplatePreviewContainer" class="student-card-template-preview" aria-label="Pratinjau template kartu">
            <img id="studentCardTemplatePreview" src="{{ $templateDataUri ?? '' }}" alt="Pratinjau template kartu" @unless($templateDataUri) hidden @endunless>
            <span id="studentCardTemplateEmpty" @if($templateDataUri) hidden @endif>Belum ada template</span>
        </div>
    </div>
</section>
@push('scripts')
<script>
const templateFileInput = document.getElementById('studentCardTemplateFile');
const templatePreview = document.getElementById('studentCardTemplatePreview');
const templatePreviewContainer = document.getElementById('studentCardTemplatePreviewContainer');
const templateEmptyState = document.getElementById('studentCardTemplateEmpty');

const fitTemplatePreview = () => {
    if (!templatePreview.naturalWidth || !templatePreview.naturalHeight) return;

    const maxWidth = Math.min(300, templatePreviewContainer.parentElement.clientWidth);
    const maxHeight = Math.min(460, window.innerHeight * 0.6);
    const scale = Math.min(maxWidth / templatePreview.naturalWidth, maxHeight / templatePreview.naturalHeight);
    templatePreviewContainer.style.width = `${templatePreview.naturalWidth * scale}px`;
    templatePreviewContainer.style.height = `${templatePreview.naturalHeight * scale}px`;
    templatePreviewContainer.style.aspectRatio = `${templatePreview.naturalWidth} / ${templatePreview.naturalHeight}`;
};

templatePreview.addEventListener('load', fitTemplatePreview);
window.addEventListener('resize', fitTemplatePreview);
fitTemplatePreview();

templateFileInput?.addEventListener('change', () => {
    const file = templateFileInput.files?.[0];
    if (!file || !file.type.startsWith('image/')) return;

    const reader = new FileReader();
    reader.addEventListener('load', () => {
        templatePreview.src = reader.result;
        templatePreview.hidden = false;
        templateEmptyState.hidden = true;
    });
    reader.readAsDataURL(file);
});
</script>
@endpush
@endsection