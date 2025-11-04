@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Update Template <a href="{{route('template.list')}}"><button class = "btn btn-primary float-end">View Template</button></a></h4>
                    </div>
                    <div class="card-body">
                        <div class="horizontal-wizard-wrapper">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                        <div class="tab-pane fade show active" id="wizard-info" role="tabpanel" aria-labelledby="wizard-info-tab">
                                            <form method="POST" action="{{route('template.update', ['id' =>$template->id])}}" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate="">
                                                @csrf
                                                @method("PUT")
{{--                                                <div class="col-xl-6 col-sm-6">--}}
{{--                                                    <label class="form-label" for="company_id">Select Company</label>--}}
{{--                                                    <select class="form-select" id="company_id" name="company_id" required="">--}}
{{--                                                        <option selected="" disabled="" value="">Choose...</option>--}}
{{--                                                        @foreach($companies as $company)--}}
{{--                                                            <option value="{{$company->id}}" @if($template->getProject->getUnit->getZone->getCompany->id == $company->id) selected @endif>{{$company->company_name}}</option>--}}
{{--                                                        @endforeach--}}
{{--                                                    </select>--}}
{{--                                                    @error('company_id')--}}
{{--                                                    <p class="text-red-500 text-xs mt-1">--}}
{{--                                                        {{$message}}--}}
{{--                                                    </p>--}}
{{--                                                    @enderror--}}
{{--                                                </div>--}}
{{--                                                <div class="col-xl-6 col-sm-6">--}}
{{--                                                    <label class="form-label" for="zone_id">Select Zone</label>--}}
{{--                                                    <select class="form-select" id="zone_id" name="zone_id" required="">--}}
{{--                                                        <option selected="" disabled="" value="">Choose...</option>--}}
{{--                                                        @foreach($zones as $zone)--}}
{{--                                                            <option value="{{$zone->id}}" @if($template->getProject->getUnit->getZone->id == $zone->id) selected @endif>{{$zone->zone_name}}</option>--}}
{{--                                                        @endforeach--}}
{{--                                                    </select>--}}
{{--                                                    @error('zone_id')--}}
{{--                                                    <p class="text-red-500 text-xs mt-1">--}}
{{--                                                        {{$message}}--}}
{{--                                                    </p>--}}
{{--                                                    @enderror--}}
{{--                                                </div>--}}
{{--                                                <div class="col-xl-6 col-sm-6">--}}
{{--                                                    <label class="form-label" for="unit_id">Select Unit</label>--}}
{{--                                                    <select class="form-select" id="unit_id" name="unit_id" required="">--}}
{{--                                                        <option selected="" disabled="" value="">Choose...</option>--}}
{{--                                                        @foreach($units as $unit)--}}
{{--                                                            <option value="{{$unit->id}}" @if($template->getProject->getUnit->id == $unit->id) selected @endif>{{$unit->unit_name}}</option>--}}
{{--                                                        @endforeach--}}
{{--                                                    </select>--}}
{{--                                                    @error('zone_id')--}}
{{--                                                    <p class="text-red-500 text-xs mt-1">--}}
{{--                                                        {{$message}}--}}
{{--                                                    </p>--}}
{{--                                                    @enderror--}}
{{--                                                </div>--}}
{{--                                                <div class="col-xl-6 col-sm-6">--}}
{{--                                                    <label class="form-label" for="project_id">Select Unit</label>--}}
{{--                                                    <select class="form-select" id="project_id" name="project_id" required="">--}}
{{--                                                        <option selected="" disabled="" value="">Choose...</option>--}}
{{--                                                        @foreach($projects as $project)--}}
{{--                                                            <option value="{{$project->id}}" @if($template->getProject->id == $project->id) selected @endif>{{$project->project_name}}</option>--}}
{{--                                                        @endforeach--}}
{{--                                                    </select>--}}
{{--                                                    @error('project_id')--}}
{{--                                                    <p class="text-red-500 text-xs mt-1">--}}
{{--                                                        {{$message}}--}}
{{--                                                    </p>--}}
{{--                                                    @enderror--}}
{{--                                                </div>--}}
                                                <div class="col-xl-6 col-sm-6">
                                                    <label class="form-label" for="template_name">Template Name<span class="txt-danger">*</span></label>
                                                    <input class="form-control" id="template_name" value="{{$template->template_name}}" name="template_name" type="text" placeholder="Enter template name" required="">
                                                    @error('template_name')
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
            function render_units(units){
                let unit_select = $("#unit_id");
                unit_select.empty();
                unit_select.append($('<option>').text('Choose...').attr('disabled', 'disabled').attr('selected', 'selected').val(''));
                $.each(units, function (index, value){
                    unit_select.append($('<option>').text(value.unit_name).val(value.id));
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
            $("#zone_id").change(function (e){
                const selectedZoneId = $(this).val();
                if(selectedZoneId){
                    $.ajax({
                        url:'{{route('get_zones_units')}}',
                        type:"POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "id":selectedZoneId
                        },
                        success:function (response){
                            console.log(response)
                            if(response.message=="Success"){
                                render_units(response.related_units);
                            }
                        }
                    })
                }
            })
        })
    </script>
@endsection
