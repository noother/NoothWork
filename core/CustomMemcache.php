<?php

class CustomMemcache extends Memcache {
	
	private $isConnected = false;
	private $host;
	private $queryLog = array();
	private $debugMode;
	
	function CustomMemcache($host,$debug_mode=false) {
		$this->host = $host;
		if($debug_mode) $this->debugMode = true;
	}
	
	function connect() {
		if($this->debugMode) $s = get_microtime();
		$check = parent::connect($this->host);
		if($this->debugMode) {
			$e = get_microtime();
			array_push($this->queryLog,array('query' => 'connect '.$this->host, 'time' => number_format(($e-$s)*1000,4)));
		}
		if($check) $this->isConnected = true;
	return $check;
	}
	
	function get($key,$flags=null,$mysql=false) {
		if(!$this->isConnected) $this->connect();
		
		if($this->debugMode && !$mysql) $s = get_microtime();
		$return = parent::get($key,$flags);
		if($this->debugMode && !$mysql) {
			$e = get_microtime();
			array_push($this->queryLog,array('query' => 'get '.$key.(!$return?' | not set':''), 'time' => number_format(($e-$s)*1000,4)));
		}
		
	return $return;
	}
	
	function add($key,$var,$flag=null,$expire=null,$mysql=false) {
		if(!$this->isConnected) $this->connect();
		
		if($this->debugMode && !$mysql) $s = get_microtime();
		$return = parent::add($key,$var,$flag,$expire);
		if($this->debugMode && !$mysql) {
			$e = get_microtime();
			array_push($this->queryLog,array('query' => 'add '.$key, 'time' => number_format(($e-$s)*1000,4)));
		}
	return $return;
	}
	
	function delete($key,$timeout=null) {
		if(!$this->isConnected) $this->connect();
		
		if($this->debugMode) $s = get_microtime();
		$return = parent::delete($key,$timeout);
		if($this->debugMode) {
			$e = get_microtime();
			array_push($this->queryLog,array('query' => 'delete '.$key, 'time' => number_format(($e-$s)*1000,4)));
		}
	return $return;
	}
	
	function flush() {
		if(!$this->isConnected) $this->connect();
		
		
		if($this->debugMode) $s = get_microtime();
		$return = parent::flush();
		if($this->debugMode) {
			$e = get_microtime();
			array_push($this->queryLog,array('query' => 'flush', 'time' => number_format(($e-$s)*1000,4)));
		}
	return $return;
	}
	
	function replace($key,$var,$flag=null,$expire=null) {
		if(!$this->isConnected) $this->connect();
		
		if($this->debugMode) $s = get_microtime();
		$return = parent::replace($key,$var,$flag,$expire);
		if($this->debugMode) {
			$e = get_microtime();
			array_push($this->queryLog,array('query' => 'replace '.$key, 'time' => number_format(($e-$s)*1000,4)));
		}
	return $return;
	}
	
	function set($key,$var,$flag,$expire) {
		if(!$this->isConnected) $this->connect();
		
		if($this->debugMode) $s = get_microtime();
		$return = parent::set($key,$var,$flag,$expire);
		if($this->debugMode) {
			$e = get_microtime();
			array_push($this->queryLog,array('query' => 'set '.$key, 'time' => number_format(($e-$s)*1000,4)));
		}
	return $return;
	}
	
	function debugOutput() {
		/*
			Wird nur ausgeführt, wenn in config/core.conf debug=1 ist
		*/
		echo '<table cellspacing="5" width="990" style="border:1px solid gray;">';
		echo '<tr><th colspan="3" align="center">Memcache (without mysql)</th></tr>';
		echo '<tr><th>Num</th><th width="800">Query</th><th>Time</th></tr>';
		$c = 1;
		$sum = 0;
		foreach($this->queryLog as $query) {
			$sum+=$query['time'];
			echo '<tr>';
			echo '<td align="center">'.$c++.'</td>';
			echo '<td>'.htmlspecialchars($query['query']).'</td>';
			echo '<td align="right">'.$query['time'].' ms</td>';
			echo '</tr>';
		}
		echo '<tr><td colspan="3">Total query time: <strong>'.$sum.' ms</strong></td></tr>';
		echo '</table>';
		echo '<br />';
	}
	
}

?>
