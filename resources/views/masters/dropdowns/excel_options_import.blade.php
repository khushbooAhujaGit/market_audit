@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <a href="{{route('dropdownOptionTemplate.download')}}"><button class = "btn btn-primary ">Download Dropdown Format</button></a>
                        </div>
                        <div class="card-body">
                            <div class="horizontal-wizard-wrapper">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                            <div class="tab-pane fade show active" id="wizard-info" role="tabpanel" aria-labelledby="wizard-info-tab">
                                                <form method="POST" action="{{route('import.options_dropdown.store')}}" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate="">
                                                    @csrf
                                                    <div class="col-xl-4 col-sm-4">
                                                        <label class="form-label" for="dropdown_options">Choose Excel File</label>
                                                        <input type="file" class="form-control" id="dropdown_options" name="dropdown_options" required>
                                                        @error('dropdown_options')
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
            @if(session()->has('message'))
            Swal.fire({
                position: "top-center",
                icon: "success",
                title: "{{ session('message') }}",
                showConfirmButton: false,
                timer: 1500
            });
            @endif
        })
    </script>
@endsection
