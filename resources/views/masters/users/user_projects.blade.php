@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>My Projects
                            {{--                            <a href="{{route('unit.create')}}"><button class = "btn btn-primary float-end">Create</button></a>--}}
                        </h4>

                    </div>
                    <div class="card-body">
                        <div class="table-responsive theme-scrollbar">
                            <table class="display" id="units_table">
                                <thead>
                                <tr>
                                    <th>Sno</th>
                                    <th>Project</th>
                                    <th>Unit</th>
                                    <th>Zone</th>
                                    <th>Company</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($userProjects as $userProject)
                                    <tr>
                                        <td>{{$loop->iteration}}</td>
                                        <td>{{$userProject->project_name}}</td>
                                        <td>{{optional($userProject->getUnit)->unit_name}}</td>
                                        <td>{{optional($userProject->getUnit->getZone)->zone_name}}</td>
                                        <td>{{optional($userProject->getUnit->getZone->getCompany)->company_name}}</td>
                                        <td>
                                            <ul class="action">
                                                <li class="view"> <a href="{{route('user.project_master.data', ['project'=>$userProject->id])}}"><i class="icon-eye text-secondary fs-5"></i></a></li>
                                            </ul>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div>
                                {{--                                {{$userProjects->links()}}--}}
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
