@extends('template.layouts.simple.master')
@section('style')
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
          rel="stylesheet">
    <style>
        /* Style the Select2 container */
        .select2-container {
            font-size: 14px;
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

        /* Recording styling */
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

        /* Location permission overlay */
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

        .form-blocked {
            opacity: 0.5;
            pointer-events: none;
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
                    <div class="card">
                        <div class="card-header">

                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-xl-6 col-md-6 d-none">
                                    <div class="table-card">
                                        <h5>Distributor Details</h5>
                                        <table class="table mb-0">
                                            <thead>
                                            </thead>
                                            <tbody>
                                            @foreach ($template_name_values as $row_val)
                                                <tr>
                                                    <td class="fw-medium">
                                                        {{ $row_val['template_head_name'] }}</td>
                                                    <td>{{ $row_val['value'] }}</td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                        <!--end table-->
                                    </div>
                                </div>
                                <div class="col-12 col-md-12 col-lg-6 mb-3">
                                    <div class="card">
                                        <div class="table-card table-responsive">
                                            <h5 class="mt-2 p-2">Activity Questions</h5>
                                            <form method="post" action="{{ route('user.rowId.activity.answers') }}"
                                                  enctype="multipart/form-data">
                                                @csrf
                                                @if (isset($group_info->id))
                                                    <input type="hidden" name="group_id" value="{{ $group_info->id }}">
                                                @endif
                                                <input type="hidden" name="row_id"
                                                       value="{{ $row_data->id??old('row_id') }}">
                                                <input type="hidden" name="activity_id"
                                                       value="{{ $related_questions->first()->activity_id }}">
                                                @if (isset($activity_group_info))
                                                    <input type="hidden" name="activity_group_id"
                                                           value="{{ $activity_group_info->id??old('activity_group_id') }}">
                                                @endif
                                                <table class="table mb-0">
                                                    <thead>
                                                    </thead>
                                                    <tbody>
                                                    @php  $selectArrIds = []; @endphp
                                                    @foreach ($related_questions as $related_question)
                                                        <tr class="mt-2">

                                                            <div class="col-sm-4 col-md-6 fw-medium mt-2">
                                                                {{ $related_question->question }} @if ($related_question->answer_type)
                                                                    <span class="txt-danger">*</span>
                                                                    @endif
                                                                    </td>
                                                            </div>
                                                            <div class="col-sm-6 col-md-6 mt-2">

                                                                @switch($related_question->question_type)
                                                                    @case('Image')
                                                                        <input name="{{ $related_question->id }}"
                                                                               type="file"
                                                                               class="form-control"
                                                                               @if ($related_question->answer_type) required="" @endif>
                                                                        @break

                                                                    @case('Free Text')
                                                                        <input type="text" value=""
                                                                               name="{{ $related_question->id }}"
                                                                               class="form-control"
                                                                               @if ($related_question->answer_type) required="" @endif>
                                                                        @break

                                                                    @case('Yes / No')
                                                                        <select class="form-select"
                                                                                @if ($related_question->answer_type) required=""
                                                                                @endif
                                                                                name="{{ $related_question->id }}">
                                                                            <option value="">Select ..</option>
                                                                            <option value="Yes">Yes</option>
                                                                            <option value="No">No</option>
                                                                        </select>
                                                                        @break

                                                                    @case('Subjective')
                                                                        <div class="d-flex flex-column gap-2">
                                                                            <select class="form-select subjective_ques"
                                                                                    @if ($related_question->answer_type) required=""
                                                                                    @endif
                                                                                    data-q_id="{{ $related_question->id }}"
                                                                                    name="{{ $related_question->id }}">
                                                                                <option value="">Select Subject ..
                                                                                </option>
                                                                                @foreach ($related_question->getSubjects as $questionSubjects)
                                                                                    <option
                                                                                        value="{{ $questionSubjects->subject }}">
                                                                                        {{ $questionSubjects->subject }}
                                                                                    </option>
                                                                                @endforeach
                                                                            </select>
                                                                            @if(!empty($related_question->getSubjects))
                                                                                @foreach($related_question->getSubjects as $subject)
                                                                                    @switch($subject->answer_type)
                                                                                        @case('Audio')
                                                                                            <!-- Audio Recording for Subjective -->
                                                                                            <div
                                                                                                class="audio-recorder recording-section d-none Audio{{ $subject->id }}">
                                                                                                <h6>Record Audio
                                                                                                    for {{ $subject->subject }}</h6>
                                                                                                <div
                                                                                                    class="btn-group mb-2">
                                                                                                    <button
                                                                                                        type="button"
                                                                                                        class="btn btn-sm btn-primary start-audio-recording"
                                                                                                        data-id="subjective_{{ $related_question->id }}_{{ $subject->id }}">
                                                                                                        <i class="fa fa-microphone"></i>
                                                                                                        Start Recording
                                                                                                    </button>
                                                                                                    <button
                                                                                                        type="button"
                                                                                                        class="btn btn-sm btn-danger stop-audio-recording"
                                                                                                        data-id="subjective_{{ $related_question->id }}_{{ $subject->id }}"
                                                                                                        disabled>
                                                                                                        <i class="fa fa-stop"></i>
                                                                                                        Stop
                                                                                                    </button>
                                                                                                    <button
                                                                                                        type="button"
                                                                                                        class="btn btn-sm btn-warning delete-audio-recording"
                                                                                                        data-id="subjective_{{ $related_question->id }}_{{ $subject->id }}"
                                                                                                        disabled>
                                                                                                        <i class="fa fa-trash"></i>
                                                                                                        Delete
                                                                                                    </button>
                                                                                                </div>
                                                                                                <div
                                                                                                    id="audio-recording-status-subjective_{{ $related_question->id }}_{{ $subject->id }}"
                                                                                                    class="recording-status"></div>
                                                                                                <audio
                                                                                                    id="audio-player-subjective_{{ $related_question->id }}_{{ $subject->id }}"
                                                                                                    controls
                                                                                                    class="d-none mt-2 w-100"></audio>
                                                                                                <input type="hidden"
                                                                                                       name="{{ $related_question->id }}Audio"
                                                                                                       class="audio-data"
                                                                                                       data-id="subjective_{{ $related_question->id }}_{{ $subject->id }}">
                                                                                            </div>
                                                                                            @break

                                                                                        @case('Video')
                                                                                            <!-- Video Recording for Subjective -->
                                                                                            <div
                                                                                                class="video-recorder recording-section d-none Video{{ $subject->id }}">
                                                                                                <h6>Record Video
                                                                                                    for {{ $subject->subject }}</h6>
                                                                                                <div
                                                                                                    class="video-preview-container">
                                                                                                    <video
                                                                                                        id="video-preview-subjective_{{ $related_question->id }}_{{ $subject->id }}"
                                                                                                        class="video-preview d-none"
                                                                                                        controls></video>
                                                                                                </div>
                                                                                                <div
                                                                                                    class="btn-group mb-2">
                                                                                                    <button
                                                                                                        type="button"
                                                                                                        class="btn btn-sm btn-primary start-video-recording"
                                                                                                        data-id="subjective_{{ $related_question->id }}_{{ $subject->id }}">
                                                                                                        <i class="fa fa-video-camera"></i>
                                                                                                        Start Recording
                                                                                                    </button>
                                                                                                    <button
                                                                                                        type="button"
                                                                                                        class="btn btn-sm btn-danger stop-video-recording"
                                                                                                        data-id="subjective_{{ $related_question->id }}_{{ $subject->id }}"
                                                                                                        disabled>
                                                                                                        <i class="fa fa-stop"></i>
                                                                                                        Stop
                                                                                                    </button>
                                                                                                    <button
                                                                                                        type="button"
                                                                                                        class="btn btn-sm btn-warning delete-video-recording"
                                                                                                        data-id="subjective_{{ $related_question->id }}_{{ $subject->id }}"
                                                                                                        disabled>
                                                                                                        <i class="fa fa-trash"></i>
                                                                                                        Delete
                                                                                                    </button>
                                                                                                </div>
                                                                                                <div
                                                                                                    id="video-recording-status-subjective_{{ $related_question->id }}_{{ $subject->id }}"
                                                                                                    class="recording-status"></div>
                                                                                                <input type="hidden"
                                                                                                       name="{{ $related_question->id }}Video"
                                                                                                       class="video-data"
                                                                                                       data-id="subjective_{{ $related_question->id }}_{{ $subject->id }}">
                                                                                            </div>
                                                                                            @break

                                                                                        @case('Yes / No')
                                                                                            <select
                                                                                                class="form-select d-none Yes/No{{ $subject->id }}"
                                                                                                name="{{ $related_question->id }}YesNo"
                                                                                                id="Yes/No{{ $related_question->id }}">
                                                                                                <option value="">
                                                                                                    Select
                                                                                                </option>
                                                                                                <option value="Yes">
                                                                                                    Yes
                                                                                                </option>
                                                                                                <option value="No">No
                                                                                                </option>
                                                                                            </select>
                                                                                            @break
                                                                                        @case('Date')
                                                                                            <input type="date" value=""
                                                                                                   name="{{ $related_question->id }}Date"
                                                                                                   id="Date{{ $related_question->id }}"
                                                                                                   class="form-control d-none Date{{ $subject->id }}"
                                                                                                   placeholder="select date">
                                                                                            @break
                                                                                        @case('Date & Time')
                                                                                            <input type="datetime-local"
                                                                                                   name="{{ $related_question->id }}Date&Time"
                                                                                                   id="Date&Time{{ $related_question->id }}"
                                                                                                   class="form-control d-none Date&Time{{ $subject->id }}"
                                                                                                   placeholder="select date-time">
                                                                                            @break
                                                                                        @case('Dropdown')
                                                                                            <select
                                                                                                class="form-select d-none Dropdown{{ $subject->id }}"
                                                                                                name="{{ $related_question->id }}Dropdown"
                                                                                                id="Dropdown{{ $related_question->id }}">
                                                                                                <option value="">
                                                                                                    Select
                                                                                                </option>
                                                                                                @foreach ($related_question->getOptions as $questionOption)
                                                                                                    <option
                                                                                                        value="{{ $questionOption->option }}">
                                                                                                        {{ $questionOption->option }}
                                                                                                    </option>
                                                                                                @endforeach
                                                                                            </select>
                                                                                            @break
                                                                                        @case('File Upload')
                                                                                            <span
                                                                                                class="badge text-dark h-25 d-none FileUpload{{ $subject->id }}">Select File</span>
                                                                                            <input type="file"
                                                                                                   name="{{ $related_question->id }}FileUpload"
                                                                                                   id="FileUpload{{ $related_question->id }}"
                                                                                                   class="form-control d-none FileUpload{{ $subject->id }}"
                                                                                                   placeholder="select file">
                                                                                            @break
                                                                                        @case('Free Text')
                                                                                            <input type="text" value=""
                                                                                                   name="{{ $related_question->id }}FreeText"
                                                                                                   id="FreeText{{ $related_question->id }}"
                                                                                                   class="form-control d-none FreeText{{ $subject->id }}"
                                                                                                   placeholder="add free text">
                                                                                            @break
                                                                                        @case('Image')
                                                                                            <span
                                                                                                class="badge text-dark h-25 d-none Image{{ $subject->id }}">Select Image</span>
                                                                                            <input
                                                                                                name="{{ $related_question->id }}Image"
                                                                                                id="Image{{ $related_question->id }}"
                                                                                                type="file"
                                                                                                class="form-control d-none Image{{ $subject->id }}"
                                                                                                placeholder="select image">
                                                                                            @break
                                                                                        @case('Location')
                                                                                            <input type="text"
                                                                                                   name="{{ $related_question->id }}Location"
                                                                                                   id="location_sub_{{ $related_question->id }}"
                                                                                                   class="form-control d-none get_subj_location Location{{ $subject->id }}"
                                                                                                   placeholder="select location"
                                                                                                   readonly>
                                                                                            <button type="button"
                                                                                                    class="btn btn-info d-none"
                                                                                                    id="get_subj_location_{{ $related_question->id }}"
                                                                                                    data-id="{{ $related_question->id }}">
                                                                                                Get
                                                                                                Location
                                                                                            </button>
                                                                                            <span class="p-3 d-none"
                                                                                                  id="location_subj_status_{{ $related_question->id }}"></span>
                                                                                            @break
                                                                                        @case('Multi select')
                                                                                            @php
                                                                                                $selectArrIds[] =
                                                                                                    'select' . $related_question->id;
                                                                                            @endphp
                                                                                            <select
                                                                                                class="form-select d-none Multiselect{{ $subject->id }}"
                                                                                                name="{{ $related_question->id }}Multiselect[]"
                                                                                                id="Multiselect{{ $related_question->id }}"
                                                                                                multiple>
                                                                                                <option value="">Select
                                                                                                    Option
                                                                                                </option>
                                                                                                @foreach ($related_question->getOptions as $questionOption)
                                                                                                    <option
                                                                                                        value="{{ $questionOption->option }}">
                                                                                                        {{ $questionOption->option }}
                                                                                                    </option>
                                                                                                @endforeach
                                                                                            </select>
                                                                                            @break
                                                                                        @default
                                                                                    @endswitch
                                                                                @endforeach
                                                                            @endif
                                                                        </div>
                                                                        @break
                                                                    @case('Dropdown')
                                                                        <select class="form-select"
                                                                                @if ($related_question->answer_type) required=""
                                                                                @endif
                                                                                name="{{ $related_question->id }}">
                                                                            <option value="">Select Dropdown</option>
                                                                            @foreach ($related_question->getOptions as $questionOption)
                                                                                <option
                                                                                    value="{{ $questionOption->option }}">
                                                                                    {{ $questionOption->option }}
                                                                                </option>
                                                                            @endforeach
                                                                        </select>
                                                                        @break

                                                                    @case('Multi select')
                                                                        @php
                                                                            $selectArrIds[] =
                                                                                'select' . $related_question->id;
                                                                        @endphp
                                                                        <select class="form-select"
                                                                                @if ($related_question->answer_type) required=""
                                                                                @endif
                                                                                name="{{ $related_question->id }}[]"
                                                                                id="select{{ $related_question->id }}"
                                                                                multiple>
                                                                            <option value="">Select Options</option>
                                                                            @foreach ($related_question->getOptions as $questionOption)
                                                                                <option
                                                                                    value="{{ $questionOption->option }}">
                                                                                    {{ $questionOption->option }}
                                                                                </option>
                                                                            @endforeach
                                                                        </select>
                                                                        @break

                                                                    @case('Date')
                                                                        <input type="date" value=""
                                                                               @if ($related_question->answer_type) required=""
                                                                               @endif
                                                                               name="{{ $related_question->id }}"
                                                                               placeholder="select date"
                                                                               class="form-control">
                                                                        @break

                                                                    @case('File Upload')
                                                                        <input type="file"
                                                                               @if ($related_question->answer_type) required=""
                                                                               @endif
                                                                               name="{{ $related_question->id }}"
                                                                               placeholder="select File"
                                                                               class="form-control">
                                                                        @break

                                                                    @case('Date & Time')
                                                                        <input type="datetime-local"
                                                                               @if ($related_question->answer_type) required=""
                                                                               @endif
                                                                               name="{{ $related_question->id }}"
                                                                               value=""
                                                                               placeholder="select date and time"
                                                                               class="form-control">
                                                                        @break

                                                                    @case('Location')
                                                                        <input type="text"
                                                                               id="location_{{ $related_question->id }}"
                                                                               name="{{ $related_question->id }}"
                                                                               class="form-control get_location"
                                                                               @if ($related_question->answer_type) required=""
                                                                               @endif
                                                                               placeholder="get location"
                                                                               readonly>
                                                                        <button type="button" class="btn btn-info"
                                                                                id="get_location_{{ $related_question->id }}"
                                                                                data-id="{{ $related_question->id }}">
                                                                            Get
                                                                            Location
                                                                        </button>
                                                                        <span class="p-3"
                                                                              id="location_status_{{ $related_question->id }}"></span>
                                                                        @break

                                                                    @case('Audio')
                                                                        <div class="audio-recorder recording-section">
                                                                            <h6>Record Audio</h6>
                                                                            <div class="btn-group mb-2">
                                                                                <button type="button"
                                                                                        class="btn btn-sm btn-primary start-audio-recording"
                                                                                        data-id="{{ $related_question->id }}">
                                                                                    <i class="fa fa-microphone"></i>
                                                                                    Start Recording
                                                                                </button>
                                                                                <button type="button"
                                                                                        class="btn btn-sm btn-danger stop-audio-recording"
                                                                                        data-id="{{ $related_question->id }}"
                                                                                        disabled>
                                                                                    <i class="fa fa-stop"></i> Stop
                                                                                </button>
                                                                                <button type="button"
                                                                                        class="btn btn-sm btn-warning delete-audio-recording"
                                                                                        data-id="{{ $related_question->id }}"
                                                                                        disabled>
                                                                                    <i class="fa fa-trash"></i> Delete
                                                                                </button>
                                                                            </div>
                                                                            <div
                                                                                id="audio-recording-status-{{ $related_question->id }}"
                                                                                class="recording-status"></div>
                                                                            <audio
                                                                                id="audio-player-{{ $related_question->id }}"
                                                                                controls
                                                                                class="d-none mt-2 w-100"></audio>
                                                                            <input type="hidden"
                                                                                   name="{{ $related_question->id }}"
                                                                                   class="audio-data"
                                                                                   data-id="{{ $related_question->id }}"
                                                                                   placeholder="add audio">
                                                                        </div>
                                                                        @break

                                                                    @case('Video')
                                                                        <div class="video-recorder recording-section">
                                                                            <h6>Record Video</h6>
                                                                            <div class="video-preview-container">
                                                                                <video
                                                                                    id="video-preview-{{ $related_question->id }}"
                                                                                    class="video-preview d-none"
                                                                                    controls></video>
                                                                            </div>
                                                                            <div class="btn-group mb-2">
                                                                                <button type="button"
                                                                                        class="btn btn-sm btn-primary start-video-recording"
                                                                                        data-id="{{ $related_question->id }}">
                                                                                    <i class="fa fa-video-camera"></i>
                                                                                    Start Recording
                                                                                </button>
                                                                                <button type="button"
                                                                                        class="btn btn-sm btn-danger stop-video-recording"
                                                                                        data-id="{{ $related_question->id }}"
                                                                                        disabled>
                                                                                    <i class="fa fa-stop"></i> Stop
                                                                                </button>
                                                                                <button type="button"
                                                                                        class="btn btn-sm btn-warning delete-video-recording"
                                                                                        data-id="{{ $related_question->id }}"
                                                                                        disabled>
                                                                                    <i class="fa fa-trash"></i> Delete
                                                                                </button>
                                                                            </div>
                                                                            <div
                                                                                id="video-recording-status-{{ $related_question->id }}"
                                                                                class="recording-status"></div>
                                                                            <input type="hidden"
                                                                                   name="{{ $related_question->id }}"
                                                                                   class="video-data"
                                                                                   data-id="{{ $related_question->id }}">
                                                                        </div>
                                                                        @break

                                                                    @default
                                                                        <input type="text" value=""
                                                                               @if ($related_question->answer_type) required=""
                                                                               @endif
                                                                               class="form-control"
                                                                               name="{{ $related_question->id }}">
                                                                        @endswitch

                                                                        </td>
                                                            </div>
                                                        </tr>
                                                    @endforeach
                                                    </tbody>
                                                </table>
                                                <input type="hidden" class="form-control" name="latitude" id="latitude"
                                                       value="">
                                                <input type="hidden" class="form-control" name="longitude"
                                                       id="longitude" value="">
                                                <button type="submit" id="approve" name="save"
                                                        class="btn btn-primary float-end">
                                                    Submit
                                                </button>
                                                <button type="button" id="reject" name="reject"
                                                        class="btn btn-danger float-end d-none">Reject
                                                </button>
                                            </form>
                                            <!--end table-->
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
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Global variables
        let audioMediaRecorder = null;
        let videoMediaRecorder = null;
        let audioChunks = {};
        let videoChunks = {};
        let audioStream = null;
        let videoStream = null;
        let locationObtained = false;
        let permissionDenied = false;

        // Function to enable all form inputs
        function enableFormInputs() {
            console.log('Enabling form inputs...');

            // Enable all form inputs
            $('#activityForm').find('input:not([type="hidden"])').prop('disabled', false);
            $('#activityForm').find('select').prop('disabled', false);
            $('#activityForm').find('button:not([type="submit"])').prop('disabled', false);
            $('#activityForm').find('.start-audio-recording, .start-video-recording').prop('disabled', false);
            $('#activityForm').find('#approve, #reject').prop('disabled', false);

            // Initialize Select2 for multi-selects
            const multiSelects = @json($selectArrIds);
            $.each(multiSelects, function (index, select_id) {
                $(`#${select_id}`).select2({
                    theme: "bootstrap-5",
                    placeholder: "Select options",
                    allowClear: true,
                    closeOnSelect: false,
                    width: '100%',
                });
            });

            // Hide the location overlay
            $('#locationOverlay').fadeOut(300);

            // Show success message
            Swal.fire({
                position: "top-center",
                icon: "success",
                title: "Location access granted!",
                text: "You can now fill the form.",
                showConfirmButton: false,
                timer: 1500
            });

            locationObtained = true;
            permissionDenied = false;
        }

        // Function to disable all form inputs and show overlay
        function disableFormInputs(message = "Please allow location access to continue filling the form. This is required for all submissions.", showGuide = false) {
            console.log('Disabling form inputs...');

            // Disable all form inputs
            $('#activityForm').find('input:not([type="hidden"])').prop('disabled', true);
            $('#activityForm').find('select').prop('disabled', true);
            $('#activityForm').find('button:not([type="submit"])').prop('disabled', true);
            $('#activityForm').find('.start-audio-recording, .start-video-recording').prop('disabled', true);
            $('#activityForm').find('#approve, #reject').prop('disabled', true);

            // Update overlay message
            $('#locationMessage').text(message);
            $('#locationSpinner').toggleClass('d-none', showGuide || permissionDenied);

            // Show/hide permission guide
            if (showGuide || permissionDenied) {
                $('#permissionDeniedGuide').removeClass('d-none');
            } else {
                $('#permissionDeniedGuide').addClass('d-none');
            }

            // Show the location overlay
            $('#locationOverlay').fadeIn(300);

            locationObtained = false;
        }

        // Function to check permission state
        function checkPermissionState() {
            return new Promise((resolve) => {
                if (!navigator.permissions) {
                    // Fallback for browsers that don't support permissions API
                    navigator.geolocation.getCurrentPosition(
                        () => resolve('granted'),
                        (error) => {
                            if (error.code === error.PERMISSION_DENIED) {
                                resolve('denied');
                            } else {
                                resolve('prompt');
                            }
                        },
                        {enableHighAccuracy: true, timeout: 100}
                    );
                    return;
                }

                navigator.permissions.query({name: 'geolocation'})
                    .then((result) => {
                        resolve(result.state);
                    })
                    .catch(() => {
                        resolve('prompt');
                    });
            });
        }

        // Function to request location
        async function requestLocation() {
            const permissionState = await checkPermissionState();

            if (permissionState === 'granted') {
                // Permission already granted
                $('#locationMessage').text('Getting your location...');
                $('#locationSpinner').removeClass('d-none');
                $('#permissionDeniedGuide').addClass('d-none');

                navigator.geolocation.getCurrentPosition(
                    function (position) {
                        var latitude = position.coords.latitude;
                        var longitude = position.coords.longitude;

                        console.log('Location obtained:', latitude, longitude);
                        $('#longitude').val(longitude);
                        $('#latitude').val(latitude);

                        enableFormInputs();
                    },
                    function (error) {
                        console.error('Geolocation error:', error);
                        disableFormInputs('Failed to get location. Please try again.', false);
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            } else if (permissionState === 'denied') {
                // Permission previously denied
                permissionDenied = true;
                disableFormInputs(
                    'Location permission was previously denied. Please enable location access in your browser settings to continue.',
                    true
                );

                // Show detailed instructions
                Swal.fire({
                    icon: 'warning',
                    title: 'Location Permission Denied',
                    html: `
                        <p>You have previously denied location access for this site.</p>
                        <p><strong>To enable location:</strong></p>
                        <ol style="text-align: left; margin-left: 20px;">
                            <li>Click the lock icon (🔒) in your browser's address bar</li>
                            <li>Find "Location" in the permissions list</li>
                            <li>Change from "Block" to "Allow"</li>
                            <li>Click "Retry Location Access" below</li>
                        </ol>
                    `,
                    confirmButtonText: 'Got it',
                    showCancelButton: false,
                    allowOutsideClick: false
                });
            } else {
                // Permission not set yet (prompt state)
                $('#locationMessage').text('Requesting location access...');
                $('#locationSpinner').removeClass('d-none');
                $('#permissionDeniedGuide').addClass('d-none');

                navigator.geolocation.getCurrentPosition(
                    function (position) {
                        var latitude = position.coords.latitude;
                        var longitude = position.coords.longitude;

                        console.log('Location obtained:', latitude, longitude);
                        $('#longitude').val(longitude);
                        $('#latitude').val(latitude);

                        enableFormInputs();
                    },
                    function (error) {
                        console.error('Geolocation error:', error);
                        if (error.code === error.PERMISSION_DENIED) {
                            permissionDenied = true;
                            disableFormInputs(
                                'Location permission denied. Please allow location access to continue.',
                                true
                            );

                            Swal.fire({
                                icon: 'warning',
                                title: 'Permission Denied',
                                text: 'You denied location access. Please enable it to continue filling the form.',
                                confirmButtonText: 'Retry',
                                showCancelButton: true,
                                cancelButtonText: 'Cancel',
                                allowOutsideClick: false
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    requestLocation();
                                }
                            });
                        } else {
                            disableFormInputs('Failed to get location. Please try again.', false);
                        }
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            }
        }

        // Form submit validation
        $('#activityForm').on('submit', function (e) {
            if (!locationObtained) {
                e.preventDefault();
                Swal.fire({
                    icon: "error",
                    title: "Location Required",
                    text: "Please allow location access before submitting.",
                    confirmButtonText: "Get Location"
                }).then((result) => {
                    if (result.isConfirmed) {
                        requestLocation();
                    }
                });
                return false;
            }

            if (!$('#latitude').val() || !$('#longitude').val()) {
                e.preventDefault();
                Swal.fire({
                    icon: "error",
                    title: "Location Not Set",
                    text: "Please allow location access before submitting."
                });
                requestLocation();
                return false;
            }
        });

        $(document).ready(function () {
            // Initially disable all form inputs and show overlay
            disableFormInputs();

            // Request location on page load
            requestLocation();

            // Retry button click
            $('#retryLocationBtn').on('click', function () {
                requestLocation();
            });

            @if (session()->has('message'))
            // Only show success message if location is obtained
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

            // ==================== AUDIO RECORDING ====================
            // Start Audio Recording (only when location is obtained)
            $(".start-audio-recording").on("click", function () {
                if (!locationObtained) {
                    showLocationRequiredAlert();
                    return;
                }

                // Rest of your audio recording code...
                let id = $(this).data("id");
                let statusDiv = $("#audio-recording-status-" + id);
                let audioPlayer = $("#audio-player-" + id);
                let audioInput = $(".audio-data[data-id='" + id + "']");
                let stopBtn = $(".stop-audio-recording[data-id='" + id + "']");
                let deleteBtn = $(".delete-audio-recording[data-id='" + id + "']");

                // Update UI
                statusDiv.text("🎤 Recording...").removeClass().addClass("recording-status recording");
                $(this).prop("disabled", true);
                stopBtn.prop("disabled", false);

                // Request microphone access
                navigator.mediaDevices.getUserMedia({
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        sampleRate: 44100
                    }
                })
                    .then(stream => {
                        audioStream = stream;
                        audioMediaRecorder = new MediaRecorder(stream, {
                            mimeType: 'audio/webm;codecs=opus'
                        });
                        audioChunks[id] = [];

                        audioMediaRecorder.ondataavailable = event => {
                            if (event.data.size > 0) {
                                audioChunks[id].push(event.data);
                            }
                        };

                        audioMediaRecorder.onstop = () => {
                            const audioBlob = new Blob(audioChunks[id], {
                                type: 'audio/webm;codecs=opus'
                            });
                            const audioUrl = URL.createObjectURL(audioBlob);

                            // Show audio player
                            audioPlayer.attr("src", audioUrl).removeClass("d-none");

                            // Convert to base64 and store
                            const reader = new FileReader();
                            reader.readAsDataURL(audioBlob);
                            reader.onloadend = function () {
                                audioInput.val(reader.result);
                            };

                            // Update status
                            statusDiv.text("✅ Recording completed").removeClass("recording").addClass("stopped");
                            deleteBtn.prop("disabled", false);

                            // Stop all tracks
                            stream.getTracks().forEach(track => track.stop());
                        };

                        audioMediaRecorder.start();
                    })
                    .catch(error => {
                        console.error("Error accessing microphone:", error);
                        statusDiv.text("❌ Microphone access denied or unavailable");
                        $(this).prop("disabled", false);
                        stopBtn.prop("disabled", true);

                        if (error.name === 'NotAllowedError') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Permission Denied',
                                text: 'Please allow microphone access to record audio.'
                            });
                        }
                    });
            });

            // Stop Audio Recording
            $(".stop-audio-recording").on("click", function () {
                let id = $(this).data("id");
                let startBtn = $(".start-audio-recording[data-id='" + id + "']");
                let deleteBtn = $(".delete-audio-recording[data-id='" + id + "']");

                if (audioMediaRecorder && audioMediaRecorder.state !== "inactive") {
                    audioMediaRecorder.stop();
                    startBtn.prop("disabled", false);
                    $(this).prop("disabled", true);
                    deleteBtn.prop("disabled", false);

                    // Stop microphone stream
                    if (audioStream) {
                        audioStream.getTracks().forEach(track => track.stop());
                        audioStream = null;
                    }
                }
            });

            // Delete Audio Recording
            $(".delete-audio-recording").on("click", function () {
                let id = $(this).data("id");
                let audioPlayer = $("#audio-player-" + id);
                let audioInput = $(".audio-data[data-id='" + id + "']");
                let startBtn = $(".start-audio-recording[data-id='" + id + "']");
                let stopBtn = $(".stop-audio-recording[data-id='" + id + "']");
                let statusDiv = $("#audio-recording-status-" + id);

                audioPlayer.addClass("d-none").attr("src", "");
                audioInput.val("");
                startBtn.prop("disabled", false);
                stopBtn.prop("disabled", true);
                $(this).prop("disabled", true);
                statusDiv.text("").removeClass();
            });

            // ==================== VIDEO RECORDING ====================
            // Start Video Recording (only when location is obtained)
            $(".start-video-recording").on("click", function () {
                if (!locationObtained) {
                    showLocationRequiredAlert();
                    return;
                }

                // Your video recording code...
                let id = $(this).data("id");
                let statusDiv = $("#video-recording-status-" + id);
                let videoPreview = $("#video-preview-" + id);
                let videoInput = $(".video-data[data-id='" + id + "']");
                let stopBtn = $(".stop-video-recording[data-id='" + id + "']");
                let deleteBtn = $(".delete-video-recording[data-id='" + id + "']");

                // Update UI
                statusDiv.text("🎥 Starting camera...").removeClass().addClass("recording-status recording");
                $(this).prop("disabled", true);
                stopBtn.prop("disabled", false);

                // Function to check if browser supports MP4 recording
                function checkMP4Support() {
                    const testMimeTypes = [
                        'video/mp4;codecs=h264,aac',
                        'video/mp4;codecs=avc1.42E01E,mp4a.40.2',
                        'video/mp4',
                        'video/webm;codecs=h264,opus',
                        'video/webm;codecs=vp8,opus',
                        'video/webm'
                    ];

                    for (let mimeType of testMimeTypes) {
                        if (MediaRecorder.isTypeSupported(mimeType)) {
                            console.log(`Browser supports: ${mimeType}`);
                            return mimeType;
                        }
                    }
                    return null;
                }

                const supportedMimeType = checkMP4Support();
                console.log('Selected MIME type for recording:', supportedMimeType);

                let fileExtension = 'mp4';
                if (supportedMimeType && supportedMimeType.includes('webm')) {
                    fileExtension = 'webm';
                    statusDiv.text("⚠️ MP4 not supported, using WebM format");
                }

                const constraints = {
                    video: {
                        width: {ideal: 1280},
                        height: {ideal: 720},
                        facingMode: {exact: "environment"}
                    },
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        sampleRate: 44100
                    }
                };

                const tryCamera = (constraints, attempt = 1) => {
                    navigator.mediaDevices.getUserMedia(constraints)
                        .then(stream => {
                            videoStream = stream;

                            const recorderOptions = supportedMimeType ?
                                {mimeType: supportedMimeType, videoBitsPerSecond: 2500000} :
                                {videoBitsPerSecond: 2500000};

                            videoMediaRecorder = new MediaRecorder(stream, recorderOptions);
                            videoChunks[id] = [];

                            videoPreview.removeClass("d-none");
                            videoPreview[0].srcObject = stream;
                            videoPreview[0].play();

                            statusDiv.text(`🎥 Recording... (${fileExtension.toUpperCase()} format)`);

                            videoMediaRecorder.ondataavailable = event => {
                                if (event.data.size > 0) {
                                    videoChunks[id].push(event.data);
                                }
                            };

                            videoMediaRecorder.onstop = () => {
                                const videoBlob = new Blob(videoChunks[id], {
                                    type: supportedMimeType || 'video/webm'
                                });
                                const videoUrl = URL.createObjectURL(videoBlob);

                                videoPreview[0].srcObject = null;
                                videoPreview.attr("src", videoUrl);

                                const reader = new FileReader();
                                reader.readAsDataURL(videoBlob);
                                reader.onloadend = function () {
                                    const base64Data = reader.result;
                                    const dataWithExt = `data:video/${fileExtension};base64,${base64Data.split(',')[1]}`;
                                    videoInput.val(dataWithExt);
                                };

                                statusDiv.text(`✅ Recording completed (${fileExtension.toUpperCase()})`).removeClass("recording").addClass("stopped");
                                deleteBtn.prop("disabled", false);

                                console.log('Video recorded:', {
                                    format: fileExtension,
                                    size: videoBlob.size,
                                    type: videoBlob.type,
                                    duration: videoChunks[id].length
                                });

                                stream.getTracks().forEach(track => track.stop());
                            };

                            setTimeout(() => {
                                if (videoMediaRecorder && videoMediaRecorder.state === "recording") {
                                    videoMediaRecorder.stop();
                                    statusDiv.text("⏱️ Recording stopped (time limit reached)").removeClass("recording").addClass("stopped");
                                }
                            }, 300000);

                            videoMediaRecorder.start();
                        })
                        .catch(error => {
                            console.error("Camera error (attempt " + attempt + "):", error);

                            if (attempt === 1) {
                                statusDiv.text("🎥 Trying any available camera...");
                                const fallbackConstraints = {
                                    video: {
                                        width: {ideal: 1280},
                                        height: {ideal: 720}
                                    },
                                    audio: {
                                        echoCancellation: true,
                                        noiseSuppression: true,
                                        sampleRate: 44100
                                    }
                                };
                                tryCamera(fallbackConstraints, 2);
                            } else if (attempt === 2) {
                                statusDiv.text("🎥 Trying front camera...");
                                const frontCameraConstraints = {
                                    video: {
                                        width: {ideal: 1280},
                                        height: {ideal: 720},
                                        facingMode: "user"
                                    },
                                    audio: {
                                        echoCancellation: true,
                                        noiseSuppression: true,
                                        sampleRate: 44100
                                    }
                                };
                                tryCamera(frontCameraConstraints, 3);
                            } else {
                                statusDiv.text("❌ Camera access denied or unavailable");
                                $(this).prop("disabled", false);
                                stopBtn.prop("disabled", true);

                                if (error.name === 'NotAllowedError') {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Permission Denied',
                                        text: 'Please allow camera and microphone access to record video.'
                                    });
                                } else if (error.name === 'NotFoundError') {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Camera Not Found',
                                        text: 'No camera found on this device.'
                                    });
                                } else if (error.name === 'OverconstrainedError') {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Camera Configuration Error',
                                        text: 'The requested camera configuration is not available.'
                                    });
                                }
                            }
                        });
                };

                tryCamera(constraints, 1);
            });

            // Stop Video Recording
            $(".stop-video-recording").on("click", function () {
                let id = $(this).data("id");
                let startBtn = $(".start-video-recording[data-id='" + id + "']");
                let deleteBtn = $(".delete-video-recording[data-id='" + id + "']");
                let videoPreview = $("#video-preview-" + id);

                if (videoMediaRecorder && videoMediaRecorder.state !== "inactive") {
                    videoMediaRecorder.stop();
                    startBtn.prop("disabled", false);
                    $(this).prop("disabled", true);
                    deleteBtn.prop("disabled", false);

                    if (videoStream) {
                        videoStream.getTracks().forEach(track => track.stop());
                        videoStream = null;
                    }

                    if (videoPreview[0].srcObject) {
                        videoPreview[0].srcObject.getTracks().forEach(track => track.stop());
                    }
                }
            });

            // Delete Video Recording
            $(".delete-video-recording").on("click", function () {
                let id = $(this).data("id");
                let videoPreview = $("#video-preview-" + id);
                let videoInput = $(".video-data[data-id='" + id + "']");
                let startBtn = $(".start-video-recording[data-id='" + id + "']");
                let stopBtn = $(".stop-video-recording[data-id='" + id + "']");
                let statusDiv = $("#video-recording-status-" + id);

                videoPreview.addClass("d-none").attr("src", "");
                videoInput.val("");
                startBtn.prop("disabled", false);
                stopBtn.prop("disabled", true);
                $(this).prop("disabled", true);
                statusDiv.text("").removeClass();
            });

            // ==================== LOCATION FUNCTIONS ====================
            function showLocationRequiredAlert() {
                Swal.fire({
                    icon: "warning",
                    title: "Location Required",
                    text: "Please allow location access first.",
                    confirmButtonText: "Get Location"
                }).then((result) => {
                    if (result.isConfirmed) {
                        requestLocation();
                    }
                });
            }

            $('button[id^="get_location_"]').on('click', function (e) {
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
                        function (position) {
                            var latitude = position.coords.latitude;
                            var longitude = position.coords.longitude;
                            var locationString = latitude + ", " + longitude;
                            locationInput.val(locationString);
                            locationStatus.text('Location retrieved.');
                        },
                        function (error) {
                            switch (error.code) {
                                case error.PERMISSION_DENIED:
                                    locationStatus.text('User denied the request for Geolocation.');
                                    break;
                                case error.POSITION_UNAVAILABLE:
                                    locationStatus.text('Location information is unavailable.');
                                    break;
                                case error.TIMEOUT:
                                    locationStatus.text('The request to get user location timed out.');
                                    break;
                                case error.UNKNOWN_ERROR:
                                    locationStatus.text('An unknown error occurred.');
                                    break;
                            }
                        }
                    );
                } else {
                    locationStatus.text('Geolocation is not supported by this browser.');
                }
            });

            $('button[id^="get_subj_location_"]').on('click', function (e) {
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
                        function (position) {
                            var latitude = position.coords.latitude;
                            var longitude = position.coords.longitude;
                            var locationString = latitude + ", " + longitude;
                            locationInput.val(locationString);
                            locationStatus.text('Location retrieved.');
                        },
                        function (error) {
                            switch (error.code) {
                                case error.PERMISSION_DENIED:
                                    locationStatus.text('User denied the request for Geolocation.');
                                    break;
                                case error.POSITION_UNAVAILABLE:
                                    locationStatus.text('Location information is unavailable.');
                                    break;
                                case error.TIMEOUT:
                                    locationStatus.text('The request to get user location timed out.');
                                    break;
                                case error.UNKNOWN_ERROR:
                                    locationStatus.text('An unknown error occurred.');
                                    break;
                            }
                        }
                    );
                } else {
                    locationStatus.text('Geolocation is not supported by this browser.');
                }
            });

            $('#approve, #reject').on('click', function () {
                if (!locationObtained) {
                    showLocationRequiredAlert();
                    return false;
                }
            });

            // ==================== SUBJECTIVE QUESTION HANDLING ====================
            $(".subjective_ques").change(function (event) {
                if (!locationObtained) {
                    showLocationRequiredAlert();
                    $(this).val(''); // Reset selection
                    return;
                }

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
                        success: function (response) {
                            console.log(response);
                            if (response.message == "success") {
                                let otherIds = response.otherSubjectiveIds;
                                let subject_answer_type = response.subjectOption.answer_type;
                                subject_answer_type = subject_answer_type.replace(/\s+/g, '');
                                let subjectValue = subject_answer_type + response.subjectOption.id;
                                let subjectId = response.subjectOption.id;

                                let subjectTypes = ["Location", "Audio", "Video", "Image", "FileUpload", "Yes/No", "Date", "Date&Time", "Dropdown", "FreeText", "Multiselect"];

                                subjectTypes.forEach(type => {
                                    otherIds.forEach(id => {
                                        let safeType = type.replace(/\W+/g, "_");
                                        $(`.${safeType}${id}`).addClass("d-none");
                                        $(`.${safeType}${id}`).removeAttr("required");
                                        $(`.${safeType}${id}`).prop("disabled", true);
                                    });
                                });

                                if (document.querySelector(`.${subjectValue}`)) {
                                    $(`.${subjectValue}`).removeClass('d-none');
                                    $(`.${subjectValue}`).attr('required', true);
                                    $(`.${subjectValue}`).prop('disabled', false);

                                    if (subject_answer_type == 'Location') {
                                        let id1 = 'get_subj_location_' + qid;
                                        let id2 = 'location_subj_status_' + qid;
                                        $(`#${id1}`).removeClass('d-none');
                                        $(`#${id2}`).removeClass('d-none');
                                        let locationinput = 'location_subj_' + qid;
                                        $(`#${locationinput}`).attr('required', true);
                                    } else {
                                        let id1 = 'get_subj_location_' + qid;
                                        let id2 = 'location_subj_status_' + qid;
                                        $(`#${id1}`).addClass('d-none');
                                        $(`#${id1}`).prop("disabled", true);
                                        $(`#${id2}`).addClass('d-none');
                                        $(`#${id2}`).prop("disabled", true);
                                        let locationinput = 'location_subj_' + qid;
                                        $(`#${locationinput}`).removeAttr('required');
                                        $(`#${locationinput}`).prop("disabled", true);
                                    }
                                } else {
                                    let id1 = 'get_subj_location_' + qid;
                                    let id2 = 'location_subj_status_' + qid;
                                    $(`.${subjectValue}`).addClass('d-none');
                                    $(`.${subjectValue}`).prop("disabled", true);
                                    $(`#${id1}`).addClass('d-none');
                                    $(`#${id1}`).prop("disabled", true);
                                    $(`#${id2}`).addClass('d-none');
                                    $(`#${id2}`).prop("disabled", true);
                                    let locationinput = 'location_subj_' + qid;
                                    $(`#${locationinput}`).removeAttr('required');
                                    $(`#${locationinput}`).prop("disabled", true);
                                    $(`.${subjectValue}`).removeAttr('required');
                                    $(`.${subjectValue}`).prop("disabled", true);
                                }
                            }
                        }
                    });
                }
            });

            $('#add_outlet_btn').on('click', function () {
                if (!locationObtained) {
                    showLocationRequiredAlert();
                    return;
                }
                $('#outlet_form').removeClass('d-none');
            });

        });
    </script>
@endsection


