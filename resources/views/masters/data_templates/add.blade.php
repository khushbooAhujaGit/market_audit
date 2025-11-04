@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Add New Data Template <a href="{{route('download.TemplateName.Template')}}"><button class = "btn btn-primary ">Download Heads Template</button></a> <a href="{{route('templateName.list')}}"><button class = "btn btn-primary float-end">View Data Templates</button></a></h4>
                    </div>
                    <div class="card-body">
                        <div class="horizontal-wizard-wrapper">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                        <div class="tab-pane fade show active" id="wizard-info" role="tabpanel" aria-labelledby="wizard-info-tab">
                                            <form method="POST" action="{{route('templateName.store')}}" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate="">
                                                @csrf

                                                <div class="col-xl-6 col-sm-6">
                                                    <label class="form-label" for="template_name">Template Name<span class="txt-danger">*</span></label>
                                                    <input class="form-control" id="template_name" value="{{old('template_name')}}" name="template_name" type="text" placeholder="Enter Template name" required="">
                                                    @error('template_name')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                </div>
                                                <div class="col-xl-6 col-12">
                                                    <label class="form-label" for="heads_excel">Data Head Excel File<span class="txt-danger"></span></label>
                                                    <input class="form-control" id="heads_excel" name="heads_excel" type="file" required="" placeholder="">
                                                    @error('heads_excel')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                </div>
                                                <div class="col-12 text-end">
                                                    <button class="btn btn-primary">Add Now</button>
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
@section('scripts')
    <script>
        $(document).ready(function(){

        })
    </script>
@endsection
