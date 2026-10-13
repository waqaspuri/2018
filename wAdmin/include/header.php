<?php
require_once ("../php/connect-mysql.php");
require_once ("../php/lead_pipeline.php");
ensureLeadPipelineSchema($conn);
$basePth = basename($_SERVER['PHP_SELF']);
$leadCountResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders AS o INNER JOIN products AS p ON p.id=o.productID WHERE o.leadArchived='0' AND (o.statusID='1' OR o.leadStage IN ('Sourcing / Supplier Contact','Quote Received & Submitted','Negotiation / Revision')) AND o.leadStage NOT IN ('PI Issued / Order Confirmed','Deal Lost / Cancelled')");
$leadCountRow = $leadCountResult ? mysqli_fetch_assoc($leadCountResult) : array('total' => 0);
$leadCount = (int)($leadCountRow['total'] ?? 0);
if(isset($_POST['action']) && $_POST['action']=="quickNote"){
mysqli_query($conn,"INSERT INTO to_do (title,description,isDone,createdDate) VALUES ('".mysqli_real_escape_string($conn,$_POST['title'])."','".mysqli_real_escape_string($conn,$_POST['description'])."','0','".currDateTimeGmt()."')");
header("location: toDo.php?act=1");
}
?>
<!doctype html>
<html class="fixed">
<head>
<meta charset="UTF-8">
<title><?=$siteName;?> :: Admin Panel</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="http://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800%7CShadows+Into+Light" rel="stylesheet" type="text/css">
<link rel="stylesheet" href="<?=$siteUrl;?>assets/vendor/bootstrap/css/bootstrap.css" />
<link rel="stylesheet" href="<?=$siteUrl;?>assets/vendor/font-awesome/css/font-awesome.css" />
<link rel="stylesheet" href="<?=$siteUrl;?>assets/vendor/magnific-popup/magnific-popup.css" />
<link rel="stylesheet" href="<?=$siteUrl;?>assets/vendor/bootstrap-datepicker/css/datepicker3.css" />
<link rel="stylesheet" href="<?=$siteUrl;?>assets/vendor/jquery-ui/css/ui-lightness/jquery-ui-1.10.4.custom.css" />
<link rel="stylesheet" href="<?=$siteUrl;?>assets/vendor/bootstrap-multiselect/bootstrap-multiselect.css" />
<link rel="stylesheet" href="<?=$siteUrl;?>assets/vendor/morris/morris.css" />
<link rel="stylesheet" href="<?=$siteUrl;?>assets/stylesheets/theme.css" />
<link rel="stylesheet" href="<?=$siteUrl;?>assets/stylesheets/theme-custom1.1.css">
<script src="<?=$siteUrl;?>assets/vendor/modernizr/modernizr.js"></script>
<script src="<?=$siteUrl;?>assets/vendor/jquery/jquery.js"></script>
</head>
<body>
<section class="body">
<header class="header">
<div class="logo-container"> <a href="<?=$siteUrl?>" class="logo"> <img src="<?=$siteUrl;?>assets/images/logo.png" height="35" alt="Admin" /> </a>
<div class="visible-xs toggle-sidebar-left" data-toggle-class="sidebar-left-opened" data-target="html" data-fire-event="sidebar-left-opened"> <i class="fa fa-bars" aria-label="Toggle sidebar"></i> </div>
</div>
<div class="header-right">

