<?php

class GlobalModule extends Module {
	
	function init() {
		return true;
	}
	
	function throwError($error) {
		$this->smarty->assign('error',parse($error));
		$this->smarty->display('error.tpl');
		return;
	}
	
}

?>
