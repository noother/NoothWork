<?php

class StaticModule extends GlobalModule {
	function index() {
	
		switch($this->params['page']) {
			case 'maintenance':
				$this->setTitle('Maintenance');
				$this->smarty->display('maintenance.tpl');
				break;
			case 'ie6_block':
				$this->setTitle('Please update your browser');
				$this->smarty->display('ie6_block.tpl');
				break;
		}
		
	}
}
?>
