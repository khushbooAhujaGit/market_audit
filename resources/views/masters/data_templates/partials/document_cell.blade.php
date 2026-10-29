{{-- One file-column value ($doc from TemplateDocuments::present()): a thumbnail or file link when the file is there, otherwise its name and status --}}
@php
    $docIcons = ['pdf' => 'fa-file-pdf-o', 'excel' => 'fa-file-excel-o', 'word' => 'fa-file-word-o', 'image' => 'fa-file-image-o', 'file' => 'fa-file-o'];
    $docStatus = [
        'awaiting_file' => ['Waiting for file', 'bg-warning text-dark'],
        'queued'        => ['Downloading', 'bg-info text-dark'],
        'failed'        => ['Download failed', 'bg-danger'],
        'invalid'       => ['Not a file name', 'bg-secondary'],
    ];
@endphp
@if ($doc['url'])
    <a href="{{ $doc['url'] }}" target="_blank" title="{{ $doc['file_name'] }}" class="d-inline-flex align-items-center gap-1 text-decoration-none">
        @if ($doc['kind'] === 'image')
            <img src="{{ $doc['url'] }}" alt="{{ $doc['file_name'] }}" loading="lazy"
                style="width:48px;height:48px;object-fit:cover;border-radius:4px;border:1px solid #ddd;">
        @else
            <i class="fa {{ $docIcons[$doc['kind']] ?? 'fa-file-o' }} fs-5"></i>
        @endif
        <span class="small" style="word-break:break-all;">{{ \Illuminate\Support\Str::limit($doc['file_name'], 40) }}</span>
    </a>
@else
    <span class="small" style="word-break:break-all;">{{ \Illuminate\Support\Str::limit($doc['file_name'], 60) }}</span>
    @php [$statusLabel, $statusClass] = $docStatus[$doc['status']] ?? [$doc['status'], 'bg-secondary']; @endphp
    <span class="badge {{ $statusClass }}" @if ($doc['error']) title="{{ $doc['error'] }}" @endif>{{ $statusLabel }}</span>
@endif
