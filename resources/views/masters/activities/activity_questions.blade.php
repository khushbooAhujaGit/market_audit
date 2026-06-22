@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Activities Name : {{ optional($activity)->activity_name }} <a
                                href="{{route('activities.list')}}">
                                <button class="btn btn-primary float-end">View Acitivties</button>
                            </a></h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive theme-scrollbar">
                            <table class="display" id="question_table">
                                <thead>
                                <tr>
                                    <th>Sno</th>
                                    <th>Question</th>
                                    <th>Question Type</th>
                                    <th>Question Sequence</th>
                                    <th>Answer Type</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @php
                                    $dropdown_questions = $activity->questions
                                        ->whereIn('question_type', ['Dropdown', 'Yes / No'])
                                        ->values();
                                @endphp
                                @foreach($activity->questions as $activity_question)
                                    <tr class="{{ $activity_question->is_parent == 0 ? 'child-question' : '' }}"
                                        @if($activity_question->is_parent == 0) style="background-color: #eef2ff !important;border-left: 4px solid #7366ff;" @endif >
                                        <td>{{$loop->iteration}}</td>
                                        <td>{{$activity_question->question}}</td>
                                        <td>{{$activity_question->question_type}}</td>
                                        <td>
                                            @if($activity_question->is_parent == 1)
                                                {{$activity_question->question_sequence}}
                                            @endif
                                        </td>
                                        <td>@if($activity_question->answer_type)
                                                Required
                                            @else
                                                Optional
                                            @endif
                                        </td>
                                        <td>
                                            <ul class="action">
                                                @if($activity_question->question_type =="Dropdown" || $activity_question->question_type == "Multi select")
                                                    <li class="dropdown_questions"><a
                                                            href="{{route('activity.question.dropdown', ["id"=>$activity_question->id])}}"><i
                                                                class="icon-eye text-secondary fs-5"></i></a></li>
                                                @elseif($activity_question->question_type =="Subjective")
                                                    <li class="subjective_question"><a
                                                            href="{{route('activity.question.subjective', ["id"=>$activity_question->id])}}"><i
                                                                class="icon-eye text-warning fs-5"></i></a></li>
                                                @endif
                                                @if($activity_question->question_type === 'Multi Response')
                                                <li class="sub_questions" title="Manage Sub Questions">
                                                    <a href="{{route('activity.question.sub_questions', ['question_id' => $activity_question->id])}}">
                                                        <i class="icon-layers fs-5 text-success"></i>
                                                    </a>
                                                </li>
                                                @endif
                                                <li class="edit" data-question="{{$activity_question->question}}"
                                                    data-q_type="{{$activity_question->question_type}}"
                                                    data-sequence="{{$activity_question->question_sequence}}"
                                                    data-id="{{$activity_question->id}}"
                                                    data-answer_type="{{$activity_question->answer_type}}"
                                                    data-is_parent="{{$activity_question->is_parent}}"
                                                    data-parent_question_id="{{$activity_question->parent_question_id ?? ''}}"
                                                    data-parent_value="{{$activity_question->parent_value ?? ''}}"><i
                                                        class="icon-pencil-alt fs-5"></i></li>

                                                <li class="delete" data-id="{{$activity_question->id}}"><i
                                                        class="icon-trash fs-5"></i></li>
                                            </ul>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div>
                            </div>
                        </div>
                        <div class="modal_container">
                            <div class="modal fade" id="questionEditModal" tabindex="-1" role="dialog"
                                 aria-labelledby="questionEditModal" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-body">
                                            <div class="modal-toggle-wrapper">

                                                <h4 class="text-center pb-2">Edit Question</h4>
                                                <form>
                                                    <input class="form-control" id="question_id" name="question_id"
                                                           type="hidden" placeholder="Enter question_id" required="">
                                                    <div class="col-xl-12 col-sm-12">
                                                        <label class="form-label" for="question">Question<span
                                                                class="txt-danger">*</span></label>
                                                        <input class="form-control" id="question" name="question"
                                                               type="text" placeholder="Enter Question" required="">
                                                    </div>
                                                    <div class="col-xl-12 col-sm-12">
                                                        <label class="form-label" for="question_type">Question Type<span
                                                                class="txt-danger">*</span></label>
                                                        <select class="form-control" name="question_type"
                                                                id="question_type">
                                                            <option value="Yes / No">Yes / No</option>
                                                            <option value="Free Text">Free Text</option>
                                                            <option value="Subjective">Subjective</option>
                                                            <option value="Dropdown">Dropdown</option>
                                                            <option value="Multi select">Multi select</option>
                                                            <option value="Image">Image</option>
                                                            <option value="Video">Video</option>
                                                            <option value="Date">Date</option>
                                                            <option value="Date & Time">Date & Time</option>
                                                            <option value="File Upload">File Upload</option>
                                                            <option value="Location">Location</option>
                                                            <option value="Audio">Audio</option>
                                                            <option value="Multi Response">Multi Response (Section / No Input)</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-xl-12 col-sm-12">
                                                        <label class="form-label" for="question_sequence">Question
                                                            Sequence<span class="txt-danger"></span></label>
                                                        <input class="form-control" id="question_sequence"
                                                               name="question_sequence" type="number"
                                                               placeholder="Enter question sequence">
                                                    </div>
                                                    <div class="col-xl-12 col-sm-12">
                                                        <label class="form-label" for="answer_type">Answer Type<span
                                                                class="txt-danger"></span></label>
                                                        <select class="form-control" name="answer_type"
                                                                id="answer_type">
                                                            <option value="1">Required</option>
                                                            <option value="0">Optional</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-xl-12 col-sm-12 mt-2 d-none"
                                                         id="parent_question_div">
                                                        <label class="form-label" for="parent_question">Select Parent
                                                            Question<span
                                                                class="txt-danger"></span></label>
                                                        <select class="form-control" name="parent_question"
                                                                id="parent_question">
                                                        </select>
                                                    </div>
                                                    <div class="col-xl-12 col-sm-12 mt-2 d-none"
                                                         id="parent_question_dropdown_div">
                                                        <label class="form-label" for="parent_question">Select Parent
                                                            Question Dropdown<span
                                                                class="txt-danger"></span></label>
                                                        <select class="form-control" name="parent_question_dropdown"
                                                                id="parent_question_dropdown">
                                                        </select>
                                                    </div>

                                                </form>
                                                <div class="d-flex justify-content-between mt-4">
                                                    <button class="btn btn-primary d-flex m-auto" type="button"
                                                            data-bs-dismiss="modal">Close
                                                    </button>
                                                    <button class="btn btn-primary d-flex m-auto"
                                                            id="update_question_btn" type="button"
                                                            data-bs-dismiss="modal">Update
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal fade" id="errorMessage_modal" tabindex="-1" role="dialog"
                                 aria-labelledby="errorMessage_modal" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-body">
                                            <div class="modal-toggle-wrapper">
                                                <ul class="modal-img">
                                                    <li><img src="{{url('assets/images/gif/danger.gif')}}" alt="error">
                                                    </li>
                                                </ul>
                                                <h4 class="text-center pb-2">Ohh! Something went wrong!</h4>
                                                <p class="text-center" id="error_message"></p>
                                                <button class="btn btn-primary d-flex m-auto" type="button"
                                                        data-bs-dismiss="modal">Close
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Zero Configuration  Ends-->
        </div>
    </div>

