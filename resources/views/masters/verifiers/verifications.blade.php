@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">

        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>My Verifications Projects @if (!empty($activity_group_info))
                                <div class="btn-group">
                                    <button class="btn btn-warning text-black dropdown-toggle" type="button"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false">{{ $activity_group_info->activity_group_name }}</button>
                                    <ul class="dropdown-menu dropdown-block">
                                        @foreach ($activity_group_info->get_group_activities as $group_activity)
                                            <li><a class="dropdown-item"
                                                   href="{{ route('activityGroup.verification.view', ['pt' => $projectTemplateInfo->id, 'g' => $activity_group_info->id, 's' => $group_activity->sequence, 'a' => $group_activity->activity_id]) }}">{{ $group_activity->activityName->activity_name }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive theme-scrollbar">
                            <table class="display" id="projects_table">
                                <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Instance</th>
                                    @if (!empty($verification_data))
                                        @foreach ($verification_data[0]->getProjectTemplateData->getTemplate->getTemplateHeads as $template_heads)
                                            <th>{{ $template_heads->template_head_name }}</th>
                                        @endforeach
                                    @endif
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($verification_data as $projectdata)
                                    <tr>
                                        <td>
                                            <a href="{{ route('data_to_verify', ['r' => $projectdata->id, 'a' => $activity_info->id, 'g' => $activity_group_info->id ?? '', 'seq' => $projectdata->activity_sequence ?? 0]) }}">
                                                <i class="icon-eye text-secondary fs-5"></i>
                                            </a>
                                        </td>
                                        <td>
                                            @if (!empty($projectdata->activity_sequence) && $projectdata->activity_sequence > 0)
                                                <span class="badge bg-primary">Instance {{ $projectdata->activity_sequence + 1 }}</span>
                                            @else
                                                <span class="text-muted">Original</span>
                                            @endif
                                        </td>
                                        @foreach ($projectdata->templateNameValues as $project_row_data)
                                            <td>{{ $project_row_data['value'] }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
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
    </div>

@endsection

@section('scripts')
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            const projects_table = $("#projects_table").DataTable({
                paging: false
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
            $("#projects_table").on("click", ".delete", function(event) {
                const project_id = $(this).data('id');
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
                            url: '{{ route('project.destroy') }}',
                            type: "POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "id": project_id
                            },
                            success: function(response) {
                                if (response == "Success") {
                                    projects_table.row(tar_row).remove().draw();
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

        });
    </script>
@endsection
