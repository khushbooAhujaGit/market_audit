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
            height: 100vh;
            display: flex;
            flex-direction: column;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        .header {
            text-align: center;
            font-weight: bold;
            font-size: 20px;
            color: #333;
            margin: 10px 0 5px 0;
            flex-shrink: 0;
        }

        .section-title {
            font-weight: bold;
            font-size: 14px;
            background-color: #374462;
            padding: 8px;
            border: 1px solid #ccc;
            margin-top: 20px;
            margin-bottom: 10px;
            flex-shrink: 0;
            color: floralwhite;
        }

        /* Table that takes the full available height */
        .info-table-new {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            flex-grow: 1;
            /* Take remaining space */
            margin-bottom: 0;
            display: flex;
            flex-direction: column;
        }

        .info-table-new tbody {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            justify-content: space-between;
            /* Spread rows to fill vertical space */
        }

        .info-table-new tr {
            display: table;
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
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
            white-space: nowrap;
        }

        .info-table-new td {
            width: 70%;
            background-color: #f9f9f9;
            word-wrap: break-word;
        }

        .image-section {
            margin-top: 20px;
        }

        .full-page-image {
            page-break-inside: avoid;
            break-inside: avoid;
            margin-bottom: 30px;
            text-align: center;
        }

        .image-block {
            display: inline-block;
            page-break-inside: avoid;
            break-inside: avoid;
            text-align: center;
            margin-bottom: 30px;
            width: 100%;
        }

        .image-caption {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 5px;
            text-align: center;
            color: #333;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .image-block img {
            max-width: 100%;
            height: auto;
            object-fit: contain;
            border: 1px solid #ccc;
            display: block;
            margin: 0 auto;
            page-break-inside: avoid;
        }

    </style>
</head>

<body>

<div class="header">
    @if(isset($main_header) && !empty($main_header) && isset($sub_header) && !empty($sub_header))
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
<table class="info-table-new" style="margin-top: 0; flex-grow: 3;">
    <tbody>
    <tr>
        <th style="width: 10%;">Sr. No</th>
        <th style="width: 40%;">Checklist Point</th>
        <th style="width: 30%;">Response</th>
    </tr>
    @php
        $ImageArray = [];
        $count = 0;
        $index = 1;
    @endphp
    @foreach ($related_questions as $key => $related_question)
        @php

            if ($related_question->question_type == 'Subjective') {
                $d_value = [];
            }else{
                $d_value = '';
            }
