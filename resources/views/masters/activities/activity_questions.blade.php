@extends('template.layouts.simple.master')
@section('content')
    {{-- ── Add Question Overlay + Panel ─────────────────────────────────────── --}}
    <div id="addQOverlay" onclick="closeAddPanel()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1040;"></div>

    <div id="addQPanel" style="display:none;position:fixed;top:0;right:0;height:100vh;width:580px;max-width:98vw;background:#fff;z-index:1050;box-shadow:-4px 0 24px rgba(0,0,0,.15);overflow-y:auto;font-family:system-ui,sans-serif;">
        <div style="padding:18px 20px 14px;border-bottom:1px solid #EBEBF4;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1;">
            <div>
                <div style="font-size:16px;font-weight:700;color:#111827;" id="panelTitle">Add Question</div>
                <div style="font-size:12px;color:#9CA3AF;margin-top:1px;">{{ optional($activity)->activity_name }}</div>
            </div>
            <button onclick="closeAddPanel()" style="background:none;border:none;font-size:22px;color:#6B7280;cursor:pointer;line-height:1;padding:2px 6px;">×</button>
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
                    @php
                    $qTypes = [
                        ['Yes / No','#15803D','#F0FDF4'],['Free Text','#1D4ED8','#EFF6FF'],
                        ['Dropdown','#7C3AED','#F5F3FF'],['Multi select','#0E7490','#ECFEFF'],
                        ['Image','#B45309','#FFFBEB'],['Video','#9333EA','#FAF5FF'],
                        ['Date','#0F766E','#F0FDFA'],['Date & Time','#0F766E','#F0FDFA'],
                        ['File Upload','#1D4ED8','#EFF6FF'],['Location','#DC2626','#FEF2F2'],
                        ['Audio','#6D28D9','#F5F3FF'],['Subjective','#6B7280','#F9FAFB'],
                        ['Multi Response','#1E3A5F','#DBEAFE'],
                    ];
                    @endphp
                    @foreach($qTypes as $qt)
                    <button type="button" onclick="selectQType('{{ $qt[0] }}')"
                            data-type="{{ $qt[0] }}"
                            data-color="{{ $qt[1] }}" data-bg="{{ $qt[2] }}"
                            style="padding:4px 9px;border-radius:4px;font-size:11px;font-weight:600;border:1.5px solid #E5E7EB;background:#F9FAFB;color:#374151;cursor:pointer;transition:all .12s;">
                        {{ $qt[0] }}
                    </button>
                    @endforeach
                </div>
                <input type="hidden" id="aq_type" value="">
                <div id="aq_type_err" style="font-size:11px;color:#DC2626;margin-top:3px;display:none;">Please select a question type.</div>
            </div>

            {{-- Required + Help --}}
            <div style="display:flex;gap:10px;margin-bottom:16px;align-items:center;">
                <label style="display:flex;align-items:center;gap:7px;cursor:pointer;">
                    <div onclick="toggleRequired()" id="reqToggle" style="width:36px;height:20px;border-radius:10px;background:#111827;position:relative;cursor:pointer;flex:none;transition:background .15s;">
                        <div id="reqKnob" style="width:16px;height:16px;border-radius:50%;background:white;position:absolute;top:2px;left:18px;transition:left .15s;box-shadow:0 1px 3px rgba(0,0,0,.2);"></div>
                    </div>
                    <span style="font-size:13px;font-weight:500;color:#374151;">Required</span>
                </label>
                <input type="hidden" id="aq_required" value="1">
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Help Text <span style="font-size:11px;font-weight:400;color:#9CA3AF;">(optional)</span></label>
                <input type="text" id="aq_help" placeholder="Hint shown below the question..." style="width:100%;border:1px solid #E5E7EB;border-radius:6px;padding:8px 11px;font-size:13px;color:#111827;outline:none;box-sizing:border-box;">
            </div>

            <div style="height:1px;background:#EBEBF4;margin:0 -20px 18px;"></div>

            {{-- OPTIONS section (Dropdown / Multi select) --}}
            <div id="sec_options" style="display:none;margin-bottom:18px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <label style="font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:.5px;">Answer Options <span style="color:#DC2626;">*</span></label>
                    <span id="optCount" style="font-size:11.5px;color:#9CA3AF;">0 option(s)</span>
                </div>
                {{-- Multi-select toggle --}}
                <div id="ms_extras" style="display:none;padding:9px 11px;background:#F8F8FC;border:1px solid #EBEBF4;border-radius:6px;margin-bottom:10px;">
                    <label style="display:flex;align-items:center;gap:7px;cursor:pointer;">
                        <div onclick="toggleAllowMultiple()" id="amToggle" style="width:32px;height:18px;border-radius:9px;background:#E5E7EB;position:relative;cursor:pointer;flex:none;transition:background .15s;">
                            <div id="amKnob" style="width:14px;height:14px;border-radius:50%;background:white;position:absolute;top:2px;left:2px;transition:left .15s;box-shadow:0 1px 2px rgba(0,0,0,.2);"></div>
                        </div>
                        <span style="font-size:12px;color:#374151;">Allow Multiple Selections</span>
                    </label>
                    <input type="hidden" id="aq_allow_multiple" value="0">
                </div>
                <div id="optList" style="margin-bottom:8px;"></div>
                <div style="display:flex;gap:7px;">
                    <input type="text" id="newOptInput" placeholder="Type option and press Enter..." onkeydown="if(event.key==='Enter'){event.preventDefault();addOption();}" style="flex:1;border:1px solid #E5E7EB;border-radius:5px;padding:7px 10px;font-size:13px;color:#374151;outline:none;">
                    <button onclick="addOption()" style="padding:7px 13px;background:#111827;color:white;border:none;border-radius:5px;font-size:13px;cursor:pointer;white-space:nowrap;">+ Add</button>
                </div>
                <div id="aq_opt_err" style="font-size:11px;color:#DC2626;margin-top:4px;display:none;">Add at least one option.</div>
                <div style="height:1px;background:#EBEBF4;margin:18px -20px 0;"></div>
            </div>

            {{-- FILE CONFIG (Image / File Upload) --}}
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
                {{-- Allow Multiple Images toggle (Image only) --}}
                <div id="sec_multi_img" style="display:none;">
                    <label style="display:flex;align-items:center;gap:7px;cursor:pointer;">
                        <div onclick="toggleMultipleImages()" id="miToggle" style="width:36px;height:20px;border-radius:10px;background:#E5E7EB;position:relative;cursor:pointer;flex:none;transition:background .15s;">
                            <div id="miKnob" style="width:16px;height:16px;border-radius:50%;background:white;position:absolute;top:2px;left:2px;transition:left .15s;box-shadow:0 1px 3px rgba(0,0,0,.2);"></div>
                        </div>
                        <span style="font-size:13px;color:#374151;">Allow Multiple Images</span>
                    </label>
                    <input type="hidden" id="aq_allow_multi_img" value="0">
                </div>
                <div style="height:1px;background:#EBEBF4;margin:10px -20px 0;"></div>
            </div>

            {{-- DATE CONFIG --}}
            <div id="sec_date" style="display:none;margin-bottom:18px;">
                <label style="display:block;font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:9px;">Date Range <span style="font-size:10px;font-weight:400;text-transform:none;color:#9CA3AF;">(optional)</span></label>
                <div style="display:flex;gap:9px;">
                    <div style="flex:1;">
                        <label style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px;">Min Date</label>
                        <input type="date" id="aq_date_min" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:7px 10px;font-size:13px;color:#374151;outline:none;box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px;">Max Date</label>
                        <input type="date" id="aq_date_max" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:7px 10px;font-size:13px;color:#374151;outline:none;box-sizing:border-box;">
                    </div>
                </div>
                <div style="height:1px;background:#EBEBF4;margin:18px -20px 0;"></div>
            </div>

            {{-- VALIDATION --}}
            <div id="sec_validation" style="display:none;margin-bottom:18px;">
                <label style="display:block;font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:9px;">Answer Validation</label>
                <div style="margin-bottom:10px;">
                    <label style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px;">Validation Rule</label>
                    <select id="aq_val_rule" onchange="onValRuleChange()" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:8px 11px;font-size:13px;color:#374151;background:white;outline:none;">
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
                    <div style="flex:1;"><label id="valMinLbl" style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px;">Min</label><input type="number" id="aq_val_min" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:7px 10px;font-size:13px;outline:none;box-sizing:border-box;"></div>
                    <div style="flex:1;"><label id="valMaxLbl" style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px;">Max</label><input type="number" id="aq_val_max" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:7px 10px;font-size:13px;outline:none;box-sizing:border-box;"></div>
                </div>
                <div id="sec_val_regex" style="display:none;margin-bottom:10px;">
                    <label style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px;">Regex Pattern</label>
                    <input type="text" id="aq_val_regex" placeholder="e.g. ^[A-Z]{2}[0-9]{6}$" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:7px 10px;font-size:13px;font-family:monospace;outline:none;box-sizing:border-box;">
                </div>
                <div style="height:1px;background:#EBEBF4;margin:0 -20px 0;"></div>
            </div>

            {{-- CONDITIONAL LOGIC — only for Yes/No and Dropdown --}}
            <div id="sec_conditional_wrapper" style="display:none;margin-bottom:18px;margin-top:18px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <div>
                        <div style="font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:.5px;">Conditional Logic</div>
                        <div style="font-size:12px;color:#9CA3AF;margin-top:2px;">Show only when a specific answer is given</div>
                    </div>
                    <div onclick="toggleDep()" id="depToggle" style="width:38px;height:21px;border-radius:11px;background:#E5E7EB;position:relative;cursor:pointer;flex:none;transition:background .15s;">
                        <div id="depKnob" style="width:17px;height:17px;border-radius:50%;background:white;position:absolute;top:2px;left:2px;transition:left .15s;box-shadow:0 1px 3px rgba(0,0,0,.2);"></div>
                    </div>
                </div>
                <div id="sec_dep" style="display:none;padding:13px;background:#F8F8FC;border:1px solid #E8E8F0;border-radius:7px;">
                    <div style="margin-bottom:10px;">
                        <label style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px;">Depends on question</label>
                        <select id="aq_dep_parent" onchange="onDepParentChange()" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:8px 11px;font-size:13px;color:#374151;background:white;outline:none;">
                            <option value="">— Select parent question —</option>
                            @foreach($activity->questions->where('question_type', '!=', 'Multi Response') as $pq)
                                <option value="{{ $pq->id }}"
                                        data-type="{{ $pq->question_type }}"
                                        data-options='@json($pq->getOptions->pluck("option"))'>
                                    [{{ $pq->question_type }}] {{ $pq->question }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div id="sec_dep_val" style="display:none;">
                        <label style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px;">Condition</label>
                        <select id="aq_dep_value" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:8px 11px;font-size:13px;color:#374151;background:white;outline:none;">
                            <option value="">— Select condition —</option>
                        </select>
                    </div>
                </div>
            </div>{{-- end sec_conditional_wrapper --}}

            {{-- INLINE SUB-QUESTIONS — shown in edit mode for Multi Response questions --}}
            <div id="sec_subquestions_view" style="display:none;margin-bottom:18px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <label style="font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:.5px;">Sub-Questions</label>
                    <span id="subq_count" style="font-size:11.5px;color:#9CA3AF;"></span>
                </div>
                <div id="subq_list" style="margin-bottom:6px;"></div>

                {{-- Link existing question --}}
                <div id="sec_link_existing" style="display:none;margin-bottom:8px;padding:10px;background:#F8F8FC;border:1px solid #E8E8F0;border-radius:7px;">
                    <label style="display:block;font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Link existing question</label>
                    <select id="existing_subq_select" class="form-select form-select-sm mb-2">
                        <option value="">— select a question —</option>
                        @foreach($activity->questions->where('is_parent', 0)->where('question_type','!=','Multi Response') as $aqOpt)
                            <option value="{{ $aqOpt->id }}" data-text="{{ $aqOpt->question }}" data-type="{{ $aqOpt->question_type }}">
                                [{{ $aqOpt->question_type }}] {{ Str::limit($aqOpt->question, 60) }}
                            </option>
                        @endforeach
                    </select>
                    <div class="d-flex gap-2">
                        <button type="button" onclick="linkExistingSubQ()" class="btn btn-sm btn-primary flex-grow-1">Link</button>
                        <button type="button" onclick="hideExistingSubQPanel()" class="btn btn-sm btn-outline-secondary">Cancel</button>
                    </div>
                </div>

                {{-- Buttons row --}}
                <div class="d-flex gap-2 mt-1">
                    <button type="button" onclick="addInlineSubQuestion()"
                        style="flex:1;padding:8px;border:1.5px dashed #C7D2FE;border-radius:6px;background:#F5F7FF;color:#4338CA;font-size:13px;font-weight:500;cursor:pointer;">
                        + Create New
                    </button>
                    <button type="button" onclick="toggleLinkExisting()"
                        style="flex:1;padding:8px;border:1.5px dashed #10B981;border-radius:6px;background:#F0FDF4;color:#065F46;font-size:13px;font-weight:500;cursor:pointer;">
                        &#128279; Link Existing
                    </button>
                </div>
                <div style="height:1px;background:#EBEBF4;margin:16px -20px 0;"></div>
            </div>

            {{-- ADD AS SUB-QUESTION OF removed — sub-question linking is done via the
                 inline SUB-QUESTIONS section when editing a Multi Response question,
                 or via the dedicated Sub-Questions management page --}}
            <input type="hidden" id="aq_parent_group" value="">
            <div id="sec_group" style="display:none;"></div>

            <input type="hidden" id="aq_editing_id" value="">

            {{-- Save button --}}
            <div style="padding-top:6px;">
                <button onclick="saveQuestion()" id="aq_save_btn" style="width:100%;padding:11px;background:#111827;color:white;border:none;border-radius:7px;font-size:14px;font-weight:600;cursor:pointer;transition:background .15s;">Save Question</button>
                <div id="aq_save_err" style="font-size:11px;color:#DC2626;margin-top:6px;display:none;"></div>
            </div>
        </div>
    </div>

    <div class="page-body">
        @php
            // ── Build hierarchical question list (server-side paginated) ──────
            $allQuestions = $activity->questions->keyBy('id');

            // Stats (from full activity, not paginated subset)
            $totalQ      = $activity->questions->count();
            $requiredQ   = $activity->questions->where('answer_type', 1)->count();
            $conditionalQ= $activity->questions->whereNotNull('parent_question_id')->count();
            $mrGroups    = $activity->questions->where('question_type', 'Multi Response')->count();

            // Build ordered rows for the CURRENT PAGE only
            $orderedRows = [];
            $startSeq = ($parentQuestions->currentPage() - 1) * $parentQuestions->perPage() + 1;
            $codeSeq  = $startSeq - 1;

            foreach ($parentQuestions as $pq) {
                $codeSeq++;
                $code = 'Q' . str_pad($codeSeq, 3, '0', STR_PAD_LEFT);
                $isMR = $pq->question_type === 'Multi Response';

                $conditionalChildren = $allChildren->where('parent_question_id', $pq->id)->where('is_parent', 0);
                $subChildren = isset($subLinks[$pq->id])
                    ? $subLinks[$pq->id]->map(fn($sl) => $allChildren->get($sl->child_question_id) ?? $allQuestions->get($sl->child_question_id))->filter()
                    : collect();

                $orderedRows[] = [
                    'q'        => $pq, 'code' => $code, 'depth' => 0,
                    'kind'     => $isMR ? 'mr' : 'parent', 'sub_count' => $subChildren->count(),
                ];

                $subAlpha = 0;
                foreach ($subChildren as $sq) {
                    $subAlpha++;
                    $orderedRows[] = ['q' => $sq, 'code' => $code.chr(96+$subAlpha), 'depth' => 1, 'kind' => 'subq'];
                }

                $condAlpha = $subAlpha;
                foreach ($conditionalChildren as $cq) {
                    $condAlpha++;
                    $orderedRows[] = ['q' => $cq, 'code' => $code.chr(96+$condAlpha), 'depth' => 1, 'kind' => 'conditional', 'trigger' => $cq->parent_value];
                }
            }

            // Type colour map
            $typeColors = [
                'Yes / No'      => ['#15803D','#F0FDF4'],
                'Free Text'     => ['#1D4ED8','#EFF6FF'],
                'Dropdown'      => ['#7C3AED','#F5F3FF'],
                'Multi select'  => ['#0E7490','#ECFEFF'],
                'Image'         => ['#B45309','#FFFBEB'],
                'Video'         => ['#9333EA','#FAF5FF'],
                'Date'          => ['#0F766E','#F0FDFA'],
                'Date & Time'   => ['#0F766E','#F0FDFA'],
                'File Upload'   => ['#1D4ED8','#EFF6FF'],
                'Location'      => ['#DC2626','#FEF2F2'],
                'Audio'         => ['#6D28D9','#F5F3FF'],
                'Subjective'    => ['#6B7280','#F9FAFB'],
                'Multi Response'=> ['#1E3A5F','#DBEAFE'],
                'Number'        => ['#D97706','#FFFBEB'],
            ];
        @endphp

        <div class="row">
            <div class="col-sm-12">

                {{-- ── Stats cards ── --}}
                <div class="row g-3 mb-3">
                    @foreach([
                        [$totalQ,      'Total Questions',       '#111827', '#fff'],
                        [$requiredQ,   'Required',              '#DC2626', '#FEF2F2'],
                        [$conditionalQ,'Conditional',           '#7C3AED', '#F5F3FF'],
                        [$mrGroups,    'Multi-Response Groups', '#1D4ED8', '#EFF6FF'],
                    ] as [$val, $label, $color, $bg])
                    <div class="col-6 col-md-3">
                        <div style="background:{{ $bg }};border:1px solid {{ $color }}22;border-radius:10px;padding:16px 20px;">
                            <div style="font-size:28px;font-weight:800;color:{{ $color }};">{{ $val }}</div>
                            <div style="font-size:12px;color:#6B7280;margin-top:2px;">{{ $label }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <h4 class="mb-0" style="font-size:16px;">{{ optional($activity)->activity_name }}</h4>
                            <small class="text-muted">{{ $totalQ }} question(s) &bull; {{ $parentQuestions->total() }} parent questions</small>
                        </div>
                        <div class="d-flex gap-2 flex-wrap align-items-center">
                            {{-- Server-side search form --}}
                            <form method="GET" action="{{ route('activities.question', $activity->id) }}" class="d-flex gap-2 align-items-center" id="qFilterForm">
                                <input type="text" name="q" id="qSearch" value="{{ $search }}" placeholder="Search questions…"
                                       class="form-control form-control-sm" style="width:220px;"
                                       oninput="qDebouncedSearch()"
                                       autocomplete="off">
                                <input type="hidden" name="per_page" value="{{ $perPage }}">
                                <input type="hidden" name="sort"     value="{{ $sortBy }}">
                                <input type="hidden" name="dir"      value="{{ $sortDir }}">
                            </form>
                            <select class="form-select form-select-sm" style="width:auto;" onchange="document.getElementById('qFilterForm').per_page.value=this.value;document.getElementById('qFilterForm').submit()">
                                @foreach([10,25,50,100] as $n)
                                    <option value="{{ $n }}" {{ $perPage==$n?'selected':'' }}>{{ $n }} per page</option>
                                @endforeach
                            </select>
                            <button type="button" onclick="$('#previewModal').modal('show')"
                                    class="btn btn-outline-info btn-sm">
                                &#128065; Preview
                            </button>
                            <button onclick="openAddPanel()" type="button"
                                    style="padding:7px 16px;background:#111827;color:white;border:none;border-radius:5px;font-size:13px;font-weight:500;cursor:pointer;">
                                + Add Question
                            </button>
                            <a href="{{ route('activities.list') }}" class="btn btn-outline-secondary btn-sm">View Activities</a>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        {{-- max-height + overflow-y:auto creates scroll context so sticky <th> works --}}
                    <div style="max-height:70vh;overflow-y:auto;overflow-x:auto;">
                            <table style="width:100%;border-collapse:collapse;font-size:13px;" id="question_table">
                                <thead>
                                    @php $thBase = 'position:sticky;top:0;z-index:20;background:#F3F4F6;border-bottom:2px solid #E5E7EB;padding:11px 12px;color:#6B7280;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;'; @endphp
                                    <tr>
                                        <th style="{{ $thBase }}width:36px;text-align:center;font-weight:400;"></th>
                                        <th style="{{ $thBase }}width:70px;">Code</th>
                                        <th style="{{ $thBase }}width:140px;">Type</th>
                                        <th style="{{ $thBase }}">Question</th>
                                        <th style="{{ $thBase }}width:160px;">Status</th>
                                        <th style="{{ $thBase }}width:120px;text-align:center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="qTableBody">
                                @foreach($orderedRows as $row)
                                    @php
                                        $q     = $row['q'];
                                        $code  = $row['code'];
                                        $depth = $row['depth'];
                                        $kind  = $row['kind'];
                                        $isMR  = $q->question_type === 'Multi Response';
                                        [$tc, $tbg] = $typeColors[$q->question_type] ?? ['#6B7280','#F9FAFB'];

                                        // Row bg
                                        $rowBg  = $isMR ? '#EFF6FF' : ($depth > 0 ? '#FAFAFA' : '#fff');
                                        $leftBorder = '';
                                        if ($kind === 'subq')        $leftBorder = 'border-left:3px solid #4a6cf7;';
                                        if ($kind === 'conditional') $leftBorder = 'border-left:3px solid #7C3AED;';
                                        if ($kind === 'mr')          $leftBorder = 'border-left:3px solid #1D4ED8;';
                                        $indent = $depth * 24;
                                    @endphp
                                    <tr data-qtype="{{ $q->question_type }}" data-qtext="{{ strtolower($q->question) }} {{ strtolower($code) }}"
                                        style="background:{{ $rowBg }};{{ $leftBorder }}border-bottom:1px solid #F3F4F6;">

                                        {{-- Drag handle --}}
                                        <td style="padding:10px 8px;text-align:center;color:#D1D5DB;cursor:grab;">⋮⋮</td>

                                        {{-- Code --}}
                                        <td style="padding:10px 12px;">
                                            <span style="font-size:11px;font-family:monospace;background:#F3F4F6;border:1px solid #E5E7EB;border-radius:4px;padding:2px 7px;color:#374151;font-weight:600;">{{ $code }}</span>
                                        </td>

                                        {{-- Type badge --}}
                                        <td style="padding:10px 12px;">
                                            <span style="font-size:11px;font-weight:700;padding:3px 9px;border-radius:4px;background:{{ $tbg }};color:{{ $tc }};">
                                                {{ $q->question_type }}
                                            </span>
                                        </td>

                                        {{-- Question text --}}
                                        <td style="padding:10px 12px;">
                                            <div style="padding-left:{{ $indent }}px;display:flex;align-items:flex-start;gap:8px;">
                                                @if($depth > 0)
                                                    <span style="color:#9CA3AF;font-size:13px;flex-shrink:0;">↳</span>
                                                @endif
                                                <div>
                                                    <div style="font-weight:{{ $isMR ? '700' : '500' }};color:#111827;line-height:1.4;">
                                                        {{ $q->question }}
                                                    </div>
                                                    @if($kind === 'conditional' && !empty($row['trigger']))
                                                        <div style="font-size:11px;color:#7C3AED;margin-top:2px;">
                                                            Shows when answer = "{{ $row['trigger'] }}"
                                                        </div>
                                                    @endif
                                                    @if($isMR && ($row['sub_count'] ?? 0) > 0)
                                                        <div style="font-size:11px;color:#1D4ED8;margin-top:2px;">
                                                            {{ $row['sub_count'] }} sub-question(s)
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Status badges --}}
                                        <td style="padding:10px 12px;">
                                            <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                                @if($q->answer_type)
                                                    <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:3px;background:#FEF2F2;color:#DC2626;border:1px solid #FCA5A5;">Required</span>
                                                @else
                                                    <span style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:3px;background:#F3F4F6;color:#6B7280;">Optional</span>
                                                @endif
                                                @if($kind === 'subq')
                                                    <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:3px;background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;">Sub-Q</span>
                                                @endif
                                                @if($kind === 'conditional')
                                                    <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:3px;background:#F5F3FF;color:#7C3AED;border:1px solid #DDD6FE;">Conditional</span>
                                                @endif
                                                @if($isMR)
                                                    <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:3px;background:#DBEAFE;color:#1D4ED8;border:1px solid #93C5FD;">{{ $row['sub_count'] ?? 0 }} sub-Qs</span>
                                                @endif
                                            </div>
                                        </td>

                                        {{-- Actions --}}
                                        <td style="padding:10px 12px;text-align:center;">
                                            <div style="display:flex;align-items:center;justify-content:center;gap:6px;">
                                                @if(in_array($q->question_type, ['Dropdown','Multi select']))
                                                    <a href="{{ route('activity.question.dropdown', ['id' => $q->id]) }}" title="View Options"
                                                       style="color:#6B7280;font-size:16px;line-height:1;text-decoration:none;">&#128065;</a>
                                                @elseif($q->question_type === 'Subjective')
                                                    <a href="{{ route('activity.question.subjective', ['id' => $q->id]) }}" title="View Subjects"
                                                       style="color:#D97706;font-size:16px;text-decoration:none;">&#128065;</a>
                                                @endif
                                                @if($q->question_type === 'Multi Response')
                                                    <a href="{{ route('activity.question.sub_questions', ['question_id' => $q->id]) }}" title="Manage Sub Questions"
                                                       style="color:#059669;font-size:16px;text-decoration:none;">&#9783;</a>
                                                @endif
                                                <span class="edit" title="Edit"
                                                    data-question="{{ e($q->question) }}"
                                                    data-q_type="{{ $q->question_type }}"
                                                    data-sequence="{{ $q->question_sequence }}"
                                                    data-id="{{ $q->id }}"
                                                    data-answer_type="{{ $q->answer_type }}"
                                                    data-is_parent="{{ $q->is_parent }}"
                                                    data-parent_question_id="{{ $q->parent_question_id ?? '' }}"
                                                    data-parent_value="{{ e($q->parent_value ?? '') }}"
                                                    data-allow_multiple_images="{{ $q->allow_multiple_images ?? 0 }}"
                                                    data-help_text="{{ e($q->help_text ?? '') }}"
                                                    data-validation_rule="{{ $q->validation_rule ?? 'none' }}"
                                                    data-validation_min="{{ $q->validation_min ?? '' }}"
                                                    data-validation_max="{{ $q->validation_max ?? '' }}"
                                                    data-validation_regex="{{ e($q->validation_regex ?? '') }}"
                                                    data-file_types="{{ e($q->file_types ?? '') }}"
                                                    data-max_file_size="{{ $q->max_file_size_mb ?? '' }}"
                                                    data-date_min="{{ $q->date_min ?? '' }}"
                                                    data-date_max="{{ $q->date_max ?? '' }}"
                                                    data-options='@json($q->getOptions->pluck("option"))'
                                                    data-mr_parent_id="{{ optional(\App\Models\QuestionSubQuestion::where('child_question_id',$q->id)->first())->parent_question_id }}"
                                                    onclick="openEditPanel(this)"
                                                    style="cursor:pointer;color:#2563EB;font-size:16px;">&#9998;</span>
                                                <span class="delete" data-id="{{ $q->id }}" title="Delete"
                                                    style="cursor:pointer;color:#DC2626;font-size:16px;"
                                                    onclick="triggerDelete(this)">&#128465;</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Laravel server-side pagination — only shown when more than 1 page --}}
                        @if($parentQuestions->hasPages() || $search)
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 px-3 py-2 border-top" style="background:#F9FAFB;">
                            <small class="text-muted">
                                Showing {{ $parentQuestions->firstItem() }}–{{ $parentQuestions->lastItem() }} of {{ $parentQuestions->total() }} parent questions
                                @if($search) &bull; filtered by "<strong>{{ e($search) }}</strong>" @endif
                            </small>
                            @if($parentQuestions->hasPages())
                                {{ $parentQuestions->links() }}
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @php $dropdown_questions = $activity->questions->whereIn('question_type', ['Dropdown','Yes / No'])->values(); @endphp

        <div class="row" style="display:none;"><div class="col-sm-12"><div class="card"><div class="card-body">
            <div>
                        <div class="modal_container">
                            <div class="modal fade" id="questionEditModal" tabindex="-1" role="dialog"
                                 aria-labelledby="questionEditModal" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-body">
                                            <div class="modal-toggle-wrapper">

                                                <h4 class="text-center pb-2">Edit Question</h4>
                                                <form>
                                                    <input class="form-control" id="question_id" name="question_id"
                                                           type="hidden" placeholder="Enter question_id" required="">
                                                    <div class="col-xl-12 col-sm-12">
                                                        <label class="form-label" for="question">Question<span
                                                                class="txt-danger">*</span></label>
                                                        <input class="form-control" id="question" name="question"
                                                               type="text" placeholder="Enter Question" required="">
                                                    </div>
                                                    <div class="col-xl-12 col-sm-12">
                                                        <label class="form-label" for="question_type">Question Type<span
                                                                class="txt-danger">*</span></label>
                                                        <select class="form-control" name="question_type"
                                                                id="question_type">
                                                            <option value="Yes / No">Yes / No</option>
                                                            <option value="Free Text">Free Text</option>
                                                            <option value="Subjective">Subjective</option>
                                                            <option value="Dropdown">Dropdown</option>
                                                            <option value="Multi select">Multi select</option>
                                                            <option value="Image">Image</option>
                                                            <option value="Video">Video</option>
                                                            <option value="Date">Date</option>
                                                            <option value="Date & Time">Date & Time</option>
                                                            <option value="File Upload">File Upload</option>
                                                            <option value="Location">Location</option>
                                                            <option value="Audio">Audio</option>
                                                            <option value="Multi Response">Multi Response (Section / No Input)</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-xl-12 col-sm-12">
                                                        <label class="form-label" for="question_sequence">Question
                                                            Sequence<span class="txt-danger"></span></label>
                                                        <input class="form-control" id="question_sequence"
                                                               name="question_sequence" type="number"
                                                               placeholder="Enter question sequence">
                                                    </div>
                                                    <div class="col-xl-12 col-sm-12">
                                                        <label class="form-label" for="answer_type">Answer Type<span
                                                                class="txt-danger"></span></label>
                                                        <select class="form-control" name="answer_type"
                                                                id="answer_type">
                                                            <option value="1">Required</option>
                                                            <option value="0">Optional</option>
                                                        </select>
                                                    </div>
                                                    {{-- Allow Multiple Images — only for Image question type --}}
                                                    <div class="col-xl-12 col-sm-12 mt-3 d-none" id="allow_multiple_images_div">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox"
                                                                   id="allow_multiple_images" name="allow_multiple_images" value="1">
                                                            <label class="form-check-label" for="allow_multiple_images">
                                                                Allow Multiple Images
                                                                <small class="text-muted d-block">Auditor can upload more than one image for this question</small>
                                                            </label>
                                                        </div>
                                                    </div>

                                                    {{-- hidden fields kept for old-style backward compat (values cleared on multi-trigger save) --}}
                                                    <input type="hidden" id="parent_question" name="parent_question" value="">
                                                    <input type="hidden" id="parent_question_dropdown" name="parent_question_dropdown" value="">
                                                    <div class="d-none" id="parent_question_div"></div>
                                                    <div class="d-none" id="parent_question_dropdown_div"></div>

                                                    {{-- ── Select Parent Question (multi-trigger, Yes/No & Dropdown only) ── --}}
                                                    <div class="col-xl-12 col-sm-12 mt-3">
                                                        <label class="form-label fw-bold">Select Parent Question</label>
                                                        <small class="text-muted d-block mb-2">Select one or multiple Yes/No or Dropdown parents. Choose the trigger value that makes this question appear.</small>

                                                        {{-- Added trigger rows --}}
                                                        <div id="parent_triggers_list" class="mb-2"></div>

                                                        {{-- Select2 multi-select for parent questions --}}
                                                        <select id="new_parent_select" class="w-100 mb-2" multiple="multiple" style="width:100%;">
                                                            @foreach($activity->questions->whereIn('question_type', ['Yes / No', 'Dropdown']) as $aq)
                                                                @php
                                                                    $optionsJson = json_encode($aq->getOptions->pluck('option')->values()->all(), JSON_HEX_APOS | JSON_HEX_QUOT);
                                                                @endphp
                                                                <option value="{{ $aq->id }}"
                                                                        data-type="{{ $aq->question_type }}"
                                                                        data-options="{{ $optionsJson }}">
                                                                    {{ $aq->question }} ({{ $aq->question_type }})
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        <div class="d-flex gap-2 align-items-center mt-2">
                                                            <select id="new_trigger_select" class="form-select form-select-sm flex-grow-1" disabled>
                                                                <option value="">— always visible / no trigger —</option>
                                                            </select>
                                                            <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0"
                                                                    id="add_parent_trigger_btn">
                                                                <i class="fa fa-plus"></i> Add
                                                            </button>
                                                        </div>
                                                    </div>

                                                </form>
                                                <div class="d-flex justify-content-between mt-4">
                                                    <button class="btn btn-primary d-flex m-auto" type="button"
                                                            data-bs-dismiss="modal">Close
                                                    </button>
                                                    <button class="btn btn-primary d-flex m-auto"
                                                            id="update_question_btn" type="button"
                                                            data-bs-dismiss="modal">Update
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal fade" id="errorMessage_modal" tabindex="-1" role="dialog"
                                 aria-labelledby="errorMessage_modal" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-body">
                                            <div class="modal-toggle-wrapper">
                                                <ul class="modal-img">
                                                    <li><img src="{{url('assets/images/gif/danger.gif')}}" alt="error">
                                                    </li>
                                                </ul>
                                                <h4 class="text-center pb-2">Ohh! Something went wrong!</h4>
                                                <p class="text-center" id="error_message"></p>
                                                <button class="btn btn-primary d-flex m-auto" type="button"
                                                        data-bs-dismiss="modal">Close
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Zero Configuration  Ends-->
        </div>
    </div>

