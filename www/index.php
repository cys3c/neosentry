<?php

include "../lib/_functions.php";
sessionStart();

//see if the user is logged in and if not, redirect to the login page
sessionProtect(); //if (!isLoggedIn()) {header("Location: login.php");}

//$jsonSession = json_encode($_SESSION);

?>


<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Neosentry NMS</title>
  <link rel="shortcut icon" href="/assets/images/favicon.png">
  <!-- Tell the browser to be responsive to screen width -->
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

  <!-- Styles -->
  <link rel="stylesheet" href="/assets/fonts/fonts.css" > <!-- Fonts: FontAwesome, Google Fonts  -->
  <link rel="stylesheet" href="/assets/plugins/bootstrap/css/bootstrap.min.css"> <!-- Bootstrap 3.3.6 -->
  <link rel="stylesheet" href="/assets/app-framework.min.css">  <!-- AdminLTE Theme style, skin-blue -->

  <!-- JS SCRIPTS -->
    <script src="/assets/plugins/jQuery/jquery-3.2.1.min.js"></script>
    <script src="/assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="/assets/plugins/angular/angular-all.min.js"></script> <!-- Angular Core & Routing -->
    <script src="/assets/app-framework.js"></script> <!-- AdminLTE App Framework, includes fastclick -->

    <script src="/assets/app.js"></script> <!-- Custom code for this app -->

  <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
  <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
  <!--[if lt IE 9]>
  <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
  <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
  <![endif]-->

  <base href="<?php echo str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'])); ?>"> <!-- For relative links -->
