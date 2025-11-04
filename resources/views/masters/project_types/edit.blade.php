@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">

    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Edit Project Type <a href="{{route('project_type.list')}}"><button class = "btn btn-primary float-end">View Project Types</button></a></h4>
                    </div>
                    <div class="card-body">
                        <div class="horizontal-wizard-wrapper">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                        <div class="tab-pane fade show active" id="wizard-info" role="tabpanel" aria-labelledby="wizard-info-tab">
                                            <form method="POST" action="{{route('project_type.update', ['id' =>$projectType->id])}}" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate="">
                                                @csrf
                                                @method("PUT")
                                                <div class="col-xl-6 col-sm-6">
                                                    <label class="form-label" for="project_type_name">Project Type Name<span class="txt-danger">*</span></label>
                                                    <input class="form-control" id="project_type_name" value="{{$projectType->project_type_name}}" name="project_type_name" type="text" placeholder="Enter Project type name" required="">
                                                    @error('project_type_name')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                </div>
                                                <div class="col-xl-6 col-sm-6">
                                                    <label class="form-label" for="project_type_code">Project Type Code<span
                                                            class="txt-danger">*</span></label>
                                                    <input class="form-control" id="project_type_name"
                                                           value="{{$projectType->project_type_code}}"  name="project_type_code"
                                                           type="text" placeholder="Enter Project type code" required="">
                                                    @error('project_type_code')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                </div>

                                                <div class="col-12 text-end">
                                                    <button class="btn btn-primary">Update Now</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection
