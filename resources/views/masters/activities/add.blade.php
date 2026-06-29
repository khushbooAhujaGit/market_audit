@extends('template.layouts.simple.master')
@section('content')

{{-- ── Slide-in panel overlay ── --}}
<div id="addQOverlay" onclick="closeAddPanel()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1040;"></div>

{{-- ── Slide-in panel (same markup as activity_questions.blade.php) ── --}}
<div id="addQPanel" style="display:none;position:fixed;top:0;right:0;height:100vh;width:580px;max-width:98vw;background:#fff;z-index:1050;box-shadow:-4px 0 24px rgba(0,0,0,.15);overflow-y:auto;font-family:system-ui,sans-serif;">
    <div style="padding:18px 20px 14px;border-bottom:1px solid #EBEBF4;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1;">
        <div>
            <div style="font-size:16px;font-weight:700;color:#111827;" id="panelTitle">Add Question</div>
            <div style="font-size:12px;color:#9CA3AF;margin-top:1px;" id="panelSubtitle">New Activity</div>
        </div>
        <button onclick="closeAddPanel()" style="background:none;border:none;font-size:22px;color:#6B7280;cursor:pointer;line-height:1;padding:2px 6px;">&#215;</button>
    </div>

    <div style="padding:20px;">
        {{-- Question Text --}}
        <div style="margin-bottom:16px;">
            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Question Text <span style="color:#DC2626;">*</span></label>
            <textarea id="aq_text" rows="3" placeholder="Enter the question..." style="width:100%;border:1px solid #E5E7EB;border-radius:6px;padding:9px 11px;font-size:13.5px;color:#111827;resize:vertical;outline:none;line-height:1.5;box-sizing:border-box;"></textarea>
            <div id="aq_text_err" style="font-size:11px;color:#DC2626;margin-top:3px;display:none;">Question text is required.</div>
        </div>

        {{-- Type picker --}}
        <div style="margin-bottom:16px;">
            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:7px;">Question Type <span style="color:#DC2626;">*</span></label>
            <div style="display:flex;flex-wrap:wrap;gap:5px;" id="aq_type_btns">
                @php $qTypes = [
                    ['Yes / No','#15803D','#F0FDF4'],['Free Text','#1D4ED8','#EFF6FF'],
                    ['Dropdown','#7C3AED','#F5F3FF'],['Multi select','#0E7490','#ECFEFF'],
                    ['Image','#B45309','#FFFBEB'],['Video','#9333EA','#FAF5FF'],
                    ['Date','#0F766E','#F0FDFA'],['Date & Time','#0F766E','#F0FDFA'],
                    ['File Upload','#1D4ED8','#EFF6FF'],['Location','#DC2626','#FEF2F2'],
                    ['Audio','#6D28D9','#F5F3FF'],['Subjective','#6B7280','#F9FAFB'],
                    ['Multi Response','#1E3A5F','#DBEAFE'],
                ]; @endphp
                @foreach($qTypes as $qt)
                <button type="button" onclick="selectQType('{{ $qt[0] }}')"
                        data-type="{{ $qt[0] }}" data-color="{{ $qt[1] }}" data-bg="{{ $qt[2] }}"
                        style="padding:4px 9px;border-radius:4px;font-size:11px;font-weight:600;border:1.5px solid #E5E7EB;background:#F9FAFB;color:#374151;cursor:pointer;transition:all .12s;">
                    {{ $qt[0] }}
                </button>
                @endforeach
            </div>
            <input type="hidden" id="aq_type" value="">
            <div id="aq_type_err" style="font-size:11px;color:#DC2626;margin-top:3px;display:none;">Please select a question type.</div>
        </div>

        {{-- Required toggle --}}
        <div style="display:flex;gap:10px;margin-bottom:16px;align-items:center;">
            <label style="display:flex;align-items:center;gap:7px;cursor:pointer;">
                <div onclick="toggleRequired()" id="reqToggle" style="width:36px;height:20px;border-radius:10px;background:#111827;position:relative;cursor:pointer;flex:none;transition:background .15s;">
                    <div id="reqKnob" style="width:16px;height:16px;border-radius:50%;background:white;position:absolute;top:2px;left:18px;transition:left .15s;box-shadow:0 1px 3px rgba(0,0,0,.2);"></div>
                </div>
                <span style="font-size:13px;font-weight:500;color:#374151;">Required</span>
            </label>
            <input type="hidden" id="aq_required" value="1">
        </div>

        {{-- Help Text --}}
        <div style="margin-bottom:16px;">
            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Help Text <span style="font-size:11px;font-weight:400;color:#9CA3AF;">(optional)</span></label>
            <input type="text" id="aq_help" placeholder="Hint shown below the question..." style="width:100%;border:1px solid #E5E7EB;border-radius:6px;padding:8px 11px;font-size:13px;color:#111827;outline:none;box-sizing:border-box;">
        </div>

        <div style="height:1px;background:#EBEBF4;margin:0 -20px 18px;"></div>

        {{-- OPTIONS (Dropdown / Multi select) --}}
        <div id="sec_options" style="display:none;margin-bottom:18px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <label style="font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:.5px;">Answer Options <span style="color:#DC2626;">*</span></label>
                <span id="optCount" style="font-size:11.5px;color:#9CA3AF;">0 option(s)</span>
            </div>
            <div id="ms_extras" style="display:none;padding:9px 11px;background:#F8F8FC;border:1px solid #EBEBF4;border-radius:6px;margin-bottom:10px;">
                <label style="display:flex;align-items:center;gap:7px;cursor:pointer;">
                    <div onclick="toggleAllowMultiple()" id="amToggle" style="width:32px;height:18px;border-radius:9px;background:#E5E7EB;position:relative;cursor:pointer;flex:none;transition:background .15s;">
                        <div id="amKnob" style="width:14px;height:14px;border-radius:50%;background:white;position:absolute;top:2px;left:2px;transition:left .15s;"></div>
                    </div>
                    <span style="font-size:12px;color:#374151;">Allow Multiple Selections</span>
                </label>
                <input type="hidden" id="aq_allow_multiple" value="0">
            </div>
            <div id="optList" style="margin-bottom:8px;"></div>
            <div style="display:flex;gap:7px;">
                <input type="text" id="newOptInput" placeholder="Type option and press Enter..."
                       onkeydown="if(event.key==='Enter'){event.preventDefault();addOption();}"
                       style="flex:1;border:1px solid #E5E7EB;border-radius:5px;padding:7px 10px;font-size:13px;outline:none;">
                <button onclick="addOption()" style="padding:7px 13px;background:#111827;color:white;border:none;border-radius:5px;font-size:13px;cursor:pointer;">+ Add</button>
            </div>
            <div id="aq_opt_err" style="font-size:11px;color:#DC2626;margin-top:4px;display:none;">Add at least one option.</div>
            <div style="height:1px;background:#EBEBF4;margin:18px -20px 0;"></div>
        </div>

        {{-- FILE CONFIG --}}
        <div id="sec_file" style="display:none;margin-bottom:18px;">
            <label style="display:block;font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:9px;">File Constraints</label>
            <div style="display:flex;gap:9px;margin-bottom:10px;">
                <div style="flex:1;">
                    <label style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px;">Accepted Types</label>
                    <input type="text" id="aq_file_types" placeholder=".jpg,.png,.pdf" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:7px 10px;font-size:13px;outline:none;box-sizing:border-box;">
                </div>
                <div style="width:130px;">
                    <label style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px;">Max Size (MB)</label>
                    <input type="number" id="aq_max_size" placeholder="10" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:7px 10px;font-size:13px;outline:none;box-sizing:border-box;">
                </div>
            </div>
            <div id="sec_multi_img" style="display:none;">
                <label style="display:flex;align-items:center;gap:7px;cursor:pointer;">
                    <div onclick="toggleMultipleImages()" id="miToggle" style="width:36px;height:20px;border-radius:10px;background:#E5E7EB;position:relative;cursor:pointer;flex:none;transition:background .15s;">
                        <div id="miKnob" style="width:16px;height:16px;border-radius:50%;background:white;position:absolute;top:2px;left:2px;transition:left .15s;"></div>
                    </div>
                    <span style="font-size:13px;color:#374151;">Allow Multiple Images</span>
                </label>
                <input type="hidden" id="aq_allow_multi_img" value="0">
            </div>
            <div style="height:1px;background:#EBEBF4;margin:10px -20px 0;"></div>
        </div>

        {{-- DATE CONFIG --}}
        <div id="sec_date" style="display:none;margin-bottom:18px;">
            <label style="display:block;font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:9px;">Date Range (optional)</label>
            <div style="display:flex;gap:9px;">
                <div style="flex:1;"><label style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px;">Min Date</label><input type="date" id="aq_date_min" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:7px 10px;font-size:13px;outline:none;box-sizing:border-box;"></div>
                <div style="flex:1;"><label style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px;">Max Date</label><input type="date" id="aq_date_max" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:7px 10px;font-size:13px;outline:none;box-sizing:border-box;"></div>
            </div>
            <div style="height:1px;background:#EBEBF4;margin:18px -20px 0;"></div>
        </div>

        {{-- VALIDATION section (same as activity_questions panel) --}}
        <div id="sec_validation" style="display:none;margin-bottom:18px;margin-top:18px;">
            <div style="height:1px;background:#EBEBF4;margin:0 -20px 18px;"></div>
            <label style="display:block;font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:9px;">Answer Validation</label>
            <div style="margin-bottom:10px;">
                <label style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px;">Validation Rule</label>
                <select id="aq_val_rule" onchange="onValRuleChange()" class="form-select form-select-sm">
                    <option value="none">none</option>
                    <option value="numericrange">numericrange (min/max number)</option>
                    <option value="digitlength">digitlength (digit count)</option>
                    <option value="textlength">textlength (char count)</option>
                    <option value="email">email</option>
                    <option value="phone">phone</option>
                    <option value="regex">regex (custom pattern)</option>
                </select>
            </div>
            <div id="sec_val_range" style="display:none;gap:9px;margin-bottom:10px;" class="d-flex">
                <div style="flex:1;">
                    <label id="valMinLbl" style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px;">Min</label>
                    <input type="number" id="aq_val_min" class="form-control form-control-sm">
                </div>
                <div style="flex:1;">
                    <label id="valMaxLbl" style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px;">Max</label>
                    <input type="number" id="aq_val_max" class="form-control form-control-sm">
                </div>
            </div>
            <div id="sec_val_regex" style="display:none;margin-bottom:10px;">
                <label style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px;">Regex Pattern</label>
                <input type="text" id="aq_val_regex" placeholder="e.g. ^[A-Z]{2}[0-9]{6}$" class="form-control form-control-sm" style="font-family:monospace;">
            </div>
        </div>

        {{-- Multi Response inline sub-questions builder --}}
        <div id="sec_mr_subquestions" style="display:none;margin-top:18px;margin-bottom:18px;">
            <div style="height:1px;background:#EBEBF4;margin:0 -20px 18px;"></div>
            <div style="font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Sub-Questions</div>
            <div style="font-size:12px;color:#9CA3AF;margin-bottom:10px;">Define sub-questions that will appear inside this Multi Response group.</div>
            <div id="mr_sq_list" style="margin-bottom:8px;"></div>
            <div style="display:flex;gap:7px;margin-bottom:4px;">
                <input type="text" id="mr_sq_input" placeholder="Sub-question text..."
                       class="form-control form-control-sm" style="flex:1;"
                       onkeydown="if(event.key==='Enter'){event.preventDefault();addMrSubQ();}">
                <select id="mr_sq_type" class="form-select form-select-sm" style="width:130px;">
                    <option value="Free Text">Free Text</option>
                    <option value="Image">Image</option>
                    <option value="Yes / No">Yes / No</option>
                    <option value="Dropdown">Dropdown</option>
                    <option value="Date">Date</option>
                    <option value="Location">Location</option>
                    <option value="Multi select">Multi select</option>
                </select>
                <label style="display:flex;align-items:center;gap:4px;font-size:12px;white-space:nowrap;cursor:pointer;">
                    <input type="checkbox" id="mr_sq_req" class="form-check-input" style="width:14px;height:14px;"> Req
                </label>
                <button type="button" onclick="addMrSubQ()" class="btn btn-sm btn-dark px-3">+</button>
            </div>
        </div>

        {{-- Conditional Logic — shown when Yes/No or Dropdown parents exist and type is not Multi Response --}}
        <div id="sec_conditional" style="display:none;margin-bottom:18px;margin-top:18px;">
            <div style="height:1px;background:#EBEBF4;margin:0 -20px 18px;"></div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <div>
                    <div style="font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:.5px;">Conditional Logic</div>
                    <div style="font-size:12px;color:#9CA3AF;margin-top:2px;">Show only when a specific answer is given</div>
                </div>
                <div onclick="toggleCond()" id="condToggle" style="width:38px;height:21px;border-radius:11px;background:#E5E7EB;position:relative;cursor:pointer;flex:none;transition:background .15s;">
                    <div id="condKnob" style="width:17px;height:17px;border-radius:50%;background:white;position:absolute;top:2px;left:2px;transition:left .15s;box-shadow:0 1px 3px rgba(0,0,0,.2);"></div>
                </div>
            </div>
            <div id="sec_cond_body" style="display:none;padding:12px;background:#F8F8FC;border:1px solid #E8E8F0;border-radius:7px;">
                <div style="margin-bottom:10px;">
                    <label style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px;">Depends on question</label>
                    <select id="aq_cond_parent" onchange="onCondParentChange()" class="form-select form-select-sm">
                        <option value="">— Select parent question —</option>
                    </select>
                </div>
                <div id="sec_cond_val" style="display:none;">
                    <label style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px;">When answer equals</label>
                    <select id="aq_cond_value" class="form-select form-select-sm">
                        <option value="">— Select trigger value —</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Parent Section (Multi Response) for sub-question linking --}}
        <div id="sec_parent_section" style="display:none;margin-bottom:18px;margin-top:18px;">
            <div style="height:1px;background:#EBEBF4;margin:0 -20px 14px;"></div>
            <label style="display:block;font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;">Add as Sub-Question of</label>
            <select id="aq_parent_group" class="form-select form-select-sm">
                <option value="">— None (standalone question) —</option>
            </select>
        </div>

        <input type="hidden" id="aq_editing_idx" value="">

        {{-- Save — type="button" is critical: prevents accidental form submit --}}
        <div style="padding-top:14px;">
            <button type="button" onclick="saveQuestionLocally()" id="aq_save_btn"
                    style="width:100%;padding:11px;background:#111827;color:white;border:none;border-radius:7px;font-size:14px;font-weight:600;cursor:pointer;">
                Add Question
            </button>
            <div id="aq_save_err" style="font-size:11px;color:#DC2626;margin-top:6px;display:none;"></div>
        </div>
    </div>
