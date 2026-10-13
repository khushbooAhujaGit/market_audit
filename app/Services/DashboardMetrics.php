<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Builds every number shown on the web dashboard.
 *
 * Unit of work ("checklist"): one outlet row × one activity × one repeat instance
 * (row_id + activity_id + activity_sequence) in temp_user_activity_answers_data.
 * Its review state is derived from the answer statuses:
 *   any 3 → sent back · else any 4 → rejected · else any 0/1 → awaiting review · else 5 → verified
 *
 * Who sees what:
 *   Super Admin / Audit Firm → everything
 *   Company User             → projects whose data assigns carry their company_user_id
 *   Agency                   → projects assigned to their agency
 *   Verifier                 → only the rows they may verify (same rule as VerifierAssignment::filterRowIds)
 */
class DashboardMetrics
{
    public const VIEW_ADMIN    = 'admin';
    public const VIEW_CLIENT   = 'client';
    public const VIEW_AGENCY   = 'agency';
    public const VIEW_VERIFIER = 'verifier';

    private const NUMERIC = "'^-?[0-9]+(\\\\.[0-9]+)?$'";

    private User $user;
    private string $view;
    /** null = no project restriction */
    private ?array $scopeProjectIds = null;
    private array $filters;

    public function __construct(User $user, Request $request)
    {
        $this->user = $user;
        $this->view = $this->resolveView($user);
        $this->scopeProjectIds = $this->resolveScopeProjects();
        $this->filters = $this->readFilters($request);
    }

    public function view(): string
    {
        return $this->view;
    }

    public function build(): array
    {
        $kpis = $this->kpis();

        return [
            'view'         => $this->view,
            'filters'      => $this->filters,
            'options'      => $this->filterOptions(),
            'kpis'         => $kpis,
            'trend'        => $this->trend(),
            'projects'     => $this->projectBreakdown(),
            'zones'        => $this->view === self::VIEW_VERIFIER ? [] : $this->zoneBreakdown(),
            'auditors'     => in_array($this->view, [self::VIEW_ADMIN, self::VIEW_AGENCY]) ? $this->auditorProductivity() : [],
            'issues'       => $this->recentIssues(),
            'mapPoints'    => $this->view === self::VIEW_VERIFIER ? [] : $this->mapPoints(),
            'generatedAt'  => now(),
        ];
    }

    // ── Scope ────────────────────────────────────────────────────────────────

    private function resolveView(User $user): string
    {
        if ($user->hasAnyRole(['Super Admin', 'Audit Firm'])) return self::VIEW_ADMIN;
        if ($user->hasRole('Company User')) return self::VIEW_CLIENT;
        if ($user->hasRole('Agency')) return self::VIEW_AGENCY;
        if ($user->hasRole('Verifier')) return self::VIEW_VERIFIER;
        // Any other role with access to reports sees the client-style (read-only) view of nothing
        // unless it is scoped below; default to the narrowest view.
        return self::VIEW_CLIENT;
    }

    private function resolveScopeProjects(): ?array
    {
        $uid = $this->user->id;

        switch ($this->view) {
            case self::VIEW_ADMIN:
                return null;

            case self::VIEW_CLIENT:
                return DB::table('data_assigns')->whereNull('deleted_at')->where('company_user_id', $uid)->pluck('project_id')
                    ->merge(DB::table('project_templates')->where('company_user_id', $uid)->pluck('project_id'))
                    ->filter()->unique()->values()->all();

            case self::VIEW_AGENCY:
                return DB::table('data_assigns')->whereNull('deleted_at')->where('agency_id', $uid)->pluck('project_id')
                    ->merge(DB::table('projects')->whereNull('deleted_at')->where('agency_id', $uid)->pluck('id'))
                    ->filter()->unique()->values()->all();

            case self::VIEW_VERIFIER:
                return DB::table('verifiers')->join('project_templates', 'project_templates.id', '=', 'verifiers.project_template_name_id')
                    ->where('verifiers.user_id', $uid)->pluck('project_templates.project_id')
                    ->merge(DB::table('verifier_row_assigns')->join('project_templates', 'project_templates.id', '=', 'verifier_row_assigns.project_template_id')
                        ->where('verifier_row_assigns.user_id', $uid)->distinct()->pluck('project_templates.project_id'))
                    ->filter()->unique()->values()->all();
        }

        return [];
    }

