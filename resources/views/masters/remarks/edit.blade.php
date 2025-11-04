@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Edit Remark <a href="{{ route('remark.list') }}"><button
                                        class = "btn btn-primary float-end">View Remarks</button></a></h4>
                        </div>
                        <div class="card-body">
                            <div class="horizontal-wizard-wrapper">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                            <div class="tab-pane fade show active" id="wizard-info" role="tabpanel"
                                                aria-labelledby="wizard-info-tab">
                                                <form method="POST"
                                                    action="{{ route('remark.update', ['id' => $remark->id]) }}"
                                                    enctype="multipart/form-data" class="row g-3 needs-validation"
                                                    novalidate="">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="project">Project<span
                                                                class="txt-danger">*</span>
                                                        </label>
                                                        <select class="form-control form-select" name="project_id[]"
                                                            id="project_id" required multiple >
                                                            @foreach ($projects as $data)
                                                                <option value="{{ $data->id }}"
                                                                    @if ($remark->project_id == $data->id) selected @endif>
                                                                    {{ $data->project_name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="remark">Remark<span
                                                                class="txt-danger">*</span></label>
                                                        <input class="form-control" id="remark"
                                                            value="{{ $remark->remark }}" name="remark" type="text"
                                                            placeholder="Enter remark" required="">
                                                        @error('remark')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>

                                                    <div class="col-xl-3 col-sm-3">
                                                        <div class="form-check checkbox checkbox-primary mb-0 mt-3">
                                                            <input class="form-check-input" name="status" id="status"
                                                                type="checkbox"
                                                                @if ($remark->status == 1) checked="" @endif>
                                                            <label class="form-check-label" for="status">Active Or
                                                                Un-active</label>
                                                        </div>
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
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $("#project_id").select2({
            placeholder: "Select Project"
        });
    </script>
@endsection
