@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        @can('Add User')
                            <a href="{{route('users.export')}}">
                                <button class="btn btn-primary ms-2 float-end">Export</button>
                            </a>
                            <h4>Users <a href="{{ route('users.create') }}">
                                    <button class="btn btn-primary float-end">Add
                                        User
                                    </button>
                                </a></h4>
                        @endcan
                    </div>
                    <div class="card-body">
                        <div class="table-responsive theme-scrollbar">
                            <table class="display" id="users">
                                <thead>
                                <tr>
                                    <th>Sno</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Mobile</th>
                                    <th>Roles</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($users as $user)
                                    @if ($currentUserRole == 'Agency')
                                        @if ($user->is_agency_user == 1 || $user->getRoleNames()->first() == $currentUserRole)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $user->name }}</td>
                                                <td>{{ $user->email }}</td>
                                                <td>{{ $user->mobile }}</td>
                                                <td>
                                                    @if (!empty($user->getRoleNames()))
                                                        @foreach ($user->getRoleNames() as $rolename)
                                                            <label
                                                                class="badge bg-primary mx-1">{{ $rolename }}</label>
                                                        @endforeach
                                                    @endif
                                                </td>
                                                <td>
                                                    <ul class="action">

                                                        <li class="edit"><a
                                                                href="{{ route('users.edit', $user) }}"><i
                                                                    class="icon-pencil-alt"></i></a></li>
                                                        @can('Delete User')
                                                            @if (auth()->user()->id != $user->id)
                                                                <li class="delete" data-id="{{ $user->id }}"><i
                                                                        class="icon-trash"></i>
                                                                </li>
                                                            @endif
                                                        @endcan
                                                    </ul>
                                                </td>
                                            </tr>
                                        @endif
                                    @else
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $user->name }}</td>
                                            <td>{{ $user->email }}</td>
                                            <td>{{ $user->mobile }}</td>
                                            <td>
                                                @if (!empty($user->getRoleNames()))
                                                    @foreach ($user->getRoleNames() as $rolename)
                                                        <label
                                                            class="badge bg-primary mx-1">{{ $rolename }}</label>
                                                    @endforeach
                                                @endif
                                            </td>
                                            <td>
                                                <ul class="action">
                                                    <li class="edit"><a
                                                            href="{{ route('users.edit', $user) }}"><i
                                                                class="icon-pencil-alt"></i></a></li>
                                                    @can('Delete User')
                                                        <li class="delete" data-id="{{ $user->id }}"><i
                                                                class="icon-trash"></i></li>
                                                    @endcan
                                                </ul>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-6 p-4">
                            {{-- {{$roles->links()}} --}}
                        </div>
                    </div>
                </div>
            </div>
            <!-- Zero Configuration  Ends -->
        </div>
    </div>
@endsection
@section('scripts')
    <script>
        let users_table = $("#users").DataTable();
        @if (session()->has('message'))
        Swal.fire({
            position: "top-center",
            icon: "success",
            title: "{{ session('message') }}",
            showConfirmButton: false,
            timer: 1000
        });
        @endif
        $("#users").on("click", ".delete", function (event) {
            const user_id = $(this).data('id');
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
                        url: '{{ route('user.destroy') }}',
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "id": user_id
                        },
                        success: function (response) {
                            if (response == "Success") {
                                users_table.row(tar_row).remove().draw();
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
