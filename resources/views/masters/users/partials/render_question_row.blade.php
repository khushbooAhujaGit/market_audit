{{--
    Recursive partial &#8212; renders one sub-question row and, if it is type "Outlet",
    recursively includes itself for its own sub-questions.

    Required variables passed via @include:
      $subQuestion  &#8212; Question model instance
      $depth        &#8212; nesting level (1 = first sub-level, 2 = second, &#8230;)
      $prefilled    &#8212; array [question_id => saved_answer] for sent-back pre-fill
--}} 
@php
    $sqid            = $subQuestion->id;
    $parentCtxId     = $parentQuestionId ?? null;
    // Input name encodes the parent context so the same question under different parents
    // submits as separate fields: "{question_id}_pctx_{parent_id}"
    $inputName       = $parentCtxId ? "{$sqid}_pctx_{$parentCtxId}" : (string)$sqid;
    // Prefill: 2D lookup [question_id][parent_context_id] when a parent context exists
    if ($parentCtxId && isset($prefilled[$sqid]) && is_array($prefilled[$sqid])) {
        $sqSaved = $prefilled[$sqid][$parentCtxId] ?? '';
    } else {
        $sqSaved = (isset($prefilled[$sqid]) && !is_array($prefilled[$sqid])) ? $prefilled[$sqid] : '';
    }
    $indentPx = ($depth - 1) * 14;

    $depthColors = [
        1 => ['color' => '#4a6cf7', 'label' => 'Sub Question'],
        2 => ['color' => '#28a745', 'label' => 'Level 2'],
        3 => ['color' => '#fd7e14', 'label' => 'Level 3'],
    ];
    $dc = $depthColors[min($depth, 3)];
@endphp

@php
    // For new-style sub-questions: hide if a trigger_value is set (shown by JS when parent matches)
    $triggerValue = $subLinkTrigger ?? null; // passed from parent include
    $hiddenClass  = ($triggerValue !== null && $triggerValue !== '') ? 'd-none' : '';
