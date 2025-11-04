@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Update Zone <a href="{{route('zone.list')}}"><button class = "btn btn-primary float-end">View Zone</button></a></h4>
                    </div>
                    <div class="card-body">
                        <div class="horizontal-wizard-wrapper">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                        <div class="tab-pane fade show active" id="wizard-info" role="tabpanel" aria-labelledby="wizard-info-tab">
                                            <form method="POST" action="{{route('zone.update', ['id' =>$zone->id])}}" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate="">
                                                @csrf
                                                @method("PUT")
                                                <div class="col-xl-6 col-sm-6">
                                                    <label class="form-label" for="company_id">Select Company</label>
                                                    <select class="form-select" id="company_id" name="company_id" required="">
                                                        <option selected="" disabled="" value="">Choose...</option>
                                                        @foreach($companies as $company)
                                                            <option value="{{$company->id}}" @if($zone->company_id == $company->id) selected @endif>{{$company->company_name}}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('company_id')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                </div>
                                                <div class="col-xl-6 col-sm-6">
                                                    <label class="form-label" for="zone_name">Zone Name<span class="txt-danger">*</span></label>
                                                    <input class="form-control" id="zone_name" value="{{$zone->zone_name}}" name="zone_name" type="text" placeholder="Enter zone_name" required="">
                                                    @error('zone_name')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                </div>
                                                <div class="col-xl-6 col-sm-6">
                                                    <label class="form-label" for="zone_code">Zone Short Code<span class="txt-danger">*</span></label>
                                                    <input class="form-control" id="zone_code" value="{{$zone->zone_code}}" name="zone_code" type="text" placeholder="Enter Short Code" required="">
                                                    @error('zone_code')
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
