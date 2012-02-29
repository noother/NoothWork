<?php

function parse($mixed) {
	if(is_array($mixed)) {
		array_walk_recursive($mixed,'parseCallback');
	} else $mixed = htmlspecialchars(stripslashes($mixed));
return $mixed;
}

function parseCallback(&$a,$b) { $a = htmlspecialchars(stripslashes($a)); }
	
function pr($var) {
	echo '<pre>'."\n";
	print_r($var);
	echo '</pre>'."\n";
}

function get_microtime() {
	$tmp = microtime();
	$tmp = explode(" ",$tmp);
return $tmp[0]+$tmp[1];
}

?>