@endsection
@section('scripts')
    <script>
        $(document).ready(function () {
            // Initialize DataTable
            var question_table = $("#question_table").DataTable({
                paging: false
            });
            @if(session()->has('message'))
            Swal.fire({
                position: "top-center",
                icon: "success",
                title: "{{ session('message') }}",
                showConfirmButton: false,
                timer: 1500
            });
            @endif

            let dropdownQuestions = @json($dropdown_questions);

            $('#parent_question').on('change', function () {
                let question_id = $(this).val();
                $.ajax({
                    url: "{{route('get_parent_question_dropdown')}}",
                    method: "POST",
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "id": question_id
                    },
                    success: function (response) {
                        console.log(response);
                        if (response.status) {
                            let data = response.question_option;
                            $('#parent_question_dropdown').html('');
                            data.forEach(function (q) {
                                $("#parent_question_dropdown").append(
                                    `<option value="${q.id ?? q.option}">${q.option}</option>`
                                );
                            });
                        }
                    }
                });
            });

            $("#question_table").on("click", ".delete", function (event) {
                const question_id = $(this).data('id');
                const tar_row = $(this).closest('tr');
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{route('activity.question.destroy')}}',
                            type: "POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "id": question_id
                            },
                            success: function (response) {
                                console.log(response)
                                if (response == "Success") {
                                    question_table.row(tar_row).remove().draw();
                                    Swal.fire(
                                        'Deleted!',
                                        'Record has been deleted.',
                                        'success'
                                    )
                                }
                            }
                        })
                    }
                })
            });

            $("#question_table").on("click", ".edit", function (event) {
                const q_id = $(this).data('id');
                const q = $(this).data('question');
                const q_type = $(this).data('q_type');
                const q_answer_type = $(this).data('answer_type');
                const q_sequence = $(this).data('sequence');
                const tar_row = $(this).closest('tr');
                $("#question_id").val(q_id);
                $("#question").val(q);
                $("#question_sequence").val(q_sequence);
                $("#question_type").val(q_type);
                $("#answer_type").val(q_answer_type);
                let is_parent = $(this).data('is_parent');
                let existing_parent_id = $(this).data('parent_question_id');
                let existing_parent_value = $(this).data('parent_value');
                console.log(dropdownQuestions);
                if (is_parent == 0) {
                    $('#parent_question_dropdown_div').removeClass('d-none');
                    $('#parent_question_div').removeClass('d-none');
                    $("#parent_question").html('<option value="">select question type</option>');
                    dropdownQuestions.forEach(function (q) {
                        $("#parent_question").append(
                            `<option value="${q.id}">${q.question}</option>`
                        );
                    });
                    // Pre-select existing parent question if set
                    if (existing_parent_id) {
                        $('#parent_question').val(existing_parent_id);
                        // Load the dropdown options for that parent, then select existing value
                        $.ajax({
                            url: "{{route('get_parent_question_dropdown')}}",
                            method: "POST",
                            data: {
                                "_token": "{{ csrf_token() }}",
                                "id": existing_parent_id
                            },
                            success: function (response) {
                                if (response.status) {
                                    let data = response.question_option;
                                    $('#parent_question_dropdown').html('');
                                    data.forEach(function (q) {
                                        $("#parent_question_dropdown").append(
                                            `<option value="${q.id ?? q.option}">${q.option}</option>`
                                        );
                                    });
                                    // Pre-select existing parent value
                                    if (existing_parent_value) {
                                        $('#parent_question_dropdown').val(existing_parent_value);
                                        // Try matching by text if val() didn't match
                                        if (!$('#parent_question_dropdown').val()) {
                                            $('#parent_question_dropdown option').each(function () {
                                                if ($(this).text() === existing_parent_value) {
                                                    $(this).prop('selected', true);
                                                }
                                            });
                                        }
                                    }
                                }
                            }
                        });
                    } else {
                        $('#parent_question_dropdown').html('');
                    }
                } else {
                    $('#parent_question_dropdown_div').addClass('d-none');
                    $('#parent_question_div').addClass('d-none');
                }
                $("#questionEditModal").modal('show');
            });

            // Attach the click event to the update button only once
            $("#update_question_btn").click(function () {
                const ques_value = $("#question").val();
                const ques_seq = $("#question_sequence").val();
                const ques_id = $("#question_id").val();
                const ques_type = $("#question_type").val();
                const answer_type = $("#answer_type").val();
                const parent_question = $('#parent_question').val();
                const parent_question_dropdown = $('#parent_question_dropdown').val();
                const parent_value = $('#parent_question_dropdown  option:selected').text();
                if (ques_value == "") {
                    $("#error_message").html("Question Value Can not be blank")
                    $("#errorMessage_modal").modal('show')
                } else {
                    $.ajax({
                        url: "{{ route('question.update') }}",
                        type: "PUT",
                        data: {
                            "_token": "{{ csrf_token() }}",
                            "form_data": {
                                "question": ques_value,
                                "question_sequence": ques_seq,
                                "id": ques_id,
                                "question_type": ques_type,
                                "answer_type": answer_type,
                                "parent_question_id": parent_question,
                                "parent_dropdown_id": parent_question_dropdown,
                                "parent_value": parent_value
                            }
                        },
                        success: function (response) {
                            if (response.message == "error") {
                                Swal.fire({
                                    icon: "error",
                                    title: "Cannot Map Parent Question",
                                    text: response.error,
                                    confirmButtonColor: "#d33"
                                });
                                return;
                            }
                            if (response.message == "success") {
                                Swal.fire({
                                    position: "top-center",
                                    icon: "success",
                                    title: "Question Updated Successfully",
                                    showConfirmButton: false,
                                    timer: 1500
                                });
                                const q_info = response.question_info;
                                const tar_row = $("#question_table").find(`[data-id="${ques_id}"]`).closest('tr');
                                tar_row.find('td:nth-child(2)').text(q_info['question']);
                                tar_row.find('td:nth-child(3)').text(q_info['question_type']);
                                tar_row.find('td:nth-child(4)').text(q_info['question_sequence']);
                                let ans_type = "Optional";
                                if (q_info['answer_type'] == 1) {
                                    ans_type = "Required";
                                }
                                tar_row.find('td:nth-child(5)').text(ans_type);
                                tar_row.find('td:nth-child(6) li.edit').data('question', q_info['question']);
                                tar_row.find('td:nth-child(6) li.edit').data('q_type', q_info['question_type']);
                                tar_row.find('td:nth-child(6) li.edit').data('sequence', q_info['question_sequence']);
                                tar_row.find('td:nth-child(6) li.edit').data('answer_type', q_info['answer_type']);
                                location.reload();
                            }
                        }
                    });
                }
            });
        });
    </script>
@endsection
