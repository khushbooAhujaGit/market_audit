@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Add New Activity <a href="{{route('activityTemplate.download')}}"><button class = "btn btn-primary ">Download Activity Template</button></a> <a href="{{route('activities.list')}}"><button class = "btn btn-primary float-end">View Activities</button></a></h4>
                    </div>
                    <div class="card-body">
                        <div class="horizontal-wizard-wrapper">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                        <div class="tab-pane fade show active" id="wizard-info" role="tabpanel" aria-labelledby="wizard-info-tab">
                                            <form method="POST" action="{{route('activities.store')}}" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate="">
                                                @csrf
                                                <div class="col-xl-6 col-sm-6">
                                                    <label class="form-label" for="activity_name">Activity Name<span class="txt-danger">*</span></label>
                                                    <input class="form-control" id="activity_name" value="{{old('activity_name')}}" name="activity_name" type="text" placeholder="Enter Activity name" required="">
                                                    @error('activity_name')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                </div>
                                                <div class="col-xl-6 col-12">
                                                    <label class="form-label" for="questions_excel">Excel File<span class="txt-danger"></span></label>
                                                    <input class="form-control" id="questions_excel" name="questions_excel" type="file" required="" placeholder="">
                                                    @error('questions_excel')
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

