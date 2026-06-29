@extends('template.layouts.simple.master')
@section('content')
<div class="page-body">
<div class="container-fluid">

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Bulk Add Questions</h4>
        <small class="text-muted">Activity: <strong>{{ $activity->activity_name }}</strong></small>
    </div>
    <a href="{{ route('activities.question', $activity->id) }}" class="btn btn-outline-secondary btn-sm">
        ← Back to Questions
    </a>
</div>

@if(session('message'))
    <div class="alert alert-success">{{ session('message') }}</div>
@endif

<div class="card">
    <div class="card-body p-0">

        <form id="bulkForm" action="{{ route('activities.questions.bulk_store', $activity->id) }}" method="POST">
            @csrf

            {{-- Top toolbar --}}
            <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom flex-wrap gap-2"
                 style="background:#f8f9fa;">
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" id="addRowBtn" class="btn btn-sm btn-primary">
                        + Add Row
                    </button>
                    <button type="button" id="addMultiResponseBtn" class="btn btn-sm btn-outline-primary">
                        + Add Section (Multi Response)
                    </button>
                    <button type="button" id="clearAllBtn" class="btn btn-sm btn-outline-danger">
                        Clear All
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <span class="text-muted" style="font-size:12px;align-self:center;">
                        <span id="rowCount">0</span> question(s)
                    </span>
                    <button type="submit" class="btn btn-sm btn-success px-4">
                        ✓ Save All Questions
                    </button>
                </div>
            </div>

            {{-- Legend --}}
            <div class="px-3 py-2 border-bottom" style="background:#fffbf0;font-size:11px;color:#6b6b6b;">
                <strong>Tips:</strong>
                Options — pipe-separated e.g. <code>Option A|Option B|Option C</code> &nbsp;·&nbsp;
                <span style="background:#e8f0fe;padding:2px 6px;border-radius:3px;color:#1a56db;">Blue rows</span> = Multi Response (section headers) &nbsp;·&nbsp;
                Sub-questions: set <em>Parent Section</em> to link them under a Multi Response
            </div>

            {{-- Table --}}
            <div style="overflow-x:auto;">
                <table class="table table-bordered mb-0" id="bulkTable" style="min-width:900px;font-size:13px;">
                    <thead style="background:#343a40;color:#fff;position:sticky;top:0;z-index:10;">
                        <tr>
                            <th style="width:36px;">#</th>
                            <th style="min-width:240px;">Question Text <span class="text-danger">*</span></th>
                            <th style="width:150px;">Type</th>
                            <th style="width:80px;">Required</th>
                            <th style="min-width:180px;">Options <small style="font-weight:400;opacity:.7;">(Dropdown/Multi)</small></th>
                            <th style="width:130px;">Parent Section</th>
                            <th style="width:110px;">Help Text</th>
                            <th style="width:130px;">Extra</th>
                            <th style="width:36px;"></th>
                        </tr>
                    </thead>
                    <tbody id="bulkBody">
                        {{-- rows injected by JS --}}
                    </tbody>
                </table>
            </div>

            {{-- Bottom save --}}
            <div class="px-3 py-3 border-top d-flex justify-content-end gap-2">
                <a href="{{ route('activities.question', $activity->id) }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-success px-5">✓ Save All Questions</button>
            </div>

        </form>
    </div>
</div>

