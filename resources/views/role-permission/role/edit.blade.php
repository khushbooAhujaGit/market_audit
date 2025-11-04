@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Edit Role <a href="{{url('roles')}}">
                                    <button class="btn btn-primary float-end">Back</button>
                                </a></h4>
                        </div>
                        <div class="card-body">
                            <div class="horizontal-wizard-wrapper">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                            <div class="tab-pane fade show active" id="wizard-info" role="tabpanel"
                                                 aria-labelledby="wizard-info-tab">
                                                <form method="POST" action="{{ route('roles.update', $role->id) }}"
                                                      enctype="multipart/form-data" class="row g-3 needs-validation"
                                                      novalidate="">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="company_type_name">Role Name<span
                                                                class="txt-danger">*</span></label>
                                                        <input class="form-control" id="name"
                                                               value="{{$role->name}}" name="name"
                                                               type="text" placeholder="Enter Role name" required="">
                                                        @error('name')
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
