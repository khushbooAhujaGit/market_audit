{{-- Footer hidden. Show it again with SHOW_FOOTER_COPYRIGHT=true in .env (or restore footer.blade.php.bak_before_footer_removal) --}}
@if (filter_var(env('SHOW_FOOTER_COPYRIGHT', false), FILTER_VALIDATE_BOOLEAN))
{{-- Footer Start--}}
<footer class="footer">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12 footer-copyright d-flex flex-wrap align-items-center justify-content-between">
                <p class="mb-0 f-w-600">Copyright 2024 - 2025 © TNBT</p>
                <p class="mb-0 f-w-600">
                    <svg class="footer-icon d-none">
                        <use href="../assets/svg/icon-sprite.svg#footer-heart"> </use>
                    </svg>
                </p>
            </div>
        </div>
    </div>
</footer>
@endif
