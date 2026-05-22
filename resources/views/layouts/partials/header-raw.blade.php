<link href="{{ asset('assets/css/authentiq-modals.css') }}" rel="stylesheet" type="text/css">
<link href="{{ asset('assets/css/authentiq-fab.css') }}" rel="stylesheet" type="text/css">
<style>
    .side-nav-link:hover iconify-icon {
        color: #6b87fc;
        transition: color 0.1s;
    }

</style>
<script>window.AUTHENTIQ_USER_ROLE = @json($user['role'] ?? '');</script>
<!-- Sidenav Menu Start -->
<div class="sidenav-menu" style="border-radius: 35px!important;">

<!-- Brand Logo -->
<a href="{{ url('/dashboard') }}" class="logo">
    <span class="logo-light">
        <span class="logo-lg"><img src="assets/images/logo-auth03.png" alt="logo"></span>
        <span class="logo-sm"><img src="assets/images/logo-auth03.png" alt="small logo"></span>
    </span>

    <span class="logo-dark">
        <span class="logo-lg"><img src="assets/images/logo-auth03.png" alt="dark logo"></span>
        <span class="logo-sm"><img src="assets/images/logo-auth03.png" alt="small logo"></span>
    </span>
</a>

<!-- Sidebar Hover Menu Toggle Button -->
<button class="button-sm-hover">
    <i class="ti ti-circle align-middle"></i>
</button>

<!-- Full Sidebar Menu Close Button -->
<button class="button-close-fullsidebar">
    <i class="ti ti-x align-middle"></i>
</button>

