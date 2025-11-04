<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ExcelFormatsController extends Controller
{
    function __construct()
    {
        return $this->middleware("auth");
    }
    public function userUploadTemplateDownload()
    {
        //khushboo 07-04-2025
        $currentUser = User::find(Auth::user()->id);
        $existingFilePath = public_path('assets/excel_formats/UserBulkUploadFormat.xlsx');

        // Load the existing Excel file
        $spreadsheet = IOFactory::load($existingFilePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Get all role names from the roles table
        if ($currentUser->getRoleNames()->first() == 'Agency') {
            $roles = DB::table('roles')
                ->whereIn('name', ['auditor', 'verifier'])
                ->pluck('name')
                ->toArray();
        } else {

            $roles = DB::table('roles')->pluck('name')->toArray();
        }

        // Convert to comma-separated list for dropdown (must be under 255 characters)
        $roleList = implode(',', $roles);

        // Apply dropdown to column K (11th column), from row 2 to row 100
        for ($row = 2; $row <= 100; $row++) {
            $cell = 'K' . $row;

            $validation = $sheet->getCell($cell)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setFormula1('"' . $roleList . '"');
        }

        // Save the updated file
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save(public_path('assets/excel_formats/UserBulkUploadFormat.xlsx'));
        //khushboo 07-04-2025

        $userExcelTemplatePath = public_path('assets/excel_formats/UserBulkUploadFormat.xlsx');
        // Check if the file exists
        if (!file_exists($userExcelTemplatePath)) {
            return abort(404); // Return 404 if the file doesn't exist
        }
        // Return a response with the file
        return new BinaryFileResponse($userExcelTemplatePath, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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
        // Return a response with the file
        return new BinaryFileResponse($templateFilePath, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
