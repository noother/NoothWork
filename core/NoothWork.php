<?php


require_once('smarty/functions.php');
require_once('smarty/modifiers.php');

require_once('core/functions.php');
require_once('core/Module.php');
require_once('core/Element.php');

require_once('libs/libArray.php');
require_once('libs/libConvert.php');
require_once('libs/libCrypt.php');
require_once('libs/libFile.php');
require_once('libs/libFilesystem.php');
require_once('libs/libHTTP.php');
require_once('libs/libInternet.php');
require_once('libs/libMath.php');
require_once('libs/libString.php');
require_once('libs/libSystem.php');
require_once('libs/libTime.php');
require_once('libs/libValidate.php');


class NoothWork {
	public  $memcache;
	public  $MySQL;
	public  $moduleConfig;
	public  $elementConfig;
	public  $lang;
	public  $params;
	public  $CONFIG;
	
	public  $client = array();
	
	private $prefix = false;
	private	$module;
	private $element;
	private $layout;
	private $buffer      = "";
	private $title       = null;
	private $description = null;
	private $javascripts = array();
	private $css = array();
	private $knownEngines = array('ie6','ie7','ie8','ie9','gecko','webkit','presto');
	private $consoleMode = false;
	
	private $smarty;
	private $smartyRestore = array();
	
	
	function NoothWork($argv=null) {
		$start = get_microtime();
		
		if($argv) {
			$this->consoleMode = true;
			$_GET['module'] = $argv[1];
		}
		
		$this->loadFrameworkConfig();
		if($this->CONFIG['use_prefixes']) {
			if(!$this->loadPrefix()) {
				trigger_error("No valid prefix specified",E_USER_ERROR);
				return false;
			}
		}
		
		$this->client = $this->getClientData();
		
		require_once($this->CONFIG['smarty_path']);
		require_once('core/CustomSmarty.php');
		
		if(!$this->consoleMode && isset($_COOKIE['debug']) && $_COOKIE['debug'] == $this->CONFIG['debug_key']) {
			$this->CONFIG['debug']       = 1;
			$this->CONFIG['maintenance'] = 0;
			$this->CONFIG['ie6_block']   = 0;
		}
		
		if($this->CONFIG['use_memcache']) {
			require_once('core/CustomMemcache.php');
			$this->memcache = new CustomMemcache($this->CONFIG['memcache_host'],$this->CONFIG['debug']?true:false);
		} else {
			// the sites relying on memcached shall still work, but don't get any data out of memcached
			require_once('core/FakeMemcache.php');
			$this->memcache = new FakeMemcache();
		}
		
		if($this->CONFIG['use_mysql']) {
			
			require_once('core/MySQL.php');
			
			$this->MySQL = new MySQL(	$this->CONFIG['mysql_host'],
										$this->CONFIG['mysql_user'],
										$this->CONFIG['mysql_pass'],
										$this->CONFIG['mysql_db'],
										$this->CONFIG['mysql_use_memcache'],
										$this->memcache
									);
		}
		
		if(!$this->consoleMode) session_start();
		
		$this->setLanguage();
		
		if(!$this->consoleMode) ob_start();
		
		$this->smarty = $this->spawnSmarty();
		
		$this->params = $this->getParams();
		
		$this->loadModule();
		
		if(!$this->consoleMode) {
			if($this->module->CONFIG['mode'] == 'ajax') {
				if(!$this->module->initFalse) {
					$check = $this->module->loadAjax($this->module->CONFIG['ajax']);
					if(!$check) $this->redirect('/');
				}
			} else {
				$this->changeDescription();
				$this->setCSS();
				$this->setJavascripts();
			}
			
			$this->changeTitle();
			
			if($this->CONFIG['minimize_traffic'] || $this->CONFIG['obfuscate_output']) {
				$tmp = explode("\n",$this->buffer);
				foreach($tmp as $line) {
					if($this->CONFIG['obfuscate_output']) echo trim($line).' ';
					else echo trim($line)."\n";
				}
			} else {
				echo $this->buffer;
			}
			
			ob_flush();
			
			$end = get_microtime();
		
			if((($this->module->CONFIG['mode'] != 'ajax') && $this->CONFIG['debug']) || ($this->module->CONFIG['mode'] == 'ajax' && $this->CONFIG['debug_ajax'])) {
				echo '<br /><br />';
				echo '<div style="width:990px;margin:auto;">';
				echo '<center><strong>Debug Output</strong></center><br />';
				if($this->CONFIG['use_mysql']) $this->MySQL->debugOutput();
				if($this->CONFIG['use_memcache']) $this->memcache->debugOutput();
				echo 'Page generation time: <strong>'.(number_format(round(($end-$start)*1000,4),4)).' ms</strong><br />';
				echo 'Language: '.$this->lang;
				echo '<br /><br /><br />';
				echo '</div>';
			}
		}
	}
	
	
	function loadModule($module=null,$isCrossModule=false) {
		if($module{0} == '/')         $module = substr($module, 1);
		if(substr($module,-1) == '/') $module = substr($module, 0, -1);
		
		if(!$this->consoleMode && $this->CONFIG['maintenance']) {
			$module = 'Static';
			$this->params['page'] = 'maintenance';
		} elseif(!$this->consoleMode && $this->CONFIG['ie6_block'] && preg_match('/MSIE (.*?);/',$_SERVER['HTTP_USER_AGENT'],$arr) && $arr[1] <= 6.0) {
			$module = 'Static';
			$this->params['page'] = 'ie6_block';
		} else {
			if(!isset($module)) {
				if(isset($_GET['module']) && !empty($_GET['module'])) {
					$module = $_GET['module'];
					unset($_GET['module']);
				}
				else $module = $this->CONFIG['default_module'];
				
				if($this->prefix) {
					$module = $this->prefix.'/'.$module;
				}
			}
		}
		
		if(!$this->prepareModule($module,$isCrossModule)) {
			$module = $this->CONFIG['default_module'];
			if($this->prefix) {
				$module = $this->prefix.'/'.$module;
			}
			$this->prepareModule($module,$isCrossModule);
		}
		
		if($this->consoleMode) {
			$this->moduleConfig['layout'] = 'none';
		} elseif(!isset($this->moduleConfig['layout']) || !file_exists('layouts/'.$this->moduleConfig['layout'].'.php')) {
			$this->moduleConfig['layout'] = 'default';
		}
		
		if(isset($this->module->params['ajax'])) {
			$this->moduleConfig['mode'] = 'ajax';
			$this->moduleConfig['ajax'] = $this->module->params['ajax'];
			unset($this->module->params['ajax']);
			$this->module->CONFIG = $this->moduleConfig;
		} else {
			$this->moduleConfig['mode'] = 'default';
			$this->module->CONFIG = $this->moduleConfig;
			if($this->moduleConfig['layout'] == 'none' && !$isCrossModule) {
				$this->showModule();
			} elseif(!$isCrossModule) {
				require('layouts/'.$this->moduleConfig['layout'].'.php');
			}
		}
		
	return $this->module;
	}
	
