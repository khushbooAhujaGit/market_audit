@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
    <div class="row">
        <!-- Zero Configuration  Starts-->
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    <h4>Units <a href="{{route('unit.create')}}"><button class = "btn btn-primary float-end">Create</button></a></h4>

                </div>
                <div class="card-body">
                    <div class="table-responsive theme-scrollbar">
                        <table class="display" id="units_table">
                            <thead>
                            <tr>
                                <th>Sno</th>
                                <th>Company </th>
                                <th>Zone</th>
                                <th>Unit</th>
                                <th>Unit Code</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($units as $unit)
                            <tr>
                                <td>{{$loop->iteration}}</td>
                                <td>{{$unit->getZone->getCompany->company_name}}</td>
                                <td>{{$unit->getZone->zone_name}}</td>
                                <td>{{$unit->unit_name}}</td>
                                <td>{{$unit->unit_code}}</td>
                                <td>
                                    <ul class="action">
                                        <li class="edit"> <a href="{{route('unit.edit', ["id"=>$unit->id])}}"><i class="icon-pencil-alt"></i></a></li>
                                        <li class="delete" data-id="{{$unit->id}}"><i class="icon-trash"></i></li>

                                    </ul>
                                </td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div>
                            {{$units->links()}}
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
            const units_table = $("#units_table").DataTable({
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
            $("#units_table").on("click", ".delete", function(event) {
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
                                    units_table.row(tar_row).remove().draw();
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
