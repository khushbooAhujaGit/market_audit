@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="row">
            <!-- Data Table Start -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        @if ($response == 'yes')
                            <h5>Project Name - {{ $projectTemplate->getProject->project_name }}</h5>
                            <h5>Template Name - {{ $projectTemplate->getTemplate->template_name }}</h5>
                            <a href="{{ route('project_template_data.export', $projectTemplate->id) }}">
                                <button class="btn btn-primary ms-3 float-end">Export Data</button>
                            </a>
                        @else
                            <h5>No Data Found</h5>
                        @endif
                    </div>

                    @if ($response == 'yes')
                        <div class="card-body">
                            <div class="table-responsive theme-scrollbar">
                                <table class="display" id="templates_table">
                                    <thead>
                                    <tr>
                                        <th>SNo</th>
                                        <th>Action</th>
                                        @foreach ($projectTemplate->getTemplate->getTemplateHeads as $templateHead)
                                            <th>{{ $templateHead->template_head_name }}</th>
                                        @endforeach
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @php
                                        $startSerial = ($projectTemplateDataGet->currentPage() - 1) * $projectTemplateDataGet->perPage();
                                    @endphp

                                    @foreach ($projectTemplateDataGet as $index => $projectTemplateData)
                                        <tr>
                                            <td></td> {{-- SNo will be populated by DataTables --}}
                                            <td>
                                                <a href="{{ route('project_data.edit', ['id' => $projectTemplateData->id]) }}">
                                                    <i class="icon-pencil-alt fs-5"></i>
                                                </a>
                                            </td>
                                            @foreach ($projectTemplateData->decoded_json as $value)
                                                <td>{{ $value }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                                <div class="mt-3">
                                    {{ $projectTemplateDataGet->links() }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            <!-- Data Table End -->
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function () {
            let startSerial = @json($startSerial);

            $('#templates_table').DataTable({
                paging: false, // Laravel handles pagination
                ordering: false,
                searching: false,
                columnDefs: [{
                    targets: 0,
                    render: function (data, type, row, meta) {
                        return startSerial + meta.row + 1;
                    }
                }]
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
        });
    </script>
@endsection

