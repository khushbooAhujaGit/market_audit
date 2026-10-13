{{-- "Edited by verifier" note under a question whose answer a verifier changed. Needs $answerEdits, $question. --}}
@if (!empty($answerEdits) && isset($answerEdits[$question->id]))
    @php
        $edit = $answerEdits[$question->id];
        $original = $edit->original;
        $originalFiles = [];
        if ($original !== null && $original !== '' && in_array($question->question_type, ['Image', 'File Upload', 'Audio', 'Video'])) {
            $decoded = json_decode($original, true);
            $originalFiles = is_array($decoded) ? $decoded : [$original];
        }
    @endphp
    <div class="small mt-1" style="font-weight:400;color:#b45309;">
        Edited by {{ $edit->edited_by ?? 'verifier' }} on {{ optional($edit->edited_at)->format('d-m-Y H:i') }}<br>
        Original:
        @if ($original === null || $original === '')
            <em>not answered</em>
        @elseif (!empty($originalFiles))
            @foreach ($originalFiles as $file)
                @php $file = trim(urldecode((string) $file)); @endphp
                <a href="{{ preg_match('#^https?://#i', $file) ? $file : asset(ltrim($file, '/')) }}" target="_blank">file {{ $loop->iteration }}</a>{{ $loop->last ? '' : ',' }}
            @endforeach
        @else
            {{ \Illuminate\Support\Str::limit($original, 200) }}
        @endif
    </div>
@endif
