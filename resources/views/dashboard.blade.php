@extends('template.layouts.simple.master')
@section('content')
@php
    $isAdmin    = $view === 'admin';
    $isClient   = $view === 'client';
    $isAgency   = $view === 'agency';
    $isVerifier = $view === 'verifier';
    $roleLabel  = ['admin' => 'Admin view', 'client' => 'Client view', 'agency' => 'Agency view', 'verifier' => 'Verifier view'][$view];
    $n   = fn($v) => number_format((int) $v);
    $pct = fn($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.') . '%';
    $k = $kpis;
    $issuesTotal = $k['sent_back'] + $k['rejected'];
    $stLabel = ['pending' => 'Awaiting review', 'verified' => 'Verified', 'sent_back' => 'Sent back', 'rejected' => 'Rejected'];
    $hasFilter = $filters['company_id'] || $filters['zone_id'] || $filters['unit_id'] || $filters['project_id'] || $filters['from'] || $filters['to'] || $filters['include_completed'];
@endphp
<style>
    /* Market Audit dashboard — scoped to .ma-dash so it never leaks into other pages */
    .ma-dash{--ink:#0E1526;--mute:#5B6780;--line:#E3E8F1;--card:#fff;--brand:#042b39;--blue:#1F5BD8;
        --pending:#C98A00;--verified:#13865A;--sent:#D9661F;--rejected:#C9374F;--soft:#F4F6FA;
        color:var(--ink);display:flex;flex-direction:column;gap:20px;padding-bottom:30px}
    .ma-dash .ma-head{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:12px}
    .ma-dash h3{font-size:24px;font-weight:700;margin:0}
    .ma-dash .ma-sub{color:var(--mute);font-size:13px;margin-top:4px}
    .ma-dash .ma-role{display:inline-block;font-size:11px;font-weight:600;letter-spacing:.6px;text-transform:uppercase;
        background:#E8EEF9;color:var(--blue);border-radius:999px;padding:3px 10px;margin-left:8px;vertical-align:middle}
    .ma-dash .ma-card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:18px 20px;min-width:0}
    .ma-dash .ma-card h5{font-size:15px;font-weight:700;margin:0 0 4px}
    .ma-dash .ma-card .ma-hint{font-size:12px;color:var(--mute);margin:0 0 12px}
    .ma-dash .ma-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;align-items:end}
    .ma-dash .ma-filters label{font-size:11px;font-weight:600;color:var(--mute);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;display:block}
    .ma-dash .ma-filters select,.ma-dash .ma-filters input[type=date]{width:100%;font-size:13px;border:1px solid var(--line);border-radius:8px;padding:7px 9px;background:#fff;color:var(--ink)}
    .ma-dash .ma-filters .ma-check{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--ink);padding-bottom:8px;text-transform:none;letter-spacing:0;font-weight:500}
    .ma-dash .ma-btns{display:flex;gap:8px}
    .ma-dash .ma-btn{font-size:13px;font-weight:600;border-radius:8px;padding:8px 14px;border:1px solid var(--brand);background:var(--brand);color:#fff;text-decoration:none;cursor:pointer;white-space:nowrap}
    .ma-dash .ma-btn.ghost{background:#fff;color:var(--brand)}
    .ma-dash .ma-btn:focus-visible,.ma-dash a:focus-visible{outline:3px solid #9DB8F5;outline-offset:2px}
    .ma-dash .ma-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px}
    .ma-dash .ma-kpi{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:14px 16px;display:flex;flex-direction:column;gap:4px;min-width:0}
    .ma-dash .ma-kpi .lbl{font-size:12px;color:var(--mute);font-weight:500}
    .ma-dash .ma-kpi .val{font-size:28px;font-weight:700;line-height:1.1;font-variant-numeric:tabular-nums}
    .ma-dash .ma-kpi .foot{font-size:12px;color:var(--mute)}
    .ma-dash .dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:6px;vertical-align:middle}
    .ma-dash .ma-grid{display:grid;grid-template-columns:2fr 1fr;gap:16px}
    .ma-dash .ma-grid.even{grid-template-columns:3fr 2fr}
    @media (max-width:991px){.ma-dash .ma-grid,.ma-dash .ma-grid.even{grid-template-columns:1fr}}
    .ma-dash .ma-table-wrap{overflow-x:auto}
    .ma-dash table.ma-t{width:100%;border-collapse:collapse;font-size:13px}
    .ma-dash table.ma-t th{font-size:11px;font-weight:600;color:var(--mute);text-transform:uppercase;letter-spacing:.5px;text-align:left;padding:8px 10px;border-bottom:1px solid var(--line);white-space:nowrap}
    .ma-dash table.ma-t td{padding:9px 10px;border-bottom:1px solid var(--line);vertical-align:middle}
    .ma-dash table.ma-t td.num,.ma-dash table.ma-t th.num{text-align:right;font-variant-numeric:tabular-nums}
    .ma-dash table.ma-t tr:last-child td{border-bottom:0}
    .ma-dash .ma-bar{height:8px;border-radius:4px;background:var(--soft);overflow:hidden;min-width:90px;display:flex}
    .ma-dash .ma-bar span{display:block;height:100%}
    .ma-dash .pill{display:inline-block;font-size:11px;font-weight:600;border-radius:999px;padding:2px 9px;white-space:nowrap}
    .ma-dash .pill.pending{background:#FFF4D6;color:#8A5E00}
    .ma-dash .pill.verified{background:#DDF4EA;color:#0E6B47}
    .ma-dash .pill.sent_back{background:#FDE8DA;color:#A44A10}
    .ma-dash .pill.rejected{background:#FBE0E5;color:#A3243A}
    .ma-dash #maMap{height:380px;border-radius:10px;border:1px solid var(--line);z-index:0}
    .ma-dash .ma-legend{display:flex;flex-wrap:wrap;gap:14px;font-size:12px;color:var(--mute);margin-top:10px}
    .ma-dash .ma-empty{padding:28px 10px;text-align:center;color:var(--mute);font-size:13px}
    .ma-dash .ma-links{display:flex;flex-wrap:wrap;gap:8px}
    .ma-dash .muted{color:var(--mute)}
</style>

<div class="page-body">
<div class="container-fluid ma-dash">

    {{-- Header --}}
    <div class="ma-head">
        <div>
            <h3>Dashboard <span class="ma-role">{{ $roleLabel }}</span></h3>
            <div class="ma-sub">
                @if($isVerifier) Your verification queue across assigned projects.
                @elseif($isClient) Audit progress for your projects.
                @else Audit progress across {{ $filters['include_completed'] ? 'all' : 'open' }} projects.
                @endif
                Updated {{ \Carbon\Carbon::parse($generatedAt)->format('d M Y, h:i A') }}.
            </div>
        </div>
        <div class="ma-links">
            @if($isVerifier)
                <a class="ma-btn" href="{{ route('user.verification.list') }}">Open my verifications</a>
            @endif
            @can('Non Compliance')
                <a class="ma-btn ghost" href="{{ route('company.nonCompliancePage') }}">Non-compliance</a>
            @endcan
            @can('Reports')
                <a class="ma-btn ghost" href="{{ route('view-report') }}">Reports</a>
            @endcan
            <a class="ma-btn ghost" href="{{ request()->fullUrlWithQuery(['refresh' => 1]) }}">Refresh</a>
        </div>
    </div>

    {{-- Filters --}}
    <form class="ma-card" method="GET" action="{{ route('dashboard') }}" id="maFilters">
        <div class="ma-filters">
            @if(count($options['companies']) > 1)
            <div>
                <label for="ma_company">Company</label>
                <select name="company_id" id="ma_company">
                    <option value="">All companies</option>
                    @foreach($options['companies'] as $id => $name)
                        <option value="{{ $id }}" @selected($filters['company_id'] == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div>
                <label for="ma_zone">Zone</label>
                <select name="zone_id" id="ma_zone">
                    <option value="">All zones</option>
                    @foreach($options['zones'] as $id => $name)
                        <option value="{{ $id }}" @selected($filters['zone_id'] == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="ma_unit">Unit</label>
                <select name="unit_id" id="ma_unit">
                    <option value="">All units</option>
                    @foreach($options['units'] as $id => $name)
                        <option value="{{ $id }}" @selected($filters['unit_id'] == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="grid-column:span 2">
                <label for="ma_project">Project</label>
                <select name="project_id" id="ma_project">
                    <option value="">All projects</option>
                    @foreach($options['projects'] as $p)
                        <option value="{{ $p->id }}" data-company="{{ $p->company_id }}" data-zone="{{ $p->zone_id }}" data-unit="{{ $p->unit_id }}"
                            @selected($filters['project_id'] == $p->id)>{{ $p->project_name }}{{ $p->is_completed ? ' (completed)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="ma_from">From</label>
                <input type="date" name="from" id="ma_from" value="{{ $filters['from'] }}">
            </div>
            <div>
                <label for="ma_to">To</label>
                <input type="date" name="to" id="ma_to" value="{{ $filters['to'] }}">
            </div>
            <label class="ma-check" for="ma_completed">
                <input type="checkbox" name="include_completed" id="ma_completed" value="1" @checked($filters['include_completed'])>
                Include completed projects
            </label>
            <div class="ma-btns">
                <button type="submit" class="ma-btn">Apply</button>
                @if($hasFilter)<a class="ma-btn ghost" href="{{ route('dashboard') }}">Reset</a>@endif
            </div>
        </div>
    </form>

    {{-- KPIs --}}
    <div class="ma-kpis">
        @if($isVerifier)
            <div class="ma-kpi"><span class="lbl"><span class="dot" style="background:var(--pending)"></span>Awaiting review</span><span class="val">{{ $n($k['pending']) }}</span><span class="foot">in your queue</span></div>
            <div class="ma-kpi"><span class="lbl"><span class="dot" style="background:var(--verified)"></span>Verified</span><span class="val">{{ $n($k['verified']) }}</span><span class="foot">{{ $n($k['verified_by_me']) }} verified by you</span></div>
            <div class="ma-kpi"><span class="lbl"><span class="dot" style="background:var(--sent)"></span>Sent back</span><span class="val">{{ $n($k['sent_back']) }}</span><span class="foot">waiting for the auditor</span></div>
            <div class="ma-kpi"><span class="lbl"><span class="dot" style="background:var(--rejected)"></span>Rejected</span><span class="val">{{ $n($k['rejected']) }}</span><span class="foot">excluded from reports</span></div>
            <div class="ma-kpi"><span class="lbl">Checklists in scope</span><span class="val">{{ $n($k['checklists']) }}</span><span class="foot">{{ $n($k['outlets_audited']) }} outlets · {{ $n($k['auditors']) }} auditors</span></div>
            <div class="ma-kpi"><span class="lbl">First-pass approval</span><span class="val">{{ $pct($k['first_pass']) }}</span><span class="foot">of reviewed checklists</span></div>
        @else
            <div class="ma-kpi"><span class="lbl">Outlets assigned</span><span class="val">{{ $n($k['assigned']) }}</span><span class="foot">to auditors, in selected projects</span></div>
            <div class="ma-kpi"><span class="lbl">Outlets audited</span><span class="val">{{ $n($k['outlets_audited']) }}</span><span class="foot">{{ $pct($k['coverage']) }} coverage</span></div>
            <div class="ma-kpi"><span class="lbl">Checklists submitted</span><span class="val">{{ $n($k['checklists']) }}</span><span class="foot">{{ $n($k['today']) }} today</span></div>
            <div class="ma-kpi"><span class="lbl"><span class="dot" style="background:var(--pending)"></span>Awaiting review</span><span class="val">{{ $n($k['pending']) }}</span><span class="foot">not yet verified</span></div>
            <div class="ma-kpi"><span class="lbl"><span class="dot" style="background:var(--verified)"></span>Verified</span><span class="val">{{ $n($k['verified']) }}</span><span class="foot">{{ $pct($k['first_pass']) }} first-pass approval</span></div>
            <div class="ma-kpi"><span class="lbl"><span class="dot" style="background:var(--rejected)"></span>Sent back / rejected</span><span class="val">{{ $n($issuesTotal) }}</span><span class="foot">{{ $n($k['sent_back']) }} sent back · {{ $n($k['rejected']) }} rejected</span></div>
            @unless($isClient)
            <div class="ma-kpi"><span class="lbl">Active auditors</span><span class="val">{{ $n($k['auditors']) }}</span><span class="foot">submitted in this period</span></div>
            @endunless
            <div class="ma-kpi"><span class="lbl">Geo-tagged</span><span class="val">{{ $pct($k['geo_rate']) }}</span><span class="foot">checklists with GPS location</span></div>
            <div class="ma-kpi"><span class="lbl">OTP confirmed</span><span class="val">{{ $pct($k['otp_rate']) }}</span><span class="foot">outlet owner confirmed visit</span></div>
        @endif
    </div>

    {{-- Trend + status --}}
    <div class="ma-grid">
        <div class="ma-card">
            <h5>Daily activity</h5>
            <p class="ma-hint">Checklists submitted and verified per day, {{ \Carbon\Carbon::parse($trend['from'])->format('d M') }} – {{ \Carbon\Carbon::parse($trend['to'])->format('d M Y') }}</p>
            @if(array_sum($trend['submitted']) + array_sum($trend['verified']) > 0)
                <div id="maTrend"></div>
            @else
                <div class="ma-empty">No checklists were submitted in this period.</div>
            @endif
        </div>
        <div class="ma-card">
            <h5>Review status</h5>
            <p class="ma-hint">Where every submitted checklist stands</p>
            @if($k['checklists'] > 0)
                <div id="maStatus"></div>
            @else
                <div class="ma-empty">Nothing submitted yet.</div>
            @endif
        </div>
    </div>

    {{-- Projects --}}
    <div class="ma-card">
        <h5>{{ $isVerifier ? 'Your queue by project' : 'Project progress' }}</h5>
        <p class="ma-hint">Top {{ count($projects) }} projects by checklists submitted. Click a project to filter the dashboard.</p>
        @if(count($projects))
        <div class="ma-table-wrap">
            <table class="ma-t">
                <thead><tr>
                    <th>Project</th>
                    @unless($isVerifier)<th class="num">Assigned</th>@endunless
                    <th class="num">Outlets audited</th>
                    @unless($isVerifier)<th>Coverage</th>@endunless
                    <th class="num">Checklists</th>
                    <th>Review status</th>
                    <th class="num">Awaiting</th>
                    <th class="num">Verified</th>
                    <th class="num">Sent back</th>
                    <th class="num">Rejected</th>
                    <th>Last activity</th>
                </tr></thead>
                <tbody>
                @foreach($projects as $p)
                    @php $tot = max(1, $p->checklists); @endphp
                    <tr>
                        <td style="min-width:200px"><a href="{{ route('dashboard', array_filter(['project_id' => $p->project_id, 'include_completed' => $filters['include_completed'] ? 1 : null])) }}">{{ $p->project_name }}</a></td>
                        @unless($isVerifier)<td class="num">{{ $n($p->assigned ?? 0) }}</td>@endunless
                        <td class="num">{{ $n($p->outlets) }}</td>
                        @unless($isVerifier)
                        <td style="min-width:120px">
                            @if(($p->coverage ?? null) !== null)
                                <div class="ma-bar" title="{{ $pct($p->coverage) }}"><span style="width:{{ $p->coverage }}%;background:var(--blue)"></span></div>
                                <span class="muted" style="font-size:11px">{{ $pct($p->coverage) }}</span>
                            @else <span class="muted">—</span> @endif
                        </td>
                        @endunless
                        <td class="num">{{ $n($p->checklists) }}</td>
                        <td style="min-width:120px">
                            <div class="ma-bar" title="{{ $n($p->verified) }} verified · {{ $n($p->pending) }} awaiting · {{ $n($p->sent_back) }} sent back · {{ $n($p->rejected) }} rejected">
                                <span style="width:{{ 100*$p->verified/$tot }}%;background:var(--verified)"></span>
                                <span style="width:{{ 100*$p->pending/$tot }}%;background:var(--pending)"></span>
                                <span style="width:{{ 100*$p->sent_back/$tot }}%;background:var(--sent)"></span>
                                <span style="width:{{ 100*$p->rejected/$tot }}%;background:var(--rejected)"></span>
                            </div>
                        </td>
                        <td class="num">{{ $n($p->pending) }}</td>
                        <td class="num">{{ $n($p->verified) }}</td>
                        <td class="num">{{ $n($p->sent_back) }}</td>
                        <td class="num">{{ $n($p->rejected) }}</td>
                        <td class="muted" style="white-space:nowrap">{{ $p->last_at ? \Carbon\Carbon::parse($p->last_at)->diffForHumans() : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @else
            <div class="ma-empty">No audit activity matches these filters.</div>
        @endif
    </div>

    @unless($isVerifier)
    {{-- Map + zones --}}
    <div class="ma-grid even">
        <div class="ma-card">
            <h5>Audit locations</h5>
            <p class="ma-hint">GPS position of the latest {{ $n(count($mapPoints)) }} geo-tagged checklists. Click a dot for details.</p>
            @if(count($mapPoints))
                <div id="maMap"></div>
                <div class="ma-legend">
                    @foreach(['verified','pending','sent_back','rejected'] as $s)
                        <span><span class="dot" style="width:10px;height:10px;background:var(--{{ $s === 'sent_back' ? 'sent' : $s }})"></span>{{ $stLabel[$s] }}</span>
                    @endforeach
                </div>
            @else
                <div class="ma-empty">No geo-tagged checklists for these filters.</div>
            @endif
        </div>
        <div class="ma-card">
            <h5>By zone and unit</h5>
            <p class="ma-hint">Outlets audited and open issues</p>
            @if(count($zones))
            <div class="ma-table-wrap">
                <table class="ma-t">
                    <thead><tr><th>Zone · Unit</th><th class="num">Outlets</th><th class="num">Checklists</th><th class="num">Verified</th><th class="num">Issues</th></tr></thead>
                    <tbody>
                    @foreach($zones as $z)
                        <tr>
                            <td>{{ $z->zone_name }} <span class="muted">· {{ $z->unit_name }}</span></td>
                            <td class="num">{{ $n($z->outlets) }}</td>
                            <td class="num">{{ $n($z->checklists) }}</td>
                            <td class="num">{{ $n($z->verified) }}</td>
                            <td class="num">@if($z->issues)<span class="pill rejected">{{ $n($z->issues) }}</span>@else 0 @endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @else
                <div class="ma-empty">No data yet.</div>
            @endif
        </div>
    </div>
    @endunless

    @if(count($auditors))
    {{-- Auditor productivity --}}
    <div class="ma-card">
        <h5>Auditor productivity</h5>
        <p class="ma-hint">Top {{ count($auditors) }} auditors by checklists submitted in this period</p>
        <div class="ma-table-wrap">
            <table class="ma-t">
                <thead><tr>
                    <th>Auditor</th><th>City</th>
                    <th class="num">Outlets</th><th class="num">Checklists</th><th class="num">Active days</th><th class="num">Per day</th>
                    <th class="num">Verified</th><th class="num">Sent back / rejected</th><th class="num">Geo-tagged</th><th>Last activity</th>
                </tr></thead>
                <tbody>
                @foreach($auditors as $a)
                    @php $issueRate = $a->checklists ? round(100*$a->issues/$a->checklists) : 0; @endphp
                    <tr>
                        <td style="min-width:160px"><b>{{ $a->name ?? ('User #' . $a->auditor_id) }}</b>@if($isAdmin && $a->mobile)<br><span class="muted" style="font-size:12px">{{ $a->mobile }}</span>@endif</td>
                        <td class="muted">{{ $a->city ?: '—' }}</td>
                        <td class="num">{{ $n($a->outlets) }}</td>
                        <td class="num">{{ $n($a->checklists) }}</td>
                        <td class="num">{{ $n($a->active_days) }}</td>
                        <td class="num">{{ $a->per_day }}</td>
                        <td class="num">{{ $n($a->verified) }}</td>
                        <td class="num">@if($a->issues)<span class="pill {{ $issueRate >= 20 ? 'rejected' : 'sent_back' }}">{{ $n($a->issues) }} · {{ $issueRate }}%</span>@else 0 @endif</td>
                        <td class="num">{{ $a->geo_pct }}%</td>
                        <td class="muted" style="white-space:nowrap">{{ $a->last_at ? \Carbon\Carbon::parse($a->last_at)->diffForHumans() : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Recent issues --}}
    <div class="ma-card">
        <h5>Latest sent back and rejected</h5>
        <p class="ma-hint">The 10 most recent checklists that need attention</p>
        @if(count($issues))
        <div class="ma-table-wrap">
            <table class="ma-t">
                <thead><tr><th>Status</th><th>Outlet</th><th>Project · Activity</th>@unless($isClient)<th>Auditor</th>@endunless<th>Remark</th><th>When</th></tr></thead>
                <tbody>
                @foreach($issues as $i)
                    <tr>
                        <td><span class="pill {{ $i->st }}">{{ $stLabel[$i->st] }}</span></td>
                        <td>{{ $i->outlet ?: ('Row #' . $i->row_id) }}</td>
                        <td>{{ $i->project_name }} <span class="muted">· {{ $i->activity_name }}</span></td>
                        @unless($isClient)<td>{{ $i->auditor ?? '—' }}</td>@endunless
                        <td>{{ $i->remark ?: '—' }}</td>
                        <td class="muted" style="white-space:nowrap">{{ \Carbon\Carbon::parse($i->last_at)->diffForHumans() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @else
            <div class="ma-empty">Nothing sent back or rejected.</div>
        @endif
    </div>

</div>
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="{{ asset('assets/css/vendors/leaflet.css') }}">
<script src="{{ asset('assets/js/chart/apex-chart/apex-chart.js') }}"></script>
<script src="{{ asset('assets/js/map-js/leaflet.js') }}"></script>
<script>
(function () {
    var C = {verified: '#13865A', pending: '#C98A00', sent_back: '#D9661F', rejected: '#C9374F', blue: '#1F5BD8'};
    var LABEL = @json($stLabel);
    var font = 'Outfit, sans-serif';

    // Daily trend
    var trend = @json($trend);
    var trendEl = document.getElementById('maTrend');
    if (trendEl && window.ApexCharts) {
        new ApexCharts(trendEl, {
            chart: {type: 'line', height: 300, toolbar: {show: false}, fontFamily: font, animations: {enabled: false}},
            series: [
                {name: 'Submitted', type: 'column', data: trend.submitted},
                {name: 'Verified', type: 'line', data: trend.verified}
            ],
            colors: [C.blue, C.verified],
            stroke: {width: [0, 3], curve: 'smooth'},
            plotOptions: {bar: {columnWidth: '60%', borderRadius: 3}},
            dataLabels: {enabled: false},
            xaxis: {type: 'category', categories: trend.labels.map(function (d) {
                var p = d.split('-'); return p[2] + ' ' + ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][+p[1] - 1];
            }), tickAmount: 10, labels: {rotate: 0, hideOverlappingLabels: true}},
            yaxis: {min: 0, forceNiceScale: true, labels: {formatter: function (v) { return Math.round(v); }}},
            grid: {borderColor: '#E3E8F1', strokeDashArray: 3},
            legend: {position: 'top', horizontalAlign: 'left'},
            tooltip: {shared: true, intersect: false}
        }).render();
    }

    // Review status donut
    var k = @json($kpis);
    var statusEl = document.getElementById('maStatus');
    if (statusEl && window.ApexCharts) {
        var keys = ['verified', 'pending', 'sent_back', 'rejected'];
        new ApexCharts(statusEl, {
            chart: {type: 'donut', height: 300, fontFamily: font, animations: {enabled: false}},
            series: keys.map(function (s) { return k[s] || 0; }),
            labels: keys.map(function (s) { return LABEL[s]; }),
            colors: keys.map(function (s) { return C[s]; }),
            legend: {position: 'bottom'},
            dataLabels: {enabled: false},
            plotOptions: {pie: {donut: {size: '68%', labels: {show: true, total: {show: true, label: 'Checklists',
                formatter: function () { return (k.checklists || 0).toLocaleString('en-IN'); }}}}}}
        }).render();
    }

    // Map
    var pts = @json($mapPoints);
    var mapEl = document.getElementById('maMap');
    if (mapEl && window.L && pts.length) {
        var map = L.map(mapEl, {scrollWheelZoom: false});
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); };
        var bounds = [];
        pts.forEach(function (p) {
            var lat = parseFloat(p.lat), lng = parseFloat(p.lng);
            if (!isFinite(lat) || !isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) return;
            bounds.push([lat, lng]);
            L.circleMarker([lat, lng], {radius: 6, weight: 1, color: '#fff', fillColor: C[p.st] || C.blue, fillOpacity: .9})
                .bindPopup('<b>' + esc(p.outlet || 'Outlet') + '</b><br>' + esc(p.project_name) +
                    (p.auditor ? '<br>Auditor: ' + esc(p.auditor) : '') +
                    '<br>' + esc(p.at) + '<br><span style="color:' + (C[p.st] || C.blue) + ';font-weight:600">' + esc(LABEL[p.st]) + '</span>')
                .addTo(map);
        });
        if (bounds.length) map.fitBounds(bounds, {padding: [20, 20], maxZoom: 14}); else map.setView([22.5, 79], 4);
    }

    // Narrow the project list to the chosen company / zone / unit
    var proj = document.getElementById('ma_project');
    function narrow() {
        var f = {company: (document.getElementById('ma_company') || {}).value || '',
                 zone: document.getElementById('ma_zone').value, unit: document.getElementById('ma_unit').value};
        Array.prototype.forEach.call(proj.options, function (o) {
            if (!o.value) return;
            var ok = (!f.company || o.dataset.company === f.company) && (!f.zone || o.dataset.zone === f.zone) && (!f.unit || o.dataset.unit === f.unit);
            o.hidden = !ok;
            if (!ok && o.selected) proj.value = '';
        });
    }
    ['ma_company', 'ma_zone', 'ma_unit'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('change', narrow);
    });
    narrow();
})();
</script>
@endsection
