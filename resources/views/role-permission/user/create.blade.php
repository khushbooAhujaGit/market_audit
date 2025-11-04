@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Create User <a href="{{ url('users') }}">
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
                                                <form method="POST" action="{{ url('users') }}"
                                                    enctype="multipart/form-data" class="row g-3 needs-validation"
                                                    novalidate="">
                                                    @csrf
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="name">Name<span
                                                                class="txt-danger">*</span></label>
                                                        <input class="form-control" id="name"
                                                            value="{{ old('name') }}" name="name" type="text"
                                                            placeholder="Enter name" required="">
                                                        @error('name')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="email">Email<span
                                                                class="txt-danger">*</span></label>
                                                        <input class="form-control" id="email"
                                                            value="{{ old('email') }}" name="email" type="email"
                                                            placeholder="Enter email" required="">
                                                        @error('email')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="mobile">Mobile Number<span
                                                                class="txt-danger">*</span></label>
                                                        <input class="form-control" id="mobile"
                                                            value="{{ old('mobile') }}" name="mobile" type="number"
                                                            placeholder="Enter mobile number" required="">
                                                        @error('mobile')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="password">Enter password<span
                                                                class="txt-danger">*</span></label>
                                                        <input class="form-control" id="password"
                                                            value="{{ old('password') }}" name="password" type="password"
                                                            placeholder="Enter password" required="">
                                                        @error('password')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="address1">Enter Address 1<span
                                                                class="txt-danger">*</span></label>
                                                        <input class="form-control" id="address1"
                                                            value="{{ old('address1') }}" name="address1" type="text"
                                                            placeholder="Enter address1">
                                                        @error('address1')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="address2">Enter Address 2<span
                                                                class="txt-danger"></span></label>
                                                        <input class="form-control" id="address2"
                                                            value="{{ old('address2') }}" name="address2" type="text"
                                                            placeholder="Enter address2">
                                                        @error('address2')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="city">City<span
                                                                class="txt-danger"></span></label>
                                                        <input class="form-control" id="city"
                                                            value="{{ old('city') }}" name="city" type="text"
                                                            placeholder="Enter city">
                                                        @error('city')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="district">District<span
                                                                class="txt-danger"></span></label>
                                                        <input class="form-control" id="district"
                                                            value="{{ old('district') }}" name="district" type="text"
                                                            placeholder="Enter district">
                                                        @error('district')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="pincode">Pincode<span
                                                                class="txt-danger"></span></label>
                                                        <input class="form-control" id="pincode"
                                                            value="{{ old('pincode') }}" name="pincode" type="number"
                                                            placeholder="Enter pincode">
                                                        @error('pincode')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="state">State</label>
                                                        <select class="form-select" id="state" name="state">
                                                            <option selected="" disabled="" value="">
                                                                Choose...</option>
                                                            <option value="">Select state</option>
                                                            <option value="Andaman and Nicobar Islands"
                                                                @if (old('state') == 'Andaman and Nicobar Islands') selected @endif>Andaman
                                                                and Nicobar Islands</option>
                                                            <option value="Andhra Pradesh"
                                                                @if (old('state') == 'ndhra Pradesh') selected @endif>Andhra
                                                                Pradesh</option>
                                                            <option value="Arunachal Pradesh"
                                                                @if (old('state') == 'Arunachal Pradesh') selected @endif>Arunachal
                                                                Pradesh</option>
                                                            <option value="Assam"
                                                                @if (old('state') == 'Assam') selected @endif>Assam
                                                            </option>
                                                            <option value="Bihar"
                                                                @if (old('state') == 'Bihar') selected @endif>Bihar
                                                            </option>
                                                            <option value="Chandigarh"
                                                                @if (old('state') == 'Chandigarh') selected @endif>Chandigarh
                                                            </option>
                                                            <option value="Chhattisgarh"
                                                                @if (old('state') == 'Chhattisgarh') selected @endif>
                                                                Chhattisgarh</option>
                                                            <option value="Dadra and Nagar Haveli"
                                                                @if (old('state') == 'Dadra and Nagar Haveli') selected @endif>Dadra and
                                                                Nagar Haveli</option>
                                                            <option value="Daman and Diu"
                                                                @if (old('state') == 'Daman and Diu') selected @endif>Daman and
                                                                Diu</option>
                                                            <option value="Delhi"
                                                                @if (old('state') == 'Delhi') selected @endif>Delhi
                                                            </option>
                                                            <option value="Goa"
                                                                @if (old('state') == 'Goa') selected @endif>Goa
                                                            </option>
                                                            <option value="Gujarat"
                                                                @if (old('state') == 'Gujarat') selected @endif>Gujarat
                                                            </option>
                                                            <option value="Haryana"
                                                                @if (old('state') == 'Haryana') selected @endif>Haryana
                                                            </option>
                                                            <option value="Himachal Pradesh"
                                                                @if (old('state') == 'Himachal Pradesh') selected @endif>Himachal
                                                                Pradesh</option>
                                                            <option value="Jammu and Kashmir"
                                                                @if (old('state') == 'Jammu and Kashmir') selected @endif>Jammu
                                                                and Kashmir</option>
                                                            <option value="Jharkhand"
                                                                @if (old('state') == 'Jharkhand') selected @endif>
                                                                Jharkhand</option>
                                                            <option value="Karnataka"
                                                                @if (old('state') == 'Karnataka') selected @endif>
                                                                Karnataka</option>
                                                            <option value="Kerala"
                                                                @if (old('state') == 'Kerala') selected @endif>Kerala
                                                            </option>
                                                            <option value="Ladakh"
                                                                @if (old('state') == 'Ladakh') selected @endif>Ladakh
                                                            </option>
                                                            <option value="Lakshadweep"
                                                                @if (old('state') == 'Lakshadweep') selected @endif>
                                                                Lakshadweep</option>
                                                            <option value="Madhya Pradesh"
                                                                @if (old('state') == 'Madhya Pradesh') selected @endif>Madhya
                                                                Pradesh</option>
                                                            <option value="Maharashtra"
                                                                @if (old('state') == 'Maharashtra') selected @endif>
                                                                Maharashtra</option>
                                                            <option value="Manipur"
                                                                @if (old('state') == 'Manipur') selected @endif>Manipur
                                                            </option>
                                                            <option value="Meghalaya"
                                                                @if (old('state') == 'Meghalaya') selected @endif>
                                                                Meghalaya</option>
                                                            <option value="Mizoram"
                                                                @if (old('state') == 'Mizoram') selected @endif>Mizoram
                                                            </option>
                                                            <option value="Nagaland"
                                                                @if (old('state') == 'Nagaland') selected @endif>Nagaland
                                                            </option>
                                                            <option value="Odisha"
                                                                @if (old('state') == 'Odisha') selected @endif>Odisha
                                                            </option>
                                                            <option value="Puducherry"
                                                                @if (old('state') == 'Puducherry') selected @endif>
                                                                Puducherry</option>
                                                            <option value="Punjab"
                                                                @if (old('state') == 'Punjab') selected @endif>Punjab
                                                            </option>
                                                            <option value="Rajasthan"
                                                                @if (old('state') == 'Rajasthan') selected @endif>
                                                                Rajasthan</option>
                                                            <option value="Sikkim"
                                                                @if (old('state') == 'Sikkim') selected @endif>Sikkim
                                                            </option>
                                                            <option value="Tamil Nadu"
                                                                @if (old('state') == 'Tamil Nadu') selected @endif>Tamil
                                                                Nadu</option>
                                                            <option value="Telangana"
                                                                @if (old('state') == 'Telangana') selected @endif>
                                                                Telangana</option>
                                                            <option value="Tripura"
                                                                @if (old('state') == 'Tripura') selected @endif>Tripura
                                                            </option>
                                                            <option value="Uttar Pradesh"
                                                                @if (old('state') == 'Uttar Pradesh') selected @endif>Uttar
                                                                Pradesh</option>
                                                            <option value="Uttarakhand"
                                                                @if (old('state') == 'Uttarakhand') selected @endif>
                                                                Uttarakhand</option>
                                                            <option value="West Bengal"
                                                                @if (old('state') == 'West Bengal') selected @endif>West
                                                                Bengal</option>
                                                        </select>
                                                        <div class="invalid-feedback">Please select a valid state.</div>
                                                        @error('state')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>

                                                    {{-- //khushboo 31-03-2025 --}}
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="roles">Role</label>
                                                        <select class="form-select" id="roles" name="roles"
                                                            required="" >
                                                            @foreach ($roles as $role)
                                                                @if ($currentUserRole == 'Agency')
                                                                    @if ($role->name == 'Auditor' || $role->name == 'Verifier')
                                                                        <option value="{{ $role->name }}"
                                                                            @if (is_array(old('role')) && in_array($role->id, old('role'))) selected @endif>
                                                                            {{ $role->name }}
                                                                        </option>
                                                                    @endif
                                                                @else
                                                                    <option value="{{ $role->name }}"
                                                                        @if (is_array(old('role')) && in_array($role->id, old('role'))) selected @endif>
                                                                        {{ $role->name }}
                                                                    </option>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-xl-6 col-sm-6 d-none" id="AgencyDiv" >
                                                        <label class="form-label" for="roles">Select Agency</label>
                                                        <select class="form-select agency_select" id="agency_select"
                                                            name="agency_select" >
                                                            <option></option>
                                                            @foreach ($agencies as $agency)
                                                                <option value="{{ $agency->id }}">{{ $agency->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    {{-- //khushboo 31-03-2025 --}}

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
        $(document).ready(function() {
            $("#roles").select2();
            $("#agency_select").select2({
                placeholder: "Select Role",
                allowClear: true
            });

            $('#roles').on('change', function (){

                if($(this).val() == 'Auditor' || $(this).val() == 'Verifier'){
                    $('#AgencyDiv').removeClass('d-none');
                }else{
                    $('#AgencyDiv').addClass('d-none');
                }

            })
            if($('#roles').val() == 'Auditor' || $('#roles').val() == 'Verifier'){
                $('#AgencyDiv').removeClass('d-none');
            }else{
                $('#AgencyDiv').addClass('d-none');
            }

        })
    </script>
@endsection
