<?php

include "../lib/_functions.php";
sessionStart();



$redirectUrl = dirname($_SERVER['SCRIPT_NAME']); //this will go to the default file (index.php)
if (array_key_exists('previous_location',$_SESSION) && $_SESSION['previous_location'] != '') $redirectUrl = $_SESSION['previous_location'];

$alert = '';
$url = trim(substr(filter_input(INPUT_POST, 'url', FILTER_SANITIZE_STRING),0,512)); //the url to return the user to if login succeeds
$format = trim(substr(filter_input(INPUT_POST, 'format', FILTER_SANITIZE_STRING),0,4));
$action = trim(substr(filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING),0,10));
$username = trim(substr(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_EMAIL),0,64));
$pass = trim(substr(filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING),0,64));
$remember = filter_input(INPUT_POST, 'remember', FILTER_SANITIZE_STRING)==''?false:true;

//the get statements
if ($action=='') $action = trim(substr(filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING),0,10));


if ($action == "login") {
    $loginRet = sessionLogin($username, $pass, $remember);
    if ($loginRet===true) {
        //login SUCCESS

        //clear the previous_location session variable
        unset($_SESSION['previous_location']);

        //return success or redirect
        if ($format=='ajax') {
            return true;
        } else {
            $alert = '<div class="alert alert-success"><button type="button" class="close" data-dismiss="alert" aria-hidden="true"><small>x</small></button>
            <strong>Success: </strong>Login succeeded.</div>';
            header("Location: $redirectUrl");
            exit;
        }
    } else {
        //login FAILED
        if ($format=='ajax') {
            return false;
        } else {
            $alert = '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert" aria-hidden="true"><small>x</small></button>
            <strong>Error: </strong>' . $loginRet . '</div>';
        }
    }

} elseif ($action == "logout") {
    sessionDestroy();
    $alert = '<div class="alert alert-success"><button type="button" class="close" data-dismiss="alert" aria-hidden="true"><small>x</small></button>
            <strong>Goodbye!</strong> You have been successfully logged out.</div>';
}

//see if the user is logged in and redirect to the app
if (isLoggedIn()) {header("Location: $redirectUrl");}

/*
print_r($_SESSION);
print_r(session_get_cookie_params());
print_r($_SERVER);
*/
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>NeoSentry Login</title>
        <link rel="shortcut icon" href="assets/images/favicon.png">

        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">  
        <meta name="description" content="">
        <meta name="author" content="">

        <!-- Styles -->
        <link rel="stylesheet" href="assets/fonts/fonts.css" > <!-- Fonts: FontAwesome, Google Fonts  -->
        <link rel="stylesheet" href="assets/plugins/bootstrap/css/bootstrap.min.css"> <!-- Bootstrap 3.3.6 -->
        <link rel="stylesheet" href="assets/app.min.css?v=124">  <!-- AdminLTE Theme style, skin-blue -->
        
        <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
          <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->

        <!-- Custom Style -->
        <style>body {background-image: url('assets/images/login-bg-blur.jpg'); background-size: cover; -webkit-background-size: cover; -moz-background-size: cover; -o-background-size: cover;}</style>

    </head>
    <body class="">


        <div class="login-box">
            <div class="login-logo">
                <a href="#"><b>Neosentry</b>NMS</a>
            </div>

            <!-- Warn the user if cookies aren't enabled -->
            <script>
                if (!navigator.cookieEnabled) {
                    document.write('<div class="alert alert-warning"><button type="button" class="close" data-dismiss="alert" aria-hidden="true"><small>x</small></button>'
                        +'<strong>Warning: </strong>Cookies appear to be disabled. Please enable them to fully utilize the sign-in features.</div>');
                }
            </script>
            <div id="alert">
                <?php if ($alert!="") {echo $alert;} ?>
            </div>

            <!-- /.login-logo -->
            <div class="login-box-body">
                <p class="login-box-msg">Sign in to start your session</p>

                <form name="loginform" role="form" action="login.php" method="post">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group has-feedback" ng-class="{ 'has-error': form.username.$dirty && form.username.$error.required }">
                        <input type="text" class="form-control" name="username" id="username" placeholder="Login ID" required="required">
                        <span class="glyphicon glyphicon-user form-control-feedback"></span>
                    </div>
                    <div class="form-group has-feedback" ng-class="{ 'has-error': form.password.$dirty && form.password.$error.required }">
                        <input type="password" class="form-control" name="password" id="password" placeholder="Password" required="required">
                        <span class="glyphicon glyphicon-lock form-control-feedback"></span>
                    </div>
                    <div class="row">
                        <div class="col-xs-8">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="remember"> Remember Me
                                </label>
                            </div>
                        </div>
                        <!-- /.col -->
                        <div class="col-xs-4">
                            <button type="submit" class="btn btn-primary btn-block btn-flat" ng-disabled="form.$invalid || vm.dataLoading">Sign In</button>

                        </div>
                        <!-- /.col -->
                    </div>
                </form>

                <!-- /.social-auth-links -->

                <!-- <a href="#">I forgot my password</a><br>
                <a href="#!/register" class="text-center">Register a new membership</a> -->

            </div>
            <!-- /.login-box-body -->
        </div>
        <!-- /.login-box -->

        
        <!-- JS SCRIPTS -->
        <script src="assets/plugins/jQuery/jquery-2.2.4.min.js"></script>
        <script src="assets/plugins/bootstrap/js/bootstrap.min.js"></script>
        <script src="assets/plugins/angular/angular-all.min.js"></script> <!-- Angular Core & Routing -->
        <!-- <script src="assets/app-framework.js"></script> AdminLTE App Framework, includes fastclick and slimscroll-->

        <script src="assets/app.js"></script> <!-- Custom code for this app -->

        
    </body>
</html>