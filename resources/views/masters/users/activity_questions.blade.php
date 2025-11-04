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
                                <div class="col-xl-6 col-md-6">
                                    <div class="card">
                                        <div class="table-card">
                                            <h5 class="mt-2 p-2">Activity Questions</h5>
                                            <form method="post" action="{{ route('user.rowId.activity.answers') }}"
                                                enctype="multipart/form-data">
                                                @csrf
                                                @if (isset($group_info->id))
                                                    <input type="hidden" name="group_id" value="{{ $group_info->id }}">
                                                @endif
                                                <input type="hidden" name="row_id"
                                                    value="{{ $row_data->id }}">
                                                <input type="hidden" name="activity_id"
                                                    value="{{ $related_questions->first()->activity_id }}">
                                                @if (isset($activity_group_info))
                                                    <input type="hidden" name="activity_group_id"
                                                        value="{{ $activity_group_info->id }}">
                                                @endif
                                                <table class="table mb-0">
                                                    <thead>
                                                    </thead>
                                                    <tbody>
                                                        @php  $selectArrIds = []; @endphp
                                                        @foreach ($related_questions as $related_question)
                                                            <tr>
                                                                <td class="col-md-6 fw-medium">
                                                                    {{ $related_question->question }} @if ($related_question->answer_type)
                                                                        <span class="txt-danger">*</span>
                                                                    @endif
                                                                </td>
                                                                <td class="col-md-6">

                                                                    @switch($related_question->question_type)
                                                                        @case('Image')
                                                                            <input name="{{ $related_question->id }}" type="file"
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
                                                                                @if ($related_question->answer_type) required="" @endif
                                                                                name="{{ $related_question->id }}">
                                                                                <option value="">Select ..</option>
                                                                                <option value="Yes">Yes</option>
                                                                                <option value="No">No</option>
                                                                            </select>
                                                                        @break

                                                                        @case('Subjective')
                                                                            <div class="d-flex flex-column gap-2">
                                                                                <select class="form-select subjective_ques"
                                                                                    data-q_id="{{ $related_question->id }}">
                                                                                    <option value="">Select Subject ..
                                                                                    </option>
                                                                                    @foreach ($related_question->getSubjects as $questionSubjects)
                                                                                        <option
                                                                                            value="{{ $questionSubjects->subject }}">
                                                                                            {{ $questionSubjects->subject }}
                                                                                        </option>
                                                                                    @endforeach
                                                                                </select>
                                                                                <select
                                                                                    @if ($related_question->answer_type) required="" @endif
                                                                                    class="form-select subjective_ques_option"
                                                                                    name="{{ $related_question->id }}">
                                                                                    <option value="">Select Subject Option ..
                                                                                    </option>
                                                                                </select>
                                                                            </div>
                                                                        @break

                                                                        @case('Dropdown')
                                                                            <select class="form-select"
                                                                                @if ($related_question->answer_type) required="" @endif
                                                                                name="{{ $related_question->id }}">
                                                                                <option value="">Select ..</option>
                                                                                @foreach ($related_question->getOptions as $questionOption)
                                                                                    <option value="{{ $questionOption->option }}">
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
                                                                                @if ($related_question->answer_type) required="" @endif
                                                                                name="{{ $related_question->id }}[]"
                                                                                id="select{{ $related_question->id }}" multiple>
                                                                                <option value="">Select ..</option>
                                                                                @foreach ($related_question->getOptions as $questionOption)
                                                                                    <option value="{{ $questionOption->option }}">
                                                                                        {{ $questionOption->option }}
                                                                                    </option>
                                                                                @endforeach
                                                                            </select>
                                                                        @break

                                                                        @case('Date')
                                                                            <input type="date" value=""
                                                                                @if ($related_question->answer_type) required="" @endif
                                                                                name="{{ $related_question->id }}"
                                                                                class="form-control">
                                                                        @break

                                                                        @case('File Upload')
                                                                            <input type="file"
                                                                                @if ($related_question->answer_type) required="" @endif
                                                                                name="{{ $related_question->id }}"
                                                                                class="form-control">
                                                                        @break

                                                                        @case('Date & Time')
                                                                            <input type="datetime-local"
                                                                                @if ($related_question->answer_type) required="" @endif
                                                                                name="{{ $related_question->id }}" value=""
                                                                                class="form-control">
                                                                        @break

                                                                        @case('Location')
                                                                            <input type="text"
                                                                                id="location_{{ $related_question->id }}"
                                                                                name="{{ $related_question->id }}"
                                                                                class="form-control get_location"
                                                                                @if ($related_question->answer_type) required="" @endif
                                                                                readonly>
                                                                            <button type="button" class="btn btn-info"
                                                                                id="get_location_{{ $related_question->id }}"
                                                                                data-id="{{ $related_question->id }}">Get
                                                                                Location
                                                                            </button>
                                                                            <span class="p-3"
                                                                                id="location_status_{{ $related_question->id }}"></span>
                                                                        @break

                                                                        @case('Audio')
                                                                            <div class="audio-recorder">
                                                                                <button type="button"
                                                                                    class="btn btn-sm btn-primary start-recording"
                                                                                    data-id="{{ $related_question->id }}">Record</button>
                                                                                <button type="button"
                                                                                    class="btn btn-sm btn-danger stop-recording"
                                                                                    data-id="{{ $related_question->id }}"
                                                                                    disabled>Stop</button>
                                                                                <button type="button"
                                                                                    class="btn btn-sm btn-warning delete-audio"
                                                                                    data-id="{{ $related_question->id }}"
                                                                                    disabled>Delete</button>

                                                                                <audio
                                                                                    id="audio-player-{{ $related_question->id }}"
                                                                                    controls class="d-none"></audio>
                                                                                <input type="hidden"
                                                                                    name="{{ $related_question->id }}"
                                                                                    class="audio-data"
                                                                                    data-id="{{ $related_question->id }}">
                                                                            </div>
                                                                        @break

                                                                        @default
                                                                            <input type="text" value=""
                                                                                @if ($related_question->answer_type) required="" @endif
                                                                                class="form-control"
                                                                                name="{{ $related_question->id }}">
                                                                    @endswitch
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>


                                                <button type="button" id="approve" name="save" class="btn btn-primary float-end">
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
    <script>
        $(document).ready(function() {
            const multiSelects = @json($selectArrIds);
            $.each(multiSelects, function(index, select_id) {
                $(`#${select_id}`).select2({
                    theme: "bootstrap-5", // Use Bootstrap 5 styling
                    placeholder: "Select options", // Adds placeholder text
                    allowClear: true, // Allows clearing the selection
                    closeOnSelect: false, // Keeps dropdown open after selection
                    width: '100%', // Makes it responsive to the container's width
                });
            })
            @if (session()->has('message'))
                Swal.fire({
                    position: "top-center",
                    icon: "success",
                    title: "{{ session('message') }}",
                    showConfirmButton: false,
                    timer: 1500
                });
            @endif
            $('button[id^="get_location_"]').on('click', function(e) {
                e.preventDefault();

                var questionId = $(this).data('id');
                var locationInput = $('#location_' + questionId);
                var locationStatus = $('#location_status_' + questionId);

                // Check if geolocation is available
                if (navigator.geolocation) {
                    // Show a message while retrieving location
                    locationStatus.text('Getting location...');

                    // Get the current position
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            var latitude = position.coords.latitude;
                            var longitude = position.coords.longitude;
                            var locationString = latitude + ", " + longitude;

                            // Set the location value in the input field
                            locationInput.val(locationString);
                            locationStatus.text('Location retrieved.');
                        },
                        function(error) {
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
            $('#approve, #reject').on('click', function() {
                Swal.fire({
                    position: "top-center",
                    icon: "warning",
                    title: "Please use app for answer submission",
                    showConfirmButton: false,
                    timer: 1500
                });
                // // Enable all text inputs by removing readonly
                // $('input[readonly]').removeAttr('readonly');
                // // Enable all file and date inputs by removing disabled
                // $('input[disabled], select[disabled]').removeAttr('disabled');
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
                                    $.each(response.subjectOption.get_options, function(
                                        index, subjectOptions) {
                                        let subjectOption =
                                            `${subjectInfo} (${subjectOptions.option})`;
                                        subject_options_select.append($(
                                            '<option>').text(
                                            subjectOptions.option).val(
                                            subjectOption));
                                    });
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
            // this is to get the locations on the load of the page where question type is location
            $('.get_location').each(function() {
                var questionId = $(this).attr('id').split('_')[1]; // Extracting ID
                var locationInput = $('#location_' + questionId);
                var locationStatus = $('#location_status_' +
                questionId); // Ensure you have this element in your HTML
                if (navigator.geolocation) {
                    locationStatus.text('Getting location...');
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            var latitude = position.coords.latitude;
                            var longitude = position.coords.longitude;
                            var locationString = latitude + ", " + longitude;
                            locationInput.val(locationString);
                            locationStatus.text('Location retrieved.');
                        },
                        function(error) {
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

            $('#add_outlet_btn').on('click', function(){
                $('#outlet_form').removeClass('d-none');
            });

        });
    </script>
@endsection