</head>
<!--
BODY TAG OPTIONS:
=================
Apply one or more of the following classes to get the desired effect
|---------------------------------------------------------|
|LAYOUT OPTIONS | fixed                                   |
|               | layout-boxed                            |
|               | layout-top-nav                          |
|               | sidebar-collapse                        |
|               | sidebar-mini                            |
|---------------------------------------------------------|
-->
<body class="hold-transition sidebar-mini" ng-app="neosentry" ng-controller="mainCtrl">
<div class="wrapper">

  <!-- Main Header -->
  <header class="main-header">

    <!-- Logo -->
    <a href="#" class="logo">
      <!-- mini logo for sidebar mini 50x50 pixels -->
      <span class="logo-mini"><img src="/assets/images/Lakitu-32.png" class="img-circle"/></span>
      <!-- logo for regular state and mobile devices -->
      <span class="logo-lg"><img src="/assets/images/Lakitu-32.png" class="img-circle"/> <b>Neosentry</b>NMS</span>
    </a>

    <!-- Header Navbar -->
    <nav class="navbar navbar-static-top" role="navigation">
      <!-- Sidebar toggle button-->
      <a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
        <span class="sr-only">Toggle navigation</span>
      </a>
      <!-- Navbar Right Menu -->
      <div class="navbar-custom-menu">
        <ul class="nav navbar-nav">

          <!-- Notifications Menu -->
          <li class="dropdown notifications-menu">
            <!-- Menu toggle button -->
            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
              <i class="fa fa-bell-o"></i>
              <span class="label label-warning">10</span>
            </a>
            <ul class="dropdown-menu">
              <li class="header">You have 10 alerts</li>
              <li>
                <!-- Inner Menu: contains the notifications -->
                <ul class="menu">
                  <li><!-- start notification -->
                    <a href="#">
                      <i class="fa fa-users text-aqua"></i> a new member joined today
                    </a>
                  </li>
                  <!-- end notification -->
                </ul>
              </li>
              <li class="footer"><a href="#">View all</a></li>
            </ul>
          </li>



          <!-- User Account Menu -->
          <li class="dropdown user user-menu">
            <!-- Menu Toggle Button -->
            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
              <!-- The user image in the navbar-->
              <img src="/assets/images/user.jpg" class="user-image" alt="">
              <!-- hidden-xs hides the username on small devices so only the image appears. -->
              <span class="hidden-xs"><?php echo $_SESSION['name'] ?></span>
            </a>
            <ul class="dropdown-menu">
              <!-- The user image in the menu -->
              <li class="user-header">
                <img src="/assets/images/user.jpg" class="img-circle" alt="">

                <p>
                  {{this.session.name}}
                  <!-- <small>Member since <?php echo ($_SESSION['created'] > 1)?date('F j, Y', $_SESSION['created']):"the dawn of time"; ?></small> -->
                  <small>Last logged in {{this.session['last_login'] * 1000 | date:"MMM dd, yyyy 'at' h:mma"}}</small> <!-- <?php echo date('F j, Y \a\t g:i a', $_SESSION['last_login']); ?> -->
                </p>
              </li>
              <!-- Menu Body 
              <li class="user-body">
                <div class="row">
                  <div class="col-xs-4 text-center">
                    <a href="#">Followers</a>
                  </div>
                  <div class="col-xs-4 text-center">
                    <a href="#">Sales</a>
                  </div>
                  <div class="col-xs-4 text-center">
                    <a href="#">Friends</a>
                  </div>
                </div>
              </li> -->
              <!-- Menu Footer-->
              <li class="user-footer">
                <div class="pull-left">
                  <a href="/profile" class="btn btn-default btn-flat">Profile</a>
                </div>
                <div class="pull-right">
                    <form action="/login.php" method="post">
                        <input type="hidden" name="action" value="logout">
                        <input type="submit" value="Sign out" class="btn btn-default btn-flat">
                    </form>

                </div>
              </li>
            </ul>
          </li>
          <!-- Control Sidebar Toggle Button -->
          <li>
            <a href="#" data-toggle="control-sidebar"><i class="fa fa-gears"></i></a>
          </li>
        </ul>
      </div>
    </nav>
  </header>
  <!-- Left side column. contains the logo and sidebar -->
  <aside class="main-sidebar">

    <!-- sidebar: style can be found in sidebar.less -->
    <section class="sidebar">

      <!-- search form -->
      <form action="#" method="get" class="sidebar-form">
        <div class="input-group">
          <input type="text" name="q" class="form-control" placeholder="Search...">
              <span class="input-group-btn">
                <button type="submit" name="search" id="search-btn" class="btn btn-flat"><i class="fa fa-search"></i>
                </button>
              </span>
        </div>
      </form>
      <!-- /.search form -->

      
      
      
      <!-- Sidebar Menu -->
      <ul class="sidebar-menu">
        <li class="header">DATA</li>
        <!-- Optionally, you can add icons to the links -->
        <li ng-class="{active: activeTab == 'dashboard'}"><a href="/dashboard"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a></li>
        <li ng-class="{active: activeTab == 'devices'}"><a href="/devices"><i class="fa fa-laptop"></i> <span>Devices</span></a></li>
				<li class="treeview">
					<a href="#"><i class="fa fa-magnet"></i> <span>Collectors</span>
						<span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
					</a>
					<ul class="treeview-menu">
						<li ng-class="{active: activeTab == 'col-ping'}"><a href="/collector/ping"><i class="fa fa-circle-o"></i> Ping / Traceroute</a></li>
						<li ng-class="{active: activeTab == 'col-snmp'}"><a href="/collector/snmp"><i class="fa fa-circle-o"></i> SNMP</a></li>
						<li ng-class="{active: activeTab == 'col-conf'}"><a href="/collector/configuration"><i class="fa fa-circle-o"></i> Configuration</a></li>
						<!-- <li><a href="/collector/ports"><i class="fa fa-circle-o"></i> Port Services</a></li>
						<li><a href="/collector/netflow"><i class="fa fa-circle-o"></i> Netflow</a></li>
						<li><a href="/collector/vulnerability"><i class="fa fa-circle-o"></i> Vulnerability Scans</a></li> -->
					</ul>
				</li>
				<li ng-class="{active: activeTab == 'logs'}"><a href="/logs"><i class="fa fa-book"></i> <span>Logs & Alerts</span></a></li>
				<li class="header">ADMINISTRATION</li>
        <li ng-class="{active: activeTab == 'settings'}"><a href="/settings"><i class="fa fa-sliders"></i> <span>Settings</span></a></li>
				<li ng-class="{active: activeTab == 'users'}"><a href="/users"><i class="fa fa-users"></i> <span>Users</span></a></li>

          <!-- SAMPLE
          <li class="treeview">
            <a href="#"><i class="fa fa-link"></i> <span>Multilevel</span>
              <span class="pull-right-container">
                <i class="fa fa-angle-left pull-right"></i>
              </span>
            </a>
            <ul class="treeview-menu">
              <li><a href="#">Link in level 2</a></li>
              <li><a href="#">Link in level 2</a></li>
            </ul>
          </li>
          -->
      </ul>
      <!-- /.sidebar-menu -->
    </section>
    <!-- /.sidebar -->
  </aside>

  <!-- Content Wrapper. Contains page content -->
  <div ng-view id="app-content" class="content-wrapper">
  </div>
  <!-- /.content-wrapper -->

  <!-- Main Footer -->
  <footer class="main-footer">
    <!-- To the right -->
    <div class="pull-right hidden-xs">
        <small>Version 0.1</small>
    </div>
    <!-- Default to the left -->
    <strong>Copyright &copy; 2016 <a href="#">Root Secure</a>.</strong> All rights reserved.
  </footer>


  <!-- Control Sidebar -->
  <aside class="control-sidebar control-sidebar-dark">


    <!-- Create the tabs
    <ul class="nav nav-tabs nav-justified control-sidebar-tabs">
      <li><a href="#control-sidebar-home-tab" data-toggle="tab"><i class="fa fa-home"></i></a></li>
      <li class="active"><a href="#" data-toggle="tab" ><i class="fa fa-gears"></i></a></li>
    </ul>
    -->
    <!-- Tab panes -->
    <div class="tab-content">
        <!-- For Tabs
      <div class="tab-pane" id="control-sidebar-home-tab">
      </div>
      <div class="tab-pane active" id="control-sidebar-settings-tab">
      </div>
        -->

      <!-- Settings tab content -->
      <div class="tab-pane active">
        <form method="post">
          <h3 class="control-sidebar-heading">General Settings</h3>

          <div class="form-group">
            <label class="control-sidebar-subheading">
              Report panel usage
              <input type="checkbox" class="pull-right" checked>
            </label>

            <p>
              Some information about this general settings option
            </p>
          </div>
          <!-- /.form-group -->

            <h3 class="control-sidebar-heading">Recent Activity</h3>
            <ul class="control-sidebar-menu">
                <li>
                    <a href="javascript::">
                        <i class="menu-icon fa fa-info bg-red"></i>
                        <div class="menu-info">
                            <h4 class="control-sidebar-subheading">Added 255.255.255.255</h4>
                            <p>Something else about this...</p>
                        </div>
                    </a>
                </li>
            </ul>
            <!-- /.control-sidebar-menu -->

            <h3 class="control-sidebar-heading">Current Tasks</h3>
            <ul class="control-sidebar-menu">
                <li>
                    <a href="">
                        <h4 class="control-sidebar-subheading">
                            Custom Template Design
                            <span class="pull-right-container">
                              <span class="label label-danger pull-right">70%</span>
                            </span>
                        </h4>

                        <div class="progress progress-xxs">
                            <div class="progress-bar progress-bar-danger" style="width: 70%"></div>
                        </div>
                    </a>
                </li>
            </ul>
            <!-- /.control-sidebar-menu -->

        </form>
      </div>
      <!-- /.tab-pane -->
    </div>
  </aside>
  <!-- /.control-sidebar -->
  <!-- Add the sidebar's background. This div must be placed
       immediately after the control sidebar -->
  <div class="control-sidebar-bg"></div>
</div>
<!-- ./wrapper -->




</body>
</html>
