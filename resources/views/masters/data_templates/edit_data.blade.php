@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h2>Edit Project Data</h2>

                            @if(session('success'))
                                <div id="update_message" class="alert alert-success">{{ session('success') }}</div>
                            @endif
                            @if($errors->any())
                                <div class="alert alert-danger">
                                    <ul>
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                        <div class="card-body">
                            <div class="table-card">
                                <form action="{{ route('project_data.update', ['id' => $related_values->id]) }}" method="POST">
                                    @csrf
                                    @foreach($templateHeadData as $headData)
                                        <div class="form-group">
                                            <label for="value_{{ $headData['id'] }}">{{$headData['template_head_name']}}</label>
                                            <input type="text" class="form-control" id="value_{{ $headData['id'] }}" name="values[{{ $loop->index }}][value]" value="{{ $headData['value'] }}">
                                            <input type="hidden" name="values[{{ $loop->index }}][id]" value="{{ $headData['id'] }}">
                                        </div>
                                    @endforeach

                                    <button type="submit" class="btn btn-primary mt-3">Update</button>
                                </form>
                            </div>


                            <div class="d-none">
                                <form method="POST" action="{{route('project_template_data.view')}}" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate="">
                                    @csrf
                                    <div class="col-xl-6 col-sm-6">
                                        <label class="form-label" for="project_id">Select Project</label>
                                        <select class="form-select" id="project_id" name="project_id" required="">
                                          <option value="{{$related_values->getProjectTemplateData->project_id}}">{{$related_values->getProjectTemplateData->project_id}}</option>

                                        </select>
                                        @error('project_id')
                                        <p class="text-red-500 text-xs mt-1">
                                            {{$message}}
                                        </p>
                                        @enderror
                                    </div>
                                    <div class="col-xl-6 col-sm-6">
                                        <label class="form-label" for="template_name_id">Select Data Template</label>
                                        <select class="form-select" id="template_name_id" name="template_name_id" required="">
                                            <option selected="" disabled="" value="">Choose...</option>
                                                <option value="{{$related_values->getProjectTemplateData->template_name_id}}" selected >{{$related_values->getProjectTemplateData->template_id}}</option>
                                        </select>
                                        @error('template_name_id')
                                        <p class="text-red-500 text-xs mt-1">
                                            {{$message}}
                                        </p>
                                        @enderror
                                    </div>

                                    <div class="col-12 text-end">
                                        <button class="btn btn-primary" id="view_project_btn">View Data</button>
                                    </div>
                                </form>

                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>

@endsection
@section('scripts')
    <script>
        $(document).ready(function () {
            @if(session('success'))
            setTimeout(function() {
                $('#update_message').fadeOut('slow'); // this is to slowly close the message of success
                $("#view_project_btn").click();
            }, 2000); // 3000 milliseconds = 3 seconds
            @endif
        })
    </script>
@endsection
