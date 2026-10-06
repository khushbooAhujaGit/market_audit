# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Laravel 10 (PHP 8.2) market-audit platform (TNBT "Market Audit", production at `marketaudit.escrips.in`, a Hostinger/cPanel host). Admins build **activities** made of **questions**, attach them to **projects** whose row data (distributors/outlets) comes from Excel uploads, and assign rows to **auditors**. Auditors answer mostly through a mobile app (the Sanctum API in `routes/api.php`) and sometimes through a web form. **Verifiers** then approve or reject the answers, and admins export Excel reports.

## Commands

The local environment is WAMP on Windows (`C:\wamp64\bin\php\php8.2.29`), with MySQL on `localhost:3306`.

```bash
composer install
npm run dev          # vite
npm run build
php artisan serve
php artisan queue:work   # needed only if QUEUE_CONNECTION is not "sync"
./vendor/bin/pint        # code style (Laravel Pint)
php artisan test
php artisan test --filter=ExampleTest
```

The `tests/` folder only has the default Laravel example tests. In practice, changes get checked against real data in the database or phpMyAdmin.

**Composer SSL on this machine:** Avast HTTPS scanning intercepts TLS. The global Composer `cafile` points to `%APPDATA%\Composer\cacert-with-avast.pem`, which is WAMP's `cacert.pem` plus the Avast root. If Composer starts failing with `curl error 60`, rebuild that bundle. Don't disable TLS.

## Architecture

### Controllers are large and carry most of the logic
There is little service or repository layering, so most business logic sits directly in these controllers:
- `app/Http/Controllers/API/TaskController.php` (~5k lines): the whole mobile auditor API. It covers project/activity/question fetch, `saveAnswer` (one answer at a time), `answerSubmit` (bulk), `finalizeAnswers`, repeat instances (`addInstance`/`deleteInstance`/`activityInstances`), `closeActivity`, signatures and OTP.
- `app/Http/Controllers/Masters/TaskHandlerController.php`: the web auditor form and admin "My Project Data". It also has `autoSaveAnswer`, the web counterpart of `saveAnswer`.
- `Masters/ActivityController.php` and `Masters/QuestionController.php`: activity/question creation, both from the Excel template (`public/assets/excel_formats/activityFormat.xlsx`) and manually.
- `Masters/ProjectController.php`: projects and project data uploads, which run through queued jobs.
- `Masters/ReportController.php`: report exports ("Get Project Report" and "Distributor Report", each with single-instance and repeat-instance sheet variants).
- `Masters/VerifierController.php`: the verifier queue and approve/reject.

`*Old.php` / `*bk.php` files (`TaskControllerOld`, `TaskHandlerControllerOld`, `UploadProjectDataJobbk`) are dead backups. Composer skips them with PSR-4 warnings, so don't edit them. `API/AuthenticationController.php` declares its namespace as `...\Api` while its folder is `API`.

### Data model essentials
- Project row data is stored as JSON in `template_data_json`, one object per row, keyed by the template head name. It is decoded in many places (`json_decode($row->template_data_json, true)`). `main_header` selects the distributor key. Related models: `ProjectTemplate`, `ProjectTemplateNameValue(sNew)`, `TemplateName`/`TemplateNameHead`.
- `Question.question_type` is a string enum. Values include `Dropdown`, `Multi select`, `Single select`, `Multi Response`, `Free Text`, `Yes / No`, `Image`, `Video`, `Barcode`, `QR Code`, `RFID`, and others. `question_sequence`, `answer_type`, sub-questions (`QuestionSubQuestion`), conditional/dependent questions and subject dropdowns (`SubjectDropdown`, `subject_dropdown_id`) all shape how an activity renders.
- Options live in `question_dropdowns` (`QuestionDropdown`, accessed as `$question->getOptions`).
- Answers are stored in `UserActivityAnswersData` (`user_answer` and `status`), keyed by `row_id + activity_id + question_id + user_id`, with `activity_sequence` used for repeat instances (`ActivityRepeatInstance`). A normal submission sets `status = 1`, and verifier queries must match that.
- Access control uses spatie/laravel-permission. Web route groups are wrapped in `permission:<Name>` middleware (`routes/web.php`).

### Barcode / QR Code / RFID questions
For these three types, the question's `question_dropdowns` options are **not** choices. They are the **expected header keys** that the scanned payload should contain. A scan is saved as a JSON wrapper: `{format: json|vcard|raw_text, raw_value, parsed, expected_headers, missing_headers, extra_headers, all_expected_present}`. Header matching is exact and case-sensitive.
- `app/Services/ScanAnswerFormatter.php` is the shared formatter and is used by `TaskHandlerController::autoSaveAnswer`. `TaskController::saveAnswer` (~line 3965) still has an **inline copy** of the same logic, plus `parseVCardScan()`, so keep the two in sync or consolidate them.
- ReportController splits a scan answer into one column per expected header, plus one line-broken column for unmatched keys (see the wrap-text support in `FastXlsxWriter`).
- On the web form (`resources/views/masters/users/activity_questions.blade.php`), scanning uses `html5-qrcode` for QR/barcodes and Web NFC for RFID. Answers auto-save on `blur`, so any JS that fills a field has to trigger `blur` explicitly.

### Completed (closed) projects
`projects.is_completed` (admin toggles it with "Complete/Reopen" on the project list, `ProjectController::toggleComplete`). A project can only be completed once at least one auditor is assigned. A completed project is hidden from auditors (APK and web) and from the verifier queue, but **stays reportable**. On the report pages (Report Dump and Project Report in `ReportController`, PDF Report in `VerifierController::reportPage`), an Open/Completed switch (`?view=completed`, read via `ProjectCompletion::reportView()`) filters the project dropdown for every role, Super Admin included. The switch is the partial `masters/reports/partials/completed_toggle`. Build its URL with `route(...) . '?view=completed'`, because `EncryptedUrlGenerator` encrypts any parameter passed through `route()`.
- List queries use `Project::open()` / `completed()`.
- Direct access is blocked by the `project.active` middleware (`EnsureProjectNotCompleted`). It resolves the project from `project`/`project_id`, `pt`/`project_template_id`, `row_id`/`r` or `instance_id` through `App\Services\ProjectCompletion`, and skips Super Admin. **Any new auditor or verifier route that touches a project/row needs this middleware.** Report/export routes deliberately don't have it.

### Reports and background jobs
- The default is `QUEUE_CONNECTION=sync`. Jobs in `app/Jobs` cover heavy work: `GenerateReportJob`, `ProcessExcelImportJob` / `UploadProjectTemplateDataJob` (project data import through maatwebsite/excel), infiltration reports, and OneDrive uploads.
- Excel output goes through the custom streaming `app/Services/FastXlsxWriter.php` rather than PhpSpreadsheet. Some exports instead call a Python CGI script (`/cgi-bin/gen_xlsx.py`) over HTTP, authenticated with `app.key` (`ReportController::generateXlsxViaPython`).
- `python-tools/infiltration/infiltration_tool.py` is the external Infiltration Audit Report tool. `GenerateInfiltrationReportJob` runs it using `config('services.infiltration_tool.python_bin')`.
- Microsoft Graph (`config/services.php` → `msgraph`) handles Outlook mail (`OutlookMailerService`) and OneDrive uploads (`OneDrivePersonalService` for per-user delegated tokens in `one_drive_tokens`, `OneDriveSharedService` for app-level access). Shared-folder uploads resolve the folder from the `infiltration_shared_folder_url` sharing link.

### Misc
- `app/Helpers/EncryptHelper.php` is autoloaded globally (composer `files`).
- `workdone.readme` is a hand-written log of recent feature work, useful for context on OneDrive, verifier status, signatures and scan questions. `_workdone_run_report.bat` and `serve.bat` are personal helper scripts that point at other repos and paths.
