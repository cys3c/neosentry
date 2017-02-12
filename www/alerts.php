<?php //alerts.php

include 'header.html';

//============================= Start of the container, header of the page ================================
echo '
		<div id="m-alerts">
			<div class="container">
				<div class="row">';

//============================= do processing and output success / failure here ===========================
$alertScript = (isset($_POST['alertscript']))?$_POST['alertscript']:""; //file data to add
if ($devicelist != "") { //update the alert check script if needed
	$ret = file_put_contents(".checkalerts.php", $alertScript);
	if ($ret===FALSE) {
		echo '<div class="error">ERROR: Could not write to the .checkalerts.php file.</div><br>';
	} else {
		echo '<div class="success">SUCCESS: $ret bytes were written to the alert check script.</div><br>';
	}
}
$alertScript = file_get_contents(".checkalerts.php");




//============================= body of the page ==========================================================
//ALERT SETTINGS
echo '				<br><div class="section_header">EDIT ALERT SCRIPT</div>';
echo '<p>Only edit this if you have PHP programming experience and are familiar with the way this functions.</p>
	<form class="form-horizontal" action="devices_manage.php" method="post">
		<div class="form-group">
			<center><textarea  name="alertscript" class="form-control" style="height:300px;width:97.5%" placeholder="Device List">'.$alertScript.'</textarea></center>
		</div>
		<input type="submit" class="btn btn-orange pull-right" value="SUBMIT"/>
		<p class="pull-right">&nbsp;</p>
	</form>
	<div class="clearfix"></div>
';

//ALERT RULES
//echo '				<br><div class="section_header">ALERT RULES</div>';


//CURRENT ACTIVE ALERTS
echo '				<br><div class="section_header">CURRENT ACTIVE ALERTS</div>';


//ALERT HISTORY
echo '				<br><div class="section_header">ALERT HISTORY</div>';






//============================= The end of the container ==================================================
echo '
				</div><!-- /.row -->
			</div><!-- /.container -->
		</div><!-- /#t-home -->

';


include 'footer.html';

?>