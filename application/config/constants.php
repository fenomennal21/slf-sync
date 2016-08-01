<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| File and Directory Modes
|--------------------------------------------------------------------------
|
| These prefs are used when checking and setting modes when working
| with the file system.  The defaults are fine on servers with proper
| security, but you may wish (or even need) to change the values in
| certain environments (Apache running a separate process for each
| user, PHP under CGI with Apache suEXEC, etc.).  Octal values should
| always be used to set the mode correctly.
|
*/
define('FILE_READ_MODE', 0644);
define('FILE_WRITE_MODE', 0666);
define('DIR_READ_MODE', 0755);
define('DIR_WRITE_MODE', 0777);

/*
|--------------------------------------------------------------------------
| File Stream Modes
|--------------------------------------------------------------------------
|
| These modes are used when working with fopen()/popen()
|
*/

define('FOPEN_READ',							'rb');
define('FOPEN_READ_WRITE',						'r+b');
define('FOPEN_WRITE_CREATE_DESTRUCTIVE',		'wb'); // truncates existing file data, use with care
define('FOPEN_READ_WRITE_CREATE_DESTRUCTIVE',	'w+b'); // truncates existing file data, use with care
define('FOPEN_WRITE_CREATE',					'ab');
define('FOPEN_READ_WRITE_CREATE',				'a+b');
define('FOPEN_WRITE_CREATE_STRICT',				'xb');
define('FOPEN_READ_WRITE_CREATE_STRICT',		'x+b');


/*
|--------------------------------------------------------------------------
| PDDIKTI Constant
|--------------------------------------------------------------------------
*/
define('FEEDER_SATUAN_PENDIDIKAN',	'satuan_pendidikan');
define('FEEDER_SMS',				'sms');
define('FEEDER_JENJANG_PENDIDIKAN',	'jenjang_pendidikan');
define('FEEDER_MAHASISWA',			'mahasiswa');
define('FEEDER_MAHASISWA_PT',		'mahasiswa_pt');
define('FEEDER_KULIAH_MAHASISWA',	'kuliah_mahasiswa');
define('FEEDER_KURIKULUM',			'kurikulum');
define('FEEDER_MATA_KULIAH',		'mata_kuliah');
define('FEEDER_MK_KURIKULUM',		'mata_kuliah_kurikulum');


/*
|--------------------------------------------------------------------------
| Status Sinkronisasi
|--------------------------------------------------------------------------
*/
define('SYNC_STATUS_PROSES',	'proses');
define('SYNC_STATUS_DONE',		'done');


/*
|--------------------------------------------------------------------------
| Mode Sinkronisasi
|--------------------------------------------------------------------------
*/
define('MODE_SYNC',						'sync');
define('MODE_AMBIL_DATA_FEEDER',		'ambil_data_feeder');
define('MODE_AMBIL_DATA_FEEDER_2',		'ambil_data_feeder_2');
define('MODE_AMBIL_DATA_FEEDER_3',		'ambil_data_feeder_3');
define('MODE_AMBIL_DATA_LANGITAN',		'ambil_data_langitan');
define('MODE_AMBIL_DATA_LANGITAN_2',	'ambil_data_langitan_2');
define('MODE_AMBIL_DATA_LANGITAN_3',	'ambil_data_langitan_3');

/* End of file constants.php */
/* Location: ./application/config/constants.php */