	function prepareModule($module,$isCrossModule) {
		$module_arr = explode('/',$module);
		foreach($module_arr as $temp) if(preg_match('/[^0-9a-zA-Z_-]/',$temp)) return false;
		
		$this->moduleConfig = $this->parseConfigFile('config/modules/'.strtolower($module_arr[0]).'.conf');
		
		$path = 'modules/';
		
		require_once('modules/GlobalModule.php');
		for($x=0;$x<sizeof($module_arr);$x++) {
			if(!preg_match('/[A-Z]/',$module_arr[$x]{0})) break; // if it is a parameter
			
			$module_name = $module_arr[$x];
			if($this->prefix && $x != 0) {
				if($isCrossModule) {
					$module_name = $module_arr[0].$module_name;
				} else {
					$module_name = $this->prefix.$module_name;
				}
			}
			$module_name.= 'Module';
			$path.= strtolower($module_arr[$x]).'/';
			
			if(!file_exists($path.$module_name.'.php')) return false;
			require_once($path.$module_name.'.php');
			$classname = $module_name;
		}
		
		if(!isset($classname)) return false;
		
		$this->module = new $classname($this,$path,$isCrossModule);
		
		if(!$isCrossModule) {
			$this->module->params = $this->params;
		} else {
			$this->module->params = $this->getParams($module);
		}
		
		if(!$isCrossModule) {
			if(!$this->module->checkPermission()) $this->redirect($this->CONFIG['nopermission_redirect']);
		}
		
		if(!isset($this->module->params['ajax']) && !$isCrossModule && !$this->consoleMode) {
			$path = 'modules/';
			$module_path = "";
			for($x=0;$x<sizeof($module_arr)+1;$x++) {
				// Javascripts
				if(file_exists($path.'_js')) {
					$dirp = opendir($path.'_js');
					$javascripts = array();
					while($file = readdir($dirp)) {
						if(substr($file,-3) == '.js') {
							$lastmodified = filemtime($path.'_js/'.$file);
							array_push($javascripts,'/js/'.$module_path.$file.'?revision='.$lastmodified);
						}
					}
					sort($javascripts);
					foreach($javascripts as $javascript) $this->addJavascript($javascript);
				}
			
				// CSS
				if(file_exists($path.'_css')) {
					$dirp = opendir($path.'_css');
					$stylesheets = array();
					while($file = readdir($dirp)) {
						if(substr($file,-4) == '.css'
						|| substr($file,-(strlen($this->client['engine'])+5)) == '_'.$this->client['engine'].'.css') {
							foreach($this->knownEngines as $engine) {
								if(substr($file,-(strlen($engine)+5),-4) == '_'.$engine
								&& $engine != $this->client['engine']) continue 2;
							}
							
							$lastmodified = filemtime($path.'_css/'.$file);
							array_push($stylesheets,'/css/'.$module_path.$file.'?revision='.$lastmodified);
						}
					}
					sort($stylesheets);
					foreach($stylesheets as $stylesheet) $this->addCSS($stylesheet);
				}
				
				if($x < sizeof($module_arr)) {
					$path.= strtolower($module_arr[$x]).'/';
					$module_path.= strtolower($module_arr[$x]).'/';
				}
			}
		}
	
	return true;
	}
	
	
	
	
	function showModule() {
		if($this->module->init()) {
			$this->module->index();
		}
	}
	
	
	
