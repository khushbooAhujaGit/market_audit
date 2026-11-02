@extends('template.layouts.simple.master')
@section('style')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .gk-hero { display:flex; align-items:center; gap:16px; padding:22px 26px; border-radius:14px;
            background:linear-gradient(120deg,#042b39 0%,#0b4a63 100%); color:#fff; margin-bottom:22px; }
        .gk-hero h4 { color:#fff; margin:0 0 4px; font-weight:600; letter-spacing:.2px; }
        .gk-hero p { margin:0; color:rgba(255,255,255,.78); font-size:13px; max-width:760px; }
        .gk-hero .gk-badge { margin-left:auto; flex:none; font-size:11px; letter-spacing:.8px; text-transform:uppercase;
            padding:6px 12px; border:1px solid rgba(255,255,255,.35); border-radius:999px; color:#fff; }
        .gk-step { background:#fff; border:1px solid #e6ebf1; border-radius:14px; padding:20px 22px; margin-bottom:16px; }
        .gk-step-head { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
        .gk-num { width:28px; height:28px; border-radius:50%; background:#042b39; color:#fff; display:flex;
            align-items:center; justify-content:center; font-size:13px; font-weight:600; flex:none; }
        .gk-step.is-locked { opacity:.55; }
        .gk-step-head h6 { margin:0; font-weight:600; color:#042b39; }
        .gk-step-head small { color:#7b8794; display:block; font-size:12px; margin-top:1px; }
        .gk-hint { font-size:12px; color:#7b8794; margin-top:6px; }
        .gk-panel .select2-container--default .select2-selection--multiple { min-height:38px; max-height:38px; overflow:hidden; }
        .gk-panel { background:linear-gradient(180deg,#eaf2f7 0%,#e1ecf3 100%); border:1px solid #cfdfe9; border-left:4px solid #042b39;
            border-radius:14px; padding:16px 20px 18px; margin-bottom:16px; box-shadow:0 1px 3px rgba(4,43,57,.08); }
        .gk-label { display:flex; height:18px; align-items:center; font-size:11px; font-weight:600; letter-spacing:.6px; text-transform:uppercase; color:#042b39; margin-bottom:6px; }
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
        .gk-toolbar { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
        .gk-len { display:flex; align-items:center; gap:8px; font-size:13px; color:#52606d; margin:0; }
        .gk-len select { width:auto; }
        .gk-search { max-width:260px; }
        .gk-table-wrap { overflow-x:auto; border:1px solid #e6ebf1; border-radius:10px; }
        .gk-table { width:100%; border-collapse:collapse; font-size:13px; white-space:nowrap; }
        .gk-table th { background:#f4f7fa; color:#042b39; font-weight:600; text-align:left; padding:10px 12px; border-bottom:1px solid #e6ebf1; position:sticky; top:0; }
        .gk-table td { padding:9px 12px; border-bottom:1px solid #eef2f6; color:#33414f; max-width:360px; overflow:hidden; text-overflow:ellipsis; }
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
        .gk-foot { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-top:12px; }
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
                            <h4>Godrej Report</h4>
                            <p>Choose a project and a date range to generate the audit report in the client format (one row per audit),
                                together with a ZIP of the audit photos.</p>
                        </div>
                        <span class="gk-badge">Custom Report</span>
                    </div>

                    <div class="gk-panel">
                        <div class="row g-3 align-items-end">
                            <div class="col-lg-3 col-md-6">
                                <label class="gk-label">Project</label>
                                <select id="project_id" class="form-select">
                                    @foreach ($projects as $project)
                                        <option value="{{ $project->id }}" @selected(stripos($project->project_name, 'godrej') !== false)>{{ $project->project_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <label class="gk-label">Data template</label>
                                <select id="template_id" class="form-select"></select>
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <label class="gk-label">From</label>
                                <input type="date" id="from_date" class="form-control" value="{{ now()->toDateString() }}">
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <label class="gk-label">To</label>
                                <input type="date" id="to_date" class="form-control" value="{{ now()->toDateString() }}">
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <button type="button" id="generate" class="btn btn-primary w-100">Generate</button>
                            </div>
                        </div>
                    </div>

                    <div class="gk-step">
                        <div class="gk-step-head"><div><h6>Reports</h6><small>Your latest reports. Address lookup takes about a second per audit, so large ranges take a while.</small></div></div>
                        <div class="gk-table-wrap">
                            <table class="gk-table">
                                <thead><tr><th>Requested</th><th>From</th><th>To</th><th>Status</th><th>Download</th></tr></thead>
                                <tbody id="runs">
                                    @forelse ($recentRuns as $run)
                                        <tr data-run="{{ $run->id }}" data-status="{{ $run->status }}">
                                            <td>{{ \Carbon\Carbon::parse($run->created_at)->format('d M Y H:i') }}</td>
                                            <td>{{ $run->header_value }}</td><td>{{ $run->sub_header_value }}</td>
                                            <td class="st">{{ ucfirst($run->status) }}{{ $run->message ? ' — ' . $run->message : '' }}</td>
                                            <td class="dl gk-links">
                                                @if ($run->status === 'done')
                                                    <a href="{{ url('custom-reports/godrej/run/' . $run->id . '/download/report') }}">Report</a>
                                                    @if ($run->zip_path)<a href="{{ url('custom-reports/godrej/run/' . $run->id . '/download/zip') }}">Photos</a>@endif
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr class="none"><td class="gk-empty" colspan="5">No reports yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script>
        $(function () {
            const csrf = '{{ csrf_token() }}';
            const urls = {
                templates: '{{ route('godrej.templates') }}',
                generate: '{{ route('godrej.generate') }}',
                status: '{{ url('custom-reports/godrej/run') }}',
            };
            const esc = t => $('<div>').text(t == null ? '' : t).html();

            function loadTemplates() {
                $.getJSON(urls.templates, { project_id: $('#project_id').val() }, function (items) {
                    const $t = $('#template_id').empty();
                    items.forEach(i => $t.append(new Option(i.label, i.id)));
                    $t.val(items.length ? items[0].id : null);
                });
            }
            $('#project_id').on('change', loadTemplates);

            function paint($tr, r) {
                $tr.find('.st').text(r.status.charAt(0).toUpperCase() + r.status.slice(1) + (r.message ? ' — ' + r.message : ''));
                $tr.find('.dl').html(r.status === 'done'
                    ? '<a href="' + r.report_url + '">Report</a>' + (r.zip_url ? '<a href="' + r.zip_url + '">Photos</a>' : '') : '');
            }
            function poll(runId) {
                const $tr = $('#runs tr[data-run="' + runId + '"]');
                $.getJSON(urls.status + '/' + runId + '/status', function (r) {
                    paint($tr, r);
                    if (r.status !== 'done' && r.status !== 'failed') setTimeout(() => poll(runId), 2000);
                }).fail(() => setTimeout(() => poll(runId), 4000));
            }
            $('#runs tr[data-run]').each(function () {
                const s = $(this).data('status');
                if (s === 'queued' || s === 'running') poll($(this).data('run'));
            });

            $('#generate').on('click', function () {
                const $b = $(this).prop('disabled', true);
                $.post(urls.generate, { _token: csrf, project_template_id: $('#template_id').val(),
                                        from_date: $('#from_date').val(), to_date: $('#to_date').val() })
                    .done(function (r) {
                        $('#runs .none').remove();
                        $('#runs').prepend('<tr data-run="' + r.run_id + '"><td>now</td><td>' + esc($('#from_date').val()) + '</td><td>' + esc($('#to_date').val()) +
                            '</td><td class="st">Queued</td><td class="dl gk-links"></td></tr>');
                        poll(r.run_id);
                    })
                    .fail(xhr => alert((xhr.responseJSON && xhr.responseJSON.message) || 'Could not start the report.'))
                    .always(() => $b.prop('disabled', false));
            });

            loadTemplates();
        });
    </script>
@endsection