//            dd($user_responses);
            foreach ($user_responses as $user_response) {
                if ($user_response->question_id == $related_question->id) {

                    if ($related_question->question_type == 'Subjective') {
                        $d_value[] = $user_response->user_answer;

                        $d_answer = $user_response->user_answer;

                        $extension = strtolower(pathinfo($d_answer, PATHINFO_EXTENSION));

                        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])){
                            $question = $related_question->question;
                            $ImageArray[$question] = $d_answer;
                        }

                    } else {
                        $d_value = $user_response->user_answer;
                    }

                }

            }
        @endphp

        @if ($related_question->question_type == 'Image')
            @php
                $question = $related_question->question;
                $ImageArray[$question] = $d_value;
            @endphp
            @continue
        @endif

        <tr>
            <td style="width: 10%;">{{ $index++ }}</td>
            <td style="width: 40%;">{{ $related_question->question }}</td>
            <td style="width: 30%;">
                @switch($related_question->question_type)
                    @case('Location')
                        @if ($d_value)
                            <a href="https://www.google.com/maps?q={{ $d_value }}" target="_blank"
                               class="text-underline text-dark">
                                Location
                            </a>
                        @endif
                        @break
                    @case('Video')
                        @if ($d_value)
                            <a class="badge badge-primary mt-2" href="{{ asset($d_value) }}">Click Here</a>
                        @endif
                        @break
                    @case('Free Text')
                    @case('Dropdown')
                    @case('Yes / No')
                        {{ $d_value }}
                        @break
                    @case('Subjective')

                        @foreach ($d_value as $d_val)
                            @php

                                $extension = strtolower(pathinfo($d_val, PATHINFO_EXTENSION));
                                $isUrlOrPath = str_contains($d_val, '/'); // crude check for file path

                                $isDate = false;
                                $isDateTime = false;
                                $parsedDate = null;

                                // Detect Date or DateTime format using Carbon
                                try {
                                    $parsedDate = \Carbon\Carbon::parse($d_val);
                                    $isDate = $parsedDate && $parsedDate->format('Y-m-d') === $d_val;
                                    $isDateTime = $parsedDate && !$isDate;
                                } catch (\Exception $e) {
                                    // Not a valid date/datetime, fallback
                                }

                            @endphp

                            @if (!$isUrlOrPath)
                                {{ $d_val }}
                            @elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                {{-- Image --}}
                                @php
                                    $question = $related_question->question;
                                    $ImageArray[$question] = $d_val;
                                @endphp
                            @elseif (in_array($extension, ['mp4', 'webm', 'ogg', 'temp']))
                                {{-- Video --}}
                                <a class="badge badge-primary mt-2" href="{{ asset($d_val) }}">Click Here</a>

                            @elseif (in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt']))
                                {{-- File --}}
                                <a class="badge badge-primary mt-2" href="{{ asset($d_val) }}">Click Here</a>

                            @elseif ($isDate)
                                {{ $d_value ? \Carbon\Carbon::createFromFormat('d/m/Y', $d_val)->format('Y-m-d') : '' }}

                            @elseif ($isDateTime)
                                @php

                                    $formattedDateTime = \Carbon\Carbon::parse(
                                        $d_val,
                                    )->format('Y-m-d\TH:i');
                                @endphp
                                {{ $formattedDateTime }}
                            @endif
                        @endforeach

                        @break
                    @case('Multi select')
                        @php
                            $selectedValues = explode(',', $d_value ?? '');
                        @endphp
                        {{ $d_value }}
                        @break
                    @case('Date & Time')
                        @php
                            $formattedDateTime = \Carbon\Carbon::parse($d_value)->format('Y-m-d\TH:i');
                        @endphp
                        {{ $formattedDateTime }}
                        @break
                    @case('Date')
                        {{ $d_value ? \Carbon\Carbon::createFromFormat('d/m/Y', $d_value)->format('Y-m-d') : '' }}
                        @break
                    @case('File Upload')
                        @if ($d_value)
                            <a class="badge badge-primary mt-2" href="{{ asset($d_value) }}">Click Here</a>
                        @endif
                        @break
                    @case('Audio')
                        <div class="audio-recorder">
                            <audio id="audio-player-{{ $related_question->id }}" controls></audio>
                        </div>
                        @break
                    @default
                @endswitch
            </td>
        </tr>
    @endforeach

    </tbody>
</table>

@if (count($ImageArray) > 0)
    @php
        $ImageArray = array_filter($ImageArray, function ($path) {
            return file_exists(public_path($path));
        });
    @endphp

    <div class="image-section">

        @foreach ($ImageArray as $key => $value)
            <div style="page-break-before: always;">
                <table style="width:100%; margin-bottom: 20px;">
                    <tr style="page-break-inside: avoid; break-inside: avoid;">
                        <td style="text-align:center; font-weight: bold; font-size: 18px; color: #374462; padding: 10px 0; background-color: #f0f0f0; border: 1px solid #ccc;">
                            {{ $key }}
                        </td>
                    </tr>
                    <tr style="page-break-inside: avoid; break-inside: avoid;">
                        <td style="text-align:center;">
                            <img src="{{ $value }}"
                                 style="max-width: 100%; max-height: 90%; display: block; margin: auto;">
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach
    </div>
@endif


</body>

</html>
