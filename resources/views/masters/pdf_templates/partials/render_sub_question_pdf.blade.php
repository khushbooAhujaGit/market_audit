{{--
    PDF sub-question partial – recursively renders sub-questions for DomPDF.

    Required:
      $subQuestion    – Question model (with subQuestions.childQuestion loaded)
      $depth          – nesting level (1, 2, 3 …)
      $user_responses – collection of TempUserActivityAnswersData
      $mr_parent_id   – Multi Response parent question ID (or null)
--}}
@php
    $sqid       = $subQuestion->id;
    $mrParentId = $mr_parent_id ?? null;

    if ($mrParentId !== null) {
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

    $indentPx = 12 + $depth * 16;
    $bgColor  = $depth === 1 ? '#f4f7ff' : ($depth === 2 ? '#f4fff7' : '#fff8f4');
@endphp

@if($subQuestion->question_type === 'Multi Response')
    {{-- Multi Response sub-question: render as a sub-section header --}}
    <tr>
        <td colspan="3"
            style="padding:5px 8px 5px {{ $indentPx }}px; background:#e8ecf7; font-weight:bold; font-size:11px; color:#374462; border-top:1px solid #ccc;">
            {{ $subQuestion->question }}
        </td>
    </tr>
    @if($subQuestion->subQuestions && $subQuestion->subQuestions->count() > 0)
        @foreach($subQuestion->subQuestions as $nestedLink)
            @if($nestedLink->childQuestion)
                @include('masters.pdf_templates.partials.render_sub_question_pdf', [
                    'subQuestion'    => $nestedLink->childQuestion,
                    'depth'          => $depth + 1,
                    'user_responses' => $user_responses,
                    'mr_parent_id'   => $sqid,
                ])
            @endif
        @endforeach
    @endif
@else
    @php
        // Format the answer value for PDF inline display
        $sqDisplay = '';
        if ($sqValue !== '' && $sqValue !== null) {
            $sqType = $subQuestion->question_type ?? 'Free Text';
            $decoded = json_decode($sqValue, true);
            $isJsonArray = is_array($decoded) && !empty($decoded);

            if ($isJsonArray) {
                // Multi-image JSON array
                $parts = [];
                foreach ($decoded as $imgPath) {
                    $absPath = public_path(ltrim($imgPath, '/'));
                    if (file_exists($absPath)) {
                        $parts[] = '<img src="' . $absPath . '" style="max-width:140px;max-height:100px;object-fit:contain;border:1px solid #ccc;margin:2px;">';
                    } else {
                        $parts[] = '<span style="color:#888;font-style:italic;">[Image: ' . e(basename($imgPath)) . ']</span>';
                    }
                }
                $sqDisplay = implode(' ', $parts);
            } elseif ($sqType === 'Location') {
                $sqDisplay = '<span style="color:#17a2b8;">' . e($sqValue) . '</span>';
            } elseif ($sqType === 'Date & Time') {
                try { $sqDisplay = \Carbon\Carbon::parse($sqValue)->format('d-m-Y H:i'); } catch(\Exception $e) { $sqDisplay = e($sqValue); }
            } elseif ($sqType === 'Date') {
                try {
                    if (str_contains($sqValue, '/')) {
                        $sqDisplay = \Carbon\Carbon::createFromFormat('d/m/Y', $sqValue)->format('d-m-Y');
                    } else {
                        $sqDisplay = \Carbon\Carbon::parse($sqValue)->format('d-m-Y');
                    }
                } catch(\Exception $e) { $sqDisplay = e($sqValue); }
            } elseif ($sqType === 'Multi select') {
                $vals = is_array($sqValue) ? $sqValue : (json_decode($sqValue, true) ?: [$sqValue]);
                $sqDisplay = e(implode(', ', $vals));
            } elseif (in_array($sqType, ['Image', 'File Upload'])) {
                $ext = strtolower(pathinfo($sqValue, PATHINFO_EXTENSION));
                $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','svg']);
                if ($isImg) {
                    $absPath = public_path(ltrim($sqValue, '/'));
                    if (file_exists($absPath)) {
                        $sqDisplay = '<img src="' . $absPath . '" style="max-width:180px;max-height:130px;object-fit:contain;border:1px solid #ccc;">';
                    } else {
                        $sqDisplay = '<span style="color:#888;font-style:italic;">[Image not found]</span>';
                    }
                } else {
                    $sqDisplay = '<span style="color:#555;">[File: ' . e(basename($sqValue)) . ']</span>';
                }
            } elseif (in_array($sqType, ['Audio', 'Video'])) {
                $sqDisplay = '<span style="color:#555;">[' . $sqType . ': ' . e(basename($sqValue)) . ']</span>';
            } else {
                $sqDisplay = nl2br(e($sqValue));
            }
        }
    @endphp
    <tr style="background:{{ $bgColor }};">
        <td style="width:8%; vertical-align:top;"></td>
        <td style="width:52%; vertical-align:top; padding-left:{{ $indentPx }}px; font-style:italic; color:#444;">
            {{ $subQuestion->question }}
            @if($subQuestion->answer_type == 1)<span style="color:red;">*</span>@endif
        </td>
        <td style="width:40%; vertical-align:top;">
            @if($sqDisplay !== '')
                {!! $sqDisplay !!}
            @else
                <span style="color:#aaa; font-style:italic;">—</span>
            @endif
        </td>
    </tr>

    {{-- Nested Multi Response sub-sub-questions --}}
    @if($subQuestion->subQuestions && $subQuestion->subQuestions->count() > 0)
        @foreach($subQuestion->subQuestions as $nestedLink)
            @if($nestedLink->childQuestion)
                @include('masters.pdf_templates.partials.render_sub_question_pdf', [
                    'subQuestion'    => $nestedLink->childQuestion,
                    'depth'          => $depth + 1,
                    'user_responses' => $user_responses,
                    'mr_parent_id'   => $sqid,
                ])
            @endif
        @endforeach
    @endif

    {{-- Conditional children of this sub-question --}}
    @php
        $condChildren = \App\Models\Question::where('parent_question_id', $sqid)
            ->orderBy('question_sequence')->get();
    @endphp
    @foreach($condChildren as $condChild)
        @php
            $ccid = $condChild->id;
            $triggerVal = $condChild->parent_value;
            $ccAns = $user_responses->where('question_id', $ccid)->whereNull('parent_context_id')->first()
                  ?? $user_responses->where('question_id', $ccid)->first();
            $ccValue = $ccAns ? $ccAns->user_answer : '';
            $triggerMet = ($triggerVal === '__non_empty__' && $ccValue !== '')
                       || ($triggerVal !== null && $triggerVal !== '' && $sqValue === $triggerVal);
        @endphp
        @if($triggerMet)
            @php
                $ccIndent = $indentPx + 14;
                $ccDisplay = '';
                if ($ccValue !== '' && $ccValue !== null) {
                    $ccExt = strtolower(pathinfo($ccValue, PATHINFO_EXTENSION));
                    $ccIsImg = in_array($ccExt, ['jpg','jpeg','png','gif','webp','bmp']) && str_contains($ccValue, '/');
                    if ($ccIsImg) {
                        $ccAbs = public_path(ltrim($ccValue, '/'));
                        $ccDisplay = file_exists($ccAbs)
                            ? '<img src="' . $ccAbs . '" style="max-width:160px;max-height:120px;object-fit:contain;border:1px solid #ccc;">'
                            : '<span style="color:#888;">[Image not found]</span>';
                    } else {
                        $ccDisplay = nl2br(e($ccValue));
                    }
                }
            @endphp
            <tr style="background:#fff8f4;">
                <td style="width:8%; vertical-align:top;"></td>
                <td style="width:52%; vertical-align:top; padding-left:{{ $ccIndent }}px; font-style:italic; color:#666;">
                    {{ $condChild->question }}
                    @if($condChild->answer_type == 1)<span style="color:red;">*</span>@endif
                </td>
                <td style="width:40%; vertical-align:top;">
                    @if($ccDisplay !== '')
                        {!! $ccDisplay !!}
                    @else
                        <span style="color:#aaa; font-style:italic;">—</span>
                    @endif
                </td>
            </tr>
        @endif
    @endforeach
@endif
