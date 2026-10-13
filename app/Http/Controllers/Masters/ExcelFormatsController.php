<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ExcelFormatsController extends Controller
{
    function __construct()
    {
        return $this->middleware("auth");
    }
    /**
     * Bulk user template, built for the person downloading it: a Role dropdown with the
     * roles they may assign and, for admins, an Agency dropdown (agency users' emails) to
     * link an Auditor/Verifier to an agency — same choices as the Add User form. Built per
     * download instead of editing the shared file, so users never get each other's lists.
     */
    public function userUploadTemplateDownload()
    {
        $currentRole = User::find(Auth::id())->getRoleNames()->first();
        $roles = \App\Http\Controllers\Auth\UserController::assignableRoleNames($currentRole);
        $withAgency = $currentRole !== 'Agency'; // agency uploads are always linked to the agency itself
        $agencyEmails = $withAgency
            ? User::role('Agency')->whereNotNull('email')->orderBy('email')->pluck('email')
            : collect();

        $columns = ['Name', 'Mobile No', 'Email ID', 'Address 1', 'Address 2', 'City', 'District', 'State', 'Pincode', 'Password', 'Role'];
        if ($withAgency) $columns[] = 'Agency';
        $lastRow = 2000;

        $w = new \App\Services\XlsxStream();
        $w->beginSheet('Users', array_slice([24, 14, 30, 26, 26, 16, 16, 16, 10, 14, 14, 32], 0, count($columns)), 1);
        $w->row($columns, \App\Services\XlsxStream::STYLE_HEADER);
        if ($roles->isNotEmpty()) {
            $w->listValidation("K2:K{$lastRow}", 'Roles!$A$1:$A$' . $roles->count(), true,
                'Role', 'Pick the role for this user.');
        }
        if ($withAgency && $agencyEmails->isNotEmpty()) {
            $w->listValidation("L2:L{$lastRow}", 'Agencies!$A$1:$A$' . $agencyEmails->count(), true,
                'Agency (optional)', 'Only for an Auditor or Verifier: pick the agency user\'s email to link this user to that agency. Leave blank for no agency.');
        }
        $w->endSheet();
        $w->addSheet('Roles', $roles->map(fn($r) => [$r])->all(), 'hidden');
        if ($withAgency) $w->addSheet('Agencies', $agencyEmails->map(fn($e) => [$e])->all(), 'hidden');

        $tmp = tempnam(sys_get_temp_dir(), 'usertpl') . '.xlsx';
        $w->save($tmp);

        return response()->download($tmp, 'UserBulkUploadFormat.xlsx', [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ])->deleteFileAfterSend(true);
    }
    public function downloadDropdownOptionsFormat()
    {
        $userExcelTemplatePath = public_path('assets/excel_formats/DropdownOptionImportFormat.xlsx');
        // Check if the file exists
        if (!file_exists($userExcelTemplatePath)) {
            return abort(404); // Return 404 if the file doesn't exist
        }
        // Return a response with the file
        return new BinaryFileResponse($userExcelTemplatePath, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="dropdown_option_format.xlsx"',
        ]);
    }

    public function activityTemplateDownload()
    {
        $userExcelTemplatePath = public_path('assets/excel_formats/activityFormat.xlsx');
        // Check if the file exists
        if (!file_exists($userExcelTemplatePath)) {
            return abort(404); // Return 404 if the file doesn't exist
        }
        // Return a response with the file
        return new BinaryFileResponse($userExcelTemplatePath, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
    public function downloadTemplateHeadsTemplate()
    {
        $templateFilePath = public_path('assets/excel_formats/sampleTemplate.xlsx');

        // Check if the file exists
        if (!file_exists($templateFilePath)) {
            return abort(404); // Return 404 if the file doesn't exist
        }
        // No-cache: this file's content changes when the sample columns are updated (e.g. the
        // Type/Options columns added for dropdown/yes-no heads) — without this, browsers can keep
        // serving a stale previously-downloaded copy for this same URL indefinitely.
        return new BinaryFileResponse($templateFilePath, 200, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }
}
