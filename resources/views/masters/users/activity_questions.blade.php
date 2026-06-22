@extends('template.layouts.simple.master')
@section('style')
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet">
    <style>
        .select2-container {
            font-size: 14px;
        }

        .select2-container--default .select2-selection--multiple {
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 5px;
            min-height: 38px;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 2px 5px;
            margin: 2px;
        }

        .select2-container--default .select2-dropdown {
            border: 1px solid #ced4da;
            border-radius: 4px;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__placeholder {
            color: #6c757d;
        }

        .recording-section {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }

        .video-preview-container {
            width: 100%;
            max-width: 400px;
            margin: 10px 0;
        }

        .video-preview {
            width: 100%;
            height: auto;
            border-radius: 4px;
        }

        .recording-status {
            padding: 5px 10px;
            border-radius: 4px;
            margin-top: 5px;
            font-weight: 500;
        }

        .recording-status.recording {
            background-color: #f8d7da;
            color: #721c24;
        }

        .recording-status.stopped {
            background-color: #d4edda;
            color: #155724;
        }

        .btn-recording {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .location-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            flex-direction: column;
            color: white;
            text-align: center;
            padding: 20px;
        }

        .location-overlay h3 {
            color: white;
            margin-bottom: 20px;
            font-size: 24px;
        }

        .location-overlay p {
            font-size: 16px;
            margin-bottom: 30px;
            max-width: 600px;
            line-height: 1.5;
        }

        .location-overlay .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .form-disabled {
            opacity: 0.6;
            pointer-events: none;
        }

        .permission-guide {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            max-width: 500px;
            text-align: left;
        }

        .permission-guide h6 {
            color: #4fc3f7;
            margin-bottom: 10px;
        }

        .permission-guide ol {
            margin-bottom: 0;
            padding-left: 20px;
        }

        .permission-guide li {
            margin-bottom: 8px;
        }

        .form-container {
            position: relative;
        }

        .instance-header-fixed {
            position: fixed;
            left: 0;
            right: 0;
            z-index: 1050;
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        }

        .instance-header-placeholder {
            display: none;
        }

        .form-blocked {
            opacity: 0.5;
            pointer-events: none;
        }
        
         @media (max-width: 767px) {
            .swal2-popup {
                width: 80vw !important;
                padding: 24px 16px !important;
            }

            .swal2-title {
                font-size: 16px !important;
            }

            .swal2-loader {
                border-color: #111 transparent #111 transparent !important;
            }
        }
    </style>
@endsection

@section('content')
    <!-- Location Permission Overlay -->
    <div id="locationOverlay" class="location-overlay">
        <div class="spinner" id="locationSpinner"></div>
        <h3>Location Permission Required</h3>
        <p id="locationMessage">Please allow location access to continue filling the form. This is required for all
            submissions.</p>
        <div id="permissionDeniedGuide" class="permission-guide d-none">
            <h6>📱 How to enable location permission:</h6>
            <ol>
                <li>Click the lock icon (🔒) in your browser's address bar</li>
                <li>Find "Location" in the permissions list</li>
                <li>Change from "Block" to "Allow"</li>
                <li>Refresh this page or click "Retry Location Access"</li>
            </ol>
        </div>
        <button id="retryLocationBtn" class="btn btn-primary btn-lg mt-3">
            <i class="fa fa-refresh"></i> Retry Location Access
        </button>
        <div class="mt-4">
            <small class="text-muted">Note: Location access is mandatory for form submission</small>
        </div>
    </div>

    <div class="page-body form-container" id="formContainer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    {{-- Fixed instance header lives OUTSIDE the card so card overflow:hidden can't clip it --}}
                    <div class="p-0 instance-header-fixed" id="instanceHeaderFixed" style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 60%,#0f3460 100%); border-bottom:none;">
                            @php
                                $activityName = $activity->activity_name ?? 'Activity';
                            @endphp
                            {{-- Top bar: activity name + action buttons --}}
                            <div class="d-flex align-items-center justify-content-between px-3 py-2" style="border-bottom:1px solid rgba(255,255,255,0.1);">
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;">
                                        <i class="fa fa-clipboard-list text-white" style="font-size:14px;"></i>
                                    </div>
                                    <div>
                                        <div style="color:rgba(255,255,255,0.6);font-size:10px;text-transform:uppercase;letter-spacing:1px;">Activity</div>
                                        <div style="color:#fff;font-size:13px;font-weight:600;">{{ $activityName }}</div>
                                    </div>
                                </div>
                                @if (!empty($allow_repeat))
                                    <div class="d-flex align-items-center gap-2">
                                        @if (!$is_closed)
                                            <button type="button" id="addInstanceBtn"
                                                style="background:{{ $original_submitted ? 'rgba(40,167,69,0.9)' : 'rgba(108,117,125,0.5)' }};border:none;color:#fff;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;display:flex;align-items:center;gap:5px;cursor:{{ $original_submitted ? 'pointer' : 'not-allowed' }};"
                                                title="{{ $original_submitted ? 'Add new instance' : 'Save the original first before adding a new instance' }}"
                                                {{ $original_submitted ? '' : 'disabled' }}>
                                                <i class="fa fa-plus" style="font-size:11px;"></i> Add
                                            </button>
                                            <button type="button" id="submitActivityBtn"
                                                style="background:rgba(220,53,69,0.9);border:none;color:#fff;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;display:flex;align-items:center;gap:5px;">
                                                <i class="fa fa-check" style="font-size:11px;"></i> Submit
                                            </button>
                                        @else
                                            <div style="background:rgba(40,167,69,0.2);border:1px solid rgba(40,167,69,0.5);border-radius:20px;padding:5px 14px;display:flex;align-items:center;gap:6px;">
                                                <i class="fa fa-check-circle" style="color:#28a745;font-size:13px;"></i>
                                                <span style="color:#28a745;font-size:12px;font-weight:600;">Closed</span>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Instance navigation bar (only for repeat activities) --}}
                            @if (!empty($allow_repeat))
                                @php
                                    $currentSeq = $activity_sequence ?? 0;
                                    $prevSeq = null;
                                    $nextSeq = null;
                                    $navSequences = array_merge([0], $all_instances->pluck('activity_sequence')->toArray());
                                    $navIdx = array_search($currentSeq, $navSequences);
                                    if ($navIdx !== false) {
                                        if ($navIdx > 0) $prevSeq = $navSequences[$navIdx - 1];
                                        if ($navIdx < count($navSequences) - 1) $nextSeq = $navSequences[$navIdx + 1];
                                    }
                                    $totalNav = count($navSequences);
                                    $instanceLabel = $currentSeq == 0 ? 'Original' : ('Instance ' . ($currentSeq + 1));
                                @endphp
                                <div class="d-flex align-items-center justify-content-center gap-3 px-3 py-2">
                                    @if ($prevSeq !== null)
                                        <a href="{{ route('user.project.row_id.activity', ['row_id' => $row_data->id, 'activity' => $activity->id, 'group_info' => $group_info->id ?? null, 'activity_sequence' => $prevSeq]) }}"
                                           style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;text-decoration:none;color:#fff;font-size:13px;transition:background 0.2s;"
                                           onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">
                                            <i class="fa fa-chevron-left"></i>
                                        </a>
                                    @else
                                        <div style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,0.2);font-size:13px;">
                                            <i class="fa fa-chevron-left"></i>
                                        </div>
                                    @endif

                                    <div style="text-align:center;">
                                        <div style="color:rgba(255,255,255,0.5);font-size:10px;text-transform:uppercase;letter-spacing:0.8px;">Viewing</div>
                                        <div style="color:#fff;font-size:14px;font-weight:700;">{{ $instanceLabel }}</div>
                                        <div style="color:rgba(255,255,255,0.4);font-size:10px;">{{ $navIdx + 1 }} of {{ $totalNav }}</div>
                                    </div>

                                    @if ($nextSeq !== null)
                                        <a href="{{ route('user.project.row_id.activity', ['row_id' => $row_data->id, 'activity' => $activity->id, 'group_info' => $group_info->id ?? null, 'activity_sequence' => $nextSeq]) }}"
                                           style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;text-decoration:none;color:#fff;font-size:13px;transition:background 0.2s;"
                                           onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">
                                            <i class="fa fa-chevron-right"></i>
                                        </a>
                                    @else
                                        <div style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,0.2);font-size:13px;">
                                            <i class="fa fa-chevron-right"></i>
                                        </div>
                                    @endif
                                </div>

                                {{-- Instance dots indicator --}}
                                @if ($totalNav > 1)
                                    <div class="d-flex justify-content-center gap-1 pb-2">
                                        @foreach ($navSequences as $seq)
                                            <div style="width:{{ $seq == $currentSeq ? '20px' : '6px' }};height:6px;border-radius:3px;background:{{ $seq == $currentSeq ? '#4a6cf7' : 'rgba(255,255,255,0.25)' }};transition:all 0.3s;"></div>
                                        @endforeach
                                    </div>
                                @endif
                            @endif
                    </div>{{-- /instanceHeaderFixed --}}

                    <div class="card" style="border-radius:12px; overflow:hidden;">
                        {{-- Placeholder reserves vertical space the fixed header occupies --}}
                        <div class="instance-header-placeholder" id="instanceHeaderPlaceholder"></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-xl-6 col-md-6 d-none">
                                    <div class="table-card">
                                        <h5>Distributor Details</h5>
                                        <table class="table mb-0">
                                            <thead></thead>
                                            <tbody>
                                                @foreach ($template_name_values as $row_val)
                                                    <tr>
                                                        <td class="fw-medium">{{ $row_val['template_head_name'] }}</td>
                                                        <td>{{ $row_val['value'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="col-12 col-md-12 col-lg-6 mb-3">
                                    <div class="card">
                                        <div class="table-card table-responsive">

                                            <h5 class="mt-2 p-2"
                                                style="color:#fff !important;  margin-top: 40px !important;">Activity
                                                Questions</h5>

                                            {{-- ── Sent Back Warning Banner ── --}}
                                            @if (!empty($is_sent_back) && $is_sent_back)
                                                <div class="alert d-flex align-items-center gap-2 mx-3 mt-2"
                                                    style="background:#fff3cd; border:1.5px solid #ffc107; border-radius:8px; padding:10px 14px; font-size:13px;">
                                                    <span style="font-size:18px;">⚠️</span>
                                                    <div>
                                                        <strong>This submission was sent back for correction.</strong><br>
                                                        <span class="text-muted" style="font-size:12px;">Please review your
                                                            answers below and resubmit.</span>
                                                    </div>
                                                </div>
                                            @endif

                                            {{-- ── Repeat Instance Banner ── --}}
                                            @if (!empty($activity_sequence))
                                                <div class="alert d-flex align-items-center gap-2 mx-3 mt-2"
                                                    style="background:#e7f1ff; border:1.5px solid #4a6cf7; border-radius:8px; padding:10px 14px; font-size:13px;">
                                                    <span style="font-size:18px;">📝</span>
                                                    <div>
                                                        <strong>Additional Submission{{ isset($repeat_instance) && $repeat_instance ? ': ' . $repeat_instance->instance_label : '' }}</strong><br>
                                                        <span class="text-muted" style="font-size:12px;">You are filling a new set of
                                                            answers for this activity. The original submission is unaffected.</span>
                                                    </div>
                                                </div>
                                            @endif
                                            
                                            @if (session()->has('error'))
                                                <div class="alert d-flex align-items-center gap-2 mx-3 mt-2"
                                                    style="background:#f8d7da; border:1.5px solid #f5c6cb; border-radius:8px; padding:10px 14px; font-size:13px;">
                                                    <span style="font-size:18px;">❌</span>
                                                    <div>
                                                        <strong>Submission Failed</strong><br>
                                                        <span class="text-muted" style="font-size:12px;">{{ session('error') }}</span>
                                                    </div>
                                                </div>
                                            @endif

                                            <form id="activityForm" method="post"
                                                action="{{ route('user.rowId.activity.answers', ['activity_sequence' => $activity_sequence ?? 0]) }}"
                                                enctype="multipart/form-data">
                                                @csrf
                                                @if (isset($group_info->id))
                                                    <input type="hidden" name="group_id" value="{{ $group_info->id }}">
                                                @endif
                                                <input type="hidden" name="row_id"
                                                    value="{{ $row_data->id ?? old('row_id') }}">
                                                <input type="hidden" name="activity_id"
                                                    value="{{ $related_questions->first()->activity_id }}">
                                                <input type="hidden" name="activity_sequence"
                                                    value="{{ $activity_sequence ?? 0 }}">
                                                @if (isset($activity_group_info))
                                                    <input type="hidden" name="activity_group_id"
                                                        value="{{ $activity_group_info->id ?? old('activity_group_id') }}">
                                                @endif

                                                {{-- ── Build prefill lookup from sent-back answers ── --}}
                                                @php
                                                    $sub_question_child_ids = $sub_question_child_ids ?? [];
                                                    $prefilled = [];
                                                    $prefilledSub = [];
                                                    $prefilledSubDependent = [];

                                                    // Flatten all questions (main + sub-children) for prefill lookup
                                                    $all_questions_flat = $related_questions->flatMap(function ($q) {
                                                        return collect([$q])->merge($q->subQuestions->map(fn($l) => $l->childQuestion)->filter());
                                                    });

                                                    if (!empty($answer_data) && $answer_data->count()) {
                                                        foreach ($answer_data as $ans) {
                                                            $qid = $ans->question_id;
                                                            $question = $all_questions_flat->firstWhere('id', $qid);

                                                            // Check if this is a subjective question
                                                            if (
                                                                $question &&
                                                                $question->question_type === 'Subjective'
                                                            ) {
                                                                // For subjective questions, check if the answer matches any subject name
                                                                $isSubjectValue = false;
                                                                if (!empty($question->getSubjects)) {
                                                                    foreach ($question->getSubjects as $subject) {
                                                                        if ($ans->user_answer === $subject->subject) {
                                                                            $isSubjectValue = true;
                                                                            break;
                                                                        }
                                                                    }
                                                                }

                                                                if ($isSubjectValue) {
                                                                    // This is the main subject answer
                                                                    $prefilled[$qid] = $ans->user_answer;
                                                                } else {
                                                                    // This is a dependent answer
                                                                    if (!isset($prefilledSubDependent[$qid])) {
                                                                        $prefilledSubDependent[$qid] = [];
                                                                    }
                                                                    $prefilledSubDependent[$qid][] = $ans->user_answer;
                                                                }
                                                            } else {
                                                                // For non-subjective questions
                                                                if (isset($prefilledSub[$qid])) {
                                                                    $prefilledSub[$qid][] = $ans->user_answer;
                                                                } elseif (isset($prefilled[$qid])) {
                                                                    $prefilledSub[$qid] = [
                                                                        $prefilled[$qid],
                                                                        $ans->user_answer,
                                                                    ];
                                                                    unset($prefilled[$qid]);
                                                                } else {
                                                                    $prefilled[$qid] = $ans->user_answer;
                                                                }
                                                            }
                                                        }
                                                    }
                                                    $selectArrIds = [];
                                                @endphp

                                                <table class="table mb-0">
                                                    <thead></thead>
                                                    <tbody>
                                                        @foreach ($related_questions as $related_question)
                                                            @php
                                                                $qid = $related_question->id;
                                                                // Skip questions that appear as sub-children — rendered under their parent below
                                                                if (in_array($qid, $sub_question_child_ids)) continue;
                                                                $savedAnswer = $prefilled[$qid] ?? '';
                                                                $savedSub = $prefilledSub[$qid] ?? [];
                                                            @endphp

                                                            <tr class="mt-2 question-row {{ $related_question->is_parent == 0 ? 'child-question d-none' : '' }} {{ $related_question->question_type === 'Multi Response' ? 'outlet-section-header' : '' }}"
                                                                data-question-id="{{ $qid }}"
                                                                data-parent-id="{{ $related_question->parent_question_id }}"
                                                                data-parent-value="{{ $related_question->parent_value }}"
                                                                @if($related_question->question_type === 'Multi Response')
                                                                style="background:#e8eaf6; border-left:5px solid #3949ab; border-top:2px solid #c5cae9;"
                                                                @endif>
                                                                <td>
                                                                    <div class="col-sm-4 col-md-6 fw-medium mt-2">
                                                                        {{ $related_question->question }}
                                                                        @if ($related_question->answer_type)
                                                                            <span class="txt-danger">*</span>
                                                                        @endif
                                                                    </div>
                                                                    <div class="col-sm-6 col-md-6 mt-2">

                                                                        @switch($related_question->question_type)
                                                                            @case('Image')
                                                                                @if ($related_question->allow_multiple_images)
                                                                                    @php
                                                                                        $savedPaths = [];
                                                                                        if ($savedAnswer) {
                                                                                            $decoded = json_decode($savedAnswer, true);
                                                                                            $savedPaths = is_array($decoded) ? $decoded : [$savedAnswer];
                                                                                        }
                                                                                    @endphp
                                                                                    <div class="multi-image-wrapper"
                                                                                         data-qid="{{ $qid }}"
                                                                                         data-upload-url="{{ route('upload.activity.image.temp') }}"
                                                                                         data-row-id="{{ $row_data->id }}">

                                                                                        {{-- Already-saved images (revisit) --}}
                                                                                        @foreach ($savedPaths as $sp)
                                                                                            <div class="multi-img-row d-flex align-items-center gap-2 mb-2 saved-img-row">
                                                                                                <img src="{{ asset($sp) }}"
                                                                                                     style="width:56px;height:56px;object-fit:cover;border-radius:6px;border:1px solid #ccc;flex-shrink:0;">
                                                                                                <span class="text-muted" style="font-size:12px;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                                                                                    {{ basename($sp) }}
                                                                                                </span>
                                                                                                <button type="button" class="btn btn-outline-danger btn-sm remove-saved-img-btn flex-shrink-0"
                                                                                                        style="width:32px;height:32px;padding:0;font-size:16px;line-height:1;" title="Remove">×</button>
                                                                                                <input type="hidden" name="multi_images_{{ $qid }}[]" value="{{ $sp }}">
                                                                                            </div>
                                                                                        @endforeach

                                                                                        {{-- New file input rows --}}
                                                                                        <div class="multi-img-inputs" id="inputs_{{ $qid }}">
                                                                                            <div class="multi-img-row d-flex align-items-center gap-2 mb-2">
                                                                                                {{-- Preview thumbnail (hidden until file selected) --}}
                                                                                                <div class="img-preview-box flex-shrink-0"
                                                                                                     style="width:56px;height:56px;border-radius:6px;border:2px dashed #ccc;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#f8f9fa;">
                                                                                                    <span class="preview-placeholder text-muted" style="font-size:20px;">🖼</span>
                                                                                                </div>
                                                                                                <input type="file"
                                                                                                       class="form-control form-control-sm multi-image-file-input"
                                                                                                       accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                                                                                       style="flex:1;">
                                                                                                <button type="button"
                                                                                                        class="btn btn-outline-secondary btn-sm add-image-row-btn flex-shrink-0"
                                                                                                        data-qid="{{ $qid }}"
                                                                                                        style="width:32px;height:32px;padding:0;font-size:20px;line-height:1;">+</button>
                                                                                            </div>
                                                                                        </div>

                                                                                        {{-- Upload progress bar (shown on save) --}}
                                                                                        <div class="multi-upload-progress d-none mt-1" id="progress_{{ $qid }}">
                                                                                            <div class="d-flex align-items-center gap-2">
                                                                                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                                                                                <small class="text-muted">Uploading images, please wait…</small>
                                                                                            </div>
                                                                                        </div>

                                                                                        @if ($related_question->answer_type && empty($savedPaths))
                                                                                            <input type="hidden" class="multi-image-required" data-qid="{{ $qid }}">
                                                                                        @endif
                                                                                    </div>
                                                                                @else
                                                                                    @if ($savedAnswer)
                                                                                        <div class="mb-2">
                                                                                            <img src="{{ asset($savedAnswer) }}"
                                                                                                alt="Existing image"
                                                                                                style="max-width:100%; max-height:160px; border-radius:6px; border:1px solid #ddd;">
                                                                                            <small class="text-muted d-block mt-1">Current image — upload a new one to replace it</small>
                                                                                        </div>
                                                                                        <input type="hidden" name="{{ $qid }}_existing" value="{{ $savedAnswer }}">
                                                                                    @endif
                                                                                    <input name="{{ $qid }}" type="file"
                                                                                        class="form-control"
                                                                                        @if ($related_question->answer_type && !$savedAnswer) required @endif>
                                                                                @endif
                                                                            @break

                                                                            @case('Free Text')
                                                                                <input type="text" value="{{ $savedAnswer }}"
                                                                                    name="{{ $qid }}" class="form-control"
                                                                                    @if ($related_question->answer_type) required data-required="true" @endif>
                                                                            @break

                                                                            @case('Yes / No')
                                                                                <select
                                                                                    class="form-select {{ $related_question->is_parent == 1 ? 'parent-question' : '' }}"
                                                                                    data-question-id="{{ $qid }}"
                                                                                    name="{{ $qid }}"
                                                                                    @if ($related_question->answer_type) required data-required="true" @endif>
                                                                                    <option value="">Select ..</option>
                                                                                    <option value="Yes"
                                                                                        @if ($savedAnswer === 'Yes') selected @endif>
                                                                                        Yes</option>
                                                                                    <option value="No"
                                                                                        @if ($savedAnswer === 'No') selected @endif>
                                                                                        No</option>
                                                                                </select>
                                                                            @break

                                                                            @case('Dropdown')
                                                                                <select
                                                                                    class="form-select {{ $related_question->is_parent == 1 ? 'parent-question' : '' }}"
                                                                                    data-question-id="{{ $qid }}"
                                                                                    name="{{ $qid }}"
                                                                                    @if ($related_question->answer_type) required data-required="true" @endif>
                                                                                    <option value="">Select Dropdown</option>
                                                                                    @foreach ($related_question->getOptions as $questionOption)
                                                                                        <option
                                                                                            value="{{ $questionOption->option }}"
                                                                                            @if ($savedAnswer === $questionOption->option) selected @endif>
                                                                                            {{ $questionOption->option }}
                                                                                        </option>
                                                                                    @endforeach
                                                                                </select>
                                                                            @break

                                                                            @case('Multi select')
                                                                                @php
                                                                                    $selectArrIds[] = 'select' . $qid;
                                                                                    $savedMulti = $savedAnswer
                                                                                        ? explode(',', $savedAnswer)
                                                                                        : [];
                                                                                @endphp
                                                                                <select class="form-select"
                                                                                    name="{{ $qid }}[]"
                                                                                    id="select{{ $qid }}" multiple
                                                                                    @if ($related_question->answer_type) required data-required="true" @endif>
                                                                                    <option value="">Select Options</option>
                                                                                    @foreach ($related_question->getOptions as $questionOption)
                                                                                        <option
                                                                                            value="{{ $questionOption->option }}"
                                                                                            @if (in_array($questionOption->option, $savedMulti)) selected @endif>
                                                                                            {{ $questionOption->option }}
                                                                                        </option>
                                                                                    @endforeach
                                                                                </select>
                                                                            @break

                                                                            @case('Date')
                                                                                @php
                                                                                    $savedDate = '';
                                                                                    if ($savedAnswer) {
                                                                                        try {
                                                                                            if (
                                                                                                preg_match(
                                                                                                    '/^\d{2}\/\d{2}\/\d{4}$/',
                                                                                                    $savedAnswer,
                                                                                                )
                                                                                            ) {
                                                                                                $savedDate = \Carbon\Carbon::createFromFormat(
                                                                                                    'd/m/Y',
                                                                                                    $savedAnswer,
                                                                                                )->format('Y-m-d');
                                                                                            } else {
                                                                                                $savedDate = \Carbon\Carbon::parse(
                                                                                                    $savedAnswer,
                                                                                                )->format('Y-m-d');
                                                                                            }
                                                                                        } catch (\Exception $e) {
                                                                                            $savedDate = '';
                                                                                        }
                                                                                    }
                                                                                @endphp
                                                                                <input type="date" value="{{ $savedDate }}"
                                                                                    name="{{ $qid }}" class="form-control"
                                                                                    @if ($related_question->answer_type) required data-required="true" @endif>
                                                                            @break

                                                                            @case('Date & Time')
                                                                                @php
                                                                                    $savedDT = '';
                                                                                    if ($savedAnswer) {
                                                                                        // Stored as d/m/Y H:i — try explicit format first, then fallback to parse
                                                                                        try {
                                                                                            $savedDT = \Carbon\Carbon::createFromFormat('d/m/Y H:i', $savedAnswer)->format('Y-m-d\TH:i');
                                                                                        } catch (\Exception $e) {
                                                                                            try {
                                                                                                $savedDT = \Carbon\Carbon::parse($savedAnswer)->format('Y-m-d\TH:i');
                                                                                            } catch (\Exception $e2) {
                                                                                                $savedDT = $savedAnswer;
                                                                                            }
                                                                                        }
                                                                                    }
                                                                                @endphp
                                                                                <input type="datetime-local"
                                                                                    value="{{ $savedDT }}"
                                                                                    name="{{ $qid }}" class="form-control"
                                                                                    @if ($related_question->answer_type) required data-required="true" @endif>
                                                                            @break

                                                                            @case('Location')
                                                                                <input type="text"
                                                                                    id="location_{{ $qid }}"
                                                                                    name="{{ $qid }}"
                                                                                    class="form-control get_location"
                                                                                    value="{{ $savedAnswer }}"
                                                                                    @if ($related_question->answer_type) required data-required="true" @endif
                                                                                    placeholder="get location" readonly>
                                                                                <button type="button" class="btn btn-info"
                                                                                    id="get_location_{{ $qid }}"
                                                                                    data-id="{{ $qid }}">
                                                                                    {{ $savedAnswer ? 'Update Location' : 'Get Location' }}
                                                                                </button>
                                                                                <span class="p-3"
                                                                                    id="location_status_{{ $qid }}"></span>
                                                                            @break

                                                                            @case('File Upload')
                                                                                @if ($savedAnswer)
                                                                                    <div class="mb-2">
                                                                                        <a href="{{ asset($savedAnswer) }}"
                                                                                            target="_blank"
                                                                                            class="btn btn-sm btn-outline-secondary">📎
                                                                                            View existing file</a>
                                                                                        <small
                                                                                            class="text-muted d-block mt-1">Upload
                                                                                            a new file to replace it</small>
                                                                                    </div>
                                                                                    <input type="hidden"
                                                                                        name="{{ $qid }}_existing"
                                                                                        value="{{ $savedAnswer }}">
                                                                                @endif
                                                                                <input type="file" name="{{ $qid }}"
                                                                                    class="form-control"
                                                                                    @if ($related_question->answer_type && !$savedAnswer) required @endif>
                                                                            @break

                                                                            @case('Audio')
                                                                                <div class="audio-recorder recording-section">
                                                                                    <h6>Record Audio</h6>
                                                                                    @if ($savedAnswer)
                                                                                        <div class="mb-2">
                                                                                            <small class="text-muted">Existing
                                                                                                recording:</small>
                                                                                            <audio controls class="w-100 mt-1"
                                                                                                style="max-width:280px;">
                                                                                                <source
                                                                                                    src="{{ asset($savedAnswer) }}">
                                                                                            </audio>
                                                                                            <small
                                                                                                class="text-muted d-block">Record a
                                                                                                new one to replace it</small>
                                                                                        </div>
                                                                                    @endif
                                                                                    <div class="btn-group mb-2">
                                                                                        <button type="button"
                                                                                            class="btn btn-sm btn-primary start-audio-recording"
                                                                                            data-id="{{ $qid }}">
                                                                                            <i class="fa fa-microphone"></i> Start
                                                                                            Recording
                                                                                        </button>
                                                                                        <button type="button"
                                                                                            class="btn btn-sm btn-danger stop-audio-recording"
                                                                                            data-id="{{ $qid }}"
                                                                                            disabled>
                                                                                            <i class="fa fa-stop"></i> Stop
                                                                                        </button>
                                                                                        <button type="button"
                                                                                            class="btn btn-sm btn-warning delete-audio-recording"
                                                                                            data-id="{{ $qid }}"
                                                                                            disabled>
                                                                                            <i class="fa fa-trash"></i> Delete
                                                                                        </button>
                                                                                    </div>
                                                                                    <div id="audio-recording-status-{{ $qid }}"
                                                                                        class="recording-status"></div>
                                                                                    <audio id="audio-player-{{ $qid }}"
                                                                                        controls class="d-none mt-2 w-100"></audio>
                                                                                    <input type="hidden"
                                                                                        name="{{ $qid }}"
                                                                                        class="audio-data"
                                                                                        data-id="{{ $qid }}"
                                                                                        value="{{ $savedAnswer ?? '' }}">
                                                                                </div>
                                                                            @break

                                                                            @case('Video')
                                                                                <div class="video-recorder recording-section">
                                                                                    <h6>Record Video</h6>
                                                                                    @if ($savedAnswer)
                                                                                        <div class="mb-2">
                                                                                            <small class="text-muted">Existing
                                                                                                recording:</small>
                                                                                            <video controls preload="metadata"
                                                                                                style="max-width:100%; max-height:180px; border-radius:4px;"
                                                                                                class="d-block mt-1">
                                                                                                <source
                                                                                                    src="{{ asset($savedAnswer) }}">
                                                                                            </video>
                                                                                            <small
                                                                                                class="text-muted d-block">Record a
                                                                                                new one to replace it</small>
                                                                                        </div>
                                                                                        {{-- Keep existing path as fallback --}}
                                                                                        <input type="hidden"
                                                                                            name="{{ $qid }}_existing_path"
                                                                                            value="{{ $savedAnswer }}">
                                                                                    @endif
                                                                                    <div class="video-preview-container">
                                                                                        <video
                                                                                            id="video-preview-{{ $qid }}"
                                                                                            class="video-preview d-none"
                                                                                            controls></video>
                                                                                    </div>
                                                                                    <div class="btn-group mb-2">
                                                                                        <button type="button"
                                                                                            class="btn btn-sm btn-primary start-video-recording"
                                                                                            data-id="{{ $qid }}">
                                                                                            <i class="fa fa-video-camera"></i>
                                                                                            Start Recording
                                                                                        </button>
                                                                                        <button type="button"
                                                                                            class="btn btn-sm btn-danger stop-video-recording"
                                                                                            data-id="{{ $qid }}"
                                                                                            disabled>
                                                                                            <i class="fa fa-stop"></i> Stop
                                                                                        </button>
                                                                                        <button type="button"
                                                                                            class="btn btn-sm btn-warning delete-video-recording"
                                                                                            data-id="{{ $qid }}"
                                                                                            disabled>
                                                                                            <i class="fa fa-trash"></i> Delete
                                                                                        </button>
                                                                                    </div>
                                                                                    <div id="video-recording-status-{{ $qid }}"
                                                                                        class="recording-status"></div>
                                                                                    <input type="hidden"
                                                                                        name="{{ $qid }}"
                                                                                        class="video-data"
                                                                                        data-id="{{ $qid }}"
                                                                                        value="{{ $savedAnswer ?? '' }}">
                                                                                </div>
                                                                            @break

                                                                            @case('Subjective')
                                                                                @php
                                                                                    $subjectSaved =
                                                                                        $prefilled[$qid] ?? '';
                                                                                    // Get dependent answers for this subjective question
                                                                                    $dependentAnswers =
                                                                                        $prefilledSubDependent[$qid] ??
                                                                                        [];
                                                                                @endphp
                                                                                <div class="d-flex flex-column gap-2">
                                                                                    <select class="form-select subjective_ques"
                                                                                        @if ($related_question->answer_type) required data-required="true" @endif
                                                                                        data-q_id="{{ $qid }}"
                                                                                        name="{{ $qid }}">
                                                                                        <option value="">Select Subject ..
                                                                                        </option>
                                                                                        @foreach ($related_question->getSubjects as $questionSubjects)
                                                                                            <option
                                                                                                value="{{ $questionSubjects->subject }}"
                                                                                                @if ($subjectSaved === $questionSubjects->subject) selected @endif>
                                                                                                {{ $questionSubjects->subject }}
                                                                                            </option>
                                                                                        @endforeach
                                                                                    </select>

                                                                                    @if (!empty($related_question->getSubjects))
                                                                                        @foreach ($related_question->getSubjects as $index => $subject)
                                                                                            @php
                                                                                                // Match dependent answer by index or find matching one
                                                                                                // $depVal =
                                                                                                //     $dependentAnswers[
                                                                                                //         $index
                                                                                                //     ] ?? '';

                                                                                                $depVal =
                                                                                                    $subjectSaved ===
                                                                                                    $subject->subject
                                                                                                        ? $dependentAnswers[0] ??
                                                                                                            ''
                                                                                                        : '';
                                                                                            @endphp
                                                                                            @switch($subject->answer_type)
                                                                                                @case('Audio')
                                                                                                    <div
                                                                                                        class="audio-recorder recording-section {{ $subjectSaved === $subject->subject ? '' : 'd-none' }} Audio{{ $subject->id }}">
                                                                                                        <!-- ... rest of the audio code remains same but use $depVal -->
                                                                                                        @if ($depVal)
                                                                                                            <audio controls
                                                                                                                class="w-100 mb-2"
                                                                                                                style="max-width:280px;">
                                                                                                                <source
                                                                                                                    src="{{ asset($depVal) }}">
                                                                                                            </audio>
                                                                                                        @endif
                                                                                                        <input type="hidden"
                                                                                                            name="{{ $qid }}Audio"
                                                                                                            class="audio-data"
                                                                                                            data-id="subjective_{{ $qid }}_{{ $subject->id }}"
                                                                                                            value="{{ $depVal ? asset($depVal) : '' }}">
                                                                                                    </div>
                                                                                                @break

                                                                                                @case('Video')
                                                                                                    <div
                                                                                                        class="video-recorder recording-section {{ $subjectSaved === $subject->subject ? '' : 'd-none' }} Video{{ $subject->id }}">
                                                                                                        @if ($depVal)
                                                                                                            <video controls
                                                                                                                preload="metadata"
                                                                                                                style="max-width:100%; max-height:180px;"
                                                                                                                class="d-block mb-2 rounded">
                                                                                                                <source
                                                                                                                    src="{{ asset($depVal) }}">
                                                                                                            </video>
                                                                                                        @endif
                                                                                                        <input type="hidden"
                                                                                                            name="{{ $qid }}Video"
                                                                                                            class="video-data"
                                                                                                            data-id="subjective_{{ $qid }}_{{ $subject->id }}"
                                                                                                            value="{{ $depVal ? asset($depVal) : '' }}">
                                                                                                    </div>
                                                                                                @break

                                                                                                @case('Yes / No')
                                                                                                    <select
                                                                                                        class="form-select {{ $subjectSaved === $subject->subject ? '' : 'd-none' }} Yes/No{{ $subject->id }}"
                                                                                                        name="{{ $qid }}YesNo"
                                                                                                        id="Yes/No{{ $qid }}"
                                                                                                        data-original-value="{{ $depVal }}">
                                                                                                        <option value="">Select
                                                                                                        </option>
                                                                                                        <option value="Yes"
                                                                                                            @if ($depVal === 'Yes') selected @endif>
                                                                                                            Yes</option>
                                                                                                        <option value="No"
                                                                                                            @if ($depVal === 'No') selected @endif>
                                                                                                            No</option>
                                                                                                    </select>
                                                                                                @break

                                                                                                @case('Date')
                                                                                                    @php
                                                                                                        $dv = '';
                                                                                                        if ($depVal) {
                                                                                                            try {
                                                                                                                if (
                                                                                                                    preg_match(
                                                                                                                        '/^\d{2}\/\d{2}\/\d{4}$/',
                                                                                                                        $depVal,
                                                                                                                    )
                                                                                                                ) {
                                                                                                                    $dv = \Carbon\Carbon::createFromFormat(
                                                                                                                        'd/m/Y',
                                                                                                                        $depVal,
                                                                                                                    )->format(
                                                                                                                        'Y-m-d',
                                                                                                                    );
                                                                                                                } else {
                                                                                                                    $dv = \Carbon\Carbon::parse(
                                                                                                                        $depVal,
                                                                                                                    )->format(
                                                                                                                        'Y-m-d',
                                                                                                                    );
                                                                                                                }
                                                                                                            } catch (\Exception $e) {
                                                                                                                $dv =
                                                                                                                    '';
                                                                                                            }
                                                                                                        }
                                                                                                    @endphp
                                                                                                    <input type="date"
                                                                                                        value="{{ $dv }}"
                                                                                                        name="{{ $qid }}Date"
                                                                                                        id="Date{{ $qid }}"
                                                                                                        data-original-value="{{ $dv }}"
                                                                                                        class="form-control {{ $subjectSaved === $subject->subject ? '' : 'd-none' }} Date{{ $subject->id }}"
                                                                                                        placeholder="select date">
                                                                                                @break

                                                                                                @case('Date & Time')
                                                                                                    @php
                                                                                                        $dtv = '';
                                                                                                        if ($depVal) {
                                                                                                            try {
                                                                                                                $dtv = \Carbon\Carbon::createFromFormat('d/m/Y H:i', $depVal)->format('Y-m-d\TH:i');
                                                                                                            } catch (\Exception $e) {
                                                                                                                try {
                                                                                                                    $dtv = \Carbon\Carbon::parse($depVal)->format('Y-m-d\TH:i');
                                                                                                                } catch (\Exception $e2) {
                                                                                                                    $dtv = $depVal;
                                                                                                                }
                                                                                                            }
                                                                                                        }
                                                                                                    @endphp
                                                                                                    <input type="datetime-local"
                                                                                                        value="{{ $dtv }}"
                                                                                                        name="{{ $qid }}Date&Time"
                                                                                                        id="Date&Time{{ $qid }}"
                                                                                                        data-original-value="{{ $dtv }}"
                                                                                                        class="form-control {{ $subjectSaved === $subject->subject ? '' : 'd-none' }} Date&Time{{ $subject->id }}"
                                                                                                        placeholder="select date-time">
                                                                                                @break

                                                                                                @case('Dropdown')
                                                                                                    <select
                                                                                                        class="form-select {{ $subjectSaved === $subject->subject ? '' : 'd-none' }} Dropdown{{ $subject->id }}"
                                                                                                        name="{{ $qid }}Dropdown"
                                                                                                        id="Dropdown{{ $qid }}"
                                                                                                        data-original-value="{{ $depVal }}">
                                                                                                        <option value="">Select
                                                                                                        </option>
                                                                                                        @foreach ($subject->getOptions as $questionOption)
                                                                                                            <option
                                                                                                                value="{{ $questionOption->option }}"
                                                                                                                @if ($depVal === $questionOption->option) selected @endif>
                                                                                                                {{ $questionOption->option }}
                                                                                                            </option>
                                                                                                        @endforeach
                                                                                                    </select>
                                                                                                @break

                                                                                                @case('File Upload')
                                                                                                    @if ($depVal)
                                                                                                        <a href="{{ asset($depVal) }}"
                                                                                                            target="_blank"
                                                                                                            class="btn btn-sm btn-outline-secondary mb-1 {{ $subjectSaved === $subject->subject ? '' : 'd-none' }}">
                                                                                                            📎 View existing file
                                                                                                        </a>
                                                                                                        <input type="hidden"
                                                                                                            name="{{ $qid }}FileUpload_existing"
                                                                                                            value="{{ $depVal }}">
                                                                                                    @endif
                                                                                                    <span
                                                                                                        class="badge text-dark h-25 {{ $subjectSaved === $subject->subject ? '' : 'd-none' }} FileUpload{{ $subject->id }}">Select
                                                                                                        File</span>
                                                                                                    <input type="file"
                                                                                                        name="{{ $qid }}FileUpload"
                                                                                                        id="FileUpload{{ $qid }}"
                                                                                                        class="form-control {{ $subjectSaved === $subject->subject ? '' : 'd-none' }} FileUpload{{ $subject->id }}"
                                                                                                        placeholder="select file">
                                                                                                @break

                                                                                                @case('Free Text')
                                                                                                    <input type="text"
                                                                                                        value="{{ $depVal }}"
                                                                                                        name="{{ $qid }}FreeText"
                                                                                                        data-original-value="{{ $depVal }}"
                                                                                                        id="FreeText{{ $qid }}"
                                                                                                        class="form-control {{ $subjectSaved === $subject->subject ? '' : 'd-none' }} FreeText{{ $subject->id }}"
                                                                                                        placeholder="add free text">
                                                                                                @break

                                                                                                @case('Image')
                                                                                                    @if ($depVal)
                                                                                                        <img src="{{ asset($depVal) }}"
                                                                                                            alt="Existing"
                                                                                                            class="{{ $subjectSaved === $subject->subject ? '' : 'd-none' }} mb-1"
                                                                                                            style="max-width:100%; max-height:150px; border-radius:4px;">
                                                                                                        <input type="hidden"
                                                                                                            name="{{ $qid }}Image_existing"
                                                                                                            value="{{ $depVal }}">
                                                                                                    @endif
                                                                                                    <span
                                                                                                        class="badge text-dark h-25 {{ $subjectSaved === $subject->subject ? '' : 'd-none' }} Image{{ $subject->id }}">Select
                                                                                                        Image</span>
                                                                                                    <input
                                                                                                        name="{{ $qid }}Image"
                                                                                                        id="Image{{ $qid }}"
                                                                                                        type="file"
                                                                                                        class="form-control {{ $subjectSaved === $subject->subject ? '' : 'd-none' }} Image{{ $subject->id }}"
                                                                                                        placeholder="select image">
                                                                                                @break

                                                                                                @case('Location')
                                                                                                    <input type="text"
                                                                                                        name="{{ $qid }}Location"
                                                                                                        id="location_sub_{{ $qid }}"
                                                                                                        value="{{ $depVal }}"
                                                                                                        data-original-value="{{ $depVal }}"
                                                                                                        class="form-control {{ $subjectSaved === $subject->subject ? '' : 'd-none' }} get_subj_location Location{{ $subject->id }}"
                                                                                                        placeholder="select location"
                                                                                                        readonly>
                                                                                                    <button type="button"
                                                                                                        class="btn btn-info {{ $subjectSaved === $subject->subject ? '' : 'd-none' }}"
                                                                                                        id="get_subj_location_{{ $qid }}"
                                                                                                        data-id="{{ $qid }}">
                                                                                                        {{ $depVal ? 'Update Location' : 'Get Location' }}
                                                                                                    </button>
                                                                                                    <span class="p-3 d-none"
                                                                                                        id="location_subj_status_{{ $qid }}"></span>
                                                                                                @break

                                                                                                @case('Multi select')
                                                                                                    @php
                                                                                                        $selectArrIds[] =
                                                                                                            'Multiselect' .
                                                                                                            $qid;
                                                                                                        $multiValues = $depVal
                                                                                                            ? explode(
                                                                                                                ',',
                                                                                                                $depVal,
                                                                                                            )
                                                                                                            : [];
                                                                                                    @endphp
                                                                                                    <select
                                                                                                        class="form-select {{ $subjectSaved === $subject->subject ? '' : 'd-none' }} Multiselect{{ $subject->id }}"
                                                                                                        name="{{ $qid }}Multiselect[]"
                                                                                                        id="Multiselect{{ $qid }}"
                                                                                                        multiple>
                                                                                                        <option value="">Select
                                                                                                            Option</option>
                                                                                                        @foreach ($subject->getOptions as $questionOption)
                                                                                                            <option
                                                                                                                value="{{ $questionOption->option }}"
                                                                                                                @if (in_array($questionOption->option, $multiValues)) selected @endif>
                                                                                                                {{ $questionOption->option }}
                                                                                                            </option>
                                                                                                        @endforeach
                                                                                                    </select>
                                                                                                @break

                                                                                                @default
                                                                                                    <input type="text"
                                                                                                        value="{{ $depVal }}"
                                                                                                        class="form-control {{ $subjectSaved === $subject->subject ? '' : 'd-none' }}"
                                                                                                        name="{{ $qid }}{{ $subject->answer_type }}"
                                                                                                        data-original-value="{{ $depVal }}">
                                                                                            @endswitch
                                                                                        @endforeach
                                                                                    @endif
                                                                                </div>
                                                                            @break

                                                                            @case('Multi Response')
                                                                            {{-- Multi Response = section grouping, no direct input --}}
                                                                        @break

                                                                        @default
                                                                                <input type="text"
                                                                                    value="{{ $savedAnswer }}"
                                                                                    class="form-control"
                                                                                    name="{{ $qid }}"
                                                                                    @if ($related_question->answer_type) required data-required="true" @endif>
                                                                        @endswitch

                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            {{-- ── Sub-Questions: grouped in a card ── --}}
                                                            @if ($related_question->subQuestions->count() > 0)
                                                                <tr class="sub-questions-card-row">
                                                                    <td style="padding: 4px 0 10px 0;">
                                                                        <div style="border: 2px solid #4a6cf7; border-radius: 10px; overflow: hidden; background: #f0f4ff; margin: 0 6px;">
                                                                            <table class="table mb-0" style="margin:0; background:transparent;">
                                                                                <tbody>
                                                                                    @foreach ($related_question->subQuestions as $sub_link)
                                                                                        @if ($sub_link->childQuestion)
                                                                                            @include('masters.users.partials.render_question_row', [
                                                                                                'subQuestion' => $sub_link->childQuestion,
                                                                                                'depth'       => 1,
                                                                                                'prefilled'   => $prefilled,
                                                                                            ])
                                                                                        @endif
                                                                                    @endforeach
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @endif
                                                            {{-- ── End Sub-Questions ── --}}

                                                        @endforeach
                                                    </tbody>
                                                </table>

                                                <input type="hidden" class="form-control" name="latitude"
                                                    id="latitude" value="">
                                                <input type="hidden" class="form-control" name="longitude"
                                                    id="longitude" value="">
                                                <button type="submit" id="approve" name="save"
                                                    class="btn btn-primary float-end">Save</button>
                                                <button type="button" id="reject" name="reject"
                                                    class="btn btn-danger float-end d-none">Reject</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bottom Navigation --}}
    <nav class="mobile-bottom-nav d-md-none">
        <a href="{{ route('user.projects') }}" class="{{ request()->routeIs('user.projects') ? 'active' : '' }}">
            <i class="icon-folder"></i> Projects
        </a>
        <a href="javascript:history.back()">
            <i class="icon-arrow-left"></i> Back
        </a>
    </nav>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
    
    // ── Universal mobile keyboard suppression ──
// Runs on every page, only on mobile browsers
if (window.innerWidth <= 767 || /iPhone|iPad|iPod|Android/i.test(navigator.userAgent)) {
    
    // 1. Intercept DataTables BEFORE it focuses anything
    if (typeof $.fn.DataTable !== 'undefined') {
        var _originalDataTable = $.fn.DataTable;
        $.fn.DataTable = function(options) {
            var result = _originalDataTable.apply(this, arguments);
            // After DT init, immediately kill focus on search
            var container = this.closest('.dataTables_wrapper') || 
                            $('#' + this.attr('id') + '_wrapper');
            setTimeout(function() {
                $(container).find('input[type="search"]')
                    .attr('inputmode', 'none')
                    .attr('readonly', 'readonly')
                    .css('font-size', '16px')
                    .off('focus.dtmobile')
                    .on('focus.dtmobile touchstart.dtmobile click.dtmobile', function() {
                        $(this)
                            .attr('inputmode', 'text')
                            .removeAttr('readonly');
                        $(this).off('focus.dtmobile touchstart.dtmobile click.dtmobile');
                    });
                // Kill any active focus
                if (document.activeElement && 
                    document.activeElement.tagName !== 'BODY') {
                    document.activeElement.blur();
                }
            }, 0);
            return result;
        };
        // Copy all DataTable properties over
        $.extend($.fn.DataTable, _originalDataTable);
    }

    // 2. Catch any input that gets focused on page load
    var blurCount = 0;
    var blurInterval = setInterval(function() {
        if (document.activeElement && 
            document.activeElement !== document.body &&
            document.activeElement.tagName !== 'BUTTON' &&
            document.activeElement.tagName !== 'A') {
            document.activeElement.blur();
        }
        blurCount++;
        if (blurCount >= 10) clearInterval(blurInterval); // Stop after 1 second
    }, 100);
}

        let audioMediaRecorder = null,
            videoMediaRecorder = null;
        let audioChunks = {},
            videoChunks = {};
        let audioStream = null,
            videoStream = null;
        let locationObtained = false,
            permissionDenied = false;

        // Show pre-selected child rows (for sent-back pre-filled forms)
        function initParentChildOldadsdVisibility() {
            $('.parent-question').each(function() {
                let parentId = $(this).data('question-id');
                let selectedValue = String($(this).val() || '').trim();
                let parentRow = $(this).closest('tr');
                let lastInserted = parentRow;

                $('tr.child-question[data-parent-id="' + parentId + '"]').each(function() {
                    let childParentValue = String($(this).data('parent-value')).trim();

                    if (selectedValue && childParentValue === selectedValue) {
                        $(this).removeClass('d-none');
                        $(this).find('.recording-section').removeClass('d-none');
                        lastInserted.after($(this));
                        lastInserted = $(this);
                        $(this).find('input, select, textarea').each(function() {
                            if ($(this).attr('data-required') === 'true') {
                                $(this).prop('required', true);
                            }
                        });
                    } else {
                        $(this).addClass('d-none');
                        $(this).find('.recording-section').addClass('d-none');
                        $(this).find('input, select, textarea').prop('required', false);
                    }
                });
            });
        }

        function getChildRows(parentId) {
            return $('tr.child-question').filter(function() {
                return String($(this).data('parent-id')) === String(parentId);
            });
        }

        function setQuestionRowEnabled($row, enabled) {
            $row.find('input, select, textarea, button').each(function() {
                const $field = $(this);

                $field.prop('disabled', !enabled);

                if (enabled && $field.attr('data-required') === 'true') {
                    $field.prop('required', true);
                } else {
                    $field.prop('required', false);
                }
            });
        }

        function hideQuestionRow($row) {
            $row.addClass('d-none');
            setQuestionRowEnabled($row, false);

            const childId = $row.data('question-id');
            if (childId) {
                hideChildBranch(childId);
            }
        }

        function showQuestionRow($row) {
            $row.removeClass('d-none');
            setQuestionRowEnabled($row, true);

            // Only restore normal Audio/Video question blocks.
            // Subjective dependent Audio/Video blocks are controlled by .subjective_ques logic.
            if (!$row.find('.subjective_ques').length) {
                $row.find('.recording-section').removeClass('d-none');
            }
        }

        function hideChildBranch(parentId) {
            getChildRows(parentId).each(function() {
                hideQuestionRow($(this));
            });
        }

        // function showMatchingChildren($parentSelect) {
        //     const parentId = $parentSelect.data('question-id');
        //     const selectedValue = String($parentSelect.val() || '').trim();
        //     let $lastInserted = $parentSelect.closest('tr');

        //     if (!selectedValue) {
        //         return;
        //     }

        //     getChildRows(parentId).each(function() {
        //         const $childRow = $(this);
        //         const childParentValue = String($childRow.data('parent-value') || '').trim();

        //         if (childParentValue === selectedValue) {
        //             showQuestionRow($childRow);

        //             $lastInserted.after($childRow);
        //             $lastInserted = $childRow;

        //             $childRow.find('.parent-question').each(function() {
        //                 showMatchingChildren($(this));
        //             });
        //         }
        //     });
        // }

        function showMatchingChildren($parentSelect, skipHide = false) {
            const parentId = $parentSelect.data('question-id');
            const selectedValue = String($parentSelect.val() || '').trim();
            let $lastInserted = $parentSelect.closest('tr');

            if (!skipHide) {
                hideChildBranch(parentId);
            }

            if (!selectedValue) {
                return;
            }

            getChildRows(parentId).each(function() {
                const $childRow = $(this);
                const childParentValue = String($childRow.data('parent-value') || '').trim();

                if (childParentValue === selectedValue) {
                    showQuestionRow($childRow);

                    $lastInserted.after($childRow);
                    $lastInserted = $childRow;

                    $childRow.find('.parent-question').each(function() {
                        showMatchingChildren($(this), true);
                    });
                }
            });
        }


        // function initParentChildVisibility() {
        //     // First hide every child row, including sent-back rows.
        //     $('tr.child-question').each(function() {
        //         hideQuestionRow($(this));
        //     });

        //     // Then show only children matching currently selected parent values.
        //     $('.parent-question').each(function() {
        //         const $parentSelect = $(this);

        //         if (!$parentSelect.closest('tr').hasClass('d-none')) {
        //             showMatchingChildren($parentSelect);
        //         }
        //     });
        // }

        function initParentChildVisibility() {
            $('tr.child-question').each(function() {
                hideQuestionRow($(this));
            });

            $('.parent-question').each(function() {
                const $parentSelect = $(this);

                if (!$parentSelect.closest('tr').hasClass('d-none')) {
                    showMatchingChildren($parentSelect, true);
                }
            });
        }


        function enableFormInputs(lat, lng) {
            // Cache coordinates so the next instance page doesn't need to re-ask
            if (lat !== undefined && lng !== undefined) {
                sessionStorage.setItem('loc_lat', lat);
                sessionStorage.setItem('loc_lng', lng);
            }
            $('#activityForm').find('input:not([type="hidden"])').prop('disabled', false);
            $('#activityForm').find('select').prop('disabled', false);
            $('#activityForm').find('button:not([type="submit"])').prop('disabled', false);
            $('#activityForm').find('.start-audio-recording, .start-video-recording').prop('disabled', false);
            $('#activityForm').find('#approve, #reject').prop('disabled', false);
            // Init select2 on ALL multi-selects in the form (covers main + sub-question multi-selects)
            $('#activityForm select[multiple]').each(function () {
                $(this).select2({
                    theme: "bootstrap-5",
                    placeholder: "Select options",
                    allowClear: true,
                    closeOnSelect: false,
                    width: '100%'
                });
            });
            $('#locationOverlay').fadeOut(300);
            // Swal.fire({
            //     position: "top-center",
            //     icon: "success",
            //     title: "Location access granted!",
            //     text: "You can now fill the form.",
            //     showConfirmButton: false,
            //     timer: 1500
            // });
            
            // Inside enableFormInputs(), after the location success Swal:
            // Swal.fire({
            //     position: "top-center",
            //     icon: "success",
            //     title: "Location access granted!",
            //     showConfirmButton: false,
            //     timer: 1500
            // }).then(() => {
            //     // Show error AFTER location alert closes, so it's not overridden
            //     @if (session()->has('error'))
            //     Swal.fire({
            //         icon: 'error',
            //         title: 'Submission Failed',
            //         text: "{{ session('error') }}",
            //         confirmButtonText: 'OK',
            //         confirmButtonColor: '#111111'
            //     });
            //     @endif
            // });
            
            
            @if (session()->has('error'))
                // Error occurred — show error alert directly, skip location success alert
                Swal.fire({
                    icon: 'error',
                    title: 'Submission Failed',
                    text: "{{ session('error') }}",
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#111111',
                    timer: 4000,
                    timerProgressBar: true,
                }).then(() => {
                    location.reload();
                });
            @else
                Swal.fire({
                    position: "top-center",
                    icon: "success",
                    title: "Location access granted!",
                    showConfirmButton: false,
                    timer: 1500
                });
            @endif

            locationObtained = true;
            permissionDenied = false;
            // Show child rows that have pre-selected parent values (sent-back case)
            initParentChildVisibility();
        }

        function disableFormInputs(message =
            "Please allow location access to continue filling the form. This is required for all submissions.", showGuide =
            false) {
            $('#activityForm').find('input:not([type="hidden"])').prop('disabled', true);
            $('#activityForm').find('select').prop('disabled', true);
            $('#activityForm').find('button:not([type="submit"])').prop('disabled', true);
            $('#activityForm').find('.start-audio-recording, .start-video-recording').prop('disabled', true);
            $('#activityForm').find('#approve, #reject').prop('disabled', true);
            $('#locationMessage').text(message);
            $('#locationSpinner').toggleClass('d-none', showGuide || permissionDenied);
            if (showGuide || permissionDenied) {
                $('#permissionDeniedGuide').removeClass('d-none');
            } else {
                $('#permissionDeniedGuide').addClass('d-none');
            }
            $('#locationOverlay').fadeIn(300);
            locationObtained = false;
        }

        function checkPermissionState() {
            return new Promise((resolve) => {
                if (!navigator.permissions) {
                    navigator.geolocation.getCurrentPosition(() => resolve('granted'), (error) => {
                        resolve(error.code === error.PERMISSION_DENIED ? 'denied' : 'prompt');
                    }, {
                        enableHighAccuracy: true,
                        timeout: 100
                    });
                    return;
                }
                navigator.permissions.query({
                    name: 'geolocation'
                }).then((result) => resolve(result.state)).catch(() => resolve('prompt'));
            });
        }

        async function requestLocation() {
            const permissionState = await checkPermissionState();
            if (permissionState === 'granted') {
                $('#locationMessage').text('Getting your location...');
                $('#locationSpinner').removeClass('d-none');
                $('#permissionDeniedGuide').addClass('d-none');
                navigator.geolocation.getCurrentPosition(function(position) {
                    $('#longitude').val(position.coords.longitude);
                    $('#latitude').val(position.coords.latitude);
                    enableFormInputs(position.coords.latitude, position.coords.longitude);
                }, function(error) {
                    disableFormInputs('Failed to get location. Please try again.', false);
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                });
            } else if (permissionState === 'denied') {
                permissionDenied = true;
                disableFormInputs(
                    'Location permission was previously denied. Please enable location access in your browser settings to continue.',
                    true);
                Swal.fire({
                    icon: 'warning',
                    title: 'Location Permission Denied',
                    html: `<p>You have previously denied location access for this site.</p><p><strong>To enable location:</strong></p><ol style="text-align:left;margin-left:20px;"><li>Click the lock icon (🔒) in your browser's address bar</li><li>Find "Location" in the permissions list</li><li>Change from "Block" to "Allow"</li><li>Click "Retry Location Access" below</li></ol>`,
                    confirmButtonText: 'Got it',
                    showCancelButton: false,
                    allowOutsideClick: false
                });
            } else {
                $('#locationMessage').text('Requesting location access...');
                $('#locationSpinner').removeClass('d-none');
                $('#permissionDeniedGuide').addClass('d-none');
                navigator.geolocation.getCurrentPosition(function(position) {
                    $('#longitude').val(position.coords.longitude);
                    $('#latitude').val(position.coords.latitude);
                    enableFormInputs(position.coords.latitude, position.coords.longitude);
                }, function(error) {
                    if (error.code === error.PERMISSION_DENIED) {
                        permissionDenied = true;
                        disableFormInputs(
                            'Location permission denied. Please allow location access to continue.', true);
                        Swal.fire({
                            icon: 'warning',
                            title: 'Permission Denied',
                            text: 'You denied location access. Please enable it to continue filling the form.',
                            confirmButtonText: 'Retry',
                            showCancelButton: true,
                            cancelButtonText: 'Cancel',
                            allowOutsideClick: false
                        }).then((result) => {
                            if (result.isConfirmed) requestLocation();
                        });
                    } else {
                        disableFormInputs('Failed to get location. Please try again.', false);
                    }
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                });
            }
        }

        var _allowRepeat = {{ !empty($allow_repeat) ? 'true' : 'false' }};
        var _rowId = {{ $row_data->id ?? 0 }};
        var _activityId = {{ $related_questions->first()->activity_id ?? 0 }};
        var _groupId = {{ $group_info->id ?? 'null' }};
        var _currentSequence = {{ $activity_sequence ?? 0 }};
        var _csrfToken = '{{ csrf_token() }}';
        var _activityUrl = '{{ route('user.project.row_id.activity', ['row_id' => $row_data->id, 'activity' => $related_questions->first()->activity_id ?? 0, 'group_info' => $group_info->id ?? '']) }}';
        var _closeUrl = '{{ route('user.activity.close_instances') }}';
        var _sendOtpUrl = '{{ route('send_otp') }}';
        var _verifyOtpUrl = '{{ route('verify_otp') }}';
        var _addInstanceUrl = '{{ route('user.activity.add_instance') }}';

        // ── Unified form submit: images upload first, then AJAX save, then OTP if needed ──
        $('#activityForm').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);

            // 1. Location guard
            if (!locationObtained || !$('#latitude').val() || !$('#longitude').val()) {
                Swal.fire({ icon: 'error', title: 'Location Required',
                    text: 'Please allow location access before submitting.',
                    confirmButtonText: 'Get Location'
                }).then(r => { if (r.isConfirmed) requestLocation(); });
                return;
            }

            // 2. Validate required multi-image slots
            var missingImg = false;
            $form.find('.multi-image-required').each(function () {
                var qid = $(this).data('qid');
                var wrapper = $form.find('.multi-image-wrapper[data-qid="' + qid + '"]');
                var hasSaved = wrapper.find('input[name="multi_images_' + qid + '[]"]').length > 0;
                var hasNew   = wrapper.find('.multi-image-file-input').toArray().some(function(inp){ return inp.files && inp.files[0]; });
                if (!hasSaved && !hasNew) missingImg = true;
            });
            if (missingImg) {
                Swal.fire('Required', 'Please select at least one image for all required image questions.', 'warning');
                return;
            }

            // 3. Collect new multi-image file inputs that have a file selected
            var uploads = [];
            $form.find('.multi-image-wrapper').each(function () {
                var wrapper   = this;
                var qid       = $(wrapper).data('qid');
                var uploadUrl = $(wrapper).data('upload-url');
                var rowId     = $(wrapper).data('row-id');
                $(wrapper).find('.multi-image-file-input').each(function () {
                    if (this.files && this.files[0]) {
                        uploads.push({ input: this, qid: qid, url: uploadUrl, rowId: rowId, wrapper: wrapper });
                    }
                });
            });

            // 4. Show saving indicator
            Swal.fire({
                title: uploads.length ? 'Uploading images…' : 'Saving…',
                html: '<p style="font-size:14px;color:#555;">Please wait.</p>',
                allowOutsideClick: false, allowEscapeKey: false,
                showConfirmButton: false, didOpen: () => Swal.showLoading()
            });
            $('#approve').prop('disabled', true).text('Saving…');

            // 5. Upload images sequentially, then AJAX-submit the form
            var idx = 0;
            function uploadNext() {
                if (idx >= uploads.length) {
                    doAjaxSubmit();
                    return;
                }
                var u  = uploads[idx++];
                var fd = new FormData();
                fd.append('image',     u.input.files[0]);
                fd.append('row_id',    u.rowId);
                fd.append('latitude',  $('#latitude').val());
                fd.append('longitude', $('#longitude').val());
                fd.append('_token',    _csrfToken);
                $.ajax({
                    url: u.url, type: 'POST', data: fd,
                    processData: false, contentType: false,
                    success: function (res) {
                        if (res.path) {
                            $('<input type="hidden">').attr('name', 'multi_images_' + u.qid + '[]').val(res.path).appendTo($(u.wrapper));
                        }
                        uploadNext();
                    },
                    error: function (xhr) {
                        Swal.close();
                        $('#approve').prop('disabled', false).text('Save');
                        var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Image upload failed.';
                        Swal.fire('Upload Error', msg, 'error');
                    }
                });
            }

            function doAjaxSubmit() {
                Swal.update({ title: 'Saving…' });
                var formData = new FormData($form[0]);
                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    success: function (res) {
                        Swal.close();
                        $('#approve').prop('disabled', false).text('Save');
                        if (res.success && res.otp_required) {
                            showOtpOverlay(res.row_id, res.activity_id);
                        } else if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Saved!', text: res.message, timer: 1500, showConfirmButton: false })
                                .then(() => location.reload());
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Submission failed.' });
                        }
                    },
                    error: function (xhr) {
                        Swal.close();
                        $('#approve').prop('disabled', false).text('Save');
                        var msg = 'Submission failed. Please try again.';
                        try { msg = JSON.parse(xhr.responseText).message || msg; } catch(err) {}
                        Swal.fire({ icon: 'error', title: 'Error', text: msg });
                    }
                });
            }

            uploadNext();
        });

        // ── Inline OTP Overlay (full-page blocking, no way to escape) ──────────
        function showOtpOverlay(rowId, activityId) {
            // Block browser back button while OTP is pending
            history.pushState(null, '', location.href);
            window.addEventListener('popstate', function onPop() {
                history.pushState(null, '', location.href);
            });
            window.addEventListener('beforeunload', function(e) {
                e.preventDefault(); e.returnValue = '';
            });

            // Inject full-page overlay HTML (once)
            if (!$('#otpOverlay').length) {
                $('body').append(`
                <div id="otpOverlay" style="
                    position:fixed;inset:0;z-index:99999;
                    background:rgba(0,0,0,0.85);
                    display:flex;align-items:center;justify-content:center;">
                  <div style="background:#fff;border-radius:14px;padding:28px 24px;width:92%;max-width:360px;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,0.4);">
                    <div style="font-size:36px;margin-bottom:8px;">🔐</div>
                    <h5 style="margin-bottom:4px;font-weight:700;">OTP Verification</h5>
                    <p style="font-size:13px;color:#666;margin-bottom:16px;">
                        Your answers have been saved.<br>Please verify your identity to complete submission.
                    </p>

                    {{-- Step 1: mobile number --}}
                    <div id="otpStep1">
                        <label style="font-size:13px;font-weight:600;display:block;text-align:left;margin-bottom:4px;">Mobile Number</label>
                        <input id="otpMobile" type="tel" maxlength="10" placeholder="Enter 10-digit mobile number"
                               class="form-control mb-3" style="font-size:15px;letter-spacing:1px;">
                        <div id="otpStep1Error" class="text-danger mb-2" style="font-size:12px;display:none;"></div>
                        <button id="otpSendBtn" class="btn btn-dark w-100" style="font-weight:600;">Send OTP</button>
                    </div>

                    {{-- Step 2: enter OTP --}}
                    <div id="otpStep2" style="display:none;">
                        <p style="font-size:13px;color:#444;" id="otpSentTo">OTP sent to your number</p>
                        <label style="font-size:13px;font-weight:600;display:block;text-align:left;margin-bottom:4px;">Enter OTP</label>
                        <input id="otpCode" type="number" maxlength="6" placeholder="6-digit OTP"
                               class="form-control mb-3" style="font-size:20px;letter-spacing:4px;text-align:center;">
                        <div id="otpStep2Error" class="text-danger mb-2" style="font-size:12px;display:none;"></div>
                        <button id="otpVerifyBtn" class="btn btn-dark w-100 mb-2" style="font-weight:600;">Verify OTP</button>
                        <button id="otpResendBtn" class="btn btn-link btn-sm text-muted w-100">Resend OTP</button>
                    </div>

                    {{-- Step 3: success --}}
                    <div id="otpStep3" style="display:none;">
                        <div style="font-size:48px;margin-bottom:8px;">✅</div>
                        <h6 style="font-weight:700;color:#28a745;">Verified!</h6>
                        <p style="font-size:13px;color:#555;">OTP verified successfully. Redirecting…</p>
                    </div>
                  </div>
                </div>`);
            }

            $('#otpOverlay').show();
            $('#otpStep1,#otpStep2,#otpStep3').hide();
            $('#otpStep1').show();
            $('#otpMobile').val('').focus();
            $('#otpStep1Error,#otpStep2Error').hide().text('');

            // Send OTP
            $('#otpSendBtn').off('click').on('click', function () {
                var mobile = $('#otpMobile').val().trim();
                if (!/^[6-9]\d{9}$/.test(mobile)) {
                    $('#otpStep1Error').text('Please enter a valid 10-digit mobile number.').show();
                    return;
                }
                $('#otpStep1Error').hide();
                var $btn = $(this).prop('disabled', true).text('Sending…');
                $.ajax({
                    url: _sendOtpUrl, method: 'POST',
                    data: { _token: _csrfToken, mobile_no: mobile, row_id: rowId, activity_id: activityId },
                    success: function () {
                        $btn.prop('disabled', false).text('Send OTP');
                        $('#otpSentTo').html('OTP sent to <strong>' + mobile + '</strong>');
                        $('#otpStep1').hide();
                        $('#otpStep2').show();
                        $('#otpCode').val('').focus();

                        // Store mobile for resend
                        $('#otpResendBtn').data('mobile', mobile);
                        $('#otpVerifyBtn').data('mobile', mobile);
                    },
                    error: function (xhr) {
                        $btn.prop('disabled', false).text('Send OTP');
                        var msg = 'Failed to send OTP.';
                        try { msg = JSON.parse(xhr.responseText).message || msg; } catch(err) {}
                        $('#otpStep1Error').text(msg).show();
                    }
                });
            });

            // Verify OTP
            $('#otpVerifyBtn').off('click').on('click', function () {
                var code   = $('#otpCode').val().trim();
                var mobile = $(this).data('mobile');
                if (!/^\d{6}$/.test(code)) {
                    $('#otpStep2Error').text('Please enter a valid 6-digit OTP.').show();
                    return;
                }
                $('#otpStep2Error').hide();
                var $btn = $(this).prop('disabled', true).text('Verifying…');
                $.ajax({
                    url: _verifyOtpUrl, method: 'POST',
                    data: { _token: _csrfToken, mobile_otp: code, mobile_no: mobile, row_id: rowId, activity_id: activityId },
                    success: function (res) {
                        $btn.prop('disabled', false).text('Verify OTP');
                        if (res.success) {
                            $('#otpStep2').hide();
                            $('#otpStep3').show();
                            setTimeout(function () {
                                // Navigate back based on session redirect or just reload
                                if (res.redirectUrl) {
                                    window.location.href = res.redirectUrl;
                                } else {
                                    location.reload();
                                }
                            }, 1200);
                        } else {
                            $('#otpStep2Error').text(res.message || 'Invalid OTP. Please try again.').show();
                        }
                    },
                    error: function (xhr) {
                        $btn.prop('disabled', false).text('Verify OTP');
                        var msg = 'Verification failed.';
                        try { msg = JSON.parse(xhr.responseText).message || msg; } catch(err) {}
                        $('#otpStep2Error').text(msg).show();
                    }
                });
            });

            // Resend OTP: go back to step 1
            $('#otpResendBtn').off('click').on('click', function () {
                $('#otpStep2').hide();
                $('#otpStep1').show();
                $('#otpMobile').focus();
            });
        }

        // ── Add Instance Button ──
        $(document).on('click', '#addInstanceBtn', function() {
            if (!_allowRepeat) return;
            Swal.fire({
                title: 'New Instance',
                html: `<p style="font-size:13px;color:#555;">Enter a label for the new instance</p>
                       <input id="instance-label" class="swal2-input" type="text" placeholder="e.g. Visit 2">`,
                confirmButtonText: 'Add',
                confirmButtonColor: '#111',
                showCancelButton: true,
                preConfirm: () => {
                    var label = document.getElementById('instance-label').value.trim();
                    if (!label) { Swal.showValidationMessage('Please enter a label'); return false; }
                    return label;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    var form = $('<form method="POST" action="' + _addInstanceUrl + '" style="display:none">' +
                        '<input name="_token" value="' + _csrfToken + '">' +
                        '<input name="row_id" value="' + _rowId + '">' +
                        '<input name="activity_id" value="' + _activityId + '">' +
                        '<input name="group_id" value="' + (_groupId || '') + '">' +
                        '<input name="instance_label" value="' + $('<div>').text(result.value).html() + '">' +
                        '</form>');
                    $('body').append(form);
                    form.submit();
                }
            });
        });

        // ── Submit (Close) Activity Button ──
        $(document).on('click', '#submitActivityBtn', function() {
            Swal.fire({
                title: 'Submit Activity',
                text: 'Are you sure you want to finalize and close this activity? You will not be able to add more instances after this.',
                icon: 'warning',
                confirmButtonText: 'Yes, Submit',
                confirmButtonColor: '#d33',
                showCancelButton: true,
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (!result.isConfirmed) return;
                Swal.fire({ title: 'Closing...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
                $.ajax({
                    url: _closeUrl,
                    method: 'POST',
                    data: { _token: _csrfToken, row_id: _rowId, activity_id: _activityId },
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    success: function(res) {
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Activity Submitted!', text: 'The activity has been closed.', timer: 1500, showConfirmButton: false })
                                .then(() => { history.back(); });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Cannot Submit', text: res.message || 'All instances must be submitted first.' });
                        }
                    },
                    error: function() {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to close activity. Please try again.' });
                    }
                });
            });
        });



        $(document).ready(function() {
            
            @if (session()->has('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'Submission Failed',
                    text: "{{ session('error') }}",
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#111111'
                });
            @endif
            
            // Position fixed instance header just below the site's top navbar
            (function positionInstanceHeader() {
                var fixedHeader   = document.getElementById('instanceHeaderFixed');
                var placeholder   = document.getElementById('instanceHeaderPlaceholder');
                if (!fixedHeader) return;

                function update() {
                    // The theme gives .page-body-wrapper a margin-top equal to the
                    // site navbar height. So the top of #formContainer (which IS the
                    // page-body) == the site-navbar height from the viewport top.
                    // Using this is more reliable than querying .page-header, which
                    // is display:none on mobile in this theme.
                    var formContainer = document.getElementById('formContainer');
                    var top = 0;
                    if (formContainer) {
                        // scrollY = 0 at page load; getBoundingClientRect().top gives
                        // viewport-relative offset, which is what position:fixed needs.
                        top = Math.max(0, Math.round(
                            formContainer.getBoundingClientRect().top + window.scrollY
                        ));
                        // Also align the fixed bar to the card's horizontal edges
                        var card = formContainer.querySelector('.card');
                        if (card) {
                            var cr = card.getBoundingClientRect();
                            fixedHeader.style.left         = Math.round(cr.left) + 'px';
                            fixedHeader.style.right        = Math.round(window.innerWidth - cr.right) + 'px';
                            fixedHeader.style.width        = 'auto';
                            fixedHeader.style.borderRadius = '12px 12px 0 0';
                        }
                    }

                    // Hard fallback: try to read a rendered navbar element
                    if (top === 0) {
                        var nav = document.querySelector('.header-wrapper') ||
                                  document.querySelector('.page-header')    ||
                                  document.querySelector('header');
                        top = nav ? Math.max(0, Math.round(nav.getBoundingClientRect().bottom)) : 60;
                    }

                    fixedHeader.style.top = top + 'px';

                    var hdrHeight = fixedHeader.offsetHeight;
                    if (hdrHeight > 0 && placeholder) {
                        placeholder.style.display = 'block';
                        placeholder.style.height  = hdrHeight + 'px';
                    }
                }

                // Run immediately, after fonts/images settle, and on resize
                update();
                setTimeout(update, 150);
                setTimeout(update, 500);
                window.addEventListener('resize', update);
            })();

            initParentChildVisibility();
            disableFormInputs();

            // If the user already granted location in this session, reuse it instantly
            // without showing the overlay again (avoids re-asking on every instance nav)
            var _cachedLat = sessionStorage.getItem('loc_lat');
            var _cachedLng = sessionStorage.getItem('loc_lng');
            if (_cachedLat && _cachedLng) {
                $('#latitude').val(_cachedLat);
                $('#longitude').val(_cachedLng);
                enableFormInputs(); // overlay hidden, form unlocked, no GPS call
            } else {
                requestLocation();
            }

            $('#retryLocationBtn').on('click', function() {
                requestLocation();
            });

            @if (session()->has('message'))
                if (locationObtained) {
                    Swal.fire({
                        position: "top-center",
                        icon: "success",
                        title: "{{ session('message') }}",
                        showConfirmButton: false,
                        timer: 1500
                    });
                }
            @endif

            $(document).on('change', '.parent-question', function() {
                const $parentSelect = $(this);
                const parentId = $parentSelect.data('question-id');

                // Hide all children and grandchildren of this parent first.
                hideChildBranch(parentId);

                // Show only the child branch matching the newly selected value.
                showMatchingChildren($(this));
            });


            // $(document).on('change', '.parent-question', function() {

            //     let parentId = $(this).data('question-id');
            //     let selectedValue = String($(this).val() || '').trim();
            //     let parentRow = $(this).closest('tr');

            //     // PASS 1: Hide ALL children first (clean slate)
            //     $('tr.child-question[data-parent-id="' + parentId + '"]').each(function() {
            //         $(this).addClass('d-none');
            //         $(this).find('.recording-section').addClass('d-none');
            //         $(this).find('input[type="text"]').val('');
            //         $(this).find('input[type="date"]').val('');
            //         $(this).find('input[type="datetime-local"]').val('');
            //         $(this).find('select').val('');
            //         $(this).find('input[type="file"]').val('');
            //         $(this).find('.audio-data, .video-data').val('');
            //         $(this).find('.recording-status').text('').removeClass();
            //         $(this).find('input, select, textarea').prop('required', false);
            //     });

            //     if (!selectedValue) return;

            //     // PASS 2: Show the matching child and move it after parent
            //     $('tr.child-question[data-parent-id="' + parentId + '"]').each(function() {
            //         let childVal = String($(this).data('parent-value')).trim();
            //         if (childVal === selectedValue) {
            //             $(this).removeClass('d-none');
            //             $(this).find('.recording-section').removeClass('d-none');
            //             parentRow.after($(this));
            //             $(this).find('input, select, textarea').each(function() {
            //                 if ($(this).attr('data-required') === 'true') {
            //                     $(this).prop('required', true);
            //                 }
            //             });
            //         }
            //     });

            // });



            function showLocationRequiredAlert() {
                Swal.fire({
                    icon: "warning",
                    title: "Location Required",
                    text: "Please allow location access first.",
                    confirmButtonText: "Get Location"
                }).then((result) => {
                    if (result.isConfirmed) requestLocation();
                });
            }

            $(".start-audio-recording").on("click", function() {
                if (!locationObtained) {
                    showLocationRequiredAlert();
                    return;
                }
                let id = $(this).data("id");
                let statusDiv = $("#audio-recording-status-" + id);
                let audioPlayer = $("#audio-player-" + id);
                let audioInput = $(".audio-data[data-id='" + id + "']");
                let stopBtn = $(".stop-audio-recording[data-id='" + id + "']");
                let deleteBtn = $(".delete-audio-recording[data-id='" + id + "']");
                statusDiv.text("🎤 Recording...").removeClass().addClass("recording-status recording");
                $(this).prop("disabled", true);
                stopBtn.prop("disabled", false);
                navigator.mediaDevices.getUserMedia({
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        sampleRate: 44100
                    }
                }).then(stream => {
                    audioStream = stream;
                    audioMediaRecorder = new MediaRecorder(stream, {
                        mimeType: 'audio/webm;codecs=opus'
                    });
                    audioChunks[id] = [];
                    audioMediaRecorder.ondataavailable = event => {
                        if (event.data.size > 0) audioChunks[id].push(event.data);
                    };
                    audioMediaRecorder.onstop = () => {
                        const audioBlob = new Blob(audioChunks[id], {
                            type: 'audio/webm;codecs=opus'
                        });
                        audioPlayer.attr("src", URL.createObjectURL(audioBlob)).removeClass(
                            "d-none");
                        const reader = new FileReader();
                        reader.readAsDataURL(audioBlob);
                        reader.onloadend = function() {
                            audioInput.val(reader.result);
                        };
                        statusDiv.text("✅ Recording completed").removeClass("recording")
                            .addClass("stopped");
                        deleteBtn.prop("disabled", false);
                        stream.getTracks().forEach(track => track.stop());
                    };
                    audioMediaRecorder.start();
                }).catch(error => {
                    statusDiv.text("❌ Microphone access denied or unavailable");
                    $(this).prop("disabled", false);
                    stopBtn.prop("disabled", true);
                    if (error.name === 'NotAllowedError') Swal.fire({
                        icon: 'error',
                        title: 'Permission Denied',
                        text: 'Please allow microphone access to record audio.'
                    });
                });
            });

            $(".stop-audio-recording").on("click", function() {
                let id = $(this).data("id");
                if (audioMediaRecorder && audioMediaRecorder.state !== "inactive") {
                    audioMediaRecorder.stop();
                    $(".start-audio-recording[data-id='" + id + "']").prop("disabled", false);
                    $(this).prop("disabled", true);
                    $(".delete-audio-recording[data-id='" + id + "']").prop("disabled", false);
                    if (audioStream) {
                        audioStream.getTracks().forEach(track => track.stop());
                        audioStream = null;
                    }
                }
            });

            $(".delete-audio-recording").on("click", function() {
                let id = $(this).data("id");
                $("#audio-player-" + id).addClass("d-none").attr("src", "");
                $(".audio-data[data-id='" + id + "']").val("");
                $(".start-audio-recording[data-id='" + id + "']").prop("disabled", false);
                $(".stop-audio-recording[data-id='" + id + "']").prop("disabled", true);
                $(this).prop("disabled", true);
                $("#audio-recording-status-" + id).text("").removeClass();
            });

            $(".start-video-recording").on("click", function() {
                if (!locationObtained) {
                    showLocationRequiredAlert();
                    return;
                }
                let id = $(this).data("id");
                let statusDiv = $("#video-recording-status-" + id);
                let videoPreview = $("#video-preview-" + id);
                let videoInput = $(".video-data[data-id='" + id + "']");
                let stopBtn = $(".stop-video-recording[data-id='" + id + "']");
                let deleteBtn = $(".delete-video-recording[data-id='" + id + "']");
                statusDiv.text("🎥 Starting camera...").removeClass().addClass(
                    "recording-status recording");
                $(this).prop("disabled", true);
                stopBtn.prop("disabled", false);

                const testMimeTypes = ['video/mp4;codecs=h264,aac',
                    'video/mp4;codecs=avc1.42E01E,mp4a.40.2', 'video/mp4',
                    'video/webm;codecs=h264,opus', 'video/webm;codecs=vp8,opus', 'video/webm'
                ];
                let supportedMimeType = null;
                for (let mimeType of testMimeTypes) {
                    if (MediaRecorder.isTypeSupported(mimeType)) {
                        supportedMimeType = mimeType;
                        break;
                    }
                }
                let fileExtension = (supportedMimeType && supportedMimeType.includes('webm')) ? 'webm' :
                    'mp4';

                const tryCamera = (constraints, attempt = 1) => {
                    navigator.mediaDevices.getUserMedia(constraints).then(stream => {
                        videoStream = stream;
                        videoMediaRecorder = new MediaRecorder(stream, supportedMimeType ? {
                            mimeType: supportedMimeType,
                            videoBitsPerSecond: 2500000
                        } : {
                            videoBitsPerSecond: 2500000
                        });
                        videoChunks[id] = [];
                        videoPreview.removeClass("d-none");
                        videoPreview[0].srcObject = stream;
                        videoPreview[0].play();
                        statusDiv.text(
                            `🎥 Recording... (${fileExtension.toUpperCase()} format)`);
                        videoMediaRecorder.ondataavailable = event => {
                            if (event.data.size > 0) videoChunks[id].push(event.data);
                        };
                        videoMediaRecorder.onstop = () => {
                            const videoBlob = new Blob(videoChunks[id], {
                                type: supportedMimeType || 'video/webm'
                            });
                            videoPreview[0].srcObject = null;
                            videoPreview.attr("src", URL.createObjectURL(videoBlob));
                            const reader = new FileReader();
                            reader.readAsDataURL(videoBlob);
                            reader.onloadend = function() {
                                const fullDataUrl = reader.result;
                                const base64Marker = 'base64,';
                                const markerIndex = fullDataUrl.indexOf(base64Marker);
                                if (markerIndex === -1) {
                                    statusDiv.text("❌ Error preparing video data")
                                        .removeClass("recording").addClass("stopped");
                                    return;
                                }
                                const rawBase64 = fullDataUrl.substring(markerIndex +
                                    base64Marker.length);
                                const cleanMime = videoBlob.type.split(';')[0];
                                videoInput.val(`data:${cleanMime};base64,${rawBase64}`);
                            };
                            statusDiv.text(
                                `✅ Recording completed (${fileExtension.toUpperCase()})`
                            ).removeClass("recording").addClass("stopped");
                            deleteBtn.prop("disabled", false);
                            stream.getTracks().forEach(track => track.stop());
                        };
                        setTimeout(() => {
                            if (videoMediaRecorder && videoMediaRecorder.state ===
                                "recording") {
                                videoMediaRecorder.stop();
                                statusDiv.text(
                                        "⏱️ Recording stopped (time limit reached)")
                                    .removeClass("recording").addClass("stopped");
                            }
                        }, 300000);
                        videoMediaRecorder.start();
                    }).catch(error => {
                        if (attempt === 1) {
                            tryCamera({
                                video: {
                                    width: {
                                        ideal: 1280
                                    },
                                    height: {
                                        ideal: 720
                                    }
                                },
                                audio: {
                                    echoCancellation: true,
                                    noiseSuppression: true,
                                    sampleRate: 44100
                                }
                            }, 2);
                        } else if (attempt === 2) {
                            tryCamera({
                                video: {
                                    width: {
                                        ideal: 1280
                                    },
                                    height: {
                                        ideal: 720
                                    },
                                    facingMode: "user"
                                },
                                audio: {
                                    echoCancellation: true,
                                    noiseSuppression: true,
                                    sampleRate: 44100
                                }
                            }, 3);
                        } else {
                            statusDiv.text("❌ Camera access denied or unavailable");
                            $(this).prop("disabled", false);
                            stopBtn.prop("disabled", true);
                            if (error.name === 'NotAllowedError') Swal.fire({
                                icon: 'error',
                                title: 'Permission Denied',
                                text: 'Please allow camera and microphone access to record video.'
                            });
                            else if (error.name === 'NotFoundError') Swal.fire({
                                icon: 'error',
                                title: 'Camera Not Found',
                                text: 'No camera found on this device.'
                            });
                        }
                    });
                };
                tryCamera({
                    video: {
                        width: {
                            ideal: 1280
                        },
                        height: {
                            ideal: 720
                        },
                        facingMode: {
                            exact: "environment"
                        }
                    },
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        sampleRate: 44100
                    }
                }, 1);
            });

            $(".stop-video-recording").on("click", function() {
                let id = $(this).data("id");
                let videoPreview = $("#video-preview-" + id);
                if (videoMediaRecorder && videoMediaRecorder.state !== "inactive") {
                    videoMediaRecorder.stop();
                    $(".start-video-recording[data-id='" + id + "']").prop("disabled", false);
                    $(this).prop("disabled", true);
                    $(".delete-video-recording[data-id='" + id + "']").prop("disabled", false);
                    if (videoStream) {
                        videoStream.getTracks().forEach(track => track.stop());
                        videoStream = null;
                    }
                    if (videoPreview[0].srcObject) videoPreview[0].srcObject.getTracks().forEach(track =>
                        track.stop());
                }
            });

            $(".delete-video-recording").on("click", function() {
                let id = $(this).data("id");
                $("#video-preview-" + id).addClass("d-none").attr("src", "");
                $(".video-data[data-id='" + id + "']").val("");
                $(".start-video-recording[data-id='" + id + "']").prop("disabled", false);
                $(".stop-video-recording[data-id='" + id + "']").prop("disabled", true);
                $(this).prop("disabled", true);
                $("#video-recording-status-" + id).text("").removeClass();
            });

            $('button[id^="get_location_"]').on('click', function(e) {
                if (!locationObtained) {
                    e.preventDefault();
                    showLocationRequiredAlert();
                    return;
                }
                e.preventDefault();
                var questionId = $(this).data('id');
                var locationInput = $('#location_' + questionId);
                var locationStatus = $('#location_status_' + questionId);
                if (navigator.geolocation) {
                    locationStatus.text('Getting location...');
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            locationInput.val(position.coords.latitude + ", " + position.coords
                                .longitude);
                            locationStatus.text('Location retrieved.');
                        },
                        function(error) {
                            locationStatus.text('Failed to get location.');
                        }
                    );
                }
            });

            $('button[id^="get_subj_location_"]').on('click', function(e) {
                if (!locationObtained) {
                    e.preventDefault();
                    showLocationRequiredAlert();
                    return;
                }
                e.preventDefault();
                var questionId = $(this).data('id');
                var locationInput = $('#location_sub_' + questionId);
                var locationStatus = $('#location_subj_status_' + questionId);
                if (navigator.geolocation) {
                    locationStatus.text('Getting location...');
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            locationInput.val(position.coords.latitude + ", " + position.coords
                                .longitude);
                            locationStatus.text('Location retrieved.');
                        },
                        function(error) {
                            locationStatus.text('Failed to get location.');
                        }
                    );
                }
            });

            $('#approve, #reject').on('click', function() {
                if (!locationObtained) {
                    showLocationRequiredAlert();
                    return false;
                }
            });

            $(".subjective_ques").change(function(event) {
                if (!locationObtained) {
                    showLocationRequiredAlert();
                    $(this).val('');
                    return;
                }
                let subjectTd = $(this).closest('td');
                const subjectInfo = $(this).val();
                let qid = $(this).data('q_id');

                // Clear all dependent inputs when switching subject
                subjectTd.find('input[type="text"], input[type="date"], input[type="datetime-local"]').val(
                    '');
                subjectTd.find('select:not(.subjective_ques)').val('');
                subjectTd.find('input[type="file"]').val('');
                subjectTd.find('.audio-data').val('');
                subjectTd.find('.video-data').val('');
                subjectTd.find('.recording-status').text('').removeClass();
                subjectTd.find('[id^="audio-player-"]').addClass('d-none').attr('src', '');
                subjectTd.find('[id^="video-preview-"]').addClass('d-none').attr('src', '');

                if (qid === "" || subjectInfo === "") return;

                // Hide ALL dependent sections before AJAX — fixes previously-selected subject staying visible
                subjectTd.find('.recording-section').addClass('d-none');
                subjectTd.find('select:not(.subjective_ques)').addClass('d-none').val('').removeAttr(
                    'required').prop('disabled', true);
                subjectTd.find(
                    'input[type="text"], input[type="date"], input[type="datetime-local"], input[type="file"]'
                ).addClass('d-none').val('').removeAttr('required').prop('disabled', true);
                subjectTd.find('span.badge').addClass('d-none');
                subjectTd.find('.audio-data, .video-data').val('');
                subjectTd.find('[id^="audio-player-"]').addClass('d-none').attr('src', '');
                subjectTd.find('[id^="video-preview-"]').addClass('d-none').attr('src', '');
                subjectTd.find('[id^="get_subj_location_"]').addClass('d-none').prop('disabled', true);

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
                            let otherIds = response.otherSubjectiveIds;
                            let subject_answer_type = response.subjectOption.answer_type
                                .replace(/\s+/g, '');
                            let subjectValue = subject_answer_type + response.subjectOption.id;
                            let subjectTypes = ["Location", "Audio", "Video", "Image",
                                "FileUpload", "Yes/No", "Date", "Date&Time", "Dropdown",
                                "FreeText", "Multiselect"
                            ];

                            // Hide & clear all other subjects' dependent fields
                            subjectTypes.forEach(type => {
                                otherIds.forEach(id => {
                                    let safeType = type.replace(/\W+/g, "_");
                                    let $els = $(`.${safeType}${id}`);
                                    $els.addClass("d-none").removeAttr(
                                        "required").prop("disabled", true);
                                    $els.filter(
                                        'input[type="text"], input[type="date"], input[type="datetime-local"]'
                                    ).val('');
                                    $els.filter('select').val('');
                                    $els.filter('input[type="file"]').val('');
                                    $els.filter('.audio-data, .video-data').val(
                                        '');
                                });
                            });

                            if (document.querySelector(`.${subjectValue}`)) {
                                // $(`.${subjectValue}`).removeClass('d-none').attr('required',
                                //     true).prop('disabled', false);
                                let $matchedField = $(`.${subjectValue}`);
                                $matchedField.removeClass('d-none').attr('required', true).prop(
                                    'disabled', false);
                                // Restore original prefill value when user switches back to this subject
                                $matchedField.each(function() {
                                    let origVal = $(this).attr('data-original-value');
                                    if (origVal !== undefined && origVal !== null &&
                                        origVal !== '') {
                                        $(this).val(origVal);
                                    }
                                });
                                if (subject_answer_type == 'Location') {
                                    $(`#get_subj_location_${qid}`).removeClass('d-none').prop(
                                        "disabled", false);
                                    $(`#location_subj_status_${qid}`).removeClass('d-none');
                                    $(`#location_subj_${qid}`).attr('required', true);
                                } else {
                                    $(`#get_subj_location_${qid}`).addClass('d-none').prop(
                                        "disabled", true);
                                    $(`#location_subj_status_${qid}`).addClass('d-none').prop(
                                        "disabled", true);
                                    $(`#location_subj_${qid}`).removeAttr('required').prop(
                                        "disabled", true);
                                }
                            } else {
                                $(`.${subjectValue}`).addClass('d-none').prop("disabled", true)
                                    .removeAttr('required');
                                $(`#get_subj_location_${qid}`).addClass('d-none').prop(
                                    "disabled", true);
                                $(`#location_subj_status_${qid}`).addClass('d-none').prop(
                                    "disabled", true);
                                $(`#location_subj_${qid}`).removeAttr('required').prop(
                                    "disabled", true);
                            }
                        }
                    }
                });
            });

            $('#add_outlet_btn').on('click', function() {
                if (!locationObtained) {
                    showLocationRequiredAlert();
                    return;
                }
                $('#outlet_form').removeClass('d-none');
            });

            // ── Multi-image: preview on select, upload on Save ───────────────────

            // Show local preview thumbnail immediately when user picks a file
            $(document).on('change', '.multi-image-file-input', function () {
                var file = this.files[0];
                if (!file) return;
                var $row    = $(this).closest('.multi-img-row');
                var $box    = $row.find('.img-preview-box');
                var reader  = new FileReader();
                reader.onload = function (e) {
                    $box.html('<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;border-radius:4px;">');
                    $box.css('border', '2px solid #28a745'); // green border = file selected
                };
                reader.readAsDataURL(file);
            });

            // "+" button: add another file input row
            $(document).on('click', '.add-image-row-btn', function () {
                var qid = $(this).data('qid');
                var row = '<div class="multi-img-row d-flex align-items-center gap-2 mb-2">'
                    + '<div class="img-preview-box flex-shrink-0" style="width:56px;height:56px;border-radius:6px;border:2px dashed #ccc;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#f8f9fa;">'
                    +   '<span class="preview-placeholder text-muted" style="font-size:20px;">🖼</span>'
                    + '</div>'
                    + '<input type="file" class="form-control form-control-sm multi-image-file-input" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp" style="flex:1;">'
                    + '<button type="button" class="btn btn-outline-danger btn-sm remove-img-row-btn flex-shrink-0" style="width:32px;height:32px;padding:0;font-size:20px;line-height:1;" title="Remove row">×</button>'
                    + '</div>';
                $('#inputs_' + qid).append(row);
            });

            // "×" on an added row: remove it
            $(document).on('click', '.remove-img-row-btn', function () {
                $(this).closest('.multi-img-row').remove();
            });

            // Remove a previously-saved image row
            $(document).on('click', '.remove-saved-img-btn', function () {
                $(this).closest('.saved-img-row').remove();
            });

            // Multi-image submit is now handled by the unified form submit handler above.
            // ── End multi-image ──────────────────────────────────────────────────

        });
    </script>
@endsection
