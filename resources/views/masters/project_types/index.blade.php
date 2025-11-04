@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
    <div class="row">
        <!-- Zero Configuration  Starts-->
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    <h4>Project Types <a href="{{route('project_type.create')}}"><button class = "btn btn-primary float-end">Create</button></a></h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive theme-scrollbar">
                        <table class="display" id="companies">
                            <thead>
                            <tr>
                                <th>Sno</th>
                                <th>Project Type Name</th>
                                <th>Project Type PO</th>

                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($projectTypes as $projectType)
                            <tr>
                                <td>{{$loop->iteration}}</td>
                                <td>{{$projectType->project_type_name}}</td>
                                <td>{{$projectType->project_type_code}}</td>
                                <td>
                                    <ul class="action">
                                        <li class="edit"> <a href="{{route('project_type.edit', ["id"=>$projectType->id])}}"><i class="icon-pencil-alt"></i></a></li>
                                        <li class="delete" data-id="{{$projectType->id}}"><i class="icon-trash"></i></li>
                                    </ul>
                                </td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6 p-4">
                        {{$projectTypes->links()}}
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
          let project_type_table = $("#companies").DataTable({
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

            $("#companies").on("click", ".delete", function(event) {
                const project_type_id=$(this).data('id');
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
                            url:'{{route('project_type.destroy')}}',
                            type:"POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "id":project_type_id
                            },
                            success:function (response){
                                if(response=="Success"){
                                    project_type_table.row(tar_row).remove().draw();
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