<div data-simplebar>

    <!--- Sidenav Menu -->
    <ul class="side-nav">
        <li class="side-nav-title">Menu</li>

        <li class="side-nav-item">
            <a href="{{ url('/accueil') }}" class="side-nav-link">
                <span class="menu-icon">
                    <iconify-icon icon="solar:home-angle-bold-duotone"></iconify-icon>
                </span>
                <span class="menu-text"> Accueil </span>
            </a>
        </li>

        <li class="side-nav-item">
            <a href="{{ url('/encodage-document') }}" class="side-nav-link">
                <span class="menu-icon">
                    <iconify-icon icon="solar:printer-minimalistic-bold-duotone"></iconify-icon>
                </span>
                <span class="menu-text"> Encoder docs </span>
            </a>
        </li>

        <li class="side-nav-item">
            <a href="{{ url('/gestion-clients') }}" class="side-nav-link{{ request()->routeIs('clients.index') ? ' active' : '' }}">
                <span class="menu-icon">
                    <iconify-icon icon="solar:user-plus-bold-duotone"></iconify-icon>
                </span>
                <span class="menu-text"> Nouveau client </span>
            </a>
        </li>

        <li class="side-nav-item">
            <a href="{{ route('documents.library') }}" class="side-nav-link{{ request()->routeIs('documents.library') ? ' active' : '' }}">
                <span class="menu-icon">
                    <iconify-icon icon="solar:folder-with-files-bold-duotone"></iconify-icon>
                </span>
                <span class="menu-text"> Documents </span>
            </a>
        </li>

        <!-- <li class="side-nav-item">
            <a href="#!" class="side-nav-link">
                <span class="menu-icon">
                    <iconify-icon icon="solar:users-group-rounded-line-duotone"></iconify-icon>
                </span>
                <span class="menu-text"> Afficher clients </span>
            </a>
        </li> -->


        <li class="side-nav-title mt-2">Rapports</li>

        <li class="side-nav-item">
            <a href="{{ route('reports.daily') }}" class="side-nav-link{{ request()->routeIs('reports.daily') ? ' active' : '' }}">
                <span class="menu-icon">
                    <iconify-icon icon="solar:chart-square-line-duotone"></iconify-icon>
                </span>
                <span class="menu-text"> Journalier</span>
            </a>
        </li>

        <li class="side-nav-item">
            <a href="{{ route('reports.monthly') }}" class="side-nav-link{{ request()->routeIs('reports.monthly') ? ' active' : '' }}">
                <span class="menu-icon">
                    <iconify-icon icon="solar:pie-chart-2-line-duotone"></iconify-icon>
                </span>
                <span class="menu-text"> Mensuel</span>
            </a>
        </li>

        @if(($user['role'] ?? '') === 'admin')
        <li class="side-nav-item">
            <a href="{{ route('reports.global') }}" class="side-nav-link{{ request()->routeIs('reports.global') ? ' active' : '' }}">
                <span class="menu-icon">
                    <iconify-icon icon="solar:pie-chart-2-bold-duotone"></iconify-icon>
                </span>
                <span class="menu-text"> Global</span>
            </a>
        </li>
        @endif

        <li class="side-nav-title mt-2">Options</li>

        <li class="side-nav-item">
            <a href="{{ url('/mon-profil') }}" class="side-nav-link">
                <span class="menu-icon">
                    <iconify-icon icon="solar:shield-user-line-duotone"></iconify-icon>
                </span>
                <span class="menu-text"> Mon profil</span>
            </a>
        </li>


        @if(($user['role'] ?? '') === 'admin')
        <li class="side-nav-item">
            <a href="{{ url('/gestion-utilisateurs') }}" class="side-nav-link{{ request()->routeIs('users.index') ? ' active' : '' }}">
                <span class="menu-icon">
                    <iconify-icon icon="solar:users-group-rounded-bold-duotone"></iconify-icon>
                </span>
                <span class="menu-text"> Users</span>
            </a>
        </li>

        <li class="side-nav-item">
            <a href="{{ url('/documents') }}" class="side-nav-link{{ request()->routeIs('docs.index') ? ' active' : '' }}">
                <span class="menu-icon">
                    <iconify-icon icon="solar:folder-with-files-bold-duotone"></iconify-icon>
                </span>
                <span class="menu-text"> Types de docs</span>
            </a>
        </li>

        <li class="side-nav-item">
            <a href="{{ url('/maisons-communales') }}" class="side-nav-link{{ request()->routeIs('communes.index') ? ' active' : '' }}">
                <span class="menu-icon">
                    <iconify-icon icon="solar:map-point-hospital-bold-duotone"></iconify-icon>
                </span>
                <span class="menu-text"> communes</span>
            </a>
        </li>

        <li class="side-nav-item">
            <a href="{{ url('/regions-villes') }}" class="side-nav-link{{ request()->routeIs('villes.index') ? ' active' : '' }}">
                <span class="menu-icon">
                    <iconify-icon icon="solar:point-on-map-bold-duotone"></iconify-icon>
                </span>
                <span class="menu-text"> Regions/ville</span>
            </a>
        </li>
        @endif

        <li class="side-nav-item">
            <a href="#!" class="side-nav-link">
                <span class="menu-icon">
                    <iconify-icon icon="solar:lightbulb-bolt-bold-duotone"></iconify-icon>
                </span>
                <span class="menu-text"> Supports</span>
            </a>
        </li>

        <li class="side-nav-item">
            <a href="{{ url('/deconnexion') }}" class="side-nav-link">
                <span class="menu-icon">
                    <iconify-icon icon="solar:logout-3-bold-duotone"></iconify-icon>
                </span>
                <span class="menu-text"> Se déconnecter</span>
            </a>
        </li>



    </ul>

    <div class="clearfix"></div>
</div>
</div>
<!-- Sidenav Menu End -->


