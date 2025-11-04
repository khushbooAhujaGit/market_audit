@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Add New Company <a href="{{route('companies.list')}}"><button class = "btn btn-primary float-end">View Companies</button></a></h4>
                    </div>
                    <div class="card-body">
                        <div class="horizontal-wizard-wrapper">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                        <div class="tab-pane fade show active" id="wizard-info" role="tabpanel" aria-labelledby="wizard-info-tab">
                                            <form method="POST" action="{{route('company.update', ['id' =>$company->id])}}" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate="">
                                                @csrf
                                                @method("PUT")
                                                <div class="col-xl-6 col-6">
                                                    <label class="form-label" for="company_type">Company Type<span class="txt-danger"></span></label>
                                                    <select name="company_type" class="form-select" id="company_type">
                                                        <option value="">Select Company --</option>
                                                        @foreach($company_types as $company_type)
                                                            <option value="{{$company_type->id}}" @if($company->company_type==$company_type->id) selected @endif>{{$company_type->company_type_name}}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('company_type')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                </div>
                                                <div class="col-xl-6 col-sm-6">
                                                    <label class="form-label" for="company_name">Company Name<span class="txt-danger">*</span></label>
                                                    <input class="form-control" id="company_name" value="{{$company->company_name}}" name="company_name" type="text" placeholder="Enter company_name" required="">
                                                    @error('company_name')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                </div>
                                                <div class="col-xl-6 col-sm-6">
                                                    <label class="form-label" for="company_code">Company Short Code<span class="txt-danger">*</span></label>
                                                    <input class="form-control" id="company_code" value="{{$company->company_code}}" name="company_code" type="text" placeholder="Enter Short Code" required="">
                                                    @error('company_code')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                </div>

                                                <div class="col-xl-6 col-sm-6">
                                                    <label class="form-label" for="address">Address<span class="txt-danger"></span></label>
                                                    <input class="form-control" id="address" name="address" value="{{$company->address}}" type="text" placeholder="Enter Address" required="">
                                                    @error('address')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                </div>

                                                <div class="col-xl-6 col-12">
                                                    <label class="form-label" for="city">City<span class="txt-danger"></span></label>
                                                    <input class="form-control" id="city" name="city" value="{{$company->city}}" type="text" required="" placeholder="">
                                                    @error('city')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                </div>
                                                <div class="col-xl-6 col-12">
                                                    <label class="form-label" for="district">District<span class="txt-danger"></span></label>
                                                    <input class="form-control" id="district" name="district" value="{{$company->district}}" type="text" required="" placeholder="">
                                                    @error('district')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                </div>

                                                <div class="col-xl-6 col-sm-4">
                                                    <label class="form-label" for="state">State</label>
                                                    <select class="form-select" id="state" name="state" required="">
                                                        <option selected="" disabled="" value="">Choose...</option>
                                                        <option value="">Select state</option>
                                                        <option value="Andaman and Nicobar Islands" @if($company->state =="Andaman and Nicobar Islands") selected @endif >Andaman and Nicobar Islands</option>
                                                        <option value="Andhra Pradesh" @if($company->state =="ndhra Pradesh") selected @endif>Andhra Pradesh</option>
                                                        <option value="Arunachal Pradesh" @if($company->state =="Arunachal Pradesh") selected @endif>Arunachal Pradesh</option>
                                                        <option value="Assam" @if($company->state =="Assam") selected @endif>Assam</option>
                                                        <option value="Bihar" @if($company->state =="Bihar") selected @endif>Bihar</option>
                                                        <option value="Chandigarh" @if($company->state =="Chandigarh") selected @endif>Chandigarh</option>
                                                        <option value="Chhattisgarh" @if($company->state =="Chhattisgarh") selected @endif>Chhattisgarh</option>
                                                        <option value="Dadra and Nagar Haveli" @if($company->state =="Dadra and Nagar Haveli") selected @endif>Dadra and Nagar Haveli</option>
                                                        <option value="Daman and Diu" @if($company->state =="Daman and Diu") selected @endif>Daman and Diu</option>
                                                        <option value="Delhi" @if($company->state =="Delhi") selected @endif>Delhi</option>
                                                        <option value="Goa" @if($company->state =="Goa") selected @endif>Goa</option>
                                                        <option value="Gujarat" @if($company->state =="Gujarat") selected @endif>Gujarat</option>
                                                        <option value="Haryana" @if($company->state =="Haryana") selected @endif>Haryana</option>
                                                        <option value="Himachal Pradesh" @if($company->state =="Himachal Pradesh") selected @endif>Himachal Pradesh</option>
                                                        <option value="Jammu and Kashmir" @if($company->state =="Jammu and Kashmir") selected @endif>Jammu and Kashmir</option>
                                                        <option value="Jharkhand" @if($company->state =="Jharkhand") selected @endif>Jharkhand</option>
                                                        <option value="Karnataka" @if($company->state =="Karnataka") selected @endif>Karnataka</option>
                                                        <option value="Kerala" @if($company->state =="Kerala") selected @endif>Kerala</option>
                                                        <option value="Ladakh" @if($company->state =="Ladakh") selected @endif>Ladakh</option>
                                                        <option value="Lakshadweep" @if($company->state =="Lakshadweep") selected @endif>Lakshadweep</option>
                                                        <option value="Madhya Pradesh" @if($company->state =="Madhya Pradesh") selected @endif>Madhya Pradesh</option>
                                                        <option value="Maharashtra" @if($company->state =="Maharashtra") selected @endif>Maharashtra</option>
                                                        <option value="Manipur" @if($company->state =="Manipur") selected @endif>Manipur</option>
                                                        <option value="Meghalaya" @if($company->state =="Meghalaya") selected @endif>Meghalaya</option>
                                                        <option value="Mizoram" @if($company->state =="Mizoram") selected @endif>Mizoram</option>
                                                        <option value="Nagaland" @if($company->state =="Nagaland") selected @endif>Nagaland</option>
                                                        <option value="Odisha" @if($company->state =="Odisha") selected @endif>Odisha</option>
                                                        <option value="Puducherry" @if($company->state =="Puducherry") selected @endif>Puducherry</option>
                                                        <option value="Punjab" @if($company->state =="Punjab") selected @endif>Punjab</option>
                                                        <option value="Rajasthan" @if($company->state =="Rajasthan") selected @endif>Rajasthan</option>
                                                        <option value="Sikkim" @if($company->state =="Sikkim") selected @endif>Sikkim</option>
                                                        <option value="Tamil Nadu" @if($company->state =="Tamil Nadu") selected @endif>Tamil Nadu</option>
                                                        <option value="Telangana" @if($company->state =="Telangana") selected @endif>Telangana</option>
                                                        <option value="Tripura" @if($company->state =="Tripura") selected @endif>Tripura</option>
                                                        <option value="Uttar Pradesh" @if($company->state =="Uttar Pradesh") selected @endif>Uttar Pradesh</option>
                                                        <option value="Uttarakhand" @if($company->state =="Uttarakhand") selected @endif>Uttarakhand</option>
                                                        <option value="West Bengal" @if($company->state =="West Bengal") selected @endif>West Bengal</option>
                                                    </select>
                                                    <div class="invalid-feedback">Please select a valid state.</div>
                                                    @error('state')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                </div>
                                                <div class="col-xl-6 col-12">
                                                    <label class="form-label" for="image">Logo<span class="txt-danger"></span></label>
                                                    <input class="form-control" id="image" name="image" type="file" placeholder="">
                                                    @error('image')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                    <div class="col-xl-4 col-sm-6 mt-4">
                                                        <img class="img-fluid " src="{{url('assets/images/company_images/'.$company->image)}}" height="50%" width="50%" alt="">
                                                        {{--                                                    <img class="img-thumbnail" src="{{ url('assets/'.$company->image)}}" height="100%" width="100%" alt="Business Image">--}}
                                                    </div>
                                                </div>

                                                {{--                                                <div class="col-xl-3 col-sm-4">--}}
                                                {{--                                                    <label class="form-label" for="custom-zipcode">Zip Code</label>--}}
                                                {{--                                                    <input class="form-control" id="custom-zipcode" type="text" required="">--}}
                                                {{--                                                    <div class="invalid-feedback">Please provide a valid zip.</div>--}}
                                                {{--                                                </div>--}}
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
