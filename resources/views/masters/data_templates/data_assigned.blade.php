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
                            <div class="modal-dialog" role="document">
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
                                                            <th>Assigned Value</th>
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
                                                    data-id="{{ $data_assigned_value->id }}">
                                                    {{ $data_assigned_value->getAutiors() }}
                                                </td>
                                                <td></td>
                                                <td>
                                                    <ul class="action">
{{--                                                        <li class="edit"><a--}}
{{--                                                                href="{{ route('data_assign.edit', $data_assigned_value->id) }}"><i--}}
{{--                                                                    class="icon-pencil-alt"></i></a>--}}
{{--                                                        </li>--}}
                                                        <li class="delete" data-id="{{ $data_assigned_value->id }}"><i
                                                                class="icon-trash"></i></li>
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

            // function render_assigned_user(users) {
            //     $("#model_title").html(`<strong class="txt-danger"></strong>Auditors List`)
            //     $("#user_type").html("Auditor Name");
            //     var user_table = $("#user_render_table").DataTable(); // Get reference to the DataTable instance
            //     user_table.clear(); // Clear the existing data
            //     $.each(users, function(index, user) {
            //         let new_tr = $("<tr>");
            //         let sn = $("<td>").text(index + 1);
            //         let user_td = $("<td>").text(user.get_user_info.name);
            //         let assigned_value_td = $("<td>").text(user.template_name_head_value);
            //         new_tr.append(sn, user_td, assigned_value_td);
            //         // Append the new row to the DataTable
            //         user_table.row.add(new_tr);
            //     });
            //     // Redraw the DataTable to reflect the changes
            //     user_table.draw();
            //
            // }

            function render_assigned_user_old(users, assignedValues) {
                // 1. Static labels
                $("#model_title").html(`<strong class="txt-danger"></strong>Auditors List`);
                $("#user_type").text("Auditor Name");

                // 2. Grab the existing DataTable instance, then clear old rows
                const dt = $("#user_render_table").DataTable();
                dt.clear();

                // 3. Convert assignedValues → single comma‑separated string
                const combinedHeadValues = assignedValues
                    .map(v => v.head_value)
                    .join(", ");   // e.g. "Test1, Test2, Test3"

                // 4. Add one row per user (same head‑value string for each)
                $.each(users, function (index, user) {
                    dt.row.add([
                        index + 1,                       // “#”
                        user.get_user_info.name,         // “User Name”
                        combinedHeadValues               // “Head Value”
                    ]);
                });

                // 5. Draw the table
                dt.draw();
            }
            
             function render_assigned_user(users, assignedValues = []) {
                // 1. Static labels
                $("#model_title").html(`<strong class="txt-danger"></strong>Auditors List`);
                $("#user_type").text("Auditor Name");

                // 2. Grab the existing DataTable instance, then clear old rows
                const dt = $("#user_render_table").DataTable();
                dt.clear();

                // 3. Convert assignedValues → single comma-separated string
                const combinedHeadValues = (assignedValues || [])
                    .map(v => v.head_value?.trim())   // trim first
                    .filter(v => v) // keep only non-empty strings
                    .join(', ');

                // 4. Add one row per user (same head-value string for each)
                $.each(users || [], function (index, user) {
                    dt.row.add([
                        index + 1,                       // “#”
                        user.get_user_info?.name || '',  // “User Name”
                        combinedHeadValues               // “Head Value”
                    ]);
                });

                // 5. Draw the table
                dt.draw();

                $("#user_assigned_model").modal('show')
            }


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
                                "id": data_assigned_id
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
                $("#user_assigned_model").modal('show')
                // alert(data_assigned_id)

                $.ajax({
                    url: '{{ route('auditors.show') }}',
                    type: "POST",
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "data_assigned_id": data_assigned_id,
                        "user_type": "auditors"
                    },
                    success: function(response) {
                        console.log(response);
                        if (response.message == "Success") {
                            render_assigned_user(response.user_list, response.distinctValuesAssign);
                        }
                    }
                })
            })
        });
    </script>
@endsection
