<!-- Page Sidebar Start-->
<div class="sidebar-wrapper" data-layout="stroke-svg">
    <div>
        <div class="logo-wrapper"><a href="/">
                <img class="img-fluid" width="135px"
                    src="{{ asset('assets/images/logo/tnbtlogo.png') }}" alt="">
            </a>
            <div class="back-btn"><i class="fa fa-angle-left"></i></div>
            <div class="toggle-sidebar">
                <svg class="stroke-icon sidebar-toggle status_toggle middle">
                    <use href="{{ url('assets/svg/icon-sprite.svg#toggle-icon') }}"></use>
                </svg>
                <svg class="fill-icon sidebar-toggle status_toggle middle">
                    <use href="{{ url('assets/svg/icon-sprite.svg#fill-toggle-icon') }}"></use>
                </svg>
            </div>
        </div>
        <div class="logo-icon-wrapper"><a href="index.html"><img class="img-fluid"
                    src="../assets/images/logo/logo-icon.png" alt=""></a></div>
        <nav class="sidebar-main">
            <div class="left-arrow" id="left-arrow"><i data-feather="arrow-left"></i></div>
            <div id="sidebar-menu">
                <ul class="sidebar-links" id="simple-bar">
                    <li class="back-btn"><a href="index.html"><img class="img-fluid"
                                src="{{ url('assets/images/logo/logo-icon.png') }}" alt=""></a>
                        <div class="mobile-back text-end"><span>Back</span><i class="fa fa-angle-right ps-2"
                                aria-hidden="true"></i></div>
                    </li>
                    <li class="pin-title sidebar-main-title">
                        <div style="background-color: white;" >
                            <h6 style="color: #042b39;" >Pinned</h6>
                        </div>
                    </li>
                    <li class="sidebar-main-title">
                        <div style="background-color: white;" >
                            <h6 class="lan-1" style="color: #042b39;" >General</h6>
                        </div>
                    </li>
                    <li class="sidebar-list d-none"><i class="fa fa-thumb-tack"></i><a
                            class="sidebar-link sidebar-title" href="javascript:void(0)">
                            <svg class="stroke-icon">
                                <use href="../assets/svg/icon-sprite.svg#stroke-home"></use>
                            </svg>
                            <svg class="fill-icon">
                                <use href="../assets/svg/icon-sprite.svg#fill-home"></use>
                            </svg><span class="lan-3">Dashboard </span></a>
                        <ul class="sidebar-submenu">
                            <li><a class="lan-4" href="index.html">Default</a></li>
                            <li><a class="lan-5" href="dashboard-02.html">Project</a></li>
                            <li><a href="dashboard-03.html">Ecommerce</a></li>
                            <li><a href="dashboard-04.html">Education</a></li>
                        </ul>
                    </li>
                    @can('Company Master')
                        <li class="sidebar-list"><i class="fa fa-thumb-tack"> </i><a class="sidebar-link sidebar-title"
                                href="javascript:void(0)">
                                <svg class="stroke-icon">
                                    <use href="../assets/svg/icon-sprite.svg#stroke-project"></use>
                                </svg>
                                <svg class="fill-icon">
                                    <use href="../assets/svg/icon-sprite.svg#fill-project"></use>
                                </svg>
                                <span> Master </span></a>
                            <ul class="sidebar-submenu">
                                @can('Company Types')
                                    <li><a href="{{ route('company_type.list') }}">Company Types</a></li>
                                @endcan
                                @can('Project Types')
                                    <li><a href="{{ route('project_type.list') }}">Project Types</a></li>
                                @endcan
                                @can('Companies')
                                    <li><a href="{{ route('companies.list') }}">Companies</a></li>
                                @endcan
                                @can('Zones')
                                    <li><a href="{{ route('zone.list') }}">Zones</a></li>
                                @endcan
                                @can('Units')
                                    <li><a href="{{ route('unit.list') }}">Units</a></li>
                                @endcan
                            </ul>
                        </li>
                    @endcan
                    @can('Users')
                        <li class="sidebar-list"><i class="fa fa-thumb-tack"> </i><a class="sidebar-link sidebar-title"
                                href="javascript:void(0)">
                                <svg class="stroke-icon">
                                    <use href="../assets/svg/icon-sprite.svg#stroke-project"></use>
                                </svg>
                                <svg class="fill-icon">
                                    <use href="../assets/svg/icon-sprite.svg#fill-project"></use>
                                </svg><span>Users </span></a>
                            <ul class="sidebar-submenu">
                                @can('View Users')
                                    <li><a href="{{ url('users') }}">View User</a></li>
                                @endcan
                                @can('Add User')
                                    <li><a href="{{ url('users/create') }}">Add User</a></li>
                                @endcan
                                @can('User Bulk Upload')
                                    <li><a href="{{ route('users.upload.view') }}">User Bulk Upload</a></li>
                                @endcan
                            </ul>
                        </li>
                    @endcan
                    @can('Role-Permission')
                        <li class="sidebar-list"><i class="fa fa-thumb-tack"> </i><a class="sidebar-link sidebar-title"
                                href="javascript:void(0)">
                                <svg class="stroke-icon">
                                    <use href="../assets/svg/icon-sprite.svg#stroke-project"></use>
                                </svg>
                                <svg class="fill-icon">
                                    <use href="../assets/svg/icon-sprite.svg#fill-project"></use>
                                </svg><span>Role-Permission </span></a>
                            <ul class="sidebar-submenu">
                                <li><a href="{{ url('permissions') }}">Permissions</a></li>
                                <li><a href="{{ url('roles') }}">Roles</a></li>
                            </ul>
                        </li>
                    @endcan
                    @can('Project Mangement')
                        <li class="sidebar-list"><i class="fa fa-thumb-tack"> </i><a class="sidebar-link sidebar-title"
                                href="javascript:void(0)">
                                <svg class="stroke-icon">
                                    <use href="../assets/svg/icon-sprite.svg#stroke-project"></use>
                                </svg>
                                <svg class="fill-icon">
                                    <use href="../assets/svg/icon-sprite.svg#fill-project"></use>
                                </svg>
                                <span>Project Management </span></a>
                            <ul class="sidebar-submenu">
                                @can('Projects')
                                    <li><a class="submenu-title" href="javascript:void(0)">Projects<span class="sub-arrow"><i
                                                    class="fa fa-angle-right"></i></span></a>
                                        <ul class="nav-sub-childmenu submenu-content">
                                            <li><a href="{{ route('project.list') }}">List</a></li>
                                            <li><a href="{{ route('project.create') }}">Create</a></li>
                                        </ul>
                                    </li>
                                @endcan
                                @can('Upload Project Data')
                                    <li><a href="{{ route('projectData.upload') }}">Upload</a></li>
                                @endcan
                                @can('View Project Data')
                                    <li><a href="{{ route('project_template_uploaded_data.view') }}">View Project data</a>
                                    </li>
                                @endcan
                                @can('Project Data Activity Mapping')
                                    <li><a href="{{ route('project.data_activity_mapping') }}">Mapping Activity</a></li>
                                @endcan
                                @can('Set Compliance')
                                    <li><a href="{{ route('set_compliance') }}">Set Compliance</a></li>
                                @endcan
                                @can('Compliance Action Taken')
                                    <li><a href="{{ route('compliance_action_taken') }}">Compliance Action Taken</a></li>
                                @endcan
                                @can('Distributor Setting')
                                    <li><a href="{{ route('distributor_setting') }}">Distributor Setting</a></li>
                                @endcan
                                @can('Assign Data')
                                    <li><a href="{{ route('assign_data.create') }}">Assign Data</a></li>
                                @endcan
                                @can('Assign Data List')
                                    <li><a href="{{ route('auditorAssigned.list') }}">Assign Data List</a></li>
                                @endcan
                            </ul>
                        </li>
                    @endcan
                    @can('Activity Master')
                        <li class="sidebar-list"><i class="fa fa-thumb-tack"> </i><a class="sidebar-link sidebar-title"
                                href="javascript:void(0)">
                                <svg class="stroke-icon">
                                    <use href="../assets/svg/icon-sprite.svg#stroke-project"></use>
                                </svg>
                                <svg class="fill-icon">
                                    <use href="../assets/svg/icon-sprite.svg#fill-project"></use>
                                </svg><span>Activity Master </span></a>
                            <ul class="sidebar-submenu">
                                <li><a href="{{ route('activities.list') }}">Activities</a></li>
                                <li><a href="{{ route('activities.create') }}">Create</a></li>
                                <li><a href="{{ route('activityGroup.create') }}">Create Activity Group</a></li>
                                <li><a href="{{ route('activityGroup.list') }}">Activity Group List</a></li>
                                <li class="d-none"><a href="{{ route('dropdown.list') }}">Activity Dropdown Question</a>
                                </li>
                                <li class="d-none"><a href="{{ route('dropdown.create') }}">Create Dropdown</a></li>
                                <li class="d-none"><a href="{{ route('import.options_dropdown') }}">Dropdown Options
                                        Excel Import</a></li>
                                <li class="d-none"><a href="{{ route('subjectiveQuestion.list') }}">Subjective
                                        Activity</a></li>
                            </ul>
                        </li>
                    @endcan
                    @can('Template Master')
                        <li class="sidebar-list "><i class="fa fa-thumb-tack"> </i><a class="sidebar-link sidebar-title"
                                href="javascript:void(0)">
                                <svg class="stroke-icon">
                                    <use href="../assets/svg/icon-sprite.svg#stroke-project"></use>
                                </svg>
                                <svg class="fill-icon">
                                    <use href="../assets/svg/icon-sprite.svg#fill-project"></use>
                                </svg><span>Template Master </span></a>
                            <ul class="sidebar-submenu">
                                <li><a href="{{ route('templateName.list') }}">Templates</a></li>
                                <li><a href="{{ route('templateName.create') }}">Create</a></li>
                            </ul>
                        </li>
                    @endcan
                    @can('Remark Master')
                        <li class="sidebar-list "><i class="fa fa-thumb-tack"> </i><a class="sidebar-link sidebar-title"
                                href="javascript:void(0)">
                                <svg class="stroke-icon">
                                    <use href="../assets/svg/icon-sprite.svg#stroke-project"></use>
                                </svg>
                                <svg class="fill-icon">
                                    <use href="../assets/svg/icon-sprite.svg#fill-project"></use>
                                </svg><span>Remark Master </span></a>
                            <ul class="sidebar-submenu">
                                <li><a href="{{ route('remark.list') }}">Remarks</a></li>
                                <li><a href="{{ route('remark.create') }}">Create</a></li>
                            </ul>
                        </li>
                    @endcan
                    @can('My Verifications')
                        <li class="sidebar-list"><i class="fa fa-thumb-tack"></i><a
                                class="sidebar-link sidebar-title link-nav" href="{{ route('user.verification.list') }}">
                                <svg class="stroke-icon">
                                    <use href="../assets/svg/icon-sprite.svg#stroke-file"></use>
                                </svg>
                                <svg class="fill-icon">
                                    <use href="../assets/svg/icon-sprite.svg#fill-file"></use>
                                </svg><span>My Verifications</span></a></li>
                    @endcan
                    @can('Company Verification')
                        <li class="sidebar-list"><i class="fa fa-thumb-tack"></i><a
                                class="sidebar-link sidebar-title link-nav" href="{{ route('company.verifyPage') }}">
                                <svg class="stroke-icon">
                                    <use href="../assets/svg/icon-sprite.svg#stroke-file"></use>
                                </svg>
                                <svg class="fill-icon">
                                    <use href="../assets/svg/icon-sprite.svg#fill-file"></use>
                                </svg><span>Company Verification</span></a></li>
                    @endcan
                    @can('PDF Download')
                        <li class="sidebar-list"><i class="fa fa-thumb-tack"></i><a
                                class="sidebar-link sidebar-title link-nav" href="{{ route('report_page') }}">
                                <svg class="stroke-icon">
                                    <use href="../assets/svg/icon-sprite.svg#stroke-file"></use>
                                </svg>
                                <svg class="fill-icon">
                                    <use href="../assets/svg/icon-sprite.svg#fill-file"></use>
                                </svg><span>PDF Report</span></a>
                        </li>
                    @endcan
                    @can('Non Compliance Report')
                        <li class="sidebar-list"><i class="fa fa-thumb-tack"></i><a
                                class="sidebar-link sidebar-title link-nav"
                                href="{{ route('company.nonCompliancePage') }}">
                                <svg class="stroke-icon">
                                    <use href="../assets/svg/icon-sprite.svg#stroke-file"></use>
                                </svg>
                                <svg class="fill-icon">
                                    <use href="../assets/svg/icon-sprite.svg#fill-file"></use>
                                </svg><span>Non Compliance Report</span></a></li>
                    @endcan
                    @can('Reports')
                        <li class="sidebar-list"><i class="fa fa-thumb-tack"></i><a
                                class="sidebar-link sidebar-title link-nav" href="{{ route('view-report') }}">
                                <svg class="stroke-icon">
                                    <use href="../assets/svg/icon-sprite.svg#stroke-file"></use>
                                </svg>
                                <svg class="fill-icon">
                                    <use href="../assets/svg/icon-sprite.svg#fill-file"></use>
                                </svg>
                                <span>Reports</span></a></li>
                    @endcan
                    @can('Project Report')
                        <li class="sidebar-list"><i class="fa fa-thumb-tack"></i><a
                                class="sidebar-link sidebar-title link-nav" href="{{ route('project-report') }}">
                                <svg class="stroke-icon">
                                    <use href="../assets/svg/icon-sprite.svg#stroke-file"></use>
                                </svg>
                                <svg class="fill-icon">
                                    <use href="../assets/svg/icon-sprite.svg#fill-file"></use>
                                </svg>
                                <span>Project Report</span></a></li>
                    @endcan
                    @can('My Projects')
                        <li class="sidebar-list"><i class="fa fa-thumb-tack"></i><a
                                class="sidebar-link sidebar-title link-nav" href="{{ route('user.projects') }}">
                                <svg class="stroke-icon">
                                    <use href="../assets/svg/icon-sprite.svg#stroke-file"></use>
                                </svg>
                                <svg class="fill-icon">
                                    <use href="../assets/svg/icon-sprite.svg#fill-file"></use>
                                </svg><span>My Projects</span></a></li>
                    @endcan
                </ul>
            </div>
            <div class="right-arrow" id="right-arrow"><i data-feather="arrow-right"></i></div>
        </nav>
    </div>
</div>
<!-- Page Sidebar Ends-->
