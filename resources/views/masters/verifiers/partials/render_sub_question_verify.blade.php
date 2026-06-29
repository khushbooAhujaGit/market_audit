{{--
    Recursive partial &#8212; read-only sub-question display for the verifier view.

    Required:
      $subQuestion  &#8212; Question model
      $depth        &#8212; nesting level (1, 2, 3 &#8230;)
      $user_responses &#8212; collection of TempUserActivityAnswersData
--}}
@php
    $sqid       = $subQuestion->id;
    $mrParentId = $mr_parent_id ?? null; // Multi Response parent ID passed from the caller

    if ($mrParentId !== null) {
        // Look up the answer stored with exactly this Multi Response parent as pctx.
        // With the new pctx-for-all system, sub-question answers use parent_context_id = mr_parent_id.
        // Fall back to any non-null pctx (older data) then to null-pctx as last resort.
        $sqAns = $user_responses->first(function ($a) use ($sqid, $mrParentId) {
                     return (int)$a->question_id === (int)$sqid
                         && (int)$a->parent_context_id === (int)$mrParentId;
                 })
              ?? $user_responses->where('question_id', $sqid)->whereNotNull('parent_context_id')->first()
              ?? $user_responses->where('question_id', $sqid)->whereNull('parent_context_id')->first();
    } else {
        $sqAns = $user_responses->where('question_id', $sqid)->first();
    }
    $sqValue = $sqAns ? $sqAns->user_answer : '';

    $depthStyles = [
        1 => ['bg' => '#f0f4ff', 'border' => '#4a6cf7', 'label' => 'Sub Question'],
        2 => ['bg' => '#f0fff4', 'border' => '#28a745', 'label' => 'Level 2'],
        3 => ['bg' => '#fff8f0', 'border' => '#fd7e14', 'label' => 'Level 3'],
    ];
    $style   = $depthStyles[min($depth, 3)];
    $indent  = $depth * 18;
@endphp