<!-- Topbar Start -->
<header class="app-topbar">
<div class="page-container topbar-menu">
    <div class="d-flex align-items-center gap-2">

        <!-- Brand Logo -->
        <a href="{{ url('/dashboard') }}" class="logo">
            <span class="logo-light">
                <span class="logo-lg">
                    <img src="assets/images/logo-h.png" alt="logo">
                </span>
                <span class="logo-sm">
                    <img src="assets/images/logo-h.png" alt="small logo">
                </span>
            </span>

            <span class="logo-dark">
                <span class="logo-lg">
                    <img src="assets/images/logo-h-l.png" alt="dark logo">
                </span>
                <span class="logo-sm">
                    <img src="assets/images/logo-h-l.png" alt="small logo">
                </span>
            </span>
        </a>

        <!-- Sidebar Menu Toggle Button -->
        <button class="sidenav-toggle-button px-2">
            <i class="ti ti-menu-deep fs-24"></i>
        </button>

        <!-- Horizontal Menu Toggle Button -->
        <button class="topnav-toggle-button px-2" data-bs-toggle="collapse" data-bs-target="#topnav-menu-content">
            <i class="ti ti-menu-deep fs-22"></i>
        </button>

        <!-- Button Trigger Search Modal -->
        <div class="topbar-search text-muted d-none d-xl-flex gap-2 align-items-center" data-bs-toggle="modal" data-bs-target="#searchModal" type="button">
            <i class="ti ti-search fs-18"></i>
            <span class="me-2">Recherchez...</span>
            <span class="ms-auto fw-medium">⌘K</span>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">

        <!-- Search for small devices -->
        <div class="topbar-item d-flex d-xl-none">
            <button class="topbar-link" data-bs-toggle="modal" data-bs-target="#searchModal" type="button">
                <i class="ti ti-search fs-22"></i>
            </button>
        </div>

        

        <!-- Notification Dropdown -->
        <div class="topbar-item">
            <div class="dropdown">
                <button class="topbar-link dropdown-toggle drop-arrow-none" data-bs-toggle="dropdown" data-bs-offset="0,25" type="button" data-bs-auto-close="outside" aria-haspopup="false" aria-expanded="false">
                    <i class="ti ti-bell animate-ring fs-22"></i>
                    <span class="noti-icon-badge"></span>
                </button>

                <div class="dropdown-menu p-0 dropdown-menu-end dropdown-menu-lg" style="min-height: 300px;">
                    <div class="p-3 border-bottom border-dashed">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="m-0 fs-16 fw-semibold"> Notifications</h6>
                            </div>
                            <div class="col-auto">
                                <div class="dropdown">
                                    <a href="#" class="dropdown-toggle drop-arrow-none link-dark" data-bs-toggle="dropdown" data-bs-offset="0,15" aria-expanded="false">
                                        <i class="ti ti-settings fs-22 align-middle"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <!-- item-->
                                        <a href="javascript:void(0);" class="dropdown-item">Mark as Read</a>
                                        <!-- item-->
                                        <a href="javascript:void(0);" class="dropdown-item">Delete All</a>
                                        <!-- item-->
                                        <a href="javascript:void(0);" class="dropdown-item">Do not Disturb</a>
                                        <!-- item-->
                                        <a href="javascript:void(0);" class="dropdown-item">Other Settings</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="position-relative z-2 card shadow-none rounded-0" style="max-height: 300px;" data-simplebar>
                        <!-- item-->
                        <div class="dropdown-item notification-item py-2 text-wrap active" id="notification-1">
                            <span class="d-flex align-items-center">
                                <span class="me-3 position-relative flex-shrink-0">
                                    <img src="assets/images/users/avatar-2.jpg" class="avatar-md rounded-circle" alt="" />
                                    <span class="position-absolute rounded-pill bg-danger notification-badge">
                                        <i class="ti ti-message-circle"></i>
                                        <span class="visually-hidden">unread messages</span>
                                    </span>
                                </span>
                                <span class="flex-grow-1 text-muted">
                                    <span class="fw-medium text-body">Glady Haid</span> commented on <span class="fw-medium text-body">paces admin status</span>
                                    <br />
                                    <span class="fs-12">25m ago</span>
                                </span>
                                <span class="notification-item-close">
                                    <button type="button" class="btn btn-ghost-danger rounded-circle btn-sm btn-icon" data-dismissible="#notification-1">
                                        <i class="ti ti-x fs-16"></i>
                                    </button>
                                </span>
                            </span>
                        </div>

                        <!-- item-->
                        <div class="dropdown-item notification-item py-2 text-wrap" id="notification-2">
                            <span class="d-flex align-items-center">
                                <span class="me-3 position-relative flex-shrink-0">
                                    <img src="assets/images/users/avatar-4.jpg" class="avatar-md rounded-circle" alt="" />
                                    <span class="position-absolute rounded-pill bg-info notification-badge">
                                        <i class="ti ti-currency-dollar"></i>
                                        <span class="visually-hidden">unread messages</span>
                                    </span>
                                </span>
                                <span class="flex-grow-1 text-muted">
                                    <span class="fw-medium text-body">Tommy Berry</span> donated <span class="text-success">$100.00</span> for <span class="fw-medium text-body">Carbon removal program</span>
                                    <br />
                                    <span class="fs-12">58m ago</span>
                                </span>
                                <span class="notification-item-close">
                                    <button type="button" class="btn btn-ghost-danger rounded-circle btn-sm btn-icon" data-dismissible="#notification-2">
                                        <i class="ti ti-x fs-16"></i>
                                    </button>
                                </span>
                            </span>
                        </div>

                        <!-- item-->
                        <div class="dropdown-item notification-item py-2 text-wrap" id="notification-3">
                            <span class="d-flex align-items-center">
                                <div class="avatar-md flex-shrink-0 me-3">
                                    <span class="avatar-title bg-success-subtle text-success rounded-circle fs-22">
                                        <iconify-icon icon="solar:wallet-money-bold-duotone"></iconify-icon>
                                    </span>
                                </div>
                                <span class="flex-grow-1 text-muted">
                                    You withdraw a <span class="fw-medium text-body">$500</span> by <span class="fw-medium text-body">New York ATM</span>
                                    <br />
                                    <span class="fs-12">2h ago</span>
                                </span>
                                <span class="notification-item-close">
                                    <button type="button" class="btn btn-ghost-danger rounded-circle btn-sm btn-icon" data-dismissible="#notification-3">
                                        <i class="ti ti-x fs-16"></i>
                                    </button>
                                </span>
                            </span>
                        </div>

                        <!-- item-->
                        <div class="dropdown-item notification-item py-2 text-wrap" id="notification-4">
                            <span class="d-flex align-items-center">
                                <span class="me-3 position-relative flex-shrink-0">
                                    <img src="assets/images/users/avatar-7.jpg" class="avatar-md rounded-circle" alt="" />
                                    <span class="position-absolute rounded-pill bg-secondary notification-badge">
                                        <i class="ti ti-plus"></i>
                                        <span class="visually-hidden">unread messages</span>
                                    </span>
                                </span>
                                <span class="flex-grow-1 text-muted">
                                    <span class="fw-medium text-body">Richard Allen</span> followed you in <span class="fw-medium text-body">Facebook</span>
                                    <br />
                                    <span class="fs-12">3h ago</span>
                                </span>
                                <span class="notification-item-close">
                                    <button type="button" class="btn btn-ghost-danger rounded-circle btn-sm btn-icon" data-dismissible="#notification-4">
                                        <i class="ti ti-x fs-16"></i>
                                    </button>
                                </span>
                            </span>
                        </div>

                        <!-- item-->
                        <div class="dropdown-item notification-item py-2 text-wrap" id="notification-5">
                            <span class="d-flex align-items-center">
                                <span class="me-3 position-relative flex-shrink-0">
                                    <img src="assets/images/users/avatar-10.jpg" class="avatar-md rounded-circle" alt="" />
                                    <span class="position-absolute rounded-pill bg-danger notification-badge">
                                        <i class="ti ti-heart-filled"></i>
                                        <span class="visually-hidden">unread messages</span>
                                    </span>
                                </span>
                                <span class="flex-grow-1 text-muted">
                                    <span class="fw-medium text-body">Victor Collier</span> liked you recent photo in <span class="fw-medium text-body">Instagram</span>
                                    <br />
                                    <span class="fs-12">10h ago</span>
                                </span>
                                <span class="notification-item-close">
                                    <button type="button" class="btn btn-ghost-danger rounded-circle btn-sm btn-icon" data-dismissible="#notification-5">
                                        <i class="ti ti-x fs-16"></i>
                                    </button>
                                </span>
                            </span>
                        </div>
                    </div>

                    <div style="height: 300px;" class="d-flex align-items-center justify-content-center text-center position-absolute top-0 bottom-0 start-0 end-0 z-1">
                        <div>
                            <iconify-icon icon="line-md:bell-twotone-alert-loop" class="fs-80 text-secondary mt-2"></iconify-icon>
                            <h4 class="fw-semibold mb-0 fst-italic lh-base mt-3">Hey! 👋 <br />You have no any notifications</h4>
                        </div>
                    </div>

                    <!-- All-->
                    <a href="javascript:void(0);" class="dropdown-item notification-item position-fixed z-2 bottom-0 text-center text-reset text-decoration-underline link-offset-2 fw-bold notify-item border-top border-light py-2">
                        View All
                    </a>
                </div>
            </div>
        </div>


        <!-- Light/Dark Mode Button -->
        <div class="topbar-item d-none d-sm-flex">
            <button class="topbar-link" id="light-dark-mode" type="button">
                <i class="ti ti-moon fs-22"></i>
            </button>
        </div>

        <!-- User Dropdown -->
        <div class="topbar-item nav-user">
            <div class="dropdown">
                <a class="topbar-link dropdown-toggle drop-arrow-none px-2" data-bs-toggle="dropdown" data-bs-offset="0,19" type="button" aria-haspopup="false" aria-expanded="false">
                    <img id="userProfileImage" src="{{ $user['photo_url'] ?? asset('assets/images/user.jpg') }}" width="32" class="rounded-circle me-lg-2 d-flex" alt="user-image">
                    <span class="d-lg-flex flex-column gap-1 d-none">
                        <h5 class="my-0">
                            {{ $user['nom_complet'] ?? '' }}
                        </h5>
                        <h6 class="my-0 fw-normal">{{ $user['affectation'] ?? '' }}</h6>
                    </span>
                    <i class="ti ti-chevron-down d-none d-lg-block align-middle ms-2"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end">

                    <!-- item-->
                    <a href="javascript:void(0);" id="openSettingsBtn" class="dropdown-item">
                        <iconify-icon class="me-1 fs-17 align-middle" icon="solar:settings-bold-duotone"></iconify-icon>
                        <span class="align-middle">Paramètres</span>
                    </a>

                    <!-- item-->
                    <a href="javascript:void(0);" class="dropdown-item">
                        <iconify-icon class="me-1 fs-17 align-middle" icon="solar:lightbulb-bolt-bold-duotone"></iconify-icon>
                        <span class="align-middle">Supports</span>
                    </a>

                    <!-- item-->
                    <a href="{{ url('/deconnexion') }}" class="dropdown-item fw-semibold text-danger">
                        <iconify-icon class="me-1 fs-17 align-middle" icon="solar:logout-3-bold-duotone"></iconify-icon>
                        <span class="align-middle">Se déconnecter</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