    private function readFilters(Request $request): array
    {
        $date = function ($v) {
            try {
                return $v ? Carbon::parse($v)->toDateString() : null;
            } catch (\Throwable $e) {
                return null;
            }
        };

        $from = $date($request->query('from'));
        $to   = $date($request->query('to'));
        if ($from && $to && $from > $to) [$from, $to] = [$to, $from];

        return [
            'company_id'        => $request->integer('company_id') ?: null,
            'zone_id'           => $request->integer('zone_id') ?: null,
            'unit_id'           => $request->integer('unit_id') ?: null,
            'project_id'        => $request->integer('project_id') ?: null,
            'from'              => $from,
            'to'                => $to,
            'include_completed' => $request->boolean('include_completed'),
        ];
    }

    /** WHERE fragments on alias p (projects) shared by every query. */
    private function projectWhere(array &$b): string
    {
        $w = ['p.deleted_at IS NULL'];
        $f = $this->filters;

        if (!$f['include_completed']) $w[] = 'p.is_completed = 0';
        foreach (['company_id', 'zone_id', 'unit_id'] as $col) {
            if ($f[$col]) { $w[] = "p.$col = ?"; $b[] = $f[$col]; }
        }
        if ($f['project_id']) { $w[] = 'p.id = ?'; $b[] = $f['project_id']; }

        if ($this->scopeProjectIds !== null) {
            if (empty($this->scopeProjectIds)) {
                $w[] = '1 = 0';
            } else {
                $w[] = 'p.id IN (' . implode(',', array_map('intval', $this->scopeProjectIds)) . ')';
            }
        }
        return implode(' AND ', $w);
    }

    /**
     * The "s" CTE: one row per checklist in scope, with its derived status.
     * $applyDates = false is used for the trend, which picks its own window.
     */
    /** Name of the per-request temporary table holding the checklist aggregate, or null to aggregate inline. */
    private ?string $checklistTable = null;
    private bool $checklistTableTried = false;

    /**
     * Every dashboard query starts from the same per-checklist aggregate of the whole answers
     * table. Build it once per request into a temporary table so the 8+ queries reuse it instead
     * of each re-aggregating the table. Falls back to the inline aggregate if the database user
     * can't create temporary tables.
     */
    private function checklistSource(): string
    {
        if (!$this->checklistTableTried) {
            $this->checklistTableTried = true;
            try {
                DB::statement('DROP TEMPORARY TABLE IF EXISTS dash_checklists');
                DB::statement('CREATE TEMPORARY TABLE dash_checklists (INDEX (row_id)) AS ' . $this->checklistAggregateSql());
                $this->checklistTable = 'dash_checklists';
            } catch (\Throwable $e) {
                report($e);
                $this->checklistTable = null;
            }
        }

        return $this->checklistTable
            ? "SELECT * FROM {$this->checklistTable}"
            : $this->checklistAggregateSql();
    }

    private function checklistAggregateSql(): string
    {
        $num = self::NUMERIC;
        return "SELECT t.row_id, t.activity_id, COALESCE(t.activity_sequence, 0) AS seq,
                   MIN(t.user_id) AS auditor_id,
                   -- DATETIME, not TIMESTAMP: a TIMESTAMP column created from a SELECT gets a zero
                   -- default, which strict mode rejects when building the temporary table.
                   MIN(CAST(t.created_at AS DATETIME)) AS started_at,
                   MAX(CAST(t.updated_at AS DATETIME)) AS last_at,
                   MAX(CASE WHEN t.status = 5 THEN CAST(t.updated_at AS DATETIME) END) AS verified_at,
                   MAX(t.verified_by) AS verified_by,
                   SUM(t.status = 3) AS n_sb, SUM(t.status = 4) AS n_rj,
                   SUM(t.status = 5) AS n_vf, SUM(t.status IN (0, 1)) AS n_pend,
                   MAX(t.otp_verified_status = 1 AND t.mobile_otp IS NOT NULL) AS otp_ok,
                   MAX(CASE WHEN t.status IN (3, 4) THEN t.remark END) AS remark,
                   AVG(CASE WHEN t.latitude REGEXP $num AND t.latitude + 0 <> 0 THEN t.latitude + 0 END) AS lat,
                   AVG(CASE WHEN t.longitude REGEXP $num AND t.longitude + 0 <> 0 THEN t.longitude + 0 END) AS lng
            FROM temp_user_activity_answers_data t
            WHERE t.deleted_at IS NULL
            GROUP BY t.row_id, t.activity_id, COALESCE(t.activity_sequence, 0)";
    }

