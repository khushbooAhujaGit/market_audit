@extends('template.layouts.simple.master')
@section('content')

    <div class="page-body">
    <div class="row">
        <!-- Zero Configuration  Starts-->
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    <h4>Data Templates
                        <a href="{{ route('template.export') }}">
                                <button class = "btn btn-primary ms-3">Export Data</button>
                        </a>
                        <a href="{{route('templateName.create')}}"><button class = "btn btn-primary float-end">Template Create</button>
                        </a>
                    </h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive theme-scrollbar">
                        <table class="display" id="templates_table">
                            <thead>
                            <tr>
                                <th>Sno</th>
                                <th>Template Name</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($template_names as $template_name)
                                <tr>
                                    <td>{{$loop->iteration}}</td>
                                    <td>{{$template_name->template_name}}</td>
                                    <td>
                                        <ul class="action">
{{--                                            <li class="edit"> <a href="{{route('templateName.view_uploaded_data', ["id"=>$template_name->id])}}"><i class="icofont icofont-download"></i></a></li>--}}
                                            <li class="edit"> <a href="{{route('templateName.download', ["id"=>$template_name->id])}}"><i class="icofont icofont-download"></i></a></li>
                                            <li class="edit"> <a href="{{route('templateName.edit', ["id"=>$template_name->id])}}"><i class="icon-pencil-alt"></i></a></li>
                                            <li class="delete" data-id="{{$template_name->id}}"><i class="icon-trash"></i></li>
                                        </ul>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div>
                            {{$template_names->links()}}
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
            const templates_table = $("#templates_table").DataTable({
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
            $("#templates_table").on("click", ".delete", function(event) {
                const template_name_id=$(this).data('id');
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
                            url:'{{route('templateName.destroy')}}',
                            type:"POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "id":template_name_id
                            },
                            success:function (response){
                                if(response=="Success"){
                                    templates_table.row(tar_row).remove().draw();
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
