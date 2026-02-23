@extends('template.layouts.simple.master')
@section('style')

    <style>
        .loader-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            /* Semi-transparent black backdrop */
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1050;
            /* Higher than Bootstrap modal (default is 1040) */
        }

        .large-spinner {
            width: 5rem;
            /* Adjust size as needed */
            height: 5rem;
            border-width: 0.4rem;
            /* Optional: Increase border thickness */
        }
    </style>
@endsection
@section('content')
    <div class="loader-backdrop">
        <div class="spinner-border text-light large-spinner" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    <div id="page-lock-overlay" style="
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background-color: rgba(0,0,0,0.5);
    z-index: 9999;
    cursor: not-allowed;
    pointer-events: all;
">
        <div style="position: absolute; top: 50%; left: 50%; color: white; transform: translate(-50%, -50%); font-size: 20px;">
            Please wait... Deletion in progress.
        </div>
    </div>
    <div class="page-body">

        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Projects
                            <a href="{{ route('project.export') }}">
                                <button class = "btn btn-primary ms-3">Export Data</button>
                            </a>
                            <a href="{{ route('project.create') }}">
                                <button class = "btn btn-primary float-end">Project Create</button>
                            </a>
                        </h4>

                    </div>
                    <div class="card-body">
                        <div class="table-responsive theme-scrollbar">
                            <table class="display" id="projects_table">
                                <thead>
                                    <tr>
                                        <th>Sno</th>
                                        <th>Company </th>
                                        <th>Zone</th>
                                        <th>Unit</th>
                                        <th>Project</th>
                                        <th>Project Type</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($projects as $project)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $project->getUnit->getZone->getCompany->company_name }}</td>
                                            <td>{{ $project->getUnit->getZone->zone_name }}</td>
                                            <td>{{ $project->getUnit->unit_name }}</td>
                                            <td>{{ $project->project_name }}</td>
                                            <td>{{ optional($project->getProjectType)->project_type_name }}</td>
                                            <td>
                                                <ul class="action">
                                                    <li class="edit"> <a
                                                            href="{{ route('project.edit', ['id' => $project->id]) }}"><i
                                                                class="icon-pencil-alt"></i></a></li>
                                                    <li class="delete" data-id="{{ $project->id }}"><i
                                                            class="icon-trash"></i></li>
                                                </ul>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div>
                              
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

        function lockPage() {
            $('#page-lock-overlay').show();

            // Disable F5 and Ctrl+R
            $(document).on('keydown.preventRefresh', function(e) {
                if ((e.which || e.keyCode) === 116 ||  // F5
                    (e.ctrlKey && e.which === 82)) {   // Ctrl + R
                    e.preventDefault();
                }
            });

            // Prevent close/refresh
            window.addEventListener('beforeunload', preventUnload);
        }

        function unlockPage() {
            $('#page-lock-overlay').hide();
            $(document).off('keydown.preventRefresh');
            window.removeEventListener('beforeunload', preventUnload);
        }

        function preventUnload(e) {
            e.preventDefault();
            e.returnValue = 'Deletion is in progress. Please wait.';
        }


        $(document).ready(function() {
            $('.loader-backdrop').addClass('d-none');
            // Initialize DataTable
            const projects_table = $("#projects_table").DataTable({
                paging: true
            });

            @if (session()->has('message'))
                Swal.fire({
                    position: "top-center",
                    icon: "success",
                    title: "{{ session('message') }}",
                    showConfirmButton: false,
                    timer: 1500
                });
            @endif
            $("#projects_table").on("click", ".delete", function(event) {
                const project_id = $(this).data('id');
                const tar_row = $(this).closest('tr');
                Swal.fire({
                    title: 'Are you sure?',
                    text: "All data related to this project will be permanently deleted. This action cannot be undone!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('.loader-backdrop').removeClass('d-none');
                        lockPage(); // 🚫 LOCK

                        $.ajax({
                            url: '{{ route('project.destroy') }}',
                            type: "POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "id": project_id
                            },
                            success: function(response) {
                                $('.loader-backdrop').addClass('d-none');
                                unlockPage(); // ✅ UNLOCK

                                if (response == "Success") {

                                    projects_table.row(tar_row).remove().draw();
                                    Swal.fire(
                                        'Deleted!',
                                        'Record has been deleted.',
                                        'success'
                                    );


                                }else{
                                    unlockPage(); // ✅ UNLOCK

                                    Swal.fire(
                                        'Warning!',
                                        'Something Went Wrong!.',
                                        'danger'
                                    );
                                }
                            }
                        })
                    }
                })
            })

        });
    </script>
@endsection
