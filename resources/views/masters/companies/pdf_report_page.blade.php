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
    <div id="page-lock-overlay" style="
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background-color: rgba(0,0,0,0.5);
    z-index: 9999;
    cursor: not-allowed;
    pointer-events: all;
">
        <div style="position: absolute; top: 50%; left: 50%; color: white; transform: translate(-50%, -50%); font-size: 20px;">
            Please wait... Export in progress.
        </div>
    </div>
    <div class="page-body">
        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>PDF Report Page </h4>
                    </div>
                    <div class="card-body">
                        <div class="horizontal-wizard-wrapper">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                        <div class="tab-pane fade show active" id="wizard-info" role="tabpanel"
                                             aria-labelledby="wizard-info-tab">
                                            <div class="row">

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
                                                    <label class="form-label" for="activity_id">Select
                                                        Activity</label>
                                                    <select class="form-select" id="activity_id"
                                                            onchange="getDistributors()">
                                                        <option selected="" disabled="" value="">Select Option
                                                        </option>
                                                    </select>
                                                </div>
                                                <div class="col-xl-4 col-sm-6">
                                                    <label class="form-label" for="activity_id">Select From Date</label>
                                                    <input class="form-control" type="date" name="fromDate" id="fromDate" onchange="getDistributors()">
                                                </div>
                                                <div class="col-xl-4 col-sm-6">
                                                    <label class="form-label" for="activity_id">Select To Date</label>
                                                    <input class="form-control" type="date" name="toDate" id="toDate" max="{{date('Y-m-d')}}" onchange="getDistributors()">
                                                </div>
                                                <div class="col-xl-4 col-sm-6 mt-4">
                                                    <button class="btn btn-primary" id="export_pdf" >Export PDF</button>
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
                                    <th>Auditor Name</th>
                                    <th>Agency Name</th>
                                    <th>Audit Date and Time</th>
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

        function lockPage() {
            $('#page-lock-overlay').show();

            // Disable F5 and Ctrl+R
            $(document).on('keydown.preventRefresh', function(e) {
                if ((e.which || e.keyCode) === 116 ||  // F5
                    (e.ctrlKey && e.which === 82)) {   // Ctrl + R
                    e.preventDefault();
                }
            });

            // Prevent close/refresh
            window.addEventListener('beforeunload', preventUnload);
        }

        function unlockPage() {
            $('#page-lock-overlay').hide();
            $(document).off('keydown.preventRefresh');
            window.removeEventListener('beforeunload', preventUnload);
        }

        function preventUnload(e) {
            e.preventDefault();
            e.returnValue = 'Deletion is in progress. Please wait.';
        }

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
        $("#activity_id").select2();

        //get activity data
        function getActivity() {

            let templateId = $('#project_template_id').val();
            let projectId = $('#project_id').val();

            $.ajax({
                'url': "{{route('company.projectActivity')}}",
                'method': 'POST',
                'data': {
                    'template_id': templateId,
                    'project_id': projectId,
                    "_token": "{{ @csrf_token() }}",
                },
                success: function (res) {
                    console.log(res);

                    if (res.status) {
                        let data = res.data.activityData;
                        // console.log(data);

                        let html = '<option disabled >Choose...</option>';

                        data.forEach((activity, index) => {

                            html +=
                                `<option value="${activity.id}" >${activity.activity_name}
                                    </option>`;

                        });

                        $('#activity_id').html(html);
                        getDistributors();
                    } else {

                        Swal.fire({
                            position: "center",
                            icon: "warning",
                            title: "The Above Project is Not Assigned",
                            timer: 3000, // 3 seconds
                            timerProgressBar: true,
                            didClose: () => {
                            }
                        });

                    }

                }
            })
        }

        function downloadPDF(rowId, distributorValue, activityID) {
            $.ajax({
                url: "{{route('pdf_template')}}",
                method: "POST",
                data: {
                    '_token': "{{ csrf_token() }}",
                    'row_id': rowId,
                    'distributor_value': distributorValue,
                    'activity_id': activityID,
                },
                success: function (res) {
                    // console.log(res);
                    if (res.file_url && res.file_name) {
                        // Create a temporary anchor to download the file
                        const a = document.createElement('a');
                        a.href = res.file_url;
                        a.download = res.file_name;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);

                        // Delay deletion (enough for download to start)
                        setTimeout(() => {
                            $.ajax({
                                url: "{{ route('delete_temp_pdf') }}",
                                method: "POST",
                                data: {
                                    _token: "{{ csrf_token() }}",
                                    file_name: res.file_name
                                },
                                success: function (deleteRes) {
                                    console.log('File deleted:', deleteRes);
                                },
                                error: function (err) {
                                    console.error('Delete failed:', err);
                                }
                            });
                        }, 5000); // Wait 5 seconds before deleting

                    }
                },
                error: function (err) {
                    console.error("Download failed", err);
                }
            });
        }


        //get distributor data
        function getDistributors() {
            let templateId = $('#project_template_id').val();
            let projectId = $('#project_id').val();
            let activityId = $('#activity_id').val();
            let fromDate = $('#fromDate').val();
            let toDate = $('#toDate').val();

            $('#distributor_id').html('<option selected="" disabled="" value="">Choose...</option>');
            $('#templates_table tbody').html('');
            $('.loader-backdrop').removeClass('d-none');

            $.ajax({
                url: "{{ route('company.getDistributors') }}",
                method: 'POST',
                data: {
                    'template_id': templateId,
                    'project_id': projectId,
                    'activity_id': activityId,
                    'from_date': fromDate,
                    'to_date': toDate,
                    "_token": "{{ @csrf_token() }}",
                },
                success: function (response) {
                    // console.log(response);
                    render_headers(response);
                }
            })
        }


        //render distributor data
        function render_headers(data) {
            $('.loader-backdrop').addClass('d-none');
            let selectedStatus = $('#approved_status').val();

            console.log(data);
            let companyverifycheckstatus = data.companyCanVerify;

            let templateId = $('#project_template_id').val();
            // console.log($('#project_template_id option:selected').data('master'));
            let isMaster = $('#project_template_id option:selected').data('master');
            let masterExist = data.masterExist;


            let sub_headers = data.sub_headers;
            let activityData = data.activities;
            let activityDiv = '';
            console.log(activityData);

            // Destroy the existing DataTable instance (if any)
            if ($.fn.DataTable.isDataTable('#templates_table')) {
                $('#templates_table').DataTable().destroy();
            }

            // Populate the table body
            let html = '';
            let main_headers;
            selectedStatus == 'all';

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

            // console.log(data);
            main_headers = data.main_headers;
            let indexcount = 0;
            $.each(main_headers, function (index, main_header_info) {
                const sub_header_info = sub_headers[index];
                // console.log(main_header_info);
                const valueSafe = main_header_info.value.replace(/'/g, "\\'");

                // console.log(main_header_info.answer_exist);

                if (main_header_info.answer_exist === true) {
                    console.log(main_header_info.auditorData, main_header_info.agencyName, main_header_info.answer_created_at);
                    indexcount++;

                    html += `
                    <tr>
                        <td>${indexcount}</td>
                        <td>`;

                    // const safeHeaderValue = encodeURIComponent(main_header_info.value);
                    console.log(main_header_info.id);

                    // if (currentUserRole == 'Verifier') {

                    let activityId = $('#activity_id').val();
                    // console.log(isMaster);
                    if (isMaster == 1) {
                        html +=
                            `<a class="badge badge-success" style="cursor:pointer;" onclick="downloadPDF('${main_header_info.id}', '${main_header_info.value}', '${activityId}')" ><i class="bi bi-download"></i>
                            </a>`;
                    } else {
                        html +=
                            `<a class="badge badge-success" style="cursor:pointer;" onclick="downloadPDF('${sub_header_info.id}', '${sub_header_info.value}', '${activityId}')" ><i class="bi bi-download"></i>
                            </a>`;
                    }

                    // }

                    // const valueSafe = main_header_info.value.replace(/'/g, "\\'");
                    html += `</td>
                        <td>${main_header_info.value}</a>
                        </td>;
                        <td>${sub_header_info.value}</td><td>`;

                    if (activityData.length > 0) {
                        const links = activityData.map(option => {
                            const activityId = option.id;
                            return `<span class="" >${option.activity_name}</span>`;
                        });

                        html += links.join(', ');
                    }

                    html += `<td>${main_header_info.auditorData}</td>
                             <td>${main_header_info.agencyName}</td>
                             <td>${main_header_info.answer_created_at}</td>`;

                    html += `</td>
                    </tr>
                `;

                }else{

                }

            })

            $('#templates_table tbody').html(html);

            // Reinitialize DataTable with pagination
            $('#templates_table').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                pageLength: 10,
                lengthChange: true
            });

        }


        $(document).ready(function () {
            $('.loader-backdrop').addClass('d-none');

            $('#project_template_id').on('change', function (){
                getActivity();
            });

            //get project template data
            $('#project_id').on('change', function () {
                let projectId = $(this).val();

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
                        // console.log(response);
                        if (response.status) {

                            let data = response.data[0].get_project_templates;
                            // let all_child_route_data = response.data[2];

                            // childTemplatesAllRoutes = response.data[2];
                            let html = '';

                            // all_child_route_data.forEach((routedata, index) => {
                            //     if (routedata.is_master == 0) {
                            //         childTemplatesAllRoutes.push(routedata);
                            //     }
                            //
                            // })
                            //
                            // childTemplates.length = 0;
                            data.forEach((project, index) => {
                                // if (project.is_master == 0) {
                                //     childTemplates.push(project);
                                // }
                                // console.log(project);
                                html +=
                                    `<option value="${project.id}" data-master="${project.is_master}"
                                            >${project.get_template.template_name}
                                    </option>`;

                            });
                            $('#project_template_id').append(html);

                        } else {
                            console.log(`${response.message}`);
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

            $('#export_pdf').on('click', function (){
                $('.loader-backdrop').addClass('d-none');
                lockPage();
                $.ajax({
                    url: "{{route('export_answer_pdfs')}}",
                    method: "POST",
                    data: {
                        "_token": "{{ @csrf_token() }}",
                        "project_id": $('#project_id').val(),
                        "project_template_id": $('#project_template_id').val(),
                        "activity_id": $('#activity_id').val(),
                        "start_date": $('#fromDate').val(),
                        "end_date": $('#toDate').val()
                    },
                    xhrFields: {
                        responseType: 'blob'
                    },
                    success: function (response, status, xhr) {
                        unlockPage();
                        let blob = new Blob([response], { type: 'application/zip' });
                        let link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = "All_PDFs.zip";
                        link.click();
                    },
                    error: function (xhr, status, error) {
                        unlockPage();
                        alert("Error generating ZIP: " + error);
                    }
                })
            });

        })
    </script>
@endsection
