@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Template Documents</h4>
                            <p class="text-muted mb-0 mt-1">Files named in a template's file columns. Upload them here
                                (several at once, or a .zip); each file goes to the file name of the data found in its name.
                                Files given as links in the data are downloaded automatically after the data upload.</p>
                        </div>
                        <div class="card-body">
                            {{-- Project / template (loaded without leaving the page) --}}
                            <div class="row g-3 mb-3">
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
                                @if ($projects->isEmpty())
                                    <div class="col-12 text-muted">No project uses a template with a file column yet.</div>
                                @endif
                            </div>

                            <div id="documents_section" class="d-none">
                                {{-- Upload --}}
                                <form id="upload_form" class="row g-3 align-items-end mb-3 border rounded p-3 mx-0">
                                    <div class="col-xl-7 col-md-8">
                                        <label class="form-label" for="files">Upload files</label>
                                        <input class="form-control" type="file" id="files" multiple
                                            accept=".{{ implode(',.', \App\Services\TemplateDocuments::EXTENSIONS) }},.zip">
                                        <small class="text-muted">{{ strtoupper(implode(', ', \App\Services\TemplateDocuments::EXTENSIONS)) }} up to 25 MB each,
                                            or a .zip of them. A file goes to the data's file name found in its name (capital letters don't
                                            matter), e.g. "Scan_inv1.pdf" for inv1.pdf. Uploading a file again replaces it.</small>
                                    </div>
                                    <div class="col-xl-2 col-md-4">
                                        <button class="btn btn-primary w-100" type="submit" id="upload_btn">Upload</button>
                                    </div>
                                    <div class="col-xl-3 col-md-12 text-xl-end">
                                        <button class="btn btn-outline-danger d-none" type="button" id="retry_btn"></button>
                                    </div>
                                </form>

                                <div id="upload_results"></div>

                                {{-- Status filter (search and pages are on the table) --}}
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-3" id="status_filters"></div>

                                <div class="table-responsive theme-scrollbar">
                                    <table class="display" id="documents_table" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>File</th>
                                                <th>From</th>
                                                <th>Rows using it</th>
                                                <th>Status</th>
                                                <th>Size</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
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
            const urls = {
                templates: @json(route('template_documents.templates')),
                list: @json(route('template_documents.list')),
                upload: @json(route('template_documents.upload')),
                retry: @json(route('template_documents.retry')),
            };
            const csrf = @json(csrf_token());
            const limits = @json($uploadLimits);
            const maxFileBytes = {{ \App\Services\TemplateDocuments::MAX_BYTES }};
            const statusLabels = {
                available: ['Available', 'bg-success'],
                awaiting_file: ['Waiting for file', 'bg-warning text-dark'],
                queued: ['Downloading', 'bg-info text-dark'],
                failed: ['Failed', 'bg-danger'],
            };
            let templateId = null;
            let statusFilter = '';
            let table = null;

            const esc = (value) => $('<div>').text(value ?? '').html();

            // ── Project → its templates ──
            $('#project_id').on('change', function() {
                const $template = $('#project_template_id');
                $template.prop('disabled', true).html('<option value="" selected disabled>Loading...</option>');
                hideDocuments();
                $.get(urls.templates, { project_id: this.value }).done(function(response) {
                    const templates = response.templates || [];
                    $template.html('<option value="" selected disabled>' + (templates.length ? 'Choose...' : 'No template with a file column') + '</option>' +
                        templates.map(t => '<option value="' + t.id + '">' + esc(t.name) + '</option>').join(''));
                    $template.prop('disabled', !templates.length);
                    if (templates.length === 1) $template.val(templates[0].id).trigger('change');
                }).fail(() => $template.html('<option value="" selected disabled>Could not load the templates</option>'));
            });

            // ── Template → its documents ──
            $('#project_template_id').on('change', function() {
                templateId = this.value;
                statusFilter = '';
                $('#upload_results').empty();
                $('#files').val('');
                $('#documents_section').removeClass('d-none');
                if (table) {
                    table.search('').page(0).ajax.reload();
                } else {
                    initTable();
                }
            });

            function hideDocuments() {
                templateId = null;
                $('#documents_section').addClass('d-none');
                $('#upload_results').empty();
            }

            function initTable() {
                table = $('#documents_table').DataTable({
                    serverSide: true,
                    processing: true,
                    ordering: false,
                    pageLength: 25,
                    lengthMenu: [10, 25, 50, 100, 250],
                    searchDelay: 400,
                    language: { search: 'Search file name:', emptyTable: 'No files for this template.' },
                    ajax: {
                        url: urls.list,
                        data: d => Object.assign(d, { project_template_id: templateId, status: statusFilter }),
                        dataSrc: function(json) {
                            renderSummary(json.counts || {}, json.failed_links || 0);
                            return json.data;
                        },
                    },
                    columns: [
                        { data: 'file_name', render: (v, t, row) => '<div style="max-width:360px; word-break:break-all;">' + esc(v) +
                            (row.source_url ? '<div class="small text-muted">' + esc(row.source_url) + '</div>' : '') + '</div>' },
                        { data: 'from' },
                        { data: 'rows' },
                        { data: 'status', render: (v, t, row) => {
                            const [label, cls] = statusLabels[v] || [v, 'bg-secondary'];
                            return '<span class="badge ' + cls + '">' + esc(label) + '</span>' +
                                (row.error ? '<div class="small text-danger mt-1" style="max-width:280px; white-space:normal;">' + esc(row.error) + '</div>' : '');
                        } },
                        { data: 'size' },
                        { data: 'view_url', className: 'text-nowrap', render: v => v
                            ? '<a href="' + esc(v) + '" target="_blank">View</a> · <a href="' + esc(v + (v.includes('?') ? '&' : '?') + 'download=1') + '">Download</a>'
                            : '' },
                    ],
                });
            }

            function renderSummary(counts, failedLinks) {
                const total = Object.values(counts).reduce((sum, n) => sum + Number(n), 0);
                const button = (status, label, n) => '<button type="button" class="btn btn-sm status-filter ' +
                    (statusFilter === status ? 'btn-primary' : 'btn-light') + '" data-status="' + status + '">' + esc(label) + ' (' + n + ')</button>';
                $('#status_filters').html(button('', 'All', total) +
                    Object.entries(statusLabels).map(([status, [label]]) => button(status, label, counts[status] || 0)).join(''));
                $('#retry_btn').toggleClass('d-none', !failedLinks)
                    .text('Retry ' + failedLinks + ' failed link' + (failedLinks === 1 ? '' : 's'));
            }

            $('#status_filters').on('click', '.status-filter', function() {
                statusFilter = $(this).data('status');
                table.page(0).ajax.reload();
            });

            // ── Retry failed links ──
            $('#retry_btn').on('click', function() {
                const $btn = $(this).prop('disabled', true);
                $.post(urls.retry, { _token: csrf, project_template_id: templateId })
                    .done(response => showMessage('info', response.message))
                    .fail(xhr => showMessage('danger', errorText(xhr)))
                    .always(() => { $btn.prop('disabled', false); table.ajax.reload(null, false); });
            });

            // ── Upload: sent in batches that fit the server's limits; one click = one "batch" token ──
            $('#upload_form').on('submit', async function(e) {
                e.preventDefault();
                const files = Array.from($('#files')[0].files);
                if (!files.length) return showMessage('warning', 'Choose the files (or a .zip) to upload.');

                const results = [];
                const sendable = [];
                files.forEach(file => {
                    const isZip = /\.zip$/i.test(file.name);
                    if (!isZip && file.size > maxFileBytes) {
                        results.push({ name: file.name, ok: false, message: 'The file is larger than 25 MB.' });
                    } else if (file.size > limits.file || file.size > limits.bytes) {
                        results.push({ name: file.name, ok: false, message: 'The file is larger than this server accepts in one upload (' + Math.floor(Math.min(limits.file, limits.bytes) / 1048576) + ' MB).' });
                    } else {
                        sendable.push(file);
                    }
                });

                const batches = [];
                let current = [], currentBytes = 0;
                const maxCount = Math.min(limits.files, 10), maxBytes = limits.bytes * 0.9;
                sendable.forEach(file => {
                    if (current.length && (current.length >= maxCount || currentBytes + file.size > maxBytes)) {
                        batches.push(current);
                        current = [];
                        currentBytes = 0;
                    }
                    current.push(file);
                    currentBytes += file.size;
                });
                if (current.length) batches.push(current);

                const batchToken = Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
                const $btn = $('#upload_btn').prop('disabled', true);
                let sent = 0;
                for (const batch of batches) {
                    $btn.text('Uploading ' + (sent + 1) + '-' + (sent + batch.length) + ' of ' + sendable.length + '...');
                    const form = new FormData();
                    form.append('_token', csrf);
                    form.append('project_template_id', templateId);
                    form.append('batch', batchToken);
                    batch.forEach(file => form.append('files[]', file));
                    try {
                        const response = await $.ajax({ url: urls.upload, method: 'POST', data: form, processData: false, contentType: false });
                        results.push(...(response.results || []));
                    } catch (xhr) {
                        batch.forEach(file => results.push({ name: file.name, ok: false, message: errorText(xhr) }));
                    }
                    sent += batch.length;
                }
                $btn.prop('disabled', false).text('Upload');
                $('#files').val('');
                renderResults(results);
                table.ajax.reload(null, false);
            });

            function renderResults(results) {
                const ok = results.filter(r => r.ok).length;
                const ordered = results.filter(r => !r.ok).concat(results.filter(r => r.ok));
                $('#upload_results').html('<div class="alert ' + (ok === results.length ? 'alert-success' : 'alert-warning') + '">' +
                    '<strong>' + ok + ' of ' + results.length + ' file' + (results.length === 1 ? '' : 's') + ' uploaded.</strong>' +
                    '<ul class="mb-0 mt-1 ps-3" style="max-height:260px; overflow:auto;">' +
                    ordered.map(r => '<li>' + esc(r.name) + ': <span class="' + (r.ok ? 'text-success' : 'text-danger') + '">' + esc(r.message) + '</span></li>').join('') +
                    '</ul></div>');
            }

            function showMessage(type, text) {
                $('#upload_results').html('<div class="alert alert-' + type + '">' + esc(text) + '</div>');
            }

            function errorText(xhr) {
                const json = xhr && xhr.responseJSON;
                if (json && json.errors) return Object.values(json.errors).flat().join(' ');
                if (json && json.message) return json.message;
                if (xhr && xhr.status === 413) return 'The upload is too large for the server.';
                return 'The request failed' + (xhr && xhr.status ? ' (HTTP ' + xhr.status + ')' : '') + '.';
            }
        });
    </script>
@endsection
