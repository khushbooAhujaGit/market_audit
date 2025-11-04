@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
    <div class="row">
        <!-- Zero Configuration  Starts-->
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    <h4>Companies <a href="{{route('company.create')}}"><button class = "btn btn-primary float-end">Create</button></a></h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive theme-scrollbar">
                        <table class="display" id="companies">
                            <thead>
                            <tr>
                                <th>Sno</th>
                                <th>Company Type</th>
                                <th>Company Name</th>
                                <th>Company Code</th>
                                <th>Address</th>
                                <th>City</th>
                                <th>District</th>
                                <th>State</th>
                                <th>Logo</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($companies as $company)
                            <tr>
                                <td>{{$loop->iteration}}</td>
                                <td>{{$company->getCompanyTypeInfo->company_type_name}}</td>
                                <td>{{$company->company_name}}</td>
                                <td>{{$company->company_code}}</td>
                                <td>{{$company->address}}</td>
                                <td>{{$company->city}}</td>
                                <td>{{$company->district}}</td>
                                <td>{{$company->state}}</td>
                                <td> <img class="img-fluid table-avtar" src="{{ url('assets/images/company_images/'.$company->image)}}" alt=""></td>
                                <td>
                                    <ul class="action">
                                        <li class="edit"> <a href="{{route('company.edit', ["id"=>$company->id])}}"><i class="icon-pencil-alt"></i></a></li>
                                        <li class="delete" data-id="{{$company->id}}"><i class="icon-trash"></i></li>
                                    </ul>
                                </td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6 p-4">
                        {{$companies->links()}}
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
          let company_table = $("#companies").DataTable({
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
                const company_id=$(this).data('id');
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
                            url:'{{route('company.destroy')}}',
                            type:"POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "id":company_id
                            },
                            success:function (response){
                                console.log(response)
                                if(response=="Success"){
                                    company_table.row(tar_row).remove().draw();
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