</div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    var rowIndex = 0;
    var TYPES = [
        'Free Text','Yes / No','Dropdown','Multi select','Image','Video',
        'Date','Date & Time','File Upload','Location','Audio','Subjective','Multi Response'
    ];

    // Build type <select> options HTML
    var typeOptions = TYPES.map(function(t) {
        return '<option value="' + t + '">' + t + '</option>';
    }).join('');

    // Get Multi Response rows for the "Parent Section" dropdown
    function getMROptions() {
        var opts = '<option value="">— None (standalone) —</option>';
        $('#bulkBody tr[data-qtype="Multi Response"]').each(function() {
            var idx  = $(this).data('row-index');
            var text = $(this).find('.q-text').val().trim() || '(Section ' + (idx + 1) + ')';
            var qid  = $(this).data('server-id') || '';
            opts += '<option value="' + idx + '" data-server-id="' + qid + '">' + text + '</option>';
        });
        return opts;
    }

    function refreshParentSelects() {
        var opts = getMROptions();
        $('#bulkBody .q-parent').each(function() {
            var cur = $(this).val();
            $(this).html(opts);
            if (cur) $(this).val(cur);
        });
    }

    function addRow(type, isSubQ) {
        type = type || 'Free Text';
        var isMR = (type === 'Multi Response');
        var bgStyle = isMR ? 'background:#e8f0fe;' : '';

        var row = $('<tr>')
            .attr('data-row-index', rowIndex)
            .attr('data-qtype', type)
            .css(isMR ? {'background':'#e8f0fe','font-weight':'600'} : {});

        var rIdx = rowIndex;

        row.html(
            '<td class="text-center text-muted" style="font-size:11px;vertical-align:middle;">' + (rowIndex + 1) + '</td>' +

            '<td style="vertical-align:top;padding:4px;">' +
                '<textarea name="questions[' + rIdx + '][question]" rows="2" class="form-control form-control-sm q-text" ' +
                    'placeholder="Type your question here..." style="resize:vertical;font-size:12px;"></textarea>' +
            '</td>' +

            '<td style="vertical-align:top;padding:4px;">' +
                '<select name="questions[' + rIdx + '][question_type]" class="form-select form-select-sm q-type">' +
                    typeOptions +
                '</select>' +
            '</td>' +

            '<td style="vertical-align:top;padding:4px;text-align:center;">' +
                '<input type="checkbox" name="questions[' + rIdx + '][answer_type]" value="1" class="form-check-input q-required" ' +
                    'style="width:18px;height:18px;margin-top:6px;">' +
            '</td>' +

            '<td style="vertical-align:top;padding:4px;">' +
                '<textarea name="questions[' + rIdx + '][options]" rows="2" class="form-control form-control-sm q-options" ' +
                    'placeholder="A|B|C" style="resize:vertical;font-size:11px;' + (isMR ? 'display:none;' : '') + '">' +
                '</textarea>' +
                (isMR ? '<small class="text-muted" style="font-size:11px;">Section header</small>' : '') +
            '</td>' +

            '<td style="vertical-align:top;padding:4px;">' +
                '<select name="questions[' + rIdx + '][parent_group_id]" class="form-select form-select-sm q-parent">' +
                    getMROptions() +
                '</select>' +
            '</td>' +

            '<td style="vertical-align:top;padding:4px;">' +
                '<input type="text" name="questions[' + rIdx + '][help_text]" class="form-control form-control-sm" ' +
                    'placeholder="Hint..." style="font-size:11px;">' +
            '</td>' +

            '<td style="vertical-align:top;padding:4px;">' +
                '<div class="q-extra-img" style="' + (type === 'Image' ? '' : 'display:none;') + '">' +
                    '<label style="font-size:10px;color:#555;">Multi-image</label><br>' +
                    '<input type="checkbox" name="questions[' + rIdx + '][allow_multiple_images]" value="1" ' +
                        'class="form-check-input" style="width:16px;height:16px;">' +
                '</div>' +
            '</td>' +

            '<td style="vertical-align:middle;padding:4px;text-align:center;">' +
                '<button type="button" class="btn btn-sm text-danger p-0 remove-row-btn" ' +
                    'style="font-size:18px;line-height:1;background:none;border:none;" title="Remove row">×</button>' +
            '</td>'
        );

        // Set selected type
        row.find('.q-type').val(type);

        $('#bulkBody').append(row);
        rowIndex++;
        updateRowNumbers();
        refreshParentSelects();
        updateCount();

        if (isMR) {
            // Disable options and parent for Multi Response rows
            row.find('.q-options').hide();
            row.find('.q-parent').prop('disabled', true).closest('td').html(
                '<small class="text-muted" style="font-size:11px;">—</small>' +
                '<input type="hidden" name="questions[' + rIdx + '][parent_group_id]" value="">'
            );
        }

        return row;
    }

    function updateRowNumbers() {
        $('#bulkBody tr').each(function(i) {
            $(this).find('td:first').text(i + 1);
        });
    }

    function updateCount() {
        $('#rowCount').text($('#bulkBody tr').length);
    }

    // Type change handler
    $(document).on('change', '.q-type', function() {
        var $row  = $(this).closest('tr');
        var type  = $(this).val();
        var isMR  = (type === 'Multi Response');
        var isOpt = (type === 'Dropdown' || type === 'Multi select');
        var isImg = (type === 'Image');

        $row.attr('data-qtype', type);
        $row.css(isMR ? {'background':'#e8f0fe','font-weight':'600'} : {'background':'','font-weight':''});

        $row.find('.q-options').toggle(isOpt && !isMR);
        $row.find('.q-extra-img').toggle(isImg);

        refreshParentSelects();
        updateCount();
    });

    // Question text change — refresh parent selects so MR names update
    $(document).on('input', '.q-text', function() {
        if ($(this).closest('tr').attr('data-qtype') === 'Multi Response') {
            refreshParentSelects();
        }
    });

    // Remove row
    $(document).on('click', '.remove-row-btn', function() {
        $(this).closest('tr').remove();
        updateRowNumbers();
        refreshParentSelects();
        updateCount();
    });

    // Toolbar buttons
    $('#addRowBtn').on('click', function() { addRow('Free Text'); });
    $('#addMultiResponseBtn').on('click', function() { addRow('Multi Response'); });

    $('#clearAllBtn').on('click', function() {
        if (confirm('Clear all rows?')) {
            $('#bulkBody').empty();
            rowIndex = 0;
            updateCount();
        }
    });

    // Map parent_group_id index → real question ID on submit
    $('#bulkForm').on('submit', function(e) {
        // For each row, resolve parent_group_id from row-index to server ID (if saved)
        // For new bulk creation, parent links are resolved server-side by row order
        // Nothing extra needed — server handles index-based linking
    });

    // Keyboard shortcut: Tab on last cell of last row adds new row
    $(document).on('keydown', 'td:last-child input, td:last-child textarea', function(e) {
        if (e.key === 'Tab' && !e.shiftKey) {
            var $lastRow = $('#bulkBody tr:last');
            if ($(this).closest('tr').is($lastRow)) {
                e.preventDefault();
                addRow('Free Text');
                $('#bulkBody tr:last .q-text').focus();
            }
        }
    });

    // Start with 5 blank rows
    for (var i = 0; i < 5; i++) addRow('Free Text');

}());
</script>
@endsection
