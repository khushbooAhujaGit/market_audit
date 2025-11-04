@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>My Project Data
                            @if($currentUserRole == 'Auditor')
                                <button id="add_data_btn"
                                        class ="btn btn-primary float-end">{{ $add_project_data_title }}
                                </button>
                            @endif
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-sm-4 g-3">
                            @forelse($project_data_arr as $project_data)
                                <div class="col-xl-4 col-md-6">
                                    <div class="prooduct-details-box row_data_con">
                                        <div class="d-flex">
                                            <div class="flex-grow-1 ms-3">
                                                <div class="product-name">
                                                    <h6>
                                                        @if (isset($project_data['main_header']))
                                                            {{ $project_data['main_header'] }}
                                                        @else
                                                            {{ optional($project_data['data_item']['head_name']) }}
                                                        @endif
                                                        <span class="show-details float-end"
                                                              data-val="{{ $project_data['data_item']['head_value'] }}"><i
                                                                class="fs-4 icofont icofont-exclamation-circle"></i></span>
                                                    </h6>
                                                </div>
                                                <div class="price d-flex border-0 p-0">
                                                    {{--                                                        @dd($project_data) --}}
                                                    <div class="text-muted me-2">
                                                        @if (isset($project_data['sub_header']))
                                                            {{ $project_data['sub_header'] }}
                                                        @else
                                                            {{ $project_data['data_item']['head_value'] }}
                                                        @endif
                                                    </div>
                                                    @if ($project_data['status'] == 'completed')
                                                        <div class="text-muted" style="margin-top: 10%;"><span
                                                                class="btn btn-primary btn-xs"
                                                                @if ($currentUserRole == 'Company User') onclick="viewQuestionModel('{{ $project_data['data_item']['id'] }}', '{{ $activity->id }}')" @endif>
                                                                @if ($project_data['status'] == 'pending')
                                                                    Pending
                                                                @else
                                                                    Completed
                                                                @endif
                                                            </span></div>
                                                    @else
                                                        <div class="text-muted" style="margin-top: 10%;">

                                                            <a class="btn btn-primary btn-xs"
                                                               @if ($currentUserRole == 'Company User') onclick="viewQuestionModel('{{ $project_data['data_item']['id'] }}', '{{ $activity->id }}')"
                                                               @else
                                                                   href="{{ route('user.project.row_id.activity', ['row_id' => $project_data['data_item']['id'], 'activity' => $activity->id, 'group_info' => isset($group_info->id) ? $group_info->id : null]) }}"
                                                                @endif>
                                                                @if ($project_data['status'] == 'pending')
                                                                    Pending
                                                                @else
                                                                    Completed
                                                                @endif
                                                            </a>

                                                        </div>
                                                    @endif

                                                </div>
                                                <div class="avaiabilty">

                                                </div>
                                                <div class="border">
                                                    <div class="show-con d-none">
                                                        @foreach ($project_data['data_item'] as $rowData)
                                                            @if(!empty($rowData))
                                                                <span>{{ $rowData['head_name']??'' }} -
                                                                {{ $rowData['head_value']??'' }}</span>
                                                                <br>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p>NO Data FOUND</p>
                            @endforelse

                        </div>
                    </div>
                </div>
            </div>
            <!-- Zero Configuration  Ends-->
            <div class="modal_con">
                <div class="modal fade" id="row_detail_modal" tabindex="-1" role="dialog"
                     aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="row_data_title"></h5>
                                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div id="main-content"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="add_data" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                     role="dialog" aria-labelledby="questionEditModal" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                        <div class="modal-content">
                            <!-- Modal Header with Close (X) Button -->
                            <div class="modal-header">
                                <h4 class="modal-title">{{ $add_project_data_title }}</h4>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="modal-toggle-wrapper">
                                    <form id="project_data_add_form">
                                        <div class="row">
                                            <input type="hidden" name="project_template_id"
                                                   value="{{ $project_temp_info->id }}">
                                            @foreach ($projectTemplateHeaders as $projectTemplateHeaderInfo)
                                                <div class="col-xl-6 col-sm-6">
                                                    <label class="form-label"
                                                           for="{{ $projectTemplateHeaderInfo->id }}">{{ $projectTemplateHeaderInfo->template_head_name }}</label>
                                                    <input class="form-control" id="{{ $projectTemplateHeaderInfo->id }}"
                                                           name="{{ $projectTemplateHeaderInfo->id }}" type="text"
                                                           placeholder="Enter Question" required="">
                                                </div>
                                            @endforeach
                                        </div>
                                    </form>
                                    <div class="mt-4 text-end">
                                        <button class="btn btn-secondary me-2" type="button"
                                                data-bs-dismiss="modal">Close</button>
                                        <button class="btn btn-primary" id="submit_data_btn" type="button">Add Now</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- view question data model --}}
    <div class="modal fade" id="viewQuestionDataModal" tabindex="-1" aria-labelledby="viewQuestionDataModalLabel"
         aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewQuestionDataModal">Question/Answer Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="QuestionModalBodyData">

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    {{-- view question data model --}}

