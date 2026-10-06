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
                                                            <th>Activity</th>
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

                        <div class="modal fade" id="activities_modal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="activities_modal_title">Assigned Activities</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        {{-- A list, not a .table: the layout turns every .table into a DataTable --}}
                                        <ul class="list-group" id="activities_modal_list"></ul>
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
                                            <th>Activities</th>
                                            <th>Template Name</th>
                                            <th>Template Head</th>
                                            <th>Auditors</th>
                                            <th class="d-none">Verify Users</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {{-- One row per assigned project template; its activities open in a popup --}}
                                        @foreach ($data_assigned_values as $group)
                                            @php
                                                $assign  = $group->assign;
                                                $project = $assign?->getProjectTemplate?->getProject;
                                                $activityCount = $group->activities->count();
                                            @endphp
                                            <tr>
                                                <td>{{ ($data_assigned_values->firstItem() ?? 1) + $loop->index }}</td>
                                                <td>{{ $project?->getUnit?->getZone?->getCompany?->company_name }}</td>
                                                <td>{{ $project?->getUnit?->getZone?->zone_name }}</td>
                                                <td>{{ $project?->getUnit?->unit_name }}</td>
                                                <td>{{ $project?->project_name }}</td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary view_activities"
                                                        data-activities="{{ json_encode($group->activities) }}"
                                                        data-project-template-id="{{ $group->project_template_id }}"
                                                        data-template-name="{{ $assign?->templateName?->template_name }}"
                                                        data-project-name="{{ $project?->project_name }}">
                                                        {{ $activityCount }} {{ \Illuminate\Support\Str::plural('Activity', $activityCount) }}
                                                    </button>
                                                </td>
                                                <td>{{ $assign?->templateName?->template_name }}</td>
                                                <td>{{ $assign?->TemplateHeadName?->template_head_name }}</td>
                                                <td class="view_auditors text-decoration-underline"
                                                    data-id="{{ $group->first_id }}"
                                                    data-project-template-id="{{ $group->project_template_id }}"
                                                    style="cursor:pointer;">
                                                    {{ $group->auditor_count }}
                                                </td>
                                                <td></td>
                                                <td>
                                                    <ul class="action">
                                                        <li class="delete"
                                                            data-activity-ids="{{ json_encode($group->activities->pluck('id')) }}"
                                                            data-project-template-id="{{ $group->project_template_id }}"
                                                            title="Delete assignment for all activities of this template"><i
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

            function escapeHtml(value) {
                return $('<div>').text(value ?? '').html();
            }

            // One line per auditor + verifier (row-wise verifier assignment); rows with no
            // verifier are shown on a line with "—".
            function render_assigned_user(lines) {

                window.auditData = lines;

                $("#model_title").html(`<strong class="txt-danger"></strong>Auditors List`);
                $("#user_type").text("Auditor Name");

                const dt = $("#user_render_table").DataTable();
                dt.clear();

                lines.forEach((line, i) => {

                    const combinedHeadValues = [...new Set(
                        (line.audits || [])
                        .map(v => v.head_value?.trim())
                        .filter(v => v)
                    )].join(', ');

                    // Removes the auditor's assignment for this line's rows
                    const deleteBtn = (line.audits || []).length
                        ? `<a class="text-danger open-delete-modal" data-line="${i}">
                               <i class="icon-trash"></i>
                           </a>`
                        : '';

                    const verifierCell = !line.verifier_name
                        ? '—'
                        : escapeHtml(line.verifier_name) + (line.template_wise
                            ? '<br><small class="text-muted">(template-wise assignment)</small>'
                            : '');

                    const activityCell = (line.activity_names || []).map(escapeHtml).join(', ');

                    dt.row.add([
                        i + 1,
                        escapeHtml(line.user_name),
                        activityCell,
                        verifierCell,
                        combinedHeadValues ? escapeHtml(combinedHeadValues) : (line.verifier_name ? '<span class="text-muted">No rows yet</span>' : ''),
                        deleteBtn
                    ]);

                });

                dt.draw();
                $("#user_assigned_model").modal('show');
            }


            $(document).on("click", ".open-delete-modal", function() {

                const line = (window.auditData || [])[$(this).data("line")] || {};

                $("#delete_user_id").val(line.user_id);

                const userAudits = line.audits || [];

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

            // Deletes the assignment for every activity of the template, then reloads the list.
            function deleteAssignment(payload, confirmText) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: confirmText,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (!result.isConfirmed) return;
                    $.ajax({
                        url: '{{ route('assigned_data.destroy') }}',
                        type: "POST",
                        data: Object.assign({ "_token": "{{ csrf_token() }}" }, payload),
                        success: function(response) {
                            if (response && response.message === "Success") {
                                Swal.fire('Deleted!', 'Assignment has been deleted.', 'success')
                                    .then(() => location.reload());
                            } else {
                                Swal.fire('Not deleted', (response && response.message) || 'Please try again.', 'error');
                            }
                        },
                        error: function(xhr) {
                            Swal.fire('Not deleted', (xhr.responseJSON && xhr.responseJSON.message) || 'Please try again.', 'error');
                        }
                    });
                });
            }

            $("#templates_table").on("click", ".delete", function() {
                const activityIds = $(this).data('activity-ids') || [];
                deleteAssignment(
                    { "project_template_id": $(this).data('project-template-id'), "activity_ids": activityIds },
                    `This deletes the assignment for all ${activityIds.length} activit${activityIds.length === 1 ? 'y' : 'ies'} of this template. You won't be able to revert this!`
                );
            });

            $("#templates_table").on("click", ".view_activities", function() {
                const activities = $(this).data('activities') || [];
                const title = [$(this).data('project-name'), $(this).data('template-name')].filter(Boolean).join(' — ');

                $("#activities_modal_title").text(title ? `Assigned Activities: ${title}` : 'Assigned Activities');
                const $list = $("#activities_modal_list").empty();
                activities.forEach((activity, i) => {
                    $('<li class="list-group-item d-flex gap-3">')
                        .append($('<span class="text-muted">').text(`${i + 1}.`))
                        .append($('<span>').text(activity.name))
                        .appendTo($list);
                });
                $("#activities_modal").modal('show');
            });


            $("#templates_table").on("click", ".view_auditors", function(event) {
                const data_assigned_id = $(this).data('id');
                const project_template_id = $(this).data('project-template-id');
                $("#user_assigned_model").modal('show')

                // No activity_id: auditors across every activity of this template
                $.ajax({
                    url: '{{ route('auditors.show') }}',
                    type: "POST",
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "data_assigned_id": data_assigned_id,
                        "project_template_id": project_template_id,
                        "user_type": "auditors"
                    },
                    success: function(response) {
                        console.log(response);
                        if (response.message == "Success") {
                            render_assigned_user(response.assignedValues);
                        }
                    }
                })
            })
        });
    </script>
@endsection
