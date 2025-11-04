<div class="page-header row">
    <div class="header-logo-wrapper col-auto">
        <div class="logo-wrapper"><a href="index.html"><img class="img-fluid for-light"
                                                            src="{{url('assets/images/logo/logo.png')}}" alt=""/><img
                    class="img-fluid for-dark" src="{{url('assets/images/logo/logo_light.png')}}" alt=""/></a></div>
    </div>
    <div class="col-4 col-xl-4 page-title">
        <h4 class="f-w-700">{{isset($title) ? $title : ''}}</h4>
        <nav>
            <ol class="breadcrumb justify-content-sm-start align-items-center mb-0">
                <li class="breadcrumb-item"><a href=""> <i data-feather="home"> </i></a></li>
                <li class="breadcrumb-item f-w-400">Dashboard</li>
                <li class="breadcrumb-item f-w-400 active">Default</li>
            </ol>
        </nav>
    </div>
    <!-- Page Header Start-->
    <div class="header-wrapper col m-0">
        <div class="row">
            <form class="form-inline search-full col" action="#" method="get">
                <div class="form-group w-100">
                    <div class="Typeahead Typeahead--twitterUsers">
                        <div class="u-posRelative">
                            <input class="demo-input Typeahead-input form-control-plaintext w-100" type="text"
                                   placeholder="Search Mofi .." name="q" title="" autofocus>
                            <div class="spinner-border Typeahead-spinner" role="status"><span
                                    class="sr-only">Loading...</span></div>
                            <i class="close-search" data-feather="x"></i>
                        </div>
                        <div class="Typeahead-menu"></div>
                    </div>
                </div>
            </form>
            <div class="header-logo-wrapper col-auto p-0">
                <div class="logo-wrapper"><a href="index.html"><img class="img-fluid"
                                                                    src="{{url('assets/images/logo/logo.png')}}" alt=""></a>
                </div>
                <div class="toggle-sidebar">
                    <svg class="stroke-icon sidebar-toggle status_toggle middle">
                        <use href="{{url('assets/svg/icon-sprite.svg#toggle-icon')}}"></use>
                    </svg>
                </div>
            </div>
            <div class="nav-right col-xxl-8 col-xl-6 col-md-7 col-8 pull-right right-header p-0 ms-auto">
                <ul class="nav-menus">
                    <li>                         <span class="header-search">
                    <svg>
                      <use href="{{url('assets/svg/icon-sprite.svg#search')}}"></use>
                    </svg></span></li>
                    <li class="d-none">
                        <div class="form-group w-100">
                            <div class="Typeahead Typeahead--twitterUsers">
                                <div class="u-posRelative d-flex align-items-center">
                                    <svg class="search-bg svg-color">
                                        <use href="{{url('assets/svg/icon-sprite.svg#search')}}"></use>
                                    </svg>
                                    <input class="demo-input py-0 Typeahead-input form-control-plaintext w-100"
                                           type="text" placeholder="Search Mofi .." name="q" title="">
                                </div>
                            </div>
                        </div>
                    </li>
                    <li>
                        <div class="mode">
                            <svg>
                                <use href="{{url('assets/svg/icon-sprite.svg#moon')}}"></use>
                            </svg>
                        </div>
                    </li>
                    <li class="profile-nav onhover-dropdown pe-0 py-0">
                        <div class="media profile-media">

                            <div class="media-body"><span class="fw-bold">
                                    @php
                                        $user = \Illuminate\Support\Facades\Auth::user();
                                        $role = $user->getRoleNames()
                                    @endphp
                                    {{$user->name}}
                                </span>
                                <div>
                                    <a href="{{route("logout-route")}}">
                                        <button class=" btn-danger rounded-pill">Log Out</button>
                                    </a>
                                </div>

                                <p class="mb-0 font-roboto d-none">
                                    {{isset($role[0]) ? $role[0]: ""}}
                                    <i class="middle fa fa-angle-down"></i>
                                </p>
                            </div>
                        </div>
                        <ul class="d-none profile-dropdown onhover-show-div">
                            <li>
                                <a href="{{route("logout-route")}}"><i data-feather="log-in"> </i><span  class="text-white fw-bold">Log Out</span></a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
            <script class="result-template" type="text/x-handlebars-template">
                <div class="ProfileCard u-cf">
                    <div class="ProfileCard-avatar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             class="feather feather-airplay m-0">
                            <path d="M5 17H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-1"></path>
                            <polygon points="12 15 17 21 7 21 12 15"></polygon>
                        </svg>
                    </div>
                    <div class="ProfileCard-details">
                        {{--                        <div class="ProfileCard-realName">{{name}}</div>--}}
                        <div class="ProfileCard-realName">Name</div>
                    </div>
                </div>
            </script>
            <script class="empty-template" type="text/x-handlebars-template">
                <div class="EmptyMessage">Your search turned up 0 results. This most likely means the backend is down,
                    yikes!
                </div></script>
        </div>
    </div>
    <!-- Page Header Ends                              -->
</div>
