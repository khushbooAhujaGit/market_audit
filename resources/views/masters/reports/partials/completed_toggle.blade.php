{{--
    Open / Completed project switch for report pages (segmented control in the card header).
    The controller filters the project dropdown by ?view=completed (projects.is_completed)
    for every role, Super Admin included.

    Required:
      $routeName        — named route of the current report page
      $view             — 'open' | 'completed'
    Optional:
      $part             — 'switch' (default, header control) | 'hint' (note under the dropdown)

    The query string is appended manually: route() params are encrypted by EncryptedUrlGenerator.
--}}
@php $isCompletedView = ($view ?? 'open') === 'completed'; @endphp

@if (($part ?? 'switch') === 'hint')
    @if ($isCompletedView)
        <small class="project-view-hint d-block mt-1">
            <i class="fa fa-check-circle"></i> Showing completed projects only
        </small>
    @endif
@else
    @once
        <style>
            .project-view-switch {
                display: inline-flex;
                padding: 3px;
                border-radius: 999px;
                background: #eef1f5;
                border: 1px solid #dde2ea;
            }
            .project-view-switch a {
                padding: 6px 16px;
                border-radius: 999px;
                font-size: 13px;
                font-weight: 600;
                color: #5b6573;
                text-decoration: none;
                white-space: nowrap;
                transition: background .15s, color .15s;
            }
            .project-view-switch a:hover { color: #0a2b3d; }
            .project-view-switch a.active {
                background: #0a2b3d;
                color: #fff;
                box-shadow: 0 1px 3px rgba(0, 0, 0, .15);
            }
            .project-view-hint { color: #198754; font-weight: 500; }
        </style>
    @endonce
    <div class="project-view-switch" role="tablist" aria-label="Project list">
        <a href="{{ route($routeName) }}" class="{{ $isCompletedView ? '' : 'active' }}"
           role="tab" aria-selected="{{ $isCompletedView ? 'false' : 'true' }}">Open Projects</a>
        <a href="{{ route($routeName) }}?view=completed" class="{{ $isCompletedView ? 'active' : '' }}"
           role="tab" aria-selected="{{ $isCompletedView ? 'true' : 'false' }}">Completed Projects</a>
    </div>
@endif