    private function checklistCte(array &$b, bool $applyDates = true): string
    {
        $sql = "WITH a AS (
            " . $this->checklistSource() . "
        ), s AS (
            SELECT a.*,
                   CASE WHEN a.n_sb > 0 THEN 'sent_back'
                        WHEN a.n_rj > 0 THEN 'rejected'
                        WHEN a.n_pend > 0 THEN 'pending'
                        ELSE 'verified' END AS st,
                   r.project_template_id, p.id AS project_id, p.project_name, p.zone_id, p.unit_id, p.company_id
            FROM a
            -- STRAIGHT_JOIN keeps this order (checklist -> row -> template -> project). Without it
            -- MySQL pairs every checklist with every project first and the dashboard takes ~10s per query.
            STRAIGHT_JOIN project_template_name_values_new r ON r.id = a.row_id
            STRAIGHT_JOIN project_templates pt ON pt.id = r.project_template_id
            STRAIGHT_JOIN projects p ON p.id = pt.project_id
            WHERE " . $this->projectWhere($b);

        if ($applyDates) {
            if ($this->filters['from']) { $sql .= ' AND a.started_at >= ?'; $b[] = $this->filters['from'] . ' 00:00:00'; }
            if ($this->filters['to'])   { $sql .= ' AND a.started_at <= ?'; $b[] = $this->filters['to'] . ' 23:59:59'; }
        }

        if ($this->view === self::VIEW_VERIFIER) {
            $sql .= ' AND (EXISTS (SELECT 1 FROM verifier_row_assigns v
                                    WHERE v.user_id = ? AND v.project_template_id = r.project_template_id
                                      AND v.activity_id = a.activity_id AND v.row_id = a.row_id)
                        OR EXISTS (SELECT 1 FROM verifiers v
                                    WHERE v.user_id = ? AND v.project_template_name_id = r.project_template_id
                                      AND v.legacy_max_row_id IS NOT NULL AND a.row_id <= v.legacy_max_row_id))';
            $b[] = $this->user->id;
            $b[] = $this->user->id;
        }

        if ($this->view === self::VIEW_AGENCY) {
            // Only work done by this agency's own field staff.
            $sql .= ' AND a.auditor_id IN (SELECT id FROM users WHERE agency_user_id = ? OR id = ?)';
            $b[] = $this->user->id;
            $b[] = $this->user->id;
        }

        return $sql . ')';
    }

    // ── Sections ─────────────────────────────────────────────────────────────

    private function kpis(): array
    {
        $b = [];
        $row = DB::selectOne($this->checklistCte($b) . "
            SELECT COUNT(*) AS checklists,
                   COUNT(DISTINCT row_id) AS outlets_audited,
                   SUM(st = 'pending') AS pending,
                   SUM(st = 'verified') AS verified,
                   SUM(st = 'sent_back') AS sent_back,
                   SUM(st = 'rejected') AS rejected,
                   SUM(otp_ok) AS otp_ok,
                   SUM(lat IS NOT NULL) AS geo_ok,
                   COUNT(DISTINCT auditor_id) AS auditors,
                   SUM(st = 'verified' AND verified_by = ?) AS verified_by_me,
                   SUM(DATE(started_at) = CURDATE()) AS today
            FROM s", array_merge($b, [$this->user->id]));

        $k = array_map('intval', (array) $row);

        $k['assigned'] = $this->view === self::VIEW_VERIFIER ? null : $this->assignedOutlets();
        $k['coverage'] = $k['assigned'] ? round(100 * $k['outlets_audited'] / $k['assigned'], 1) : null;
        $reviewed = $k['verified'] + $k['sent_back'] + $k['rejected'];
        $k['first_pass'] = $reviewed ? round(100 * $k['verified'] / $reviewed, 1) : null;
        $k['otp_rate'] = $k['checklists'] ? round(100 * $k['otp_ok'] / $k['checklists'], 1) : null;
        $k['geo_rate'] = $k['checklists'] ? round(100 * $k['geo_ok'] / $k['checklists'], 1) : null;

        return $k;
    }

    private function assignedOutlets(): int
    {
        $b = [];
        $where = $this->projectWhere($b);
        $agency = '';
        if ($this->view === self::VIEW_AGENCY) {
            $agency = ' AND d.user_id IN (SELECT id FROM users WHERE agency_user_id = ? OR id = ?)';
            $b[] = $this->user->id;
            $b[] = $this->user->id;
        }
        return (int) DB::selectOne("
            SELECT COUNT(DISTINCT u.row_id) AS n
            FROM user_audit_assigns u
            JOIN user_activity_data_assigns d ON d.common_id = u.common_id
            JOIN project_templates pt ON pt.id = d.project_template_id
            JOIN projects p ON p.id = pt.project_id
            WHERE $where $agency", $b)->n;
    }

    private function trend(): array
    {
        $to   = $this->filters['to'] ? Carbon::parse($this->filters['to']) : Carbon::today();
        $from = $this->filters['from'] ? Carbon::parse($this->filters['from']) : $to->copy()->subDays(29);
        if ($from->diffInDays($to) > 180) $from = $to->copy()->subDays(180);

        $b = [];
        $cte = $this->checklistCte($b, false);
        $range = [$from->toDateString() . ' 00:00:00', $to->toDateString() . ' 23:59:59'];

        $submitted = collect(DB::select($cte . "
            SELECT DATE(started_at) AS d, COUNT(*) AS n FROM s
            WHERE started_at BETWEEN ? AND ? GROUP BY DATE(started_at)", array_merge($b, $range)))->pluck('n', 'd');

        $b2 = [];
        $cte2 = $this->checklistCte($b2, false);
        $verified = collect(DB::select($cte2 . "
            SELECT DATE(verified_at) AS d, COUNT(*) AS n FROM s
            WHERE st = 'verified' AND verified_at BETWEEN ? AND ? GROUP BY DATE(verified_at)", array_merge($b2, $range)))->pluck('n', 'd');

        $labels = $sub = $ver = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $labels[] = $key;
            $sub[] = (int) ($submitted[$key] ?? 0);
            $ver[] = (int) ($verified[$key] ?? 0);
        }
        return ['labels' => $labels, 'submitted' => $sub, 'verified' => $ver,
                'from' => $from->toDateString(), 'to' => $to->toDateString()];
    }

    private function projectBreakdown(): array
    {
        $b = [];
        $rows = DB::select($this->checklistCte($b) . "
            SELECT project_id, project_name,
                   COUNT(DISTINCT row_id) AS outlets, COUNT(*) AS checklists,
                   SUM(st = 'pending') AS pending, SUM(st = 'verified') AS verified,
                   SUM(st = 'sent_back') AS sent_back, SUM(st = 'rejected') AS rejected,
                   MAX(last_at) AS last_at
            FROM s GROUP BY project_id, project_name ORDER BY checklists DESC LIMIT 25", $b);

        if ($this->view === self::VIEW_VERIFIER || empty($rows)) return $rows;

        // Assigned outlets per project, for a coverage bar.
        $ids = implode(',', array_map(fn($r) => (int) $r->project_id, $rows));
        $assigned = collect(DB::select("
            SELECT pt.project_id, COUNT(DISTINCT u.row_id) AS n
            FROM user_audit_assigns u
            JOIN user_activity_data_assigns d ON d.common_id = u.common_id
            JOIN project_templates pt ON pt.id = d.project_template_id
            WHERE pt.project_id IN ($ids) GROUP BY pt.project_id"))->pluck('n', 'project_id');

        foreach ($rows as $r) {
            $r->assigned = (int) ($assigned[$r->project_id] ?? 0);
            $r->coverage = $r->assigned ? min(100, round(100 * $r->outlets / $r->assigned, 1)) : null;
        }
        return $rows;
    }

    private function zoneBreakdown(): array
    {
        $b = [];
        return DB::select($this->checklistCte($b) . "
            SELECT COALESCE(z.zone_name, '—') AS zone_name, COALESCE(u.unit_name, '—') AS unit_name,
                   COUNT(DISTINCT s.row_id) AS outlets, COUNT(*) AS checklists,
                   SUM(st = 'pending') AS pending, SUM(st = 'verified') AS verified,
                   SUM(st IN ('sent_back', 'rejected')) AS issues
            FROM s
            LEFT JOIN zones z ON z.id = s.zone_id
            LEFT JOIN units u ON u.id = s.unit_id
            GROUP BY s.zone_id, s.unit_id, z.zone_name, u.unit_name
            ORDER BY checklists DESC LIMIT 20", $b);
    }

    private function auditorProductivity(): array
    {
        $b = [];
        return DB::select($this->checklistCte($b) . "
            SELECT s.auditor_id, us.name, us.mobile, us.city,
                   COUNT(DISTINCT s.row_id) AS outlets, COUNT(*) AS checklists,
                   COUNT(DISTINCT DATE(s.started_at)) AS active_days,
                   ROUND(COUNT(*) / GREATEST(COUNT(DISTINCT DATE(s.started_at)), 1), 1) AS per_day,
                   SUM(st IN ('sent_back', 'rejected')) AS issues,
                   SUM(st = 'verified') AS verified,
                   ROUND(100 * SUM(lat IS NOT NULL) / COUNT(*), 0) AS geo_pct,
                   MAX(s.last_at) AS last_at
            FROM s LEFT JOIN users us ON us.id = s.auditor_id
            GROUP BY s.auditor_id, us.name, us.mobile, us.city
            ORDER BY checklists DESC LIMIT 50", $b);
    }

    private function recentIssues(): array
    {
        $b = [];
        return DB::select($this->checklistCte($b) . "
            SELECT s.st, s.project_name, s.last_at, s.row_id, s.activity_id,
                   ac.activity_name, us.name AS auditor,
                   JSON_UNQUOTE(JSON_EXTRACT(r.template_data_json, CONCAT('$.\"', pt.main_header, '\"'))) AS outlet,
                   COALESCE(rm.remark, s.remark) AS remark
            FROM s
            JOIN project_template_name_values_new r ON r.id = s.row_id
            JOIN project_templates pt ON pt.id = r.project_template_id
            LEFT JOIN activities ac ON ac.id = s.activity_id
            LEFT JOIN users us ON us.id = s.auditor_id
            LEFT JOIN remark_masters rm ON s.remark REGEXP '^[0-9]+$' AND rm.id = s.remark
            WHERE s.st IN ('sent_back', 'rejected')
            ORDER BY s.last_at DESC LIMIT 10", $b);
    }

    private function mapPoints(): array
    {
        $b = [];
        $rows = DB::select($this->checklistCte($b) . "
            SELECT ROUND(s.lat, 5) AS lat, ROUND(s.lng, 5) AS lng, s.st, s.project_name,
                   DATE_FORMAT(s.started_at, '%d %b %Y %H:%i') AS at,
                   us.name AS auditor,
                   JSON_UNQUOTE(JSON_EXTRACT(r.template_data_json, CONCAT('$.\"', pt.main_header, '\"'))) AS outlet
            FROM s
            JOIN project_template_name_values_new r ON r.id = s.row_id
            JOIN project_templates pt ON pt.id = r.project_template_id
            LEFT JOIN users us ON us.id = s.auditor_id
            WHERE s.lat IS NOT NULL AND s.lng IS NOT NULL
            ORDER BY s.started_at DESC LIMIT 1500", $b);

        // Auditor names are internal; clients see outlet, project and status only.
        if ($this->view === self::VIEW_CLIENT) {
            foreach ($rows as $r) unset($r->auditor);
        }
        return $rows;
    }

    private function filterOptions(): array
    {
        $b = [];
        $saved = $this->filters;
        // Options list everything in scope, not just what the current filter narrows to.
        $this->filters = array_merge($saved, ['company_id' => null, 'zone_id' => null, 'unit_id' => null, 'project_id' => null]);
        $where = $this->projectWhere($b);
        $this->filters = $saved;

        $projects = DB::select("
            SELECT p.id, p.project_name, p.company_id, p.zone_id, p.unit_id, p.is_completed,
                   c.company_name, z.zone_name, u.unit_name
            FROM projects p
            LEFT JOIN companies c ON c.id = p.company_id
            LEFT JOIN zones z ON z.id = p.zone_id
            LEFT JOIN units u ON u.id = p.unit_id
            WHERE $where ORDER BY p.project_name", $b);

        $pick = fn($id, $name) => collect($projects)->filter(fn($p) => $p->$id)->mapWithKeys(fn($p) => [$p->$id => $p->$name ?? ('#' . $p->$id)])->sort()->all();

        return [
            'companies' => $pick('company_id', 'company_name'),
            'zones'     => $pick('zone_id', 'zone_name'),
            'units'     => $pick('unit_id', 'unit_name'),
            'projects'  => $projects,
        ];
    }
}
