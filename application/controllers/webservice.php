<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Webservice extends MY_Controller {
	
	function __construct()
	{
		parent::__construct();
		
		$this->check_credentials();

		$this->token = $this->session->userdata('token');
		
		$this->load->library('feeder', array('url' => $this->session->userdata('wsdl')));
	}
	
	function list_table()
	{
		$result = $this->feeder->ListTable($this->token);

		foreach ($result['result'] as &$table)
		{
			// $column = $this->client->getProxy()->GetDictionary($this->token, $table['table']);
			// $table['column_set'] = $column['result'];
		}

		$this->smarty->assign('data_set', $result['result']);

		$this->smarty->display('webservice/list_table.tpl');
	}

	function table_column($table)
	{
		// Ambil kolom
		$result = $this->feeder->GetDictionary($this->token, $table);

		$this->smarty->assign('data_set', $result['result']);

		$this->smarty->display('webservice/table_column.tpl');
	}

	function table_data($table)
	{
		// Inisialisasi parameter
		$filter	= null;
		$order	= null;
		$limit	= 50;  // per page
		$offset	= null;
		
		// Ambil kolom
		$column = $this->feeder->GetDictionary($this->token, $table);
		
		// Filter Khusus
		if ($table == FEEDER_SATUAN_PENDIDIKAN)
		{
			$filter = "npsn = '".$this->session->userdata('username')."'";
		}
		
		if ($table == 'sms')
		{
			// Ambil id_sp dari PT
			$result = $this->feeder->GetRecord($this->token, FEEDER_SATUAN_PENDIDIKAN, "npsn = '".$this->session->userdata('username')."'");
			$id_sp = $result['result']['id_sp'];
			unset($result);
			
			$filter = "id_sp = '{$id_sp}'";
		}

		// Ambil data --> params: token, tabel, filter, order, limit, offset
		$result = $this->feeder->GetRecordset($this->token, $table, $filter, $order, $limit, $offset);

		
		$this->smarty->assign('column_set', $column['result']);
		$this->smarty->assign('data_set', $result['result']);

		$this->smarty->display('webservice/table_data.tpl');
	}
}
