<?php

class CustomSmarty extends Smarty {

	private $levels;
	
	function CustomSmarty() {
		parent::Smarty();
	}
	
	function setModulePath($module_path) {
		$this->levels = substr_count($module_path,'/')+1;
	}
	
	function display($template, $cache_id=null, $compile_id=null) {
		if(substr($template,-4) != '.tpl') $template.= '.tpl';
		
		$template_path = $this->template_dir;
		$check = false;
		for($x=0;$x<$this->levels+1;$x++) {
			if(file_exists($template_path.'/'.$template)) {
				$check = true;
				break;
			}
			$template_path = libFile::stripDirs($template_path,2).'/_templates';
		}
		if(!$check) return false;
		
		$save = $this->template_dir;
		$this->template_dir = $template_path;
		parent::display($template,$cache_id,$compile_id);
		$this->template_dir = $save;
	}	
	
}

?>