{{-- ── Auditor Preview Modal ──────────────────────────────────────────── --}}
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content" style="border:none;background:transparent;box-shadow:none;">

            {{-- Phone frame --}}
            <div style="background:#1a1a2e;border-radius:36px;padding:14px 8px;box-shadow:0 20px 60px rgba(0,0,0,.5);position:relative;">
                {{-- Speaker --}}
                <div style="width:60px;height:6px;background:#333;border-radius:3px;margin:0 auto 12px;"></div>

                {{-- Screen --}}
                <div style="background:#fff;border-radius:24px;overflow:hidden;max-height:72vh;display:flex;flex-direction:column;">

                    {{-- Status bar --}}
                    <div style="background:#1a1a2e;padding:8px 16px;display:flex;justify-content:space-between;align-items:center;flex-shrink:0;">
                        <span style="color:#fff;font-size:11px;font-weight:600;">{{ optional($activity)->activity_name }}</span>
                        <span style="color:#9CA3AF;font-size:10px;">Preview</span>
                    </div>

                    {{-- Questions scroll area --}}
                    <div style="overflow-y:auto;flex:1;padding:12px;background:#f5f5f5;">

                        @php
                            // Build ALL questions for preview (not just current page)
                            $allQ       = $activity->questions->keyBy('id');
                            $allParents = $activity->questions->where('is_parent', 1)->sortBy('question_sequence');
                            $allSubL    = \App\Models\QuestionSubQuestion::whereIn('parent_question_id', $allQ->keys())->orderBy('sequence')->get()->groupBy('parent_question_id');
                        @endphp

                        @foreach($allParents as $pq)
                            @php
                                $isMR   = $pq->question_type === 'Multi Response';
                                $subCh  = isset($allSubL[$pq->id]) ? $allSubL[$pq->id]->map(fn($sl)=>$allQ->get($sl->child_question_id))->filter() : collect();
                                $condCh = $activity->questions->where('parent_question_id', $pq->id)->where('is_parent', 0);
                            @endphp

                            @if($isMR)
                                {{-- Multi Response section --}}
                                <div style="background:#fff;border-radius:10px;margin-bottom:10px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08);">
                                    <div style="background:#1a1a2e;padding:8px 12px;">
                                        <div style="font-size:12px;font-weight:700;color:#fff;">{{ $pq->question }}@if($pq->answer_type)<span style="color:#f87171;margin-left:4px;">*</span>@endif</div>
                                    </div>
                                    <div style="padding:10px 12px;">
                                        @foreach($subCh as $sq)
                                            @if($sq)
                                            <div style="border:1px solid #e5e7eb;border-radius:7px;padding:8px 10px;margin-bottom:8px;background:#fafafa;">
                                                <div style="font-size:10px;color:#4a6cf7;font-weight:700;margin-bottom:3px;">&#8627; Sub Question</div>
                                                <div style="font-size:12px;font-weight:600;color:#111;margin-bottom:6px;">{{ $sq->question }}@if($sq->answer_type)<span style="color:#f87171;margin-left:3px;">*</span>@endif</div>
                                                @include('masters.activities.partials.preview_input', ['q' => $sq])
                                            </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                {{-- Standalone question --}}
                                <div style="background:#fff;border-radius:10px;padding:10px 12px;margin-bottom:8px;box-shadow:0 1px 4px rgba(0,0,0,.08);">
                                    <div style="font-size:12px;font-weight:600;color:#111;margin-bottom:6px;">{{ $pq->question }}@if($pq->answer_type)<span style="color:#f87171;margin-left:3px;">*</span>@endif</div>
                                    @include('masters.activities.partials.preview_input', ['q' => $pq])
                                </div>

                                @foreach($condCh as $cq)
                                {{-- Hidden by default; shown via JS when parent answer matches --}}
                                <div class="preview-cond-row"
                                     data-parent-qid="{{ $cq->parent_question_id }}"
                                     data-trigger="{{ $cq->parent_value }}"
                                     style="display:none;background:#f5f3ff;border-left:3px solid #7C3AED;border-radius:7px;padding:8px 10px;margin-bottom:8px;margin-left:12px;font-size:11px;">
                                    <div style="color:#7C3AED;font-size:9px;font-weight:700;margin-bottom:2px;">Shows when answer = "{{ $cq->parent_value }}"</div>
                                    <div style="font-weight:600;color:#111;margin-bottom:5px;font-size:12px;">{{ $cq->question }}@if($cq->answer_type)<span style="color:#f87171;">*</span>@endif</div>
                                    @include('masters.activities.partials.preview_input', ['q' => $cq])
                                </div>
                                @endforeach
                            @endif
                        @endforeach

                    </div>

                    {{-- Bottom bar --}}
                    <div style="background:#fff;border-top:1px solid #e5e7eb;padding:10px 16px;display:flex;justify-content:center;flex-shrink:0;">
                        <button type="button" data-bs-dismiss="modal"
                                style="background:#1a1a2e;color:#fff;border:none;border-radius:8px;padding:8px 32px;font-size:12px;font-weight:700;cursor:pointer;">
                            Close Preview
                        </button>
                    </div>
                </div>

                {{-- Home button --}}
                <div style="width:44px;height:5px;background:#333;border-radius:3px;margin:12px auto 0;"></div>
            </div>
        </div>
    </div>