@endsection
@section('scripts')
    <script>
        function viewQuestionModel(rowId, activityId) {
            $('#QuestionModalBodyData').html('');

            $.ajax({
                url: "{{ route('getQuestionAnswersofChildTemplate') }}",
                method: "POST",
                data: {
                    "_token": "{{ @csrf_token() }}",
                    'row_id': rowId,
                    'activity_id': activityId,
                },
                success: function(response) {
                    console.log(response);
                    let data = response.data.activityAnswerData

                    if (data.length > 0) {

                        let html = '<div class="row mb-3">';

                        // Show Auditor name (available in each answerData.get_user)
                        if (data.length > 0) {
                            const auditor = data[0].get_user;

                            let auditDate = data[0].created_at;
                            // Convert to Date object
                            let dateObjNew = new Date(auditDate);
                            // Format to readable date and time
                            let formattedDateTimeNew = dateObjNew.toLocaleString();
                            let agency = data[0].get_user.agency_user;

                            if (agency) {
                                html += `
                                        <div class="col-4"><b>Agency Name:</b></div>
                                        <div class="col-7">${agency.name.toUpperCase()}</div>
                                    `;
                            }

                            html += `
                                        <div class="col-4 mt-2"><b>Auditor Name:</b></div>
                                        <div class="col-7 mt-2">${auditor.name.toUpperCase()}</div>
                                        <div class="col-4 mt-2"><b>Audit Date-Time:</b></div>
                                        <div class="col-7 mt-2">${formattedDateTimeNew}</div>
                                    `;

                            // Show Verifier name if available
                            const verifier = data[0].get_verifier;
                            if (verifier) {

                                let verificationDate = data[0].updated_at;
                                // Convert to Date object
                                let dateObj = new Date(verificationDate);
                                // Format to readable date and time
                                let formattedDateTime = dateObj.toLocaleString();

                                html += `
                                            <div class="col-4 mt-2"><b>Verifier Name:</b></div>
                                            <div class="col-7 mt-2">${verifier.name.toUpperCase()}</div>
                                            <div class="col-4 mt-2"><b>Verification Date-Time:</b></div>
                                            <div class="col-7 mt-2">${formattedDateTime}</div>
                                        `;
                            }
                        }

                        html += '</div>'; // Close auditor/verifier row

                        // Render questions and answers
                        data.forEach(answerData => {
                            // const baseUrl = "{{ config('app.url') }}";
                            const baseUrl = "{{ url('/') }}";
                            let fileUrl = '';

                            if((answerData.get_question_info.question_type == "File Upload" || answerData.get_question_info.question_type == "Video") && (answerData.user_answer != null)){
                                console.log(answerData.user_answer);
                                fileUrl = baseUrl + '/' + answerData.user_answer.replaceAll('/company','');
                                // fileUrl = baseUrl + '/' + (answerData.user_answer.includes('/company')  ? answerData.user_answer.replaceAll('/company', '') : answerData.user_answer);
                            }


                            html += `
                                        <div class="row mb-2">
                                            <div class="col-4"><label><b>${answerData.get_question_info.question} :</b></label></div>
                                            <div class="col-7">
                                    `;

                            if (answerData.get_question_info.question_type === 'Image' || answerData
                                .get_question_info.question_type === 'File Upload') {
                                html +=
                                    `<label><a href="${fileUrl}" target="_blank">View File</a></label>`;
                            } else {
                                html += `<label>${answerData.user_answer}</label>`;
                            }

                            html += `
                                            </div>
                                        </div>
                                    `;
                        });

                        // Finally inject to modal

                        $('#QuestionModalBodyData').html(html);
                        $('#viewQuestionDataModal').modal('show');

                    } else {

                        Swal.fire({
                            position: "center",
                            icon: "warning",
                            title: "Either Answer is not filled Or not verified",
                            timer: 3000, // 3 seconds
                            timerProgressBar: true,
                            didClose: () => {}
                        });
                    }
                }
            })
        }

        $(document).ready(function() {
            @if (session()->has('message'))
            Swal.fire({
                position: "top-center",
                icon: "success",
                title: "{{ session('message') }}",
                showConfirmButton: false,
                timer: 1500
            });
            @endif
            $(".show-details").click(function(event) {
                const data_val = $(this).data('val');
                const row_con = $(this).closest('.row_data_con');
                const row_data = row_con.find('.show-con').html();
                $("#main-content").html(row_data)
                $("#row_data_title").html(data_val)
                $("#row_detail_modal").modal('show');
            });

            // this is to add the funcationality to add the data in the project template
            $("#add_data_btn").click(function() {
                $("#add_data").modal("show");
            })
            $("#submit_data_btn").click(function(event) {
                event.preventDefault();
                const pt_id = $("#project_template_id").val();
                let formData = new FormData($('#project_data_add_form')[0]);
                formData.append("_token", "{{ csrf_token() }}"); // Append CSRF token to FormData
                let filledFields = 0;
                formData.forEach((value, key) => {
                    if (value !== "" && value !== null) {
                        filledFields++;
                    }
                });
                if (filledFields > 2) {
                    $.ajax({
                        url: "{{ route('projectData.add') }}",
                        type: "POST",
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.message == "success") {
                                $("#add_data").modal("hide");
                                Swal.fire({
                                    position: "top-center",
                                    icon: "success",
                                    title: "New Data added ,will are redirecting to answers",
                                    showConfirmButton: false,
                                    timer: 1500
                                });
                                location.reload();

                            }
                        }
                    })
                } else {
                    $("#add_data").modal("hide");

                    Swal.fire({
                        icon: "error",
                        title: "Oops...",
                        text: "You Need to add atleast one value!",
                        footer: '<a href="#">Why do I have this issue?</a>'
                    });

                }
            })
        })
    </script>
@endsection
