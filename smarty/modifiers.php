<?php

function smarty_custom_modifier_parse($string) {
	return parse($string);
}

function smarty_custom_modifier_p($string) {
	return parse($string);
}

function smarty_custom_modifier_euro($value) {
	return number_format($value,2,',','.')." €";
}

?>