</div>

@endsection
@section('scripts')
    <script>
        // Preview modal: show/hide conditional questions based on parent select value
        window.previewCondChange = function(sel) {
            var qid = sel.getAttribute('data-preview-qid');
            var val = sel.value;
            // Find all conditional rows that depend on this parent question
            document.querySelectorAll('.preview-cond-row[data-parent-qid="' + qid + '"]').forEach(function(row) {
                var trigger = row.getAttribute('data-trigger');
                var shouldShow = trigger === '__non_empty__' ? val.length > 0 : (val && trigger === val);
                row.style.display = shouldShow ? '' : 'none';
            });
        };

        // Global: used trigger values per parent question — populated in document.ready
        var _usedTriggerValues = {};

        // Server-side pagination: no client-side DataTable needed
        var question_table = { row: function() { return { remove: function() { return { draw: function(){} }; } }; } };

        // Debounced search — submits 400ms after user stops typing
        var _qSearchTimer = null;
        window.qDebouncedSearch = function() {
            clearTimeout(_qSearchTimer);
            _qSearchTimer = setTimeout(function() {
                document.getElementById('qFilterForm').submit();
            }, 400);
        };

        $(document).ready(function () {
            var question_table = { row: function() { return { remove: function() { return { draw: function(){} }; } }; } };

            @if(session()->has('message'))
            Swal.fire({
                position: "top-center",
                icon: "success",
                title: "{{ session('message') }}",
                showConfirmButton: false,
                timer: 1500
            });
            @endif

            let dropdownQuestions = @json($dropdown_questions);

            // NOTE: _usedTriggerValues is defined at script level (before document.ready)
            // so onDepParentChange() can access it. We just populate it here.
            @foreach($activity->questions->whereNotNull('parent_question_id')->whereNotNull('parent_value') as $cq)
            (function() {
                var pid = {{ (int)$cq->parent_question_id }};
                var qid = {{ (int)$cq->id }};
                var val = @json($cq->parent_value); // safe JSON encoding
                if (!_usedTriggerValues[pid]) _usedTriggerValues[pid] = [];
                _usedTriggerValues[pid].push({ qid: qid, val: val });
            })();
            @endforeach

            $('#parent_question').on('change', function () {
                let question_id = $(this).val();
                $.ajax({
                    url: "{{route('get_parent_question_dropdown')}}",
                    method: "POST",
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "id": question_id
                    },
                    success: function (response) {
                        console.log(response);
                        if (response.status) {
                            let data = response.question_option;
                            $('#parent_question_dropdown').html('');
                            data.forEach(function (q) {
                                $("#parent_question_dropdown").append(
                                    `<option value="${q.id ?? q.option}">${q.option}</option>`
                                );
                            });
                        }
                    }
                });
            });

            // triggerDelete called directly via onclick attribute (bypasses jQuery delegation for hidden rows)
            window.triggerDelete = function(el) { $(el).trigger('click'); };

            $("#question_table").on("click", ".delete", function (event) {
                const question_id = $(this).data('id');
                const tar_row = $(this).closest('tr');
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{route('activity.question.destroy')}}',
                            type: "POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "id": question_id
                            },
                            success: function (response) {
                                console.log(response)
                                if (response == "Success") {
                                    tar_row.remove();
                                    Swal.fire(
                                        'Deleted!',
                                        'Record has been deleted.',
                                        'success'
                                    )
                                }
                            }
                        })
                    }
                })
            });

            // ── Multi-parent trigger builder ──────────────────────────────────

            // (change handler is now inside the Select2 init above)

            // Add ALL selected parent questions as trigger rows
            $('#add_parent_trigger_btn').on('click', function () {
                var selectedVals = $('#new_parent_select').val() || [];
                var trigVal      = $('#new_trigger_select').val();
                var trigLabel    = $('#new_trigger_select option:selected').text().trim();
                if (!selectedVals.length) { alert('Please select at least one parent question.'); return; }

                selectedVals.forEach(function (parentId) {
                    var parentName = $('#new_parent_select option[value="' + parentId + '"]').text().trim();

                    // Skip duplicates
                    if ($('#parent_triggers_list').find('[data-parent-id="' + parentId + '"]').length) return;

                    var row = $('<div class="d-flex align-items-center gap-2 mb-1 border rounded p-2 bg-light trigger-row">')
                        .attr('data-parent-id', parentId)
                        .attr('data-trigger-value', trigVal);
                    row.append('<span class="flex-grow-1 small"><strong>' + $('<div>').text(parentName).html() + '</strong></span>');
                    row.append('<span class="badge bg-info text-dark small" style="min-width:80px;">' +
                        (trigVal ? 'When: ' + $('<div>').text(trigLabel).html() : 'Always') + '</span>');
                    row.append($('<button type="button" class="btn btn-sm btn-outline-danger remove-trigger-row" style="min-width:28px;">×</button>'));
                    $('#parent_triggers_list').append(row);
                });

                // Deselect all + reset trigger
                $('#new_parent_select').val([]);
                $('#new_trigger_select').html('<option value="">— trigger value —</option>').prop('disabled', true);
            });

            // Remove a trigger row
            $(document).on('click', '.remove-trigger-row', function () {
                $(this).closest('.trigger-row').remove();
            });

            // Load existing parent links when opening the edit modal
            // Init Select2 on the parent question multi-select (inside the modal)
            $('#questionEditModal').on('shown.bs.modal', function () {
                if (!$('#new_parent_select').hasClass('select2-hidden-accessible')) {
                    $('#new_parent_select').select2({
                        dropdownParent: $('#questionEditModal'),
                        placeholder: '— select parent question(s) —',
                        allowClear: true,
                        width: '100%'
                    }).on('change', function () {
                        var selected = $(this).find('option:selected');
                        var $tvSel   = $('#new_trigger_select');
                        if (!selected.length) {
                            $tvSel.html('<option value="">— always visible / no trigger —</option>').prop('disabled', true);
                            return;
                        }
                        var $first  = selected.first();
                        var qType   = $first.data('type');
                        var rawOpts = $first.attr('data-options') || '[]';
                        var options = JSON.parse(rawOpts);
                        $tvSel.html('<option value="">— always visible —</option>');
                        if (qType === 'Yes / No') {
                            $tvSel.append('<option value="Yes">Yes</option><option value="No">No</option>');
                        } else if (options.length) {
                            options.forEach(function (o) {
                                $tvSel.append('<option value="' + $('<div>').text(o).html() + '">' + $('<div>').text(o).html() + '</option>');
                            });
                        }
                        $tvSel.prop('disabled', false);
                    });
                }
            });

            // loadParentLinks is defined globally below (outside document.ready)
            // so openEditPanel() can also call it.

            // ── End multi-parent trigger builder ─────────────────────────────

            // Show/hide Allow Multiple Images checkbox based on question type
            function toggleMultipleImagesDiv() {
                const qType = $('#question_type').val();
                if (qType === 'Image') {
                    $('#allow_multiple_images_div').removeClass('d-none');
                } else {
                    $('#allow_multiple_images_div').addClass('d-none');
                    $('#allow_multiple_images').prop('checked', false);
                }
            }
            $('#question_type').on('change', toggleMultipleImagesDiv);

            // Edit button now opens the sliding panel instead of the old modal
            $("#question_table").on("click", ".edit", function (event) {
                openEditPanel(this);
                return; // stop — old modal code below is no longer used

                const q_id = $(this).data('id');
                const q = $(this).data('question');
                const q_type = $(this).data('q_type');
                const q_answer_type = $(this).data('answer_type');
                const q_sequence = $(this).data('sequence');
                const allow_multiple = $(this).data('allow_multiple_images');
                const tar_row = $(this).closest('tr');
                $("#question_id").val(q_id);
                $("#question").val(q);
                $("#question_sequence").val(q_sequence);
                $("#question_type").val(q_type);
                $("#answer_type").val(q_answer_type);

                // Set checkbox state and visibility
                if (q_type === 'Image') {
                    $('#allow_multiple_images_div').removeClass('d-none');
                    $('#allow_multiple_images').prop('checked', allow_multiple == 1);
                } else {
                    $('#allow_multiple_images_div').addClass('d-none');
                    $('#allow_multiple_images').prop('checked', false);
                }

                // Multi-trigger system now handles parent linking — load existing links
                $("#questionEditModal").modal('show');
                loadParentLinks(q_id);
            });

            // Attach the click event to the update button only once
            $("#update_question_btn").click(function () {
                const ques_value = $("#question").val();
                const ques_seq   = $("#question_sequence").val();
                const ques_id    = $("#question_id").val();
                const ques_type  = $("#question_type").val();
                const answer_type = $("#answer_type").val();
                const parent_question          = $('#parent_question').val();
                const parent_question_dropdown = $('#parent_question_dropdown').val();
                const parent_value             = $('#parent_question_dropdown option:selected').text();

                // Auto-add any currently selected Select2 parents that haven't been
                // explicitly added as trigger rows yet (user may select + change trigger
                // value without clicking "+ Add" — we capture them here automatically)
                var selectedParentIds = $('#new_parent_select').val() || [];
                var currentTriggerVal = $('#new_trigger_select').val() || null;
                selectedParentIds.forEach(function (pid) {
                    // Only add if not already a trigger row
                    if ($('#parent_triggers_list .trigger-row[data-parent-id="' + pid + '"]').length === 0) {
                        var row = $('<div class="trigger-row">')
                            .attr('data-parent-id', pid)
                            .attr('data-trigger-value', currentTriggerVal || '');
                        $('#parent_triggers_list').append(row);
                    }
                });

                // Collect all trigger rows (explicit + auto-added above)
                const parent_triggers = [];
                $('#parent_triggers_list .trigger-row').each(function () {
                    var pid = $(this).data('parent-id');
                    var tv  = $(this).data('trigger-value');
                    if (pid) {
                        parent_triggers.push({
                            parent_id:     pid,
                            trigger_value: (tv !== undefined && tv !== '') ? tv : null
                        });
                    }
                });

                if (ques_value == "") {
                    $("#error_message").html("Question Value Can not be blank")
                    $("#errorMessage_modal").modal('show')
                } else {
                    $.ajax({
                        url: "{{ route('question.update') }}",
                        type: "PUT",
                        data: {
                            "_token": "{{ csrf_token() }}",
                            "form_data": {
                                "question":             ques_value,
                                "question_sequence":    ques_seq,
                                "id":                   ques_id,
                                "question_type":        ques_type,
                                "answer_type":          answer_type,
                                "parent_question_id":   parent_question,
                                "parent_dropdown_id":   parent_question_dropdown,
                                "parent_value":         parent_value,
                                "allow_multiple_images": (ques_type === 'Image' && $("#allow_multiple_images").is(":checked")) ? 1 : 0,
                                "parent_triggers":      parent_triggers
                            }
                        },
                        success: function (response) {
                            if (response.message == "error") {
                                Swal.fire({
                                    icon: "error",
                                    title: "Cannot Map Parent Question",
                                    text: response.error,
                                    confirmButtonColor: "#d33"
                                });
                                return;
                            }
                            if (response.message == "success") {
                                Swal.fire({
                                    position: "top-center",
                                    icon: "success",
                                    title: "Question Updated Successfully",
                                    showConfirmButton: false,
                                    timer: 1500
                                });
                                const q_info = response.question_info;
                                const tar_row = $("#question_table").find(`[data-id="${ques_id}"]`).closest('tr');
                                tar_row.find('td:nth-child(2)').text(q_info['question']);
                                tar_row.find('td:nth-child(3)').text(q_info['question_type']);
                                tar_row.find('td:nth-child(4)').text(q_info['question_sequence']);
                                let ans_type = "Optional";
                                if (q_info['answer_type'] == 1) {
                                    ans_type = "Required";
                                }
                                tar_row.find('td:nth-child(5)').text(ans_type);
                                tar_row.find('td:nth-child(6) li.edit').data('question', q_info['question']);
                                tar_row.find('td:nth-child(6) li.edit').data('q_type', q_info['question_type']);
                                tar_row.find('td:nth-child(6) li.edit').data('sequence', q_info['question_sequence']);
                                tar_row.find('td:nth-child(6) li.edit').data('answer_type', q_info['answer_type']);
                                location.reload();
                            }
                        }
                    });
                }
            });
        });
    </script>

    <script>
    // ── Add Question Panel ──────────────────────────────────────────────────
    var _qTypeSelected   = '';
    var _reqOn           = true;
    var _depOn           = false;
    var _allowMultOn     = false;
    var _allowMultImgOn  = false;
    var _options         = [];

    // ── Global: parent-links API base URL and loadParentLinks function ──────
    // Defined here (outside document.ready) so openEditPanel() can call it.
    var _parentLinksBase = '{{ url("questions/question") }}/';

    function loadParentLinks(questionId) {
        $('#parent_triggers_list').empty();
        if ($('#new_parent_select').hasClass('select2-hidden-accessible')) {
            $('#new_parent_select').val(null).trigger('change');
        }
        $('#new_trigger_select').html('<option value="">— always visible / no trigger —</option>').prop('disabled', true);

        $.get(_parentLinksBase + questionId + '/parent-links', function (res) {
            $('#parent_triggers_list').empty();
            if (res.links && res.links.length) {
                res.links.forEach(function (link) {
                    var row = $('<div class="d-flex align-items-center gap-2 mb-1 border rounded p-2 bg-light trigger-row">')
                        .attr('data-parent-id', link.parent_id)
                        .attr('data-trigger-value', link.trigger_value || '');
                    row.append('<span class="flex-grow-1 small"><strong>' + $('<div>').text(link.parent_name).html() + '</strong></span>');
                    row.append('<span class="badge bg-info text-dark small" style="min-width:90px;">' +
                        (link.trigger_value ? 'When: ' + $('<div>').text(link.trigger_value).html() : 'Always') + '</span>');
                    row.append($('<button type="button" class="btn btn-sm btn-outline-danger remove-trigger-row" style="min-width:30px;">×</button>'));
                    $('#parent_triggers_list').append(row);

                    if ($('#new_parent_select option[value="' + link.parent_id + '"]').length === 0) {
                        var opts = JSON.stringify(link.options || []);
                        $('#new_parent_select').append($('<option>', {
                            value: link.parent_id,
                            text:  link.parent_name + ' (' + (link.parent_type || '') + ')',
                            'data-type': link.parent_type || '',
                            'data-options': opts
                        }));
                        if ($('#new_parent_select').hasClass('select2-hidden-accessible')) {
                            $('#new_parent_select').trigger('change.select2');
                        }
                    }
                });
            }
        });
    }
    // ── End global loadParentLinks ───────────────────────────────────────────

    // ── Inline Sub-Question Management ──────────────────────────────────────
    var _sqTypeColors = {
        'Yes / No':    { bg:'#F0FDF4', color:'#15803D' },
        'Free Text':   { bg:'#EFF6FF', color:'#1D4ED8' },
        'Dropdown':    { bg:'#F5F3FF', color:'#7C3AED' },
        'Multi select':{ bg:'#ECFEFF', color:'#0E7490' },
        'Image':       { bg:'#FFFBEB', color:'#B45309' },
        'Video':       { bg:'#FAF5FF', color:'#9333EA' },
        'Date':        { bg:'#F0FDFA', color:'#0F766E' },
        'Date & Time': { bg:'#F0FDFA', color:'#0F766E' },
        'File Upload': { bg:'#EFF6FF', color:'#1D4ED8' },
        'Location':    { bg:'#FEF2F2', color:'#DC2626' },
        'Audio':       { bg:'#F5F3FF', color:'#6D28D9' },
        'Subjective':  { bg:'#F9FAFB', color:'#6B7280' },
    };
    var _sqTypes = ['Yes / No','Free Text','Dropdown','Multi select','Image','Video','Date','Date & Time','File Upload','Location','Audio','Subjective'];

    window._subqItems     = [];
    window._subqParentId  = null;
    window._subqExpanded  = null; // index of expanded row

    function renderSubQList() {
        var html = '';
        (_subqItems || []).forEach(function(sq, idx) {
            var tc  = _sqTypeColors[sq.type] || { bg:'#F3F4F6', color:'#6B7280' };
            var isReq   = sq.answer_type == 1 || sq.required;
            var isNew   = !sq.id; // no existing question_id
            var isExp   = (window._subqExpanded === idx);
            var borderC = isExp ? '#C7D2FE' : '#E8E8F0';

            html += '<div style="border:1px solid ' + borderC + ';border-radius:7px;margin-bottom:6px;overflow:hidden;" id="sqrow_' + idx + '">';
            // Header row
            html += '<div style="display:flex;align-items:center;gap:7px;padding:8px 10px;background:#F8F8FC;">';
            html += '<span style="padding:2px 7px;border-radius:3px;font-size:10px;font-weight:600;background:' + tc.bg + ';color:' + tc.color + ';flex:none;">' + escHtml(sq.type || 'Free Text') + '</span>';
            html += '<span style="flex:1;font-size:13px;color:#374151;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escHtml(sq.question || sq.text || ('Sub-question ' + (idx+1))) + '</span>';
            if (isReq) html += '<span style="font-size:10px;color:#B91C1C;font-weight:600;flex:none;">Req</span>';
            html += '<button type="button" onclick="toggleSqExpand(' + idx + ')" style="font-size:11px;color:#4338CA;background:none;border:1px solid #C7D2FE;border-radius:4px;cursor:pointer;padding:3px 8px;flex:none;white-space:nowrap;">' + (isExp ? '▲ Collapse' : '▶ Configure') + '</button>';
            html += '<button type="button" onclick="removeSqRow(' + idx + ')" style="font-size:18px;color:#DC2626;background:none;border:none;cursor:pointer;padding:1px 4px;flex:none;line-height:1;">×</button>';
            html += '</div>';

            // Expanded config section
            if (isExp) {
                html += '<div style="padding:12px 11px;border-top:1px solid #E8E8F0;">';
                // Type picker
                html += '<div style="margin-bottom:10px;">';
                html += '<label style="display:block;font-size:10.5px;font-weight:600;color:#9CA3AF;margin-bottom:5px;text-transform:uppercase;letter-spacing:.3px;">Type</label>';
                html += '<div style="display:flex;flex-wrap:wrap;gap:3px;">';
                _sqTypes.forEach(function(t) {
                    var sel = (sq.type || 'Free Text') === t;
                    var tc2 = _sqTypeColors[t] || {bg:'#F3F4F6',color:'#6B7280'};
                    html += '<button type="button" onclick="setSqType(' + idx + ',\'' + t + '\')" style="padding:4px 8px;border-radius:3px;font-size:10.5px;font-weight:' + (sel?'700':'500') + ';border:1.5px solid ' + (sel?tc2.color:'#E5E7EB') + ';background:' + (sel?tc2.bg:'#F9FAFB') + ';color:' + (sel?tc2.color:'#374151') + ';cursor:pointer;white-space:nowrap;">' + t + '</button>';
                });
                html += '</div></div>';
                // Text + Required
                html += '<div style="display:flex;gap:8px;margin-bottom:9px;align-items:flex-end;">';
                html += '<div style="flex:1;"><label style="display:block;font-size:11px;color:#6B7280;margin-bottom:3px;">Sub-question Text</label>';
                html += '<input type="text" id="sqtext_' + idx + '" value="' + escAttr(sq.question || sq.text || '') + '" oninput="setSqText(' + idx + ',this.value)" placeholder="Enter sub-question..." style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:7px 9px;font-size:13px;outline:none;box-sizing:border-box;"></div>';
                html += '<div style="padding-bottom:2px;"><label style="display:flex;align-items:center;gap:5px;cursor:pointer;white-space:nowrap;">';
                html += '<input type="checkbox" ' + (isReq?'checked':'') + ' onchange="setSqRequired(' + idx + ',this.checked)" style="cursor:pointer;">';
                html += '<span style="font-size:12px;color:#374151;">Required</span></label></div>';
                html += '</div>';
                // Type-specific configuration fields
                var sqType = sq.type || 'Free Text';
                var isTextType  = ['Free Text','Subjective','Location'].indexOf(sqType) >= 0;
                var isFileType  = ['Image','File Upload','Video','Audio'].indexOf(sqType) >= 0;
                var isDateType  = ['Date','Date & Time'].indexOf(sqType) >= 0;
                var divider = '<div style="height:1px;background:#F3F4F6;margin:8px 0;"></div>';

                // ── Text validation ──────────────────────────────
                if (isTextType) {
                    var sqVRule  = sq.validation_rule || 'none';
                    var sqVMin   = sq.validation_min  || '';
                    var sqVMax   = sq.validation_max  || '';
                    var sqVRegex = sq.validation_regex || '';
                    var needsRange = ['numericrange','digitlength','textlength'].indexOf(sqVRule) >= 0;
                    var needsRegex = sqVRule === 'regex';
                    html += divider;
                    html += '<div style="margin-bottom:9px;">';
                    html += '<label style="display:block;font-size:11px;color:#6B7280;margin-bottom:3px;">Validation Rule</label>';
                    html += '<select onchange="setSqValidation(' + idx + ',\'rule\',this.value)" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:6px 9px;font-size:12px;outline:none;box-sizing:border-box;">';
                    ['none','numericrange','digitlength','textlength','email','phone','regex'].forEach(function(r) {
                        html += '<option value="' + r + '"' + (sqVRule===r?' selected':'') + '>' + r + '</option>';
                    });
                    html += '</select></div>';
                    if (needsRange) {
                        html += '<div style="display:flex;gap:8px;margin-bottom:9px;">';
                        html += '<div style="flex:1;"><label style="display:block;font-size:11px;color:#6B7280;margin-bottom:3px;">Min</label><input type="number" value="' + sqVMin + '" onchange="setSqValidation(' + idx + ',\'min\',this.value)" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:6px 9px;font-size:12px;outline:none;box-sizing:border-box;"></div>';
                        html += '<div style="flex:1;"><label style="display:block;font-size:11px;color:#6B7280;margin-bottom:3px;">Max</label><input type="number" value="' + sqVMax + '" onchange="setSqValidation(' + idx + ',\'max\',this.value)" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:6px 9px;font-size:12px;outline:none;box-sizing:border-box;"></div>';
                        html += '</div>';
                    }
                    if (needsRegex) {
                        html += '<div style="margin-bottom:9px;"><label style="display:block;font-size:11px;color:#6B7280;margin-bottom:3px;">Regex Pattern</label><input type="text" value="' + escAttr(sqVRegex) + '" onchange="setSqValidation(' + idx + ',\'regex\',this.value)" placeholder="e.g. ^[0-9]{10}$" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:6px 9px;font-size:12px;font-family:monospace;outline:none;box-sizing:border-box;"></div>';
                    }
                }

                // ── File / Image constraints ──────────────────────
                if (isFileType) {
                    var sqFTypes   = sq.file_types     || '';
                    var sqMaxSize  = sq.max_file_size  || '';
                    var sqMultiImg = sq.allow_multiple_images == 1;
                    html += divider;
                    html += '<div style="display:flex;gap:8px;margin-bottom:9px;">';
                    html += '<div style="flex:1;"><label style="display:block;font-size:11px;color:#6B7280;margin-bottom:3px;">Accepted Types</label><input type="text" value="' + escAttr(sqFTypes) + '" onchange="setSqValidation(' + idx + ',\'file_types\',this.value)" placeholder=".jpg,.png,.pdf" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:6px 9px;font-size:12px;outline:none;box-sizing:border-box;"></div>';
                    html += '<div style="width:110px;"><label style="display:block;font-size:11px;color:#6B7280;margin-bottom:3px;">Max Size (MB)</label><input type="number" value="' + sqMaxSize + '" onchange="setSqValidation(' + idx + ',\'max_file_size\',this.value)" placeholder="10" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:6px 9px;font-size:12px;outline:none;box-sizing:border-box;"></div>';
                    html += '</div>';
                    if (sqType === 'Image') {
                        html += '<label style="display:flex;align-items:center;gap:6px;cursor:pointer;margin-bottom:9px;font-size:12px;">';
                        html += '<input type="checkbox" ' + (sqMultiImg?'checked':'') + ' onchange="setSqValidation(' + idx + ',\'allow_multiple_images\',this.checked?1:0)" style="cursor:pointer;">';
                        html += '<span style="color:#374151;">Allow Multiple Images</span></label>';
                    }
                }

                // ── Date range ────────────────────────────────────
                if (isDateType) {
                    var sqDMin = sq.date_min || '';
                    var sqDMax = sq.date_max || '';
                    var dtType = sqType === 'Date & Time' ? 'datetime-local' : 'date';
                    html += divider;
                    html += '<div style="display:flex;gap:8px;margin-bottom:9px;">';
                    html += '<div style="flex:1;"><label style="display:block;font-size:11px;color:#6B7280;margin-bottom:3px;">Min Date</label><input type="' + dtType + '" value="' + escAttr(sqDMin) + '" onchange="setSqValidation(' + idx + ',\'date_min\',this.value)" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:6px 9px;font-size:12px;outline:none;box-sizing:border-box;"></div>';
                    html += '<div style="flex:1;"><label style="display:block;font-size:11px;color:#6B7280;margin-bottom:3px;">Max Date</label><input type="' + dtType + '" value="' + escAttr(sqDMax) + '" onchange="setSqValidation(' + idx + ',\'date_max\',this.value)" style="width:100%;border:1px solid #E5E7EB;border-radius:5px;padding:6px 9px;font-size:12px;outline:none;box-sizing:border-box;"></div>';
                    html += '</div>';
                }
                html += '<button type="button" onclick="toggleSqExpand(' + idx + ')" style="font-size:11.5px;color:#9CA3AF;background:none;border:none;cursor:pointer;padding:0;">▲ Collapse</button>';
                html += '</div>';
            }
            html += '</div>';
        });

        if (!html) html = '<div style="font-size:13px;color:#9CA3AF;padding:8px 0;text-align:center;border:1px dashed #E5E7EB;border-radius:6px;">Add sub-questions using the button below.</div>';
        $('#subq_list').html(html);
        $('#subq_count').text((_subqItems||[]).length + ' sub-question(s)');
        updateExistingSelectOptions();
    }

    function toggleSqExpand(idx) {
        window._subqExpanded = (window._subqExpanded === idx) ? null : idx;
        renderSubQList();
    }

    function setSqType(idx, type) {
        window._subqItems[idx].type = type;
        renderSubQList();
    }

    function setSqText(idx, val) {
        window._subqItems[idx].question = val;
        window._subqItems[idx].text     = val;
    }

    function setSqRequired(idx, checked) {
        window._subqItems[idx].required     = checked;
        window._subqItems[idx].answer_type  = checked ? 1 : 0;
    }

    function setSqValidation(idx, field, value) {
        var sq = window._subqItems[idx];
        if (field === 'rule') {
            sq.validation_rule  = value;
            sq.validation_min   = '';
            sq.validation_max   = '';
            sq.validation_regex = '';
            renderSubQList(); // re-render to show correct sub-fields
        } else if (field === 'min')                 { sq.validation_min   = value; }
        else if (field === 'max')                   { sq.validation_max   = value; }
        else if (field === 'regex')                 { sq.validation_regex = value; }
        else if (field === 'file_types')            { sq.file_types       = value; }
        else if (field === 'max_file_size')         { sq.max_file_size    = value; }
        else if (field === 'allow_multiple_images') { sq.allow_multiple_images = value; }
        else if (field === 'date_min')              { sq.date_min         = value; }
        else if (field === 'date_max')              { sq.date_max         = value; }
    }

    function addInlineSubQuestion() {
        window._subqItems = window._subqItems || [];
        var newIdx = window._subqItems.length;
        window._subqItems.push({ id: null, question: '', text: '', type: 'Free Text', required: false, answer_type: 0, isNew: true });
        window._subqExpanded = newIdx;
        renderSubQList();
        // Focus the text input for the new row
        setTimeout(function() { var el = document.getElementById('sqtext_' + newIdx); if (el) el.focus(); }, 100);
    }

    function removeSqRow(idx) {
        var sq = window._subqItems[idx];
        if (!sq.isNew && sq.id) {
            // Unlink existing sub-question via AJAX
            $.post('{{ route("activity.question.sub_question.destroy") }}', {
                _token: '{{ csrf_token() }}',
                id: sq.link_id || sq.id
            }, function() {
                window._subqItems.splice(idx, 1);
                if (window._subqExpanded === idx) window._subqExpanded = null;
                else if (window._subqExpanded > idx) window._subqExpanded--;
                renderSubQList();
            }).fail(function() {
                Swal.fire('Error', 'Could not remove sub-question.', 'error');
            });
        } else {
            // Just remove from local list (not yet saved)
            window._subqItems.splice(idx, 1);
            if (window._subqExpanded === idx) window._subqExpanded = null;
            else if (window._subqExpanded > idx) window._subqExpanded--;
            renderSubQList();
        }
    }

    // ── Link Existing: Select2 on body (no overflow changes to panel) ────────
    window.toggleLinkExisting = function() {
        var $sec = $('#sec_link_existing');
        $sec.toggle();
        if ($sec.is(':visible')) {
            var $sel = $('#existing_subq_select');
            if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
            // Update disabled state before reinit so Select2 picks it up correctly
            updateExistingSelectOptions();

            $sel.select2({
                dropdownParent: $('body'),
                placeholder: '— search and select a question —',
                allowClear: true,
                width: '100%',
                // Hide disabled options (already-linked questions) from the dropdown
                templateResult: function(option) {
                    if (option.disabled) return null;
                    return option.text;
                }
            });
            $sel.val('').trigger('change');
            setTimeout(function() { $sel.select2('open'); }, 50);
        }
    };

    window.hideExistingSubQPanel = function() {
        $('#sec_link_existing').hide();
        var $sel = $('#existing_subq_select');
        if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('close');
    };

    function linkExistingSubQ() {
        var childId = $('#existing_subq_select').val();
        if (!childId) { alert('Please select a question.'); return; }
        var parentId = window._subqParentId;

        // ADD mode: parent doesn't exist in DB yet — add as pending sub-question
        if (!parentId && _panelMode === 'add') {
            var $opt = $('#existing_subq_select option[value="' + childId + '"]');
            var qText = $opt.data('text') || $opt.text().trim();
            var qType = $opt.data('type') || 'Free Text';
            window._subqItems = window._subqItems || [];
            window._subqItems.push({ id: childId, question: qText, type: qType, answer_type: 0, required: false, sequence: (window._subqItems.length + 1), isNew: false, isLinked: true });
            renderSubQList();
            updateExistingSelectOptions();
            $('#sec_link_existing').hide();
            $('#existing_subq_select').val('').trigger('change');
            return;
        }

        if (!parentId) { alert('No parent question set.'); return; }

        $.post('{{ route("activity.question.sub_question.store") }}', {
            _token: '{{ csrf_token() }}',
            parent_question_id: parentId,
            child_question_id:  childId,
            sequence:           (window._subqItems || []).length + 1
        }, function(res) {
            $('#sec_link_existing').hide();
            $('#existing_subq_select').val('').trigger('change');
            // Reload sub-questions list
            var subqJsonUrl = '{{ url("questions/question") }}/' + parentId + '/sub-questions-json';
            $.getJSON(subqJsonUrl, function(data) {
                window._subqItems = data.sub_questions || [];
                renderSubQList();
                // Hide already-linked options from the existing select
                updateExistingSelectOptions();
            });
        }).fail(function(xhr) {
            var msg = 'Failed to link.';
            try { msg = JSON.parse(xhr.responseText).message || msg; } catch(e) {}
            alert(msg);
        });
    }

    function updateExistingSelectOptions() {
        var linked = (window._subqItems || []).map(function(sq) { return String(sq.id); });
        // Update disabled state on raw DOM options.
        // Select2 is destroyed+reinited on each open, so no Select2 refresh needed here.
        $('#existing_subq_select option').each(function() {
            if (!$(this).val()) return;
            $(this).prop('disabled', linked.includes($(this).val()));
        });
    }

    function saveNewSubQuestions(parentQId, callback) {
        // Save new (isNew) sub-questions and link pending (isLinked) sub-questions
        var pendingItems = (window._subqItems || []).filter(function(sq) { return sq.isNew || sq.isLinked; });
        if (!pendingItems.length) { if (callback) callback(); return; }
        var newItems = pendingItems; // treat all pending the same

        var done = 0;
        newItems.forEach(function(sq) {
            // isLinked: link existing question via sub_question.store
            if (sq.isLinked) {
                $.post('{{ route("activity.question.sub_question.store") }}', {
                    _token: '{{ csrf_token() }}',
                    parent_question_id: parentQId,
                    child_question_id:  sq.id,
                    sequence: sq.sequence || (done + 1)
                }, function() { done++; if (done === newItems.length && callback) callback(); })
                .fail(function() { done++; if (done === newItems.length && callback) callback(); });
                return;
            }

            var text = (sq.question || sq.text || '').trim();
            if (!text) { done++; if (done === newItems.length && callback) callback(); return; }

            var data = {
                activity_id:      {{ $activity->id }},
                question:         text,
                question_type:    sq.type || 'Free Text',
                answer_type:      sq.required ? '1' : '0',
                parent_group_id:  parentQId,
                validation_rule:       sq.validation_rule       || 'none',
                validation_min:        sq.validation_min        || null,
                validation_max:        sq.validation_max        || null,
                validation_regex:      sq.validation_regex      || null,
                file_types:            sq.file_types            || null,
                max_file_size_mb:      sq.max_file_size         || null,
                allow_multiple_images: sq.allow_multiple_images || 0,
                date_min:              sq.date_min              || null,
                date_max:              sq.date_max              || null,
            };
            $.post('{{ route("question.store") }}', { _token: '{{ csrf_token() }}', form_data: data }, function(res) {
                done++;
                if (done === newItems.length && callback) callback();
            }).fail(function() {
                done++;
                if (done === newItems.length && callback) callback();
            });
        });
    }

    function escAttr(s) {
        return String(s).replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    // ── End Inline Sub-Question Management ──────────────────────────────────

    var _panelMode  = 'add'; // 'add' or 'edit'

    function openAddPanel() {
        _panelMode = 'add';
        resetPanel();
        document.getElementById('panelTitle').textContent   = 'Add Question';
        document.getElementById('aq_save_btn').textContent  = 'Save Question';
        document.getElementById('aq_editing_id').value      = '';
        $('#aq_parent_group option').prop('disabled', false).show(); // reset self-exclusion
        document.getElementById('addQOverlay').style.display = 'block';
        document.getElementById('addQPanel').style.display   = 'block';
        document.getElementById('aq_text').focus();
    }

    function openEditPanel(btn) {
        _panelMode = 'edit';
        resetPanel(); // clear everything first

        var $btn = $(btn);
        var qId      = $btn.data('id');
        var qText    = $btn.data('question');
        var qType    = $btn.data('q_type');
        var ansType  = $btn.data('answer_type');
        var helpText = $btn.data('help_text') || '';
        var valRule  = $btn.data('validation_rule') || 'none';
        var valMin   = $btn.data('validation_min') || '';
        var valMax   = $btn.data('validation_max') || '';
        var valRegex = $btn.data('validation_regex') || '';
        var fileTypes   = $btn.data('file_types') || '';
        var maxSize     = $btn.data('max_file_size') || '';
        var dateMin     = $btn.data('date_min') || '';
        var dateMax     = $btn.data('date_max') || '';
        var allowMulti  = $btn.data('allow_multiple_images') || 0;
        var existingOpts = $btn.data('options') || [];

        // Set panel mode
        document.getElementById('panelTitle').textContent   = 'Edit Question';
        document.getElementById('aq_save_btn').textContent  = 'Update Question';
        document.getElementById('aq_editing_id').value      = qId;

        // Populate basic fields
        document.getElementById('aq_text').value  = qText;
        document.getElementById('aq_help').value  = helpText;
        document.getElementById('aq_required').value = ansType ? '1' : '0';
        _reqOn = !!parseInt(ansType);
        setToggle('reqToggle','reqKnob', _reqOn);

        // Select question type
        selectQType(qType);

        // Validation
        if (valRule && valRule !== 'none') {
            document.getElementById('aq_val_rule').value = valRule;
            onValRuleChange();
        }
        document.getElementById('aq_val_min').value   = valMin;
        document.getElementById('aq_val_max').value   = valMax;
        document.getElementById('aq_val_regex').value = valRegex;

        // File / Image
        document.getElementById('aq_file_types').value = fileTypes;
        document.getElementById('aq_max_size').value   = maxSize;

        // Date
        document.getElementById('aq_date_min').value = dateMin;
        document.getElementById('aq_date_max').value = dateMax;

        // Allow Multiple Images
        _allowMultImgOn = !!parseInt(allowMulti);
        document.getElementById('aq_allow_multi_img').value = _allowMultImgOn ? '1' : '0';
        setToggle('miToggle','miKnob', _allowMultImgOn);

        // Pre-fill options for Dropdown / Multi select
        if (existingOpts && existingOpts.length) {
            _options = existingOpts;
            renderOptions();
        }

        // In edit mode: hide Conditional Logic section unless question actually has it
        var existingParentIdCheck = $btn.data('parent_question_id') + '';
        var hasConditional = existingParentIdCheck && existingParentIdCheck !== 'undefined' && existingParentIdCheck !== '' && existingParentIdCheck !== 'null';
        if (!hasConditional) {
            document.getElementById('sec_conditional_wrapper').style.display = 'none';
        }

        // For Multi Response: fetch and display linked sub-questions as interactive rows
        if (qType === 'Multi Response') {
            window._subqParentId = qId; // set before fetch so linkExistingSubQ works immediately
            var subqJsonUrl = '{{ url("questions/question") }}/' + qId + '/sub-questions-json';
            $('#sec_subquestions_view').show();
            $('#sec_link_existing').hide();
            $('#subq_list').html('<small class="text-muted">Loading…</small>');

            $.getJSON(subqJsonUrl, function(res) {
                window._subqParentId   = qId;
                window._subqParentRawId = null; // set after first render for sub-question store
                window._subqItems      = res.sub_questions || [];
                renderSubQList();
            }).fail(function() {
                $('#subq_list').html('<small class="text-muted">Could not load sub-questions.</small>');
            });
        } else {
            $('#sec_subquestions_view').hide();
            $('#subq_list').empty();
            window._subqItems = [];
        }

        // Load parent trigger links (new-style multi-parent system)
        loadParentLinks(qId);

        // Pre-populate conditional logic if this question has a parent (old-style parent_question_id)
        var existingParentId    = $btn.data('parent_question_id') + '';
        var existingParentValue = $btn.data('parent_value') + '';
        if (existingParentId && existingParentId !== 'undefined' && existingParentId !== '') {
            // Expand the conditional toggle
            _depOn = true;
            setToggle('depToggle','depKnob', true);
            document.getElementById('sec_dep').style.display = 'block';
            updateDepParentOptions(); // hide fully-taken parents (but keep current parent visible)

            // Select the triggering parent question then populate trigger values
            $('#aq_dep_parent').val(existingParentId);
            onDepParentChange(); // fills the trigger-value dropdown

            // Set the trigger value after the dropdown is populated
            setTimeout(function() {
                var $tv = $('#aq_dep_value');
                $tv.val(existingParentValue);
                // Fallback: match by text if val() didn't select
                if (!$tv.val() && existingParentValue) {
                    $tv.find('option').each(function() {
                        if ($(this).text().trim() === existingParentValue) {
                            $(this).prop('selected', true);
                        }
                    });
                }
                document.getElementById('sec_dep_val').style.display = 'block';
            }, 150);
        }

        document.getElementById('addQOverlay').style.display = 'block';
        document.getElementById('addQPanel').style.display   = 'block';
    }

    function closeAddPanel() {
        document.getElementById('addQOverlay').style.display = 'none';
        document.getElementById('addQPanel').style.display   = 'none';
    }

    function resetPanel() {
        _qTypeSelected = ''; _reqOn = true; _depOn = false;
        _allowMultOn = false; _allowMultImgOn = false; _options = [];
        document.getElementById('aq_text').value    = '';
        document.getElementById('aq_type').value    = '';
        document.getElementById('aq_help').value    = '';
        document.getElementById('aq_required').value = '1';
        document.getElementById('aq_file_types').value = '';
        document.getElementById('aq_max_size').value   = '';
        document.getElementById('aq_date_min').value   = '';
        document.getElementById('aq_date_max').value   = '';
        document.getElementById('aq_val_rule').value   = 'none';
        document.getElementById('aq_val_min').value    = '';
        document.getElementById('aq_val_max').value    = '';
        document.getElementById('aq_val_regex').value  = '';
        document.getElementById('aq_dep_parent').value = '';
        document.getElementById('aq_dep_value').value  = '';
        document.getElementById('aq_parent_group').value = '';
        document.getElementById('newOptInput').value   = '';
        document.getElementById('optList').innerHTML   = '';
        updateOptCount();
        setToggle('reqToggle','reqKnob',true);
        setToggle('depToggle','depKnob',false);
        setToggle('amToggle','amKnob',false);
        setToggle('miToggle','miKnob',false);
        hideErrors();
        // Deselect all type buttons
        document.querySelectorAll('#aq_type_btns button').forEach(b=>{
            b.style.background='#F9FAFB'; b.style.color='#374151'; b.style.borderColor='#E5E7EB';
        });
        showSections('');
    }

    function setToggle(trackId, knobId, on) {
        document.getElementById(trackId).style.background = on ? '#111827' : '#E5E7EB';
        document.getElementById(knobId).style.left = on ? '18px' : '2px';
    }

    function selectQType(type) {
        _qTypeSelected = type;
        document.getElementById('aq_type').value = type;
        document.querySelectorAll('#aq_type_btns button').forEach(b => {
            const isSelected = b.dataset.type === type;
            b.style.background   = isSelected ? b.dataset.bg    : '#F9FAFB';
            b.style.color        = isSelected ? b.dataset.color  : '#374151';
            b.style.borderColor  = isSelected ? b.dataset.color  : '#E5E7EB';
            b.style.fontWeight   = isSelected ? '700' : '600';
        });
        showSections(type);
    }

    function showSections(type) {
        const isOpts        = ['Dropdown','Multi select'].includes(type);
        const isFile        = ['Image','File Upload'].includes(type);
        const isDate        = ['Date','Date & Time'].includes(type);
        const isValid       = ['Free Text','Yes / No','Dropdown','Multi select','Subjective'].includes(type);
        // Conditional logic: visible for any type that can be a conditional child
        // (everything except Multi Response which is a section header, not a question)
        const isConditional = type !== '' && type !== 'Multi Response';
        const isMultiResp   = type === 'Multi Response';

        document.getElementById('sec_options').style.display            = isOpts        ? 'block' : 'none';
        document.getElementById('ms_extras').style.display              = type === 'Multi select' ? 'block' : 'none';
        document.getElementById('sec_file').style.display               = isFile        ? 'block' : 'none';
        document.getElementById('sec_multi_img').style.display          = type === 'Image' ? 'block' : 'none';
        document.getElementById('sec_date').style.display               = isDate        ? 'block' : 'none';
        document.getElementById('sec_validation').style.display         = isValid       ? 'block' : 'none';
        document.getElementById('sec_conditional_wrapper').style.display = isConditional ? 'block' : 'none';
        document.getElementById('sec_group').style.display              = isMultiResp   ? 'block' : 'none';
        // Show sub-questions section for Multi Response in BOTH add and edit mode
        if (document.getElementById('sec_subquestions_view')) {
            document.getElementById('sec_subquestions_view').style.display = isMultiResp ? 'block' : 'none';
        }
        // In ADD mode: initialize empty sub-questions list so Create New / Link Existing work
        if (isMultiResp && _panelMode === 'add') {
            window._subqParentId = null; // no DB parent yet — saveQuestion() creates it first
            window._subqItems    = [];
            renderSubQList();
            updateExistingSelectOptions();
            $('#sec_link_existing').hide();
            $('#subq_count').text('0 sub-question(s)');
        }

        // Collapse conditional logic panel if switching to Multi Response or no type
        if (!isConditional && _depOn) {
            _depOn = false;
            setToggle('depToggle','depKnob',false);
            document.getElementById('sec_dep').style.display = 'none';
        }
        if (!isMultiResp) {
            document.getElementById('aq_parent_group').value = '';
        }

        onValRuleChange();
    }

    // Required toggle
    function toggleRequired() {
        _reqOn = !_reqOn;
        document.getElementById('aq_required').value = _reqOn ? '1' : '0';
        setToggle('reqToggle','reqKnob',_reqOn);
    }

    // Allow multiple (Multi select)
    function toggleAllowMultiple() {
        _allowMultOn = !_allowMultOn;
        document.getElementById('aq_allow_multiple').value = _allowMultOn ? '1' : '0';
        setToggle('amToggle','amKnob',_allowMultOn);
    }

    // Allow multiple images (Image)
    function toggleMultipleImages() {
        _allowMultImgOn = !_allowMultImgOn;
        document.getElementById('aq_allow_multi_img').value = _allowMultImgOn ? '1' : '0';
        setToggle('miToggle','miKnob',_allowMultImgOn);
    }

    // Conditional logic toggle
    function toggleDep() {
        _depOn = !_depOn;
        setToggle('depToggle','depKnob',_depOn);
        document.getElementById('sec_dep').style.display = _depOn ? 'block' : 'none';
        if (_depOn) updateDepParentOptions(); // refresh available parents when section opens
    }

    // Refresh "Depends on question" dropdown — hide parents with all values taken
    function updateDepParentOptions() {
        var editingId = (document.getElementById('aq_editing_id') || {}).value || '';
        $('#aq_dep_parent option').each(function() {
            var pid = $(this).val();
            if (!pid) return; // skip placeholder
            var qType   = $(this).data('type');
            var opts    = $(this).data('options') || [];
            if (typeof opts === 'string') { try { opts = JSON.parse(opts); } catch(e) { opts = []; } }

            // All possible trigger values for this parent
            var allValues = qType === 'Yes / No' ? ['Yes', 'No'] : opts;
            if (!allValues.length) return;

            // Taken values by OTHER questions (not the one being edited)
            var editingIdNow = (document.getElementById('aq_editing_id') || {}).value || '';
            var taken = (_usedTriggerValues[pid] || [])
                .filter(function(e) { return String(e.qid) !== String(editingIdNow); })
                .map(function(e) { return e.val; });

            // Count free values
            var freeCount = allValues.filter(function(v) { return !taken.includes(v); }).length;

            if (freeCount === 0) {
                // All values taken — disable and show hint
                $(this).prop('disabled', true)
                       .text($(this).data('orig-text') || $(this).text().replace(' (all values used)', '') + ' (all values used)');
                if (!$(this).data('orig-text')) $(this).data('orig-text', $(this).text().replace(' (all values used)', ''));
            } else {
                // Has free values — keep enabled
                $(this).prop('disabled', false);
                var orig = $(this).data('orig-text');
                if (orig) $(this).text(orig);
            }
        });

        // If currently-selected parent became fully-taken, reset selection
        var $sel = $('#aq_dep_parent');
        var curOpt = $sel.find('option[value="' + $sel.val() + '"]');
        if (curOpt.prop('disabled')) {
            $sel.val('');
            onDepParentChange();
        }
    }

    function onDepParentChange() {
        var sel = document.getElementById('aq_dep_parent');
        var opt = sel.options[sel.selectedIndex];
        var $tv = document.getElementById('sec_dep_val');
        var $vs = document.getElementById('aq_dep_value');
        $vs.innerHTML = '<option value="">— Select trigger value —</option>';
        if (!opt.value) { $tv.style.display = 'none'; return; }
        $tv.style.display = 'block';

        var parentId  = opt.value;
        var qType     = opt.dataset.type;
        var editingId = (document.getElementById('aq_editing_id') || {}).value || '';
        var takenValues = (_usedTriggerValues[parentId] || [])
            .filter(function(e) { return String(e.qid) !== String(editingId); })
            .map(function(e) { return e.val; });

        // "Is Answered" option — available for ALL question types
        var nonEmptyTaken = takenValues.includes('__non_empty__');
        $vs.innerHTML += '<option value="__non_empty__"' + (nonEmptyTaken ? ' disabled style="color:#9CA3AF;"' : '') + '>'
            + 'Is Answered (any value)' + (nonEmptyTaken ? ' (already used)' : '') + '</option>';

        // Value-specific options — only for Yes/No and Dropdown
        if (qType === 'Yes / No' || qType === 'Dropdown') {
            var allValues = qType === 'Yes / No' ? ['Yes', 'No']
                          : JSON.parse(opt.dataset.options || '[]');
            allValues.forEach(function(o) {
                if (takenValues.includes(o)) {
                    $vs.innerHTML += '<option value="' + o + '" disabled style="color:#9CA3AF;">' + o + ' (already used)</option>';
                } else {
                    $vs.innerHTML += '<option value="' + o + '">' + o + '</option>';
                }
            });
        }
    }

    // Validation rule change
    function onValRuleChange() {
        var rule = document.getElementById('aq_val_rule')?.value || 'none';
        var showRange = ['numericrange','digitlength','textlength'].includes(rule);
        var showRegex = rule === 'regex';
        var minLbl  = rule === 'numericrange' ? 'Min Number'
                    : rule === 'digitlength' ? 'Min Digits'
                    : rule === 'textlength'  ? 'Min Chars' : 'Min';
        var maxLbl  = rule === 'numericrange' ? 'Max Number'
                    : rule === 'digitlength' ? 'Max Digits'
                    : rule === 'textlength'  ? 'Max Chars' : 'Max';
        var r = document.getElementById('sec_val_range');
        var x = document.getElementById('sec_val_regex');
        if (r) r.style.display  = showRange ? 'flex' : 'none';
        if (x) x.style.display  = showRegex ? 'block' : 'none';
        var ml = document.getElementById('valMinLbl');
        var xl = document.getElementById('valMaxLbl');
        if (ml) ml.textContent = minLbl;
        if (xl) xl.textContent = maxLbl;
    }

    // Options management
    function addOption() {
        var inp = document.getElementById('newOptInput');
        var val = inp.value.trim();
        if (!val) return;
        _options.push(val);
        inp.value = '';
        renderOptions();
    }

    function removeOption(i) {
        _options.splice(i, 1);
        renderOptions();
    }

    function renderOptions() {
        var html = '';
        _options.forEach((o, i) => {
            html += `<div style="display:flex;align-items:center;gap:7px;padding:7px 9px;border:1px solid #E8E8F0;border-radius:5px;margin-bottom:4px;background:#FAFAFA;">
                <span style="flex:1;font-size:13px;color:#374151;">${escHtml(o)}</span>
                <button onclick="removeOption(${i})" style="background:none;border:none;cursor:pointer;color:#DC2626;font-size:17px;padding:1px 4px;line-height:1;border-radius:3px;">×</button>
            </div>`;
        });
        document.getElementById('optList').innerHTML = html;
        updateOptCount();
    }

    function updateOptCount() {
        var el = document.getElementById('optCount');
        if (el) el.textContent = _options.length + ' option(s)';
    }

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function hideErrors() {
        ['aq_text_err','aq_type_err','aq_opt_err','aq_save_err'].forEach(id => {
            var el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });
    }

    function saveQuestion() {
        hideErrors();
        var valid = true;
        var text = document.getElementById('aq_text').value.trim();
        var type = document.getElementById('aq_type').value;
        var editingId = document.getElementById('aq_editing_id').value;
        var isEdit = (_panelMode === 'edit' && editingId);

        if (!text) { document.getElementById('aq_text_err').style.display = 'block'; valid = false; }
        if (!type) { document.getElementById('aq_type_err').style.display = 'block'; valid = false; }
        if (['Dropdown','Multi select'].includes(type) && _options.length === 0) {
            document.getElementById('aq_opt_err').style.display = 'block'; valid = false;
        }
        if (!valid) return;

        var btn = document.getElementById('aq_save_btn');
        btn.disabled = true; btn.textContent = 'Saving…';

        var depParent = document.getElementById('aq_dep_parent').value;
        var depValue  = document.getElementById('aq_dep_value').value;

        // Collect parent triggers for edit mode
        var selectedParentIds = isEdit ? ($('#new_parent_select').val() || []) : [];
        var currentTriggerVal = isEdit ? ($('#new_trigger_select').val() || null) : null;
        selectedParentIds.forEach(function(pid) {
            if ($('#parent_triggers_list .trigger-row[data-parent-id="' + pid + '"]').length === 0) {
                var row = $('<div class="trigger-row">').attr('data-parent-id', pid).attr('data-trigger-value', currentTriggerVal || '');
                $('#parent_triggers_list').append(row);
            }
        });
        var parent_triggers = [];
        $('#parent_triggers_list .trigger-row').each(function() {
            var pid = $(this).data('parent-id');
            var tv  = $(this).data('trigger-value');
            if (pid) parent_triggers.push({ parent_id: pid, trigger_value: (tv !== undefined && tv !== '') ? tv : null });
        });

        var data = {
            activity_id:          {{ $activity->id }},
            question:             text,
            question_type:        type,
            answer_type:          document.getElementById('aq_required').value,
            help_text:            document.getElementById('aq_help').value.trim() || null,
            options:              _options.join('|') || null,
            allow_multiple_images: document.getElementById('aq_allow_multi_img').value,
            validation_rule:      document.getElementById('aq_val_rule').value,
            validation_min:       document.getElementById('aq_val_min').value  || null,
            validation_max:       document.getElementById('aq_val_max').value  || null,
            validation_regex:     document.getElementById('aq_val_regex').value || null,
            file_types:           document.getElementById('aq_file_types').value.trim() || null,
            max_file_size_mb:     document.getElementById('aq_max_size').value || null,
            date_min:             document.getElementById('aq_date_min').value || null,
            date_max:             document.getElementById('aq_date_max').value || null,
            parent_question_id:   (_depOn && depParent) ? depParent : null,
            parent_value:         (_depOn && depParent) ? depValue  : null,
            parent_group_id:      document.getElementById('aq_parent_group').value || null,
        };

        var ajaxConfig;
        if (isEdit) {
            data.id = editingId;
            data.question_sequence = null; // keep existing sequence
            data.parent_triggers = parent_triggers;
            ajaxConfig = {
                url:  '{{ route("question.update") }}',
                type: 'PUT',
                data: { _token: '{{ csrf_token() }}', form_data: data },
            };
        } else {
            ajaxConfig = {
                url:  '{{ route("question.store") }}',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', form_data: data },
            };
        }

        $.ajax(Object.assign(ajaxConfig, {
            success: function(res) {
                if (res.message === 'success') {
                    // After question is saved, persist any new inline sub-questions
                    var savedQId = isEdit ? editingId : (res.question ? res.question.id : null);
                    saveNewSubQuestions(savedQId, function() {
                        closeAddPanel();
                        var title = isEdit ? 'Updated!' : 'Question Added!';
                        var msgTxt = isEdit ? 'Question has been updated.' : 'The question has been created.';
                        Swal.fire({ icon:'success', title:title, text:msgTxt, timer:1500, showConfirmButton:false })
                            .then(() => location.reload());
                    });
                } else {
                    showSaveErr('Unexpected response.');
                }
            },
            error: function(xhr) {
                var msg = 'Save failed.';
                try { msg = JSON.parse(xhr.responseText).message || msg; } catch(e){}
                showSaveErr(msg);
            },
            complete: function() {
                btn.disabled = false;
                btn.textContent = isEdit ? 'Update Question' : 'Save Question';
            }
        }));
    }

    function showSaveErr(msg) {
        var el = document.getElementById('aq_save_err');
        el.textContent = msg; el.style.display = 'block';
    }

    // Close panel on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeAddPanel();
    });
    </script>
@endsection