</header>
<!-- Topbar End -->

<!-- Search Modal -->
<div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
<div class="modal-dialog modal-lg">
    <div class="modal-content bg-transparent">
        <div class="card mb-1">
            <div class="px-3 py-2 d-flex flex-row align-items-center" id="top-search">
                <i class="ti ti-search fs-22"></i>
                <input type="search" class="form-control border-0" id="search-modal-input" placeholder="Search for actions, people,">
                <button type="button" class="btn p-0" data-bs-dismiss="modal" aria-label="Close">[esc]</button>
            </div>
        </div>
    </div>
</div>
</div>


<!-- Modal Settings — mot de passe -->
<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:35px!important">
            <div class="modal-header text-bg-primary border-0" style="border-top-left-radius:35px!important;border-top-right-radius:35px!important">
                <h5 class="modal-title" id="settingsModalLabel">
                    Modifier les paramètres
                    <br> 
                    <span class="fw-normal" style="font-size: .8em;">Changer votre mot de passe</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="settingsForm">
                    <!-- Champ pour le mot de passe actuel -->
                    <div class="mb-3 px-3">
                        <div class="form-floating">
                            <input type="password" id="currentPassword" class="form-control" placeholder="Mot de passe actuel">
                            <label for="currentPassword" class="form-label">Mot de passe actuel</label>
                        </div>
                    </div>

                    <!-- Champ pour le nouveau mot de passe -->
                    <div class="mb-3 px-3">
                        <div class="form-floating">
                            <input type="password" id="newPassword" class="form-control" placeholder="Nouveau mot de passe">
                            <label for="newPassword" class="form-label">Nouveau mot de passe</label>
                        </div>
                    </div>

                    <!-- Champ pour confirmer le nouveau mot de passe -->
                    <div class="mb-3 px-3">
                        <div class="form-floating">
                            <input type="password" id="confirmPassword" class="form-control" placeholder="Confirmer le mot de passe">
                            <label for="confirmPassword" class="form-label">Confirmer le mot de passe</label>
                        </div>
                    </div>

                    <div class="modal-footer border-0">
                       <a class="btn btn-light btn-lg fw-semibold" data-bs-dismiss="modal" style="border-radius:12px">Close</a>
                       <button type="submit" id="saveSettingsBtn" class="btn btn-primary fw-semibold btn-lg" style="border-radius:12px">Enregistrer</button>
                   </div>
                </form>
            </div>
        </div>
    </div>
</div>