<?php
	require_once('core/NoothWork.php');
	if(php_sapi_name() == 'cli') new NoothWork($argv);
	else new NoothWork;
?>

