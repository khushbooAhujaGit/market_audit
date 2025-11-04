@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Create Activity Group <a href="{{route('activityGroup.create')}}"><button class = "btn btn-primary float-end">Create Activity Group</button></a></h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive theme-scrollbar">
                            <table class="display" id="activity_groups">
                                <thead>
                                <tr>
                                    <th>Sno</th>
                                    <th>Group Name</th>
                                    <th>Total Activities</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($activity_groups as $activity_group)
                                    <tr>
                                        <td>{{$loop->iteration}}</td>
                                        <td>{{$activity_group->activity_group_name}}</td>
                                        <td>{{count($activity_group->get_group_activities)}}</td>
                                        <td>
                                            <ul class="action">
                                                <li class="edit"> <a href="{{route('activity.group.edit', ["id"=>$activity_group->id])}}"><i class="icon-pencil-alt"></i></a></li>
                                                <li class="delete" data-id="{{$activity_group->id}}"><i class="icon-trash"></i></li>

                                            </ul>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-6 p-4">
                            {{$activity_groups->links()}}
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
            let activity_group_table = $("#activity_groups").DataTable({
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

            $("#activity_groups").on("click", ".delete", function(event) {
                const activity_group_id=$(this).data('id');
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
                            url:'{{route('activity_group.destroy')}}',
                            type:"POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "id":activity_group_id
                            },
                            success:function (response){
                                console.log(response)
                                if(response=="Success"){
                                    activity_group_table.row(tar_row).remove().draw();
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