<tr style="background:{{ $style['bg'] }}; border-left:4px solid {{ $style['border'] }};">
    <td class="col-md-6 fw-medium" style="padding-left:{{ $indent + 12 }}px;">
        <small style="color:{{ $style['border'] }}; font-size:11px; font-weight:600;">
            &#8627; {{ $style['label'] }}
        </small><br>
        {{ $subQuestion->question }}
        @if($subQuestion->answer_type == 1)
            <span class="text-danger">*</span>
        @endif
    </td>
    <td class="col-md-6">
        @if($subQuestion->question_type === 'Multi Response')
            {{-- Multi Response = section header, no answer stored --}}
        @elseif(!$sqValue)
            <span class="text-muted fst-italic">&#8212; No answer &#8212;</span>
        @else
            @php
                // Check for JSON array (multi-image stored as JSON) FIRST,
                // before extension detection &#8212; pathinfo() on a JSON string gives wrong results.
                $sqImgPathsDec = json_decode($sqValue, true);
                $isJsonImgArray = is_array($sqImgPathsDec) && !empty($sqImgPathsDec);

                if (!$isJsonImgArray) {
                    $ext      = strtolower(pathinfo($sqValue, PATHINFO_EXTENSION));
                    $isImage  = in_array($ext, ['jpg','jpeg','png','gif','webp','svg']) && str_contains($sqValue, '/');
                    $isVideo  = in_array($ext, ['mp4','webm','mov','avi','wmv','mkv']) && str_contains($sqValue, '/');
                    $isAudio  = in_array($ext, ['mp3','wav','ogg','m4a','weba','webm']) && str_contains($sqValue, '/');
                    $isFile   = in_array($ext, ['pdf','xls','xlsx','doc','docx']) && str_contains($sqValue, '/');
                    $isLatLng = (bool) preg_match('/^-?\d+\.\d+,\s*-?\d+\.\d+$/', trim($sqValue));
                } else {
                    $isImage = $isVideo = $isAudio = $isFile = $isLatLng = false;
                }
            @endphp

            @if($isJsonImgArray)
                @php
                    $sqImgPaths = $sqImgPathsDec;
                    $sqIsMulti  = count($sqImgPaths) > 1;
                @endphp
                @if ($sqIsMulti)
                    {{-- Multiple images: thumbnails + ZIP download --}}
                    <div class="d-flex flex-wrap gap-2 mb-1">
                        @foreach ($sqImgPaths as $sqImg)
                            <img src="{{ asset($sqImg) }}" alt="answer"
                                 class="imagemodal"
                                 data-setval="{{ asset($sqImg) }}"
                                 style="width:72px;height:72px;object-fit:cover;border-radius:4px;border:1px solid #ddd;cursor:pointer;">
                        @endforeach
                    </div>
                    @if ($sqAns)
                        <a href="{{ route('report.download.images.zip', ['answer_id' => $sqAns->id]) }}"
                           class="btn btn-sm btn-success mt-1" style="font-size:12px;">
                            &#8615; Download ZIP ({{ count($sqImgPaths) }} images)
                        </a>
                    @endif
                @else
                    {{-- Single image stored as JSON array --}}
                    <div style="max-width:200px;">
                        <img src="{{ asset($sqImgPaths[0]) }}" alt="answer"
                             class="imagemodal"
                             data-setval="{{ asset($sqImgPaths[0]) }}"
                             style="max-width:100%;max-height:150px;border-radius:4px;border:1px solid #ddd;cursor:pointer;">
                    </div>
                    @if ($sqAns)
                        <a href="{{ asset($sqImgPaths[0]) }}" target="_blank"
                           class="btn btn-sm btn-outline-secondary mt-1" style="font-size:12px;">
                            &#8615; Download Image
                        </a>
                    @endif
                @endif
            @elseif($isImage)
                {{-- Single image stored as plain path string --}}
                <div style="max-width:200px;">
                    <img src="{{ asset($sqValue) }}" alt="answer"
                         class="imagemodal"
                         data-setval="{{ asset($sqValue) }}"
                         style="max-width:100%;max-height:150px;border-radius:4px;border:1px solid #ddd;cursor:pointer;">
                </div>
            @elseif($isVideo)
                <video controls preload="metadata" style="max-width:100%; max-height:180px; border-radius:4px;">
                    <source src="{{ asset($sqValue) }}">
                </video>
                <a href="{{ asset($sqValue) }}" download class="btn btn-sm btn-outline-secondary d-block mt-1">&#11015; Download</a>
            @elseif($isAudio)
                <audio controls style="width:100%; max-width:280px;">
                    <source src="{{ asset($sqValue) }}">
                </audio>
            @elseif($isFile)
                <a href="{{ asset($sqValue) }}" target="_blank" class="btn btn-sm btn-outline-secondary">&#128206; View File</a>
            @elseif($isLatLng)
                <input type="text" value="{{ $sqValue }}" class="form-control" readonly>
                <a href="https://www.google.com/maps?q={{ urlencode($sqValue) }}" target="_blank"
                    class="small text-primary mt-1 d-inline-block">&#128205; View on Map</a>
            @else
                <input type="text" value="{{ $sqValue }}" class="form-control" readonly>
            @endif
        @endif
    </td>
</tr>

{{-- RECURSION: if this sub-question is Outlet, render its own sub-questions --}}
@if($subQuestion->question_type === 'Multi Response' && $subQuestion->subQuestions->count() > 0)
    @foreach($subQuestion->subQuestions as $nestedLink)
        @if($nestedLink->childQuestion)
            @include('masters.verifiers.partials.render_sub_question_verify', [
                'subQuestion'    => $nestedLink->childQuestion,
                'depth'          => $depth + 1,
                'user_responses' => $user_responses,
                'mr_parent_id'   => $sqid, // nested sub-questions use this level as their pctx
            ])
        @endif
    @endforeach
@endif