</div>

{{-- ── Main page ── --}}
<div class="page-body">
<div class="container-fluid">
<div class="row">
<div class="col-sm-12">
<div class="card">

    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="mb-0">Add New Activity</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('activities.list') }}" class="btn btn-outline-secondary btn-sm">View Activities</a>
        </div>
    </div>

    <div class="card-body">
        <form method="POST" action="{{ route('activities.store') }}" enctype="multipart/form-data" id="createActivityForm">
            @csrf

            {{-- Activity name + mode toggle --}}
            <div class="row g-3 mb-4">
                <div class="col-xl-6 col-sm-6">
                    <label class="form-label" for="activity_name">Activity Name <span class="txt-danger">*</span></label>
                    <input class="form-control" id="activity_name" value="{{ old('activity_name') }}"
                           name="activity_name" type="text" placeholder="Enter Activity name" required>
                    @error('activity_name')<p class="text-danger small mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="col-xl-6 col-sm-6">
                    <label class="form-label">How would you like to add questions?</label>
                    <div class="d-flex gap-2">
                        <button type="button" id="modeExcel" onclick="setMode('excel')"
                                class="btn btn-primary btn-sm flex-fill">
                            &#128196; Upload Excel Template
                        </button>
                        <button type="button" id="modeManual" onclick="setMode('manual')"
                                class="btn btn-outline-primary btn-sm flex-fill">
                            &#9999; Add Manually
                        </button>
                    </div>
                </div>
            </div>

            {{-- Excel section --}}
            <div id="sec_excel" class="mb-3">
                <label class="form-label" for="questions_excel">Excel File</label>
                <input class="form-control" id="questions_excel" name="questions_excel" type="file" accept=".xlsx">
                @error('questions_excel')<p class="text-danger small mt-1">{{ $message }}</p>@enderror

                {{-- Download template card — theme colour #042b39 --}}
                <a href="{{ route('activityTemplate.download') }}"
                   style="display:flex;align-items:center;gap:14px;margin-top:12px;padding:14px 18px;background:linear-gradient(135deg,#f0f4f4,#dce8e8);border:1.5px dashed #042b39;border-radius:10px;text-decoration:none;transition:all .15s;"
                   onmouseover="this.style.background='linear-gradient(135deg,#dce8e8,#c5dadb)';this.style.borderColor='#042b39'"
                   onmouseout="this.style.background='linear-gradient(135deg,#f0f4f4,#dce8e8)'">
                    <div style="width:44px;height:44px;background:#042b39;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 12px rgba(4,43,57,.25);">
                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:14px;font-weight:700;color:#042b39;margin-bottom:2px;">
                            Download Activity Template
                        </div>
                        <div style="font-size:12px;color:#4a7a7e;">
                            Fill in the .xlsx template, then upload it above
                        </div>
                    </div>
                    <div style="flex-shrink:0;background:#042b39;color:#fff;font-size:11px;font-weight:700;padding:5px 12px;border-radius:20px;letter-spacing:.3px;">
                        .xlsx
                    </div>
                </a>
            </div>

            {{-- Manual section: just create the activity, redirect to questions page --}}
            <div id="sec_manual" style="display:none;">
                <div class="alert alert-info d-flex align-items-start gap-3 py-3" style="border-radius:8px;">
                    <div style="font-size:22px;flex-shrink:0;">&#8505;</div>
                    <div>
                        <strong>How it works:</strong>
                        <ol class="mb-0 mt-1" style="padding-left:18px;font-size:13px;">
                            <li>Enter the Activity Name above</li>
                            <li>Click <strong>Create Activity</strong> below</li>
                            <li>You'll be redirected to the <strong>Questions page</strong> where you can add, edit, reorder, and link questions with full support for all types — Multi Response groups, conditional logic, sub-questions, validation, and more</li>
                        </ol>
                    </div>
                </div>
                <input type="hidden" name="manual_mode" value="1">
            </div>

            {{-- Submit --}}
            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary px-5" id="submitBtn">
                    <span id="sec_excel_label">Add Now</span>
                    <span id="sec_manual_label" style="display:none;">Create Activity &amp; Add Questions &#8594;</span>
                </button>
            </div>
        </form>
    </div>

</div>
</div>
</div>
</div>
</div>
@endsection

@section('scripts')
<script>
// ─── Local question store ──────────────────────────────────────────────────
var _localQuestions = []; // [{question, question_type, answer_type, options, help_text, allow_multiple_images, parent_group_id}]
var _editingIdx = null;

// ─── Mode toggle ──────────────────────────────────────────────────────────
window.setMode = function(mode) {
    var isManual = mode === 'manual';
    document.getElementById('sec_excel').style.display  = isManual ? 'none' : '';
    document.getElementById('sec_manual').style.display = isManual ? '' : 'none';
    document.getElementById('modeManual').className = isManual
        ? 'btn btn-primary btn-sm flex-fill'
        : 'btn btn-outline-primary btn-sm flex-fill';
    document.getElementById('modeExcel').className = isManual
        ? 'btn btn-outline-primary btn-sm flex-fill'
        : 'btn btn-primary btn-sm flex-fill';
    document.getElementById('questions_excel').value = '';
    // Swap submit button label
    document.getElementById('sec_excel_label').style.display  = isManual ? 'none' : '';
    document.getElementById('sec_manual_label').style.display = isManual ? '' : 'none';
};

// ─── Panel open/close ─────────────────────────────────────────────────────
window.openAddPanel = function(editIdx) {
    _editingIdx = editIdx !== undefined ? editIdx : null;
    resetPanel();

    if (_editingIdx !== null) {
        var q = _localQuestions[_editingIdx];
        document.getElementById('panelTitle').textContent = 'Edit Question';
        document.getElementById('aq_text').value = q.question;
        selectQType(q.question_type);
        document.getElementById('aq_required').value = q.answer_type;
        setRequiredToggle(q.answer_type == 1);
        document.getElementById('aq_help').value = q.help_text || '';
        document.getElementById('aq_allow_multi_img').value = q.allow_multiple_images || 0;
        if (q.allow_multiple_images == 1) setToggle('miToggle','miKnob',true);

        // Options
        if (q.options) {
            q.options.split('|').forEach(function(o) { if(o.trim()) pushOption(o.trim()); });
        }

        // Restore sub-questions if editing a Multi Response question
        if (q.question_type === 'Multi Response') {
            _mrSubQuestions = _localQuestions
                .map(function(sq, i) { return {sq: sq, idx: i}; })
                .filter(function(item) { return String(item.sq.parent_group_id) === String(_editingIdx); })
                .map(function(item) { return {text: item.sq.question, type: item.sq.question_type, required: item.sq.answer_type}; });
            renderMrSubQList();
        }

        // Restore conditional if it was set
        if (q.cond_parent_idx !== '' && q.cond_parent_idx !== undefined) {
            document.getElementById('sec_cond_body').style.display = '';
            document.getElementById('condToggle').style.background = '#111827';
            document.getElementById('condKnob').style.left = '19px';
            refreshCondParentSelect();
            document.getElementById('aq_cond_parent').value = q.cond_parent_idx;
            onCondParentChange();
            document.getElementById('aq_cond_value').value = q.cond_trigger || '';
        }

        document.getElementById('aq_save_btn').textContent = 'Update Question';
    } else {
        document.getElementById('panelTitle').textContent = 'Add Question';
        document.getElementById('aq_save_btn').textContent = 'Add Question';
    }

    refreshParentSelect();
    if (_editingIdx !== null) {
        document.getElementById('aq_parent_group').value = _localQuestions[_editingIdx].parent_group_id || '';
    }

    // Show Conditional Logic immediately on panel open — for ALL question types
    var hasCondParent = _localQuestions.some(function(q) {
        return q.question_type === 'Yes / No' || q.question_type === 'Dropdown';
    });
    if (hasCondParent) {
        document.getElementById('sec_conditional').style.display = '';
        refreshCondParentSelect();
    }

    // Show "Add as Sub-Question of" immediately if Multi Response parents exist
    var hasMRParent = _localQuestions.some(function(q) { return q.question_type === 'Multi Response'; });
    if (hasMRParent) {
        document.getElementById('sec_parent_section').style.display = '';
        refreshParentSelect();
    }

    document.getElementById('addQPanel').style.display = '';
    document.getElementById('addQOverlay').style.display = '';
    document.getElementById('aq_text').focus();
};

window.closeAddPanel = function() {
    document.getElementById('addQPanel').style.display = 'none';
    document.getElementById('addQOverlay').style.display = 'none';
};

// ─── Type selection ───────────────────────────────────────────────────────
var _currentType = '';
window.selectQType = function(type) {
    _currentType = type;
    document.getElementById('aq_type').value = type;
    document.getElementById('aq_type_err').style.display = 'none';

    // Reset button styles
    document.querySelectorAll('#aq_type_btns button').forEach(function(btn) {
        btn.style.background = '#F9FAFB';
        btn.style.color = '#374151';
        btn.style.borderColor = '#E5E7EB';
    });

    // Highlight selected
    var activeBtn = document.querySelector('#aq_type_btns button[data-type="' + type + '"]');
    if (activeBtn) {
        activeBtn.style.background = activeBtn.getAttribute('data-bg');
        activeBtn.style.color = activeBtn.getAttribute('data-color');
        activeBtn.style.borderColor = activeBtn.getAttribute('data-color');
    }

    // Show/hide sections
    var isOpt = (type === 'Dropdown' || type === 'Multi select');
    var isFile = (type === 'Image' || type === 'File Upload' || type === 'Video' || type === 'Audio');
    var isDate = (type === 'Date' || type === 'Date & Time');
    var isMR   = (type === 'Multi Response');

    document.getElementById('sec_options').style.display        = isOpt  ? '' : 'none';
    document.getElementById('sec_file').style.display           = isFile ? '' : 'none';
    document.getElementById('sec_date').style.display           = isDate ? '' : 'none';
    document.getElementById('ms_extras').style.display          = (type === 'Multi select') ? '' : 'none';
    document.getElementById('sec_multi_img').style.display      = (type === 'Image') ? '' : 'none';
    // Validation — for text, number and location types
    var hasValidation = ['Free Text','Subjective','Location'].indexOf(type) >= 0;
    document.getElementById('sec_validation').style.display     = hasValidation ? '' : 'none';
    // Inline sub-questions — only for Multi Response type
    document.getElementById('sec_mr_subquestions').style.display = isMR ? '' : 'none';
    if (isMR) renderMrSubQList();

    // Conditional Logic — show when Yes/No or Dropdown parents exist (for ALL types including MR)
    var hasCondParent = _localQuestions.some(function(q) {
        return q.question_type === 'Yes / No' || q.question_type === 'Dropdown';
    });
    document.getElementById('sec_conditional').style.display = hasCondParent ? '' : 'none';
    if (hasCondParent) refreshCondParentSelect();

    // Sub-question linking — only for non-MR types when MR parents exist
    var hasMRParent = _localQuestions.some(function(q) { return q.question_type === 'Multi Response'; });
    document.getElementById('sec_parent_section').style.display = (!isMR && hasMRParent) ? '' : 'none';
    if (!isMR && hasMRParent) refreshParentSelect();
};

// ─── Validation rule show/hide ────────────────────────────────────────────
window.onValRuleChange = function() {
    var rule = document.getElementById('aq_val_rule').value;
    var needsRange = ['numericrange','digitlength','textlength'].indexOf(rule) >= 0;
    var needsRegex = rule === 'regex';
    document.getElementById('sec_val_range').style.display = needsRange ? 'flex' : 'none';
    document.getElementById('sec_val_regex').style.display  = needsRegex ? '' : 'none';
    // Update labels
    var minLbl = 'Min', maxLbl = 'Max';
    if (rule === 'digitlength' || rule === 'textlength') { minLbl = 'Min length'; maxLbl = 'Max length'; }
    document.getElementById('valMinLbl').textContent = minLbl;
    document.getElementById('valMaxLbl').textContent = maxLbl;
};

// ─── Multi Response inline sub-questions ─────────────────────────────────
var _mrSubQuestions = []; // [{text, type, required}]

window.addMrSubQ = function() {
    var text = document.getElementById('mr_sq_input').value.trim();
    if (!text) { document.getElementById('mr_sq_input').focus(); return; }
    _mrSubQuestions.push({
        text:     text,
        type:     document.getElementById('mr_sq_type').value,
        required: document.getElementById('mr_sq_req').checked ? '1' : '0'
    });
    document.getElementById('mr_sq_input').value = '';
    document.getElementById('mr_sq_req').checked = false;
    renderMrSubQList();
    document.getElementById('mr_sq_input').focus();
};

function renderMrSubQList() {
    var html = '';
    _mrSubQuestions.forEach(function(sq, i) {
        html += '<div style="display:flex;align-items:center;gap:6px;padding:6px 8px;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:5px;margin-bottom:5px;">'
            + '<span style="font-size:11px;font-weight:600;padding:2px 6px;border-radius:3px;background:#EFF6FF;color:#1D4ED8;">' + sq.type + '</span>'
            + '<span style="flex:1;font-size:13px;color:#374151;">' + sq.text + '</span>'
            + (sq.required === '1' ? '<span style="font-size:10px;color:#DC2626;font-weight:700;">Req</span>' : '')
            + '<button type="button" onclick="removeMrSubQ(' + i + ')" style="background:none;border:none;color:#EF4444;font-size:16px;cursor:pointer;line-height:1;">&#215;</button>'
            + '</div>';
    });
    document.getElementById('mr_sq_list').innerHTML = html;
}

window.removeMrSubQ = function(i) {
    _mrSubQuestions.splice(i, 1);
    renderMrSubQList();
};

// ─── Conditional logic helpers ────────────────────────────────────────────
function refreshCondParentSelect() {
    var sel = document.getElementById('aq_cond_parent');
    if (!sel) return;
    var cur = sel.value;
    sel.innerHTML = '<option value="">— Select parent question —</option>';
    _localQuestions.forEach(function(q, i) {
        if (q.question_type === 'Yes / No' || q.question_type === 'Dropdown') {
            var opt = document.createElement('option');
            opt.value = i;
            opt.textContent = '[' + q.question_type + '] ' + q.question;
            opt.setAttribute('data-type', q.question_type);
            opt.setAttribute('data-options', q.options || '');
            sel.appendChild(opt);
        }
    });
    if (cur) sel.value = cur;
}

window.onCondParentChange = function() {
    var sel = document.getElementById('aq_cond_parent');
    var opt = sel.options[sel.selectedIndex];
    var valSel = document.getElementById('aq_cond_value');
    document.getElementById('sec_cond_val').style.display = sel.value ? '' : 'none';
    valSel.innerHTML = '<option value="">— Select trigger value —</option>';
    if (!sel.value) return;
    var qtype = opt.getAttribute('data-type');
    var opts  = opt.getAttribute('data-options') || '';
    if (qtype === 'Yes / No') {
        valSel.innerHTML += '<option value="Yes">Yes</option><option value="No">No</option>';
    } else {
        opts.split('|').filter(Boolean).forEach(function(v) {
            valSel.innerHTML += '<option value="' + v.trim() + '">' + v.trim() + '</option>';
        });
    }
};

window.toggleCond = function() {
    var body = document.getElementById('sec_cond_body');
    var on   = body.style.display === 'none';
    body.style.display = on ? '' : 'none';
    document.getElementById('condKnob').style.left    = on ? '19px' : '2px';
    document.getElementById('condToggle').style.background = on ? '#111827' : '#E5E7EB';
    if (!on) {
        document.getElementById('aq_cond_parent').value = '';
        document.getElementById('sec_cond_val').style.display = 'none';
    }
};

// ─── Required toggle ──────────────────────────────────────────────────────
window.toggleRequired = function() {
    var isOn = document.getElementById('aq_required').value == '1';
    setRequiredToggle(!isOn);
};
function setRequiredToggle(on) {
    document.getElementById('aq_required').value = on ? '1' : '0';
    document.getElementById('reqToggle').style.background = on ? '#111827' : '#E5E7EB';
    document.getElementById('reqKnob').style.left = on ? '18px' : '2px';
}
function setToggle(toggleId, knobId, on) {
    document.getElementById(toggleId).style.background = on ? '#111827' : '#E5E7EB';
    document.getElementById(knobId).style.left = on ? '18px' : '2px';
}

// ─── Multi-image toggle ───────────────────────────────────────────────────
window.toggleMultipleImages = function() {
    var cur = document.getElementById('aq_allow_multi_img').value;
    var on  = cur != '1';
    document.getElementById('aq_allow_multi_img').value = on ? '1' : '0';
    setToggle('miToggle','miKnob', on);
};

// ─── Allow Multiple Selections toggle (multi-select) ─────────────────────
window.toggleAllowMultiple = function() {
    var cur = document.getElementById('aq_allow_multiple').value;
    var on  = cur != '1';
    document.getElementById('aq_allow_multiple').value = on ? '1' : '0';
    document.getElementById('amToggle').style.background = on ? '#0E7490' : '#E5E7EB';
    document.getElementById('amKnob').style.left = on ? '16px' : '2px';
};

// ─── Options list ─────────────────────────────────────────────────────────
var _options = [];
window.addOption = function() {
    var val = document.getElementById('newOptInput').value.trim();
    if (!val) return;
    pushOption(val);
    document.getElementById('newOptInput').value = '';
    document.getElementById('newOptInput').focus();
};
function pushOption(val) {
    _options.push(val);
    renderOptions();
}
window.removeOption = function(i) { _options.splice(i,1); renderOptions(); };
function renderOptions() {
    var html = _options.map(function(o, i) {
        return '<div style="display:flex;align-items:center;gap:8px;padding:6px 8px;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:5px;margin-bottom:5px;">'
             + '<span style="flex:1;font-size:13px;color:#374151;">' + o + '</span>'
             + '<button onclick="removeOption(' + i + ')" style="background:none;border:none;color:#EF4444;font-size:16px;cursor:pointer;line-height:1;">&#215;</button>'
             + '</div>';
    }).join('');
    document.getElementById('optList').innerHTML = html;
    document.getElementById('optCount').textContent = _options.length + ' option(s)';
}

// ─── Parent section select refresh ───────────────────────────────────────
function refreshParentSelect() {
    var mrQuestions = _localQuestions.filter(function(q) { return q.question_type === 'Multi Response'; });
    var $sel = document.getElementById('aq_parent_group');
    $sel.innerHTML = '<option value="">— None (standalone question) —</option>';
    mrQuestions.forEach(function(q, i) {
        var idx = _localQuestions.indexOf(q);
        var opt = document.createElement('option');
        opt.value = idx;
        opt.textContent = q.question;
        $sel.appendChild(opt);
    });
}

// ─── Save question locally (instead of AJAX) ─────────────────────────────
window.saveQuestionLocally = function() {
    var text = document.getElementById('aq_text').value.trim();
    var type = document.getElementById('aq_type').value;

    document.getElementById('aq_text_err').style.display = text ? 'none' : '';
    document.getElementById('aq_type_err').style.display = type ? 'none' : '';
    if (!text || !type) return;

    if ((type === 'Dropdown' || type === 'Multi select') && _options.length === 0) {
        document.getElementById('aq_opt_err').style.display = '';
        return;
    }
    document.getElementById('aq_opt_err').style.display = 'none';

    // Conditional: capture parent index and trigger value
    var condParentIdx = '';
    var condTrigger   = '';
    var condBody = document.getElementById('sec_cond_body');
    if (condBody && condBody.style.display !== 'none') {
        condParentIdx = document.getElementById('aq_cond_parent').value;
        condTrigger   = document.getElementById('aq_cond_value').value;
    }

    var q = {
        question:              text,
        question_type:         type,
        answer_type:           document.getElementById('aq_required').value,
        options:               _options.join('|'),
        help_text:             document.getElementById('aq_help').value.trim(),
        allow_multiple_images: document.getElementById('aq_allow_multi_img').value,
        parent_group_id:       document.getElementById('aq_parent_group').value,
        cond_parent_idx:       condParentIdx,
        cond_trigger:          condTrigger,
        validation_rule:       document.getElementById('aq_val_rule').value,
        validation_min:        document.getElementById('aq_val_min').value,
        validation_max:        document.getElementById('aq_val_max').value,
        validation_regex:      document.getElementById('aq_val_regex').value,
    };

    // Read editing index from both JS variable and DOM backup — use whichever is valid
    var domIdx = document.getElementById('aq_editing_idx').value;
    var resolvedIdx = (_editingIdx !== null) ? _editingIdx
                    : (domIdx !== '' ? parseInt(domIdx) : null);

    if (resolvedIdx !== null && !isNaN(resolvedIdx)) {
        _editingIdx = resolvedIdx; // sync back
        // Update the question itself
        _localQuestions[resolvedIdx] = q;
        // If MR: remove old sub-questions and re-insert updated ones
        if (type === 'Multi Response') {
            _localQuestions = _localQuestions.filter(function(sq, idx) {
                return idx === _editingIdx || String(sq.parent_group_id) !== String(_editingIdx);
            });
            // Insert updated sub-questions right after the MR parent
            var insertAt = _editingIdx + 1;
            var newSubs = _mrSubQuestions.map(function(sq) {
                return {question: sq.text, question_type: sq.type, answer_type: sq.required,
                        options: '', help_text: '', allow_multiple_images: '0',
                        parent_group_id: String(_editingIdx), cond_parent_idx: '', cond_trigger: ''};
            });
            _localQuestions.splice.apply(_localQuestions, [insertAt, 0].concat(newSubs));
        }
    } else {
        _localQuestions.push(q);
        // Auto-add inline sub-questions right after the Multi Response parent
        if (type === 'Multi Response' && _mrSubQuestions.length > 0) {
            var mrIdx = _localQuestions.length - 1;
            _mrSubQuestions.forEach(function(sq) {
                _localQuestions.push({
                    question:              sq.text,
                    question_type:         sq.type,
                    answer_type:           sq.required,
                    options:               '',
                    help_text:             '',
                    allow_multiple_images: '0',
                    parent_group_id:       String(mrIdx),
                    cond_parent_idx:       '',
                    cond_trigger:          ''
                });
            });
        }
    }

    _editingIdx = null;
    document.getElementById('aq_editing_idx').value = '';
    renderPreviewTable();
    closeAddPanel();
};

// ─── Preview table ────────────────────────────────────────────────────────
function renderPreviewTable() {
    var tbody = document.getElementById('previewBody');
    document.getElementById('qCount').textContent = _localQuestions.length;

    if (_localQuestions.length === 0) {
        tbody.innerHTML = '<tr id="emptyRow"><td colspan="5" style="text-align:center;color:#9CA3AF;padding:28px;font-size:13px;">No questions added yet. Click <strong>+ Add Question</strong> to start.</td></tr>';
        return;
    }

    var html = '';
    _localQuestions.forEach(function(q, i) {
        var isMR = q.question_type === 'Multi Response';
        var rowStyle = isMR ? 'background:#EFF6FF;font-weight:600;' : (i % 2 === 0 ? 'background:#fff;' : 'background:#F9FAFB;');
        var parentLabel = '';
        if (q.parent_group_id !== '' && q.parent_group_id !== null && q.parent_group_id !== undefined) {
            var parent = _localQuestions[parseInt(q.parent_group_id)];
            if (parent) parentLabel = '<br><small style="color:#6B7280;font-weight:400;">↳ ' + parent.question.substring(0,40) + '</small>';
        }
        html += '<tr style="' + rowStyle + '">'
            + '<td style="padding:10px 12px;color:#6B7280;font-size:12px;vertical-align:middle;">' + (i+1) + '</td>'
            + '<td style="padding:10px 12px;vertical-align:middle;">'
            +   '<span style="font-size:13px;color:#111827;">' + q.question + '</span>' + parentLabel
            +   (q.options ? '<br><small style="color:#6B7280;font-size:11px;">Options: ' + q.options.replace(/\|/g,', ') + '</small>' : '')
            + '</td>'
            + '<td style="padding:10px 12px;vertical-align:middle;">'
            +   '<span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:3px;background:#F3F4F6;color:#374151;">' + q.question_type + '</span>'
            + '</td>'
            + '<td style="padding:10px 12px;vertical-align:middle;text-align:center;">'
            +   (q.answer_type == 1 ? '<span style="color:#DC2626;font-size:11px;font-weight:600;">Required</span>' : '<span style="color:#6B7280;font-size:11px;">Optional</span>')
            + '</td>'
            + '<td style="padding:10px 12px;vertical-align:middle;text-align:center;">'
            +   '<button type="button" onclick="editLocalQ(' + i + ')" style="background:none;border:none;color:#2563EB;font-size:12px;cursor:pointer;padding:2px 6px;">Edit</button>'
            +   '<button type="button" onclick="deleteLocalQ(' + i + ')" style="background:none;border:none;color:#DC2626;font-size:12px;cursor:pointer;padding:2px 6px;">Delete</button>'
            + '</td>'
            + '</tr>';
    });
    tbody.innerHTML = html;
}

window.deleteLocalQ = function(i) {
    // Also remove any child questions (sub-questions whose parent_group_id === i)
    _localQuestions = _localQuestions.filter(function(q, idx) {
        return idx !== i && String(q.parent_group_id) !== String(i);
    });
    // Re-index parent_group_id references after deletion
    _localQuestions = _localQuestions.map(function(q) {
        var pgid = parseInt(q.parent_group_id);
        if (!isNaN(pgid) && pgid > i) {
            return Object.assign({}, q, {parent_group_id: String(pgid - 1)});
        }
        return q;
    });
    renderPreviewTable();
};

window.editLocalQ = function(editIdx) {
    var q = _localQuestions[editIdx];
    if (!q) return;

    _editingIdx = editIdx;
    document.getElementById('aq_editing_idx').value = editIdx; // DOM backup
    resetPanel();

    // Populate text and type
    document.getElementById('aq_text').value = q.question;
    document.getElementById('panelTitle').textContent = 'Edit Question';
    document.getElementById('aq_save_btn').textContent = 'Update Question';

    // Required
    document.getElementById('aq_required').value = q.answer_type;
    setRequiredToggle(q.answer_type == 1);

    // Help text
    document.getElementById('aq_help').value = q.help_text || '';

    // Select type — this shows/hides sections automatically
    selectQType(q.question_type);

    // Options (Dropdown / Multi select)
    if (q.options) {
        q.options.split('|').forEach(function(o) { if (o.trim()) pushOption(o.trim()); });
    }

    // Allow multiple images (Image type)
    if (q.allow_multiple_images == 1) {
        document.getElementById('aq_allow_multi_img').value = '1';
        setToggle('miToggle', 'miKnob', true);
    }

    // Sub-question of Multi Response parent
    if (q.parent_group_id !== '' && q.parent_group_id !== null && q.parent_group_id !== undefined) {
        refreshParentSelect();
        document.getElementById('aq_parent_group').value = String(q.parent_group_id);
        document.getElementById('sec_parent_section').style.display = '';
    }

    // Conditional logic
    if (q.cond_parent_idx !== '' && q.cond_parent_idx !== undefined && q.cond_parent_idx !== null) {
        refreshCondParentSelect();
        document.getElementById('sec_conditional').style.display = '';
        document.getElementById('sec_cond_body').style.display = '';
        document.getElementById('condToggle').style.background = '#111827';
        document.getElementById('condKnob').style.left = '19px';
        document.getElementById('aq_cond_parent').value = String(q.cond_parent_idx);
        onCondParentChange();
        if (q.cond_trigger) document.getElementById('aq_cond_value').value = q.cond_trigger;
    }

    // Multi Response: restore sub-questions from _localQuestions
    if (q.question_type === 'Multi Response') {
        _mrSubQuestions = _localQuestions
            .filter(function(sq, idx) { return String(sq.parent_group_id) === String(editIdx); })
            .map(function(sq) { return {text: sq.question, type: sq.question_type, required: sq.answer_type}; });
        renderMrSubQList();
    }

    // Validation
    if (q.validation_rule && q.validation_rule !== 'none') {
        document.getElementById('aq_val_rule').value = q.validation_rule;
        onValRuleChange();
        if (q.validation_min) document.getElementById('aq_val_min').value = q.validation_min;
        if (q.validation_max) document.getElementById('aq_val_max').value = q.validation_max;
        if (q.validation_regex) document.getElementById('aq_val_regex').value = q.validation_regex;
    }

    // Open panel
    document.getElementById('addQPanel').style.display = '';
    document.getElementById('addQOverlay').style.display = '';
    document.getElementById('aq_text').focus();
};

// ─── Reset panel ──────────────────────────────────────────────────────────
function resetPanel() {
    _options = [];
    _mrSubQuestions = [];
    document.getElementById('aq_text').value = '';
    document.getElementById('aq_type').value = '';
    document.getElementById('aq_required').value = '1';
    document.getElementById('aq_help').value = '';
    document.getElementById('aq_allow_multi_img').value = '0';
    document.getElementById('aq_allow_multiple').value = '0';
    document.getElementById('aq_text_err').style.display = 'none';
    document.getElementById('aq_type_err').style.display = 'none';
    document.getElementById('aq_opt_err').style.display = 'none';
    document.getElementById('sec_options').style.display = 'none';
    document.getElementById('sec_file').style.display = 'none';
    document.getElementById('sec_date').style.display = 'none';
    document.getElementById('sec_conditional').style.display = 'none';
    document.getElementById('sec_mr_subquestions').style.display = 'none';
    document.getElementById('sec_parent_section').style.display = 'none';
    document.getElementById('sec_validation').style.display = 'none';
    document.getElementById('aq_val_rule').value = 'none';
    document.getElementById('sec_val_range').style.display = 'none';
    document.getElementById('sec_val_regex').style.display = 'none';
    document.getElementById('aq_val_min').value = '';
    document.getElementById('aq_val_max').value = '';
    document.getElementById('aq_val_regex').value = '';
    document.getElementById('mr_sq_list').innerHTML = '';
    // Reset conditional toggle to OFF state
    document.getElementById('sec_cond_body').style.display = 'none';
    document.getElementById('condToggle').style.background = '#E5E7EB';
    document.getElementById('condKnob').style.left = '2px';
    document.getElementById('aq_cond_parent').value = '';
    document.getElementById('sec_cond_val').style.display = 'none';
    setRequiredToggle(true);
    setToggle('miToggle','miKnob', false);
    setToggle('amToggle','amKnob', false);
    renderOptions();

    // Reset type buttons
    document.querySelectorAll('#aq_type_btns button').forEach(function(btn) {
        btn.style.background = '#F9FAFB';
        btn.style.color = '#374151';
        btn.style.borderColor = '#E5E7EB';
    });
    _currentType = '';
    _editingIdx = null;
    document.getElementById('aq_editing_idx').value = ''; // clear DOM backup
}

// ─── Form submit: inject hidden inputs ────────────────────────────────────
document.getElementById('createActivityForm').addEventListener('submit', function() {
    var isManual = document.getElementById('sec_manual').style.display !== 'none';
    if (!isManual) return; // Excel mode — nothing to inject

    var container = document.getElementById('hiddenInputsContainer');
    container.innerHTML = '';
    _localQuestions.forEach(function(q, i) {
        function hi(name, val) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'questions[' + i + '][' + name + ']';
            inp.value = val || '';
            container.appendChild(inp);
        }
        hi('question',              q.question);
        hi('question_type',         q.question_type);
        hi('answer_type',           q.answer_type);
        hi('options',               q.options);
        hi('help_text',             q.help_text);
        hi('allow_multiple_images', q.allow_multiple_images);
        hi('parent_group_id',       q.parent_group_id);
        hi('cond_parent_idx',       q.cond_parent_idx || '');
        hi('cond_trigger',          q.cond_trigger || '');
        hi('validation_rule',       q.validation_rule || 'none');
        hi('validation_min',        q.validation_min || '');
        hi('validation_max',        q.validation_max || '');
        hi('validation_regex',      q.validation_regex || '');
    });
});
</script>
@endsection
