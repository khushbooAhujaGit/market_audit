@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">

    <div class="row">
        <!-- Zero Configuration  Starts-->
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    <h4>Activities having subjective question</h4>

                </div>
                <div class="card-body">
                    <div class="table-responsive theme-scrollbar">
                        <table class="display" id="activities_table">
                            <thead>
                            <tr>
                                <th>Sno</th>
{{--                                <th>Company</th>--}}
{{--                                <th>Zone</th>--}}
{{--                                <th>Unit</th>--}}
{{--                                <th>Project</th>--}}
                                <th>Activity</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($subjective_activities as $activity)
                            <tr>
                                <td>{{$loop->iteration}}</td>
                                <td>{{$activity->activity_name}}</td>
                                <td>
                                    <ul class="action">
                                        <li class="view-questions"> <a href="{{route('activities.question', ["activity_id"=>$activity->id])}}"><i class="icon-eye text-secondary fs-5"></i></a></li>

                                        <li class="edit"> <a href="{{route('activities.edit', ["id"=>$activity->id])}}"><i  class="icon-pencil-alt p-1"></i></a></li>
                                        <li class="delete" data-id="{{$activity->id}}"><i class="icon-trash"></i></li>
                                    </ul>
                                </td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div>
                            {{$subjective_activities->links()}}
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
            // Initialize DataTable
            var activities_table = $("#activities_table").DataTable({
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
            $("#activities_table").on("click", ".delete", function(event) {
                const activity_id=$(this).data('id');
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
                            url:'{{route('activities.destroy')}}',
                            type:"POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "id":activity_id
                            },
                            success:function (response){
                                console.log(response)
                                if(response=="Success"){
                                    activities_table.row(tar_row).remove().draw();
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
