@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Role : {{$role->name}} <a href="{{url('roles')}}">
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
                                                <form method="POST"
                                                      action="{{ route('role.givePermissions', $role->id) }}"
                                                      enctype="multipart/form-data" class="row g-3 needs-validation"
                                                      novalidate="">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="mb-3">
                                                        @error('permission')
                                                        <span class="text-danger">{{$message}}</span>
                                                        @enderror
                                                        <label>Permissions</label>
                                                        <p>

                                                        <span>
                                                            <input type="checkbox" id="select_all"> </span>
                                                            <label for="select_all" id="select_all_label">Select
                                                                All</label>
                                                        </p>
                                                    </div>
                                                    <div class="row">
                                                        @foreach($permissions as $permission)

                                                            <div class="col-md-3">
                                                                <div
                                                                    class="form-check form-check-inline checkbox checkbox-dark mb-0">
                                                                    <input class="form-check-input permissionCheckbox"
                                                                           id="{{$permission->name}}"
                                                                           name="permission[]"
                                                                           value="{{$permission->name}}"
                                                                           type="checkbox"
                                                                        {{ in_array($permission->id,  $rolePermissions->toArray()) ? 'checked' : "" }}
                                                                    >
                                                                    <label class="form-check-label"
                                                                           for="{{$permission->name}}">
                                                                        {{$permission->name}}</label>
                                                                </div>

                                                            </div>
                                                        @endforeach
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
        @if(session()->has('message'))
        Swal.fire({
            position: "top-center",
            icon: "success",
            title: "{{ session('message') }}",
            showConfirmButton: false,
            timer: 1000
        });
        @endif

        $("#select_all").change(function () {
            const isChecked = $(this).is(":checked");
            if (isChecked) {
                $("#select_all_label").html("Unselect All");
            } else {
                $("#select_all_label").html("Select All");
            }
            $(".permissionCheckbox").prop("checked", isChecked);
        })
    </script>

@endsection
