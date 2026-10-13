@extends('template.layouts.simple.master')
@section('style')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .bulk-step { border: 1px solid #e6edef; border-radius: 10px; padding: 18px 20px; margin-bottom: 18px; }
        .bulk-step h6 { font-weight: 700; margin-bottom: 14px; }
        .bulk-hint { font-size: 13px; color: #6c757d; }
        #templateInfo .badge { font-weight: 500; }
    </style>
@endsection
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Bulk Assign
                                <a href="{{ route('assign_data.create') }}" class="btn btn-outline-primary float-end">Single Assign</a>
                            </h4>
                        </div>
                        <div class="card-body">

                            {{-- Step 1: download --}}
                            <div class="bulk-step">
                                <h6>1. Download the assignment sheet</h6>
                                <div class="row g-3">
                                    <div class="col-xl-4 col-md-6">
                                        <label class="form-label" for="project_id">Select Project</label>
                                        <select class="form-select" id="project_id">
                                            <option value="" selected disabled>Choose...</option>
                                            @foreach ($projects as $project)
                                                <option value="{{ $project->id }}">{{ $project->project_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-xl-4 col-md-6">
                                        <label class="form-label" for="project_template_id">Select Data Template</label>
                                        <select class="form-select" id="project_template_id" disabled>
                                            <option value="" selected disabled>Choose a project first</option>
                                        </select>
                                    </div>
                                </div>

                                <div id="templateInfo" class="mt-3 d-none">
                                    <div class="bulk-hint mb-1" id="templateCounts"></div>
                                    <div class="bulk-hint mb-1"><strong>Activities:</strong> <span id="templateActivities"></span></div>
                                    <div class="bulk-hint d-none" id="outletHint">
                                        <strong>Assign Outlets:</strong> this template has outlet (child) templates, so the sheet has an
                                        <strong>Assign Outlets</strong> column (Yes/No) on every line. <strong>Yes</strong> also assigns the
                                        outlet rows linked to that row to the same auditor and verifier; <strong>No</strong> assigns only that row.
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <button type="button" class="btn btn-primary" id="downloadBtn" disabled>Download Sheet</button>
                                </div>
                            </div>

                            {{-- Step 2: upload --}}
                            <div class="bulk-step">
                                <h6>2. Upload the filled sheet</h6>
                                <ul class="bulk-hint ps-3 mb-3">
                                    <li>Fill <strong>both Auditor Mobile No and Verifier Mobile No</strong> (10-digit mobile numbers) on the lines you want to assign. For more than one person, separate the numbers with commas.</li>
                                    <li>A line with only one of the two, or neither, is not assigned to anyone. Existing assignments are kept; nothing is removed or replaced.</li>
                                    <li>The whole sheet is checked first. If any line is wrong, nothing is assigned and a file listing the errors is downloaded.</li>
                                </ul>
                                <div class="row g-3 align-items-end">
                                    <div class="col-xl-6 col-md-8">
                                        <label class="form-label" for="sheetFile">Filled sheet (.xlsx)</label>
                                        <input class="form-control" type="file" id="sheetFile" accept=".xlsx">
                                    </div>
                                    <div class="col-xl-3 col-md-4">
                                        <button type="button" class="btn btn-primary w-100" id="uploadBtn">Upload &amp; Assign</button>
                                    </div>
                                </div>

                                <div id="uploadStatus" class="mt-3"></div>
                                <div id="progressWrap" class="mt-2 d-none">
                                    <div class="progress" style="height: 22px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar"
                                            role="progressbar" style="width: 0%">0%</div>
                                    </div>
                                    <div class="bulk-hint mt-1" id="progressText"></div>
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
        $(function() {
            const csrf = "{{ csrf_token() }}";
            let templatesById = {};

            $('#project_id').select2({ width: '100%' });
            // Same widget for both dropdowns so they render at the same height
            $('#project_template_id').select2({ width: '100%' });

            @if (session('error'))
                Swal.fire({ icon: 'error', title: @json(session('error')) });
            @endif

            function escapeHtml(value) {
                return $('<div>').text(value ?? '').html();
            }

            function showStatus(type, html) {
                $('#uploadStatus').html(`<div class="alert alert-${type} mb-0">${html}</div>`);
            }

            function selectedTemplate() {
                return templatesById[$('#project_template_id').val()] || null;
            }

            function refreshTemplateInfo() {
                const t = selectedTemplate();
                $('#templateInfo, #outletHint').addClass('d-none');
                $('#downloadBtn').prop('disabled', true);
                if (!t) return;

                const lines = t.rows * t.activities.length;
                $('#templateCounts').html(`<strong>${t.rows.toLocaleString()}</strong> rows × <strong>${t.activities.length}</strong> ${t.activities.length === 1 ? 'activity' : 'activities'} = <strong>${lines.toLocaleString()}</strong> lines in the sheet`);
                $('#templateActivities').text(t.activities.length ? t.activities.join(', ') : 'No activity mapped to this template yet');
                $('#templateInfo').removeClass('d-none');

                $('#outletHint').toggleClass('d-none', !t.can_assign_outlets);

                if (t.rows === 0) {
                    $('#templateCounts').append(' — <span class="text-danger">this template has no data to assign; use Single Assign.</span>');
                }
                $('#downloadBtn').prop('disabled', t.rows === 0 || t.activities.length === 0);
            }

            $('#project_id').on('change', function() {
                const $tpl = $('#project_template_id').prop('disabled', true).html('<option value="">Loading...</option>').trigger('change.select2');
                templatesById = {};
                refreshTemplateInfo();
                $.post("{{ route('bulk_assign.templates') }}", { _token: csrf, project_id: $(this).val() })
                    .done(function(res) {
                        $tpl.empty().append('<option value="" selected disabled>Choose...</option>');
                        res.templates.forEach(t => {
                            templatesById[t.id] = t;
                            $tpl.append($('<option>').val(t.id).text(t.name));
                        });
                        $tpl.prop('disabled', false);
                        if (res.templates.length === 1) {
                            // Only one template in the project: select it straight away
                            $tpl.val(String(res.templates[0].id)).trigger('change');
                        } else {
                            $tpl.val('').trigger('change.select2');
                        }
                    })
                    .fail(() => $tpl.html('<option value="">Could not load templates</option>').trigger('change.select2'));
            });

            $('#project_template_id').on('change', refreshTemplateInfo);

            $('#downloadBtn').on('click', function() {
                const t = selectedTemplate();
                if (!t) return;
                window.location = "{{ route('bulk_assign.download') }}" + `?project_template_id=${t.id}`;
            });

            function downloadBlob(blob, filename) {
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(() => URL.revokeObjectURL(url), 1000);
            }

            function filenameFrom(res, fallback) {
                const m = /filename="?([^";]+)"?/.exec(res.headers.get('Content-Disposition') || '');
                return m ? m[1] : fallback;
            }

            function incompleteNote(job) {
                return job.incomplete
                    ? `<br><strong>${job.incomplete.toLocaleString()} line(s) were not assigned</strong> because they have only an Auditor Mobile No or only a Verifier Mobile No; both are needed. `
                    : '';
            }

            function setProgress(processed, total) {
                const pct = total ? Math.round(processed / total * 100) : 100;
                $('#progressBar').css('width', pct + '%').text(pct + '%');
                $('#progressText').text(`Assigning… batch ${processed} of ${total}`);
            }

            function runProcessing(job, offset) {
                $('#progressWrap').removeClass('d-none');
                setProgress(offset, job.units);
                $.post("{{ route('bulk_assign.process') }}", { _token: csrf, token: job.token, offset: offset })
                    .done(function(res) {
                        setProgress(res.processed, res.total);
                        if (!res.done) return runProcessing(job, res.processed);
                        $('#progressBar').removeClass('progress-bar-animated');
                        $('#progressText').text('Done.');
                        $('#uploadBtn').prop('disabled', false);
                        showStatus(job.incomplete ? 'warning' : 'success',
                            `<strong>Assignment complete.</strong> ${job.lines.toLocaleString()} line(s) assigned` +
                            (job.skipped ? `, ${job.skipped.toLocaleString()} blank line(s) skipped` : '') + '. ' +
                            incompleteNote(job) +
                            `<a href="{{ route('auditorAssigned.list') }}">View Data Assigned List</a>`);
                    })
                    .fail(function(xhr) {
                        $('#uploadBtn').prop('disabled', false);
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'The server did not respond.';
                        showStatus('danger',
                            `<strong>Assignment stopped at batch ${offset} of ${job.units}.</strong> ${escapeHtml(msg)} ` +
                            `<button type="button" class="btn btn-sm btn-outline-danger ms-2" id="resumeBtn">Resume</button>`);
                        $('#resumeBtn').one('click', () => { showStatus('info', 'Resuming…'); $('#uploadBtn').prop('disabled', true); runProcessing(job, offset); });
                    });
            }

            $('#uploadBtn').on('click', function() {
                const file = $('#sheetFile')[0].files[0];
                if (!file) {
                    showStatus('warning', 'Please choose the filled sheet first.');
                    return;
                }
                const fd = new FormData();
                fd.append('_token', csrf);
                fd.append('sheet', file);

                $('#uploadBtn').prop('disabled', true);
                $('#progressWrap').addClass('d-none');
                $('#progressBar').addClass('progress-bar-animated');
                showStatus('info', 'Checking the sheet…');

                fetch("{{ route('bulk_assign.upload') }}", { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
                    .then(function(res) {
                        const ct = res.headers.get('Content-Type') || '';
                        if (ct.indexOf('spreadsheetml') !== -1) {
                            const lines = res.headers.get('X-Error-Lines');
                            const name = filenameFrom(res, 'bulk_assign_errors.xlsx');
                            return res.blob().then(blob => ({ type: 'errors', blob, name, lines }));
                        }
                        return res.json().then(data => ({ type: res.ok ? 'ok' : 'fail', data }));
                    })
                    .then(function(result) {
                        if (result.type === 'errors') {
                            $('#uploadBtn').prop('disabled', false);
                            downloadBlob(result.blob, result.name);
                            showStatus('danger',
                                `<strong>${result.lines ? Number(result.lines).toLocaleString() + ' line(s) have' : 'Some lines have'} errors, so nothing was assigned.</strong> ` +
                                `The file <em>${escapeHtml(result.name)}</em> has been downloaded with the sheet row and the reason for each. Fix them and upload again.`);
                            return;
                        }
                        if (result.type === 'fail') {
                            $('#uploadBtn').prop('disabled', false);
                            const d = result.data || {};
                            const detail = d.errors ? Object.values(d.errors).flat().join(' ') : '';
                            showStatus('danger', escapeHtml(d.message || 'Upload failed.') + (detail ? ' ' + escapeHtml(detail) : ''));
                            return;
                        }
                        const job = result.data;
                        showStatus('info',
                            `Sheet is valid: <strong>${job.lines.toLocaleString()}</strong> line(s) to assign for ` +
                            `<strong>${escapeHtml(job.project)}</strong> — ${escapeHtml(job.template)}` +
                            (job.outlets ? ` (${job.outlets.toLocaleString()} of them also assign their outlets)` : '') + '. ' + incompleteNote(job));
                        runProcessing(job, 0);
                    })
                    .catch(function() {
                        $('#uploadBtn').prop('disabled', false);
                        showStatus('danger', 'Upload failed. Please check your connection and try again.');
                    });
            });
        });
    </script>
@endsection
