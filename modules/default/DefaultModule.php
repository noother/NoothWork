<?php

class DefaultModule extends GlobalModule {
	
	function index() {
		$this->smarty->display('default.tpl');	
		$this->setTitle('Default');
	}
	
}

?>