<ul class="notifications">
<li>
<a href="#" class="dropdown-toggle notification-icon" data-toggle="dropdown">
<i class="fa fa-bell"></i>
<span class="badge"><?=quickNotesTotal();?></span>
</a>
<div class="dropdown-menu notification-menu">
<div class="notification-title">
To do List
</div>
<div class="content">
<ul>
<li>
<a class="popup-with-form clearfix" href="#quickNoteForm">
<div class="image">
<i class="fa fa-list bg-danger"></i>
</div>
<span class="title">Add New Note!</span>
<span class="message">Just now</span>
</a>
</li>
</ul>
<hr />
<div class="text-right">
<a href="<?=$siteUrl;?>toDo.php" class="view-more">View All</a>
</div>
</div>
</div>
</li>
</ul>
<span class="separator"></span>
<div id="userbox" class="userbox"> <a href="<?=$siteUrl?>" data-toggle="dropdown">
<figure class="profile-picture"> <img src="<?=$siteUrl;?>assets/images/user.png" alt="logo" class="img-circle" data-lock-picture="<?=$siteUrl;?>assets/images/user.png" /> </figure>
<div class="profile-info"> <span class="name">Waqas Chemical</span> <span class="role">administrator</span> </div>
<i class="fa custom-caret"></i> </a>
<div class="dropdown-menu">
<ul class="list-unstyled">
<li class="divider"></li>
<li> <a role="menuitem" tabindex="-1" href="<?=$siteUrl?>changePassword.php" ><i class="fa fa-lock"></i> Change Password</a> </li>
<li> <a role="menuitem" tabindex="-1" href="<?=$siteUrl?>logout.php"><i class="fa fa-power-off"></i> Logout</a> </li>
</ul>
</div>
</div>
</div>
</header>
<div class="inner-wrapper">
<aside id="sidebar-left" class="sidebar-left">
<div class="sidebar-header">
<div class="sidebar-title"> Navigation </div>
<div class="sidebar-toggle hidden-xs" data-toggle-class="sidebar-left-collapsed" data-target="html" data-fire-event="sidebar-left-toggle"> <i class="fa fa-bars" aria-label="Toggle sidebar"></i> </div>
</div>
<div class="nano">
<div class="nano-content">
<nav id="menu" class="nav-main" role="navigation">
<ul class="nav nav-main">
<li <?php if($basePth=="index.php"){ echo 'class="nav-active"'; }?> > <a href="<?=$siteUrl?>"> <i class="fa fa-home" aria-hidden="true"></i> <span>Dashboard</span> </a> </li>
<li class="<?=($basePth=="tasks.php")?'nav-active':'';?>" ><a href="<?=$siteUrl?>tasks.php"><i class="fa fa-file" aria-hidden="true"></i> <span>Manage Tasks</span> </a> </li>
<li class="<?=($basePth=="bulkMessages.php")?'nav-active':'';?>"><a href="<?=$siteUrl?>bulkMessages.php"><i class="fa fa-paper-plane" aria-hidden="true"></i> <span>Bulk Email / SMS</span></a></li>
<li class="nav-parent <?=($basePth=="price_requests.php" || $basePth=="price_request_settings.php")?'nav-active nav-expanded':'';?>" ><a href="<?=$siteUrl?>price_requests.php"><i class="fa fa-envelope" aria-hidden="true"></i> <span>Price Requests</span> </a>
<ul class="nav nav-children">
<li><a href="<?=$siteUrl?>price_requests.php">Request Vendor Prices</a></li>
<li><a href="<?=$siteUrl?>price_request_settings.php">Custom Message</a></li>
</ul>
</li>
<li class="<?=($basePth=="orders.php")?'nav-active':'';?>" ><a href="<?=$siteUrl?>orders.php"><i class="fa fa-shopping-cart" aria-hidden="true"></i> <span>Manage Orders</span> </a> </li>
<li class="<?=($basePth=="leads.php")?'nav-active':'';?>" ><a href="<?=$siteUrl?>leads.php"><i class="fa fa-list" aria-hidden="true"></i> <span>Lead Follow-up</span> <span class="badge" style="float:right;border-radius:12px;min-width:22px;"><?=$leadCount?></span></a> </li>
<li class="<?=($basePth=="invoices.php")?'nav-active':'';?>" ><a href="<?=$siteUrl?>invoices.php"><i class="fa fa-list" aria-hidden="true"></i> <span>Manage Invoices</span> </a> </li>
<li class="<?=($basePth=="yearly_income.php")?'nav-active':'';?>" ><a href="<?=$siteUrl?>yearly_income.php"><i class="fa fa-line-chart" aria-hidden="true"></i> <span>Yearly Income</span> </a> </li>
<li class="<?=($basePth=="vendors.php")?'nav-active':'';?>" ><a href="<?=$siteUrl?>vendors.php"><i class="fa fa-user" aria-hidden="true"></i> <span>Manage Vendors</span> </a> </li>
<li class="<?=($basePth=="customers.php")?'nav-active':'';?>" ><a href="<?=$siteUrl?>customers.php"><i class="fa fa-users" aria-hidden="true"></i> <span>Manage Customers</span> </a> </li>
<li class="<?=($basePth=="customer_reorders.php")?'nav-active':'';?>"><a href="<?=$siteUrl?>customer_reorders.php"><i class="fa fa-refresh" aria-hidden="true"></i> <span>Customer Reorders</span></a></li>
<li class="<?=($basePth=="contactBook.php")?'nav-active':'';?>" ><a href="<?=$siteUrl?>contactBook.php"><i class="fa fa-book" aria-hidden="true"></i> <span>Manage Contacts</span> </a> </li>
<li class="<?=($basePth=="products.php")?'nav-active':'';?>" ><a href="<?=$siteUrl?>products.php"><i class="fa fa-list" aria-hidden="true"></i> <span>Manage Products</span> </a> </li>
<li class="<?=($basePth=="database_backup.php")?'nav-active':'';?>" ><a href="<?=$siteUrl?>database_backup.php"><i class="fa fa-database" aria-hidden="true"></i> <span>MySQL Backup</span> </a> </li>
<li class="nav-parent <?=($basePth=="posSaleDrafts.php" || $basePth=="posProductStocks.php" || $basePth=="posPayments.php" || $basePth=="posUnitTypes.php" || $basePth=="posBuyers.php" || $basePth=="posSettings.php" || $basePth=="posGoodDeclaration.php" || $basePth=="posProducts.php" || $basePth=="posSale.php" || $basePth=="posPurchase.php")?'nav-active nav-expanded':'';?>" > <a> <i class="fa fa-usd" aria-hidden="true"></i> <span>Manage POS</span> </a>
<ul class="nav nav-children">
<li><a href="<?=$siteUrl?>posSale.php?action=add">Add Sales Tax Invoice</a> </li>
<li><a href="<?=$siteUrl?>posSale.php">Manage Sales</a> </li>
<li><a href="<?=$siteUrl?>posSaleDrafts.php">Manage Draft Invoices</a> </li>
<li><a href="<?=$siteUrl?>posPayments.php">Manage Payments</a> </li>
<li><a href="<?=$siteUrl?>posBuyers.php">Manage Buyers</a> </li>
<li><a href="<?=$siteUrl?>posProducts.php?action=add">Add New Product</a> </li>
<li><a href="<?=$siteUrl?>posProducts.php">Manage Products</a> </li>
<li><a href="<?=$siteUrl?>posProductStocks.php">Manage Product Stocks</a> </li>
<li><a href="<?=$siteUrl?>posGoodDeclaration.php">Manage Good Declaration</a> </li>
<li><a href="<?=$siteUrl?>posUnitTypes.php">Manage Unit Types</a> </li>
<li><a href="<?=$siteUrl?>posSettings.php">Manage POS Settings</a> </li>
</ul>
</li>
<li class="nav-parent <?=($basePth=="emailTemplates.php" || $basePth=="smsTemplates.php")?'nav-active nav-expanded':'';?>" > <a> <i class="fa fa-list" aria-hidden="true"></i> <span>Manage Templates</span> </a>
<ul class="nav nav-children">
<li><a href="<?=$siteUrl?>emailTemplates.php">Manage Email Templates</a> </li>
<li><a href="<?=$siteUrl?>smsTemplates.php">Manage SMS Templates</a> </li>
</ul>
</li>
<li class="<?=($basePth=="settings.php")?'nav-active':'';?>" ><a href="<?=$siteUrl?>settings.php"><i class="fa fa-wrench" aria-hidden="true"></i> <span>Manage Settings</span> </a> </li>
<li class="<?=($basePth=="changePassword.php")?'nav-active':'';?>" ><a href="<?=$siteUrl?>changePassword.php"><i class="fa fa-lock" aria-hidden="true"></i> <span>Manage Change Password</span> </a> </li>
<li><a href="<?=$siteUrl?>logout.php"><i class="fa fa-power-off" aria-hidden="true"></i> <span>Logout</span> </a> </li>
<li><a href="<?=$siteUrl?>sms_log.php?limit=10"><i class="fa fa-history" aria-hidden="true"></i> <span>SMS Logs</span> </a> </li>
</ul>
</nav>
</div>
</div>
</aside>
