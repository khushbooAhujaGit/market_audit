<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Which distributor an outlet belongs to, through the project mapping:
 * the outlet's Own Template Head value (e.g. "DB Code") against the distributor's
 * Master Template Head value (e.g. "Distributor").
 *
 * An outlet belongs to a distributor when its value is the distributor value, or the
 * distributor value followed by extra text after a space/symbol — e.g. outlet DB Code
 * "Disha Enteprises (SS)" belongs to distributor "Disha Enteprises". Spacing and case are
 * ignored. When several distributor names fit, the longest one wins, so
 * "Shankar Trading Company (SS)" goes to "Shankar Trading Company", not "Shankar Trading".
 */
class OutletDistributorMatch
{
    public static function normalize($value): string
    {
        $value = preg_replace('/\s+/u', ' ', (string) $value) ?? (string) $value;
        return mb_strtolower(trim($value));
    }

    /** Outlet value refers to this distributor value (same text, or followed by extra text). */
    public static function matches($outletValue, $distributorValue): bool
    {
        $outlet = self::normalize($outletValue);
        $distributor = self::normalize($distributorValue);
        if ($outlet === '' || $distributor === '') return false;
        if ($outlet === $distributor) return true;
        if (!str_starts_with($outlet, $distributor)) return false;

        // The extra text must start after a space or symbol: "Disha Enteprises (SS)" yes,
        // "Disha Enteprisesxyz" no.
        $next = mb_substr($outlet, mb_strlen($distributor), 1);
        return !preg_match('/[\p{L}\p{N}]/u', $next);
    }

    /** The distributor value an outlet value belongs to (longest match), or null. */
    public static function bestMatch($outletValue, iterable $distributorValues): ?string
    {
        $best = null;
        foreach ($distributorValues as $value) {
            if (self::matches($outletValue, $value)
                && ($best === null || mb_strlen(self::normalize($value)) > mb_strlen(self::normalize($best)))) {
                $best = (string) $value;
            }
        }
        return $best;
    }

    /** Outlet value belongs to this distributor, and not to a longer distributor name that also fits. */
    public static function belongsTo($outletValue, $distributorValue, iterable $allDistributorValues): bool
    {
        if (!self::matches($outletValue, $distributorValue)) return false;
        $best = self::bestMatch($outletValue, $allDistributorValues);
        return $best === null || self::normalize($best) === self::normalize($distributorValue);
    }

    /** All values of the master (distributor) head across the master template's rows. */
    public static function distributorValues(int $masterProjectTemplateId, $masterHeadId): array
    {
        if (empty($masterHeadId)) return [];
        return DB::table('project_template_name_values_new')
            ->where('project_template_id', $masterProjectTemplateId)
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$masterHeadId}\"')) AS head_value")
            ->pluck('head_value')
            ->filter(fn($v) => trim((string) $v) !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * For an outlet (child) template: the given rows whose own reference head value matches no
     * parent row's master head value. Returns ['master_head' => name, 'own_head' => name,
     * 'missing' => [own value => [outlet names...]], 'missing_rows' => [row id => own value]];
     * 'missing' is empty when every row has a
     * parent, or when the template is not an outlet template with a mapping.
     */
    public static function rowsWithoutParent($childTemplate, array $rowIds): array
    {
        $result = ['master_head' => '', 'own_head' => '', 'missing' => [], 'missing_rows' => []];
        if (empty($rowIds) || (int) ($childTemplate->is_master ?? 1) === 1
            || empty($childTemplate->master_head_id) || empty($childTemplate->own_reference_head_id)) {
            return $result;
        }
        $master = DB::table('project_templates')->where('project_id', $childTemplate->project_id)->where('is_master', 1)->first(['id']);
        $heads = DB::table('template_name_heads')->whereIn('id', [$childTemplate->master_head_id, $childTemplate->own_reference_head_id])
            ->pluck('template_head_name', 'id');
        $result['master_head'] = (string) ($heads[$childTemplate->master_head_id] ?? 'Master head');
        $result['own_head']    = (string) ($heads[$childTemplate->own_reference_head_id] ?? 'Own head');

        $distributorNames = $master ? self::distributorValues((int) $master->id, $childTemplate->master_head_id) : [];
        foreach (DB::table('project_template_name_values_new')->whereIn('id', $rowIds)->get(['id', 'template_data_json']) as $row) {
            $data = json_decode($row->template_data_json, true) ?: [];
            $ownValue = self::headValue($data, $childTemplate->own_reference_head_id);
            if (self::bestMatch($ownValue, $distributorNames) === null) {
                $label = trim((string) ($data[$childTemplate->main_header] ?? '')) ?: "Row {$row->id}";
                $result['missing'][(string) $ownValue][] = $label;
                $result['missing_rows'][(int) $row->id] = (string) $ownValue;
            }
        }
        return $result;
    }

    /** One readable message for rowsWithoutParent() results. */
    public static function missingParentMessage(array $check, int $maxNames = 5): string
    {
        $parts = [];
        foreach ($check['missing'] as $ownValue => $outlets) {
            $shown = implode(', ', array_slice($outlets, 0, $maxNames)) . (count($outlets) > $maxNames ? ', …' : '');
            $parts[] = "{$check['own_head']} \"{$ownValue}\" (" . count($outlets) . " outlet" . (count($outlets) === 1 ? '' : 's') . ": {$shown})";
        }
        return "No matching parent row: the {$check['own_head']} value(s) below match no {$check['master_head']} value in the parent template, so nothing was assigned. "
            . implode('; ', $parts) . ". Add the {$check['master_head']} to the parent template, or correct the {$check['own_head']}, then assign again.";
    }

    /**
     * The distributor (master) row an outlet row added one at a time belongs to, for its distributor_id:
     * a master row whose master head value equals one of the outlet's values, else the master value its
     * own reference head refers to (see bestMatch). Null for a master template or no match.
     */
    public static function parentRowId($outletTemplate, array $outletData): ?int
    {
        if ((int) ($outletTemplate->is_master ?? 1) === 1 || empty($outletTemplate->master_head_id)) return null;
        $master = DB::table('project_templates')->where('project_id', $outletTemplate->project_id)->where('is_master', 1)->first(['id']);
        if (!$master) return null;

        $headId = $outletTemplate->master_head_id;
        $masterRows = DB::table('project_template_name_values_new')
            ->where('project_template_id', $master->id)
            ->selectRaw("id, JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$headId}\"')) AS head_value")
            ->get()
            ->filter(fn($row) => trim((string) $row->head_value) !== '');

        $matched = null;
        foreach ($outletData as $value) {
            if (!is_scalar($value) || $value === '') continue;
            $found = $masterRows->firstWhere('head_value', (string) $value);
            if ($found) $matched = (int) $found->id;   // the last matching value wins, as on the web form before
        }
        if ($matched || empty($outletTemplate->own_reference_head_id)) return $matched;

        $best = self::bestMatch(self::headValue($outletData, $outletTemplate->own_reference_head_id), $masterRows->pluck('head_value')->unique());
        return $best !== null ? (int) $masterRows->firstWhere('head_value', $best)->id : null;
    }

    /** Value of a head in a row's template_data_json (array or JSON string). */
    public static function headValue($templateData, $headId): ?string
    {
        $data = is_array($templateData) ? $templateData : json_decode((string) $templateData, true);
        if (!is_array($data) || $headId === null || !array_key_exists($headId, $data)) return null;
        return is_scalar($data[$headId]) ? (string) $data[$headId] : null;
    }
}
