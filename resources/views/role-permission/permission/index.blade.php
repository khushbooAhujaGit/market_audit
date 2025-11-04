@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Permissions <a href="{{route('permissions.create')}}"><button class = "btn btn-primary float-end">Create</button></a></h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive theme-scrollbar">
                            <table class="display" id="permissions">
                                <thead>
                                <tr>
                                    <th>Sno</th>
                                    <th>Permission Name</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($permissions as $permission)
                                    <tr>
                                        <td>{{$loop->iteration}}</td>
                                        <td>{{$permission->name}}</td>
                                        <td> <ul class="action"> <li class="edit"> <a href="{{url('permissions/'.$permission->id. "/edit")}}"><i class="icon-pencil-alt"></i></a></li>
                                            <li class="delete" data-id="{{$permission->id}}"><i class="icon-trash"></i></li>
                                            </ul>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-6 p-4">
                            {{$permissions->links()}}
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
        let permissions_table = $("#permissions").DataTable();
        @if(session()->has('message'))
        Swal.fire({
            position: "top-center",
            icon: "success",
            title: "{{ session('message') }}",
            showConfirmButton: false,
            timer: 1000
        });
        @endif
        $("#permissions").on("click", ".delete", function(event) {
            const permission_id=$(this).data('id');
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
                        url:'{{route('permission.destroy')}}',
                        type:"POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "id":permission_id
                        },
                        success:function (response){
                            console.log(response)
                            if(response=="Success"){
                                permissions_table.row(tar_row).remove().draw();
                                Swal.fire(
                                    'Deleted!',
                                    'Permission has been deleted.',
                                    'success'
                                )
                            }
                        }
                    })
                }
            })
        })
    </script>
@endsection