@endphp
{{--
    This partial is ONLY called for sub-questions rendered beneath their Multi Response parent.
    Never apply 'child-question d-none' here &#8212; that class is for old-style conditional children
    in the main question loop. A question can have parent_question_id set (for conditional
    dependency on a Yes/No or Dropdown parent) AND still be a sub-question of a Multi Response
    parent. Hiding it here would prevent it from showing at all.
    Visibility inside this group is controlled only by $hiddenClass (trigger_value logic).
--}}
<tr class="question-row sub-question-new-row {{ $hiddenClass }}"
    data-question-id="{{ $sqid }}"
    data-sub-depth="{{ $depth }}"
    data-pctx-parent-id="{{ $parentCtxId ?? '' }}"
    data-trigger-value="{{ $triggerValue ?? '' }}"
    style="background: transparent; border-top: 1px solid #dde3f7;">
    <td>
        <div style="padding-left:{{ $indentPx }}px;">

            <small style="color:{{ $dc['color'] }}; font-weight:600; font-size:11px; letter-spacing:.3px;">
                &#8627; {{ $dc['label'] }}
            </small>

            <div class="fw-medium mt-1" style="font-size:14px;">
                {{ $subQuestion->question }}
                @if($subQuestion->answer_type)
                    <span class="txt-danger">*</span>
                @endif
            </div>

            @if($subQuestion->question_type === 'Multi Response')
                {{-- Outlet = section header, no input --}}

            @else
                {{-- Regular question &#8212; render input by type --}}
                <div class="mt-2">
                    @switch($subQuestion->question_type)

                        @case('Free Text')
                            <input type="text" value="{{ $sqSaved }}" name="{{ $inputName }}"
                                class="form-control"
                                @if($subQuestion->answer_type) required data-required="true" @endif>
                        @break

                        @case('Yes / No')
                            <select class="form-select {{ $subQuestion->is_parent == 1 ? 'parent-question' : '' }}"
                                data-question-id="{{ $sqid }}"
                                name="{{ $inputName }}"
                                @if($subQuestion->answer_type) required data-required="true" @endif>
                                <option value="">Select ..</option>
                                <option value="Yes" @if($sqSaved==='Yes') selected @endif>Yes</option>
                                <option value="No"  @if($sqSaved==='No')  selected @endif>No</option>
                            </select>
                        @break

                        @case('Dropdown')
                            <select class="form-select {{ $subQuestion->is_parent == 1 ? 'parent-question' : '' }}"
                                data-question-id="{{ $sqid }}"
                                name="{{ $inputName }}"
                                @if($subQuestion->answer_type) required data-required="true" @endif>
                                <option value="">Select</option>
                                @foreach($subQuestion->getOptions as $opt)
                                    <option value="{{ $opt->option }}"
                                        @if($sqSaved===$opt->option) selected @endif>
                                        {{ $opt->option }}
                                    </option>
                                @endforeach
                            </select>
                        @break

                        @case('Multi select')
                            @php $sqSavedMulti = $sqSaved ? explode(',', $sqSaved) : []; @endphp
                            <select class="form-select sub-multiselect" name="{{ $inputName }}[]" multiple
                                @if($subQuestion->answer_type) required data-required="true" @endif>
                                <option value="">Select Options</option>
                                @foreach($subQuestion->getOptions as $opt)
                                    <option value="{{ $opt->option }}"
                                        @if(in_array($opt->option,$sqSavedMulti)) selected @endif>
                                        {{ $opt->option }}
                                    </option>
                                @endforeach
                            </select>
                        @break

                        @case('Image')
                            @if($subQuestion->allow_multiple_images)
                                @php
                                    // Decode saved JSON array or wrap single path
                                    $sqImgPaths = [];
                                    if ($sqSaved) {
                                        $dec = json_decode($sqSaved, true);
                                        $sqImgPaths = is_array($dec) ? $dec : [$sqSaved];
                                    }
                                @endphp
                                {{-- Multi-image wrapper &#8212; use $inputName as unique key so each
                                     pctx instance (same question under different parents) is independent --}}
                                {{-- Wrapper: overflow:visible so nothing inside gets clipped --}}
                                <div class="multi-image-wrapper"
                                     data-qid="{{ $inputName }}"
                                     data-upload-url="{{ route('upload.activity.image.temp') }}"
                                     data-row-id="{{ $row_data->id ?? '' }}"
                                     style="overflow:visible;">

                                    {{-- Saved images --}}
                                    @foreach ($sqImgPaths as $sqImg)
                                        <div class="multi-img-row d-flex align-items-center gap-2 mb-2 saved-img-row">
                                            <img src="{{ asset($sqImg) }}"
                                                 style="width:56px;height:56px;object-fit:cover;border-radius:6px;border:1px solid #ccc;flex-shrink:0;">
                                            <span class="text-muted" style="font-size:12px;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ basename($sqImg) }}</span>
                                            <button type="button" class="btn btn-outline-danger btn-sm remove-saved-img-btn flex-shrink-0"
                                                    style="width:32px;height:32px;padding:0;font-size:16px;line-height:1;" title="Remove">&#215;</button>
                                            <input type="hidden" name="multi_images_{{ $inputName }}[]" value="{{ $sqImg }}">
                                        </div>
                                    @endforeach

                                    <div class="multi-img-inputs" id="inputs_{{ $inputName }}" style="overflow:visible;">
                                        @include('masters.users.partials.image_input_row', ['inputName' => $inputName, 'isFirst' => true])
                                    </div>

                                    <div class="multi-upload-progress d-none mt-1" id="progress_{{ $inputName }}">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                            <small class="text-muted">Uploading&#8230;</small>
                                        </div>
                                    </div>

                                    @if($subQuestion->answer_type && empty($sqImgPaths))
                                        <input type="hidden" class="multi-image-required" data-qid="{{ $inputName }}">
                                    @endif
                                </div>
                                {{-- Button is OUTSIDE the wrapper &#8212; cannot be clipped by any wrapper overflow --}}
                                <div class="add-image-row-btn-wrap" data-for-qid="{{ $inputName }}">
                                    <button type="button" class="add-image-row-btn" data-qid="{{ $inputName }}"
                                            onmousedown="this.blur()">
                                        + add more
                                    </button>
                                </div>
                            @else
                                {{-- Single image --}}
                                @if($sqSaved)
                                    <div class="mb-2">
                                        <img src="{{ asset($sqSaved) }}" alt="Existing"
                                            style="max-width:100%;max-height:140px;border-radius:6px;border:1px solid #ddd;">
                                        <small class="text-muted d-block mt-1">Upload new to replace</small>
                                    </div>
                                    <input type="hidden" name="{{ $inputName }}_existing" value="{{ $sqSaved }}">
                                @endif
                                @php $cu = $inputName . '_s' . uniqid(); @endphp
                                <input type="file" id="cam_{{ $cu }}" name="{{ $inputName }}" class="d-none" accept="image/*" capture="environment" @if($subQuestion->answer_type && !$sqSaved) required @endif>
                                <input type="file" id="gal_{{ $cu }}" name="{{ $inputName }}" class="d-none" accept="image/*" @if($subQuestion->answer_type && !$sqSaved) required @endif>
                                <div class="d-flex gap-2 mt-1">
                                    <button type="button" onclick="document.getElementById('cam_{{ $cu }}').click()" class="btn btn-sm" style="flex:1;border:1.5px solid #2563EB;background:#EFF6FF;color:#1D4ED8;font-weight:600;">&#128247; Camera</button>
                                    <button type="button" onclick="document.getElementById('gal_{{ $cu }}').click()" class="btn btn-sm" style="flex:1;border:1.5px solid #059669;background:#F0FDF4;color:#065F46;font-weight:600;">&#128247; Gallery</button>
                                </div>
                                <div id="fn_{{ $cu }}" style="font-size:11px;color:#6B7280;margin-top:4px;display:none;"></div>
                                <script>(function(){['cam_','gal_'].forEach(function(p){var el=document.getElementById(p+'{{ $cu }}');if(!el)return;el.addEventListener('change',function(){if(this.files&&this.files[0]){document.getElementById('fn_{{ $cu }}').textContent=this.files[0].name;document.getElementById('fn_{{ $cu }}').style.display='block';}});});})();</script>
                            @endif
                        @break

                        @case('File Upload')
                            @if($sqSaved)
                                <div class="mb-2">
                                    <a href="{{ asset($sqSaved) }}" target="_blank"
                                        class="btn btn-sm btn-outline-secondary">&#128206; View existing file</a>
                                    <small class="text-muted d-block mt-1">Upload new to replace</small>
                                </div>
                                <input type="hidden" name="{{ $inputName }}_existing" value="{{ $sqSaved }}">
                            @endif
                            <input type="file" name="{{ $inputName }}" class="form-control"
                                @if($subQuestion->answer_type && !$sqSaved) required @endif>
                        @break

                        @case('Date')
                            @php
                                $sqDate = '';
                                if($sqSaved) {
                                    try {
                                        $sqDate = preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $sqSaved)
                                            ? \Carbon\Carbon::createFromFormat('d/m/Y', $sqSaved)->format('Y-m-d')
                                            : \Carbon\Carbon::parse($sqSaved)->format('Y-m-d');
                                    } catch(\Exception $e) {}
                                }
                            @endphp
                            <input type="date" value="{{ $sqDate }}" name="{{ $inputName }}" class="form-control"
                                @if($subQuestion->answer_type) required data-required="true" @endif>
                        @break

                        @case('Date & Time')
                            @php
                                $sqDT = '';
                                if($sqSaved) {
                                    try { $sqDT = \Carbon\Carbon::parse($sqSaved)->format('Y-m-d\TH:i'); }
                                    catch(\Exception $e) { $sqDT = $sqSaved; }
                                }
                            @endphp
                            <input type="datetime-local" value="{{ $sqDT }}" name="{{ $inputName }}" class="form-control"
                                @if($subQuestion->answer_type) required data-required="true" @endif>
                        @break

                        @case('Location')
                            <input type="text" id="location_{{ $sqid }}" name="{{ $inputName }}"
                                class="form-control get_location" value="{{ $sqSaved }}"
                                @if($subQuestion->answer_type) required data-required="true" @endif
                                placeholder="Get location" readonly>
                            <button type="button" class="btn btn-info btn-sm mt-1"
                                id="get_location_{{ $sqid }}" data-id="{{ $sqid }}">
                                {{ $sqSaved ? 'Update Location' : 'Get Location' }}
                            </button>
                            <span class="p-2" id="location_status_{{ $sqid }}"></span>
                        @break

                        @case('Audio')
                            <div class="audio-recorder recording-section">
                                <h6>Record Audio</h6>
                                @if($sqSaved)
                                    <audio controls class="w-100 mb-2" style="max-width:280px;">
                                        <source src="{{ asset($sqSaved) }}">
                                    </audio>
                                @endif
                                <div class="btn-group mb-2">
                                    <button type="button" class="btn btn-sm btn-primary start-audio-recording" data-id="{{ $sqid }}">
                                        <i class="fa fa-microphone"></i> Start
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger stop-audio-recording" data-id="{{ $sqid }}" disabled>
                                        <i class="fa fa-stop"></i> Stop
                                    </button>
                                    <button type="button" class="btn btn-sm btn-warning delete-audio-recording" data-id="{{ $sqid }}" disabled>
                                        <i class="fa fa-trash"></i> Delete
                                    </button>
                                </div>
                                <div id="audio-recording-status-{{ $sqid }}" class="recording-status"></div>
                                <audio id="audio-player-{{ $sqid }}" controls class="d-none mt-2 w-100"></audio>
                                <input type="hidden" name="{{ $inputName }}" class="audio-data"
                                    data-id="{{ $sqid }}" value="{{ $sqSaved ?? '' }}">
                            </div>
                        @break

                        @case('Video')
                            <div class="video-recorder recording-section">
                                <h6>Record Video</h6>
                                @if($sqSaved)
                                    <video controls preload="metadata"
                                        style="max-width:100%;max-height:160px;border-radius:4px;" class="d-block mb-2">
                                        <source src="{{ asset($sqSaved) }}">
                                    </video>
                                    <input type="hidden" name="{{ $sqid }}_existing_path" value="{{ $sqSaved }}">
                                @endif
                                <div class="video-preview-container">
                                    <video id="video-preview-{{ $sqid }}" class="video-preview d-none" controls></video>
                                </div>
                                <div class="btn-group mb-2">
                                    <button type="button" class="btn btn-sm btn-primary start-video-recording" data-id="{{ $sqid }}">
                                        <i class="fa fa-video-camera"></i> Start
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger stop-video-recording" data-id="{{ $sqid }}" disabled>
                                        <i class="fa fa-stop"></i> Stop
                                    </button>
                                    <button type="button" class="btn btn-sm btn-warning delete-video-recording" data-id="{{ $sqid }}" disabled>
                                        <i class="fa fa-trash"></i> Delete
                                    </button>
                                </div>
                                <div id="video-recording-status-{{ $sqid }}" class="recording-status"></div>
                                <input type="hidden" name="{{ $inputName }}" class="video-data"
                                    data-id="{{ $sqid }}" value="{{ $sqSaved ?? '' }}">
                            </div>
                        @break

                        @default
                            <input type="text" value="{{ $sqSaved }}" class="form-control" name="{{ $inputName }}"
                                @if($subQuestion->answer_type) required data-required="true" @endif>
                    @endswitch
                </div>
            @endif

        </div>
    </td>
</tr>

{{--
    RECURSION: if this sub-question is Outlet type, render ITS own sub-questions
    at the next depth level (same partial, $depth + 1)
--}}
@if($subQuestion->question_type === 'Multi Response' && $subQuestion->subQuestions->count() > 0)
    @foreach($subQuestion->subQuestions as $nestedLink)
        @if($nestedLink->childQuestion)
            @include('masters.users.partials.render_question_row', [
                'subQuestion'      => $nestedLink->childQuestion,
                'depth'            => $depth + 1,
                'prefilled'        => $prefilled,
                'parentQuestionId' => $sqid,
                'row_data'         => $row_data ?? null,
            ])
        @endif
    @endforeach
@endif
