@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Add Bulk User <a href="{{route('userUploadTemplate.download')}}"><button class = "btn btn-primary ">Download User Excel Template</button></a> <a href="{{url('users')}}"><button class = "btn btn-primary float-end">View Users</button></a></h4>
                        </div>
                        <div class="card-body">
                            @if (session('upload_result'))
                                @php $result = session('upload_result'); @endphp
                                <div class="alert {{ $result['created'] ? 'alert-warning' : 'alert-danger' }} mb-4">
                                    <strong>{{ $result['created'] }} user(s) uploaded, {{ $result['skipped'] }} row(s) skipped because of errors.</strong>
                                    The skipped rows were not saved. Download the error file to see the reason for each row,
                                    correct them in that file and upload it again.
                                    <div class="mt-2">
                                        <a href="{{ route('users.uploadErrors', $result['token']) }}" class="btn btn-sm btn-primary">Download Error File</a>
                                    </div>
                                </div>
                            @endif
                            <ul class="text-muted small ps-3 mb-4">
                                <li>Each row is checked like the Add User form: name, email, 10-digit mobile, password (8–20 characters) and role are required; pincode must be 6 digits.</li>
                                <li>Email and mobile must not belong to an existing user or be repeated in the sheet.</li>
                                <li>Agency (optional, Auditor or Verifier only): pick the agency user's email to link the user to that agency, like "Select Agency" on the Add User form.</li>
                                <li>Valid rows are added; rows with errors are skipped and listed in an error file you can correct and upload again.</li>
                            </ul>
                            <div class="horizontal-wizard-wrapper">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                            <div class="tab-pane fade show active" id="wizard-info" role="tabpanel" aria-labelledby="wizard-info-tab">
                                                <form method="POST" action="{{route('user.blukUpload')}}" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate="">
                                                    @csrf
                                                    <div class="col-xl-6 col-12">
                                                        <label class="form-label" for="user_excel">User Excel File<span class="txt-danger"></span></label>
                                                        <input class="form-control" id="user_excel" name="user_excel" type="file" required="" placeholder="">
                                                        @error('user_excel')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-12 text-end">
                                                        <button class="btn btn-primary">Upload Now</button>
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

    </script>
@endsection
