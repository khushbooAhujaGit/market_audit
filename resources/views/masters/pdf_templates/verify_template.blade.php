<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Audit PDF</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            padding: 0;
            margin: 0;
            background-color: #fff;
            color: #000;
        }

        .header {
            text-align: center;
            font-weight: bold;
            font-size: 20px;
            color: #333;
            margin: 10px 0 5px 0;
        }

        .section-title {
            font-weight: bold;
            font-size: 14px;
            background-color: #374462;
            padding: 8px;
            border: 1px solid #ccc;
            margin-top: 20px;
            margin-bottom: 10px;
            color: floralwhite;
        }

        .info-table-new {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .info-table-new th,
        .info-table-new td {
            border: 1px solid #999;
            padding: 8px;
            vertical-align: top;
        }

        .info-table-new th {
            width: 30%;
            background-color: #374462;
            text-align: left;
            color: floralwhite;
        }

        .info-table-new td {
            width: 70%;
            background-color: #f9f9f9;
            word-wrap: break-word;
        }

        .checklist-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .checklist-table th,
        .checklist-table td {
            border: 1px solid #999;
            padding: 8px;
            vertical-align: top;
        }

        .checklist-table th {
            background-color: #374462;
            color: floralwhite;
            text-align: left;
        }

        .parent-question-row {
            background-color: #ffffff;
        }

        .mr-section-row {
            background-color: #dce3f5;
        }

        .answer-text {
            line-height: 1.5;
        }

        .file-link {
            color: #007bff;
            text-decoration: underline;
            font-size: 11px;
        }
    </style>
</head>

<body>

<div class="header">
    @if(isset($main_header) && isset($sub_header) && !empty($main_header) && !empty($sub_header))
        {{ $main_header . ' - ' . $sub_header }}
    @else
        {{ $value }}
    @endif
</div>

<div class="section-title">Audit Details</div>
<table class="info-table-new">
    <tbody>
    <tr>
        <th>Auditor Name</th>
        <td>{{ $auditorInfo->getUser->name ?? '---' }}</td>
    </tr>
    <tr>
        <th>Date of Audit</th>
        <td>{{ $auditDate ?? '---' }}</td>
    </tr>
    <tr>
        <th>Time of Audit</th>
        <td>{{ $auditTime ?? '---' }}</td>
    </tr>
    </tbody>
</table>

<div class="section-title">Checklist</div>
<table class="checklist-table">
    <thead>
    <tr>
        <th style="width: 8%;">Sr. No</th>
        <th style="width: 52%;">Checklist Point</th>
        <th style="width: 40%;">Response / Remarks</th>
    </tr>
    </thead>
    <tbody>
    @php
        $index = 1;
        $subQuestionChildIds = $sub_question_child_ids ?? [];

        // Format a single answer value for PDF (inline images via public_path)
        if (!function_exists('pdfFormatAnswer')) {
        function pdfFormatAnswer($questionType, $value) {
            if ($value === null || $value === '') return null;

            $decoded = json_decode($value, true);
            $isJsonArray = is_array($decoded) && !empty($decoded);

            if ($isJsonArray) {
                $parts = [];
                foreach ($decoded as $imgPath) {
                    $abs = public_path(ltrim($imgPath, '/'));
                    if (file_exists($abs)) {
                        $parts[] = '<img src="' . $abs . '" style="max-width:150px;max-height:110px;object-fit:contain;border:1px solid #ccc;margin:2px;">';
                    } else {
                        $parts[] = '<span style="color:#888;font-style:italic;">[Image: ' . e(basename($imgPath)) . ']</span>';
                    }
                }
                return implode(' ', $parts);
            }

            switch ($questionType) {
                case 'Location':
                    return '<span style="color:#17a2b8;">' . e($value) . '</span>';
                case 'Date & Time':
                    try { return \Carbon\Carbon::parse($value)->format('d-m-Y H:i'); } catch(\Exception $e) { return e($value); }
                case 'Date':
                    try {
                        return str_contains($value, '/')
                            ? \Carbon\Carbon::createFromFormat('d/m/Y', $value)->format('d-m-Y')
                            : \Carbon\Carbon::parse($value)->format('d-m-Y');
                    } catch(\Exception $e) { return e($value); }
                case 'Multi select':
                    $vals = is_array($value) ? $value : (json_decode($value, true) ?: [$value]);
                    return e(implode(', ', array_filter($vals)));
                case 'Image':
                case 'File Upload': {
                    $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
                    $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','svg']);
                    if ($isImg) {
                        $abs = public_path(ltrim($value, '/'));
                        return file_exists($abs)
                            ? '<img src="' . $abs . '" style="max-width:180px;max-height:130px;object-fit:contain;border:1px solid #ccc;">'
                            : '<span style="color:#888;">[Image not found]</span>';
                    }
                    return '<span style="color:#555;">[File: ' . e(basename($value)) . ']</span>';
                }
                case 'Audio':
                case 'Video':
                    return '<span style="color:#555;">[' . $questionType . ': ' . e(basename($value)) . ']</span>';
                case 'Subjective': {
                    $values = is_array($value) ? $value : (json_decode($value, true) ?: [$value]);
                    $out = '';
                    foreach ($values as $v) {
                        if (empty($v)) continue;
                        $ext = strtolower(pathinfo($v, PATHINFO_EXTENSION));
                        $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp','bmp']) && str_contains($v, '/');
                        if ($isImg) {
                            $abs = public_path(ltrim($v, '/'));
                            $out .= file_exists($abs)
                                ? '<img src="' . $abs . '" style="max-width:150px;max-height:110px;object-fit:contain;border:1px solid #ccc;margin:2px;">'
                                : '<span style="color:#888;">[Image not found]</span>';
                        } elseif (str_contains($v, '/')) {
                            $out .= '<span style="color:#555;">[File: ' . e(basename($v)) . ']</span> ';
                        } else {
                            $out .= '<div class="answer-text">' . nl2br(e($v)) . '</div>';
                        }
                    }
                    return $out ?: null;
                }
                default:
                    $text = trim(is_array($value) ? implode(', ', $value) : $value);
                    return !empty($text) ? '<div class="answer-text">' . nl2br(e($text)) . '</div>' : null;
            }
        }
        } // end if (!function_exists('pdfFormatAnswer'))
    @endphp

    @foreach ($related_questions as $related_question)
        @php
            // Skip questions that are sub-question children (rendered under their parent)
            if (in_array($related_question->id, $subQuestionChildIds)) continue;

            // Skip conditional children (they have parent_question_id set, rendered after their parent)
            if ($related_question->parent_question_id) continue;

            $qType = $related_question->question_type;
        @endphp

        @if($qType === 'Multi Response')
            {{-- Section header row: no answer stored for Multi Response --}}
            <tr class="mr-section-row">
                <td style="width:8%; vertical-align:top;">{{ $index++ }}</td>
                <td colspan="2" style="width:92%; font-weight:bold; font-size:13px; color:#374462; vertical-align:top;">
                    {{ $related_question->question }}
                </td>
            </tr>
            {{-- Render sub-questions --}}
            @if($related_question->subQuestions && $related_question->subQuestions->count() > 0)
                @foreach($related_question->subQuestions as $sqLink)
                    @if($sqLink->childQuestion)
                        @include('masters.pdf_templates.partials.render_sub_question_pdf', [
                            'subQuestion'    => $sqLink->childQuestion,
                            'depth'          => 1,
                            'user_responses' => $user_responses,
                            'mr_parent_id'   => $related_question->id,
                        ])
                    @endif
                @endforeach
            @endif
        @else
            @php
                $ans = $user_responses->where('question_id', $related_question->id)->first();
                $rawValue = $ans ? $ans->user_answer : null;
                $formatted = ($rawValue !== null && $rawValue !== '') ? pdfFormatAnswer($qType, $rawValue) : null;
            @endphp
            @if($formatted !== null)
                <tr class="parent-question-row">
                    <td style="width:8%; vertical-align:top;">{{ $index++ }}</td>
                    <td style="width:52%; font-weight:bold; vertical-align:top;">{{ $related_question->question }}</td>
                    <td style="width:40%; vertical-align:top;">{!! $formatted !!}</td>
                </tr>

                {{-- Sub-questions (non-MR parent questions can still have sub-questions) --}}
                @if($related_question->subQuestions && $related_question->subQuestions->count() > 0)
                    @foreach($related_question->subQuestions as $sqLink)
                        @if($sqLink->childQuestion)
                            @include('masters.pdf_templates.partials.render_sub_question_pdf', [
                                'subQuestion'    => $sqLink->childQuestion,
                                'depth'          => 1,
                                'user_responses' => $user_responses,
                                'mr_parent_id'   => $related_question->id,
                            ])
                        @endif
                    @endforeach
                @endif

                {{-- Conditional children of this question --}}
                @php
                    $condChildren = \App\Models\Question::where('parent_question_id', $related_question->id)
                        ->orderBy('question_sequence')->get();
                @endphp
                @foreach($condChildren as $condChild)
                    @php
                        $triggerVal = $condChild->parent_value;
                        $ccAns = $user_responses->where('question_id', $condChild->id)->whereNull('parent_context_id')->first()
                              ?? $user_responses->where('question_id', $condChild->id)->first();
                        $ccValue = $ccAns ? $ccAns->user_answer : '';
                        $triggerMet = ($triggerVal === '__non_empty__' && $rawValue !== '' && $rawValue !== null)
                                   || ($triggerVal !== null && $triggerVal !== '' && $rawValue === $triggerVal);
                    @endphp
                    @if($triggerMet)
                        @php
                            $ccFormatted = ($ccValue !== '' && $ccValue !== null) ? pdfFormatAnswer($condChild->question_type, $ccValue) : null;
                        @endphp
                        <tr style="background:#f4fff7;">
                            <td style="width:8%; vertical-align:top;"></td>
                            <td style="width:52%; vertical-align:top; padding-left:26px; font-style:italic; color:#444;">
                                {{ $condChild->question }}
                                @if($condChild->answer_type == 1)<span style="color:red;">*</span>@endif
                            </td>
                            <td style="width:40%; vertical-align:top;">
                                @if($ccFormatted !== null)
                                    {!! $ccFormatted !!}
                                @else
                                    <span style="color:#aaa; font-style:italic;">—</span>
                                @endif
                            </td>
                        </tr>
                    @endif
                @endforeach
            @endif
        @endif
    @endforeach
    </tbody>
</table>

</body>

</html>
