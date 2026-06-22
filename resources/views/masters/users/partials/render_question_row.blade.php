{{--
    Recursive partial — renders one sub-question row and, if it is type "Outlet",
    recursively includes itself for its own sub-questions.

    Required variables passed via @include:
      $subQuestion  — Question model instance
      $depth        — nesting level (1 = first sub-level, 2 = second, …)
      $prefilled    — array [question_id => saved_answer] for sent-back pre-fill
--}}
@php
    $sqid     = $subQuestion->id;
    $sqSaved  = $prefilled[$sqid] ?? '';
    $indentPx = ($depth - 1) * 14;

    $depthColors = [
        1 => ['color' => '#4a6cf7', 'label' => 'Sub Question'],
        2 => ['color' => '#28a745', 'label' => 'Level 2'],
        3 => ['color' => '#fd7e14', 'label' => 'Level 3'],
    ];
    $dc = $depthColors[min($depth, 3)];
@endphp

<tr class="question-row sub-question-new-row {{ !empty($subQuestion->parent_question_id) ? 'child-question d-none' : '' }}"
    data-question-id="{{ $sqid }}"
    data-sub-depth="{{ $depth }}"
    data-parent-id="{{ $subQuestion->parent_question_id }}"
    data-parent-value="{{ $subQuestion->parent_value }}"
    style="background: transparent; border-top: 1px solid #dde3f7;">
    <td>
        <div style="padding-left:{{ $indentPx }}px;">

            <small style="color:{{ $dc['color'] }}; font-weight:600; font-size:11px; letter-spacing:.3px;">
                ↳ {{ $dc['label'] }}
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
                {{-- Regular question — render input by type --}}
                <div class="mt-2">
                    @switch($subQuestion->question_type)

                        @case('Free Text')
                            <input type="text" value="{{ $sqSaved }}" name="{{ $sqid }}"
                                class="form-control"
                                @if($subQuestion->answer_type) required data-required="true" @endif>
                        @break

                        @case('Yes / No')
                            <select class="form-select {{ $subQuestion->is_parent == 1 ? 'parent-question' : '' }}"
                                data-question-id="{{ $sqid }}"
                                name="{{ $sqid }}"
                                @if($subQuestion->answer_type) required data-required="true" @endif>
                                <option value="">Select ..</option>
                                <option value="Yes" @if($sqSaved==='Yes') selected @endif>Yes</option>
                                <option value="No"  @if($sqSaved==='No')  selected @endif>No</option>
                            </select>
                        @break

                        @case('Dropdown')
                            <select class="form-select {{ $subQuestion->is_parent == 1 ? 'parent-question' : '' }}"
                                data-question-id="{{ $sqid }}"
                                name="{{ $sqid }}"
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
                            <select class="form-select sub-multiselect" name="{{ $sqid }}[]" multiple
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
                            @if($sqSaved)
                                <div class="mb-2">
                                    <img src="{{ asset($sqSaved) }}" alt="Existing"
                                        style="max-width:100%;max-height:140px;border-radius:6px;border:1px solid #ddd;">
                                    <small class="text-muted d-block mt-1">Upload new to replace</small>
                                </div>
                                <input type="hidden" name="{{ $sqid }}_existing" value="{{ $sqSaved }}">
                            @endif
                            <input name="{{ $sqid }}" type="file" class="form-control"
                                @if($subQuestion->answer_type && !$sqSaved) required @endif>
                        @break

                        @case('File Upload')
                            @if($sqSaved)
                                <div class="mb-2">
                                    <a href="{{ asset($sqSaved) }}" target="_blank"
                                        class="btn btn-sm btn-outline-secondary">📎 View existing file</a>
                                    <small class="text-muted d-block mt-1">Upload new to replace</small>
                                </div>
                                <input type="hidden" name="{{ $sqid }}_existing" value="{{ $sqSaved }}">
                            @endif
                            <input type="file" name="{{ $sqid }}" class="form-control"
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
                            <input type="date" value="{{ $sqDate }}" name="{{ $sqid }}" class="form-control"
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
                            <input type="datetime-local" value="{{ $sqDT }}" name="{{ $sqid }}" class="form-control"
                                @if($subQuestion->answer_type) required data-required="true" @endif>
                        @break

                        @case('Location')
                            <input type="text" id="location_{{ $sqid }}" name="{{ $sqid }}"
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
                                <input type="hidden" name="{{ $sqid }}" class="audio-data"
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
                                <input type="hidden" name="{{ $sqid }}" class="video-data"
                                    data-id="{{ $sqid }}" value="{{ $sqSaved ?? '' }}">
                            </div>
                        @break

                        @default
                            <input type="text" value="{{ $sqSaved }}" class="form-control" name="{{ $sqid }}"
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
                'subQuestion' => $nestedLink->childQuestion,
                'depth'       => $depth + 1,
                'prefilled'   => $prefilled,
            ])
        @endif
    @endforeach
@endif
