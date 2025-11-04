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
                        <h4>Compliance Action Taken </h4>

                    </div>
                    <div class="card-body">
                        <div class="horizontal-wizard-wrapper">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                        <div class="tab-pane fade show active" id="wizard-info" role="tabpanel"
                                            aria-labelledby="wizard-info-tab">
                                            <div class="row">
                                                <div class="col-xl-3 col-sm-6">
                                                    <label class="form-label" for="project_id">Select
                                                        Project</label>
                                                    <select class="form-select" id="project_id" name="project_id"
                                                        required="" onchange="getNonComplianceData()">
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

                    </div>
                    <div class="card-body">
                        <div class="table-responsive theme-scrollbar">
                            <table class="display" id="non_compliance_data_table">
                                <thead>
                                    <tr>
                                        <th>Sno</th>
                                        <th>Action</th>
                                        <th class="text-nowrap">Company Name</th>
                                        <th class="text-nowrap">Distributor Name</th>
                                        <th class="text-nowrap">Audit Firm</th>
                                        <th>Auditor</th>
                                        <th class="text-nowrap">Verifier Name</th>
                                        <th class="text-nowrap">Audit Period</th>
                                        <th class="text-nowrap">Non Compliance</th>
                                        <th class="text-nowrap">Comments/Action Details</th>
                                        <th class="text-nowrap">Amount Debit</th>
                                        <th class="text-nowrap">Document Number</th>
                                        <th class="text-nowrap">Document Attached</th>
                                    </tr>
                                </thead>
                                <tbody id="complianceTbodyContainer">

                                </tbody>
                            </table>
                             <div id="custom-pagination" class="mt-3"></div>
                            <div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- Action Taken Modal --}}
        <div class="modal" tabindex="-1" id="ActionTakenModal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Action Taken Modal</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="actionTakenForm">
                            @csrf
                            <div class="row">
                                <div class="col-md-12">
                                    <label class="form-label" for="">Upload File</label>
                                    <input class="form-control" type="file" name="complianceDocument"
                                        id="complianceDocument" required>
                                </div>
                                <div class="col-md-12 d-none mt-2" id="amountDiv">
                                    <label class="form-label" for="">Enter Amount</label>
                                    <input class="form-control" type="number" placeholder="Enter Amount"
                                        name="action_taken_amount" id="action_taken_amount">
                                </div>
                                <div class="col-md-12 d-none mt-2 mt-2" id="documentDiv">
                                    <label class="form-label" for="">Enter Document Number</label>
                                    <input class="form-control" type="number" placeholder="Enter Document Number"
                                        name="compliance_document_number" id="compliance_document_number">
                                </div>
                                <div class="col-md-12 d-none mt-2" id="remarkDiv">
                                    <label class="form-label" for="">Enter Remark</label>
                                    <input class="form-control" type="text" placeholder="Enter Remark"
                                        name="action_remark" id="action_remark">
                                </div>
                                <input type="hidden" name="project_template_id" value=""
                                    id="project_template_id">
                                <input type="hidden" name="row_id" value="" id="row_id">
                                <input type="hidden" name="template_name_head_id" value=""
                                    id="template_name_head_id">
                                <input type="hidden" name="template_value" value="" id="template_value">
                                <input type="hidden" name="actionTaken" value="" id="actionTaken">
                                <div class="col-md-12 mt-2">
                                    <button type="button" class="btn btn-primary"
                                        onclick="actionTakenFormSubmit()">Submit</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        {{-- Action Taken Modal --}}

        <div class="modal fade" id="errorMessage_modal" tabindex="-1" role="dialog"
            aria-labelledby="errorMessage_modal" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="modal-toggle-wrapper">
                            <ul class="modal-img">
                                <li> <img src="{{ url('assets/images/gif/danger.gif') }}" alt="error"></li>
                            </ul>
                            <h4 class="text-center pb-2">Ohh! Warning!</h4>
                            <p class="text-center" id="error_message"></p>
                            <button class="btn btn-secondary d-flex m-auto" type="button"
                                data-bs-dismiss="modal">Close</button>
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
        let allTableData = [];
        let currentTab = 'all'; // all, pending, action
        let currentPage = 1;
        const rowsPerPage = 10;

        const templates_table = $("#non_compliance_data_table").DataTable({
            paging: false,
            searching: true,
            info: false,
            ordering: true
        });

        $("#project_id").select2();

        function renderPagination(totalItems, currentPage, onPageClick) {
            const lastPage = Math.ceil(totalItems / rowsPerPage);
            let paginationHtml = `<nav aria-label="Page navigation"><ul class="pagination justify-content-center">`;

            paginationHtml += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage - 1}">&laquo;</a></li>`;

            for (let i = 1; i <= lastPage; i++) {
                paginationHtml += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
            }

            paginationHtml += `<li class="page-item ${currentPage === lastPage ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage + 1}">&raquo;</a></li>`;

            paginationHtml += `</ul></nav>`;

            $('#custom-pagination').html(paginationHtml);

            $('#custom-pagination .page-link').off('click').on('click', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                if (page && page > 0 && page <= lastPage) {
                    currentPage = page;
                    // console.log(page);
                    onPageClick(page);
                }
            });
        }

        function openActionTakenModal(templateId, mainHeaderValue, templateNameHeadeId, rowId, type) {
            $('#actionTakenForm')[0].reset();

            if (type == 1) {
                // $('#remarkDiv').addClass('d-none');
                $('#amountDiv').removeClass('d-none');
                $('#documentDiv').removeClass('d-none');
                $('#action_taken_amount').prop('required', true);
                $('#compliance_document_number').prop('required', true);
                $('#action_remark').prop('required', false);
                $('#actionTaken').val('Action Taken');
            } else {
                $('#remarkDiv').removeClass('d-none');
                $('#amountDiv').addClass('d-none');
                $('#documentDiv').addClass('d-none');
                $('#action_remark').prop('required', true);
                $('#action_taken_amount').prop('required', false);
                $('#compliance_document_number').prop('required', false);
                if (type == 2) {
                    $('#actionTaken').val('Exceptional Action Taken');
                } else {
                    $('#actionTaken').val('Incorrect Audit');
                }

            }

            $('#template_name_head_id').val(templateNameHeadeId);
            $('#template_value').val(mainHeaderValue);
            $('#row_id').val(rowId);
            $('#project_template_id').val(templateId);

            $('#ActionTakenModal').modal('show');

        }


        function render_headers(data, tabStatus, page = 1) {
            $('.loader-backdrop').addClass('d-none');
            // Destroy the existing DataTable instance (if any)
            let table = $('#non_compliance_data_table').DataTable();
            if ($.fn.DataTable.isDataTable('#non_compliance_data_table')) {
                table.clear();
            }

            // let rowData = [];

            console.log(data);
            let html = '';

            let main_headers = data.paginated.data;
            let sub_headers = data.sub_headers;

            let rowData = [];
            let childauditorsData = data.childauditorAssignDataIds;
            let verifiersData = data.verifierNames;

            if (main_headers == null || main_headers.length == 0) {
                Swal.fire({
                    position: "center",
                    icon: "warning",
                    title: "No Data Found",
                    timer: 3000, // 3 seconds
                    timerProgressBar: true
                });
            } else {

                let agencyUserIds = data.agencyUserIds;
                // console.log(agencyUserIds);
                let count = 0;
                const pageLength = data.paginated.per_page;
                const currentPage = data.paginated.current_page;
                
                $.each(main_headers, function(index, main_header_info) {
                    const sub_header_info = sub_headers[index];

                    // console.log(startDate)
                    const templateNameToCheck = sub_header_info.value; // Adjust key as per your structure
                    let auditorNames = '';
                    let matchedUser = '';
                    const addedUserIds = new Set();

                    childauditorsData.forEach(function(item) {
                        const userId = item.get_user_info.id;
                        const userName = item.get_user_info.name;
                        matchedUser = agencyUserIds.find(user => user.id === item.get_user_info
                            .agency_user_id);

                        if (!addedUserIds.has(userId)) {
                            if (auditorNames !== '') {
                                auditorNames += `, `;
                            }
                            auditorNames += userName;
                            addedUserIds.add(userId);
                        }
                    });
                    // console.log(matchedUser);
                    //verifier names
                    // let verifiersNames = '';
                    // verifiersData.find(function(item) {
                    //     if (verifiersData.length > 1) {
                    //         verifiersNames += `, ${item.get_verifier.name}`;
                    //     } else {
                    //         verifiersNames += `${item.get_verifier.name}`;
                    //     }
                    // });
                    let verifiersNames = '';
                    const verifierUserIds = new Set();
                    verifiersData.find(function(item) {
                        const userId = item.get_verifier.id;
                        const userName = item.get_verifier.name;

                        if (!verifierUserIds.has(userId)) {
                            if (verifiersNames !== '') {
                                verifiersNames += `, `;
                            }
                            verifiersNames += userName;
                            verifierUserIds.add(userId);
                        }

                        // if (verifiersData.length > 1) {
                        //     verifiersNames += `, ${item.get_verifier.name}`;
                        // } else {
                        //     verifiersNames += `${item.get_verifier.name}`;
                        // }
                    });


                    let templateId = main_header_info.get_project_template.id;
                    // console.log(templateId);
                    const valueSafe = main_header_info.value.replace(/'/g, "\\'");

                    let actiontaken = `
                        <div class="btn-group">
                            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-gear"></i> <span class="visually-hidden">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                 <a class="dropdown-item" onclick="openActionTakenModal('${templateId}', '${valueSafe}', 
                                 '${main_header_info.template_name_head_id}', '${main_header_info.row_id}', 1)" 
                                 style="cursor:pointer;">Action Taken</a>
                                </li>
                                <li>
                                 <a class="dropdown-item" onclick="openActionTakenModal('${templateId}', '${valueSafe}', 
                                 '${main_header_info.template_name_head_id}', '${main_header_info.row_id}', 2)"
                                 style="cursor:pointer;">Exceptional Action Taken</a>
                                </li>
                                <li>
                                 <a class="dropdown-item" onclick="openActionTakenModal('${templateId}', '${valueSafe}', 
                                 '${main_header_info.template_name_head_id}', '${main_header_info.row_id}', 3)"
                                 style="cursor:pointer;">Incorrect Audit</a>
                                </li>
                            </ul>
                        </div>
                    `;

                    let auditPeriod = '';
                    // console.log(main_header_info.duration_days);

                    if (main_header_info.duration_days && main_header_info.duration_days != 0) {
                        auditPeriod += main_header_info.duration_days + ' days';
                    }
                    if (main_header_info.duration_human) {
                        auditPeriod += main_header_info.duration_days != 0 ? ' and ' + main_header_info
                            .duration_human :
                            main_header_info.duration_human;
                    }

                    let complianceDocumentLInk = '';
                    if (main_header_info.complianceDocument) {
                        complianceDocument = main_header_info.complianceDocument;
                        complianceDocumentLInk =
                            `<a href="${complianceDocument}" target="_blank" >View Document</a>`;
                    }

                    const rowIndex = (currentPage - 1) * pageLength + count + 1;
                    count++;

                    rowData.push([
                        rowIndex,
                        main_header_info.action_taken_type ?
                        '<span class="badge badge-primary">No Action</span>' : actiontaken,
                        main_header_info.get_project_template.get_project.get_company_info.company_name,
                        `${valueSafe} - ${sub_header_info.value}`,
                        `${matchedUser? matchedUser.name : ''}`, `${auditorNames}`, `${verifiersNames}`,
                        `${auditPeriod}`,
                        `${sub_header_info.compliance_remarks??''}`,
                        `${main_header_info.action_remark??''}`,
                        `${main_header_info.action_taken_amount??''}`,
                        `${main_header_info.compliance_document_number??''}`,
                        `${main_header_info.complianceDocument? complianceDocumentLInk :  ''}`
                    ]);
                });

            }

            allTableData = rowData;
            // console.log(rowData);
            // Paginate
            const startIndex = (page - 1) * rowsPerPage;
            table.clear().rows.add(rowData).draw();
            let type = 'data';
            renderPagination(data.paginated.total, page, (newPage) => getNonComplianceData(type, newPage, tabStatus));

        }

        function getNonComplianceData(type = 'data', page = 1, status = 'all') {
            let projectId = $('#project_id').val();
            // let projectTemplate = $('#project_template_id').val();
            console.log('get master templates');
            $('.loader-backdrop').removeClass('d-none');

            $.ajax({
                url: "{{ route('company.nonComplainceData') }}",
                method: "POST",
                data: {
                    "_token": "{{ @csrf_token() }}",
                    project_id: projectId,
                    complianceType: 'compliance',
                    type: type,
                    page: page,
                    status: status,
                },
                success: function(response) {
                    // console.log(response);
                    render_headers(response, status, page);
                    $('.loader-backdrop').addClass('d-none');
                }
            });
        }


        function actionTakenFormSubmit() {
            let form = $('#actionTakenForm')[0]; // or .get(0)
            let formData = new FormData(form);

            $.ajax({
                url: "{{ route('add_action_taken') }}",
                method: "POST",
                data: formData,
                processData: false, // Important for file upload
                contentType: false, // Important for file upload
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') // if not already in header
                },
                success: function(response) {
                    console.log(response);
                    $('#ActionTakenModal').modal('hide');
                    if (response.status) {
                        Swal.fire({
                            position: "center",
                            icon: "success",
                            title: `${response.message}`,
                            timer: 3000, // 3 seconds
                            timerProgressBar: true
                        });
                        getNonComplianceData();
                    } else {
                        Swal.fire({
                            position: "center",
                            icon: "warning",
                            title: `${response.message}`,
                            timer: 3000, // 3 seconds
                            timerProgressBar: true
                        });
                        getNonComplianceData();
                    }

                },
                error: function(xhr) {
                    // handle error
                    alert('An error occurred: ' + xhr.responseJSON.message);
                }
            });
        }

        $(document).ready(function() {
            $('.loader-backdrop').addClass('d-none');

            // $('#project_id').on('change', function() {
            //     let projectId = $(this).val();
            //     // document.querySelector('.loader-backdrop').style.display = 'flex';
            //     $.ajax({
            //         url: "{{ route('company.projectTemplateData') }}",
            //         method: "POST",
            //         data: {
            //             "projectId": projectId,
            //             "_token": "{{ @csrf_token() }}",
            //         },
            //         success: function(response) {
            //             // console.log(response);
            //             if (response.status) {
            //                 let data = response.data;
            //                 let html = '';

            //                 data.forEach((project, index) => {
            //                     const masterTemplates = project.get_project_templates
            //                         .filter(t => t.is_master == 1);

            //                     // const masterTemplates = project.get_project_templates;

            //                     masterTemplates.forEach(option => {
            //                         // console.log(option);
            //                         html +=
            //                             `<option value="${option.id}">${option.get_template.template_name}</option>`;
            //                     });

            //                     $('#project_template_id').append(html);

            //                 });

            //             } else {
            //                 console.log(`${response.message}`);
            //             }
            //         }
            //     })
            // });
        })
    </script>
@endsection
