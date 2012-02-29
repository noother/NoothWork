<?php

class Module {
	
	
	public $params;
	public $CONFIG;
	protected $MySQL;
	protected $smarty;
	protected $lang;
	protected $framework;
	protected $crossModules;
	protected $memcache;
	protected $modulePath;
	
	public $level;
	
	function Module($framework,$module_path,$isCrossModule) {
		$this->modulePath = $module_path;
		$this->framework	= $framework;
		$this->MySQL		= $framework->MySQL;
		$this->lang			= $framework->lang;
		if($isCrossModule) {
			$this->smarty = $framework->spawnSmarty();
			$this->smarty->template_dir	= $this->modulePath.'/_templates';
			$this->smarty->compile_dir	= 'smarty/templates_c/'.str_replace('/','_',$this->modulePath).'_module';
			if(!is_dir($this->smarty->compile_dir)) mkdir($this->smarty->compile_dir);
			$this->smarty->cache_dir	= 'smarty/cache/'.str_replace('/','_',$this->modulePath).'_module';
			if(!is_dir($this->smarty->cache_dir)) mkdir($this->smarty->cache_dir);
		} else {
			$this->smarty		= $framework->getSmarty($this->modulePath.'/_templates',
													 'smarty/templates_c/'.str_replace('/','_',$this->modulePath).'_module',
													 'smarty/cache/'.str_replace('/','_',$this->modulePath).'_module');
		}
		$this->smarty->setModulePath($this->modulePath);
												 
		$this->smarty->assign('loggedin',$this->isLoggedIn());
		
		$this->memcache = $framework->memcache;
		
		if(!isset($this->level)) {
			$this->level = $framework->CONFIG['default_level'];
		}
		
	}
	
	function loadAjax($method) {
		$method = 'ajax_'.$method;
		if(!method_exists($this,$method)) return false;
		$this->$method();
	return true;
	}
	
	function loadModule($module) {
		return $this->framework->loadModule($module,true);
	}
	
	function checkPermission() {
		$level = 0;
		if($this->isLoggedIn()) $level = $_SESSION['user']['level'];
		if($level >= $this->level) return true;
	return false;
	}
	
	function showElement($params) {
		if(!is_array($params)) return $this->framework->showElement($element);
		return $this->framework->showElement($params['element'],$params);
	}
	
	function redirect($url) { return $this->framework->redirect($url); }

	function setTitle($string) { $this->framework->setTitle($string); }

	function setDescription($string) { $this->framework->setDescription($string); }

	function addJavascript($filename) { $this->framework->addJavascript($filename); }
	
	function addCSS($filename) { $this->framework->addCSS($filename); }

	function sendMail($to, $subject, $template, $vars, $attachment=null) { return $this->framework->sendMail($to, $subject, $template, $vars, $attachment); }

	function isLoggedIn() { return $this->framework->isLoggedIn(); }
	
	function init() {}

	function index(){}
}

?>
