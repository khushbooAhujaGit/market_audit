{{-- Read-only visual preview of a question input for the admin preview modal --}}
@php $inputStyle = 'width:100%;border:1px solid #e5e7eb;border-radius:6px;padding:6px 9px;font-size:11px;background:#f9fafb;color:#9CA3AF;pointer-events:none;box-sizing:border-box;'; @endphp

@switch($q->question_type)
    @case('Free Text')
    @case('Subjective')
        <input type="text" data-preview-qid="{{ $q->id }}" oninput="previewCondChange(this)"
               placeholder="Type answer here..."
               style="width:100%;border:1px solid #e5e7eb;border-radius:6px;padding:6px 9px;font-size:11px;background:#f9fafb;color:#374151;box-sizing:border-box;outline:none;">
    @break

    @case('Yes / No')
        <select data-preview-qid="{{ $q->id }}" onchange="previewCondChange(this)"
                style="width:100%;border:1px solid #e5e7eb;border-radius:6px;padding:6px 9px;font-size:11px;background:#f9fafb;color:#374151;box-sizing:border-box;outline:none;">
            <option value="">Select..</option>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
        </select>
    @break

    @case('Dropdown')
        <select data-preview-qid="{{ $q->id }}" onchange="previewCondChange(this)"
                style="width:100%;border:1px solid #e5e7eb;border-radius:6px;padding:6px 9px;font-size:11px;background:#f9fafb;color:#374151;box-sizing:border-box;outline:none;">
            <option value="">Select...</option>
            @foreach($q->getOptions as $opt)
                <option value="{{ $opt->option }}">{{ $opt->option }}</option>
            @endforeach
        </select>
    @break

    @case('Multi select')
        <div style="{{ $inputStyle }}">
            @foreach($q->getOptions->take(3) as $opt)
                <div style="display:flex;align-items:center;gap:5px;padding:2px 0;"><span style="width:12px;height:12px;border:1px solid #ccc;border-radius:2px;display:inline-block;"></span>{{ $opt->option }}</div>
            @endforeach
            @if($q->getOptions->count() > 3)<div style="font-size:10px;color:#9CA3AF;margin-top:2px;">+{{ $q->getOptions->count()-3 }} more...</div>@endif
        </div>
    @break

    @case('Image')
        <div style="display:flex;gap:6px;">
            <div style="flex:1;padding:8px 6px;border:1.5px solid #2563EB;border-radius:6px;background:#EFF6FF;color:#1D4ED8;font-size:10px;font-weight:700;text-align:center;">&#128247; Camera</div>
            <div style="flex:1;padding:8px 6px;border:1.5px solid #059669;border-radius:6px;background:#F0FDF4;color:#065F46;font-size:10px;font-weight:700;text-align:center;">&#128247; Gallery</div>
        </div>
    @break

    @case('File Upload')
        <div style="{{ $inputStyle }}display:flex;align-items:center;gap:6px;">
            <span style="font-size:14px;">&#128206;</span><span>Choose file...</span>
        </div>
    @break

    @case('Date')
        <div style="{{ $inputStyle }}display:flex;align-items:center;justify-content:space-between;">
            <span>dd-mm-yyyy</span><span>&#128197;</span>
        </div>
    @break

    @case('Date & Time')
        <div style="{{ $inputStyle }}display:flex;align-items:center;justify-content:space-between;">
            <span>dd-mm-yyyy hh:mm</span><span>&#128336;</span>
        </div>
    @break

    @case('Location')
        <div style="{{ $inputStyle }}display:flex;align-items:center;gap:6px;">
            <span style="font-size:14px;">&#128205;</span><span>Get Location</span>
        </div>
    @break

    @case('Audio')
        <div style="display:flex;gap:6px;">
            <div style="flex:1;padding:7px;border:1px solid #e5e7eb;border-radius:6px;background:#f9fafb;font-size:10px;text-align:center;color:#6B7280;">&#9654; Start</div>
            <div style="flex:1;padding:7px;border:1px solid #e5e7eb;border-radius:6px;background:#f9fafb;font-size:10px;text-align:center;color:#6B7280;">&#9646;&#9646; Stop</div>
        </div>
    @break

    @case('Video')
        <div style="{{ $inputStyle }}display:flex;align-items:center;gap:6px;">
            <span style="font-size:16px;">&#127909;</span><span>Record Video</span>
        </div>
    @break

    @default
        <div style="{{ $inputStyle }}">Enter answer...</div>
@endswitch
