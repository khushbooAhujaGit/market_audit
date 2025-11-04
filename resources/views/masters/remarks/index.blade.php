@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
    <div class="row">
        <!-- Zero Configuration  Starts-->
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    <h4>Remarks <a href="{{route('remark.create')}}"><button class = "btn btn-primary float-end">Create</button></a></h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive theme-scrollbar">
                        <table class="display" id="remarks_table">
                            <thead>
                            <tr>
                                <th>Sno</th>
                                <th>Project Name</th>
                                <th>Remark</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($remarks as $remark)
                            <tr>
                                <td>{{$loop->iteration}}</td>
                                <td>{{isset($remark->project)? $remark->project->project_name: ''}}</td>
                                <td>{{$remark->remark}}</td>
                                <td>@if($remark->status == 1)
                                    <span class="text-success">Active</span>
                                    @else
                                    <span class="text-danger">Un-Active</span>
                                    @endif
                                </td>
                                <td>
                                    <ul class="action">
                                        <li class="edit"> <a href="{{route('remark.edit', ["id"=>$remark->id])}}"><i class="icon-pencil-alt"></i></a></li>
                                        <li class="delete" data-id="{{$remark->id}}"><i class="icon-trash"></i></li>
                                    </ul>
                                </td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6 p-4">
                        {{$remarks->links()}}
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
          let remark_table = $("#remarks_table").DataTable({
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

            $("#remarks_table").on("click", ".delete", function(event) {
                const remark_id=$(this).data('id');
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
                            url:'{{route('remark.destroy')}}',
                            type:"POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "id":remark_id
                            },
                            success:function (response){
                                if(response=="Success"){
                                    remark_table.row(tar_row).remove().draw();
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
