@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Project : {{optional($project)->project_name}}
                            {{--                            <a href="{{route('unit.create')}}"><button class = "btn btn-primary float-end">Create</button></a>--}}
                        </h4>
                        @if(isset($projectTemplateInfo))
                        <h4>{{$projectTemplateInfo->getTemplate->template_name}}</h4>
                        @endif

                    </div>
                    <div class="card-body">
                        <div class="table-responsive theme-scrollbar">
                            <table class="display" id="project_activities">
                                <thead>
                                <tr>
                                    <th>Sno</th>
                                    <th>Template Name</th>
                                    <th>Group Name</th>
                                    <th>Activity</th>
                                    <th>Sequence</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($userAssignedActivities as $userAssignedActivity)
                                    <tr @if($userAssignedActivity['is_master']) class="bg-success" @endif>
                                        <td @if($userAssignedActivity['is_master']) class="bg-success" @endif>{{$loop->iteration}}</td>
                                        <td>{{$userAssignedActivity['template_name']}}</td>
                                        <td>@if(isset($userAssignedActivity['group_name']))
                                                {{$userAssignedActivity['group_name']}}
                                            @else
                                                -
                                        @endif</td>
                                        <td>{{$userAssignedActivity['activity_name']}}</td>
                                        <td>
                                            @if(isset($userAssignedActivity['sequence']))
                                                {{$userAssignedActivity['sequence']}}
                                            @else
                                                -
                                            @endif
                                            </td>
                                        <td>
                                            <ul class="action">
                                                @if($userAssignedActivity['is_master'])
                                                <li class="view"> <a href="{{route('user.project_master.data', ['project'=>$project->id, 'template' =>$userAssignedActivity['template_name_id'], 'activity' => $userAssignedActivity['activity_id'], 'group_info' => $userAssignedActivity['group_id']])}}"><i class="icon-eye text-secondary fs-5"></i></a></li>
                                                @else
                                                <li class="view"> <a href="{{route('user.project_child.data', ['type'=>0, 'project'=>$project->id, 'template' =>$userAssignedActivity['template_name_id'], 'activity' => $userAssignedActivity['activity_id'], 'group_info' => $userAssignedActivity['group_id']])}}"><i class="icon-eye text-secondary fs-5"></i></a></li>
                                                @endif
                                            </ul>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div>
                                {{--                                {{$userProjects->links()}}--}}
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
        $(document).ready(function (){
            const project_activities = $("#project_activities").DataTable({
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
            $("#project_activities").on("click", ".delete", function(event) {
                const unit_id=$(this).data('id');
                const tar_row=$(this).closest('tr');
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
                            url:'{{route('unit.destroy')}}',
                            type:"POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "id":unit_id
                            },
                            success:function (response){
                                if(response=="Success"){
                                    project_activities.row(tar_row).remove().draw();
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
        })
    </script>
@endsection
