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
            /* Force GPU compositing — prevents position:fixed clipping in Android WebView */
            -webkit-transform: translateZ(0);
            transform: translateZ(0);
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
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
    <!-- Location Permission Overlay (hidden by default; shown only when permission is needed) -->
    <div id="locationOverlay" class="location-overlay" style="display:none">
        <div class="spinner" id="locationSpinner"></div>
        <h3>Location Permission Required</h3>
        <p id="locationMessage">Please allow location access to continue filling the form. This is required for all
            submissions.</p>
        <div id="permissionDeniedGuide" class="permission-guide d-none">
            <h6>&#240;&#376;&#8220;&#177; How to enable location permission:</h6>
            <ol>
                <li>Click the lock icon (&#240;&#376;&#8221;&#8217;) in your browser's address bar</li>
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
                                            {{-- Delete button: only for non-original instances that haven't been submitted --}}
                                            @if (!empty($repeat_instance) && $repeat_instance->status == 0)
                                                <button type="button" id="deleteInstanceBtn"
                                                    data-instance-id="{{ $repeat_instance->id }}"
                                                    style="background:rgba(220,53,69,0.15);border:1px solid rgba(220,53,69,0.5);color:#dc3545;border-radius:8px;padding:6px 10px;font-size:12px;font-weight:600;display:flex;align-items:center;gap:4px;cursor:pointer;"
                                                    title="Remove this instance">
                                                    <i class="fa fa-trash" style="font-size:11px;"></i>
                                                </button>
                                            @endif
                                            {{-- Add button moved to FAB above bottom nav --}}
                                            {{-- Submit button: only show when the original instance is saved and no sent-backs --}}
                                            @if ($original_submitted)
                                                @if (empty($has_any_sent_back))
                                                    <button type="button" id="submitActivityBtn"
                                                        style="background:rgba(220,53,69,0.9);border:none;color:#fff;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;display:flex;align-items:center;gap:5px;">
                                                        <i class="fa fa-check" style="font-size:11px;"></i> Submit
                                                    </button>
                                                @else
                                                    {{-- Some instances are sent back — show re-submit prompt --}}
                                                    <div style="background:rgba(255,152,0,0.15);border:1px solid rgba(255,152,0,0.6);border-radius:8px;padding:5px 12px;display:flex;align-items:center;gap:6px;">
                                                        <i class="fa fa-exclamation-triangle" style="color:#ff9800;font-size:12px;"></i>
                                                        <span style="color:#e65100;font-size:11px;font-weight:600;">Fix sent-back instance(s), then Submit</span>
                                                    </div>
                                                    {{-- Show Submit only after all sent-back instances are corrected --}}
                                                    <button type="button" id="submitActivityBtn"
                                                        style="background:rgba(220,53,69,0.9);border:none;color:#fff;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;display:flex;align-items:center;gap:5px;">
                                                        <i class="fa fa-check" style="font-size:11px;"></i> Submit
                                                    </button>
                                                @endif
                                            @endif
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
                                    // Ensure the current sequence is always in the nav list
                                    // (can be missing after a delete+add cycle with non-sequential IDs)
                                    if ($currentSeq > 0 && !in_array($currentSeq, $navSequences)) {
                                        $navSequences[] = $currentSeq;
                                        sort($navSequences);
                                    }
                                    $navIdx = array_search($currentSeq, $navSequences);
                                    if ($navIdx !== false) {
                                        if ($navIdx > 0) $prevSeq = $navSequences[$navIdx - 1];
                                        if ($navIdx < count($navSequences) - 1) $nextSeq = $navSequences[$navIdx + 1];
                                    }
                                    $totalNav = count($navSequences);
                                    $instanceLabel = $currentSeq == 0
                                        ? 'Original'
                                        : ($repeat_instance->instance_label ?? ('Instance ' . $currentSeq));
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

                                            {{-- &#226;&#8221;&#8364;&#226;&#8221;&#8364; Sent Back Warning Banner &#226;&#8221;&#8364;&#226;&#8221;&#8364; --}}
                                            @if (!empty($is_sent_back) && $is_sent_back)
                                                <div class="alert d-flex align-items-center gap-2 mx-3 mt-2"
                                                    style="background:#fff3cd; border:1.5px solid #ffc107; border-radius:8px; padding:10px 14px; font-size:13px;">
                                                    <span style="font-size:18px;">&#226;&#353; &#239;&#184;&#143;</span>
                                                    <div>
                                                        <strong>This submission was sent back for correction.</strong><br>
                                                        <span class="text-muted" style="font-size:12px;">Please review your
                                                            answers below and resubmit.</span>
                                                    </div>
                                                </div>
                                            @endif

                                            {{-- &#226;&#8221;&#8364;&#226;&#8221;&#8364; Repeat Instance Banner &#226;&#8221;&#8364;&#226;&#8221;&#8364; --}}
                                            @if (!empty($activity_sequence))
                                                <div class="alert d-flex align-items-center gap-2 mx-3 mt-2"
                                                    style="background:#e7f1ff; border:1.5px solid #4a6cf7; border-radius:8px; padding:10px 14px; font-size:13px;">
                                                    <span style="font-size:18px;">&#240;&#376;&#8220;&#157;</span>
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
                                                    <span style="font-size:18px;">&#226;&#157;&#338;</span>
                                                    <div>
                                                        <strong>Submission Failed</strong><br>
                                                        <span class="text-muted" style="font-size:12px;">{{ session('error') }}</span>
                                                    </div>
                                                </div>
                                            @endif

                                            <form id="activityForm" method="post"
                                                action="{{ route('user.rowId.activity.answers', ['activity_sequence' => $activity_sequence ?? 0]) }}"
                                                enctype="multipart/form-data"
                                                novalidate>
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

                                                {{-- &#226;&#8221;&#8364;&#226;&#8221;&#8364; Build prefill lookup from sent-back answers &#226;&#8221;&#8364;&#226;&#8221;&#8364; --}}
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
                                                            $qid    = $ans->question_id;
                                                            $pctx   = $ans->parent_context_id; // null for standalone, int for shared sub-questions
                                                            $question = $all_questions_flat->firstWhere('id', $qid);

                                                            // Shared sub-question: store as 2D array [$qid][$parent_context_id]
                                                            if ($pctx !== null) {
                                                                if (!isset($prefilled[$qid]) || !is_array($prefilled[$qid])) {
                                                                    $prefilled[$qid] = [];
                                                                }
                                                                $prefilled[$qid][$pctx] = $ans->user_answer;
                                                                continue;
                                                            }

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
                                                                    $prefilled[$qid] = $ans->user_answer;
                                                                } else {
                                                                    if (!isset($prefilledSubDependent[$qid])) {
                                                                        $prefilledSubDependent[$qid] = [];
                                                                    }
                                                                    $prefilledSubDependent[$qid][] = $ans->user_answer;
                                                                }
                                                            } else {
                                                                // For non-subjective questions
                                                                if (isset($prefilledSub[$qid])) {
                                                                    $prefilledSub[$qid][] = $ans->user_answer;
                                                                } elseif (isset($prefilled[$qid]) && !is_array($prefilled[$qid])) {
                                                                    $prefilledSub[$qid] = [
                                                                        $prefilled[$qid],
                                                                        $ans->user_answer,
                                                                    ];
                                                                    unset($prefilled[$qid]);
                                                                } elseif (!isset($prefilled[$qid])) {
                                                                    // Only set if not already populated (avoids overwriting
                                                                    // array-based pctx prefill with a plain string)
                                                                    $prefilled[$qid] = $ans->user_answer;
                                                                }
                                                                // If $prefilled[$qid] is already an array (pctx-based),
                                                                // leave it alone &#226;&#8364;&#8221; the render_question_row partial handles it
                                                            }
                                                        }
                                                    }
                                                    $selectArrIds = [];

                                                    // IDs of questions that have __non_empty__ conditional children
                                                    // These need data-question-id + non-empty-parent class on their input
                                                    $nonEmptyParentIds = $related_questions
                                                        ->where('parent_value', '__non_empty__')
                                                        ->pluck('parent_question_id')
                                                        ->filter()
                                                        ->unique()
                                                        ->toArray();

                                                    // IDs of ALL questions referenced as a conditional trigger (parent_question_id).
                                                    // Used to add .parent-question class even on is_parent=0 questions
                                                    // (e.g. Yes/No sub-question inside MR that has its own conditional children).
                                                    $conditionalTriggerIds = $related_questions
                                                        ->pluck('parent_question_id')
                                                        ->filter()
                                                        ->unique()
                                                        ->toArray();

                                                    // Build question code maps:
                                                    // $questionCodeMap[question_id] = 'Q001' (parent codes)
                                                    // $subCodeMap[parent_id][child_id] = 'Q001a' (per-parent sub-question codes)
                                                    // Using per-parent map because the same physical question can be a sub-question
                                                    // of multiple MR parents with different sequence positions (Q001a vs Q002b etc.)
                                                    $questionCodeMap = [];
                                                    $subCodeMap = [];
                                                    $parentSeq = 0;

                                                    $subLinksByParent = $related_questions
                                                        ->flatMap(fn($q) => $q->subQuestions ?? collect())
                                                        ->groupBy('parent_question_id');

                                                    foreach ($related_questions as $_cq) {
                                                        if ($_cq->is_parent != 1) continue;
                                                        if (in_array($_cq->id, $sub_question_child_ids)) continue;
                                                        $parentSeq++;
                                                        $parentCode = 'Q' . str_pad($parentSeq, 3, '0', STR_PAD_LEFT);
                                                        $questionCodeMap[$_cq->id] = $parentCode;

                                                        $alpha = 0;
                                                        foreach (($subLinksByParent[$_cq->id] ?? collect())->sortBy('sequence') as $_sl) {
                                                            $child = $_sl->childQuestion ?? null;
                                                            if ($child) {
                                                                $alpha++; // only increment for sub-questions that actually exist
                                                                // Per-parent code: each MR parent gets its own codes for its sub-questions
                                                                $subCodeMap[$_cq->id][$child->id] = $parentCode . chr(96 + $alpha);
                                                                // Also store in flat map for first occurrence (fallback for non-shared questions)
                                                                if (!isset($questionCodeMap[$child->id])) {
                                                                    $questionCodeMap[$child->id] = $parentCode . chr(96 + $alpha);
                                                                }
                                                            }
                                                        }
                                                    }
                                                    // Conditional children (is_parent=0 with parent_question_id) that aren't MR sub-questions
                                                    foreach ($related_questions as $_cq) {
                                                        if (isset($questionCodeMap[$_cq->id])) continue;
                                                        if ($_cq->is_parent == 0 && $_cq->parent_question_id && isset($questionCodeMap[$_cq->parent_question_id])) {
                                                            $baseCode = $questionCodeMap[$_cq->parent_question_id];
                                                            $alpha = count($subLinksByParent[$_cq->parent_question_id] ?? collect()) + 1;
                                                            $questionCodeMap[$_cq->id] = $baseCode . chr(96 + $alpha);
                                                        }
                                                    }
                                                @endphp

                                                <table class="table mb-0">
                                                    <thead></thead>
                                                    <tbody>
                                                        @foreach ($related_questions as $related_question)
                                                            @php
                                                                $qid = $related_question->id;
                                                                if (in_array($qid, $sub_question_child_ids) && empty($related_question->parent_question_id)) continue;

                                                                // &#226;&#8221;&#8364;&#226;&#8221;&#8364; pctx input naming &#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;
                                                                // Conditional children (is_parent=0, parent_question_id set)
                                                                // use {qid}_pctx_{parent_question_id} so each parent-child
                                                                // relationship gets its own separate answer record.
                                                                // This allows the same child question to be triggered by
                                                                // multiple different Yes/No or Dropdown parents and still
                                                                // store distinct answers per triggering parent.
                                                                $conditionalParentId = ($related_question->is_parent == 0 && $related_question->parent_question_id)
                                                                    ? $related_question->parent_question_id
                                                                    : null;
                                                                $mainInputName = $conditionalParentId
                                                                    ? $qid . '_pctx_' . $conditionalParentId
                                                                    : (string)$qid;

                                                                // Prefill: use pctx-based lookup for conditional children
                                                                $rawPrefill = $prefilled[$qid] ?? '';
                                                                if ($conditionalParentId && is_array($rawPrefill)) {
                                                                    // New format: pctx stored per parent
                                                                    $savedAnswer = $rawPrefill[$conditionalParentId] ?? $rawPrefill[null] ?? '';
                                                                } elseif (is_array($rawPrefill)) {
                                                                    // Sub-question only (no conditional parent in main loop)
                                                                    $savedAnswer = $rawPrefill[null] ?? '';
                                                                } else {
                                                                    $savedAnswer = $rawPrefill;
                                                                }
                                                                $savedSub = $prefilledSub[$qid] ?? [];

                                                                // &#226;&#8221;&#8364;&#226;&#8221;&#8364; Build validation HTML attributes from question config &#226;&#8221;&#8364;&#226;&#8221;&#8364;
                                                                $vRule  = $related_question->validation_rule ?? 'none';
                                                                $vMin   = $related_question->validation_min;
                                                                $vMax   = $related_question->validation_max;
                                                                $vRegex = $related_question->validation_regex;
                                                                $vAttrs = '';
                                                                $vMsg   = '';
                                                                switch ($vRule) {
                                                                    case 'numericrange':
                                                                        $vAttrs = 'type="number" step="any"'
                                                                            . ($vMin !== null ? " min=\"{$vMin}\"" : '')
                                                                            . ($vMax !== null ? " max=\"{$vMax}\"" : '');
                                                                        $vMsg = "Enter a number" . ($vMin !== null ? " ≥ {$vMin}" : '') . ($vMax !== null ? " and ≤ {$vMax}" : '');
                                                                        break;
                                                                    case 'digitlength':
                                                                        $vAttrs = 'inputmode="numeric" pattern="\d{'
                                                                            . ($vMin ?? '1') . ',' . ($vMax ?? '') . '}"';
                                                                        $vMsg = "Enter " . ($vMin == $vMax ? "{$vMin}" : "{$vMin}–{$vMax}") . " digits";
                                                                        break;
                                                                    case 'textlength':
                                                                        $vAttrs = ($vMin !== null ? " minlength=\"{$vMin}\"" : '')
                                                                            . ($vMax !== null ? " maxlength=\"{$vMax}\"" : '');
                                                                        $vMsg = "Enter " . ($vMin !== null ? "at least {$vMin}" : '') . ($vMax !== null ? "–{$vMax}" : '') . " characters";
                                                                        break;
                                                                    case 'email':
                                                                        $vAttrs = 'type="email"';
                                                                        $vMsg   = "Enter a valid email address";
                                                                        break;
                                                                    case 'phone':
                                                                        $vAttrs = 'type="tel" pattern="[6-9]\d{9}" inputmode="numeric"';
                                                                        $vMsg   = "Enter a valid 10-digit mobile number";
                                                                        break;
                                                                    case 'regex':
                                                                        if ($vRegex) {
                                                                            $vAttrs = 'pattern="' . e($vRegex) . '"';
                                                                            $vMsg   = "Value does not match the required format";
                                                                        }
                                                                        break;
                                                                }
                                                                $vDataAttrs = $vMsg ? "data-validation-msg=\"" . e($vMsg) . "\"" : '';
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
                                                                        @if(isset($questionCodeMap[$related_question->id]))
                                                                            <span style="font-size:10px;font-family:monospace;background:#F3F4F6;border:1px solid #E5E7EB;border-radius:4px;padding:1px 5px;color:#374151;font-weight:700;margin-right:5px;vertical-align:middle;white-space:nowrap;">{{ $questionCodeMap[$related_question->id] }}</span>
                                                                        @endif
                                                                        {{ html_entity_decode($related_question->question, ENT_QUOTES, 'UTF-8') }}
                                                                        @if ($related_question->answer_type)
                                                                            <span class="txt-danger">*</span>
                                                                        @endif
                                                                        @if (!empty($related_question->help_text))
                                                                            <span class="help-text-icon"
                                                                                data-help="{{ html_entity_decode($related_question->help_text, ENT_QUOTES, 'UTF-8') }}"
                                                                                style="display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;border-radius:50%;background:#2563EB;color:#fff;font-size:10px;font-weight:700;cursor:pointer;margin-left:4px;vertical-align:middle;flex-shrink:0;">i</span>
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
                                                                                    {{-- Use mainInputName (includes pctx) as unique key so the same Image
                                                                                         question linked to multiple parents each gets its own independent wrapper --}}
                                                                                    <div class="multi-image-wrapper"
                                                                                         data-qid="{{ $mainInputName }}"
                                                                                         data-upload-url="{{ route('upload.activity.image.temp') }}"
                                                                                         data-row-id="{{ $row_data->id }}"
                                                                                         style="overflow:visible;">

                                                                                        @foreach ($savedPaths as $sp)
                                                                                            <div class="multi-img-row d-flex align-items-center gap-2 mb-2 saved-img-row">
                                                                                                <img src="{{ asset($sp) }}"
                                                                                                     style="width:56px;height:56px;object-fit:cover;border-radius:6px;border:1px solid #ccc;flex-shrink:0;">
                                                                                                <button type="button" class="btn btn-outline-danger btn-sm remove-saved-img-btn"
                                                                                                        style="width:32px;height:32px;padding:0;font-size:16px;line-height:1;" title="Remove">&#215;</button>
                                                                                                <input type="hidden" name="multi_images_{{ $mainInputName }}[]" value="{{ $sp }}">
                                                                                            </div>
                                                                                        @endforeach

                                                                                        <div class="multi-img-inputs" id="inputs_{{ $mainInputName }}" style="overflow:visible;">
                                                                                            @include('masters.users.partials.image_input_row', ['inputName' => $mainInputName, 'isFirst' => true])
                                                                                        </div>

                                                                                        <div class="multi-upload-progress d-none mt-1" id="progress_{{ $mainInputName }}">
                                                                                            <div class="d-flex align-items-center gap-2">
                                                                                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                                                                                <small class="text-muted">Uploading images...</small>
                                                                                            </div>
                                                                                        </div>

                                                                                        @if ($related_question->answer_type && empty($savedPaths))
                                                                                            <input type="hidden" class="multi-image-required" data-qid="{{ $mainInputName }}">
                                                                                        @endif
                                                                                    </div>
                                                                                    {{-- Button OUTSIDE the wrapper -- cannot be clipped by any overflow:hidden on the wrapper --}}
                                                                                    <div class="add-image-row-btn-wrap" data-for-qid="{{ $mainInputName }}">
                                                                                        <button type="button" class="add-image-row-btn" data-qid="{{ $mainInputName }}"
                                                                                                onmousedown="this.blur()">
                                                                                            + add more
                                                                                        </button>
                                                                                    </div>
                                                                                @else
                                                                                    @if ($savedAnswer)
                                                                                        <div class="mb-2">
                                                                                            <img src="{{ asset($savedAnswer) }}"
                                                                                                alt="Existing image"
                                                                                                style="max-width:100%; max-height:160px; border-radius:6px; border:1px solid #ddd;">
                                                                                            <small class="text-muted d-block mt-1">Current image - upload a new one to replace it</small>
                                                                                        </div>
                                                                                        <input type="hidden" name="{{ $mainInputName }}_existing" value="{{ $savedAnswer }}">
                                                                                    @endif
                                                                                    @php $camUid = $mainInputName . '_single_' . uniqid(); @endphp
                                                                                    {{-- Single input: Camera button adds capture="environment" then clicks it;
                                                                                         Gallery button removes capture then clicks it. One named input = PHP
                                                                                         always receives exactly one file regardless of which button was used. --}}
                                                                                    <input type="file" id="sel_{{ $camUid }}" name="{{ $mainInputName }}"
                                                                                           class="d-none single-img-input" accept="image/*"
                                                                                           @if($related_question->answer_type && !$savedAnswer) data-img-required="true" @endif>
                                                                                    <div class="d-flex gap-2 mt-1">
                                                                                        <button type="button" id="camBtn_{{ $camUid }}" class="btn btn-sm" style="flex:1;border:1.5px solid #2563EB;background:#EFF6FF;color:#1D4ED8;font-weight:600;">Camera</button>
                                                                                        <button type="button" id="galBtn_{{ $camUid }}" class="btn btn-sm" style="flex:1;border:1.5px solid #059669;background:#F0FDF4;color:#065F46;font-weight:600;">Gallery</button>
                                                                                    </div>
                                                                                    <div id="fn_{{ $camUid }}" style="font-size:11px;color:#6B7280;margin-top:4px;display:none;"></div>
                                                                                    <script>
                                                                                    (function(){
                                                                                        var sel=document.getElementById('sel_{{ $camUid }}');
                                                                                        var fn =document.getElementById('fn_{{ $camUid }}');
                                                                                        document.getElementById('camBtn_{{ $camUid }}').addEventListener('click',function(){
                                                                                            sel.setAttribute('capture','environment');
                                                                                            sel.click();
                                                                                        });
                                                                                        document.getElementById('galBtn_{{ $camUid }}').addEventListener('click',function(){
                                                                                            sel.removeAttribute('capture');
                                                                                            sel.click();
                                                                                        });
                                                                                        sel.addEventListener('change',function(){
                                                                                            if(this.files&&this.files[0]){
                                                                                                fn.textContent=this.files[0].name;
                                                                                                fn.style.display='block';
                                                                                            }
                                                                                        });
                                                                                    })();
                                                                                    </script>
                                                                                @endif
                                                                            @break

                                                                            @case('Free Text')
                                                                                <input value="{{ $savedAnswer }}"
                                                                                    name="{{ $mainInputName }}"
                                                                                    class="form-control {{ in_array($qid,$nonEmptyParentIds)?'non-empty-parent':'' }}"
                                                                                    @if(in_array($qid,$nonEmptyParentIds)) data-question-id="{{ $qid }}" @endif
                                                                                    {!! $vAttrs !!} {!! $vDataAttrs !!}
                                                                                    @if ($related_question->answer_type) required data-required="true" @endif>
                                                                            @break

                                                                            @case('Yes / No')
                                                                                <select
                                                                                    class="form-select {{ ($related_question->is_parent == 1 || in_array($qid, $conditionalTriggerIds)) ? 'parent-question' : '' }}"
                                                                                    data-question-id="{{ $qid }}"
                                                                                    name="{{ $mainInputName }}"
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
                                                                                    class="form-select {{ ($related_question->is_parent == 1 || in_array($qid, $conditionalTriggerIds)) ? 'parent-question' : '' }}"
                                                                                    data-question-id="{{ $qid }}"
                                                                                    name="{{ $mainInputName }}"
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
                                                                                    name="{{ $mainInputName }}" class="form-control {{ in_array($qid,$nonEmptyParentIds)?'non-empty-parent':'' }}"
                                                                                    @if(in_array($qid,$nonEmptyParentIds)) data-question-id="{{ $qid }}" @endif
                                                                                    @if($related_question->date_min) min="{{ $related_question->date_min }}" @endif
                                                                                    @if($related_question->date_max) max="{{ $related_question->date_max }}" @endif
                                                                                    @if ($related_question->answer_type) required data-required="true" @endif>
                                                                                @if($related_question->date_min || $related_question->date_max)
                                                                                    <small class="text-muted">
                                                                                        @if($related_question->date_min && $related_question->date_max)
                                                                                            Date must be between {{ $related_question->date_min }} and {{ $related_question->date_max }}
                                                                                        @elseif($related_question->date_min)
                                                                                            Date must be on or after {{ $related_question->date_min }}
                                                                                        @else
                                                                                            Date must be on or before {{ $related_question->date_max }}
                                                                                        @endif
                                                                                    </small>
                                                                                @endif
                                                                            @break

                                                                            @case('Date & Time')
                                                                                @php
                                                                                    $savedDT = '';
                                                                                    if ($savedAnswer) {
                                                                                        // Stored as d/m/Y H:i &#226;&#8364;&#8221; try explicit format first, then fallback to parse
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
                                                                                    name="{{ $mainInputName }}" class="form-control"
                                                                                    @if($related_question->date_min) min="{{ $related_question->date_min }}T00:00" @endif
                                                                                    @if($related_question->date_max) max="{{ $related_question->date_max }}T23:59" @endif
                                                                                    @if ($related_question->answer_type) required data-required="true" @endif>
                                                                            @break

                                                                            @case('Location')
                                                                                <input type="text"
                                                                                    id="location_{{ $qid }}"
                                                                                    name="{{ $mainInputName }}"
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
                                                                                            class="btn btn-sm btn-outline-secondary">View existing file</a>
                                                                                        <small
                                                                                            class="text-muted d-block mt-1">Upload
                                                                                            a new file to replace it</small>
                                                                                    </div>
                                                                                    <input type="hidden"
                                                                                        name="{{ $mainInputName }}_existing"
                                                                                        value="{{ $savedAnswer }}">
                                                                                @endif
                                                                                <input type="file" name="{{ $mainInputName }}"
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
                                                                                        name="{{ $mainInputName }}"
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
                                                                                            name="{{ $mainInputName }}_existing_path"
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
                                                                                        name="{{ $mainInputName }}"
                                                                                        class="video-data"
                                                                                        data-id="{{ $qid }}"
                                                                                        value="{{ $savedAnswer ?? '' }}">
                                                                                </div>
                                                                            @break

                                                                            @case('Barcode')
                                                                            @case('QR Code')
                                                                                <div class="scan-question-wrap" data-qid="{{ $qid }}">
                                                                                    <div class="d-flex gap-2 align-items-center">
                                                                                        <input type="text" class="form-control"
                                                                                            name="{{ $mainInputName }}"
                                                                                            id="scan_input_{{ $qid }}"
                                                                                            value="{{ $savedAnswer }}"
                                                                                            placeholder="Scan or type value"
                                                                                            @if ($related_question->answer_type) required data-required="true" @endif>
                                                                                        <button type="button"
                                                                                            class="btn btn-sm btn-primary scan-open-btn"
                                                                                            data-target-id="{{ $qid }}"
                                                                                            style="white-space:nowrap;flex-shrink:0;">
                                                                                            <i class="fa fa-camera"></i> Scan
                                                                                        </button>
                                                                                    </div>
                                                                                    <div id="scan_status_{{ $qid }}" class="small text-muted mt-1"></div>
                                                                                </div>
                                                                            @break

                                                                            @case('RFID')
                                                                                {{-- RFID cannot be read from a standard browser page (needs
                                                                                     dedicated reader hardware). Web NFC (NDEFReader) works ONLY
                                                                                     on Chrome for Android over HTTPS — everywhere else (iPhone,
                                                                                     desktop, other browsers) this is a plain manual-entry field. --}}
                                                                                <div class="rfid-question-wrap" data-qid="{{ $qid }}">
                                                                                    <div class="d-flex gap-2 align-items-center">
                                                                                        <input type="text" class="form-control"
                                                                                            name="{{ $mainInputName }}"
                                                                                            id="scan_input_{{ $qid }}"
                                                                                            value="{{ $savedAnswer }}"
                                                                                            placeholder="Tap RFID/NFC tag, or type value manually"
                                                                                            @if ($related_question->answer_type) required data-required="true" @endif>
                                                                                        <button type="button"
                                                                                            class="btn btn-sm btn-outline-primary rfid-scan-btn"
                                                                                            data-target-id="{{ $qid }}"
                                                                                            style="white-space:nowrap;flex-shrink:0;">
                                                                                            <i class="fa fa-wifi"></i> Scan (NFC)
                                                                                        </button>
                                                                                    </div>
                                                                                    <div id="rfid_status_{{ $qid }}" class="small text-muted mt-1"></div>
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
                                                                                        name="{{ $mainInputName }}">
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
                                                                                                            View existing file
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
                                                                                    name="{{ $mainInputName }}"
                                                                                    @if ($related_question->answer_type) required data-required="true" @endif>
                                                                        @endswitch

                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            {{-- &#226;&#8221;&#8364;&#226;&#8221;&#8364; Sub-Questions: grouped in a card &#226;&#8221;&#8364;&#226;&#8221;&#8364; --}}
                                                            @if ($related_question->subQuestions->count() > 0)
                                                                <tr class="sub-questions-card-row">
                                                                    <td style="padding: 4px 0 10px 0;">
                                                                        <div style="border: 2px solid #4a6cf7; border-radius: 10px; overflow: hidden; background: #f0f4ff; margin: 0 6px;">
                                                                            <table class="table mb-0" style="margin:0; background:transparent;">
                                                                                <tbody>
                                                                                    @foreach ($related_question->subQuestions as $sub_link)
                                                                                        @if ($sub_link->childQuestion)
                                                                                            @include('masters.users.partials.render_question_row', [
                                                                                                'subQuestion'      => $sub_link->childQuestion,
                                                                                                'depth'            => 1,
                                                                                                'prefilled'        => $prefilled,
                                                                                                'parentQuestionId' => $qid,
                                                                                                'subLinkTrigger'   => $sub_link->trigger_value,
                                                                                                'row_data'         => $row_data,
                                                                                                'subCodeMap'       => $subCodeMap,
                                                                                            ])
                                                                                        @endif
                                                                                    @endforeach
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @endif
                                                            {{-- &#226;&#8221;&#8364;&#226;&#8221;&#8364; End Sub-Questions &#226;&#8221;&#8364;&#226;&#8221;&#8364; --}}

                                                        @endforeach
                                                    </tbody>
                                                </table>

                                                <input type="hidden" class="form-control" name="latitude"
                                                    id="latitude" value="">
                                                <input type="hidden" class="form-control" name="longitude"
                                                    id="longitude" value="">
                                                {{-- When another instance is sent back, lock this instance's Save button
                                                     unless THIS instance is also the one sent back --}}
                                                @php
                                                    $thisInstanceLocked = !empty($has_any_sent_back) && empty($is_sent_back);
                                                @endphp
                                                @if ($is_closed)
                                                    {{-- Activity fully closed — no saves allowed --}}
                                                @elseif ($thisInstanceLocked)
                                                    <button type="button" class="btn btn-secondary float-end" disabled
                                                        title="Fix the sent-back instance(s) first before editing this one.">
                                                        <i class="fa fa-lock me-1"></i> Locked
                                                    </button>
                                                @else
                                                    <div class="mb-4">
                                                        <button type="submit" id="approve" name="save"
                                                            class="btn btn-primary float-end">Save
                                                        </button>
                                                    </div>
                                                @endif
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

    {{-- Floating Add Instance Button (FAB) — only shown for add-on activities when not closed/sent-back --}}
    @if (!$is_closed && empty($has_any_sent_back))
        @php $canAddInstance = $current_instance_saved ?? $original_submitted; @endphp
        <button type="button" id="addInstanceBtn"
            data-can-add="{{ $canAddInstance ? '1' : '0' }}"
            style="position:fixed;bottom:76px;right:20px;
                   width:54px;height:54px;border-radius:50%;border:none;
                   background:{{ $canAddInstance ? '#1e293b' : '#6b7280' }};
                   color:#fff;
                   box-shadow:0 4px 16px rgba(0,0,0,0.28);
                   display:flex;align-items:center;justify-content:center;
                   cursor:pointer;
                   z-index:1040;
                   transition:transform .15s,box-shadow .15s;">
            <i class="fa fa-plus" style="font-size:22px;"></i>
        </button>
    @endif

    {{-- Bottom Navigation --}}
    <nav class="mobile-bottom-nav d-md-none">
        <a href="{{ route('user.projects') }}" class="{{ request()->routeIs('user.projects') ? 'active' : '' }}">
            <i class="icon-folder"></i> Projects
        </a>
        <a href="javascript:history.back()">
            <i class="icon-arrow-left"></i> Back
        </a>
    </nav>

    {{-- Shared Barcode/QR Code scan modal — one instance reused by every
         Barcode/QR Code question's "Scan" button (see .scan-open-btn JS below) --}}
    <div class="modal fade" id="barcodeScanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Scan Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="barcode-scan-reader" style="width:100%;"></div>
                    <div id="barcode-scan-error" class="text-danger small mt-2 d-none"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
    // &#226;&#8221;&#8364;&#226;&#8221;&#8364; Barcode / QR Code camera scanner &#226;&#8221;&#8364;&#226;&#8221;&#8364;
    // One shared modal + Html5Qrcode instance reused by every Barcode/QR Code
    // question's "Scan" button. Decoded text fills that question's own input
    // (matched via data-target-id -> #scan_input_{qid}) and the modal closes.
    (function () {
        var html5QrCode = null;
        var currentTargetId = null;
        var modalEl = document.getElementById('barcodeScanModal');
        if (!modalEl) return;

        function stopScanner() {
            if (html5QrCode) {
                html5QrCode.stop().then(function () { html5QrCode.clear(); }).catch(function () {});
                html5QrCode = null;
            }
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scan-open-btn');
            if (!btn) return;

            currentTargetId = btn.getAttribute('data-target-id');
            var errEl = document.getElementById('barcode-scan-error');
            errEl.classList.add('d-none');
            errEl.textContent = '';

            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();

            html5QrCode = new Html5Qrcode('barcode-scan-reader');
            Html5Qrcode.getCameras().then(function (devices) {
                if (!devices || !devices.length) throw new Error('No camera found on this device.');
                var backCam = devices.find(function (d) { return /back|rear|environment/i.test(d.label); });
                var cameraId = backCam ? backCam.id : devices[devices.length - 1].id;

                return html5QrCode.start(
                    cameraId,
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    function onScanSuccess(decodedText) {
                        var input = document.getElementById('scan_input_' + currentTargetId);
                        if (input) {
                            input.value = decodedText;
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                            // This page auto-saves each answer on native `blur`, not on
                            // change/input — the field was never actually focused during a
                            // scan, so a real blur never happens. Trigger jQuery's delegated
                            // blur handler directly so the scanned value actually gets
                            // auto-saved (otherwise it looks filled in but never reaches the
                            // server, and final submit fails with "missing" for this question).
                            $(input).trigger('blur');
                        }
                        stopScanner();
                        modal.hide();
                    }
                );
            }).catch(function (err) {
                errEl.textContent = 'Camera error: ' + (err && err.message ? err.message : err);
                errEl.classList.remove('d-none');
            });
        });

        modalEl.addEventListener('hidden.bs.modal', stopScanner);
    })();

    // &#226;&#8221;&#8364;&#226;&#8221;&#8364; RFID via Web NFC (Chrome for Android + HTTPS only) &#226;&#8221;&#8364;&#226;&#8221;&#8364;
    // Everywhere else this button just reports "not supported" and the field
    // stays a normal manual-entry text input (its default state).
    (function () {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.rfid-scan-btn');
            if (!btn) return;

            var targetId = btn.getAttribute('data-target-id');
            var statusEl = document.getElementById('rfid_status_' + targetId);

            if (!('NDEFReader' in window)) {
                statusEl.textContent = "NFC scanning isn't supported on this browser/device — enter the value manually.";
                statusEl.classList.add('text-danger');
                return;
            }

            statusEl.classList.remove('text-danger');
            statusEl.textContent = 'Hold the tag near the back of your device…';

            try {
                var reader = new NDEFReader();
                reader.scan().then(function () {
                    reader.onreading = function (event) {
                        var text = '';
                        for (var i = 0; i < event.message.records.length; i++) {
                            var record = event.message.records[i];
                            try {
                                var decoder = new TextDecoder(record.encoding || 'utf-8');
                                text += decoder.decode(record.data);
                            } catch (e2) {
                                text += new TextDecoder().decode(record.data);
                            }
                        }
                        var input = document.getElementById('scan_input_' + targetId);
                        if (input) {
                            input.value = text || event.serialNumber || '';
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                            // Same reasoning as the QR/Barcode handler: force the blur-based
                            // auto-save to actually run for this NFC-populated field.
                            $(input).trigger('blur');
                        }
                        statusEl.textContent = 'Tag read successfully.';
                    };
                }).catch(function (err) {
                    statusEl.textContent = 'NFC error: ' + (err && err.message ? err.message : err);
                    statusEl.classList.add('text-danger');
                });
            } catch (err) {
                statusEl.textContent = "NFC scanning isn't supported on this browser/device — enter the value manually.";
                statusEl.classList.add('text-danger');
            }
        });
    })();
    </script>
    <script>

    // &#226;&#8221;&#8364;&#226;&#8221;&#8364; Universal mobile keyboard suppression &#226;&#8221;&#8364;&#226;&#8221;&#8364;
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

        // ── React Native WebView bridge ───────────────────────────────────────
        // Detect RN WebView by checking the injected global or user-agent.
        var isRNWebView = !!(window.ReactNativeWebView) ||
                          /\bwv\b|ReactNative/i.test(navigator.userAgent);

        // Called by the RN app to inject device-level GPS coordinates:
        //   webViewRef.injectJavaScript("window.setRNLocation(lat, lng);");
        window.setRNLocation = function(lat, lng) {
            if (!lat || !lng) return;
            $('#latitude').val(lat);
            $('#longitude').val(lng);
            sessionStorage.setItem('loc_lat', lat);
            sessionStorage.setItem('loc_lng', lng);
            locationObtained = true;
            enableFormInputs(lat, lng);
        };

        // Also listen for postMessage events from the RN app:
        //   webViewRef.postMessage(JSON.stringify({ type: 'location', lat: x, lng: y }));
        function _rnMessageHandler(e) {
            var data;
            try { data = typeof e.data === 'string' ? JSON.parse(e.data) : e.data; } catch(_) { return; }
            if (data && data.type === 'location' && data.lat && data.lng) {
                window.setRNLocation(data.lat, data.lng);
            }
        }
        window.addEventListener('message',   _rnMessageHandler);   // iOS WebView
        document.addEventListener('message', _rnMessageHandler);   // Android WebView

        // Show pre-selected child rows (for sent-back pre-filled forms)
        function initParentChildOldadsdVisibility() {
            $('.parent-question').each(function() {
                let parentId = $(this).data('question-id');
                let selectedValue = String($(this).val() || '').trim();
                let parentRow = $(this).closest('tr');
                let lastInserted = parentRow;
                $('tr.child-question[data-parent-id="' + parentId + '"]').each(function() {
                    let childParentValue = String($(this).data('parent-value')).trim();

                    // Match logic:
                    // '__non_empty__' → show when parent has ANY non-empty answer
                    // specific value   → show when parent answer === that value (original behaviour)
                    var shouldShow = false;
                    if (childParentValue === '__non_empty__') {
                        shouldShow = selectedValue.length > 0;
                    } else {
                        shouldShow = selectedValue !== '' && childParentValue === selectedValue;
                    }

                    if (shouldShow) {
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

                // Support __non_empty__: show when parent has any answer
                const shouldShow = childParentValue === '__non_empty__'
                    ? selectedValue.length > 0
                    : childParentValue === selectedValue;

                if (shouldShow) {
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

            // &#226;&#8221;&#8364;&#226;&#8221;&#8364; New-style sub-question trigger visibility &#226;&#8221;&#8364;&#226;&#8221;&#8364;
            // For sub-question rows with data-trigger-value, show only when their parent matches
            evaluateSubQuestionTriggers();
        }

        function evaluateSubQuestionTriggers() {
            $('tr.sub-question-new-row[data-trigger-value]').each(function () {
                var $row       = $(this);
                var triggerVal = ($row.data('trigger-value') || '').toString().trim();
                var parentId   = ($row.data('pctx-parent-id') || '').toString().trim();
                if (!triggerVal || !parentId) return; // no trigger &#226;&#8364;&#8221; always visible

                // Find the parent question's current answer
                var $parentInput = $('[name="' + parentId + '"], [data-question-id="' + parentId + '"]').first();
                var currentVal   = ($parentInput.val() || '').toString().trim();

                if (currentVal === triggerVal) {
                    $row.removeClass('d-none');
                } else {
                    $row.addClass('d-none');
                }
            });
        }

        // Re-evaluate whenever any question answer changes
        $(document).on('change input', 'select, input[type="text"], input[type="number"]', function () {
            evaluateSubQuestionTriggers();
        });


        function enableFormInputs(lat, lng, silent) {
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

            // After enabling all inputs, initialize __non_empty__ conditional children
            // for any parent that already has a value (e.g., pre-filled on revisit)
            $('.non-empty-parent').each(function() {
                $(this).trigger('change');
            });
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

            if (!silent) {
                @if (session()->has('error'))
                    Swal.fire({
                        icon: 'error',
                        title: 'Submission Failed',
                        text: "{{ session('error') }}",
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#111111',
                        timer: 4000,
                        timerProgressBar: true,
                    }).then(() => { location.reload(); });
                @else
                    Swal.fire({
                        position: "top-center",
                        icon: "success",
                        title: "Location access granted!",
                        showConfirmButton: false,
                        timer: 1500
                    });
                @endif
            }

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
                // Permission already granted — fetch silently without showing the overlay
                $('#locationOverlay').hide();
                enableFormInputs(undefined, undefined, true); // enable form immediately; coords fill in below
                navigator.geolocation.getCurrentPosition(function(position) {
                    $('#longitude').val(position.coords.longitude);
                    $('#latitude').val(position.coords.latitude);
                    sessionStorage.setItem('loc_lat', position.coords.latitude);
                    sessionStorage.setItem('loc_lng', position.coords.longitude);
                    locationObtained = true;
                }, function(error) {
                    // Silent failure — location fields stay empty, form stays usable
                    console.warn('Silent location fetch failed:', error.message);
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 60000
                });
            } else if (permissionState === 'denied') {
                permissionDenied = true;
                disableFormInputs(
                    'Location permission was previously denied. Please enable location access in your browser settings to continue.',
                    true);
                Swal.fire({
                    icon: 'warning',
                    title: 'Location Permission Denied',
                    html: `<p>You have previously denied location access for this site.</p><p><strong>To enable location:</strong></p><ol style="text-align:left;margin-left:20px;"><li>Click the lock icon (&#240;&#376;&#8221;&#8217;) in your browser's address bar</li><li>Find "Location" in the permissions list</li><li>Change from "Block" to "Allow"</li><li>Click "Retry Location Access" below</li></ol>`,
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
                        // Timeout / position unavailable — hide spinner, show retry button
                        $('#locationSpinner').addClass('d-none');
                        var errMsg = error.code === error.TIMEOUT
                            ? 'Location request timed out. Please try again.'
                            : 'Failed to get location. Please try again.';
                        $('#locationMessage').text(errMsg);
                        Swal.fire({
                            icon: 'warning',
                            title: 'Location Error',
                            text: errMsg,
                            confirmButtonText: 'Try Again',
                            showCancelButton: true,
                            cancelButtonText: 'Cancel',
                            allowOutsideClick: false
                        }).then(function(result) {
                            if (result.isConfirmed) {
                                requestLocation();
                            } else {
                                // User cancelled — keep overlay but show retry button clearly
                                $('#locationMessage').text('Location access is required. Click "Retry Location Access" to try again.');
                            }
                        });
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

        // Track in-progress background image uploads so Save can wait for them
        var _pendingUploads = 0;
        // Track in-flight auto-save AJAX calls so Save waits for the last blur to finish
        var _pendingAutoSaves = 0;

        // Compress an image file to ≤1024px JPEG before uploading
        function compressImage(file) {
            return new Promise(function(resolve) {
                if (file.size < 200 * 1024) { resolve(file); return; }
                var img = new Image();
                var url = URL.createObjectURL(file);
                img.onload = function() {
                    URL.revokeObjectURL(url);
                    var MAX = 1024;
                    var w = img.width, h = img.height;
                    if (w > MAX) { h = Math.round(h * MAX / w); w = MAX; }
                    if (h > MAX) { w = Math.round(w * MAX / h); h = MAX; }
                    var canvas = document.createElement('canvas');
                    canvas.width = w; canvas.height = h;
                    canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                    canvas.toBlob(function(blob) { resolve(blob || file); }, 'image/jpeg', 0.72);
                };
                img.onerror = function() { URL.revokeObjectURL(url); resolve(file); };
                img.src = url;
            });
        }

        // Compress then POST one image to the server; returns Promise<path>
        function compressAndUpload(file, url, rowId) {
            return compressImage(file).then(function(blob) {
                var fd = new FormData();
                fd.append('image',     blob, 'photo.jpg');
                fd.append('row_id',    rowId);
                fd.append('latitude',  $('#latitude').val() || '');
                fd.append('longitude', $('#longitude').val() || '');
                fd.append('_token',    _csrfToken);
                return fetch(url, { method: 'POST', body: fd })
                    .then(function(resp) { return resp.ok ? resp.json() : Promise.reject('HTTP ' + resp.status); })
                    .then(function(res) {
                        if (!res.path) return Promise.reject('no path');
                        return res.path;
                    });
            });
        }
        var _sendOtpUrl = '{{ route('send_otp') }}';
        var _verifyOtpUrl = '{{ route('verify_otp') }}';
        var _addInstanceUrl = '{{ route('user.activity.add_instance') }}';

        // ── Inline field validation ────────────────────────────────────────────────
        function isFieldVisible($el) {
            if ($el.prop('disabled')) return false;
            // Hidden if the element itself or any ancestor has d-none (Bootstrap hidden class)
            return $el.closest('.d-none').length === 0;
        }

        function getFieldError($el) {
            if (!isFieldVisible($el)) return null;
            var el = $el[0];
            var val = $el.val();
            var isRequired = $el.prop('required') || $el.attr('data-required') === 'true';
            var customMsg = $el.attr('data-validation-msg') || '';

            // Required check
            var isEmpty = !val || (Array.isArray(val) ? val.length === 0 : String(val).trim() === '');
            if (isRequired && isEmpty) return 'This field is required';
            if (isEmpty) return null; // optional + empty = OK

            // Delegate to native browser validity (checks pattern, min, max, type, minlength, maxlength)
            if (el.checkValidity && !el.checkValidity()) {
                return customMsg || el.validationMessage || 'Invalid value';
            }
            return null;
        }

        function showFieldError($el, msg) {
            // For select2 elements the container span is inserted after the <select>,
            // so we anchor the error after the select2 container (or the element itself).
            var $anchor = $el;
            var $s2 = $el.nextAll('.select2-container').first();
            if ($s2.length) $anchor = $s2;

            var $err = $anchor.next('.field-error');
            if (!$err.length) {
                $err = $('<small class="field-error text-danger d-block mt-1" style="font-size:12px;"></small>').insertAfter($anchor);
            }
            $err.text(msg).show();
            $el.addClass('is-invalid').css('border-color', '#dc3545');
        }

        function clearFieldError($el) {
            var $s2 = $el.nextAll('.select2-container').first();
            var $anchor = $s2.length ? $s2 : $el;
            $anchor.next('.field-error').remove();
            $el.siblings('.field-error').remove(); // fallback sweep
            $el.removeClass('is-invalid').css('border-color', '');
        }

        function validateField($el) {
            var msg = getFieldError($el);
            if (msg) { showFieldError($el, msg); return false; }
            clearFieldError($el);
            return true;
        }

        function validateForm() {
            var valid = true;
            var $firstError = null;
            $('#activityForm').find('input:not([type="hidden"]):not([type="file"]), select, textarea').each(function() {
                var $el = $(this);
                if (!isFieldVisible($el)) return;
                if (!validateField($el)) {
                    valid = false;
                    if (!$firstError) $firstError = $el;
                }
            });
            if (!valid && $firstError) {
                setTimeout(function() {
                    $firstError[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 50);
            }
            return valid;
        }
        // ── End inline field validation ────────────────────────────────────────────

        // ── Unified form submit: images upload first, then AJAX save, then OTP if needed ──
        $('#activityForm').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);

            // 0. Validate all visible fields first
            if (!validateForm()) return;

            // 1. Location guard
            if (!locationObtained || !$('#latitude').val() || !$('#longitude').val()) {
                Swal.fire({ icon: 'error', title: 'Location Required',
                    text: 'Please allow location access before submitting.',
                    confirmButtonText: 'Get Location'
                }).then(r => { if (r.isConfirmed) requestLocation(); });
                return;
            }

            // 2a. Validate required single-image (Camera/Gallery) slots
            var missingSingle = false;
            $form.find('.single-img-input[data-img-required="true"]').each(function () {
                var hasFile   = this.files && this.files[0];
                var hasHidden = $form.find('input[name="' + $(this).attr('name') + '_existing"]').length > 0;
                if (!hasFile && !hasHidden) missingSingle = true;
            });
            if (missingSingle) {
                Swal.fire('Required', 'Please capture or select an image for all required image questions.', 'warning');
                return;
            }

            // 2b. Validate required multi-image slots
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

            // 3. Check for any images that background-upload failed (red border) and need retry
            var failedRows = [];
            $form.find('.bg-upload-failed').each(function() {
                var $row = $(this);
                var $wrapper = $row.closest('.multi-image-wrapper');
                failedRows.push({
                    row: $row,
                    qid: $wrapper.data('qid'),
                    url: $wrapper.data('upload-url'),
                    rowId: $wrapper.data('row-id')
                });
            });

            // 4. Blur active element so its auto-save fires before we proceed
            if (document.activeElement &&
                document.activeElement !== document.body &&
                !$(document.activeElement).is('[type="submit"],[type="button"],button,a')) {
                $(document.activeElement).blur();
            }

            // 5. Show SweetAlert loader + block navigation while submitting
            var _hasPending = _pendingUploads > 0;
            var _hasFailedRows = failedRows.length > 0;
            Swal.fire({
                title: (_hasPending || _hasFailedRows) ? 'Finishing image uploads...' : 'Saving...',
                html: '<p style="font-size:13px;color:#555;margin:0;">Please wait, do not go back.</p>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: function() { Swal.showLoading(); }
            });
            $('#approve').prop('disabled', true).text('Saving...');

            // Block back button and page close for the duration of submission
            history.pushState(null, '', location.href);
            _popHandler    = function() { history.pushState(null, '', location.href); };
            _unloadHandler = function(e) { e.preventDefault(); e.returnValue = ''; };
            window.addEventListener('popstate',    _popHandler);
            window.addEventListener('beforeunload', _unloadHandler);
            $(document).on('click.submitlock', 'a', function(e) { e.preventDefault(); });

            // 6. Wait for any still-in-progress background uploads AND in-flight auto-saves,
            //    then retry failed image rows, then save
            function waitForPendingThenSave() {
                // Wait for both: background image uploads AND in-flight auto-save AJAX calls
                if (_pendingUploads > 0 || _pendingAutoSaves > 0) {
                    setTimeout(waitForPendingThenSave, 300);
                    return;
                }
                // Retry any rows that failed during background upload
                if (failedRows.length === 0) {
                    doAjaxSubmit();
                    return;
                }
                var retryPromises = failedRows.map(function(u) {
                    var $row = u.row;
                    var $inp = $row.find('.multi-image-file-input[id^="cam_"]');
                    // Re-read the original file from the cam input (still holds the File object)
                    var file = null;
                    $row.find('.multi-image-file-input').each(function() {
                        if (this.files && this.files[0]) { file = this.files[0]; return false; }
                    });
                    if (!file) { return Promise.resolve({ ok: false }); }
                    return compressAndUpload(file, u.url, u.rowId).then(function(path) {
                        $row.find('.bg-upload-path').remove();
                        $('<input type="hidden" class="bg-upload-path">').attr('name', 'multi_images_' + u.qid + '[]').val(path).appendTo($row);
                        $row.removeClass('bg-upload-failed');
                        return { ok: true };
                    }).catch(function() {
                        return { ok: false };
                    });
                });
                Promise.allSettled(retryPromises).then(function(results) {
                    var stillFailed = results.filter(function(r) { return r.value && r.value.ok === false; }).length;
                    if (stillFailed === 0) {
                        doAjaxSubmit();
                    } else {
                        unlockNavigation();
                        Swal.close();
                        $('#approve').prop('disabled', false).text('Save');
                        Swal.fire({
                            icon: 'warning',
                            title: 'Image Upload Issue',
                            html: stillFailed + ' image(s) could not be uploaded due to poor network.<br><br>'
                                + '<b>Your other answers are safe.</b> Try saving again when connection improves.',
                            showCancelButton: true,
                            confirmButtonText: 'Save Without Failed Images',
                            cancelButtonText: 'Try Again'
                        }).then(function(result) {
                            if (result.isConfirmed) { doAjaxSubmit(); }
                            else { $('#approve').prop('disabled', false).text('Save'); }
                        });
                    }
                });
            }
            waitForPendingThenSave();

            function doAjaxSubmit(skipImages) {
                // Build a lean FormData: metadata + images only.
                // Text/select/radio answers were already persisted via auto-save on blur/change,
                // so we don't re-send them here (saves bandwidth on slow networks).
                var formData = new FormData();

                // Always include submission metadata
                // _token comes from the hidden @csrf input already in the form
                formData.append('_token',            $form.find('[name="_token"]').val());
                formData.append('row_id',            $form.find('[name="row_id"]').val());
                formData.append('activity_id',       $form.find('[name="activity_id"]').val());
                formData.append('activity_sequence', $form.find('[name="activity_sequence"]').val() || '0');
                formData.append('group_id',          $form.find('[name="group_id"]').val() || '');
                formData.append('latitude',          $('#latitude').val()  || '');
                formData.append('longitude',         $('#longitude').val() || '');

                if (!skipImages) {
                    // Single-image file inputs (Camera / Gallery)
                    $form.find('input[type="file"].single-img-input').each(function() {
                        if (this.files && this.files[0]) {
                            formData.append(this.name, this.files[0]);
                        }
                    });
                    // Multi-image paths (hidden inputs set by background uploader)
                    $form.find('input[type="hidden"][name^="multi_images_"]').each(function() {
                        formData.append(this.name, this.value);
                    });
                }

                Swal.update({ title: 'Saving\u2026', text: 'Please keep the app open.' });

                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    timeout: 0,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    success: function (res) {
                        unlockNavigation();
                        Swal.close();
                        $('#approve').prop('disabled', false).text('Save');
                        if (res.success && res.otp_required) {
                            showOtpOverlay(res.row_id, res.activity_id);
                        } else if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Saved!', text: res.message, timer: 1500, showConfirmButton: false })
                                .then(() => {
                                    if (res.redirectUrl) {
                                        window.location.href = res.redirectUrl;
                                    } else {
                                        location.reload();
                                    }
                                });
                        } else {
                            console.error('[submit failed]', res);
                            Swal.fire({ icon: 'error', title: 'Submission Failed', text: res.message || JSON.stringify(res) });
                        }
                    },
                    error: function (xhr) {
                        unlockNavigation();
                        Swal.close();
                        $('#approve').prop('disabled', false).text('Save');
                        var msg = 'HTTP ' + xhr.status + ': ';
                        if (xhr.status === 0) {
                            msg = 'Network error. Please check your connection.';
                        } else {
                            try {
                                var parsed = JSON.parse(xhr.responseText);
                                if (parsed.message) {
                                    msg += parsed.message;
                                } else if (parsed.errors) {
                                    msg += Object.values(parsed.errors).flat().join('; ');
                                } else {
                                    msg += xhr.responseText.substring(0, 300);
                                }
                            } catch(e) {
                                msg += xhr.responseText.substring(0, 300);
                            }
                        }
                        console.error('[submit error]', xhr.status, xhr.responseText);
                        Swal.fire({ icon: 'error', title: 'Error ('+xhr.status+')', text: msg });
                    }
                });
            }

        });

        // -- Navigation lock helpers -------------------------------------------
        var _popHandler    = null;
        var _unloadHandler = null;
        function unlockNavigation() {
            if (_popHandler)    window.removeEventListener('popstate',    _popHandler);
            if (_unloadHandler) window.removeEventListener('beforeunload', _unloadHandler);
            $(document).off('click.submitlock');
        }

        // -- Inline OTP Overlay (full-page blocking, no way to escape) ----------
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
                    <div style="font-size:36px;margin-bottom:8px;">&#240;&#376;&#8221;&#144;</div>
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
                        <div style="font-size:48px;margin-bottom:8px;">&#226;&#339;...</div>
                        <h6 style="font-weight:700;color:#28a745;">Verified!</h6>
                        <p style="font-size:13px;color:#555;">OTP verified successfully. Redirecting&#226;&#8364;&#166;</p>
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
                var $btn = $(this).prop('disabled', true).text('Sending&#226;&#8364;&#166;');
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
                var $btn = $(this).prop('disabled', true).text('Verifying&#226;&#8364;&#166;');
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

        // &#226;&#8221;&#8364;&#226;&#8221;&#8364; Add Instance Button &#226;&#8221;&#8364;&#226;&#8221;&#8364;
        var _deleteInstanceUrl = '{{ route('user.activity.delete_instance') }}';

        $(document).on('click', '#deleteInstanceBtn', function() {
            var instanceId = $(this).data('instance-id');
            Swal.fire({
                title: 'Remove Instance?',
                text: 'This will permanently delete this instance and all its answers.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Remove',
                confirmButtonColor: '#dc3545',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (!result.isConfirmed) return;
                var form = $('<form method="POST" action="' + _deleteInstanceUrl + '" style="display:none">' +
                    '<input name="_token" value="' + _csrfToken + '">' +
                    '<input name="instance_id" value="' + instanceId + '">' +
                    '<input name="row_id" value="' + _rowId + '">' +
                    '<input name="activity_id" value="' + _activityId + '">' +
                    '<input name="group_id" value="' + (_groupId || '') + '">' +
                    '</form>');
                $('body').append(form);
                form.submit();
            });
        });

        // FAB press feedback
        $(document).on('mousedown touchstart', '#addInstanceBtn', function() {
            $(this).css({ transform: 'scale(0.91)', boxShadow: '0 2px 6px rgba(0,0,0,0.18)' });
        }).on('mouseup mouseleave touchend', '#addInstanceBtn', function() {
            $(this).css({ transform: 'scale(1)', boxShadow: '0 4px 16px rgba(0,0,0,0.28)' });
        });

        $(document).on('click', '#addInstanceBtn', function() {
            if (!_allowRepeat) return;

            // If the current instance has never been saved at all, block immediately
            if ($(this).data('can-add') != '1') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Complete Current Instance',
                    text: 'Please save your answers for the current instance before adding a new one.',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#2563EB'
                });
                return;
            }

            // Even when some auto-saved answers exist, validate that ALL required fields
            // are filled before allowing a new instance to be created.
            if (!validateForm()) {
                // validateForm() already highlights missing fields and scrolls to the first one
                Swal.fire({
                    icon: 'warning',
                    title: 'Current Instance Incomplete',
                    text: 'Please fill all required fields before adding a new instance.',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#2563EB'
                });
                return;
            }
            // No label prompt &#226;&#8364;&#8221; backend auto-generates "Instance N"
            var form = $('<form method="POST" action="' + _addInstanceUrl + '" style="display:none">' +
                '<input name="_token" value="' + _csrfToken + '">' +
                '<input name="row_id" value="' + _rowId + '">' +
                '<input name="activity_id" value="' + _activityId + '">' +
                '<input name="group_id" value="' + (_groupId || '') + '">' +
                '</form>');
            $('body').append(form);
            form.submit();
        });

        // &#226;&#8221;&#8364;&#226;&#8221;&#8364; Submit (Close) Activity Button &#226;&#8221;&#8364;&#226;&#8221;&#8364;
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
                                .then(() => {
                                    if (res.redirectUrl) {
                                        window.location.href = res.redirectUrl;
                                    } else {
                                        window.location.href = '{{ route("user.projects") }}';
                                    }
                                });
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

            // ── Help text popup ───────────────────────────────────────────────────
            function decodeHtmlEntities(str) {
                var txt = document.createElement('textarea');
                txt.innerHTML = str;
                return txt.value;
            }

            $(document).on('click', '.help-text-icon', function(e) {
                e.stopPropagation();
                var msg = decodeHtmlEntities($(this).attr('data-help') || '');
                var $modal = $('#help-text-modal');
                if (!$modal.length) {
                    $modal = $([
                        '<div id="help-text-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;">',
                        '  <div style="background:#fff;border-radius:10px;width:82vw;max-width:340px;padding:16px;box-shadow:0 8px 32px rgba(0,0,0,.3);">',
                        '    <div id="help-text-modal-body" style="font-size:13px;color:#111827;line-height:1.6;white-space:pre-wrap;max-height:50vh;overflow-y:auto;"></div>',
                        '    <button id="help-text-modal-close" type="button" style="margin-top:12px;width:100%;padding:8px;background:#111827;border:none;border-radius:6px;color:#fff;font-size:13px;cursor:pointer;">Close</button>',
                        '  </div>',
                        '</div>'
                    ].join('')).appendTo('body');
                }
                $('#help-text-modal-body').text(msg);
                $modal.css('display', 'flex');
            });
            $(document).on('click', '#help-text-modal-close', function(e) {
                e.stopPropagation();
                $('#help-text-modal').fadeOut(200);
            });
            $(document).on('click', '#help-text-modal', function(e) {
                if (e.target === this) $(this).fadeOut(200);
            });
            // ── End help text popup ───────────────────────────────────────────────

            // ── Real-time validation events ────────────────────────────────────────
            // Clear error while user is actively correcting any text/number field
            $(document).on('input', '#activityForm input:not([type="hidden"]):not([type="file"]):not([type="radio"]):not([type="checkbox"])', function() {
                if ($(this).hasClass('is-invalid')) validateField($(this));
            });
            // Select/dropdown change — validate immediately (also clears error on correction)
            $(document).on('change', '#activityForm select', function() {
                validateField($(this));
            });
            // Select2 multi-select change
            $(document).on('select2:select select2:unselect', '#activityForm select', function() {
                validateField($(this));
            });
            // ── End real-time validation events ───────────────────────────────────

            // ── Auto-save: persist each answer on blur/change without waiting for full Save ──
            var _autoSaveUrl     = "{{ route('user.activity.autosave') }}";
            var _autoSaveCsrf    = "{{ csrf_token() }}";
            var _autoSaveRowId   = "{{ $row_data->id ?? '' }}";
            var _autoSaveActId   = "{{ $related_questions->first()->activity_id ?? '' }}";
            var _autoSaveActSeq  = "{{ $activity_sequence ?? 0 }}";
            var _autoSaveGroupId = "{{ $group_info->id ?? '' }}";

            // Status indicator — one shared element shown briefly after each save
            var $autoSaveStatus = $('<span id="autoSaveStatus" style="position:fixed;bottom:80px;left:12px;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;z-index:2000;display:none;pointer-events:none;"></span>').appendTo('body');

            function showAutoStatus(ok) {
                $autoSaveStatus
                    .text(ok ? '✓ Saved' : '! Save failed')
                    .css({
                        background: ok ? 'rgba(34,197,94,0.92)' : 'rgba(220,53,69,0.92)',
                        color: '#fff'
                    })
                    .stop(true).fadeIn(150).delay(1200).fadeOut(400);
            }

            function autoSaveField($el) {
                // Skip file inputs, hidden fields, and invalid fields
                if ($el.is('[type="file"],[type="hidden"]')) return;
                if (!isFieldVisible($el)) return;

                // Don't auto-save if the field currently has a validation error
                if ($el.hasClass('is-invalid')) return;

                var name  = $el.attr('name') || '';
                var val   = $el.val();
                if (val === null || val === undefined) return;

                // Extract question_id and optional pctx_parent_id from the input name
                // Name formats: "123", "123[]", "123_pctx_456", "123_pctx_456[]"
                var nameClean = name.replace(/\[\]$/, '');
                var pctxMatch = nameClean.match(/^(\d+)_pctx_(\d+)$/);
                var plainMatch = nameClean.match(/^(\d+)$/);
                var qid, pctxId;
                if (pctxMatch) {
                    qid    = pctxMatch[1];
                    pctxId = pctxMatch[2];
                } else if (plainMatch) {
                    qid    = plainMatch[1];
                    pctxId = '';
                } else {
                    return; // Not a question field
                }

                // For multi-select, collect all selected values
                if ($el.is('select[multiple]')) {
                    val = $el.val() || [];
                }

                // Skip empty — full Save handles required validation
                var isEmpty = Array.isArray(val) ? val.length === 0 : String(val).trim() === '';
                if (isEmpty) return;

                _pendingAutoSaves++;
                $.ajax({
                    url: _autoSaveUrl,
                    method: 'POST',
                    data: {
                        _token:            _autoSaveCsrf,
                        row_id:            _autoSaveRowId,
                        activity_id:       _autoSaveActId,
                        activity_sequence: _autoSaveActSeq,
                        group_id:          _autoSaveGroupId,
                        question_id:       qid,
                        pctx_parent_id:    pctxId,
                        value:             val,
                        latitude:          $('#latitude').val() || '',
                        longitude:         $('#longitude').val() || '',
                    },
                    success: function(res) {
                        _pendingAutoSaves = Math.max(0, _pendingAutoSaves - 1);
                        if (res.saved) showAutoStatus(true);
                    },
                    error: function() {
                        _pendingAutoSaves = Math.max(0, _pendingAutoSaves - 1);
                        showAutoStatus(false);
                    }
                });
            }

            // Blur on ALL inputs/selects/textareas — validate first, then autosave if valid
            // This covers: text, number, email, tel, date, textarea, select (single + multi)
            $(document).on('blur', '#activityForm input:not([type="hidden"]):not([type="file"]):not([type="radio"]):not([type="checkbox"]), #activityForm select, #activityForm textarea', function() {
                var $el = $(this);
                validateField($el);   // shows inline error and adds is-invalid if invalid
                autoSaveField($el);   // skips save if is-invalid was just set above
            });
            // Radio / checkbox change — no blur-based validation needed; autosave on selection
            $(document).on('change', '#activityForm input[type="radio"], #activityForm input[type="checkbox"]', function() {
                autoSaveField($(this));
            });
            // Select2 multi-select (fires after Select2's own change; blur already covers single-select)
            $(document).on('select2:select select2:unselect', '#activityForm select[multiple]', function() {
                var $el = $(this);
                validateField($el);
                autoSaveField($el);
            });
            // ── End auto-save ──────────────────────────────────────────────────────

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

                var isMobile = window.innerWidth <= 767 || /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

                function update() {
                    var formContainer = document.getElementById('formContainer');
                    var top = 0;
                    if (formContainer) {
                        top = Math.max(0, Math.round(
                            formContainer.getBoundingClientRect().top + window.scrollY
                        ));
                        // On mobile/Android WebView, always stretch edge-to-edge
                        // (card getBoundingClientRect can be unreliable in WebView)
                        if (isMobile) {
                            fixedHeader.style.left         = '0';
                            fixedHeader.style.right        = '0';
                            fixedHeader.style.width        = '100%';
                            fixedHeader.style.borderRadius = '0';
                        } else {
                            var card = formContainer.querySelector('.card');
                            if (card) {
                                var cr = card.getBoundingClientRect();
                                fixedHeader.style.left         = Math.round(cr.left) + 'px';
                                fixedHeader.style.right        = Math.round(window.innerWidth - cr.right) + 'px';
                                fixedHeader.style.width        = 'auto';
                                fixedHeader.style.borderRadius = '12px 12px 0 0';
                            }
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

                // Run immediately, after fonts/images settle, and on resize/scroll
                // Extra 1000ms retry covers slow Android WebView layout passes
                update();
                setTimeout(update, 150);
                setTimeout(update, 500);
                setTimeout(update, 1000);
                window.addEventListener('resize', update);
                window.addEventListener('scroll', update, { passive: true });
            })();

            initParentChildVisibility();

            // Check permission state BEFORE showing overlay to avoid flash
            (async function initLocation() {
                var _cachedLat = sessionStorage.getItem('loc_lat');
                var _cachedLng = sessionStorage.getItem('loc_lng');

                if (_cachedLat && _cachedLng) {
                    // Already have coords from this session — use them instantly, no overlay
                    $('#latitude').val(_cachedLat);
                    $('#longitude').val(_cachedLng);
                    enableFormInputs(_cachedLat, _cachedLng, true);
                    return;
                }

                // ── React Native WebView path ──────────────────────────────────
                // Skip the browser-permission overlay entirely. The RN app will
                // inject coordinates via window.setRNLocation() or postMessage.
                // Give it 3 s; if nothing arrives, fall through to WebView geolocation.
                if (isRNWebView) {
                    $('#locationOverlay').hide();
                    enableFormInputs(undefined, undefined, true);
                    // Notify RN app that the page is ready and needs location
                    if (window.ReactNativeWebView) {
                        window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'request_location' }));
                    }
                    // Fallback: after 3 s try WebView geolocation directly
                    setTimeout(function() {
                        if (!locationObtained && navigator.geolocation) {
                            navigator.geolocation.getCurrentPosition(function(pos) {
                                window.setRNLocation(pos.coords.latitude, pos.coords.longitude);
                            }, function() { /* silent fail — no overlay in WebView */ },
                            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
                        }
                    }, 3000);
                    return;
                }
                // ── Normal browser path ────────────────────────────────────────

                const permState = await checkPermissionState();

                if (permState === 'granted') {
                    // Permission granted — fetch silently, never show overlay
                    $('#locationOverlay').hide();
                    enableFormInputs(undefined, undefined, true);
                    navigator.geolocation.getCurrentPosition(function(pos) {
                        $('#latitude').val(pos.coords.latitude);
                        $('#longitude').val(pos.coords.longitude);
                        sessionStorage.setItem('loc_lat', pos.coords.latitude);
                        sessionStorage.setItem('loc_lng', pos.coords.longitude);
                        locationObtained = true;
                    }, function(err) {
                        console.warn('Background location failed:', err.message);
                    }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
                } else {
                    // Prompt or denied — show overlay and ask
                    disableFormInputs();
                    requestLocation();
                }
            })();

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
                hideChildBranch(parentId);
                showMatchingChildren($(this));
            });

            // __non_empty__ parents: any input/select/date change triggers show/hide
            // These are non-Dropdown/Yes-No questions that have __non_empty__ conditional children
            $(document).on('change input', '.non-empty-parent', function() {
                var parentId    = $(this).data('question-id');
                var currentVal  = String($(this).val() || '').trim();
                var parentRow   = $(this).closest('tr');
                var lastInserted = parentRow;

                $('tr.child-question[data-parent-id="' + parentId + '"]').each(function() {
                    var childParentValue = String($(this).data('parent-value') || '').trim();
                    if (childParentValue !== '__non_empty__') return; // handled by other listener

                    if (currentVal.length > 0) {
                        $(this).removeClass('d-none');
                        $(this).find('.recording-section').removeClass('d-none');
                        lastInserted.after($(this));
                        lastInserted = $(this);
                        $(this).find('input, select, textarea').each(function() {
                            $(this).prop('disabled', false); // ensure enabled after disableFormInputs
                            if ($(this).attr('data-required') === 'true') $(this).prop('required', true);
                        });
                        $(this).find('button').prop('disabled', false);
                    } else {
                        $(this).addClass('d-none');
                        $(this).find('.recording-section').addClass('d-none');
                        $(this).find('input, select, textarea').prop('required', false);
                    }
                });
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
                statusDiv.text("&#240;&#376;&#381;&#164; Recording...").removeClass().addClass("recording-status recording");
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
                        statusDiv.text("&#226;&#339;... Recording completed").removeClass("recording")
                            .addClass("stopped");
                        deleteBtn.prop("disabled", false);
                        stream.getTracks().forEach(track => track.stop());
                    };
                    audioMediaRecorder.start();
                }).catch(error => {
                    statusDiv.text("&#226;&#157;&#338; Microphone access denied or unavailable");
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
                statusDiv.text("&#240;&#376;&#381;&#165; Starting camera...").removeClass().addClass(
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
                            `&#240;&#376;&#381;&#165; Recording... (${fileExtension.toUpperCase()} format)`);
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
                                    statusDiv.text("&#226;&#157;&#338; Error preparing video data")
                                        .removeClass("recording").addClass("stopped");
                                    return;
                                } 
                                const rawBase64 = fullDataUrl.substring(markerIndex +
                                    base64Marker.length);
                                const cleanMime = videoBlob.type.split(';')[0];
                                videoInput.val(`data:${cleanMime};base64,${rawBase64}`);
                            };
                            statusDiv.text(
                                `&#226;&#339;... Recording completed (${fileExtension.toUpperCase()})`
                            ).removeClass("recording").addClass("stopped");
                            deleteBtn.prop("disabled", false);
                            stream.getTracks().forEach(track => track.stop());
                        };
                        setTimeout(() => {
                            if (videoMediaRecorder && videoMediaRecorder.state ===
                                "recording") {
                                videoMediaRecorder.stop();
                                statusDiv.text(
                                        "&#226;&#143;&#177;&#239;&#184;&#143; Recording stopped (time limit reached)")
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
                            statusDiv.text("&#226;&#157;&#338; Camera access denied or unavailable");
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

                // Hide ALL dependent sections before AJAX &#226;&#8364;&#8221; fixes previously-selected subject staying visible
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

            // &#226;&#8221;&#8364;&#226;&#8221;&#8364; Multi-image: preview on select, upload on Save &#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;

            // Upload one image immediately when the user picks it.
            // Shows a spinner on the thumbnail while uploading, turns green on success, red on failure.
            $(document).on('change', '.multi-image-file-input', function () {
                var file = this.files[0];
                if (!file) return;
                var $inp    = $(this);
                var $row    = $inp.closest('.multi-img-row');
                var $wrapper = $row.closest('.multi-image-wrapper');
                var qid     = $wrapper.data('qid');
                var uploadUrl = $wrapper.data('upload-url');
                var rowId   = $wrapper.data('row-id');
                var $box    = $row.find('.img-preview-box');

                // Show local preview immediately
                var reader = new FileReader();
                reader.onload = function(e) {
                    $box.html('<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;border-radius:4px;">');
                    $box.css('border', '2px solid #6B7280');
                };
                reader.readAsDataURL(file);

                // Show spinner overlay on the preview box
                $box.css('position', 'relative');
                var $spinner = $('<div style="position:absolute;inset:0;background:rgba(0,0,0,0.45);border-radius:4px;display:flex;align-items:center;justify-content:center;"><div style="width:18px;height:18px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin 0.7s linear infinite;"></div></div>');
                $box.append($spinner);

                _pendingUploads++;

                compressAndUpload(file, uploadUrl, rowId).then(function(path) {
                    _pendingUploads--;
                    $spinner.remove();
                    $box.css('border', '2px solid #28a745'); // green = uploaded
                    // Inject hidden input with server path — file input is now just a preview holder
                    $row.find('.bg-upload-path').remove();
                    $('<input type="hidden" class="bg-upload-path">').attr('name', 'multi_images_' + qid + '[]').val(path).appendTo($row);
                    // Clear the file input so Save-time code doesn't try to re-upload it
                    $inp[0].value = '';
                }).catch(function() {
                    _pendingUploads--;
                    $spinner.remove();
                    $box.css('border', '2px solid #DC2626'); // red = failed
                    // Mark row so Save-time knows this image needs re-upload or skip
                    $row.addClass('bg-upload-failed');
                });
            });


            // "+" persistent button -- max 10 images total (1 initial + 9 added).
            // Never hides; disables with label when limit reached.
            // Re-enables when a row is removed.
            var MAX_IMAGES = 10;

            // Find the + button for a given qid (it lives outside the wrapper in .add-image-row-btn-wrap)
            function getAddBtn(qid) {
                return $('[data-qid="' + qid + '"].add-image-row-btn');
            }

            function refreshAddBtn($btn, qid) {
                var $container = $('#inputs_' + qid);
                var count = $container.find('.multi-img-row').length;
                if (count >= MAX_IMAGES) {
                    $btn.prop('disabled', true).text('Max ' + MAX_IMAGES + ' images reached').css('opacity', '0.6');
                } else {
                    $btn.prop('disabled', false).text('+ add more').css('opacity', '1');
                }
                // Show first row's X when there are 2+ rows, hide when back to 1
                var $firstX = $container.find('.multi-img-row:first .remove-img-row-btn');
                if (count > 1) {
                    $firstX.css({'visibility':'visible','pointer-events':'auto'});
                } else {
                    $firstX.css({'visibility':'hidden','pointer-events':'none'});
                }
            }

            $(document).on('click', '.add-image-row-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var $btn = $(this);
                var qid  = $btn.data('qid');
                var $container = $('#inputs_' + qid);
                if ($container.find('.multi-img-row').length >= MAX_IMAGES) return;

                // Identical to Blade partial image_input_row -- small 40px thumbnail, one-line layout
                var uid = qid + '_r' + Date.now();
                var row = '<div class="multi-img-row mb-2" style="box-sizing:border-box;">'
                    + '<input type="file" id="cam_' + uid + '" class="multi-image-file-input d-none" accept="image/*" capture="environment">'
                    + '<input type="file" id="gal_' + uid + '" class="multi-image-file-input d-none" accept="image/*">'
                    + '<div style="display:flex;align-items:center;gap:8px;width:100%;box-sizing:border-box;">'
                    +   '<div id="prev_' + uid + '" style="width:40px;height:40px;min-width:40px;border-radius:6px;border:2px dashed #ccc;background:#f8f9fa center/cover no-repeat;flex-shrink:0;display:flex;align-items:center;justify-content:center;overflow:hidden;">'
                    +     '<span id="icon_' + uid + '" style="font-size:14px;color:#aaa;">&#128444;</span>'
                    +   '</div>'
                    +   '<div style="display:flex;gap:5px;flex:1;min-width:0;">'
                    +     '<button type="button" onmousedown="this.blur()" onclick="document.getElementById(\'cam_' + uid + '\').click()" style="flex:1;padding:8px 4px;border:1.5px solid #2563EB;border-radius:6px;background:#EFF6FF;color:#1D4ED8;font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap;overflow:hidden;min-width:0;">Camera</button>'
                    +     '<button type="button" onmousedown="this.blur()" onclick="document.getElementById(\'gal_' + uid + '\').click()" style="flex:1;padding:8px 4px;border:1.5px solid #059669;border-radius:6px;background:#F0FDF4;color:#065F46;font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap;overflow:hidden;min-width:0;">Gallery</button>'
                    +   '</div>'
                    +   '<button type="button" class="remove-img-row-btn" data-qid="' + qid + '" onmousedown="this.blur()" style="width:32px;min-width:32px;height:40px;flex-shrink:0;border:1.5px solid #DC2626;border-radius:6px;background:#FEF2F2;color:#DC2626;font-size:17px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;">&#215;</button>'
                    + '</div>'
                    + '</div>';
                $container.append(row);
                // Bind preview for the new row
                ['cam_','gal_'].forEach(function(pfx) {
                    var inp = document.getElementById(pfx + uid);
                    if (!inp) return;
                    inp.addEventListener('change', function() {
                        if (!this.files || !this.files[0]) return;
                        var r = new FileReader();
                        r.onload = function(e) {
                            var p = document.getElementById('prev_' + uid);
                            p.style.backgroundImage = 'url(' + e.target.result + ')';
                            var icon = document.getElementById('icon_' + uid);
                            if (icon) icon.style.display = 'none';
                        };
                        r.readAsDataURL(this.files[0]);
                    });
                });
                refreshAddBtn($btn, qid);
                $btn[0].blur(); // drop focus so :active/:focus style doesn't persist
            });

            $(document).on('click', '.remove-img-row-btn', function (e) {
                e.stopPropagation();
                var qid  = $(this).data('qid');
                $(this).closest('.multi-img-row').remove();
                $(this)[0].blur();
                refreshAddBtn(getAddBtn(qid), qid);
            });

            // Remove a previously-saved image row
            $(document).on('click', '.remove-saved-img-btn', function () {
                $(this).closest('.saved-img-row').remove();
            });

            // Multi-image submit is now handled by the unified form submit handler above.
            // &#226;&#8221;&#8364;&#226;&#8221;&#8364; End multi-image &#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;&#226;&#8221;&#8364;

        });
    </script>
@endsection


