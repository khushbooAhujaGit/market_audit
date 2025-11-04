@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Role <a href="{{route('roles.create')}}"><button class = "btn btn-primary float-end">Create</button></a></h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive theme-scrollbar">
                            <table class="display" id="roles">
                                <thead>
                                <tr>
                                    <th>Sno</th>
                                    <th>Role Name</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($roles as $role)
                                    <tr>
                                        <td>{{$loop->iteration}}</td>
                                        <td>{{$role->name}}</td>
                                        <td> <ul class="action">

                                                <li class="edit"> <a href="{{url('roles/'.$role->id. "/give-permissions")}}" class=" btn btn-sm btn-primary">Add / Edit Permissions</a></li>
                                                <li class="edit"> <a href="{{url('roles/'.$role->id. "/edit")}}"><i class="icon-pencil-alt"></i></a></li>
                                            <li class="delete" data-id="{{$role->id}}"><i class="icon-trash"></i></li>
                                            </ul>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-6 p-4">
                            {{$roles->links()}}
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
        let roles_table = $("#roles").DataTable();
        @if(session()->has('message'))
        Swal.fire({
            position: "top-center",
            icon: "success",
            title: "{{ session('message') }}",
            showConfirmButton: false,
            timer: 1000
        });
        @endif
        $("#roles").on("click", ".delete", function(event) {
            const role_id=$(this).data('id');
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
                        url:'{{route('role.destroy')}}',
                        type:"POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "id":role_id
                        },
                        success:function (response){
                            console.log(response)
                            if(response=="Success"){
                                roles_table.row(tar_row).remove().draw();
                                Swal.fire(
                                    'Deleted!',
                                    'Role has been deleted.',
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
