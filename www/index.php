<?php

/*
 * FileSender www.filesender.org
 * 
 * Copyright (c) 2009-2012, AARNet, Belnet, HEAnet, SURFnet, UNINETT
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * 
 * *	Redistributions of source code must retain the above copyright
 * 	notice, this list of conditions and the following disclaimer.
 * *	Redistributions in binary form must reproduce the above copyright
 * 	notice, this list of conditions and the following disclaimer in the
 * 	documentation and/or other materials provided with the distribution.
 * *	Neither the name of AARNet, Belnet, HEAnet, SURFnet and UNINETT nor the
 * 	names of its contributors may be used to endorse or promote products
 * 	derived from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/*
 * loads javascript
 * js/upload.js   manages all html5 related functions and uploading
 */

// set cache to default - nocache
session_cache_limiter('nocache'); 

if(session_id() == ""){
	// start new session and mark it as valid because the system is a trusted source
	session_start();
	$_SESSION['validSession'] = true;
} 

require_once('../classes/_includes.php');

$flexerrors = "true";
// load config
$authsaml = AuthSaml::getInstance();
$authvoucher = AuthVoucher::getInstance();
$functions = Functions::getInstance();
$CFG = config::getInstance();
$config = $CFG->loadConfig();
$sendmail = Mail::getInstance();
$log = Log::getInstance();

$messageArray = array(); // messages to display to client
$errorArray = array(); // messages to display to client
date_default_timezone_set($config['Default_TimeZone']);

$useremail = "";
$s = "";

$isAuth = $authsaml->isAuth();
$isVoucher = $authvoucher->aVoucher();
$isAdmin = $authsaml->authIsAdmin();

// add token for form posting if isauth or isvoucher
if(($isAuth || $isVoucher) && !isset($_SESSION["s-token"])) 
{
	$_SESSION["s-token"] = getGUID();
}

if(isset($_REQUEST["s"]))
{
	$s = $_REQUEST["s"];
}
if(!$isVoucher && !$isAuth && $s != "complete" && $s != "completev")
{
	$s = "logon";
}
// check if authentication data and attributes exist
if($isAuth ) 
{ 
	$userdata = $authsaml->sAuth();
	if($userdata == "err_attributes")
	{
		$s = "error";
		$isAdmin = false;
		array_push($messageArray,  lang("_ERROR_ATTRIBUTES"));
	} else {
		$useremail = $userdata["email"];
	}
} 

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title><?php echo htmlspecialchars($config['site_name']); ?></title>
<link rel="icon" href="favicon.ico" type="image/x-icon" />
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
<link type="text/css" href="css/smoothness/jquery-ui-1.10.2.custom.min.css" rel="Stylesheet" />
<link rel="stylesheet" type="text/css" href="css/default.css?<?php echo FileSender_Version::VERSION; ?>" />
<?php if (isset($config["customCSS"])) {
echo '<link rel="stylesheet" type="text/css" href="'.$config["customCSS"].'" />';
}
?>
<script type="text/javascript" src="js/json2.js" ></script>
<script type="text/javascript" src="js/common.js" ></script>
<script type="text/javascript" src="js/jquery-1.9.1.min.js" ></script>
<script type="text/javascript" src="js/jquery-ui-1.10.2.custom.min.js"></script>
<script type="text/javascript">
//<![CDATA[

var debug = <?php echo $config["debug"] ? 'true' : 'false'; ?> ;
var html5 = false;
var html5webworkers = false;
//check if webworkers are available
<?php if(isset($config['terasender']) && $config['terasender']) { echo 'html5webworkers = typeof(Worker)!=="undefined";'; }?>

// check if html5 functions are available
html5 = (window.File && window.FileReader && window.FileList && window.Blob && window.FormData) ? true : false;
	if(window.opera){html5=false;};
$(function() {
	
	// display topmenu, content and userinformation
	$("#topmenu").show();
	$("#content").show();
	$("#userinformation").show();

	$( "a", ".menu" ).button();
	
	$("#dialog-help").dialog({ autoOpen: false, height: 400,width: 660, modal: true,
		buttons: {
			'helpBTN': function() {
				$( this ).dialog( "close" );
				}
			}
		});
		// token error dialog
		$("#dialog-tokenerror").dialog({ autoOpen: false, height: 240,width: 350, modal: true,title: "",		
		buttons: {
			'<?php echo lang("_OK") ?>': function() {
				location.reload(true);
				}
			}
		})
		
		$('.ui-dialog-buttonpane button:contains(helpBTN)').attr("id","btn_closehelp");            
		$('#btn_closehelp').html('<?php echo lang("_CLOSE") ?>')  
		
		$("#dialog-about").dialog({ autoOpen: false,  height: 400,width: 400, modal: true,
			buttons: {
				'aboutBTN': function() {
					$( this ).dialog( "close" );
				}
			}
		});
		$('.ui-dialog-buttonpane button:contains(aboutBTN)').attr("id","btn_closeabout");            
		$('#btn_closeabout').html('<?php echo lang("_CLOSE") ?>') 
		
		if(html5){
			// use HTML5 upload functions
			$("#html5image").attr("src","images/html5_installed.png");
			$("#html5image").attr("title","<?php echo lang("_HTML5Supported"); ?>");
			//$("#html5text").html('<?php echo lang("_HTML5Supported"); ?>');
			} else {
			$("#html5image").attr("src","images/html5_none.png");
			$("#html5image").attr("title","<?php echo lang("_HTML5NotSupported"); ?>");
			//$("#html5text").html('<?php echo lang("_HTML5NotSupported"); ?>');
			$('#html5image').click(function() { displayhtml5support(); });
			$("#html5link").removeAttr("href");
		}
		 $("#html5image").show();
		 
		 // set draggable = false for all images and a href
		 $("a").attr("draggable","false");
		 $("img").attr("draggable","false");
});
	
