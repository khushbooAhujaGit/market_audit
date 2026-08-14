@extends('template.layouts.simple.master')
@section('style')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endsection
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <h4>Infiltration Audit Report</h4>
                                <p class="text-muted mb-0">
                                    Generates the standard report for a single activity, runs it through the
                                    Infiltration Audit Report Tool, and emails you the reformatted report plus
                                    every outlet's photos as a ZIP. This can take a few minutes for projects
                                    with many outlets, since photos are downloaded one at a time.
                                </p>
                                @error('onedrive')
                                    <p class="text-red-500 text-xs mt-1 mb-0">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="text-end" style="font-size:12px;">
                                @if ($oneDriveConnected)
                                    <span class="text-success">✓ Personal OneDrive connected</span>
                                    <form method="POST" action="{{ route('onedrive.disconnect') }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-link btn-sm p-0 ms-1" style="font-size:12px;">Disconnect</button>
                                    </form>
                                @else
                                    <a href="{{ route('onedrive.connect') }}" class="btn btn-outline-secondary btn-sm">Connect My OneDrive</a>
                                @endif
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('infiltration.generate') }}"
                                class="row g-3 needs-validation" novalidate="">
                                @csrf

                                <div class="col-xl-3 col-sm-3">
                                    <label class="form-label" for="project_id">Select Project</label>
                                    <select class="form-select" id="project_id" name="project_id" required="">
                                        <option selected="" disabled="" value="">Choose...</option>
                                        @foreach ($projects as $project)
                                            <option value="{{ $project->id }}">{{ $project->project_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('project_id')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="col-xl-3 col-sm-3">
                                    <label class="form-label" for="template_name_id">Select Data Template</label>
                                    <select class="form-select" id="template_name_id" name="template_name_id" required="">
                                        <option selected="" disabled="" value="">Choose...</option>
                                        @foreach ($template_names as $template_name)
                                            <option value="{{ $template_name->id }}">{{ $template_name->template_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('template_name_id')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="col-xl-3 col-sm-3">
                                    <label class="form-label" for="activity_id">Activity</label>
                                    <select class="form-select" id="activity_id" name="activity_id[]" required="">
                                        <option selected="" disabled="" value="">Choose a template first...</option>
                                    </select>
                                    
                                    @error('activity_id')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="col-xl-3 col-sm-3" id="group_template_warning" style="display:none;">
                                    <div class="alert alert-warning py-2 px-3 mb-0" style="font-size:12px;">
                                        This template is a group-activity template. The Infiltration Audit
                                        Report Tool only supports single-activity templates.
                                    </div>
                                </div>

                                <div class="col-xl-3 col-sm-3">
                                    <label class="form-label" for="from_date">Start Date</label>
                                    <input class="form-control" id="from_date" name="from_date" type="date" required="">
                                    @error('from_date')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="col-xl-3 col-sm-3">
                                    <label class="form-label" for="to_date">End Date</label>
                                    <input class="form-control" id="to_date" name="to_date" type="date" required="">
                                    @error('to_date')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="col-12 text-end">
                                    <span id="download_wait_msg" class="text-muted me-2" style="font-size:12px;display:none;">
                                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                        Generating — this can take a few minutes, please don't close this tab...
                                    </span>
                                    <button type="submit" id="download_btn" formaction="{{ route('infiltration.download') }}"
                                        class="btn btn-outline-primary">
                                        Download ZIP
                                    </button>
                                    @if ($oneDriveConnected)
                                        <button type="submit" id="onedrive_personal_btn" formaction="{{ route('infiltration.upload_onedrive_personal') }}"
                                            class="btn btn-outline-primary">
                                            Upload to My OneDrive
                                        </button>
                                    @endif
                                    <button type="submit" id="onedrive_shared_btn" formaction="{{ route('infiltration.upload_onedrive_shared') }}"
                                        class="btn btn-outline-primary d-none">
                                        Upload to Shared OneDrive
                                    </button>
                                    <button type="submit" id="generate_btn" formaction="{{ route('infiltration.generate') }}"
                                        class="btn btn-primary">
                                        Generate &amp; Email Report
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="download_overlay" style="display:none;position:fixed;inset:0;z-index:2000;
        background:rgba(0,0,0,0.55);color:#fff;flex-direction:column;
        align-items:center;justify-content:center;text-align:center;padding:20px;">
        <div class="spinner-border text-light mb-3" role="status" style="width:3rem;height:3rem;"></div>
        <h5 class="mb-1">Generating your report...</h5>
        <p class="mb-0" style="max-width:420px;">
            This can take a few minutes for projects with many outlets, since photos are
            downloaded one at a time. Please keep this tab open — the ZIP will download
            automatically when it's ready.
        </p>
    </div>
@endsection
@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#project_id').select2();
            let all_activities = @json($activities);

            // "Download ZIP" submits the same form normally (not AJAX) so the browser
            // handles the file save itself — but that means the request blocks this
            // page for as long as generation takes, with no completion event to key
            // off of. Show a blocking overlay + wait message on submit, and reset
            // everything on window focus, which fires when the browser returns focus
            // here after the native download completes (also covers the
            // back/forward-cache restore case).
            //
            // No beforeunload guard here: the browser briefly treats this form's own
            // POST as a real navigation before it recognizes the response as a
            // download, so a beforeunload handler fires on the expected submission
            // too — showing a "Leave site?" prompt on every legitimate click, not just
            // an accidental one. The overlay + message below are enough warning.
            $('#download_btn').on('click', function() {
                if (!$(this).closest('form')[0].reportValidity()) return;
                $('#download_wait_msg').show();
                $('#download_overlay').css('display', 'flex');
                // Disabling the button that was just clicked, synchronously inside its
                // own click handler, can make the browser skip the form's default
                // submit action entirely (it checks `disabled` right before firing
                // that action). Defer it to the next tick so submission has already
                // been dispatched first.
                setTimeout(function() {
                    $('#download_btn, #generate_btn').prop('disabled', true);
                }, 0);
            });

            $(window).on('focus pageshow', function() {
                $('#download_btn, #generate_btn').prop('disabled', false);
                $('#download_wait_msg').hide();
                $('#download_overlay').hide();
            });

            @if (session()->has('message'))
                Swal.fire({
                    position: "top-center",
                    icon: "success",
                    title: "{{ session('message') }}",
                    showConfirmButton: false,
                    timer: 2500
                });
            @endif

            function render_templates(projectTemplates) {
                let select = $("#template_name_id");
                select.empty();
                select.append($('<option>').text('Choose...').attr('disabled', 'disabled').attr('selected', 'selected').val(''));
                $.each(projectTemplates, function(index, projectTemplate) {
                    select.append($('<option>').text(projectTemplate.get_template.template_name).val(projectTemplate.get_template.id));
                });
            }

            $("#project_id").change(function() {
                const selectedProjectId = $(this).val();
                $("#activity_id").html('<option selected disabled value="">Choose a template first...</option>');
                $("#group_template_warning").hide();
                if (!selectedProjectId) return;
                $.ajax({
                    url: '{{ route('get_project_info') }}',
                    type: "POST",
                    data: { "_token": "{{ csrf_token() }}", "id": selectedProjectId },
                    success: function(response) {
                        if (response.message == "Success") {
                            render_templates(response.projectTemplateNames);
                        }
                    }
                });
            });

            $("#template_name_id").change(function() {
                const template_name_id = $(this).val();
                const project_id = $("#project_id").val();
                $("#group_template_warning").hide();
                $("#generate_btn, #download_btn, #onedrive_personal_btn, #onedrive_shared_btn").prop('disabled', false);
                if (!template_name_id) return;
                $.ajax({
                    url: '{{ route('projectTemplate.info') }}',
                    type: "POST",
                    data: { "_token": "{{ csrf_token() }}", "t_id": template_name_id, "p_id": project_id },
                    success: function(response) {
                        if (response.message != "success") return;
                        const pro_temp = response.projectTemplate;
                        const activitySelect = $("#activity_id");
                        activitySelect.empty();

                        if (!pro_temp.activity_group_name_id_or_activity_id) {
                            activitySelect.append($('<option>').text('Project Template Name Mapping is not completed').val(''));
                            return;
                        }

                        if (pro_temp.activityType == 0) {
                            // Single-activity template — exactly what this tool needs.
                            const activityId = pro_temp.activity_group_name_id_or_activity_id;
                            const match = all_activities.find(a => a.id == activityId);
                            const label = match ? match.activity_name : ('Activity #' + activityId);
                            activitySelect.append($('<option>').text(label).val(activityId).attr('selected', 'selected'));
                        } else {
                            // Group-activity template — not supported by this tool (needs exactly one sheet).
                            activitySelect.append($('<option>').text('Not supported for group-activity templates').val(''));
                            $("#group_template_warning").show();
                            $("#generate_btn, #download_btn, #onedrive_personal_btn, #onedrive_shared_btn").prop('disabled', true);
                        }
                    }
                });
            });
        });
    </script>
@endsection
