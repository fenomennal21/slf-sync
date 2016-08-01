<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

require_once APPPATH . 'third_party/smarty/libs/Smarty.class.php';

class CI_Smarty extends Smarty
{
	function __construct()
	{
		parent::__construct();
		
		$this->setTemplateDir(APPPATH . 'views');
		$this->setCompileDir(APPPATH . 'cache');
	}
	
	function CI_Smarty()
	{
		parent::Smarty();
		
		$this->setTemplateDir(APPPATH . 'views');
		$this->setCompileDir(APPPATH . 'cache');
	}
}

/* End of file Smarty.php */