function openhelp()
	{
		$( "#dialog-help" ).dialog( "open" );
		$('.ui-dialog-buttonpane > button:last').focus();
	}
	
function openabout()
	{
		$( "#dialog-about" ).dialog( "open" );
		$('.ui-dialog-buttonpane > button:last').focus();
	}
//]]>
</script>
   
<meta name="robots" content="noindex, nofollow" />
<style type="text/css">
<!--
.style1 {color: #FFFFFF}
-->
</style>
</head>
<body>
<div id="wrap">
  <div id="header">
    <div align="center">
      <p><img src="displayimage.php" width="800" height="60" border="0" alt="banner" /></p>
      <noscript>
      <p class="style5 style1">JavaScript is turned off in your web browser. <br />
        This application will not run without Javascript enabled in your web browser. <br /><br />
		<br />
        <br />
      </p>
      </noscript>
    </div>
  </div>
  <div class="menubar">
  <div class="leftmenu">
  <ul>
      <?php 
  	// create menu
  	// disable all buttons if this is a voucher, even if the user is logged on
 	if (!$authvoucher->aVoucher()  &&  $s != "completev" && !isset($_REQUEST["gid"])){
	if($authsaml->isAuth() ) { echo '<li><a class="'.$functions->active($s,'upload').'" id="topmenu_newupload" href="index.php?s=upload">'.lang("_NEW_UPLOAD").'</a></li>'; }
	if($authsaml->isAuth() ) { echo '<li><a class="'.$functions->active($s,'vouchers').'" id="topmenu_vouchers" href="index.php?s=vouchers">'.lang("_VOUCHERS").'</a></li>'; }
	if($authsaml->isAuth() ) {echo '<li><a class="'.$functions->active($s,'files').'" id="topmenu_myfiles" href="index.php?s=files">'.lang("_MY_FILES").'</a></li>'; }
	if($authsaml->authIsAdmin() ) { echo '<li><a class="'.$functions->active($s,'admin').'" id="topmenu_admin" href="index.php?s=admin">'.lang("_ADMIN").'</a></li>'; }
	//if($authsaml->isAuth() ) { echo '<li><a class="'.$functions->active($s,'summary').'" id="topmenu_summary" href="testsummary.php?email='.$useremail  .'" target="_blank">Summary</a></li>'; }
  }
  ?>
  </ul>
  </div>
  <div class="rightmenu">
  <ul>
  <?php
	if($config['helpURL'] == "") {
		echo '<li><a class="'.$functions->active($s,'help').'" href="#" id="topmenu_help" onclick="openhelp()">'.lang("_HELP").'</a></li>';
	} else {
		echo '<li><a class="'.$functions->active($s,'help').'" href="'.$config['helpURL'].'" target="_blank" id="topmenu_help">'.lang("_HELP").'</a></li>';
	}
	if($config['aboutURL'] == "") {
		echo '<li><a class="'.$functions->active($s,'about').'" href="#" id="topmenu_about" onclick="openabout()">'.lang("_ABOUT").'</a></li>';
	} else {
		echo '<li><a class="'.$functions->active($s,'about').'" href="'.$config['aboutURL'].'" target="_blank" id="topmenu_about">'.lang("_ABOUT").'</a></li>';	
	}
	if(!$authsaml->isAuth() && $s != "logon" ) { echo '<li><a class="'.$functions->active($s,'logon').'" href="'.$authsaml->logonURL().'" id="topmenu_logon">'.lang("_LOGON").'</a></li>';}
	if($authsaml->isAuth() && !$authvoucher->aVoucher() &&  $s != "completev"  && !isset($_REQUEST["gid"])) { echo '<li><a class="'.$functions->active($s,'about').'" href="'.$authsaml->logoffURL().'" id="topmenu_logoff">'.lang("_LOG_OFF").'</a></li>'; }
	// end menu
	?>
	</ul>
    </div>
    </div>
    <div class="msgbox">&nbsp;
	<div id="scratch" class="scratch_msg">
	<?php
		if(array_key_exists("scratch", $_SESSION )) {
			echo($functions->getScratchMessage());
			session_unregister("scratch");
		}
	?>
	</div>	
    </div>
	<div id="userinformation" style="display:none">
	<?php 

	// set user attributes from identity provider
	if ($isAuth )
	{
		$attributes = $authsaml->sAuth();
	}

	// display user details if desired
	if($config["displayUserName"])
	{
		echo "<div class='welcomeuser'>";
		if(	$isVoucher || $s == "completev"  || isset($_REQUEST["gid"]))
		{ 
			echo lang("_WELCOMEGUEST");
		} 
		else if ($isAuth && $s != "error")
		{
			echo lang("_WELCOME")." ";
			echo utf8tohtml($attributes["cn"],true);
		}
		echo "</div>";
	}
	?>
	<div id="serviceinfo">
	<a onclick="openhelp()" style="cursor:pointer;" id="html5link" name="html5link"><img alt="" name="html5image" width="75" height="18" border="0" id="html5image" style="display:none" title="" src=""/></a></div>
	<?php
	$versiondisplay = "";
	if($config["site_showStats"])
	{
		$versiondisplay .= $functions->getStats();
	}
	if($config["versionNumber"])
	{
		$versiondisplay .= FileSender_Version::VERSION;
	}
?>
	</div>
		<div id="content" style="display:none">
		<div id="scratch"></div>
		<?php
		foreach ($messageArray as $message) 
		{
			echo '<div id="message">'.$message.'</div>';
		}
	?>
<?php
	// checks if url has vid=xxxxxxx and that voucher is valid 
	if(	$isVoucher)
	{
		// check if it is Available or a Voucher for Uploading a New File
		$voucherData = $authvoucher->getVoucher();

		if($voucherData[0]["filestatus"] == "Voucher")
		{ // load voucher upload
			require_once('../pages/multiupload.php');
		} else if($voucherData[0]["filestatus"] == "Available")
		{ 
			// allow download of voucher
			require_once('../pages/download.php');
		} else if($voucherData[0]["filestatus"] == "Closed")
		{
?>
	<div id="box"><p><?php echo lang("_VOUCHER_USED"); ?></p></div>
<?php
	}
 	else if($voucherData[0]["filestatus"] == "Voucher Cancelled")
	{
?>
		<div id="box"><p><?php echo lang("_VOUCHER_CANCELLED"); ?></p></div>
<?php
		}
		else if($voucherData[0]["filestatus"] == "Deleted")
	{
?>
		<div id="box"><p><?php echo lang("_FILE_DELETED"); ?></p></div>
<?php
		}
	} else if(isset($_REQUEST['gid'])) {
        require_once('../pages/multidownload.php');
    } else if($s == "upload")
	{
		require_once('../pages/multiupload.php');
	} else if($s == "vouchers" && !$authvoucher->aVoucher()) 
	{
		require_once('../pages/vouchers.php');
		// must be authenticated and not using a voucher to view files
	} else if($s == "files" && !$authvoucher->aVoucher() && $authsaml->isAuth() ) 
	{
		require_once('../pages/files.php');
	} else if($s == "logon") 
	{
		require_once('../pages/logon.php');
	}	
	else if($s == "admin" && $isAdmin && !$authvoucher->aVoucher()) 
	{
		require_once('../pages/admin.php');
	}
		else if($s == "uploaderror") 
	{
	?>
	<div id="message"><?php echo lang("_ERROR_UPLOADING_FILE"); ?></div>
	<?php	
	}	
		else if($s == "emailsenterror") 
	{
	?>
	<div id="message"><?php echo lang("_ERROR_SENDING_EMAIL"); ?></div>
	<?php	
	}	
	else if($s == "filesizeincorrect") 
	{
	?>
	<div id="message"><?php echo lang("_ERROR_INCORRECT_FILE_SIZE"); ?></div>
<?php	
	}	
	else if($s == "complete" || $s == "completev") 
	{
?>
		<div id="message"><?php echo lang("_UPLOAD_COMPLETE"); ?></div>
<?php
	} else if ($s == "" && $isAuth){
		require_once('../pages/multiupload.php');	
	}else if ($s == "" ){
		require_once('../pages/home.php');	
	}
?>
	</div>
   <div id="footer"> <?php echo "<div class='versionnumber'>" .$versiondisplay."</div>"; ?></div>
	</div>
	<div id="dialog-help" style="display:none" title="<?php echo lang("_HELP"); ?>">
		<?php echo lang("_HELP_TEXT"); ?>
	</div>
	<div id="dialog-about" style="display:none" title="<?php echo lang("_ABOUT"); ?>">
		<?php echo lang("_ABOUT_TEXT"); ?>
	</div>
	<div id="dialog-tokenerror" title="<?php echo lang($lang["_MESSAGE"]); ?>" style="display:none"><?php echo lang($lang["_ERROR_CONTACT_ADMIN"]); ?></div>
		<div id="footer"><?php echo lang('_SITE_FOOTER'); ?></div>
		<div id="DoneLoading"></div>
		<!-- Version <?php echo FileSender_Version::VERSION; ?> -->
	</body>
</html>
