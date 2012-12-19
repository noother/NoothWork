<?php

abstract class Model {
	
	private $framework;
	
	protected $MySQL;
	protected $memcache;
	protected $models = array();
	
	
	function __construct($framework) {
		$this->framework = $framework;
		$this->MySQL = $framework->MySQL;
		$this->memcache = $framework->memcache;
		
		foreach($this->models as $modelname) {
			$property_name = $modelname.'Model';
			if(isset($this->$property_name)) {
				trigger_error("Couldn't autoload model ".$modelname." because property ".$property_name." would be overwritten");
				return false;
			}
			
			$this->$property_name = $this->loadModel($modelname);
		}
	}
	
	function loadModel($modelname) { return $this->framework->loadModel($modelname); }
}

?>
