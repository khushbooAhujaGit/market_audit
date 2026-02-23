@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">

        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>My Verifications Projects </h4>

                    </div>
                    <div class="card-body">
                        <div class="table-responsive theme-scrollbar">
                            <table class="display" id="projects_table">
                                <thead>
                                    <tr>
                                        <th>Sno</th>
                                        <th>Project Name </th>
                                        <th>Template Name</th>
                                        <th>Activities</th>
                                        <th class="d-none" >Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($my_verifications as $my_verification)
                                        @if(!empty($my_verification->projectTemplateInfo->getProject))
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ isset($my_verification->projectTemplateInfo->getTemplate) ? $my_verification->projectTemplateInfo->getProject->project_name : '' }}
                                            </td>
                                            <td>{{ isset($my_verification->projectTemplateInfo->getTemplate) ? $my_verification->projectTemplateInfo->getTemplate->template_name : '' }}
                                            </td>
                                            <td>
                                                <ul>
                                                    @if (isset($my_verification->projectTemplateInfo) && $my_verification->projectTemplateInfo->activityType === 0)
                                                        <li><a
                                                                href="{{ route('activity.verification.view', ['pt' => $my_verification->projectTemplateInfo->id, 'a' => $my_verification->projectTemplateInfo->related_activity->id]) }}">{{ $my_verification->projectTemplateInfo->related_activity->activity_name }}</a>
                                                        </li>
                                                    @else
                                                        @if (isset($my_verification->projectTemplateInfo))
                                                        
                                                            @if(!empty($my_verification->projectTemplateInfo->activityGroup) && !empty($my_verification->projectTemplateInfo->activityGroup->get_group_activities))
                                                                @foreach ($my_verification->projectTemplateInfo->activityGroup->get_group_activities as $group_activity)
                                                                    <li><a
                                                                            href="{{ route('activityGroup.verification.view', ['pt' => $my_verification->projectTemplateInfo->id, 'g' => $my_verification->projectTemplateInfo->activityGroup->id, 's' => $group_activity->sequence, 'a' => $group_activity->activity_id]) }}">{{ optional($group_activity->activityName)->activity_name }}</a>
                                                                    </li>
                                                                @endforeach
                                                            @endif
                                                        @endif
                                                    @endif
                                                </ul>
                                            </td>
                                            <td class="d-none" >
                                                <ul class="action">
                                                    {{--                                                <li class="edit"> <a href="{{route('project.edit', ["id"=>$project->id])}}"><i class="icon-pencil-alt"></i></a></li> --}}
                                                    {{--                                                <li class="delete" data-id="{{$project->id}}"><i class="icon-trash"></i></li> --}}
                                                </ul>
                                            </td>
                                        </tr>
                                        @endif
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
                paging: true
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
