<?php

class libFile {
	
	static function stripDirs($dir,$num) {
		if(substr($dir,-1) == '/') $dir = substr($dir,0,-1);
		for($x=0;$x<$num;$x++) {
			$pos = strrpos($dir,'/');
			$dir = substr($dir,0,$pos);
		}
		
	return $dir;
	}
	
}


?>
