@extends('template.layouts.simple.master')
@section('style')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .loader-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            /* Semi-transparent black backdrop */
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1050;
            /* Higher than Bootstrap modal (default is 1040) */
        }

        .large-spinner {
            width: 5rem;
            /* Adjust size as needed */
            height: 5rem;
            border-width: 0.4rem;
            /* Optional: Increase border thickness */
        }
    </style>
@endsection
@section('content')
    <div class="loader-backdrop">
        <div class="spinner-border text-light large-spinner" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    <div class="page-body">
        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Company verification </h4>
                    </div>
                    <div class="card-body">
                        <div class="horizontal-wizard-wrapper">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                        <div class="tab-pane fade show active" id="wizard-info" role="tabpanel"
                                             aria-labelledby="wizard-info-tab">
                                            <div class="row">
                                                @if (!empty($companies))
                                                    <div class="col-xl-4 col-sm-6">
                                                        <label class="form-label" for="company_id">Select
                                                            Company</label>
                                                        <select class="form-select" id="company_id" name="company_id"
                                                                required="">
                                                            <option selected="" disabled="" value="">Choose...
                                                            </option>
                                                            @foreach ($companies as $data)
                                                                <option value="{{ $data->id }}">
                                                                    {{ $data->company_name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        @error('company_id')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{ $message }}
                                                        </p>
                                                        @enderror
                                                    </div>
                                                @endif
                                                <div class="col-xl-4 col-sm-6">
                                                    <label class="form-label" for="project_id">Select
                                                        Project</label>
                                                    <select class="form-select" id="project_id" name="project_id"
                                                            required="">
                                                        <option selected="" disabled="" value="">Choose...
                                                        </option>
                                                        @if (!empty($projects))
                                                            @foreach ($projects as $data)
                                                                <option value="{{ $data->id }}">
                                                                    {{ $data->project_name }}</option>
                                                            @endforeach
                                                        @endif
                                                    </select>
                                                    @error('project_id')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{ $message }}
                                                    </p>
                                                    @enderror
                                                </div>
                                                <div class="col-xl-4 col-sm-6">
                                                    <label class="form-label" for="project_template_id">Select
                                                        Project Template</label>
                                                    <select class="form-select" id="project_template_id"
                                                            name="project_template_id" required=""
                                                    >
                                                        <option selected="" disabled="" value="">Choose...
                                                        </option>
                                                    </select>
                                                    @error('project_template_id')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{ $message }}
                                                    </p>
                                                    @enderror
                                                </div>
                                                <div class="col-xl-4 col-sm-6">
                                                    <label class="form-label" for="approved_status">Select
                                                        Audit Status</label>
                                                    <select class="form-select" id="approved_status">
                                                        <option selected="" disabled="" value="">Select Option
                                                        </option>
                                                        <option value="all" selected>All</option>
                                                        <option value="approved">Approved</option>
                                                        <option value="unapproved">Not Approved</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-12 mt-2">

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Project Data</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive theme-scrollbar">
                            <table class="display" id="templates_table">
                                <thead>
                                <tr>
                                    <th>Sno</th>
                                    <th>Action</th>
                                    <th>Main Header</th>
                                    <th>Sub Header</th>
                                    <th>Activity Name</th>
                                </tr>
                                </thead>
                                <tbody id="project_data_table_body">

                                </tbody>
                            </table>
                            <div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Zero Configuration  Ends-->

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

        {{-- view header data model --}}
        <div class="modal fade" id="viewHeaderDataModel" tabindex="-1" aria-labelledby="viewHeaderDataModelLabel"
             aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewHeaderDataModel">Header Data</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="viewHeaderDataModelBody">

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        {{-- view header data model --}}

        {{-- view child data model --}}
        <div class="modal fade" id="viewChildDataModel" tabindex="-1" aria-labelledby="viewChildDataModelLabel"
             aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewChildDataModelHeader"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="viewChildDataModelBody">

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        {{-- view child data model --}}

        {{-- view child template model --}}
        <div class="modal fade" id="viewChildTemplateModel" tabindex="-1" aria-labelledby="viewChildTemplateModel"
             aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewChildTemplateModelHeader">Child Templates Assigned Data</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="viewChildTemplateModelBody">

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        {{-- view child template model --}}

        <div class="modal fade" id="errorMessage_modal" tabindex="-1" role="dialog"
             aria-labelledby="errorMessage_modal" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="modal-toggle-wrapper">
                            <ul class="modal-img">
                                <li><img src="{{ url('assets/images/gif/danger.gif') }}" alt="error"></li>
                            </ul>
                            <h4 class="text-center pb-2">Ohh! Warning!</h4>
                            <p class="text-center" id="error_message"></p>
                            <button class="btn btn-secondary d-flex m-auto" type="button"
                                    data-bs-dismiss="modal">Close
                            </button>
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
        const templates_table = $("#templates_table").DataTable({
            paging: true
        });

        const datatestvar = 'hii';
        const childTemplates = [];
        const childTemplatesAllRoutes = [];
        const currentUserRole = "{{ $currentUserRole }}";
        // console.log(currentUserRole);

        $("#company_id").select2();
        $("#zone_id").select2();
        $("#unit_id").select2();
        $("#project_id").select2();
        $("#project_template_id").select2();
        $("#distributor_id").select2();
        $("#project_activity_id").select2();
        $("#approved_status").select2();

        //get distributor data
        function getDistributors() {
            let templateId = $('#project_template_id').val();
            let projectId = $('#project_id').val();

            $('#distributor_id').html('<option selected="" disabled="" value="">Choose...</option>');
            $('#templates_table tbody').html('');
            $('.loader-backdrop').removeClass('d-none');

            $.ajax({
                url: "{{ route('company.getDistributors') }}",
                method: 'POST',
                data: {
                    'template_id': templateId,
                    'project_id': projectId,
                    "_token": "{{ @csrf_token() }}",
                },
                success: function (response) {
                    // console.log(response);
                    render_headers(response);
                }
            })
        }


        function openQuestionModel(activityId = null, templateId, headerValue, headerId, headerRowId) {

            $('#QuestionModalBodyData').html('');
            // console.log(activityId);

            $.ajax({
                url: "{{ route('company.getQuestionData') }}",
                method: "POST",
                data: {
                    "_token": "{{ @csrf_token() }}",
                    "template_id": templateId,
                    "activity_id": activityId,
                    "distributor_id": headerRowId,
                    "distributor_value": headerValue,
                    "main_header_templateid": headerId,
                },
                success: function (response) {
                    // console.log(response);
                    $('#QuestionModalBodyData').html('');

                    if (activityId) {

                        if (response.data.length > 0) {

                            let html = '<div class="row mb-3">';

                            // Show Auditor name (available in each answerData.get_user)
                            if (response.data.length > 0) {
                                const auditor = response.data[0].get_user;

                                let auditDate = response.data[0].created_at;
                                // Convert to Date object
                                let dateObjNew = new Date(auditDate);
                                // Format to readable date and time
                                let formattedDateTimeNew = dateObjNew.toLocaleString();
                                let agency = response.data[0].get_user.agency_user;

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
                                const verifier = response.data[0].get_verifier;
                                if (verifier) {

                                    let verificationDate = response.data[0].updated_at;
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
                            response.data.forEach(answerData => {
                                // const baseUrl = "{{ config('app.url') }}";
                                const baseUrl = "{{ url('/') }}";
                                let fileUrl = '';
                                if ((answerData.get_question_info.question_type == 'Image' || answerData
                                    .get_question_info.question_type == "File Upload" || answerData
                                    .get_question_info.question_type == "Video") && (answerData
                                    .user_answer != null)) {
                                    // console.log(answerData.user_answer);
                                    fileUrl = baseUrl + '/' + answerData.user_answer.replaceAll(
                                        '/company', '');
                                    // fileUrl = baseUrl + '/' + (answerData.user_answer.includes('/company')  ? answerData.user_answer.replaceAll('/company', '') : answerData.user_answer);
                                }


                                html += `
                                        <div class="row mb-2">
                                            <div class="col-4"><label><b>${answerData.get_question_info.question} :</b></label></div>
                                            <div class="col-7">
                                    `;

                                if (answerData.get_question_info.question_type == 'Image' || answerData
                                    .get_question_info.question_type == 'File Upload' || answerData
                                    .get_question_info.question_type == 'Video') {
                                    // console.log(fileUrl);
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
                                didClose: () => {
                                }
                            });
                        }

                    } else {

                        Swal.fire({
                            position: "center",
                            icon: "success",
                            title: `${response.message}`,
                            timer: 3000, // 3 seconds
                            timerProgressBar: true,
                            didClose: () => {
                                // location.reload();
                            }
                        });
                        getDistributors();

                    }

                }
            })
        }

        function viewHeaderData(templateId, headerValue, headerId, headerRowId) {

            $('#viewHeaderDataModelBody').html('');
            let isMaster = $('#project_template_id option:selected').data('master');

            $.ajax({
                url: "{{ route('company.getTemplateHeaderViewData') }}",
                method: "POST",
                data: {
                    "_token": "{{ @csrf_token() }}",
                    'template_id': templateId,
                    "row_id": headerRowId,
                    "distributor_value": decodeURIComponent(headerValue),
                    "main_header_templateid": headerId,
                },
                success: function (response) {
                    // console.log(response);
                    let html = '';
                    response.data.forEach(headerData => {
                        // console.log(headerData);
                        html += `
                                <div class="row">
                                    <label class="col-4"><b>${headerData.template_head_name} :</b></label>
                                    <span class="col-7"> ${headerData.value} </span>
                                </div>`;
                    });
                    $('#viewHeaderDataModelBody').append(html);
                    $('#viewHeaderDataModel').modal('show');
                }
            })
        }

        function viewChildOutlets(templateId, distributorValue, subHeaderTemplateHeadId, subHeaderProjectTemplateId,
                                  subHeaderId) {

            $('#viewChildDataModelBody').html('');

        }

        function viewChilds(templateId, distributorValue, subHeaderTemplateHeadId, subHeaderProjectTemplateId) {

            $('#viewChildTemplateModelBody').html('');

            let html = '<div class="row">';

            console.log(childTemplates);
            childTemplates.forEach(option => {

                let id = option.template_id;

                html += `
                        <div class="card shadow-lg mb-2 col-6" style="border-radius: 0.5rem;">
                            <div class="card-body p-2">
                                <h6 class="card-title mb-0">
                                    ${option.template_name}
                                </h6>
                                    <a class="badge badge-primary" style="cursor:pointer;"
                                    href="${option.url}" >
                                        <i class="bi bi-eye-fill"></i>
                                    </a>
                            </div>
                        </div>
                    `;
            });
            html += '</div>';
            $('#viewChildTemplateModelBody').append(html);
            $('#viewChildTemplateModel').modal('show');

        }

        //render distributor data
        function render_headers(data) {
            $('.loader-backdrop').addClass('d-none');
            let selectedStatus = $('#approved_status').val();

            // console.log(data);
            let companyverifycheckstatus = data.companyCanVerify;

            let templateId = $('#project_template_id').val();
            // console.log($('#project_template_id option:selected').data('master'));
            let isMaster = $('#project_template_id option:selected').data('master');
            let masterExist = data.masterExist;
            let sub_headers = data.sub_headers;
            let activityData = data.activities;
            // Destroy the existing DataTable instance (if any)
            if ($.fn.DataTable.isDataTable('#templates_table')) {
                $('#templates_table').DataTable().destroy();
            }
            // Populate the table body
            let html = '';
            let main_headers;
            if (selectedStatus == 'approved') {
                main_headers = data.main_headers.filter(header =>
                    header.answer_exist && header.verified_by_company == 1
                );
            } else if (selectedStatus == 'all') {
                main_headers = data.main_headers;
            } else if (selectedStatus == 'unapproved') {
                main_headers = data.main_headers.filter(header =>
                    header.answer_exist && header.verified_by_company == 0
                );
            }

            $.each(main_headers, function (index, main_header_info) {
                const sub_header_info = sub_headers[index];
                console.log(main_header_info);
                const valueSafe = main_header_info.value.replace(/'/g, "\\'");

                html += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>`;

                // console.log(main_header_info.answer_exist);
                if (main_header_info.answer_exist) {

                    if (currentUserRole == 'Verifier') {

                        html +=
                            `<a class="badge badge-success" ><i class="bi bi-download"></i>
                            </a>`;

                    } else {

                        if (main_header_info.verified_by_company == 1) {

                            html +=
                                `<a class="badge badge-success" ><i class="bi bi-check-circle"></i>
                            </a>
                            <a class="badge badge-secondary view-header-data" style="cursor:pointer;"
                                data-template-id="${templateId}"
                                data-value="${valueSafe}"
                                data-head-id="${main_header_info.template_name_head_id}"
                                data-row-id="${main_header_info.id}" >
                                <i class="bi bi-info-circle"></i>
                            </a>`;

                            if (isMaster == 1 && masterExist == 1) {
                                html += `<a class="badge badge-secondary view-child-data" style="cursor:pointer;"
                                        data-template-id="${templateId}"
                                        data-sub-value="${sub_header_info.value}"
                                        data-sub-head-id="${sub_header_info.template_name_head_id}"
                                        data-project-template-id="${sub_header_info.project_template_id}">
                                        <i class="bi bi-eye-fill"></i>
                                    </a>`;
                            }

                        } else {
                            if (isMaster == 1 && masterExist == 1 && companyverifycheckstatus) {
                                html +=
                                    `<a class="badge badge-secondary open-question-model" style="cursor:pointer;"
                                        data-activity-id=""
                                        data-template-id="${templateId}"
                                        data-value="${valueSafe}"
                                        data-head-id="${main_header_info.template_name_head_id}"
                                        data-row-id="${main_header_info.id}">
                            <i class="bi bi-check-circle"></i>
                            </a>
                            <a class="badge badge-secondary view-header-data" style="cursor:pointer;"
                                data-template-id="${templateId}"
                                data-value="${valueSafe}"
                                data-head-id="${main_header_info.template_name_head_id}"
                                data-row-id="${main_header_info.id}" >
                                <i class="bi bi-info-circle"></i>
                            </a>`;
                                html += `<a class="badge badge-secondary view-child-data" style="cursor:pointer;"
                                        data-template-id="${templateId}"
                                        data-sub-value="${sub_header_info.value}"
                                        data-sub-head-id="${sub_header_info.template_name_head_id}"
                                        data-project-template-id="${sub_header_info.project_template_id}">
                                <i class="bi bi-eye-fill"></i>
                            </a>`;
                            } else {

                                html +=
                                    `<a class="badge badge-secondary view-header-data" style="cursor:pointer;"
                                        data-template-id="${templateId}"
                                        data-value="${valueSafe}"
                                        data-head-id="${main_header_info.template_name_head_id}"
                                        data-row-id="${main_header_info.id}" >
                                    <i class="bi bi-info-circle"></i>
                                </a>`;

                            }

                        }

                    }

                } else {
                    html +=
                        `<a class="badge badge-secondary view-header-data" style="cursor:pointer;"
                                data-template-id="${templateId}"
                                data-value="${valueSafe}"
                                data-head-id="${main_header_info.template_name_head_id}"
                                data-row-id="${main_header_info.id}" >
                                <i class="bi bi-info-circle"></i>
                            </a>`;
                    if (isMaster == 1 && masterExist == 1) {
                        html += `<a class="badge badge-secondary view-child-data" style="cursor:pointer;"
                                        data-template-id="${templateId}"
                                        data-sub-value="${sub_header_info.value}"
                                        data-sub-head-id="${sub_header_info.template_name_head_id}"
                                        data-project-template-id="${sub_header_info.project_template_id}">
                                <i class="bi bi-eye-fill"></i>
                            </a>`;

                    }

                }

                // const valueSafe = main_header_info.value.replace(/'/g, "\\'");
                html += `</td>
                        <td>${main_header_info.value}</a>
                        </td>;
                        <td>${sub_header_info.value}</td><td>`;

                if (activityData.length > 0) {
                    const links = activityData.map(option => {
                        const activityId = option.id;
                        return `<a class="badge badge-secondary open-question-model" style="cursor:pointer;"
                                        data-activity-id="${activityId}"
                                        data-template-id="${templateId}"
                                        data-value="${valueSafe}"
                                        data-head-id="${main_header_info.template_name_head_id}"
                                        data-row-id="${main_header_info.id}">
                                    ${option.activity_name}</a>`;
                    });

                    html += links.join(' ');
                }

                html += `</td>
                    </tr>
                `;


            })

            $('#templates_table tbody').html(html);

            $(document).on('click', '.view-header-data', function () {
                const templateId = $(this).data('template-id');
                const value = $(this).data('value');
                const headId = $(this).data('head-id');
                const rowId = $(this).data('row-id');

                viewHeaderData(templateId, value, headId, rowId);
            });

            $(document).on('click', '.view-child-data', function () {
                const templateId = $(this).data('template-id');
                const value = $(this).data('sub-value');
                const headId = $(this).data('sub-head-id');
                const projectTemplateId = $(this).data('project-template-id');

                viewChilds(templateId, value, headId, projectTemplateId);
            });

            $(document).on('click', '.open-question-model', function () {
                const activityId = $(this).data('activity-id');
                const templateId = $(this).data('template-id');
                const value = $(this).data('value');
                const headId = $(this).data('head-id');
                const rowId = $(this).data('row-id');

                openQuestionModel(activityId, templateId, value, headId, rowId);
            });

            // Reinitialize DataTable with pagination
            $('#templates_table').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                pageLength: 10,
                lengthChange: true
            });

        }


        function viewDistributorData(templateId, headerName, AnswerId) {

        }

        //render question data
        function renderQuestionData() {
            let projectId = $('#project_id').val();
            let templateId = $('#project_template_id').val();
            let distributorId = $('#distributor_id').val();
            let companyId = $('#company_id').val();
            // let activityId = $('#project_activity_id').val();
            let groupInfoId = $('#group_info_id').val();
            // $('#templates_table tbody').html('');
            let mainHeaderTemplateId = $('#distributor_id').find(':selected').data('main-header-templateid');
            let mainHeaderTemplateValue = $('#distributor_id').find(':selected').data('main-header-value');
            let subHeaderTemplateId = $('#distributor_id').find(':selected').data('sub-header-templateid');
            // console.log(mainHeaderTemplateId, subHeaderTemplateId);
            let distributorName = $('#distributor_id').find(':selected').text();

        }


        $(document).ready(function () {
            $('.loader-backdrop').addClass('d-none');

            $('#project_template_id').on('change', function () {
                getDistributors();
            })

            //get project template data
            $('#project_id').on('change', function () {
                let projectId = $(this).val();
                // const getProjectTemplateDataUrl =
                //     "{{ route('company.projectTemplateData', ['id' => '__Id__']) }}";

                $('#project_template_id').html(
                    '<option selected="" disabled="" value="">Choose...</option>'
                );

                if ($.fn.DataTable.isDataTable('#templates_table')) {
                    $('#templates_table tbody').html('');
                }

                $.ajax({
                    url: "{{ route('company.projectTemplateData') }}",
                    method: "POST",
                    data: {
                        'projectId': projectId,
                        "_token": "{{ @csrf_token() }}",
                    },
                    success: function (response) {
                        console.log(response);
                        if (response.status) {
                            let data = response.data[1];
                            let all_child_route_data = response.data[2];

                            // childTemplatesAllRoutes = response.data[2];
                            let html = '';

                            all_child_route_data.forEach((routedata, index) => {
                                if (routedata.is_master == 0) {
                                    childTemplatesAllRoutes.push(routedata);
                                }

                            })

                            childTemplates.length = 0;
                            data.forEach((project, index) => {
                                console.log(project);
                                if (project.is_master == 0) {
                                    childTemplates.push(project);
                                }

                                html +=
                                    `<option value="${project.template_id}" data-master="${project.is_master}"
                                            data-url="${project.url}"
                                            >${project.template_name}
                                    </option>`;

                            });
                            $('#project_template_id').append(html);

                        } else {
                            // console.log(`${response.message}`);
                        }
                    }
                });

            })

            $('#approved_status').on('change', function () {
                getDistributors();
            })

            $('#company_id').on('change', function () {
                let companyId = $(this).val();

                $('#project_id').html(
                    '<option selected="" disabled="" value="">Choose...</option>'
                );

                $.ajax({
                    url: "{{ route('company.zoneData') }}",
                    method: "POST",
                    data: {
                        "_token": "{{ @csrf_token() }}",
                        "companyId": companyId
                    },
                    success: function (response) {
                        let html = '';
                        response.data.forEach(option => {
                            html +=
                                `<option value="${option.id}" >${option.project_name}</option>`;
                        });
                        $('#project_id').append(html);
                    }
                })
            });

        })
    </script>
@endsection
