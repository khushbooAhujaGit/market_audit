@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Add New Unit <a href="{{route('unit.list')}}"><button class = "btn btn-primary float-end">View Units</button></a></h4>
                    </div>
                    <div class="card-body">
                        <div class="horizontal-wizard-wrapper">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                        <div class="tab-pane fade show active" id="wizard-info" role="tabpanel" aria-labelledby="wizard-info-tab">
                                            <form method="POST" action="{{route('unit.store')}}" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate="">
                                                @csrf
                                                <div class="col-xl-4 col-sm-4">
                                                    <label class="form-label" for="company_id">Select Company</label>
                                                    <select class="form-select" id="company_id" name="company_id" required="">
                                                        <option selected="" disabled="" value="">Choose...</option>
                                                        @foreach($companies as $company)
                                                        <option value="{{$company->id}}" @if(old('company_id') == $company->id) selected @endif>{{$company->company_name}}</option>
                                                        @endforeach
                                                        </select>
                                                    @error('company_id')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                </div>
                                                <div class="col-xl-4 col-sm-4">
                                                    <label class="form-label" for="zone_id">Select Zone</label>
                                                    <select class="form-select" id="zone_id" name="zone_id" required="">
                                                        <option selected="" disabled="" value="">Choose...</option>
                                                        </select>
                                                    @error('zone_id')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                </div>
                                                <div class="col-xl-4 col-sm-4">
                                                    <label class="form-label" for="unit_name">Unit Name<span class="txt-danger">*</span></label>
                                                    <input class="form-control" id="unit_name" value="{{old('unit_name')}}" name="unit_name" type="text" placeholder="Enter unit name" required="">
                                                    @error('unit_name')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                </div>
                                                <div class="col-xl-6 col-sm-6">
                                                    <label class="form-label" for="unit_code">Unit Short Code<span class="txt-danger">*</span></label>
                                                    <input class="form-control" id="unit_code" value="{{old('unit_code')}}" name="unit_code" type="text" placeholder="Enter Short Code" required="">
                                                    @error('unit_code')
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
            function render_zones(zones){
                let zone_select = $("#zone_id");
                zone_select.empty();
                zone_select.append($('<option>').text('Choose...').attr('disabled', 'disabled').attr('selected', 'selected').val(''));
                $.each(zones, function (index, value){
                    zone_select.append($('<option>').text(value.zone_name).val(value.id));
                })
            }
            $("#company_id").change(function (e){
                const selectedCompanyId = $(this).val();
                if(selectedCompanyId){
                    $.ajax({
                        url:'{{route('get_company_zones')}}',
                        type:"POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "id":selectedCompanyId
                        },
                        success:function (response){
                            console.log(response)
                            if(response.message=="Success"){
                                render_zones(response.related_zones);
                            }
                        }
                    })
                }
            })
        })
    </script>
@endsection
