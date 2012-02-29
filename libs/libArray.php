<?php

class libArray {
	
	static function sortByLengthASC($array) {
		$tempFunction = create_function('$a,$b','return strlen($a)-strlen($b);');
		usort($array,$tempFunction);
	return $array;
	}
	
	static function sortByLengthDESC($array) {
		$tempFunction = create_function('$a,$b','return strlen($b)-strlen($a);');
		usort($array,$tempFunction);
	return $array;
	}
	
	static function array2xml($array) {
		$output = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";
		$output.= self::getXMLArray($array);
	return $output;
	}
	
	private function getXMLArray($array,$tabs=0) {
		foreach($array as $key => $value) {
			$output.= self::tabs($tabs).'<'.htmlspecialchars($key).'>';
			$check = false;
			if(is_array($value)) {
				$check = true;
				$output.= "\n".self::getXMLArray($value,$tabs+1);
			} else {
				$output.= htmlspecialchars($value);
			}
		
			if($check) $output.= self::tabs($tabs);
			$output.= '</'.htmlspecialchars($key).'>'."\n";
		}
	
	return $output;
	}
	
	private function tabs($n) {
		$output = "";
		for($x=0;$x<$n;$x++) {
			$output.= "\t";
		}
	return $output;
	}
	
}

?>
