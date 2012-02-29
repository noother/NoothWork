<?php

class FakeMemcache {
	
	function add() { return true; }
	function set() { return true; }
	function delete() { return true; }
	function get() { return false; }
	function flush() { return true; }
	
}

?>