	function prepareElement($element) {
		if(preg_match('/[^0-9a-zA-Z_]/',$element)) return false;
		$config = $this->parseConfigFile('config/elements/'.$element.'.conf');
		if(!$config === false) $config = array();
		if(!isset($config['name'])) $config['name'] = strtoupper($element[0]).substr($element,1);
		if(!file_exists('elements/'.$element.'/'.$config['name'].'Element.php')) return false;
		$this->elementConfig = $config;
	return true;
	}
	
	function getParams($module=null) {
		if(!isset($module)) {
			if(isset($_GET['module']) && !empty($_GET['module'])) {
				$module = $_GET['module'];
			} else {
				$module = $this->CONFIG['default_module'];
			}
		}
		
		$module_arr = explode('/',$module);
		
		$params = array();
		$params_check = false;
		$params_count = 0;
		
		for($x=0;$x<sizeof($module_arr);$x++) {
			if(empty($module_arr[$x])) continue;
			
			if(!preg_match('/[A-Z]/',$module_arr[$x]{0})) {
				$params_check = true;
			}
			
			if($params_check) {
				if(strstr($module_arr[$x],'-')) {
					$tmp = explode('-',$module_arr[$x],2);
					$params[$tmp[0]] = $tmp[1];
				} else {
					$params[$params_count++] = $module_arr[$x];
				}
			}
		}
		
	return $params;
	}
	
	
	
	function showElement($element,$params=null) {
		if(!$this->prepareElement(strtolower($element))) return false;
		require_once('elements/'.strtolower($element).'/'.$this->elementConfig['name'].'Element.php');
		$classname = $this->elementConfig['name'].'Element';
		if(isset($params)) new $classname($this,$params);
		else new $classname($this);
	}
	
	
	
	function setLanguage() {
		if(isset($_GET['lang']) && preg_match('/[a-z]{2}/',$_GET['lang']) && file_exists('lang/'.$_GET['lang'].'.po')) {
			$_SESSION['lang'] = $_GET['lang'];
		}
		if(!isset($_SESSION['lang'])) $_SESSION['lang'] = $this->CONFIG['default_lang'];
		$this->lang = $_SESSION['lang'];
	}
	
	
	
	function parseConfigFile($file) {
		if(!file_exists($file)) return array();
		$array = array();
		$fp = fopen($file,'r');
		if(!$fp) return false;
		
		while(!feof($fp)) {
			$row = trim(fgets($fp));
			if(preg_match('/^([a-zA-Z0-9_.\*-]+?)\s+=\s+(.+)$/i',$row,$arr)) {
				$array[$arr[1]] = $arr[2];
			}
		}
		
		fclose($fp);
	return $array;
	}
	
