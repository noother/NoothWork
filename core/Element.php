<?php

class Element {
	
	protected $CONFIG;
	protected $MySQL;
	protected $smarty;
	protected $lang;
	protected $framework;
	protected $memcache;
	protected $data;
	protected $params;
	
	function Element($framework,$data=null) {
		$this->framework = $framework;
		$this->params    = $framework->params;
		$this->CONFIG	 = $framework->elementConfig;
		$this->MySQL	 = $framework->MySQL;
		$this->lang		 = $framework->lang;
		$this->smarty	 = $framework->getSmarty('elements/'.strtolower($this->CONFIG['name']).'/_templates',
											 'smarty/templates_c/'.strtolower($this->CONFIG['name']).'_element',
											 'smarty/cache/'.strtolower($this->CONFIG['name']).'_element');
											 
		$this->smarty->assign('loggedin',$this->isLoggedIn());									 
											 
		$this->memcache = $framework->memcache;
		if(isset($data)) $this->data = $data;
		$this->index();
		$this->framework->restoreSmarty();
	}
	
	function isLoggedIn() { return $this->framework->isLoggedIn(); }
	
	function setTitle($string) { $this->framework->setTitle($string); }

	function setDescription($string) { $this->framework->setDescription($string); }
	
	function addJavascript($filename) { $this->framework->addJavascript($filename); }
	
	function addCSS($filename) { $this->framework->addCSS($filename); }
	
	function index(){}
}

?>
