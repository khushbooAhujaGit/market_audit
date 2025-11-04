@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
    <div class="row">
        <!-- Zero Configuration  Starts-->

        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    @if($response == "yes")

                    <h5>Project Name - {{$projectTemplate->getProject->project_name}} </h5>
                    <h5>Template Name - {{$projectTemplate->getTemplate->template_name}}</h5>
                        @else
                        <h5>No Data Found</h5>
                    @endif
                </div>
                @if($response == "yes")
                <div class="card-body">
                    <div class="table-responsive theme-scrollbar">
                        <table class="display" id="templates_table">
                            <thead>
                            <tr>
                                <th>SNo</th>
                                <th>Action</th>
                                @foreach($projectTemplate->getTemplate->getTemplateHeads as $templateHead)
                                    <th>{{$templateHead->template_head_name}}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @php
                            $indexCounter = 1;
                                $templateHeadsCount = count($projectTemplate->getTemplate->getTemplateHeads);
                                $dataCount = count($projectTemplate->getProjectTemplateData);
                                $counter = 0;
                            @endphp

                            @foreach($projectTemplate->getProjectTemplateData as $projectTemplateData)
                                @if($counter % $templateHeadsCount === 0)
                                    <tr>
                                        <td></td>
                                        <td>
                                            <a href="{{ route('project_data.edit', ['id'=>$projectTemplateData->row_id]) }}"><i
                                                    class="icon-pencil-alt fs-5"></i></a>
                                        </td>

                                        @endif

                                        <td>{{ $projectTemplateData->value }}</td>

                                        @if($counter % $templateHeadsCount === ($templateHeadsCount - 1) || $counter === ($dataCount - 1))
                                    </tr>
                                @endif

                                @php
                                    $counter++;
                                    $indexCounter++;
                                @endphp
                            @endforeach
                            </tbody>
                        </table>
                        <div>
{{--                            {{$template_names->links()}}--}}
                        </div>
                    </div>
                </div>
                @endif
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
            var templates_table = $("#templates_table").DataTable({
                paging: true, // Enable pagination
                pageLength: 25, // Set the number of rows per page to 25
                columnDefs: [
                    {
                        targets: 0, // Target the first column
                        render: function(data, type, row, meta) {
                            // Calculate the serial number based on the row index
                            var serialNumber = meta.row + 1;
                            return serialNumber;
                        }
                    }
                ]
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
        });
    </script>
@endsection