	function loadFrameworkConfig() {
		$config = $this->parseConfigFile('config/core.conf');
		
		foreach($config as $key => $value) {
			switch($key) {
				case 'default_module':
					if($value[0] == '/') $value = substr($value,1);
					if(substr($value,-1) == '/') $value = substr($value,0,-1);
					$config[$key] = $value;
					break;
				default:
					$config[$key] = $value;
					break;
			}
		}
		
		$this->CONFIG = $config;
	}
	
	function loadPrefix() {
		$prefixes = $this->parseConfigFile('config/prefixes.conf');
		
		if($this->consoleMode) {
			$tmp = explode('/',$_GET['module']);
			if(array_search($tmp[0], $prefixes) === false) {
				return false;
			}
			
			$this->prefix = $tmp[0];
			array_shift($tmp);
			$_GET['module'] = implode('/',$tmp);
			
			return true;
		}
		
		$theprefix = false;
		
		foreach($prefixes as $domain => $prefix) {
			$regex = preg_quote($domain);
			
			$regex = '/^'.str_replace('\*','.*?',$regex).'$/';
			if(preg_match($regex,$_SERVER['HTTP_HOST'])) {
				if($prefix[0] == '/') $prefix = substr($prefix,1);
				if(substr($prefix,-1) == '/') $prefix = substr($prefix,0,-1);
				$theprefix = $prefix;
				break;
			}
		}
		
		if($theprefix) {
			$this->prefix = $theprefix;
		} else {
			$this->prefix = $prefixes[$this->CONFIG['domain']];
		}
	
	return true;
	}
	
	function redirect($url,$keep_alive=false) {
		if(empty($url) || stristr($url,'\n') || stristr($url,'\r')) return false;
		
		if(isset($this->module->params['ajax'])) {
			echo '<script type="text/javascript">document.location.href="'.htmlspecialchars($url).'"</script>';
		} else {
			header("Location: ".$url);
		}
		
		if(!$keep_alive) {
			ob_flush();
			die();
		}
		
	return true;
	}
	
	function setTitle($string) {
		$this->title = $string;
	}
	
	function changeTitle() {
		$new_title = htmlspecialchars($this->title).' | '.$this->CONFIG['default_title'];
		if($this->module->CONFIG['mode'] == 'ajax') {
			echo '<script language="javascript">document.title = "'.$new_title.'";</script>';
		} else {
			$this->buffer = preg_replace('#<title>(.*?)</title>#','<title>'.$new_title.'</title>',empty($this->buffer) ? ob_get_clean() : $this->buffer,1);
		}
	}
	
	function setDescription($string) {
		$this->description = $string;
	}
	
	function changeDescription() {
		if(isset($this->description)) {
			$this->buffer = preg_replace('#meta name="Description" content="(.*?)"#','meta name="Description" content="'.$this->description.'"',empty($this->buffer) ? ob_get_clean() : $this->buffer,1);
		}
	}
	
	function addJavascript($filename) {
		array_push($this->javascripts,$filename);
	}
	
	function setJavascripts() {
		$javascripts = "";
		foreach($this->javascripts as $javascript) {
			$javascripts.= '<script src="'.$javascript.'" type="text/javascript"></script>'."\n";
		}
		$this->buffer = preg_replace('#</head>#',$javascripts."\n</head>",empty($this->buffer) ? ob_get_clean() : $this->buffer,1);
	}
	
	function addCSS($filename) {
		array_push($this->css,$filename);
	}
	
	function setCSS() {
		$csses = "";
		foreach($this->css as $css) {
			$csses.= '<link href="'.$css.'" rel="stylesheet" type="text/css" media="screen" />'."\n";
		}
		$this->buffer = preg_replace('#</head>#',$csses."\n</head>",empty($this->buffer) ? ob_get_clean() : $this->buffer,1);
	}
	
