<?php
# Defaults
$g_photo = $g_photo ?? '';
$g_fullname = $g_fullname ?? '';
$g_user_role = $g_user_role ?? '';
$g_user_role_display = str_replace('_', ' ', $g_user_role) ?? '';
?>

<div class="main-header">
    <div class="main-header-logo">
        <!-- Logo Header -->
        <div class="logo-header" data-background-color="dark">
            <a href="<?php echo BASE_URL . 'Admin/'; ?>" class="logo">
                <img src="<?php echo DISPLAY_LOGO; ?>" alt="navbar brand" class="navbar-brand" height="80%">
            </a>
            <div class="nav-toggle">
                <button class="btn btn-toggle toggle-sidebar">
                    <i class="gg-menu-right"></i>
                </button>
                <button class="btn btn-toggle sidenav-toggler">
                    <i class="gg-menu-left"></i>
                </button>
            </div>
            <button class="topbar-toggler more">
                <i class="gg-more-vertical-alt"></i>
            </button>
        </div>
        <!-- End Logo Header -->
    </div>

    <!-- Navbar Header -->
    <nav class="navbar navbar-header navbar-header-transparent navbar-expand-lg border-bottom">
        <div class="container-fluid">
            <nav class="navbar navbar-header-left navbar-expand-lg navbar-form nav-search p-0 d-none d-lg-flex">
                <!-- search bar [remove if not needed] -->
                <!-- <div class="input-group">
                    <div class="input-group-prepend">
                        <button type="submit" class="btn btn-search pe-1">
                            <i class="fa fa-search search-icon"></i>
                        </button>
                    </div>
                    <input type="text" placeholder="Search ..." class="form-control" />
                </div> -->
                <!-- date time -->
                <div class="mx-3">
                    <span class="op-7" id="now"></span>
                </div>
            </nav>

            <ul class="navbar-nav topbar-nav ms-md-auto align-items-center">
                <?php if (SYSTEM_FLAG === "DEV"): ?>
                    <!-- search bar [remove if not needed] -->
                    <!-- <li class="nav-item topbar-icon dropdown hidden-caret d-flex d-lg-none">
                        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false" aria-haspopup="true">
                            <i class="fa fa-search"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-search animated fadeIn">
                            <form class="navbar-left navbar-form nav-search">
                                <div class="input-group">
                                    <input type="text" placeholder="Search ..." class="form-control" />
                                </div>
                            </form>
                        </ul>
                    </li> -->

                    <!-- messages [remove if not needed] -->
                    <!-- <li class="nav-item topbar-icon dropdown hidden-caret">
                        <a class="nav-link dropdown-toggle" href="#" id="messageDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fa fa-envelope"></i>
                        </a>
                        <ul class="dropdown-menu messages-notif-box animated fadeIn" aria-labelledby="messageDropdown">
                            <li>
                                <div
                                    class="dropdown-title d-flex justify-content-between align-items-center">
                                    Messages
                                    <a href="#" class="small">Mark all as read</a>
                                </div>
                            </li>
                            <li>
                                <div class="message-notif-scroll scrollbar-outer">
                                    <div class="notif-center">
                                        <a href="#">
                                            <div class="notif-img">
                                                <img src="<?php echo $g_photo; ?>" alt="Img Profile" />
                                            </div>
                                            <div class="notif-content">
                                                <span class="subject">Jimmy Denis</span>
                                                <span class="block"> How are you ? </span>
                                                <span class="time">5 minutes ago</span>
                                            </div>
                                        </a>
                                        <a href="#">
                                            <div class="notif-img">
                                                <img
                                                    src="../assets/dashboard/img/chadengle.jpg"
                                                    alt="Img Profile" />
                                            </div>
                                            <div class="notif-content">
                                                <span class="subject">Chad</span>
                                                <span class="block"> Ok, Thanks ! </span>
                                                <span class="time">12 minutes ago</span>
                                            </div>
                                        </a>
                                        <a href="#">
                                            <div class="notif-img">
                                                <img
                                                    src="../assets/dashboard/img/mlane.jpg"
                                                    alt="Img Profile" />
                                            </div>
                                            <div class="notif-content">
                                                <span class="subject">Jhon Doe</span>
                                                <span class="block">
                                                    Ready for the meeting today...
                                                </span>
                                                <span class="time">12 minutes ago</span>
                                            </div>
                                        </a>
                                        <a href="#">
                                            <div class="notif-img">
                                                <img
                                                    src="../assets/dashboard/img/talha.jpg"
                                                    alt="Img Profile" />
                                            </div>
                                            <div class="notif-content">
                                                <span class="subject">Talha</span>
                                                <span class="block"> Hi, Apa Kabar ? </span>
                                                <span class="time">17 minutes ago</span>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </li>
                            <li>
                                <a class="see-all" href="javascript:void(0);">See all messages<i class="fa fa-angle-right"></i>
                                </a>
                            </li>
                        </ul>
                    </li> -->

                    <!-- notification [remove if not needed] -->
                    <!-- <li class="nav-item topbar-icon dropdown hidden-caret">
                        <a class="nav-link dropdown-toggle" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fa fa-bell"></i>
                            <span class="notification">4</span>
                        </a>
                        <ul class="dropdown-menu notif-box animated fadeIn" aria-labelledby="notifDropdown">
                            <li>
                                <div class="dropdown-title">
                                    You have 4 new notification
                                </div>
                            </li>
                            <li>
                                <div class="notif-scroll scrollbar-outer">
                                    <div class="notif-center">
                                        <a href="#">
                                            <div class="notif-icon notif-primary">
                                                <i class="fa fa-user-plus"></i>
                                            </div>
                                            <div class="notif-content">
                                                <span class="block"> New user registered </span>
                                                <span class="time">5 minutes ago</span>
                                            </div>
                                        </a>
                                        <a href="#">
                                            <div class="notif-icon notif-success">
                                                <i class="fa fa-comment"></i>
                                            </div>
                                            <div class="notif-content">
                                                <span class="block">
                                                    Rahmad commented on Admin
                                                </span>
                                                <span class="time">12 minutes ago</span>
                                            </div>
                                        </a>
                                        <a href="#">
                                            <div class="notif-img">
                                                <img src=""
                                                    alt="Img Profile" />
                                            </div>
                                            <div class="notif-content">
                                                <span class="block">
                                                    Reza send messages to you
                                                </span>
                                                <span class="time">12 minutes ago</span>
                                            </div>
                                        </a>
                                        <a href="#">
                                            <div class="notif-icon notif-danger">
                                                <i class="fa fa-heart"></i>
                                            </div>
                                            <div class="notif-content">
                                                <span class="block"> Farrah liked Admin </span>
                                                <span class="time">17 minutes ago</span>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </li>
                            <li>
                                <a class="see-all" href="javascript:void(0);">See all notifications<i class="fa fa-angle-right"></i>
                                </a>
                            </li>
                        </ul>
                    </li> -->

                    <!-- quick actions [remove if not needed] -->
                    <!-- <li class="nav-item topbar-icon dropdown hidden-caret">
                        <a class="nav-link" data-bs-toggle="dropdown" href="#" aria-expanded="false">
                            <i class="fas fa-layer-group"></i>
                        </a>
                        <div class="dropdown-menu quick-actions animated fadeIn">
                            <div class="quick-actions-header">
                                <span class="title mb-1">Quick Actions</span>
                                <span class="subtitle op-7">Shortcuts</span>
                            </div>
                            <div class="quick-actions-scroll scrollbar-outer">
                                <div class="quick-actions-items">
                                    <div class="row m-0">
                                        <a class="col-6 col-md-4 p-0" href="#">
                                            <div class="quick-actions-item">
                                                <div class="avatar-item bg-danger rounded-circle">
                                                    <i class="far fa-calendar-alt"></i>
                                                </div>
                                                <span class="text">Calendar</span>
                                            </div>
                                        </a>
                                        <a class="col-6 col-md-4 p-0" href="#">
                                            <div class="quick-actions-item">
                                                <div
                                                    class="avatar-item bg-warning rounded-circle">
                                                    <i class="fas fa-map"></i>
                                                </div>
                                                <span class="text">Maps</span>
                                            </div>
                                        </a>
                                        <a class="col-6 col-md-4 p-0" href="#">
                                            <div class="quick-actions-item">
                                                <div class="avatar-item bg-info rounded-circle">
                                                    <i class="fas fa-file-excel"></i>
                                                </div>
                                                <span class="text">Reports</span>
                                            </div>
                                        </a>
                                        <a class="col-6 col-md-4 p-0" href="#">
                                            <div class="quick-actions-item">
                                                <div
                                                    class="avatar-item bg-success rounded-circle">
                                                    <i class="fas fa-envelope"></i>
                                                </div>
                                                <span class="text">Emails</span>
                                            </div>
                                        </a>
                                        <a class="col-6 col-md-4 p-0" href="#">
                                            <div class="quick-actions-item">
                                                <div
                                                    class="avatar-item bg-primary rounded-circle">
                                                    <i class="fas fa-file-invoice-dollar"></i>
                                                </div>
                                                <span class="text">Invoice</span>
                                            </div>
                                        </a>
                                        <a class="col-6 col-md-4 p-0" href="#">
                                            <div class="quick-actions-item">
                                                <div
                                                    class="avatar-item bg-secondary rounded-circle">
                                                    <i class="fas fa-credit-card"></i>
                                                </div>
                                                <span class="text">Payments</span>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li> -->
                <?php endif; ?>

                <!-- profile -->
                <li class="nav-item topbar-user dropdown hidden-caret">
                    <!-- image, name, position -->
                    <a class="dropdown-toggle profile-pic" data-bs-toggle="dropdown" href="#" aria-expanded="false">
                        <div class="avatar-sm">
                            <!-- Avatar Image -->
                            <img id="navHeaderProfilePic" src="<?= !empty($g_photo) ? BASE_UPLOAD_PROFILE_USER_PATH . htmlspecialchars($g_photo, ENT_QUOTES, 'UTF-8') : '' ?>"
                                alt="Profile"
                                class="rounded-circle <?= empty($g_photo) ? 'd-none' : 'd-block' ?>"
                                style="width: 100%; height: 100%; object-fit: cover; aspect-ratio: 1 / 1;"
                                onerror="this.classList.remove('d-block'); this.classList.add('d-none'); document.getElementById('navHeaderInitialsFallback').classList.remove('d-none'); document.getElementById('navHeaderInitialsFallback').classList.add('d-flex');">

                            <!-- Initials Fallback -->
                            <div id="navHeaderInitialsFallback"
                                class="dp-initials rounded-circle align-items-center justify-content-center fw-bold text-white <?= !empty($g_photo) ? 'd-none' : 'd-flex' ?>"
                                style="width: 100%; height: 100%; background-color: #08184a; font-size: 0.95rem; aspect-ratio: 1 / 1;">
                                <?= htmlspecialchars($g_initials ?? 'U', ENT_QUOTES, 'UTF-8') ?>
                            </div>

                            <!-- <img src="<?php echo $g_photo; ?>" alt="..." class="avatar-img rounded-circle"> -->
                        </div>
                        <span class="profile-username">
                            <span class="fw-bold"><?php echo $g_fullname; ?></span><br>
                            <span class="op-7"><?php echo $g_user_role_display; ?></span>
                        </span>
                    </a>
                    <!-- image, name, position, profile btn, sign out btn -->
                    <ul class="dropdown-menu dropdown-user animated fadeIn">
                        <div class="dropdown-user-scroll scrollbar-outer">
                            <li>
                                <div class="user-box">
                                    <div class="u-text">
                                        <h4><?php echo $g_fullname; ?></h4>
                                        <p class="text-muted"><?php echo $g_user_role_display; ?></p>
                                    </div>
                                </div>
                            </li>
                            <li>
                                <?php if (SYSTEM_FLAG === "DEV"): ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#">My Profile</a>
                                <?php endif; ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>Admin/Sign-Out">Sign out</a>
                            </li>
                        </div>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
    <!-- End Navbar -->
</div>