@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Add New Company Type <a href="{{route('company_type.list')}}">
                                    <button class="btn btn-primary float-end">View Company Types</button>
                                </a></h4>
                        </div>
                        <div class="card-body">
                            <div class="horizontal-wizard-wrapper">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                            <div class="tab-pane fade show active" id="wizard-info" role="tabpanel"
                                                 aria-labelledby="wizard-info-tab">
                                                <form method="POST" action="{{route('company_type.store')}}"
                                                      enctype="multipart/form-data" class="row g-3 needs-validation"
                                                      novalidate="">
                                                    @csrf
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="company_type_name">Company Type Name<span
                                                                class="txt-danger">*</span></label>
                                                        <input class="form-control" id="company_type_name"
                                                               value="{{old('company_type_name')}}" name="company_type_name"
                                                               type="text" placeholder="Enter company type name" required="">
                                                        @error('company_type_name')
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
