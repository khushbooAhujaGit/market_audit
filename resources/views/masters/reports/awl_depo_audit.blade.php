@extends('template.layouts.simple.master')
@section('style')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .gk-hero { display:flex; align-items:center; gap:16px; padding:10px 18px; border-radius:10px;
            background:linear-gradient(120deg,#042b39 0%,#0b4a63 100%); color:#fff; margin-bottom:10px; }
        .gk-hero h4 { color:#fff; margin:0 0 4px; font-weight:600; letter-spacing:.2px; }
        .gk-hero p { margin:0; color:rgba(255,255,255,.78); font-size:13px; max-width:760px; }
        .gk-hero .gk-map { margin-left:auto; flex:none; width:340px; }
        .gk-hero .gk-map label { display:flex; align-items:center; font-size:11px; font-weight:600; letter-spacing:.6px;
            text-transform:uppercase; color:rgba(255,255,255,.85); margin-bottom:4px; }
        .gk-hero .gk-saved { color:#7be0ac; }
        .gk-hero .select2-container--default .select2-selection--multiple { min-height:34px; max-height:34px; border-radius:8px; }
        .gk-hero .gk-badge { margin-left:auto; flex:none; font-size:11px; letter-spacing:.8px; text-transform:uppercase;
            padding:6px 12px; border:1px solid rgba(255,255,255,.35); border-radius:999px; color:#fff; }
        .gk-step { background:#fff; border:1px solid #e6ebf1; border-radius:12px; padding:12px 16px; margin-bottom:10px; }
        .gk-step-head { display:flex; align-items:center; gap:12px; margin-bottom:8px; }
        .gk-num { width:28px; height:28px; border-radius:50%; background:#042b39; color:#fff; display:flex;
            align-items:center; justify-content:center; font-size:13px; font-weight:600; flex:none; }
        .gk-step.is-locked { opacity:.55; }
        .gk-step-head h6 { margin:0; font-weight:600; color:#042b39; }
        .gk-step-head small { color:#7b8794; display:block; font-size:12px; margin-top:1px; }
        .gk-hint { font-size:12px; color:#7b8794; margin-top:6px; }
        .gk-panel .select2-container--default .select2-selection--multiple { min-height:38px; max-height:38px; overflow:hidden; }
        .gk-panel { background:linear-gradient(180deg,#eaf2f7 0%,#e1ecf3 100%); border:1px solid #cfdfe9; border-left:4px solid #042b39;
            border-radius:12px; padding:10px 16px 12px; margin-bottom:10px; box-shadow:0 1px 3px rgba(4,43,57,.08); }
        .gk-label { display:flex; height:18px; align-items:center; font-size:11px; font-weight:600; letter-spacing:.6px; text-transform:uppercase; color:#042b39; margin-bottom:3px; }
        .gk-saved { font-size:12px; color:#1d8a5b; margin-left:auto; opacity:0; transition:opacity .25s; }
        .gk-saved.show { opacity:1; }
        .gk-result { border:1px dashed #cfd8e3; border-radius:12px; padding:18px 20px; background:#f8fafc; }
        .gk-result.is-done { border:1px solid #bfe3d0; background:#f2fbf6; }
        .gk-result.is-failed { border:1px solid #f1c3c3; background:#fff5f5; }
        .gk-dl { display:flex; gap:12px; flex-wrap:wrap; margin-top:14px; }
        .gk-dl a { display:inline-flex; align-items:center; gap:10px; padding:11px 18px; border-radius:10px; font-weight:500;
            text-decoration:none; }
        .gk-dl a small { display:block; font-weight:400; font-size:11px; opacity:.8; }
        .gk-dl .gk-primary { background:#042b39; color:#fff; }
        .gk-dl .gk-secondary { background:#fff; color:#042b39; border:1px solid #042b39; }
        .gk-dl a:hover { filter:brightness(1.12); }
        .gk-bar { height:5px; border-radius:3px; background:#e3e9f0; overflow:hidden; margin-top:12px; }
        .gk-bar > span { display:block; height:100%; width:40%; background:#0b7a9e; border-radius:3px; animation:gkslide 1.2s ease-in-out infinite; }
        @keyframes gkslide { 0% { margin-left:-40%; } 100% { margin-left:100%; } }
        .gk-toolbar { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:8px; }
        .gk-len { display:flex; align-items:center; gap:8px; font-size:13px; color:#52606d; margin:0; }
        .gk-len select { width:auto; }
        .gk-search { max-width:260px; }
        .gk-table-wrap { overflow-x:auto; border:1px solid #e6ebf1; border-radius:10px; }
        .gk-table { width:100%; border-collapse:collapse; font-size:13px; white-space:nowrap; }
        .gk-table th { background:#f4f7fa; color:#042b39; font-weight:600; text-align:left; padding:7px 10px; border-bottom:1px solid #e6ebf1; position:sticky; top:0; }
        .gk-table td { padding:6px 10px; border-bottom:1px solid #eef2f6; color:#33414f; max-width:360px; overflow:hidden; text-overflow:ellipsis; }
        .gk-table tbody tr:hover { background:#f8fbfd; }
        .gk-table tbody tr:last-child td { border-bottom:0; }
        .gk-table .gk-act { position:sticky; left:0; background:#fff; z-index:1; min-width:150px; border-right:1px solid #e6ebf1; }
        .gk-table th.gk-act { background:#f4f7fa; z-index:2; }
        .gk-empty { text-align:center; color:#7b8794; padding:28px !important; }
        .gk-gen { font-size:12px; padding:4px 12px; }
        .gk-links a { font-size:12px; margin-right:10px; color:#0b6e8f; font-weight:500; text-decoration:none; }
        .gk-links a:hover { text-decoration:underline; }
        .gk-state { font-size:12px; color:#52606d; display:inline-flex; align-items:center; gap:6px; }
        .gk-state.err { color:#c0392b; }
        .gk-foot { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-top:8px; }
        .gk-pager { display:flex; gap:4px; }
        .gk-pager button { border:1px solid #d7dee6; background:#fff; border-radius:8px; min-width:34px; height:32px; font-size:13px; color:#042b39; padding:0 10px; }
        .gk-pager button:hover:not(:disabled) { background:#eef4f8; }
        .gk-pager button.active { background:#042b39; color:#fff; border-color:#042b39; }
        .gk-pager button:disabled { opacity:.45; cursor:default; }
        .select2-container { width:100% !important; }
        .select2-container .select2-selection--single { height:38px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:36px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height:36px; }
    </style>
@endsection
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-12">

                    <div class="gk-hero">
                        <div>
                            <h4>AWL DB Compliance Report</h4>
                            <p>Filled AWL DB Audit workbook, PDF, client-facing Styled Report and a ZIP of the audit photos for one distributor.</p>
                        </div>
                        @if ($canMap)
                            <div class="gk-map">
                                <label>Report projects <span class="gk-saved" id="saved_chip">✓ Saved</span></label>
                                <select id="project_ids" multiple="multiple">
                                    @foreach ($projects as $project)
                                        <option value="{{ $project->id }}" @selected(in_array($project->id, $mappedIds))>{{ $project->project_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>

                    {{-- Control panel --}}
                    <div class="gk-panel">
                        <div class="row g-3 align-items-start">
                            <div class="col-lg-3 col-md-6">
                                <label class="gk-label">Project</label>
                                <select id="active_project" class="form-select">
                                    @foreach ($projects->whereIn('id', $mappedIds) as $project)
                                        <option value="{{ $project->id }}">{{ $project->project_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <label class="gk-label">Data template</label>
                                <select id="template_id" class="form-select"></select>
                            </div>
                            <div class="col-lg-5 col-md-12">
                                <label class="gk-label">Distributor (DB)</label>
                                <div class="d-flex gap-2">
                                    <select id="db_row" class="form-select"></select>
                                    <button type="button" class="btn btn-primary flex-none" id="db_generate" disabled>Generate</button>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-4">
                                <label class="gk-label">Outputs</label>
                                <div class="d-flex gap-3 flex-wrap pt-1">
                                    <div class="form-check m-0"><input class="form-check-input" type="checkbox" id="opt_pdf" checked><label class="form-check-label" for="opt_pdf">PDF</label></div>
                                    <div class="form-check m-0"><input class="form-check-input" type="checkbox" id="opt_styled" checked><label class="form-check-label" for="opt_styled">Styled Report</label></div>
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-4">
                                <label class="gk-label">Book stock (DMS / DTC)</label>
                                <input type="file" id="book_stock" class="form-control form-control-sm" accept=".xlsx,.xlsm,.xls">
                            </div>
                            <div class="col-lg-5 col-md-4">
                                <label class="gk-label">Physical stock (audit report) <span class="gk-hint" style="margin:0 0 0 6px;text-transform:none;letter-spacing:0;font-weight:400">· reconciliation needs both files</span></label>
                                <input type="file" id="physical_stock" class="form-control form-control-sm" accept=".xlsx,.xlsm,.xls">
                            </div>
                        </div>
                    </div>

                    {{-- 3. Distributors --}}
                    <div class="gk-step" id="step_table">
                        <div class="gk-step-head"><div><h6>Distributors</h6></div></div>
                        <div class="gk-toolbar">
                            <label class="gk-len">Show
                                <select id="per_page" class="form-select form-select-sm">
                                    <option>10</option><option>25</option><option>50</option><option>100</option>
                                </select> entries
                            </label>
                            <input type="search" id="search" class="form-control form-control-sm gk-search" placeholder="Search distributors…">
                        </div>
                        <div class="gk-table-wrap">
                            <table class="gk-table" id="gk_table">
                                <thead></thead>
                                <tbody><tr><td class="gk-empty">Select a project and template to see distributors.</td></tr></tbody>
                            </table>
                        </div>
                        <div class="gk-foot">
                            <span id="page_info" class="gk-hint m-0"></span>
                            <div class="gk-pager" id="pager"></div>
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
        $(function () {
            const csrf = '{{ csrf_token() }}';
            const canMap = @json($canMap);
            const urls = {
                mapping: '{{ route('awl_depo_audit.mapping') }}',
                templates: '{{ route('awl_depo_audit.templates') }}',
                rows: '{{ route('awl_depo_audit.rows') }}',
                distributors: '{{ route('awl_depo_audit.distributors') }}',
                generate: '{{ route('awl_depo_audit.generate') }}',
                status: '{{ url('custom-reports/awl-depo-audit/run') }}',
            };
            $('#project_ids').select2({ placeholder: 'Select the projects this report applies to' });
            $('#db_row').select2({ placeholder: 'All distributors', allowClear: true });

            const esc = t => $('<div>').text(t == null ? '' : t).html();
            const state = { page: 1, perPage: 10, search: '', rowId: '' };
            let searchTimer = null;
            const polls = {};

            const fillTemplates = items => {
                const $t = $('#template_id').empty();
                items.forEach(i => $t.append(new Option(i.label, i.id)));
                $t.val(items.length ? items[0].id : null);   // first (or only) template pre-selected
            };
            const selectedProjects = () => { const v = $('#active_project').val(); return v ? [v] : []; };

            // The one project in use is picked from the mapped ones.
            function syncActive() {
                const $a = $('#active_project'), keep = $a.val();
                $a.empty();
                $('#project_ids option:selected').each(function () { $a.append(new Option($(this).text(), this.value)); });
                $a.val($a.find('option[value="' + keep + '"]').length ? keep : ($a.find('option:first').val() || null));
            }

            function loadTemplates() {
                const ids = selectedProjects();
                if (!ids.length) { fillTemplates([]); loadDistributors(); return; }
                $.getJSON(urls.templates, { project_ids: ids }, function (items) {
                    fillTemplates(items);
                    state.page = 1; loadDistributors();
                });
            }

            function loadRows() {
                const t = $('#template_id').val();
                if (!t) {
                    $('#gk_table thead').empty();
                    $('#gk_table tbody').html('<tr><td class="gk-empty">Select a project and template to see distributors.</td></tr>');
                    $('#page_info').text(''); $('#pager').empty();
                    return;
                }
                $.getJSON(urls.rows, { project_template_id: t, page: state.page, per_page: state.perPage, search: state.search, row_id: state.rowId }, render);
            }

            function actionCell(r) {
                if (r.latest) return links(r.latest);
                return '<button type="button" class="btn btn-primary gk-gen" data-row="' + r.row_id + '">Generate</button>';
            }
            function links(r) {
                const a = (url, label) => url ? '<a href="' + url + '">' + label + '</a>' : '';
                return '<span class="gk-links">' + a(r.report_url, 'Excel') + a(r.pdf_url, 'PDF') + a(r.styled_url, 'Styled') + a(r.zip_url, 'Photos') + '</span>';
            }

            function render(d) {
                state.page = d.page;
                $('#gk_table thead').html('<tr><th class="gk-act">Action</th>' + d.columns.map(c => '<th>' + esc(c) + '</th>').join('') + '</tr>');
                if (!d.rows.length) {
                    $('#gk_table tbody').html('<tr><td class="gk-empty" colspan="' + (d.columns.length + 1) + '">No distributors found.</td></tr>');
                } else {
                    $('#gk_table tbody').html(d.rows.map(r =>
                        '<tr data-row="' + r.row_id + '"><td class="gk-act">' + actionCell(r) + '</td>' +
                        r.cells.map(c => '<td title="' + esc(c) + '">' + esc(c) + '</td>').join('') + '</tr>').join(''));
                }
                const from = d.total ? (d.page - 1) * d.per_page + 1 : 0, to = Math.min(d.page * d.per_page, d.total);
                $('#page_info').text('Showing ' + from + ' to ' + to + ' of ' + d.total + ' entries');
                renderPager(d.page, d.pages);
            }

            function renderPager(page, pages) {
                const btn = (label, p, opts = {}) => '<button type="button" data-page="' + p + '"' + (opts.disabled ? ' disabled' : '') +
                    (opts.active ? ' class="active"' : '') + '>' + label + '</button>';
                let html = btn('‹', page - 1, { disabled: page <= 1 });
                const shown = new Set([1, pages, page - 1, page, page + 1]);
                let last = 0;
                for (let p = 1; p <= pages; p++) {
                    if (!shown.has(p)) continue;
                    if (p - last > 1) html += '<button type="button" disabled>…</button>';
                    html += btn(p, p, { active: p === page });
                    last = p;
                }
                html += btn('›', page + 1, { disabled: page >= pages });
                $('#pager').html(html);
            }

            $('#pager').on('click', 'button[data-page]', function () { state.page = +$(this).data('page'); loadRows(); });
            $('#per_page').on('change', function () { state.perPage = +this.value; state.page = 1; loadRows(); });
            $('#search').on('input', function () {
                clearTimeout(searchTimer); const v = this.value;
                searchTimer = setTimeout(() => { state.search = v; state.page = 1; loadRows(); }, 300);
            });

            let saveTimer = null;
            $('#active_project').on('change', loadTemplates);
            $('#project_ids').on('change', function () {
                syncActive();
                loadTemplates();
                clearTimeout(saveTimer);
                saveTimer = setTimeout(function () {
                    $.post(urls.mapping, { _token: csrf, project_ids: selectedProjects() }, function () {
                        $('#saved_chip').addClass('show'); setTimeout(() => $('#saved_chip').removeClass('show'), 1800);
                    });
                }, 400);
            });
            $('#template_id').on('change', function () { state.page = 1; loadDistributors(); });

            // Distributor dropdown: narrows the table to one DB and enables its Generate button.
            function loadDistributors() {
                const t = $('#template_id').val(), $d = $('#db_row').empty().append(new Option('', ''));
                state.rowId = ''; $('#db_generate').prop('disabled', true);
                if (!t) { $d.trigger('change.select2'); loadRows(); return; }
                $.getJSON(urls.distributors, { project_template_id: t }, function (items) {
                    items.forEach(i => $d.append(new Option(i.label, i.id)));
                    $d.val('').trigger('change.select2');
                    loadRows();
                });
            }
            $('#db_row').on('change', function () {
                state.rowId = this.value || ''; state.page = 1;
                $('#db_generate').prop('disabled', !state.rowId);
                loadRows();
            });
            $('#db_generate').on('click', function () {
                if (!state.rowId) return;
                // The table is narrowed to this one row, so progress and downloads show in its action cell.
                startGenerate(state.rowId);
            });

            // ---- per-row generation -------------------------------------------------
            const cell = rowId => $('#gk_table tbody tr[data-row="' + rowId + '"] .gk-act');

            function poll(runId, rowId) {
                $.getJSON(urls.status + '/' + runId + '/status', function (r) {
                    if (r.status === 'done') {
                        cell(rowId).html(links(r));
                    } else if (r.status === 'failed') {
                        cell(rowId).html('<span class="gk-state err" title="' + esc(r.message) + '">Failed</span> ' +
                            '<button type="button" class="btn btn-outline-primary gk-gen" data-row="' + rowId + '">Retry</button>');
                    } else {
                        cell(rowId).html('<span class="gk-state"><span class="spinner-border spinner-border-sm"></span>' + esc(r.message || 'Generating') + '</span>');
                        polls[rowId] = setTimeout(() => poll(runId, rowId), 1500);
                    }
                }).fail(function () { polls[rowId] = setTimeout(() => poll(runId, rowId), 3000); });
            }

            $('#gk_table').on('click', '.gk-gen', function () { startGenerate($(this).data('row')); });

            function startGenerate(rowId) {
                clearTimeout(polls[rowId]);
                cell(rowId).html('<span class="gk-state"><span class="spinner-border spinner-border-sm"></span>Starting…</span>');
                const fd = new FormData();
                fd.append('_token', csrf);
                fd.append('project_template_id', $('#template_id').val());
                fd.append('row_id', rowId);
                fd.append('pdf', $('#opt_pdf').is(':checked') ? 1 : 0);
                fd.append('styled', $('#opt_styled').is(':checked') ? 1 : 0);
                ['book_stock', 'physical_stock'].forEach(id => {
                    const f = document.getElementById(id).files[0];
                    if (f) fd.append(id, f);
                });
                $.ajax({ url: urls.generate, method: 'POST', data: fd, processData: false, contentType: false })
                    .done(r => poll(r.run_id, rowId))
                    .fail(function (xhr) {
                        cell(rowId).html('<span class="gk-state err">' + esc((xhr.responseJSON && xhr.responseJSON.message) || 'Could not start') + '</span> ' +
                            '<button type="button" class="btn btn-outline-primary gk-gen" data-row="' + rowId + '">Retry</button>');
                    });
            }

            loadTemplates();
        });
    </script>
@endsection
