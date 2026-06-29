@php
    static $imgRowCounter = 0;
    $imgRowCounter++;
    $uid = $inputName . '_r' . $imgRowCounter;
    $showRemove = !($isFirst ?? true);
@endphp

<div class="multi-img-row mb-2" style="box-sizing:border-box;">
    {{-- Hidden inputs --}}
    <input type="file" id="cam_{{ $uid }}" class="multi-image-file-input d-none" accept="image/*" capture="environment">
    <input type="file" id="gal_{{ $uid }}" class="multi-image-file-input d-none" accept="image/*">

    {{-- Single row: [thumbnail] [Camera | Gallery] [&#215;] --}}
    <div style="display:flex;align-items:center;gap:8px;width:100%;box-sizing:border-box;">

        {{-- Small square thumbnail &#8212; background-image, never changes size --}}
        <div id="prev_{{ $uid }}"
             style="width:40px;height:40px;min-width:40px;border-radius:6px;border:2px dashed #ccc;background:#f8f9fa center/cover no-repeat;flex-shrink:0;display:flex;align-items:center;justify-content:center;overflow:hidden;">
            <span id="icon_{{ $uid }}" style="font-size:14px;color:#aaa;">&#128444;</span>
        </div>

        {{-- Camera + Gallery side by side, equal width --}}
        <div style="display:flex;gap:5px;flex:1;min-width:0;">
            <button type="button" onmousedown="this.blur()"
                    onclick="document.getElementById('cam_{{ $uid }}').click()"
                    style="flex:1;padding:8px 4px;border:1.5px solid #2563EB;border-radius:6px;background:#EFF6FF;color:#1D4ED8;font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap;overflow:hidden;min-width:0;">
                &#128247; Camera
            </button>
            <button type="button" onmousedown="this.blur()"
                    onclick="document.getElementById('gal_{{ $uid }}').click()"
                    style="flex:1;padding:8px 4px;border:1.5px solid #059669;border-radius:6px;background:#F0FDF4;color:#065F46;font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap;overflow:hidden;min-width:0;">
                &#128247; Gallery
            </button>
        </div>

        {{-- Remove &#8212; always in DOM, invisible on first row so widths stay consistent --}}
        <button type="button" class="remove-img-row-btn" data-qid="{{ $inputName ?? '' }}"
                onmousedown="this.blur()"
                style="width:32px;min-width:32px;height:40px;flex-shrink:0;border:1.5px solid #DC2626;border-radius:6px;background:#FEF2F2;color:#DC2626;font-size:17px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;{{ $showRemove ? '' : 'visibility:hidden;pointer-events:none;' }}">
            &#215;
        </button>
    </div>
</div>

<script>
(function(){
    function bind(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('change', function() {
            if (!this.files || !this.files[0]) return;
            var r = new FileReader();
            r.onload = function(e) {
                var prev = document.getElementById('prev_{{ $uid }}');
                prev.style.backgroundImage = 'url(' + e.target.result + ')';
                var icon = document.getElementById('icon_{{ $uid }}');
                if (icon) icon.style.display = 'none';
            };
            r.readAsDataURL(this.files[0]);
        });
    }
    bind('cam_{{ $uid }}');
    bind('gal_{{ $uid }}');
})();
</script>
