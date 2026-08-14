@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <!-- Zero Configuration  Starts-->
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Data Assigned List
                                <a href="{{ route('assigned_data.export') }}">
                                    <button class = "btn btn-primary ms-3">Export Data</button>
                                </a>
                                <a href="{{ route('assign_data.create') }}">
                                    <button class="btn btn-primary float-end">Assign Data</button>
                                </a>
                            </h4>
                        </div>
                        <div class="modal fade" id="user_assigned_model" tabindex="-1" role="dialog"
                            aria-labelledby="exampleModal" aria-hidden="true">
                            <div class="modal-dialog modal-xl" role="document">
                                <div class="modal-content">
                                    <div class="modal-body">
                                        <div class="modal-toggle-wrapper">
                                            <h4 id="model_title"></h4>
                                            <div class="table-responsive theme-scrollbar">
                                                <table id="user_render_table" class="table">
                                                    <thead>
                                                        <tr>
                                                            <th>Sn</th>
                                                            <th id="user_type"></th>
                                                            <th>Verifier Name</th>
                                                            <th>Assigned Value</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal fade" id="delete_audit_modal" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">

                                    <div class="modal-header">
                                        <h5>Select Audits to Delete</h5>
                                    </div>

                                    <div class="modal-body">

                                        <input type="hidden" id="delete_user_name">
                                        <input type="hidden" id="delete_user_id">

                                        <label>Select Assigned Values</label>
                                        <select id="delete_audit_select" class="form-control select2" multiple>

                                        </select>

                                    </div>

                                    <div class="modal-footer">
                                        <button class="btn btn-danger" id="confirm_delete_audits">
                                            Delete Selected
                                        </button>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive theme-scrollbar">
                                <table class="display" id="templates_table">
                                    <thead>
                                        <tr>
                                            <th>Sno</th>
                                            <th>Company</th>
                                            <th>Zone</th>
                                            <th>Unit</th>
                                            <th>Project</th>
                                            <th>Activity Name</th>
                                            <th>Template Name</th>
                                            <th>Template Head</th>
                                            <th>Auditors</th>
                                            <th class="d-none">Verify Users</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($data_assigned_values as $data_assigned_value)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $data_assigned_value->getProjectTemplate->getProject->getUnit->getZone->getCompany->company_name }}
                                                </td>
                                                <td>{{ $data_assigned_value->getProjectTemplate->getProject->getUnit->getZone->zone_name }}
                                                </td>
                                                <td>{{ $data_assigned_value->getProjectTemplate->getProject->getUnit->unit_name }}
                                                </td>
                                                <td>{{ $data_assigned_value->getProjectTemplate->getProject->project_name }}
                                                </td>
                                                <td>{{ !empty($data_assigned_value->activityName) ? $data_assigned_value->activityName->activity_name : '' }}
                                                </td>
                                                <td>{{ isset($data_assigned_value->TemplateName) ? $data_assigned_value->TemplateName->template_name : '' }}
                                                </td>
                                                <td>{{ isset($data_assigned_value->TemplateHeadName) ? $data_assigned_value->TemplateHeadName->template_head_name : '' }}
                                                </td>
                                                <td class="view_auditors text-decoration-underline"
                                                    data-id="{{ $data_assigned_value->id }}"
                                                    data-activity-id="{{ $data_assigned_value->activity_id }}"
                                                    data-project-template-id="{{ $data_assigned_value->project_template_id }}"
                                                    style="cursor:pointer;">
                                                    {{ $data_assigned_value->getAutiors() }}
                                                </td>
                                                <td></td>
                                                <td>
                                                    <ul class="action">
                                                        {{--                                                        <li class="edit"><a --}}
                                                        {{--                                                                href="{{ route('data_assign.edit', $data_assigned_value->id) }}"><i --}}
                                                        {{--                                                                    class="icon-pencil-alt"></i></a> --}}
                                                        {{--                                                        </li> --}}
                                                        <li class="delete" data-id="{{ $data_assigned_value->id }}"
                                                            data-activity-id="{{ $data_assigned_value->activity_id }}"
                                                            data-project-template-id="{{ $data_assigned_value->project_template_id }}" ><i
                                                                class="icon-trash"></i>
                                                        </li>
                                                    </ul>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <div>
                                    {{ $data_assigned_values->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Zero Configuration  Ends-->
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var templates_table = $("#templates_table").DataTable({
                paging: false
            });

            $('#delete_audit_select').select2({
                dropdownParent: $('#delete_audit_modal'),
                width: '100%'
            });

            $(document).on("click", ".delete-assigned-user", function() {

                const userName = $(this).data("user");
                const row = $(this).closest("tr");

                Swal.fire({
                    title: 'Delete this assignment?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes Delete'
                }).then((result) => {

                    if (result.isConfirmed) {

                        $.ajax({
                            url: "{{ route('assigned_auditor.delete') }}",
                            type: "POST",
                            data: {
                                _token: "{{ csrf_token() }}",
                                user_name: userName
                            },
                            success: function(response) {

                                if (response.status === "success") {

                                    $("#user_render_table").DataTable()
                                        .row(row)
                                        .remove()
                                        .draw();

                                    Swal.fire("Deleted!", "", "success");
                                }
                            }
                        });

                    }

                });

            });


            //  function render_assigned_user(users, assignedValues = []) {
            //     // 1. Static labels
            //     $("#model_title").html(`<strong class="txt-danger"></strong>Auditors List`);
            //     $("#user_type").text("Auditor Name");

            //     // 2. Grab the existing DataTable instance, then clear old rows
            //     const dt = $("#user_render_table").DataTable();
            //     dt.clear();

            //     // 3. Convert assignedValues → single comma-separated string
            //     const combinedHeadValues = (assignedValues || [])
            //         .map(v => v.head_value?.trim())   // trim first
            //         .filter(v => v) // keep only non-empty strings
            //         .join(', ');

            //     // 4. Add one row per user (same head-value string for each)
            //     $.each(users || [], function (index, user) {
            //         dt.row.add([
            //             index + 1,                       // “#”
            //             user.get_user_info?.name || '',  // “User Name”
            //             combinedHeadValues               // “Head Value”
            //         ]);
            //     });

            //     // 5. Draw the table
            //     dt.draw();

            //     $("#user_assigned_model").modal('show')
            // }

            function render_assigned_user_old(userassignedvalues) {
                // 1. Static labels
                // console.log(userassignedvalues);
                // ⭐ store data globally so delete modal can access it
                window.auditData = userassignedvalues;

                $("#model_title").html(`<strong class="txt-danger"></strong>Auditors List`);
                $("#user_type").text("Auditor Name");

                // // 2. Grab the existing DataTable instance, then clear old rows
                const dt = $("#user_render_table").DataTable();
                dt.clear();

                // // 3. Convert assignedValues → single comma-separated string
                // const combinedHeadValues = (assignedValues || [])
                //     .map(v => v.head_value?.trim())   // trim first
                //     .filter(v => v) // keep only non-empty strings
                //     .join(', ');

                // 4. Add one row per user (same head-value string for each)
                let index = 1;
                $.each(userassignedvalues || [], function(name, user) {
                    console.log(user);
                    const combinedHeadValues = (user || [])
                        .map(v => v.head_value?.trim()) // trim first
                        .filter(v => v) // keep only non-empty strings
                        .join(', ');

                    const deleteBtn = `
                        <a class="text-danger  open-delete-modal" data-user="${name}">
                            <i class="icon-trash"></i>
                        </a>`;

                    dt.row.add([
                        index++, // “#”
                        name || '', // “User Name”
                        combinedHeadValues, // “Head Value”
                        deleteBtn
                    ]);

                });

                // 5. Draw the table
                dt.draw();

                $("#user_assigned_model").modal('show')
            }

            function render_assigned_user(userassignedvalues, verifierNames) {

                window.auditData = userassignedvalues;

                $("#model_title").html(`<strong class="txt-danger"></strong>Auditors List`);
                $("#user_type").text("Auditor Name");

                const dt = $("#user_render_table").DataTable();
                dt.clear();

                const verifierDisplay = (verifierNames && verifierNames.length)
                    ? verifierNames.join(', ')
                    : '—';

                let index = 1;

                userassignedvalues.forEach(user => {

                    const combinedHeadValues = [...new Set(
                        (user.audits || [])
                        .map(v => v.head_value?.trim())
                        .filter(v => v)
                    )].join(', ');

                    const deleteBtn = `
                        <a class="text-danger open-delete-modal"
                           data-user-id="${user.user_id}">
                            <i class="icon-trash"></i>
                        </a>`;

                    dt.row.add([
                        index++,
                        user.user_name,
                        verifierDisplay,
                        combinedHeadValues,
                        deleteBtn
                    ]);

                });

                dt.draw();
                $("#user_assigned_model").modal('show');
            }


            $(document).on("click", ".open-delete-modal", function() {

                const userId = $(this).data("user-id");

                $("#delete_user_id").val(userId);

                const user = window.auditData.find(u => u.user_id == userId);

                const userAudits = user?.audits || [];

                let options = '';

                userAudits.forEach(v => {
                    options += `<option value="${v.row_id}">${v.head_value}</option>`;
                });

                $("#delete_audit_select").html(options).trigger('change');

                $("#delete_audit_modal").modal('show');
            });

            $("#confirm_delete_audits").click(function() {

                const rowIds = $("#delete_audit_select").val();
                const userName = $("#delete_user_name").val();
                const userId = $("#delete_user_id").val();

                if (!rowIds.length) {
                    Swal.fire("Please select audits to delete");
                    return;
                }

                Swal.fire({
                    title: "Delete selected audits?",
                    icon: "warning",
                    showCancelButton: true
                }).then((result) => {

                    if (result.isConfirmed) {

                        $.ajax({
                            url: "{{ route('assigned_auditor.delete') }}",
                            type: "POST",
                            data: {
                                _token: "{{ csrf_token() }}",
                                row_ids: rowIds,
                                user_name: userName,
                                user_id: userId
                            },
                            success: function(res) {

                                if (res.status === "success") {

                                    Swal.fire("Deleted!", "", "success");

                                    $("#delete_audit_modal").modal("hide");
                                    location.reload();

                                }
                            }
                        });

                    }

                });

            });



            @if (session()->has('message'))
                Swal.fire({
                    position: "top-center",
                    icon: "success",
                    title: "{{ session('message') }}",
                    showConfirmButton: false,
                    timer: 1500
                });
            @endif

            $("#templates_table").on("click", ".delete", function(event) {
                const data_assigned_id = $(this).data('id');
                const activity_id = $(this).data('activity-id');
                const project_template_id = $(this).data('project-template-id');
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
                            url: '{{ route('assigned_data.destroy') }}',
                            type: "POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "id": data_assigned_id,
                                "activity_id": activity_id,
                                "project_template_id": project_template_id
                            },
                            success: function(response) {
                                console.log(response);
                                if (response == "Success") {
                                    templates_table.row(tar_row).remove().draw();
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
            })


            $("#templates_table").on("click", ".view_auditors", function(event) {
                const data_assigned_id = $(this).data('id');
                const activity_id = $(this).data('activity-id');
                const project_template_id = $(this).data('project-template-id');
                $("#user_assigned_model").modal('show')

                $.ajax({
                    url: '{{ route('auditors.show') }}',
                    type: "POST",
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "data_assigned_id": data_assigned_id,
                        "activity_id": activity_id,
                        "project_template_id": project_template_id,
                        "user_type": "auditors"
                    },
                    success: function(response) {
                        console.log(response);
                        if (response.message == "Success") {
                            render_assigned_user(response.assignedValues, response.verifierNames);
                        }
                    }
                })
            })
        });
    </script>
@endsection
