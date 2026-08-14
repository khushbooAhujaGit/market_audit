@extends('template.layouts.simple.master')
@section('style')
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />


    <style>
        /* Style the Select2 container */
        .select2-container {
            font-size: 14px;
        }

        .table-avtar-new {
            height: 250px !important;
            width: 250px !important;
            position: relative;
            /* Needed for absolute positioning */
            overflow: hidden;
            /* Hide any image overflow */
        }

        .table-avtar-new img {
            border: 2px solid #374462;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 100% !important;
            height: 500px !important;
            width: auto;
        }

        .select2-container--default .select2-selection--multiple {
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 5px;
            min-height: 38px;
        }

        /* Style selected items */
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 2px 5px;
            margin: 2px;
        }

        /* Style the dropdown */
        .select2-container--default .select2-dropdown {
            border: 1px solid #ced4da;
            border-radius: 4px;
        }

        /* Placeholder styling */
        .select2-container--default .select2-selection--multiple .select2-selection__placeholder {
            color: #6c757d;
        }
    </style>
@endsection
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @php
                                    $latlongData = [];
                                    $latlongArr = [
                                        'latitude',
                                        'longitude',
                                        'Lat',
                                        'Long',
                                        'Latitude',
                                        'Longitude',
                                        'lat',
                                        'long',
                                    ];

                                    // Organize questions by parent-child relationship
                                    $sub_question_child_ids = $sub_question_child_ids ?? [];
                                    $parentQuestions = [];
                                    $childQuestions = [];

                                    foreach ($related_questions as $question) {
                                        // Skip pure sub-question children (in question_sub_questions,
                                        // no old-style conditional parent_question_id) &#8212; they are
                                        // rendered under their Multi Response parent separately.
                                        // Dual-purpose questions (sub-question AND conditional child)
                                        // must still appear here for the conditional child answer.
                                        if (in_array($question->id, $sub_question_child_ids) && empty($question->parent_question_id)) continue;
                                        if ($question->is_parent == 1 || $question->parent_question_id == 0 || empty($question->parent_question_id)) {
                                            $parentQuestions[] = $question;
                                        } else {
                                            $childQuestions[$question->parent_question_id][] = $question;
                                        }
                                    }
                                @endphp
                                <div class="col-xl-6 col-md-6">
                                    <div class="table-card">
                                        <h5>Distributor Details</h5>
                                        <table class="table mb-0">
                                            <thead>

                                            </thead>
                                            <tbody>

                                                @foreach ($related_values->templateNameValues as $r_values)
                                                    @php
                                                        $Value = trim($r_values['value']);
                                                    @endphp
                                                    @if (Str::contains($Value, $latlongArr))
                                                        @php $latlongData[] = $r_values['value']; @endphp
                                                    @endif
                                                    <tr>
                                                        <td class="fw-medium">
                                                            {{ $r_values['name'] }}</td>
                                                        <td>{{ $r_values['value'] }}</td>
                                                    </tr>
                                                @endforeach

                                            </tbody>
                                        </table>
                                        <h5 class="mt-4">Audit Details</h5>
                                        <table class="table mb-0">
                                            <thead>

                                            </thead>
                                            <tbody>

                                                <tr class="mt-2">
                                                    <td class="fw-medium">Auditor Name</td>
                                                    <td>{{ !empty($auditorData) ? $auditorData->name : '' }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-medium">Agency Name</td>
                                                    <td>{{ !empty($agencyData) ? $agencyData->name : '' }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-medium">Audit Date / Time</td>
                                                    <td>{{ $auditDateTime ?? '' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <!--end table-->
                                        @if (!empty($latlongData) || !empty($userLatLongData))
                                            <div id="map" class=""
                                                style="height: 300px; margin-top: 15px; border-radius: 8px;"></div>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-xl-6 col-md-6">
                                    <div class="card">
                                        <div class="table-card">
                                            <h5 class="mt-2 p-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                                <span>Activity Questions</span>
                                                <span class="d-flex align-items-center gap-2">
                                                    <button type="button" onclick="downloadAllImagesAsZip(this)"
                                                       class="btn btn-sm btn-success"
                                                       title="Download all image answers as ZIP">
                                                        &#11015; Download All Images
                                                    </button>
                                                    <span class="badge bg-secondary fs-6" style="cursor:pointer;" id="edit_answers">Edit</span>
                                                </span>
                                            </h5>
                                            <form method="post" action="{{ route('verifier.activityQuestionAnswers') }}"
                                                enctype="multipart/form-data">
                                                @csrf
                                                <input type="hidden" name="row_id" value="{{ $related_values->id }}">
                                                <input type="hidden" name="activity_id"
                                                    value="{{ $related_questions[0]->activity_id }}">
                                                <input type="hidden" name="activity_sequence"
                                                    value="{{ $activity_sequence ?? 0 }}">
                                                @if ($activity_group_info)
                                                    <input type="hidden" name="activity_group_id"
                                                        value="{{ $activity_group_info->id }}">
                                                @endif
                                                @if (!empty($activity_sequence) && $activity_sequence > 0)
                                                    <div class="alert d-flex align-items-center gap-2 mx-2 mt-2"
                                                        style="background:#e7f1ff; border:1.5px solid #4a6cf7; border-radius:8px; padding:10px 14px; font-size:13px;">
                                                        <span style="font-size:18px;">&#128221;</span>
                                                        <div>
                                                            <strong>Additional Submission{{ !empty($instance_label) ? ': ' . $instance_label : '' }}</strong><br>
                                                            <span class="text-muted" style="font-size:12px;">
                                                                You are verifying a repeat instance &#8212; not the original submission.
                                                            </span>
                                                        </div>
                                                    </div>
                                                @endif

                                                <div class="table-responsive">
                                                    <table class="table mb-0">
                                                        <tbody>
                                                            @php
                                                                $selectArrIds = [];
                                                            @endphp

                                                            @foreach ($parentQuestions as $parentQuestion)
                                                                @php
                                                                    // Get parent question answer
                                                                    $parentAnswer = '';
                                                                    $parentValues = []; // all answers for this question
                                                                    $subjectSelected = null; // first answer = subject name
                                                                    $subjectDepAnswers = []; // subsequent answers = dependent values

                                                                    foreach ($user_responses as $response) {
                                                                        if (
                                                                            $response->question_id ==
                                                                            $parentQuestion->id
                                                                        ) {
                                                                            if (
                                                                                $parentQuestion->question_type ==
                                                                                'Subjective'
                                                                            ) {
                                                                                $parentValues[] = [
                                                                                    'id' => $response->id,
                                                                                    'answer' => $response->user_answer,
                                                                                ];
                                                                            } else {
                                                                                // For dual-purpose questions prefer the null-context
                                                                                // answer (conditional/standalone) in the main loop
                                                                                if ($response->parent_context_id === null) {
                                                                                    $parentAnswer = $response->user_answer;
                                                                                    break;
                                                                                }
                                                                                // Fallback to any answer if no null-context answer exists
                                                                                if (!$parentAnswer) {
                                                                                    $parentAnswer = $response->user_answer;
                                                                                }
                                                                            }
                                                                        }
                                                                    }

                                                                    // Sort by DB id ascending so subject comes first
                                                                    if (!empty($parentValues)) {
                                                                        usort(
                                                                            $parentValues,
                                                                            fn($a, $b) => $a['id'] <=> $b['id'],
                                                                        );
                                                                        $subjectSelected = $parentValues[0]['answer'];
                                                                        $subjectDepAnswers = array_column(
                                                                            array_slice($parentValues, 1),
                                                                            'answer',
                                                                        );
                                                                    }
                                                                @endphp

                                                                <!-- Parent Question Row -->
                                                                <tr class="parent-question-row">
                                                                    <td class="col-md-6 fw-medium">
                                                                        {{ $parentQuestion->question }}
                                                                        @if ($parentQuestion->answer_type == 1)
                                                                            <span class="text-danger">*</span>
                                                                        @endif
                                                                    </td> 
                                                                    <td class="col-md-6">
                                                                        @switch($parentQuestion->question_type)
                                                                            @case('Multi Response')
                                                                                {{-- Multi Response = section, no answer stored --}}
                                                                            @break

                                                                            @case('Location')
                                                                                <input name="{{ $parentQuestion->id }}"
                                                                                    value="{{ $parentAnswer }}" type="text"
                                                                                    class="form-control" disabled>
                                                                                @if ($parentAnswer)
                                                                                    <a href="https://www.google.com/maps?q={{ $parentAnswer }}"
                                                                                        target="_blank"
                                                                                        class="text-underline text-dark">Location</a>
                                                                                @endif
                                                                            @break

                                                                            @case('Image')
                                                                                @if ($parentAnswer)
                                                                                    @php
                                                                                        $imgPaths     = json_decode($parentAnswer, true);
                                                                                        $isMultiImg   = is_array($imgPaths) && count($imgPaths) > 1;
                                                                                        if (!is_array($imgPaths)) $imgPaths = [$parentAnswer];
                                                                                        // Find the correct answer record ID for this question
                                                                                        $imgAnswerId  = null;
                                                                                        foreach ($user_responses as $_r) {
                                                                                            if ($_r->question_id == $parentQuestion->id) {
                                                                                                $imgAnswerId = $_r->id; break;
                                                                                            }
                                                                                        }
                                                                                    @endphp
                                                                                    @if ($isMultiImg)
                                                                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                                                                            @foreach ($imgPaths as $imgP)
                                                                                                <div class="d-flex flex-column align-items-center" style="gap:4px;">
                                                                                                    <img class="imagemodal" alt="Image"
                                                                                                         src="{{ asset($imgP) }}"
                                                                                                         data-setval="{{ asset($imgP) }}"
                                                                                                         data-verifier-img="{{ asset($imgP) }}"
                                                                                                         style="width:72px;height:72px;object-fit:cover;border-radius:6px;border:1px solid #ccc;cursor:pointer;">
                                                                                                    <a href="{{ asset($imgP) }}" download
                                                                                                       class="btn btn-xs btn-outline-secondary"
                                                                                                       style="font-size:10px;padding:1px 5px;">&#11015;</a>
                                                                                                </div>
                                                                                            @endforeach
                                                                                        </div>
                                                                                        @if ($imgAnswerId)
                                                                                            <a href="{{ route('report.download.images.zip', ['answer_id' => $imgAnswerId]) }}"
                                                                                               class="btn btn-sm btn-success mt-1"
                                                                                               style="font-size:12px;" title="Download all images as ZIP">
                                                                                                &#11015; Download ZIP ({{ count($imgPaths) }} images)
                                                                                            </a>
                                                                                        @endif
                                                                                    @else
                                                                                        <div class="table-avtar-new mb-3">
                                                                                            <img class="imagemodal" alt="Image"
                                                                                                src="{{ asset($imgPaths[0]) }}"
                                                                                                data-setval="{{ asset($imgPaths[0]) }}"
                                                                                                data-verifier-img="{{ asset($imgPaths[0]) }}">
                                                                                        </div>
                                                                                        <a href="{{ asset($imgPaths[0]) }}" download
                                                                                           class="btn btn-sm btn-outline-secondary mt-1"
                                                                                           style="font-size:12px;">&#11015; Download Image</a>
                                                                                    @endif
                                                                                @endif
                                                                                <input name="{{ $parentQuestion->id }}"
                                                                                    type="file" class="form-control" disabled>
                                                                            @break

                                                                            @case('Video')
                                                                                @php
                                                                                    $cleanPath = trim(
                                                                                        urldecode($parentAnswer),
                                                                                    );
                                                                                    if (
                                                                                        str_starts_with(
                                                                                            $cleanPath,
                                                                                            'http://',
                                                                                        ) ||
                                                                                        str_starts_with(
                                                                                            $cleanPath,
                                                                                            'https://',
                                                                                        )
                                                                                    ) {
                                                                                        $fileUrl = $cleanPath;
                                                                                    } else {
                                                                                        $cleanPath = ltrim(
                                                                                            $cleanPath,
                                                                                            '/',
                                                                                        );
                                                                                        $fileUrl = asset($cleanPath);
                                                                                    }
                                                                                    $jsFileUrl = htmlspecialchars(
                                                                                        $fileUrl,
                                                                                        ENT_QUOTES,
                                                                                        'UTF-8',
                                                                                    );
                                                                                @endphp
                                                                                @if ($parentAnswer)
                                                                                    <a class="badge badge-primary mt-2 imagemodal"
                                                                                        data-setval="{{ $jsFileUrl }}"
                                                                                        style="cursor:pointer;">Click
                                                                                        Here</a>
                                                                                @endif
                                                                            @break

                                                                            @case('Free Text')
                                                                                <input type="text" value="{{ $parentAnswer }}"
                                                                                    name="{{ $parentQuestion->id }}"
                                                                                    class="form-control" readonly>
                                                                            @break

                                                                            @case('Yes / No')
                                                                                <select class="form-select"
                                                                                    name="{{ $parentQuestion->id }}" disabled>
                                                                                    <option value="">Select ..</option>
                                                                                    <option value="Yes"
                                                                                        @if ('Yes' == $parentAnswer) selected @endif>
                                                                                        Yes
                                                                                    </option>
                                                                                    <option value="No"
                                                                                        @if ('No' == $parentAnswer) selected @endif>
                                                                                        No
                                                                                    </option>
                                                                                </select>
                                                                            @break

                                                                            @case('Dropdown')
                                                                                <select class="form-select"
                                                                                    name="{{ $parentQuestion->id }}" disabled>
                                                                                    <option value="">Select ..</option>
                                                                                    @foreach ($parentQuestion->getOptions as $questionOption)
                                                                                        <option
                                                                                            value="{{ $questionOption->option }}"
                                                                                            @if ($questionOption->option == $parentAnswer) selected @endif>
                                                                                            {{ $questionOption->option }}
                                                                                        </option>
                                                                                    @endforeach
                                                                                </select>
                                                                            @break

                                                                            @case('Multi select')
                                                                                @php
                                                                                    $selectArrIds[] =
                                                                                        'select' . $parentQuestion->id;
                                                                                    $selectedValues = explode(
                                                                                        ',',
                                                                                        $parentAnswer ?? '',
                                                                                    );
                                                                                @endphp
                                                                                <select class="form-select"
                                                                                    name="{{ $parentQuestion->id }}[]"
                                                                                    id="select{{ $parentQuestion->id }}" multiple
                                                                                    disabled>
                                                                                    <option value="">Select ..</option>
                                                                                    @foreach ($parentQuestion->getOptions as $questionOption)
                                                                                        <option
                                                                                            value="{{ $questionOption->option }}"
                                                                                            @if (in_array($questionOption->option, $selectedValues)) selected @endif>
                                                                                            {{ $questionOption->option }}
                                                                                        </option>
                                                                                    @endforeach
                                                                                </select>
                                                                            @break

                                                                            @case('Date & Time')
                                                                                @php
                                                                                    $formattedDateTime = '';
                                                                                    if (!empty($parentAnswer)) {
                                                                                        try {
                                                                                            $datetimeFormats = [
                                                                                                'Y-m-d H:i:s',
                                                                                                'Y-m-d H:i',
                                                                                                'd/m/Y H:i:s',
                                                                                                'd/m/Y H:i',
                                                                                                'm/d/Y H:i:s',
                                                                                                'm/d/Y H:i',
                                                                                                'Y-m-d\TH:i:s',
                                                                                                'Y-m-d\TH:i',
                                                                                            ];

                                                                                            $parsed = false;
                                                                                            foreach (
                                                                                                $datetimeFormats
                                                                                                as $format
                                                                                            ) {
                                                                                                try {
                                                                                                    $carbonDate = \Carbon\Carbon::createFromFormat(
                                                                                                        $format,
                                                                                                        $parentAnswer,
                                                                                                    );
                                                                                                    if (
                                                                                                        $carbonDate !==
                                                                                                        false
                                                                                                    ) {
                                                                                                        $formattedDateTime = $carbonDate->format(
                                                                                                            'Y-m-d\TH:i',
                                                                                                        );
                                                                                                        $parsed = true;
                                                                                                        break;
                                                                                                    }
                                                                                                } catch (\Exception $e) {
                                                                                                    continue;
                                                                                                }
                                                                                            }

                                                                                            if (!$parsed) {
                                                                                                try {
                                                                                                    $formattedDateTime = \Carbon\Carbon::parse(
                                                                                                        $parentAnswer,
                                                                                                    )->format(
                                                                                                        'Y-m-d\TH:i',
                                                                                                    );
                                                                                                } catch (\Exception $e) {
                                                                                                    $formattedDateTime = $parentAnswer;
                                                                                                }
                                                                                            }
                                                                                        } catch (\Exception $e) {
                                                                                            $formattedDateTime = $parentAnswer;
                                                                                        }
                                                                                    }
                                                                                @endphp
                                                                                <input type="datetime-local"
                                                                                    name="{{ $parentQuestion->id }}"
                                                                                    value="{{ $formattedDateTime }}"
                                                                                    class="form-control" readonly>
                                                                            @break

                                                                            @case('Date')
                                                                                @php
                                                                                    $formattedDate = '';
                                                                                    if (!empty($parentAnswer)) {
                                                                                        try {
                                                                                            // Try common date formats
                                                                                            $dateFormats = [
                                                                                                'd/m/Y',
                                                                                                'Y-m-d',
                                                                                                'm/d/Y',
                                                                                                'd-m-Y',
                                                                                                'Y/m/d',
                                                                                                'm-d-Y',
                                                                                                'd M Y',
                                                                                                'M d Y',
                                                                                                'd F Y',
                                                                                                'F d Y',
                                                                                            ];

                                                                                            $parsed = false;
                                                                                            foreach (
                                                                                                $dateFormats
                                                                                                as $format
                                                                                            ) {
                                                                                                try {
                                                                                                    $carbonDate = \Carbon\Carbon::createFromFormat(
                                                                                                        $format,
                                                                                                        $parentAnswer,
                                                                                                    );
                                                                                                    if (
                                                                                                        $carbonDate !==
                                                                                                        false
                                                                                                    ) {
                                                                                                        $formattedDate = $carbonDate->format(
                                                                                                            'Y-m-d',
                                                                                                        );
                                                                                                        $parsed = true;
                                                                                                        break;
                                                                                                    }
                                                                                                } catch (\Exception $e) {
                                                                                                    continue;
                                                                                                }
                                                                                            }

                                                                                            // If none of the formats worked, try parsing with Carbon's automatic parser
        if (!$parsed) {
            try {
                $formattedDate = \Carbon\Carbon::parse(
                    $parentAnswer,
                )->format('Y-m-d');
                                                                                                } catch (\Exception $e) {
                                                                                                    // If all parsing fails, just display the original value
                                                                                                    $formattedDate = $parentAnswer;
                                                                                                }
                                                                                            }
                                                                                        } catch (\Exception $e) {
                                                                                            $formattedDate = $parentAnswer;
                                                                                        }
                                                                                    }
                                                                                @endphp
                                                                                <input type="date" value="{{ $formattedDate }}"
                                                                                    name="{{ $parentQuestion->id }}"
                                                                                    class="form-control" readonly>
                                                                            @break

                                                                            @case('File Upload')
                                                                                <input type="file"
                                                                                    name="{{ $parentQuestion->id }}"
                                                                                    class="form-control" disabled>
                                                                                @if ($parentAnswer)
                                                                                    <a class="badge badge-primary mt-2"
                                                                                        href="{{ asset($parentAnswer) }}">Click
                                                                                        Here</a>
                                                                                @endif
                                                                            @break

                                                                            @case('Subjective')
                                                                                {{-- Row 1: the subject that was selected --}}
                                                                                @if ($subjectSelected)
                                                                                    <div class="mb-2">
                                                                                        <small
                                                                                            class="text-muted d-block mb-1">Subject
                                                                                            Selected</small>
                                                                                        <select class="form-select" disabled>
                                                                                            <option selected>{{ $subjectSelected }}
                                                                                            </option>
                                                                                        </select>
                                                                                    </div>
                                                                                @endif

                                                                                {{-- Row 2+: the dependent answer(s) --}}
                                                                                @foreach ($subjectDepAnswers as $depAnsIndex => $depAns)
                                                                                    @php
                                                                                        // Get the label for this dependent answer from the subject config
                                                                                        // $subjectSelected = the subject name chosen (e.g. "L2")
                                                                                        // We look it up in $parentQuestion->getSubjects to find answer_type
                                                                                        $depLabel = 'Answer'; // fallback
                                                                                        $depAnswerType = null;

                                                                                        if (
                                                                                            $parentQuestion->relationLoaded(
                                                                                                'getSubjects',
                                                                                            ) ||
                                                                                            method_exists(
                                                                                                $parentQuestion,
                                                                                                'getSubjects',
                                                                                            )
                                                                                        ) {
                                                                                            foreach (
                                                                                                $parentQuestion->getSubjects
                                                                                                as $subjectConfig
                                                                                            ) {
                                                                                                if (
                                                                                                    $subjectConfig->subject ===
                                                                                                    $subjectSelected
                                                                                                ) {
                                                                                                    $depAnswerType =
                                                                                                        $subjectConfig->answer_type;
                                                                                                    // Use a human-readable label based on answer_type
                                                                                                    $labelMap = [
                                                                                                        'Free Text' =>
                                                                                                            'Text Answer',
                                                                                                        'Date' =>
                                                                                                            'Date',
                                                                                                        'Date & Time' =>
                                                                                                            'Date & Time',
                                                                                                        'Yes / No' =>
                                                                                                            'Yes / No',
                                                                                                        'Dropdown' =>
                                                                                                            'Selected Option',
                                                                                                        'Multi select' =>
                                                                                                            'Selected Options',
                                                                                                        'Image' =>
                                                                                                            'Image',
                                                                                                        'File Upload' =>
                                                                                                            'File',
                                                                                                        'Audio' =>
                                                                                                            'Audio Recording',
                                                                                                        'Video' =>
                                                                                                            'Video Recording',
                                                                                                        'Location' =>
                                                                                                            'Location',
                                                                                                    ];
                                                                                                    $depLabel =
                                                                                                        $labelMap[
                                                                                                            $depAnswerType
                                                                                                        ] ??
                                                                                                        ($depAnswerType ??
                                                                                                            'Answer');
                                                                                                    break;
                                                                                                }
                                                                                            }
                                                                                        }

                                                                                        // Auto-detect rendering type from content
                                                                                        $depExt = strtolower(
                                                                                            pathinfo(
                                                                                                $depAns,
                                                                                                PATHINFO_EXTENSION,
                                                                                            ),
                                                                                        );
                                                                                        $isBase64 = str_starts_with(
                                                                                            $depAns,
                                                                                            'data:',
                                                                                        );
                                                                                        $isImageFile =
                                                                                            !$isBase64 &&
                                                                                            in_array($depExt, [
                                                                                                'jpg',
                                                                                                'jpeg',
                                                                                                'png',
                                                                                                'gif',
                                                                                                'webp',
                                                                                            ]) &&
                                                                                            str_contains($depAns, '/');
                                                                                        $isVideoFile =
                                                                                            !$isBase64 &&
                                                                                            in_array($depExt, [
                                                                                                'mp4',
                                                                                                'webm',
                                                                                                'mov',
                                                                                                'avi',
                                                                                                'wmv',
                                                                                                'flv',
                                                                                                'mkv',
                                                                                                'temp',
                                                                                            ]) &&
                                                                                            str_contains($depAns, '/');
                                                                                        $isAudioFile =
                                                                                            !$isBase64 &&
                                                                                            in_array($depExt, [
                                                                                                'mp3',
                                                                                                'wav',
                                                                                                'ogg',
                                                                                                'm4a',
                                                                                                'weba',
                                                                                                'webm',
                                                                                            ]) &&
                                                                                            str_contains($depAns, '/');
                                                                                        $isBase64Audio =
                                                                                            $isBase64 &&
                                                                                            str_starts_with(
                                                                                                $depAns,
                                                                                                'data:audio/',
                                                                                            );
                                                                                        $isBase64Video =
                                                                                            $isBase64 &&
                                                                                            str_starts_with(
                                                                                                $depAns,
                                                                                                'data:video/',
                                                                                            );
                                                                                        $isLatLong =
                                                                                            !$isBase64 &&
                                                                                            (bool) preg_match(
                                                                                                '/^-?\d{1,3}\.\d+,\s*-?\d{1,3}\.\d+$/',
                                                                                                trim($depAns),
                                                                                            );
                                                                                    @endphp

                                                                                    <div class="mb-2 mt-2">
                                                                                        {{-- Dependent answer label --}}
                                                                                        <small
                                                                                            class="text-muted d-block mb-1 fw-semibold">{{ $depLabel }}</small>

                                                                                        @if ($isImageFile)
                                                                                            <div class="mb-2"
                                                                                                style="max-width:200px;">
                                                                                                <img class="imagemodal img-fluid rounded border"
                                                                                                    src="{{ asset($depAns) }}"
                                                                                                    data-setval="{{ asset($depAns) }}"
                                                                                                    alt="{{ $depLabel }}"
                                                                                                    style="cursor:pointer; max-height:150px; object-fit:contain;">
                                                                                            </div>
                                                                                        @elseif ($isVideoFile)
                                                                                            @php
                                                                                                $vPath = ltrim(
                                                                                                    $depAns,
                                                                                                    '/',
                                                                                                );
                                                                                                $vExt = strtolower(
                                                                                                    pathinfo(
                                                                                                        $vPath,
                                                                                                        PATHINFO_EXTENSION,
                                                                                                    ),
                                                                                                );
                                                                                                $vUrl = asset($vPath);
                                                                                                $vMime =
                                                                                                    [
                                                                                                        'mp4' =>
                                                                                                            'video/mp4',
                                                                                                        'webm' =>
                                                                                                            'video/webm',
                                                                                                        'mov' =>
                                                                                                            'video/quicktime',
                                                                                                        'avi' =>
                                                                                                            'video/x-msvideo',
                                                                                                    ][$vExt] ??
                                                                                                    'video/mp4';
                                                                                            @endphp
                                                                                            <video controls preload="metadata"
                                                                                                playsinline
                                                                                                style="max-width:100%; max-height:200px; border-radius:4px;"
                                                                                                class="d-block border">
                                                                                                <source src="{{ $vUrl }}"
                                                                                                    type="{{ $vMime }}">
                                                                                                <source src="{{ $vUrl }}"
                                                                                                    type="video/webm">
                                                                                            </video>
                                                                                            <a href="{{ $vUrl }}" download
                                                                                                class="btn btn-sm btn-outline-secondary mt-1">&#11015;
                                                                                                Download</a>
                                                                                        @elseif ($isAudioFile)
                                                                                            @php
                                                                                                $aPath = ltrim(
                                                                                                    $depAns,
                                                                                                    '/',
                                                                                                );
                                                                                                $aExt = strtolower(
                                                                                                    pathinfo(
                                                                                                        $aPath,
                                                                                                        PATHINFO_EXTENSION,
                                                                                                    ),
                                                                                                );
                                                                                                $aUrl = asset($aPath);
                                                                                                $aMime =
                                                                                                    [
                                                                                                        'mp3' =>
                                                                                                            'audio/mpeg',
                                                                                                        'wav' =>
                                                                                                            'audio/wav',
                                                                                                        'ogg' =>
                                                                                                            'audio/ogg',
                                                                                                        'm4a' =>
                                                                                                            'audio/mp4',
                                                                                                        'webm' =>
                                                                                                            'audio/webm',
                                                                                                    ][$aExt] ??
                                                                                                    'audio/webm';
                                                                                            @endphp
                                                                                            <audio controls
                                                                                                style="width:100%; max-width:280px;"
                                                                                                class="d-block">
                                                                                                <source src="{{ $aUrl }}"
                                                                                                    type="{{ $aMime }}">
                                                                                                <source src="{{ $aUrl }}"
                                                                                                    type="audio/webm">
                                                                                            </audio>
                                                                                            <a href="{{ $aUrl }}" download
                                                                                                class="btn btn-sm btn-outline-secondary mt-1">&#11015;
                                                                                                Download Audio</a>
                                                                                        @elseif ($isBase64Audio)
                                                                                            @php
                                                                                                preg_match(
                                                                                                    '/^data:(audio\/[a-zA-Z0-9\-]+)/',
                                                                                                    $depAns,
                                                                                                    $bm,
                                                                                                );
                                                                                                $b64Mime = explode(
                                                                                                    ';',
                                                                                                    $bm[1] ??
                                                                                                        'audio/webm',
                                                                                                )[0];
                                                                                            @endphp
                                                                                            <audio controls
                                                                                                style="width:100%; max-width:280px;"
                                                                                                class="d-block">
                                                                                                <source src="{{ $depAns }}"
                                                                                                    type="{{ $b64Mime }}">
                                                                                                <source src="{{ $depAns }}"
                                                                                                    type="audio/webm">
                                                                                            </audio>
                                                                                        @elseif ($isBase64Video)
                                                                                            @php
                                                                                                preg_match(
                                                                                                    '/^data:(video\/[a-zA-Z0-9\-]+)/',
                                                                                                    $depAns,
                                                                                                    $bm,
                                                                                                );
                                                                                                $b64Mime = explode(
                                                                                                    ';',
                                                                                                    $bm[1] ??
                                                                                                        'video/webm',
                                                                                                )[0];
                                                                                            @endphp
                                                                                            <video controls preload="metadata"
                                                                                                style="max-width:100%; max-height:200px;"
                                                                                                class="d-block rounded border">
                                                                                                <source src="{{ $depAns }}"
                                                                                                    type="{{ $b64Mime }}">
                                                                                                <source src="{{ $depAns }}"
                                                                                                    type="video/webm">
                                                                                            </video>
                                                                                        @elseif ($isLatLong)
                                                                                            <input type="text"
                                                                                                value="{{ $depAns }}"
                                                                                                class="form-control" readonly>
                                                                                            <a href="https://www.google.com/maps?q={{ urlencode($depAns) }}"
                                                                                                target="_blank"
                                                                                                class="small text-primary mt-1 d-inline-block">&#128205;
                                                                                                View on Map</a>
                                                                                        @else
                                                                                            {{-- Text / Date / DateTime / Yes-No / Dropdown &#8212;&#8212; plain readonly input --}}
                                                                                            <input type="text"
                                                                                                value="{{ $depAns }}"
                                                                                                class="form-control" readonly>
                                                                                        @endif
                                                                                    </div>
                                                                                @endforeach

                                                                                @if (!$subjectSelected)
                                                                                    <span class="text-muted small">No answer
                                                                                        recorded</span>
                                                                                @endif
                                                                            @break

                                                                            @default
                                                                                <input type="text" value="{{ $parentAnswer }}"
                                                                                    class="form-control"
                                                                                    name="{{ $parentQuestion->id }}" readonly>
                                                                        @endswitch
                                                                    </td>
                                                                </tr>

                                                                <!-- Child Questions - Display directly under parent -->
                                                                @if (isset($childQuestions[$parentQuestion->id]) && !empty($childQuestions[$parentQuestion->id]))
                                                                    @foreach ($childQuestions[$parentQuestion->id] as $childQuestion)
                                                                        @php
                                                                            // Get child question answer &#8212; for dual-purpose questions
                                                                            // Get conditional child answer.
                                                                            // New format: stored with parent_context_id = parent_question_id.
                                                                            // Backward compat: old answers stored with parent_context_id = NULL.
                                                                            $childAnswer = '';
                                                                            $childValues = [];
                                                                            $cPctxId = $childQuestion->parent_question_id;
                                                                            foreach ($user_responses as $response) {
                                                                                if ($response->question_id == $childQuestion->id) {
                                                                                    if ($childQuestion->question_type == 'Subjective') {
                                                                                        $childValues[] = $response->user_answer;
                                                                                    } else {
                                                                                        // New format: pctx matches triggering parent
                                                                                        if ($cPctxId && (int)$response->parent_context_id === (int)$cPctxId) {
                                                                                            $childAnswer = $response->user_answer;
                                                                                            break;
                                                                                        }
                                                                                        // Old format fallback: null pctx
                                                                                        if ($response->parent_context_id === null && !$childAnswer) {
                                                                                            $childAnswer = $response->user_answer;
                                                                                        }
                                                                                    }
                                                                                }
                                                                            }
                                                                        @endphp
                                                                        @if ($childAnswer)
                                                                            <tr class="child-question-row">
                                                                                <td class="col-md-6 fw-medium">
                                                                                    {{ $childQuestion->question }}
                                                                                    @if ($childQuestion->answer_type == 1)
                                                                                        <span class="text-danger">*</span>
                                                                                    @endif
                                                                                </td>
                                                                                <td class="col-md-6">
                                                                                    @if ($childAnswer)
                                                                                        @switch($childQuestion->question_type)
                                                                                            @case('Location')
                                                                                                <input
                                                                                                    name="{{ $childQuestion->id }}"
                                                                                                    value="{{ $childAnswer }}"
                                                                                                    type="text"
                                                                                                    class="form-control" disabled>
                                                                                                @if ($childAnswer)
                                                                                                    <a href="https://www.google.com/maps?q={{ $childAnswer }}"
                                                                                                        target="_blank"
                                                                                                        class="text-underline text-dark">Location</a>
                                                                                                @endif
                                                                                            @break

                                                                                            @case('Image')
                                                                                                @if ($childAnswer)
                                                                                                    @php
                                                                                                        $cImgPaths   = json_decode($childAnswer, true);
                                                                                                        $cIsMulti    = is_array($cImgPaths) && count($cImgPaths) > 1;
                                                                                                        if (!is_array($cImgPaths)) $cImgPaths = [$childAnswer];
                                                                                                        $cImgAnswerId = null;
                                                                                                        foreach ($user_responses as $_r) {
                                                                                                            if ($_r->question_id == $childQuestion->id) {
                                                                                                                $cImgAnswerId = $_r->id; break;
                                                                                                            }
                                                                                                        }
                                                                                                    @endphp
                                                                                                    @if ($cIsMulti)
                                                                                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                                                                                            @foreach ($cImgPaths as $cImg)
                                                                                                                <div class="d-flex flex-column align-items-center" style="gap:4px;">
                                                                                                                    <img class="imagemodal" alt="Image"
                                                                                                                         src="{{ asset($cImg) }}"
                                                                                                                         data-setval="{{ asset($cImg) }}"
                                                                                                                         data-verifier-img="{{ asset($cImg) }}"
                                                                                                                         style="width:72px;height:72px;object-fit:cover;border-radius:6px;border:1px solid #ccc;cursor:pointer;">
                                                                                                                    <a href="{{ asset($cImg) }}" download
                                                                                                                       class="btn btn-xs btn-outline-secondary"
                                                                                                                       style="font-size:10px;padding:1px 5px;">&#11015;</a>
                                                                                                                </div>
                                                                                                            @endforeach
                                                                                                        </div>
                                                                                                        @if ($cImgAnswerId)
                                                                                                            <a href="{{ route('report.download.images.zip', ['answer_id' => $cImgAnswerId]) }}"
                                                                                                               class="btn btn-sm btn-success mt-1"
                                                                                                               style="font-size:12px;">
                                                                                                                &#11015; Download ZIP ({{ count($cImgPaths) }} images)
                                                                                                            </a>
                                                                                                        @endif
                                                                                                    @else
                                                                                                        <div class="table-avtar-new mb-3">
                                                                                                            <img class="imagemodal" alt="Image"
                                                                                                                src="{{ asset($cImgPaths[0]) }}"
                                                                                                                data-setval="{{ asset($cImgPaths[0]) }}"
                                                                                                                data-verifier-img="{{ asset($cImgPaths[0]) }}">
                                                                                                        </div>
                                                                                                        <a href="{{ asset($cImgPaths[0]) }}" download
                                                                                                           class="btn btn-sm btn-outline-secondary mt-1"
                                                                                                           style="font-size:12px;">&#11015; Download Image</a>
                                                                                                    @endif
                                                                                                @endif
                                                                                                <input
                                                                                                    name="{{ $childQuestion->id }}"
                                                                                                    type="file"
                                                                                                    class="form-control" disabled>
                                                                                            @break

                                                                                            @case('Video')
                                                                                                @if ($childAnswer)
                                                                                                    @php
                                                                                                        $videoPath = ltrim(
                                                                                                            trim(
                                                                                                                urldecode(
                                                                                                                    $childAnswer,
                                                                                                                ),
                                                                                                            ),
                                                                                                            '/',
                                                                                                        );
                                                                                                        $videoExt = strtolower(
                                                                                                            pathinfo(
                                                                                                                $videoPath,
                                                                                                                PATHINFO_EXTENSION,
                                                                                                            ),
                                                                                                        );
                                                                                                        $videoUrl = asset(
                                                                                                            $videoPath,
                                                                                                        );
                                                                                                        $jsVideoUrl = htmlspecialchars(
                                                                                                            $videoUrl,
                                                                                                            ENT_QUOTES,
                                                                                                            'UTF-8',
                                                                                                        );
                                                                                                        $mimeMap = [
                                                                                                            'mp4' =>
                                                                                                                'video/mp4',
                                                                                                            'webm' =>
                                                                                                                'video/webm',
                                                                                                            'ogg' =>
                                                                                                                'video/ogg',
                                                                                                            'mov' =>
                                                                                                                'video/quicktime',
                                                                                                            'avi' =>
                                                                                                                'video/x-msvideo',
                                                                                                            'wmv' =>
                                                                                                                'video/x-ms-wmv',
                                                                                                        ];
                                                                                                        $videoMime =
                                                                                                            $mimeMap[
                                                                                                                $videoExt
                                                                                                            ] ??
                                                                                                            'video/mp4';
                                                                                                    @endphp
                                                                                                    <video controls
                                                                                                        style="max-width:100%; max-height:220px; border-radius:4px;"
                                                                                                        class="d-block mt-1 border"
                                                                                                        preload="metadata"
                                                                                                        playsinline>
                                                                                                        <source
                                                                                                            src="{{ $videoUrl }}"
                                                                                                            type="{{ $videoMime }}">
                                                                                                        <source
                                                                                                            src="{{ $videoUrl }}"
                                                                                                            type="video/webm">
                                                                                                        Your browser does not
                                                                                                        support video playback.
                                                                                                    </video>
                                                                                                    <div
                                                                                                        class="mt-1 d-flex gap-1 flex-wrap">
                                                                                                        <a href="{{ $videoUrl }}"
                                                                                                            download
                                                                                                            class="btn btn-sm btn-outline-secondary">
                                                                                                            &#11015; Download
                                                                                                        </a>
                                                                                                        <a class="btn btn-sm btn-outline-primary imagemodal"
                                                                                                            data-setval="{{ $jsVideoUrl }}"
                                                                                                            style="cursor:pointer;">
                                                                                                            &#9974; Fullscreen
                                                                                                        </a>
                                                                                                    </div>
                                                                                                @endif
                                                                                            @break

                                                                                            @case('Audio')
                                                                                                @if ($childAnswer)
                                                                                                    @php
                                                                                                        $audioPath = ltrim(
                                                                                                            trim(
                                                                                                                $childAnswer,
                                                                                                            ),
                                                                                                            '/',
                                                                                                        );
                                                                                                        $audioExt = strtolower(
                                                                                                            pathinfo(
                                                                                                                $audioPath,
                                                                                                                PATHINFO_EXTENSION,
                                                                                                            ),
                                                                                                        );
                                                                                                        $audioUrl = asset(
                                                                                                            $audioPath,
                                                                                                        );
                                                                                                        $mimeMap = [
                                                                                                            'mp3' =>
                                                                                                                'audio/mpeg',
                                                                                                            'wav' =>
                                                                                                                'audio/wav',
                                                                                                            'ogg' =>
                                                                                                                'audio/ogg',
                                                                                                            'm4a' =>
                                                                                                                'audio/mp4',
                                                                                                            'aac' =>
                                                                                                                'audio/aac',
                                                                                                            'webm' =>
                                                                                                                'audio/webm',
                                                                                                        ];
                                                                                                        $audioMime =
                                                                                                            $mimeMap[
                                                                                                                $audioExt
                                                                                                            ] ??
                                                                                                            'audio/webm';
                                                                                                    @endphp
                                                                                                    <audio controls
                                                                                                        style="width:100%; max-width:280px;"
                                                                                                        class="mt-1 d-block">
                                                                                                        <source
                                                                                                            src="{{ $audioUrl }}"
                                                                                                            type="{{ $audioMime }}">
                                                                                                        <source
                                                                                                            src="{{ $audioUrl }}"
                                                                                                            type="audio/webm">
                                                                                                        <source
                                                                                                            src="{{ $audioUrl }}"
                                                                                                            type="audio/ogg">
                                                                                                        Your browser does not
                                                                                                        support audio playback.
                                                                                                    </audio>
                                                                                                    <a href="{{ $audioUrl }}"
                                                                                                        download
                                                                                                        class="btn btn-sm btn-outline-secondary mt-1">
                                                                                                        &#11015; Download Audio
                                                                                                    </a>
                                                                                                @endif
                                                                                            @break

                                                                                            @case('Free Text')
                                                                                                <input type="text"
                                                                                                    value="{{ $childAnswer }}"
                                                                                                    name="{{ $childQuestion->id }}"
                                                                                                    class="form-control" readonly>
                                                                                            @break

                                                                                            @case('Yes / No')
                                                                                                <select class="form-select"
                                                                                                    name="{{ $childQuestion->id }}"
                                                                                                    disabled>
                                                                                                    <option value="">Select
                                                                                                        ..
                                                                                                    </option>
                                                                                                    <option value="Yes"
                                                                                                        @if ('Yes' == $childAnswer) selected @endif>
                                                                                                        Yes
                                                                                                    </option>
                                                                                                    <option value="No"
                                                                                                        @if ('No' == $childAnswer) selected @endif>
                                                                                                        No
                                                                                                    </option>
                                                                                                </select>
                                                                                            @break

                                                                                            @case('Dropdown')
                                                                                                <select class="form-select"
                                                                                                    name="{{ $childQuestion->id }}"
                                                                                                    disabled>
                                                                                                    <option value="">Select
                                                                                                        ..
                                                                                                    </option>
                                                                                                    @foreach ($childQuestion->getOptions as $questionOption)
                                                                                                        <option
                                                                                                            value="{{ $questionOption->option }}"
                                                                                                            @if ($questionOption->option == $childAnswer) selected @endif>
                                                                                                            {{ $questionOption->option }}
                                                                                                        </option>
                                                                                                    @endforeach
                                                                                                </select>
                                                                                            @break

                                                                                            @case('Multi select')
                                                                                                @php
                                                                                                    $selectArrIds[] =
                                                                                                        'select' .
                                                                                                        $childQuestion->id;
                                                                                                    $selectedValues = explode(
                                                                                                        ',',
                                                                                                        $childAnswer ??
                                                                                                            '',
                                                                                                    );
                                                                                                @endphp
                                                                                                <select class="form-select"
                                                                                                    name="{{ $childQuestion->id }}[]"
                                                                                                    id="select{{ $childQuestion->id }}"
                                                                                                    multiple disabled>
                                                                                                    <option value="">Select
                                                                                                        ..
                                                                                                    </option>
                                                                                                    @foreach ($childQuestion->getOptions as $questionOption)
                                                                                                        <option
                                                                                                            value="{{ $questionOption->option }}"
                                                                                                            @if (in_array($questionOption->option, $selectedValues)) selected @endif>
                                                                                                            {{ $questionOption->option }}
                                                                                                        </option>
                                                                                                    @endforeach
                                                                                                </select>
                                                                                            @break

                                                                                            @case('Date & Time')
                                                                                                @php
                                                                                                    $formattedDateTime =
                                                                                                        '';
                                                                                                    if (
                                                                                                        !empty(
                                                                                                            $childAnswer
                                                                                                        )
                                                                                                    ) {
                                                                                                        try {
                                                                                                            $datetimeFormats = [
                                                                                                                'Y-m-d H:i:s',
                                                                                                                'Y-m-d H:i',
                                                                                                                'd/m/Y H:i:s',
                                                                                                                'd/m/Y H:i',
                                                                                                                'm/d/Y H:i:s',
                                                                                                                'm/d/Y H:i',
                                                                                                                'Y-m-d\TH:i:s',
                                                                                                                'Y-m-d\TH:i',
                                                                                                            ];

                                                                                                            $parsed = false;
                                                                                                            foreach (
                                                                                                                $datetimeFormats
                                                                                                                as $format
                                                                                                            ) {
                                                                                                                try {
                                                                                                                    $carbonDate = \Carbon\Carbon::createFromFormat(
                                                                                                                        $format,
                                                                                                                        $childAnswer,
                                                                                                                    );
                                                                                                                    if (
                                                                                                                        $carbonDate !==
                                                                                                                        false
                                                                                                                    ) {
                                                                                                                        $formattedDateTime = $carbonDate->format(
                                                                                                                            'Y-m-d\TH:i',
                                                                                                                        );
                                                                                                                        $parsed = true;
                                                                                                                        break;
                                                                                                                    }
                                                                                                                } catch (\Exception $e) {
                                                                                                                    continue;
                                                                                                                }
                                                                                                            }

                                                                                                            if (
                                                                                                                !$parsed
                                                                                                            ) {
                                                                                                                try {
                                                                                                                    $formattedDateTime = \Carbon\Carbon::parse(
                                                                                                                        $childAnswer,
                                                                                                                    )->format(
                                                                                                                        'Y-m-d\TH:i',
                                                                                                                    );
                                                                                                                } catch (\Exception $e) {
                                                                                                                    $formattedDateTime = $childAnswer;
                                                                                                                }
                                                                                                            }
                                                                                                        } catch (\Exception $e) {
                                                                                                            $formattedDateTime = $childAnswer;
                                                                                                        }
                                                                                                    }
                                                                                                @endphp
                                                                                                <input type="datetime-local"
                                                                                                    name="{{ $childQuestion->id }}"
                                                                                                    value="{{ $formattedDateTime }}"
                                                                                                    class="form-control" readonly>
                                                                                            @break

                                                                                            @case('Date')
                                                                                                @php
                                                                                                    $formattedDate = '';
                                                                                                    if (
                                                                                                        !empty(
                                                                                                            $parentAnswer
                                                                                                        )
                                                                                                    ) {
                                                                                                        try {
                                                                                                            // Try common date formats
                                                                                                            $dateFormats = [
                                                                                                                'd/m/Y',
                                                                                                                'Y-m-d',
                                                                                                                'm/d/Y',
                                                                                                                'd-m-Y',
                                                                                                                'Y/m/d',
                                                                                                                'm-d-Y',
                                                                                                                'd M Y',
                                                                                                                'M d Y',
                                                                                                                'd F Y',
                                                                                                                'F d Y',
                                                                                                            ];

                                                                                                            $parsed = false;
                                                                                                            foreach (
                                                                                                                $dateFormats
                                                                                                                as $format
                                                                                                            ) {
                                                                                                                try {
                                                                                                                    $carbonDate = \Carbon\Carbon::createFromFormat(
                                                                                                                        $format,
                                                                                                                        $childAnswer,
                                                                                                                    );
                                                                                                                    if (
                                                                                                                        $carbonDate !==
                                                                                                                        false
                                                                                                                    ) {
                                                                                                                        $formattedDate = $carbonDate->format(
                                                                                                                            'Y-m-d',
                                                                                                                        );
                                                                                                                        $parsed = true;
                                                                                                                        break;
                                                                                                                    }
                                                                                                                } catch (\Exception $e) {
                                                                                                                    continue;
                                                                                                                }
                                                                                                            }

                                                                                                            // If none of the formats worked, try parsing with Carbon's automatic parser
        if (
            !$parsed
        ) {
            try {
                $formattedDate = \Carbon\Carbon::parse(
                    $childAnswer,
                )->format(
                    'Y-m-d',
                                                                                                                    );
                                                                                                                } catch (\Exception $e) {
                                                                                                                    // If all parsing fails, just display the original value
                                                                                                                    $formattedDate = $childAnswer;
                                                                                                                }
                                                                                                            }
                                                                                                        } catch (\Exception $e) {
                                                                                                            $formattedDate = $childAnswer;
                                                                                                        }
                                                                                                    }
                                                                                                @endphp
                                                                                                <input type="date"
                                                                                                    value="{{ $formattedDate }}"
                                                                                                    name="{{ $childQuestion->id }}"
                                                                                                    class="form-control" readonly>
                                                                                            @break

                                                                                            @case('File Upload')
                                                                                                <input type="file"
                                                                                                    name="{{ $childQuestion->id }}"
                                                                                                    class="form-control" disabled>
                                                                                                @if ($childAnswer)
                                                                                                    <a class="badge badge-primary mt-2"
                                                                                                        href="{{ asset($childAnswer) }}">Click
                                                                                                        Here</a>
                                                                                                @endif
                                                                                            @break

                                                                                            @case('Subjective')
                                                                                                @foreach ($childValues as $value)
                                                                                                    <div
                                                                                                        class="d-flex flex-column gap-2">
                                                                                                        <select class="form-select"
                                                                                                            name="{{ $childQuestion->id }}"
                                                                                                            disabled>
                                                                                                            <option
                                                                                                                value="{{ $value }}">
                                                                                                                {{ $value }}
                                                                                                            </option>
                                                                                                        </select>
                                                                                                    </div>
                                                                                                @endforeach
                                                                                            @break

                                                                                            @default
                                                                                                <input type="text"
                                                                                                    value="{{ $childAnswer }}"
                                                                                                    class="form-control"
                                                                                                    name="{{ $childQuestion->id }}"
                                                                                                    readonly>
                                                                                        @endswitch
                                                                                    @endif
                                                                                </td>
                                                                            </tr>
                                                                        @endif
                                                                    @endforeach
                                                                @endif

                                                                {{-- &#9472;&#9472; Outlet sub-questions (new flow, recursive) &#9472;&#9472; --}}
                                                                @if ($parentQuestion->question_type === 'Multi Response' && $parentQuestion->subQuestions->count() > 0)
                                                                    @foreach ($parentQuestion->subQuestions as $subLink)
                                                                        @if ($subLink->childQuestion)
                                                                            @include('masters.verifiers.partials.render_sub_question_verify', [
                                                                                'subQuestion'     => $subLink->childQuestion,
                                                                                'depth'           => 1,
                                                                                'user_responses'  => $user_responses,
                                                                                'mr_parent_id'    => $parentQuestion->id,
                                                                            ])
                                                                        @endif
                                                                    @endforeach
                                                                @endif
                                                                {{-- &#9472;&#9472; End Outlet sub-questions &#9472;&#9472; --}}

                                                            @endforeach

                                                            @if (!empty($activitySignature))
                                                                <tr>
                                                                    <td class="col-md-6 fw-medium">Signature</td>
                                                                    <td class="col-md-6">
                                                                        <a href="{{ asset($activitySignature->signature_path) }}" target="_blank">
                                                                            <img src="{{ asset($activitySignature->signature_path) }}"
                                                                                alt="Signature"
                                                                                style="max-width:220px; max-height:120px; border:1px solid #dee2e6; border-radius:6px; background:#fff; padding:4px;">
                                                                        </a>
                                                                    </td>
                                                                </tr>
                                                            @endif

                                                            <!-- Verifier Remark Row -->
                                                            <tr>
                                                                <td class="col-md-6 fw-medium">Verifier Remark</td>
                                                                <td class="col-md-6">
                                                                    <select class="form-control" name="remark"
                                                                        id="verify_remark" required>
                                                                        <option value="">Select Remark</option>
                                                                        <option value="other">Other Remark</option>
                                                                        @foreach ($remarks as $remark)
                                                                            <option value="{{ $remark->id }}">
                                                                                {{ $remark->remark }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                    <input class="form-control d-none mt-4" type="text"
                                                                        placeholder="Add Other Remark" id="other_remark"
                                                                        name="other_remark">
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>

                                                <button id="approve" name="approve" class="btn btn-primary float-end ms-2">
                                                    Approve
                                                </button>
                                                <button id="reject" name="reject" class="btn btn-danger float-end">
                                                    Reject
                                                </button>
                                                <button id="send_back" name="send_back" class="btn btn-warning float-end me-2">
                                                    Sendback
                                                </button>
                                            </form>

                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                {{-- @foreach ($get_draft_values as $values) --}}
                {{--   <pre> {{$values}}</pre> --}}
                {{-- @endforeach --}}
            </div>
        </div>
    </div>

    {{-- khushboo 15-05-25 --}}
    {{-- View Image Modal --}}
    <div class="modal fade" id="ImageModel" tabindex="-1" aria-labelledby="ImageModelLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered"> <!-- centered modal -->
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ImageModelLabel">Uploaded File Preview</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body text-center" id="ImageModelBody" style="padding: 15px;">
                    <!-- Image will be injected here -->
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    {{-- End Modal --}}
    {{-- khushboo 15-05-25 --}}
@endsection
@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
    <script>
        async function downloadAllImagesAsZip(btn) {
            // Collect all img elements marked as verifier images
            const imgs = document.querySelectorAll('img[data-verifier-img]');
            if (imgs.length === 0) {
                alert('No images found on this page.');
                return;
            }

            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '&#8987; Preparing ZIP...';

            const zip = new JSZip();
            const folder = zip.folder('images');
            let count = 0;

            const fetchPromises = Array.from(imgs).map(async (img, i) => {
                const url = img.getAttribute('data-verifier-img');
                try {
                    const response = await fetch(url, { mode: 'cors' });
                    if (!response.ok) throw new Error('Failed');
                    const blob = await response.blob();
                    const ext  = url.split('.').pop().split('?')[0].toLowerCase() || 'jpg';
                    folder.file('image_' + (i + 1) + '.' + ext, blob);
                    count++;
                } catch (e) {
                    console.warn('Could not fetch image:', url, e);
                }
            });

            await Promise.all(fetchPromises);

            if (count === 0) {
                btn.disabled = false;
                btn.innerHTML = originalText;
                alert('Could not download any images. Check browser console for details.');
                return;
            }

            const content = await zip.generateAsync({ type: 'blob' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(content);
            a.download = 'images_{{ $related_values->id }}.zip';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);

            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    </script>


    <script>
        // function initMap() {
        //
        //     const origin = { lat: 23.02858631445742, lng: 72.53012544754631 };      // Ahmedabad
        //     const destination = { lat: 23.249656769507297, lng: 72.65856764961387 }; // Mumbai
        //
        //     const map = new google.maps.Map(document.getElementById("map"), {
        //         zoom: 6,
        //         center: origin,
        //     });
        //
        //     const directionsService = new google.maps.DirectionsService();
        //     const directionsRenderer = new google.maps.DirectionsRenderer();
        //     directionsRenderer.setMap(map);
        //
        //     // Render route on map
        //     directionsService.route(
        //         {
        //             origin: origin,
        //             destination: destination,
        //             travelMode: google.maps.TravelMode.DRIVING,
        //         },
        //         (response, status) => {
        //             if (status === "OK") {
        //                 directionsRenderer.setDirections(response);
        //             } else {
        //                 alert("Directions request failed due to " + status);
        //             }
        //         }
        //     );
        //
        //     // Use Distance Matrix to get distance and time
        //     const service = new google.maps.DistanceMatrixService();
        //     service.getDistanceMatrix(
        //         {
        //             origins: [origin],
        //             destinations: [destination],
        //             travelMode: google.maps.TravelMode.DRIVING,
        //             unitSystem: google.maps.UnitSystem.METRIC,
        //         },
        //         (result, status) => {
        //             if (status === "OK") {
        //                 const distance = result.rows[0].elements[0].distance.text;
        //                 const duration = result.rows[0].elements[0].duration.text;
        //                 document.getElementById("output").innerHTML =
        //                     `<b>Distance:</b> ${distance} <br> <b>Duration:</b> ${duration}`;
        //             }
        //         }
        //     );
        // }
        //
        // window.onload = initMap;
    </script>

    <script>
        function haversineDistance(lat1, lon1, lat2, lon2) {

            console.log(lat1, lon1, lat2, lon2);
            const R = 6371; // Earth's radius in km
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;

            const a =
                Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2);

            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            const distance = R * c;

            return distance.toFixed(2); // Returns distance in km
        }

        function convertToDecimal(coordinate) {
            if (!coordinate || typeof coordinate !== 'string') return null;

            // Split on degree symbol and any surrounding spaces
            const parts = coordinate.trim().split(/[&#194;&#176;\s]+/);
            let value = parseFloat(parts[0]);

            if (isNaN(value)) return null;

            const direction = parts[1]?.toUpperCase();

            if (direction === 'S' || direction === 'W') {
                value *= -1;
            } else if (direction === 'N' || direction === 'E') {
                value *= 1; // Explicit for clarity (positive)
            } else {
                return null; // Invalid direction
            }

            return value;
        }

        $('#verify_remark').on('change', function() {
            if ($('#verify_remark').val() == 'other') {
                $('#other_remark').removeClass('d-none');
            } else {
                $('#other_remark').addClass('d-none');
                $('#other_remark').val('');
            }
        })

        // function getOtherRemark() {
        //     if ($('#verify_remark').val() == 'other') {
        //         $('#other_remark').removeClass('d-none');
        //     } else {
        //         $('#other_remark').addClass('d-none');
        //         $('#other_remark').val('');
        //     }
        // }

        document.addEventListener("DOMContentLoaded", function() {

            const LatLong = @json($latlongData);

            var distributorLat = LatLong.length > 0 ? convertToDecimal(LatLong[0]) : null;
            var distributorLng = LatLong.length > 0 ? convertToDecimal(LatLong[1]) : null;
            // var distributorLat = '';
            // var distributorLng = '';
            // console.log(distributorLat);

            const userLatLongData = @json($userLatLongData);
            // console.log(userLatLongData, distributorLat, distributorLng);
            // const userLatLongData = [];
            const userLat = userLatLongData.latitude ? parseFloat(userLatLongData.latitude) : null;
            const userLng = userLatLongData.longitude ? parseFloat(userLatLongData.longitude) : null;


            let map;

            if (distributorLat && distributorLng && userLat && userLng) {
                const map = L.map('map').setView([distributorLat, distributorLng], 6);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&#194;&#169; OpenStreetMap contributors'
                }).addTo(map);

                const distributorMarker = L.marker([distributorLat, distributorLng]).addTo(map).bindPopup(
                    'Distributor Location').openPopup();
                const userMarker = L.marker([userLat, userLng]).addTo(map).bindPopup('User Location');

                const latlngs = [
                    [distributorLat, distributorLng],
                    [userLat, userLng]
                ];

                const polyline = L.polyline(latlngs, {
                    color: 'blue'
                }).addTo(map);

                map.fitBounds(polyline.getBounds());

                // &#240;&#376;&#376;&#162; Fetch travel/road distance using OSRM
                fetch(
                        `https://router.project-osrm.org/route/v1/driving/${distributorLng},${distributorLat};${userLng},${userLat}?overview=false`
                    )
                    .then(response => response.json())
                    .then(data => {
                        if (data.routes && data.routes.length > 0) {
                            // const distanceInKm = (data.routes[0].distance / 1000).toFixed(2);
                            // console.log(`Road Distance: ${distanceInKm} km`);
                            //
                            // const midLat = (distributorLat + userLat) / 2;
                            // const midLng = (distributorLng + userLng) / 2;
                            //
                            // // &#226;&#339;&#8230; Now safely call L.popup() after map is fully initialized
                            // L.popup()
                            //     .setLatLng([midLat, midLng])
                            //     .setContent(`<strong>Road Distance: ${distanceInKm} km</strong>`)
                            //     .openOn(map);

                            const straightLineDistance = haversineDistance(distributorLat, distributorLng,
                                userLat, userLng);
                            console.log(`Straight-line Distance: ${straightLineDistance} km`);

                            const midLat = (distributorLat + userLat) / 2;
                            const midLng = (distributorLng + userLng) / 2;

                            L.popup()
                                .setLatLng([midLat, midLng])
                                .setContent(`<strong>Distance: ${straightLineDistance} km</strong>`)
                                .openOn(map);


                        } else {
                            console.warn("No route found.");
                        }
                    })
                    .catch(err => {
                        console.error("OSRM request failed:", err);
                    });
            }


            // Case 2: Only distributor location exists
            else if (distributorLat && distributorLng) {
                map = L.map('map').setView([distributorLat, distributorLng], 13);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&#194;&#169; OpenStreetMap contributors'
                }).addTo(map);

                L.marker([distributorLat, distributorLng]).addTo(map)
                    .bindPopup('Distributor Location')
                    .openPopup();
            }

            // Case 3: Only user location exists
            else if (userLat && userLng) {
                console.log(userLat, userLng);
                map = L.map('map').setView([userLat, userLng], 13);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&#194;&#169; OpenStreetMap contributors'
                }).addTo(map);

                L.marker([userLat, userLng]).addTo(map)
                    .bindPopup('User Location')
                    .openPopup();
            }

            // Case 4: Neither location exists &#226;&#8364;&#8220; do nothing or show a message
            else {
                console.warn("No location data available.");
            }
        });

        function openImageModelold(filePath) {
            const extension = filePath.split('.').pop().toLowerCase();
            let html = '';

            const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            const videoExtensions = ['mp4', 'mp3', 'webm', 'ogg', 'temp'];

            if (imageExtensions.includes(extension)) {
                html = `
        <img
            src="${filePath}"
            alt="Uploaded File"
            class=" rounded shadow-sm border"
            style="height: 450px !important; width: auto; object-fit: contain;"
            onerror="this.onerror=null; this.src='/path/to/placeholder.png';"
        />
    `;
            } else if (videoExtensions.includes(extension)) {
                // Fix for temp: assume it's mp4
                let mimeType = 'video/' + (extension === 'temp' ? 'mp4' : extension);

                html = `
                    <video controls class="w-100 rounded shadow-sm border" style="max-height: 400px; object-fit: contain;">
                    <source src="${filePath}" type="${mimeType}">
                        Your browser does not support the video tag.
                    </video>
                `;

            } else {
                html = `<p class="text-danger">Unsupported file type.</p>`;
            }

            $('#ImageModelBody').html(html);
            const modal = new bootstrap.Modal(document.getElementById('ImageModel'));
            modal.show();
        }

        let isVideoLoading = false;
        let isVideoPlaying = false;

        function openImageModelold(filePath) {
            console.log('Original file path:', filePath);

            // Reset flags
            isVideoLoading = false;
            isVideoPlaying = false;

            // Ensure the path is properly encoded for URLs
            const encodedFilePath = encodeURI(filePath).replace(/%20/g, ' ');

            const extension = encodedFilePath.split('.').pop().toLowerCase();
            console.log('Encoded file path:', encodedFilePath, 'Extension:', extension);

            let html = '';

            const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            const videoExtensions = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'wmv', 'flv', 'mkv', 'temp'];

            if (imageExtensions.includes(extension)) {
                html = `
            <img
                src="${encodedFilePath}"
                alt="Uploaded File"
                class="img-fluid rounded shadow-sm border"
                style="height: 450px !important; width: auto; object-fit: contain;"
                onerror="handleImageError(this, '${encodedFilePath}')"
            />
        `;
            } else if (videoExtensions.includes(extension)) {
                // Determine mime type
                let mimeType = 'video/';
                switch (extension) {
                    case 'mp4':
                        mimeType += 'mp4';
                        break;
                    case 'webm':
                        mimeType += 'webm';
                        break;
                    case 'ogg':
                        mimeType += 'ogg';
                        break;
                    case 'mov':
                        mimeType += 'quicktime';
                        break;
                    case 'avi':
                        mimeType += 'x-msvideo';
                        break;
                    case 'wmv':
                        mimeType += 'x-ms-wmv';
                        break;
                    case 'flv':
                        mimeType += 'x-flv';
                        break;
                    case 'temp':
                        mimeType += 'mp4';
                        break;
                    default:
                        mimeType += 'mp4';
                }

                // Create a direct download link with proper encoding
                const downloadLink = encodedFilePath.replace(/ /g, '%20');

                html = `
            <div class="video-container">
                <video id="modal-video" controls class="w-100 rounded shadow-sm border"
                       style="max-height: 70vh; object-fit: contain; background: #000;"
                       preload="metadata"
                       crossorigin="anonymous"
                       playsinline>
                    <source src="${encodedFilePath}" type="${mimeType}">
                    <p class="mt-3 p-3 bg-light rounded">
                        <i class="fa fa-exclamation-triangle text-warning"></i>
                        Your browser does not support this video format or the file cannot be loaded.
                        <br>
                        <a href="${downloadLink}" download class="btn btn-sm btn-danger mt-2">
                            <i class="fa fa-download"></i> Download Video
                        </a>
                    </p>
                </video>
                <div class="mt-3">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-success" onclick="playVideo()" id="play-btn">
                            <i class="fa fa-play"></i> Play
                        </button>
                        <button type="button" class="btn btn-info" onclick="reloadVideo('${encodedFilePath}')" id="reload-btn">
                            <i class="fa fa-refresh"></i> Reload
                        </button>
                        <a href="${downloadLink}" download class="btn btn-primary">
                            <i class="fa fa-download"></i> Download
                        </a>
                    </div>
                </div>
                <div class="mt-2">
                    <span id="video-status" class="badge bg-secondary">Loading...</span>
                    <small class="text-muted ms-2">Format: .${extension}</small>
                </div>
                <div id="video-error" class="alert alert-danger mt-2 d-none">
                    <i class="fa fa-exclamation-triangle"></i>
                    Video cannot be played. Possible issues:
                    <ul class="mb-0 mt-1">
                        <li>Browser doesn't support .${extension} format</li>
                        <li>File path contains spaces (common issue)</li>
                        <li>Video file might be corrupted</li>
                        <li>Web recordings may have encoding issues</li>
                    </ul>
                    <div class="mt-2">
                        <a href="${downloadLink}" download class="btn btn-sm btn-danger">
                            <i class="fa fa-download"></i> Download and play locally
                        </a>
                        <button type="button" class="btn btn-sm btn-warning ms-2" onclick="forceLoadVideo('${encodedFilePath}')">
                            <i class="fa fa-bolt"></i> Force Load
                        </button>
                    </div>
                </div>
            </div>
        `;
            } else {
                html = `
            <div class="alert alert-warning">
                <p class="mb-0">File type .${extension} not supported for preview.</p>
                <a href="${encodedFilePath}" download class="btn btn-sm btn-primary mt-2">
                    <i class="fa fa-download"></i> Download File
                </a>
            </div>
        `;
            }

            $('#ImageModelBody').html(html);
            const modal = new bootstrap.Modal(document.getElementById('ImageModel'));
            modal.show();

            // Set up video event listeners
            if (videoExtensions.includes(extension)) {
                setTimeout(() => {
                    setupVideoListeners(encodedFilePath);
                }, 500); // Delay to ensure DOM is ready
            }
        }

        function setupVideoListeners(filePath) {
            const video = document.getElementById('modal-video');
            const playBtn = document.getElementById('play-btn');
            const reloadBtn = document.getElementById('reload-btn');
            const status = document.getElementById('video-status');

            if (!video) return;

            // Clear any existing listeners
            video.onerror = null;
            video.oncanplay = null;
            video.onplaying = null;
            video.onwaiting = null;

            video.onerror = function(e) {
                console.error('Video error:', video.error);
                status.textContent = 'Error: ' + getVideoError(video.error);
                status.className = 'badge bg-danger';
                document.getElementById('video-error').classList.remove('d-none');
                isVideoLoading = false;
                playBtn.disabled = false;
                reloadBtn.disabled = false;
            };

            video.oncanplay = function() {
                status.textContent = 'Ready to play';
                status.className = 'badge bg-success';
                isVideoLoading = false;
                playBtn.disabled = false;
                reloadBtn.disabled = false;
            };

            video.onplaying = function() {
                status.textContent = 'Playing';
                status.className = 'badge bg-success';
                isVideoPlaying = true;
                playBtn.innerHTML = '<i class="fa fa-pause"></i> Pause';
                playBtn.onclick = pauseVideo;
            };

            video.onpause = function() {
                isVideoPlaying = false;
                playBtn.innerHTML = '<i class="fa fa-play"></i> Play';
                playBtn.onclick = playVideo;
            };

            video.onwaiting = function() {
                status.textContent = 'Buffering...';
                status.className = 'badge bg-warning';
            };

            video.onloadstart = function() {
                status.textContent = 'Loading...';
                status.className = 'badge bg-warning';
                isVideoLoading = true;
                playBtn.disabled = true;
                reloadBtn.disabled = true;
            };
        }

        // Replace your current playVideo function with this:
        function playVideo() {
            if (isVideoLoading) {
                console.log('Video is still loading, please wait...');
                return;
            }

            const video = document.getElementById('modal-video');
            const status = document.getElementById('video-status');
            const playBtn = document.getElementById('play-btn');

            if (!video) return;

            // Don't disable button - let user click again if needed
            isVideoPlaying = true;

            // Remove the setTimeout and play directly
            const playPromise = video.play();

            if (playPromise !== undefined) {
                playPromise.then(() => {
                    console.log('Video playback started successfully');
                    status.textContent = 'Playing';
                    status.className = 'badge bg-success';
                    playBtn.innerHTML = '<i class="fa fa-pause"></i> Pause';
                    playBtn.onclick = pauseVideo;
                    isVideoPlaying = true;
                }).catch(error => {
                    console.error('Error playing video:', error);
                    status.textContent = 'Play failed - try reloading';
                    status.className = 'badge bg-danger';
                    isVideoPlaying = false;

                    // Show specific error
                    let errorMsg = 'Unknown error';
                    switch (error.name) {
                        case 'AbortError':
                            errorMsg = 'Playback was interrupted. Try clicking play again.';
                            playBtn.innerHTML = '<i class="fa fa-play"></i> Try Again';
                            break;
                        case 'NotAllowedError':
                            errorMsg = 'Autoplay blocked. Click play again or enable autoplay.';
                            playBtn.innerHTML = '<i class="fa fa-play"></i> Click to Play';
                            break;
                        case 'NotSupportedError':
                            errorMsg = 'Video format not supported by browser.';
                            break;
                        case 'NetworkError':
                            errorMsg = 'Network error loading video. Try reloading.';
                            break;
                    }

                    // Update error message
                    const errorDiv = document.getElementById('video-error');
                    const errorList = errorDiv.querySelector('ul');
                    if (errorList) {
                        errorList.innerHTML = `<li>${errorMsg}</li>
                                      <li>Try downloading and playing locally</li>`;
                        errorDiv.classList.remove('d-none');
                    }
                });
            }
        }

        // Also update your openImageModel function to fix the video setup:
        function openImageModel(filePath) {
            console.log('Original file path:', filePath);

            // Reset flags
            isVideoLoading = false;
            isVideoPlaying = false;

            // CLEAN THE PATH - Check if it has any encrypted data appended
            let cleanPath = filePath;

            // If the path contains Laravel encrypted data (starts with eyJ), remove it
            if (filePath.includes('eyJ')) {
                // Split by any whitespace or newline
                const parts = filePath.split(/\s+/);
                cleanPath = parts[0];
                console.log('Cleaned encrypted data from path. New path:', cleanPath);
            }

            // Ensure the path is properly encoded for URLs
            const encodedFilePath = encodeURI(cleanPath).replace(/%20/g, ' ');

            const extension = cleanPath.split('.').pop().toLowerCase();
            console.log('Clean file path:', cleanPath);
            console.log('Encoded file path:', encodedFilePath, 'Extension:', extension);

            let html = '';

            const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            const videoExtensions = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'wmv', 'flv', 'mkv', 'temp'];

            if (imageExtensions.includes(extension)) {
                html = `
            <img
                src="${encodedFilePath}"
                alt="Uploaded File"
                class="img-fluid rounded shadow-sm border"
                style="height: 450px !important; width: auto; object-fit: contain;"
                onerror="handleImageError(this, '${encodedFilePath}')"
            />
        `;
            } else if (videoExtensions.includes(extension)) {
                // Determine mime type
                let mimeType = 'video/';
                switch (extension) {
                    case 'mp4':
                        mimeType += 'mp4';
                        break;
                    case 'webm':
                        mimeType += 'webm';
                        break;
                    case 'ogg':
                        mimeType += 'ogg';
                        break;
                    case 'mov':
                        mimeType += 'quicktime';
                        break;
                    case 'avi':
                        mimeType += 'x-msvideo';
                        break;
                    case 'wmv':
                        mimeType += 'x-ms-wmv';
                        break;
                    case 'flv':
                        mimeType += 'x-flv';
                        break;
                    case 'temp':
                        mimeType += 'mp4';
                        break;
                    default:
                        mimeType += 'mp4';
                }

                // Create a direct download link with proper encoding
                const downloadLink = encodedFilePath.replace(/ /g, '%20');

                // SIMPLIFIED HTML - Remove complex buttons that cause conflicts
                html = `
            <div class="video-container">
                <video id="modal-video" controls class="w-100 rounded shadow-sm border"
                       style="max-height: 70vh; object-fit: contain; background: #000;"
                       preload="auto"
                       crossorigin="anonymous"
                       playsinline>
                    <source src="${encodedFilePath}" type="${mimeType}">
                    Your browser does not support this video format.
                </video>
                <div class="mt-3 text-center">
                    <a href="${downloadLink}" download class="btn btn-primary">
                        <i class="fa fa-download"></i> Download Video
                    </a>
                    <button type="button" class="btn btn-secondary ms-2" data-bs-dismiss="modal">
                        Close
                    </button>
                </div>
                <div class="mt-2 text-center">
                    <small class="text-muted">Format: .${extension} |
                    <a href="${encodedFilePath}" target="_blank" class="text-info">Open in new tab</a></small>
                </div>
            </div>
        `;
            } else {
                html = `
            <div class="alert alert-warning">
                <p class="mb-0">File type .${extension} not supported for preview.</p>
                <a href="${encodedFilePath}" download class="btn btn-sm btn-primary mt-2">
                    <i class="fa fa-download"></i> Download File
                </a>
            </div>
        `;
            }

            $('#ImageModelBody').html(html);
            const modal = new bootstrap.Modal(document.getElementById('ImageModel'));

            // Clear any existing modal listeners
            $('#ImageModel').off('shown.bs.modal');

            // Auto-play video when modal is shown
            $('#ImageModel').on('shown.bs.modal', function() {
                if (videoExtensions.includes(extension)) {
                    setTimeout(() => {
                        const video = document.getElementById('modal-video');
                        if (video) {
                            // Try to play, but don't force it
                            video.play().catch(e => {
                                console.log('Auto-play prevented:', e.message);
                                // That's OK - user can click play button
                            });
                        }
                    }, 300);
                }
            });

            modal.show();
        }

        // Remove the complex setupVideoListeners, pauseVideo, reloadVideo, forceLoadVideo functions
        // They're causing conflicts with the native video controls

        function pauseVideo() {
            const video = document.getElementById('modal-video');
            const playBtn = document.getElementById('play-btn');

            if (video && isVideoPlaying) {
                video.pause();
                isVideoPlaying = false;
                playBtn.innerHTML = '<i class="fa fa-play"></i> Play';
                playBtn.onclick = playVideo;
            }
        }

        function reloadVideo(filePath) {
            if (isVideoLoading) {
                console.log('Video is already loading, please wait...');
                return;
            }

            const video = document.getElementById('modal-video');
            const source = video.querySelector('source');
            const status = document.getElementById('video-status');
            const playBtn = document.getElementById('play-btn');
            const reloadBtn = document.getElementById('reload-btn');

            // Disable buttons during reload
            playBtn.disabled = true;
            reloadBtn.disabled = true;
            isVideoLoading = true;

            // Reset video
            video.pause();

            // Reset status
            status.textContent = 'Reloading...';
            status.className = 'badge bg-warning';

            // Hide error
            document.getElementById('video-error').classList.add('d-none');

            // Force reload with cache busting
            const newPath = filePath + (filePath.includes('?') ? '&' : '?') + 't=' + new Date().getTime();

            // Change source and load
            source.src = newPath;
            video.load();

            // Re-enable buttons after load
            setTimeout(() => {
                playBtn.disabled = false;
                reloadBtn.disabled = false;
                isVideoLoading = false;

                // Auto-play if previously playing
                if (isVideoPlaying) {
                    setTimeout(() => playVideo(), 500);
                }
            }, 1000);
        }

        function forceLoadVideo(filePath) {
            const video = document.getElementById('modal-video');
            const source = video.querySelector('source');
            const status = document.getElementById('video-status');

            // Create a new source element (clean slate)
            const newSource = document.createElement('source');
            newSource.src = filePath + '?force=' + new Date().getTime();
            newSource.type = source.type;

            // Replace the source
            video.innerHTML = '';
            video.appendChild(newSource);

            // Reset and load
            video.load();

            status.textContent = 'Force loading...';
            status.className = 'badge bg-warning';

            // Hide error
            document.getElementById('video-error').classList.add('d-none');

            // Setup listeners again
            setTimeout(() => {
                setupVideoListeners(filePath);
            }, 300);
        }

        function getVideoError(error) {
            if (!error) return 'Unknown error';

            switch (error.code) {
                case 1:
                    return 'Video loading aborted';
                case 2:
                    return 'Network error';
                case 3:
                    return 'Video decoding error (common with web recordings)';
                case 4:
                    return 'Video format not supported';
                default:
                    return 'Error code: ' + error.code;
            }
        }

        function handleImageError(img, filePath) {
            console.error('Image failed to load:', filePath);
            img.src = '/path/to/placeholder.png';
            img.alt = 'Image failed to load';
            img.style.border = '2px dashed #dc3545';
        }

        $(document).ready(function() {

            //image modal

            $('.imagemodal').on('click', function() {
                let datasetval = $(this).data('setval');
                console.log(datasetval);
                openImageModel(datasetval);
            });


            @if (session()->has('message'))
                Swal.fire({
                    position: "top-center",
                    icon: "success",
                    title: "{{ session('message') }}",
                    showConfirmButton: false,
                    timer: 1500
                });
            @endif

            const multiSelects = @json($selectArrIds);
            $.each(multiSelects, function(index, select_id) {
                console.log(select_id);
                $(`#${select_id}`).select2({
                    theme: "bootstrap-5",
                    placeholder: "Select options",
                    allowClear: true,
                    closeOnSelect: false,
                    width: '100%',
                });
            })

            $("#edit_answers").click(function() {
                // Enable all text inputs by removing readonly
                $('input[readonly]').removeAttr('readonly');
                // Enable all file and date inputs by removing disabled
                $('input[disabled], select[disabled]').removeAttr('disabled');
                $(".subjective_ques").removeClass('d-none');
            })

            $('#approve, #reject').on('click', function() {
                // Enable all text inputs by removing readonly
                $('input[readonly]').removeAttr('readonly');
                // Enable all file and date inputs by removing disabled
                $('input[disabled], select[disabled]').removeAttr('disabled');
            });
            // this is to add the subjective question implement in the activity
            $(".subjective_ques").change(function(event) {
                let subjectTd = $(this).closest('td');
                const subjectInfo = $(this).val();
                let qid = $(this).data('q_id');
                if (qid !== "" && subjectInfo !== "") {
                    $.ajax({
                        url: "{{ route('getQuestion.subject.options') }}",
                        type: "POST",
                        data: {
                            "_token": "{{ @csrf_token() }}",
                            question_id: qid,
                            subject: subjectInfo
                        },
                        success: function(response) {
                            if (response.message == "success") {
                                let subject_options_select = subjectTd.find(
                                    ".subjective_ques_option");
                                subject_options_select.empty();
                                $.each(response.subjectOption.get_options, function(index,
                                    subjectOptions) {
                                    let subjectOption =
                                        `${subjectInfo} (${subjectOptions.option})`;
                                    subject_options_select.append($('<option>').text(
                                        subjectOptions.option).val(
                                        subjectOption));
                                });
                                // this is to make the subject as the option selected so that it can be answered if the options of subjects are not present
                                if (response.subjectOption.get_options.length == 0) {
                                    // Swal.fire({
                                    //     icon: "error",
                                    //     title: "Oops...",
                                    //     text: "This Subject Doesn't have any Option!",
                                    // });
                                    subject_options_select.append($('<option>').text(
                                        subjectInfo).val(subjectInfo));
                                }
                            } else {
                                //     Something went wrong
                            }
                        }
                    })
                }

            });


            // this is to record the audio message
            let mediaRecorder;
            let audioChunks = {};

            $(".start-recording").on("click", function() {
                let id = $(this).data("id");
                let stopBtn = $(".stop-recording[data-id='" + id + "']");
                let deleteBtn = $(".delete-audio[data-id='" + id + "']");
                let audioPlayer = $("#audio-player-" + id);
                let audioInput = $("input[name='" + id + "']");

                navigator.mediaDevices.getUserMedia({
                        audio: true
                    })
                    .then(stream => {
                        mediaRecorder = new MediaRecorder(stream);
                        audioChunks[id] = [];

                        mediaRecorder.ondataavailable = event => {
                            audioChunks[id].push(event.data);
                        };
                        mediaRecorder.onstop = () => {
                            let audioBlob = new Blob(audioChunks[id], {
                                type: 'audio/wav'
                            });
                            let audioUrl = URL.createObjectURL(audioBlob);
                            audioPlayer.attr("src", audioUrl).removeClass(
                                "d-none"); // Show audio player
                            deleteBtn.prop("disabled", false);
                            // Convert audio to base64 and store in hidden input
                            let reader = new FileReader();
                            reader.readAsDataURL(audioBlob);
                            reader.onloadend = function() {
                                audioInput.val(reader.result);
                            };
                        };
                        mediaRecorder.start();
                        $(this).prop("disabled", true);
                        stopBtn.prop("disabled", false);
                    });
            });
            $(".stop-recording").on("click", function() {
                let id = $(this).data("id");
                let stopBtn = $(this);
                let startBtn = $(".start-recording[data-id='" + id + "']");
                let deleteBtn = $(".delete-audio[data-id='" + id + "']");
                let audioPlayer = $("#audio-player-" + id);
                mediaRecorder.stop();
                mediaRecorder.onstop = function() {
                    let audioBlob = new Blob(audioChunks[id], {
                        type: "audio/wav"
                    });
                    let audioUrl = URL.createObjectURL(audioBlob);

                    audioPlayer.attr("src", audioUrl).removeClass(
                        "d-none"); // Show and set audio source
                    deleteBtn.prop("disabled", false);

                    // Convert audio to base64 and store in hidden input
                    let audioInput = $("input[name='" + id + "']");
                    let reader = new FileReader();
                    reader.readAsDataURL(audioBlob);
                    reader.onloadend = function() {
                        audioInput.val(reader.result);
                    };
                };
                stopBtn.prop("disabled", true);
                startBtn.prop("disabled", false);
            });
            $(".delete-audio").on("click", function() {
                let id = $(this).data("id");
                let audioPlayer = $("#audio-player-" + id);
                let audioInput = $("input[name='" + id + "']");
                audioPlayer.addClass("d-none").attr("src", ""); // Hide audio player
                audioInput.val("");
                $(".start-recording[data-id='" + id + "']").prop("disabled", false);
                $(".stop-recording[data-id='" + id + "']").prop("disabled", true);
                $(this).prop("disabled", true);
            });

            $('.audio-data').each(function() {
                var audioFilePath = $(this).val(); // Get the audio file path
                var audioPlayer = '#audio-player-' + $(this).data('id'); // Target the specific audio player
                // Set the audio source dynamically
                if (audioFilePath) {
                    $(audioPlayer).attr('src', audioFilePath);
                }
            });
        })
    </script>
@endsection
