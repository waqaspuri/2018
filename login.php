<?php
require_once __DIR__ . "/php/session-bootstrap.php";
require_once __DIR__ . "/php/connect-mysql.php";
require_once __DIR__ . "/php/admin_auth.php";
$error = adminProcessLogin($conn, $siteUrl);
$pendingOtp = $_SESSION['admin_login_pending'] ?? null;
?>
<!doctype html>
<html class="fixed">
<head>
<title><?=$siteName;?></title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="http://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800%7CShadows+Into+Light" rel="stylesheet" type="text/css">
<link rel="stylesheet" href="<?=$siteUrl;?>assets/vendor/bootstrap/css/bootstrap.css" />
<link rel="stylesheet" href="<?=$siteUrl;?>assets/vendor/font-awesome/css/font-awesome.css" />
<link rel="stylesheet" href="<?=$siteUrl;?>assets/vendor/magnific-popup/magnific-popup.css" />
<link rel="stylesheet" href="<?=$siteUrl;?>assets/vendor/bootstrap-datepicker/css/datepicker3.css" />
<link rel="stylesheet" href="<?=$siteUrl;?>assets/stylesheets/theme.css" />
<link rel="stylesheet" href="<?=$siteUrl;?>assets/stylesheets/theme-custom1.1.css">
<script src="<?=$siteUrl;?>assets/vendor/modernizr/modernizr.js"></script>
</head>
<body>
<section class="body-sign">
<div class="center-sign"> <a href="index.php" class="logo pull-left"> <img src="<?=$siteUrl;?>assets/images/logo.png" height="54" alt="Admin" /> </a>
<div class="panel panel-sign">
<div class="panel-title-sign mt-xl text-right" >
<h2 class="title text-uppercase text-bold m-none"><i class="fa fa-user mr-xs"></i> Sign In</h2>
</div>
<div class="panel-body">
<?php if ($error !== ''): ?><div class="alert alert-danger"><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
<?php if (is_array($pendingOtp)): ?>
<p>Enter the six-digit verification code sent to <?=htmlspecialchars(adminMaskedEmail($pendingOtp['email']), ENT_QUOTES, 'UTF-8')?>.</p>
<form action="<?=htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8')?>" method="post">
<input type="hidden" name="action" value="verify_otp"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['admin_login_csrf'], ENT_QUOTES, 'UTF-8')?>">
<div class="form-group mb-lg"><label>Email verification code</label><input name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" class="form-control input-lg" autocomplete="one-time-code" required autofocus></div>
<button type="submit" class="btn btn-primary btn-block btn-lg">Verify and Sign In</button>
</form>
<form action="<?=htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8')?>" method="post" style="margin-top:10px;">
<input type="hidden" name="action" value="cancel_otp"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['admin_login_csrf'], ENT_QUOTES, 'UTF-8')?>">
<button type="submit" class="btn btn-default btn-block">Cancel</button></form>
<?php else: ?>
<form action="<?=htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8')?>" method="post"><input type="hidden" name="action" value="login"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['admin_login_csrf'], ENT_QUOTES, 'UTF-8')?>">
<div class="form-group mb-lg">
<label>Username</label>
<div class="input-group input-group-icon">
<input name="username" type="text" class="form-control input-lg" autocomplete="username" required />
<span class="input-group-addon"> <span class="icon icon-lg"> <i class="fa fa-user"></i> </span> </span> </div>
</div>
<div class="form-group mb-lg">
<div class="clearfix">
<label class="pull-left">Password</label>
</div>
<div class="input-group input-group-icon">
<input name="pasword" type="password" class="form-control input-lg" autocomplete="current-password" required />
<span class="input-group-addon"> <span class="icon icon-lg"> <i class="fa fa-lock"></i> </span> </span> </div>
</div>
<div class="row">
<div class="col-sm-8">
<div class="checkbox-custom checkbox-default">
&nbsp;
</div>
</div>
<div class="col-sm-4 text-right">
<button type="submit" class="btn btn-primary hidden-xs">Sign In</button>
<button type="submit" class="btn btn-primary btn-block btn-lg visible-xs mt-lg">Sign In</button>
</div>
</div>
</form>
<?php endif; ?>
</div>
</div>
<p class="text-center text-muted mt-md mb-md">&copy; Copyright <?=date('Y')?>. All Rights Reserved.</p>
</div>
</section>
<script src="<?=$siteUrl;?>assets/vendor/jquery/jquery.js"></script>
<script src="<?=$siteUrl;?>assets/vendor/jquery-browser-mobile/jquery.browser.mobile.js"></script>
<script src="<?=$siteUrl;?>assets/vendor/jquery-cookie/jquery.cookie.js"></script>
<script src="<?=$siteUrl;?>assets/vendor/style-switcher/style.switcher.js"></script>
<script src="<?=$siteUrl;?>assets/vendor/bootstrap/js/bootstrap.js"></script>
<script src="<?=$siteUrl;?>assets/vendor/nanoscroller/nanoscroller.js"></script>
<script src="<?=$siteUrl;?>assets/vendor/bootstrap-datepicker/js/bootstrap-datepicker.js"></script>
<script src="<?=$siteUrl;?>assets/vendor/magnific-popup/magnific-popup.js"></script>
<script src="<?=$siteUrl;?>assets/vendor/jquery-placeholder/jquery.placeholder.js"></script>
<script src="<?=$siteUrl;?>assets/javascripts/theme.js"></script>
<script src="<?=$siteUrl;?>assets/javascripts/theme.custom.js"></script>
<script src="<?=$siteUrl;?>assets/javascripts/theme.init.js"></script>
</body>
</html>