	function sendMail($to, $subject, $template, $vars,$attachment=null) {
		require_once($this->CONFIG['phpmailer_path']);
		
		
		if(!file_exists('mail_templates/'.$template.'_plain.tpl') || !file_exists('mail_templates/'.$template.'_html.tpl')) return false;
		
		$smarty = $this->spawnSmarty();
		$smarty->template_dir = 'mail_templates';
		$smarty->compile_dir  = 'smarty/templates_c/mail_templates';
		$smarty->cache_dir    = 'smarty/cache/mail_templates';
		
		if(!is_dir('smarty/templates_c/mail_templates')) mkdir('smarty/templates_c/mail_templates');
		if(!is_dir('smarty/cache/mail_templates')) mkdir('smarty/cache/mail_templates');
		
		$check = false;
		foreach($vars as $key => $value) {
			$smarty->assign($key,$value);
			if($key == 'alt_content') $check = true;
		}
		if(!$check) $vars['alt_content'] = strip_tags($vars['content']);
		$smarty->assign('alt_content',$vars['alt_content']);
		
		$mail = new PHPMailer();
		$mail->CharSet = 'utf-8';
		$mail->From = $this->CONFIG['mail_from'];
		$mail->FromName = $this->CONFIG['mail_from_name'];
		$mail->AddAddress($to);
		
		if(isset($vars['reply-to'])) {
			list($reply_to) = explode("\n",$vars['reply-to']);
			$mail->AddReplyTo($reply_to);
		}
		
		if(isset($vars['bcc'])) {
			if(is_array($vars['bcc'])) {
				foreach($vars['bcc'] as $bcc) {
					$mail->AddBCC($bcc);
				}
			} else {
				list($bcc) = explode("\n",$vars['bcc']);
				$mail->AddBCC($bcc);
			}
		}
		
		$mail->isHTML(true);
		$mail->Subject	= !libString::isUTF8($subject)?utf8_encode($subject):$subject;
		if(isset($attachment)) $mail->addAttachment($attachment);
		$body = $smarty->fetch($template.'_html.tpl');
		
		$alt_body = $smarty->fetch($template.'_plain.tpl');
		$mail->Body		= !libString::isUTF8($body)?utf8_encode($body):$body;
		$mail->AltBody	= !libString::isUTF8($alt_body)?utf8_encode($alt_body):$alt_body;
		
		if(!$mail->Send()) return false;
	return true;
	}
	
	function spawnSmarty() {
		$smarty = new CustomSmarty();
		
		$functions = get_defined_functions();
		$functions = $functions['user'];
		foreach($functions as $name) {
			if(preg_match('/^smarty_custom_function_(.*)$/',$name,$arr)) {
				$smarty->register_function($arr[1],$arr[0]);
			} elseif(preg_match('/^smarty_custom_modifier_(.*)$/',$name,$arr)) {
				$smarty->register_modifier($arr[1],$arr[0]);
			}
		}
	return $smarty;
	}
	
	function getSmarty($template_dir, $compile_dir, $cache_dir) {
		if(!is_dir($compile_dir)) mkdir($compile_dir);
		if(!is_dir($cache_dir)) mkdir($cache_dir);
		
		$this->smartyRestore['template_dir'] = $this->smarty->template_dir;
		$this->smartyRestore['compile_dir'] = $this->smarty->compile_dir;
		$this->smartyRestore['cache_dir'] = $this->smarty->cache_dir;
		
		$this->smarty->template_dir	= $template_dir;
		$this->smarty->compile_dir	= $compile_dir;
		$this->smarty->cache_dir	= $cache_dir;
	return $this->smarty;
	}
	
	function restoreSmarty() {
		$this->smarty->template_dir = $this->smartyRestore['template_dir'];
		$this->smarty->compile_dir = $this->smartyRestore['compile_dir'];
		$this->smarty->cache_dir = $this->smartyRestore['cache_dir'];
		
		$this->smartyRestore = array();
	return true;
	}
	
	function getClientData() {
		if($this->consoleMode) return array('ip' => '127.0.0.1', 'engine' => 'console');
		
		$data = array();
		$data['ip'] = $_SERVER['REMOTE_ADDR'];
		if(preg_match('/MSIE (.*?);/',$_SERVER['HTTP_USER_AGENT'],$arr)) {
			$ver = $arr[1];
			if($ver >= 5.0) $data['engine'] = 'ie5';
			if($ver >= 6.0) $data['engine'] = 'ie6';
			if($ver >= 7.0) $data['engine'] = 'ie7';
			if($ver >= 8.0) $data['engine'] = 'ie8';
			if($ver >= 9.0) $data['engine'] = 'ie9';
		}
		elseif(stristr($_SERVER['HTTP_USER_AGENT'],'presto')) $data['engine'] = 'presto';
		elseif(stristr($_SERVER['HTTP_USER_AGENT'],'applewebkit')) $data['engine'] = 'webkit';
		elseif(stristr($_SERVER['HTTP_USER_AGENT'],'gecko')) $data['engine'] = 'gecko';
		else $data['engine'] = 'unknown';
		
	return $data;
	}
	
	function isLoggedIn() {
		if(!isset($_SESSION['user']['id']) || empty($_SESSION['user']['id'])) return false;
	return true;
	}
	
}
?>
