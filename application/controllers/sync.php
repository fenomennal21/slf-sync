<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @property CI_Remotedb $rdb Remote DB Sistem Langitan
 * @property string $token Token webservice
 * @property string $npsn Kode Perguruan Tinggi
 * @property array $satuan_pendidikan Row: satuan_pendidikan
 * 
 */
class Sync extends MY_Controller 
{
	
	function __construct()
	{
		parent::__construct();
		
		$this->check_credentials();

		// Inisialisasi Token dan Satuan Pendidikan
		$this->token = $this->session->userdata('token');
		$this->satuan_pendidikan = xcache_get(FEEDER_SATUAN_PENDIDIKAN);
		
		// Inisialisasi URL Feeder
		$this->load->library('feeder', array('url' => $this->session->userdata('wsdl')));
		
		// Inisialisasi Library RemoteDB
		$this->load->library('remotedb', NULL, 'rdb');
		$this->rdb->set_url($this->session->userdata('langitan'));
	}
	
	/**
	 * GET /sync/mahasiswa
	 */
	function mahasiswa()
	{
		$jumlah = array();
		
		// Ambil jumlah mahasiswa di feeder
		$response = $this->feeder->GetCountRecordset($this->token, FEEDER_MAHASISWA, null);
		$jumlah['feeder'] = $response['result'];

		// Ambil jumlah mahasiswa di Sistem Langitan & yg sudah link
		$mhs_set = $this->rdb->QueryToArray(
			"/* Jumlah Semua Data */
			SELECT count(*) as jumlah FROM mahasiswa m
			JOIN pengguna p ON p.id_pengguna = m.id_pengguna
			JOIN perguruan_tinggi pt on pt.id_perguruan_tinggi = p.id_perguruan_tinggi
			WHERE npsn = '{$this->satuan_pendidikan['npsn']}'
			UNION ALL
			/* Jumlah Sudah Link*/
			SELECT count(*) as jumlah FROM mahasiswa m
			JOIN feeder_mahasiswa fm on fm.id_mhs = m.id_mhs
			JOIN pengguna p ON p.id_pengguna = m.id_pengguna
			JOIN perguruan_tinggi pt on pt.id_perguruan_tinggi = p.id_perguruan_tinggi
			WHERE npsn = '{$this->satuan_pendidikan['npsn']}'
			UNION ALL
			/* Jumlah Bakal Update */
			SELECT count(*) as jumlah FROM mahasiswa m
			JOIN feeder_mahasiswa fm on fm.id_mhs = m.id_mhs
			JOIN pengguna p ON p.id_pengguna = m.id_pengguna
			JOIN perguruan_tinggi pt on pt.id_perguruan_tinggi = p.id_perguruan_tinggi
			WHERE npsn = '{$this->satuan_pendidikan['npsn']}' AND fm.last_sync < fm.last_update
			UNION ALL
			/* Jumlah Bakal Insert */
			SELECT count(*) as jumlah FROM mahasiswa m
			JOIN pengguna p ON p.id_pengguna = m.id_pengguna
			JOIN perguruan_tinggi pt on pt.id_perguruan_tinggi = p.id_perguruan_tinggi
			WHERE npsn = '{$this->satuan_pendidikan['npsn']}' AND m.id_mhs NOT IN (SELECT id_mhs FROM feeder_mahasiswa)");
		$jumlah['langitan'] = $mhs_set[0]['JUMLAH'];
		$jumlah['linked'] = $mhs_set[1]['JUMLAH'];
		$jumlah['update'] = $mhs_set[2]['JUMLAH'];
		$jumlah['insert'] = $mhs_set[3]['JUMLAH'];
		$this->smarty->assign('jumlah', $jumlah);
		
		// Ambil list Prodi :
		$program_studi_set = $this->rdb->QueryToArray(
			"SELECT id_program_studi, nm_jenjang, nm_program_studi, kode_program_studi FROM program_studi ps
			JOIN jenjang j on j.id_jenjang = ps.id_jenjang
			JOIN fakultas f on f.id_fakultas = ps.id_fakultas
			JOIN perguruan_tinggi pt ON pt.id_perguruan_tinggi = f.id_perguruan_tinggi
			WHERE pt.npsn = '{$this->satuan_pendidikan['npsn']}'");
		$this->smarty->assign('program_studi_set', $program_studi_set);
		
		// Ambil Semua angkatan yg ada :
		$angkatan_set = $this->rdb->QueryToArray(
			"SELECT DISTINCT thn_angkatan_mhs FROM mahasiswa m
			JOIN pengguna p ON p.id_pengguna = m.id_pengguna
			JOIN perguruan_tinggi pt ON pt.id_perguruan_tinggi = p.id_perguruan_tinggi
			WHERE pt.npsn = '{$this->satuan_pendidikan['npsn']}' ORDER BY 1 DESC");
		$this->smarty->assign('angkatan_set', $angkatan_set);
		
		$this->smarty->assign('url_sync', site_url('sync/start/'.$this->uri->segment(2)));
		$this->smarty->display('sync/'.$this->uri->segment(2).'.tpl');
	}
	
	/**
	 * Ajax-GET /sync/mahasiswa_data/
	 * @param string $kode_prodi Kode program studi versi Feeder
	 * @param int $angkatan Tahun angkatan mahasiswa
	 */
	function mahasiswa_data($kode_prodi, $angkatan)
	{
		// Khusus UMAHA menggunakan filter nim agar match
		if ($this->satuan_pendidikan['npsn'] == '071086')
		{
			// Ambil informasi format NIM 
			$format_set = $this->rdb->QueryToArray(
				"SELECT nm_program_studi, coalesce(f.format_nim_fakultas, format_nim_pt) as format_nim, f.kode_nim_fakultas, ps.kode_nim_prodi
				FROM perguruan_tinggi pt 
				JOIN fakultas f ON f.id_perguruan_tinggi = pt.id_perguruan_tinggi
				JOIN program_studi ps ON ps.id_fakultas = f.id_fakultas
				WHERE pt.npsn = '{$this->satuan_pendidikan['npsn']}' and ps.kode_program_studi = '{$kode_prodi}'");

			$format = $format_set[0];

			$format_nim = str_replace('[F]', $format['KODE_NIM_FAKULTAS'], $format['FORMAT_NIM']);
			$format_nim = str_replace('[PS]', $format['KODE_NIM_PRODI'], $format_nim);
			$format_nim = str_replace('[A]', substr($angkatan, -2), $format_nim);
			$format_nim = str_replace('[Seri]', '___', $format_nim);

			// Jika FIKES 2014 kebawah, pakai format lama
			if ($kode_prodi == '13453' && $angkatan <= 2014)
			{
				$format_nim = substr($angkatan, -2) . '___';
			}
			
			// Ambil jumlah mahasiswa di feeder
			$response = $this->feeder->GetCountRecordset($this->token, FEEDER_MAHASISWA_PT, "p.nipd like '{$format_nim}'");
			$jumlah['feeder'] = $response['result'];
		}
		else
		{
			// Ambil jumlah mahasiswa di feeder
			$response = $this->feeder->GetCountRecordset($this->token, FEEDER_MAHASISWA_PT, "kode_prodi like '{$kode_prodi}%' and mulai_smt like '{$angkatan}%'");
			$jumlah['feeder'] = $response['result'];
		}
		
		
		// Ambil jumlah mahasiswa di Sistem Langitan & yg sudah link
		$mhs_set = $this->rdb->QueryToArray(
			"/* Jumlah Semua Data */
			SELECT count(*) as jumlah FROM mahasiswa m
			JOIN pengguna p ON p.id_pengguna = m.id_pengguna
			JOIN program_studi ps ON ps.id_program_studi = m.id_program_studi
			JOIN perguruan_tinggi pt on pt.id_perguruan_tinggi = p.id_perguruan_tinggi
			WHERE npsn = '{$this->satuan_pendidikan['npsn']}' AND ps.kode_program_studi = '{$kode_prodi}' AND m.thn_angkatan_mhs = '{$angkatan}'
			UNION ALL
			/* Jumlah Sudah Link*/
			SELECT count(*) as jumlah FROM mahasiswa m
			JOIN feeder_mahasiswa fm on fm.id_mhs = m.id_mhs
			JOIN pengguna p ON p.id_pengguna = m.id_pengguna
			JOIN program_studi ps ON ps.id_program_studi = m.id_program_studi
			JOIN perguruan_tinggi pt on pt.id_perguruan_tinggi = p.id_perguruan_tinggi
			WHERE npsn = '{$this->satuan_pendidikan['npsn']}' AND ps.kode_program_studi = '{$kode_prodi}' AND m.thn_angkatan_mhs = '{$angkatan}'
			UNION ALL
			/* Jumlah Bakal Update */
			SELECT count(*) as jumlah FROM mahasiswa m
			JOIN feeder_mahasiswa fm on fm.id_mhs = m.id_mhs
			JOIN pengguna p ON p.id_pengguna = m.id_pengguna
			JOIN program_studi ps ON ps.id_program_studi = m.id_program_studi
			JOIN perguruan_tinggi pt on pt.id_perguruan_tinggi = p.id_perguruan_tinggi
			WHERE npsn = '{$this->satuan_pendidikan['npsn']}' AND ps.kode_program_studi = '{$kode_prodi}' AND m.thn_angkatan_mhs = '{$angkatan}' AND fm.last_sync < fm.last_update
			UNION ALL
			/* Jumlah Bakal Insert */
			SELECT count(*) as jumlah FROM mahasiswa m
			JOIN pengguna p ON p.id_pengguna = m.id_pengguna
			JOIN program_studi ps ON ps.id_program_studi = m.id_program_studi
			JOIN perguruan_tinggi pt on pt.id_perguruan_tinggi = p.id_perguruan_tinggi
			WHERE npsn = '{$this->satuan_pendidikan['npsn']}' AND ps.kode_program_studi = '{$kode_prodi}' AND m.thn_angkatan_mhs = '{$angkatan}' AND m.id_mhs NOT IN (SELECT id_mhs FROM feeder_mahasiswa)");
		$jumlah['langitan'] = $mhs_set[0]['JUMLAH'];
		$jumlah['linked'] = $mhs_set[1]['JUMLAH'];
		$jumlah['update'] = $mhs_set[2]['JUMLAH'];
		$jumlah['insert'] = $mhs_set[3]['JUMLAH'];
		
		echo json_encode($jumlah);
	}
	
	/**
	 * GET /sync/mata_kuliah
	 */
	function mata_kuliah()
	{
		$jumlah = array();
		
		// Ambil jumlah mata_kuliah di feeder
		$response = $this->feeder->GetCountRecordset($this->token, FEEDER_MATA_KULIAH, null);
		$jumlah['feeder'] = $response['result'];
		
		// Ambil jumlah mata kuliah di Sistem Langitan & yg sudah link
		$mk_set = $this->rdb->QueryToArray(
			"/* Jumlah Semua Data */
			SELECT count(*) AS jumlah FROM mata_kuliah mk
			JOIN program_studi ps ON ps.id_program_studi = mk.id_program_studi
			JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
			JOIN perguruan_tinggi pt ON pt.id_perguruan_tinggi = f.id_perguruan_tinggi
			WHERE pt.npsn = '{$this->satuan_pendidikan['npsn']}'
			UNION ALL
			/* Jumlah sudah link */
			SELECT count(*) AS jumlah FROM mata_kuliah mk
			JOIN feeder_mata_kuliah fmk ON fmk.id_mata_kuliah = mk.id_mata_kuliah
			JOIN program_studi ps ON ps.id_program_studi = mk.id_program_studi
			JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
			JOIN perguruan_tinggi pt ON pt.id_perguruan_tinggi = f.id_perguruan_tinggi
			WHERE pt.npsn = '{$this->satuan_pendidikan['npsn']}'
			UNION ALL
			/* Jumlah bakal update */
			SELECT count(*) AS jumlah FROM mata_kuliah mk
			JOIN feeder_mata_kuliah fmk ON fmk.id_mata_kuliah = mk.id_mata_kuliah
			JOIN program_studi ps ON ps.id_program_studi = mk.id_program_studi
			JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
			JOIN perguruan_tinggi pt ON pt.id_perguruan_tinggi = f.id_perguruan_tinggi
			WHERE pt.npsn = '{$this->satuan_pendidikan['npsn']}' AND fmk.last_sync < fmk.last_update
			UNION ALL
			/* Jumlah bakal insert */
			SELECT count(*) AS jumlah FROM mata_kuliah mk
			JOIN program_studi ps ON ps.id_program_studi = mk.id_program_studi
			JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
			JOIN perguruan_tinggi pt ON pt.id_perguruan_tinggi = f.id_perguruan_tinggi
			WHERE pt.npsn = '{$this->satuan_pendidikan['npsn']}' AND mk.id_mata_kuliah NOT IN (SELECT id_mata_kuliah FROM feeder_mata_kuliah)");
		$jumlah['langitan'] = $mk_set[0]['JUMLAH'];
		$jumlah['linked'] = $mk_set[1]['JUMLAH'];
		$jumlah['update'] = $mk_set[2]['JUMLAH'];
		$jumlah['insert'] = $mk_set[3]['JUMLAH'];
		$this->smarty->assign('jumlah', $jumlah);
		
		// Ambil list Prodi :
		$program_studi_set = $this->rdb->QueryToArray(
			"SELECT id_program_studi, nm_jenjang, nm_program_studi, kode_program_studi FROM program_studi ps
			JOIN jenjang j on j.id_jenjang = ps.id_jenjang
			JOIN fakultas f on f.id_fakultas = ps.id_fakultas
			JOIN perguruan_tinggi pt ON pt.id_perguruan_tinggi = f.id_perguruan_tinggi
			WHERE pt.npsn = '{$this->satuan_pendidikan['npsn']}'");
		$this->smarty->assign('program_studi_set', $program_studi_set);
		
		$this->smarty->assign('url_sync', site_url('sync/start/'.$this->uri->segment(2)));
		$this->smarty->display('sync/'.$this->uri->segment(2).'.tpl');
	}
	
	/**
	 * Ajax-GET /sync/mata_kuliah_data/
	 * @param string $kode_prodi Kode program studi versi Feeder
	 */
	function mata_kuliah_data($kode_prodi)
	{
		// Ambil id_sms dari kode prodi
		$response = $this->feeder->GetRecord($this->token, FEEDER_SMS, "p.id_sp = '{$this->satuan_pendidikan['id_sp']}' and p.kode_prodi like '{$kode_prodi}%'");
		$id_sms = $response['result']['id_sms'];
		
		// Ambil jumlah mata kuliah di feeder
		$response = $this->feeder->GetCountRecordset($this->token, FEEDER_MATA_KULIAH, "id_sms = '{$id_sms}'");
		$jumlah['feeder'] = $response['result'];
		
		// Ambil jumlah mata kuliah di Sistem Langitan & yg sudah link
		$mk_set = $this->rdb->QueryToArray(
			"/* Jumlah Semua Data */
			SELECT count(*) AS jumlah FROM mata_kuliah mk
			JOIN program_studi ps ON ps.id_program_studi = mk.id_program_studi
			JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
			JOIN perguruan_tinggi pt ON pt.id_perguruan_tinggi = f.id_perguruan_tinggi
			WHERE pt.npsn = '{$this->satuan_pendidikan['npsn']}' AND ps.kode_program_studi = '{$kode_prodi}'
			UNION ALL
			/* Jumlah sudah link */
			SELECT count(*) AS jumlah FROM mata_kuliah mk
			JOIN feeder_mata_kuliah fmk ON fmk.id_mata_kuliah = mk.id_mata_kuliah
			JOIN program_studi ps ON ps.id_program_studi = mk.id_program_studi
			JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
			JOIN perguruan_tinggi pt ON pt.id_perguruan_tinggi = f.id_perguruan_tinggi
			WHERE pt.npsn = '{$this->satuan_pendidikan['npsn']}' AND ps.kode_program_studi = '{$kode_prodi}'
			UNION ALL
			/* Jumlah bakal update */
			SELECT count(*) AS jumlah FROM mata_kuliah mk
			JOIN feeder_mata_kuliah fmk ON fmk.id_mata_kuliah = mk.id_mata_kuliah
			JOIN program_studi ps ON ps.id_program_studi = mk.id_program_studi
			JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
			JOIN perguruan_tinggi pt ON pt.id_perguruan_tinggi = f.id_perguruan_tinggi
			WHERE pt.npsn = '{$this->satuan_pendidikan['npsn']}' AND ps.kode_program_studi = '{$kode_prodi}' AND fmk.last_sync < fmk.last_update
			UNION ALL
			/* Jumlah bakal insert */
			SELECT count(*) AS jumlah FROM mata_kuliah mk
			JOIN program_studi ps ON ps.id_program_studi = mk.id_program_studi
			JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
			JOIN perguruan_tinggi pt ON pt.id_perguruan_tinggi = f.id_perguruan_tinggi
			WHERE pt.npsn = '{$this->satuan_pendidikan['npsn']}' AND ps.kode_program_studi = '{$kode_prodi}' AND mk.id_mata_kuliah NOT IN (SELECT id_mata_kuliah FROM feeder_mata_kuliah)");
		$jumlah['langitan'] = $mk_set[0]['JUMLAH'];
		$jumlah['linked'] = $mk_set[1]['JUMLAH'];
		$jumlah['update'] = $mk_set[2]['JUMLAH'];
		$jumlah['insert'] = $mk_set[3]['JUMLAH'];
		
		echo json_encode($jumlah);
	}
	
	/**
	 * GET /sync/link_program_studi
	 */
	function link_program_studi()
	{	
		$jumlah = array(
			'feeder'	=> '-',
			'langitan'	=> '-',
			'linked'	=> '-'
		);
		
		// Jumlah di feeder
		$result = $this->feeder->GetCountRecordset($this->token, FEEDER_SMS, "id_sp = '{$this->satuan_pendidikan['id_sp']}'");
		$jumlah['feeder'] = $result['result'];
		
		// Jumlah di langitan
		$result = $this->rdb->QueryToArray(
			"SELECT count(*) as jumlah FROM program_studi ps
			JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
			JOIN perguruan_tinggi pt on pt.id_perguruan_tinggi = f.id_perguruan_tinggi
			WHERE npsn = '{$this->satuan_pendidikan['npsn']}'");
		$jumlah['langitan'] = $result[0]['JUMLAH'];
		
		// Jumlah yg sudah link (punya ID_SMS)
		$result = $this->rdb->QueryToArray(
			"SELECT count(*) as jumlah FROM program_studi ps
			JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
			JOIN perguruan_tinggi pt on pt.id_perguruan_tinggi = f.id_perguruan_tinggi
			JOIN feeder_sms feed ON feed.id_program_studi = ps.id_program_studi
			WHERE npsn = '{$this->satuan_pendidikan['npsn']}'");
		$jumlah['linked'] = $result[0]['JUMLAH'];
		
		$this->smarty->assign('jumlah', $jumlah);
		
		$this->smarty->display('sync/link_program_studi.tpl');
	}
	
	/**
	 * GET /sync/link_mahasiswa/
	 */
	function link_mahasiswa()
	{
		$jumlah = array();
		
		// Ambil jumlah mahasiswa di feeder
		$response = $this->feeder->GetCountRecordset($this->token, FEEDER_MAHASISWA, null);
		$jumlah['feeder'] = $response['result'];
		

		// Ambil jumlah mahasiswa di Sistem Langitan & yg sudah link
		$mhs_set = $this->rdb->QueryToArray(
			"SELECT count(*) as jumlah FROM mahasiswa m
			JOIN pengguna p ON p.id_pengguna = m.id_pengguna
			JOIN perguruan_tinggi pt on pt.id_perguruan_tinggi = p.id_perguruan_tinggi
			WHERE npsn = '{$this->satuan_pendidikan['npsn']}'
			UNION ALL
			SELECT count(*) as jumlah FROM mahasiswa m
			JOIN feeder_mahasiswa fm on fm.id_mhs = m.id_mhs
			JOIN pengguna p ON p.id_pengguna = m.id_pengguna
			JOIN perguruan_tinggi pt on pt.id_perguruan_tinggi = p.id_perguruan_tinggi
			WHERE npsn = '{$this->satuan_pendidikan['npsn']}'");
		$jumlah['langitan'] = $mhs_set[0]['JUMLAH'];
		$jumlah['linked'] = $mhs_set[1]['JUMLAH'];
		
		$this->smarty->assign('jumlah', $jumlah);
		
		$this->smarty->assign('url_sync', site_url('sync/start/'.$this->uri->segment(2)));
		$this->smarty->display('sync/'.$this->uri->segment(2).'.tpl');
	}
	
	/**
	 * GET /sync/link_mahasiswa_pt/
	 */
	function link_mahasiswa_pt()
	{
		$jumlah = array();
		
		// Ambil jumlah mahasiswa di feeder
		$response = $this->feeder->GetCountRecordset($this->token, FEEDER_MAHASISWA_PT, null);
		$jumlah['feeder'] = $response['result'];

		// Ambil jumlah mahasiswa di Sistem Langitan & yg sudah link
		$mhs_set = $this->rdb->QueryToArray(
			"SELECT count(*) as jumlah FROM mahasiswa m
			JOIN pengguna p ON p.id_pengguna = m.id_pengguna
			JOIN perguruan_tinggi pt on pt.id_perguruan_tinggi = p.id_perguruan_tinggi
			WHERE npsn = '{$this->satuan_pendidikan['npsn']}'
			UNION ALL
			SELECT count(*) as jumlah FROM mahasiswa m
			JOIN feeder_mahasiswa_pt fm on fm.id_mhs = m.id_mhs
			JOIN pengguna p ON p.id_pengguna = m.id_pengguna
			JOIN perguruan_tinggi pt on pt.id_perguruan_tinggi = p.id_perguruan_tinggi
			WHERE npsn = '{$this->satuan_pendidikan['npsn']}'");
		$jumlah['langitan'] = $mhs_set[0]['JUMLAH'];
		$jumlah['linked'] = $mhs_set[1]['JUMLAH'];
		$this->smarty->assign('jumlah', $jumlah);
		
		$this->smarty->assign('url_sync', site_url('sync/start/'.$this->uri->segment(2)));
		$this->smarty->display('sync/'.$this->uri->segment(2).'.tpl');
	}
	
	/**
	 * Tampilan awal sebelum start sinkronisasi
	 */
	function start($mode)
	{
		if ($mode == 'mahasiswa')
		{
			$kode_prodi	= $this->input->post('kode_prodi');
			$angkatan	= $this->input->post('angkatan');
			
			$program_studi_set = $this->rdb->QueryToArray(
				"SELECT nm_jenjang, nm_program_studi FROM program_studi ps
				JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
				JOIN jenjang j ON j.id_jenjang = ps.id_jenjang
				JOIN perguruan_tinggi pt ON pt.id_perguruan_tinggi = f.id_perguruan_tinggi
				WHERE pt.npsn = '{$this->satuan_pendidikan['npsn']}' AND ps.kode_program_studi = '{$kode_prodi}'");
			
			$this->smarty->assign('jenis_sinkronisasi', 'Mahasiswa '.$program_studi_set[0]['NM_JENJANG'].' '.$program_studi_set[0]['NM_PROGRAM_STUDI'].' Angkatan '.$angkatan);
			$this->smarty->assign('url', site_url('sync/proses/'.$mode));
		}
		
		if ($mode == 'mata_kuliah')
		{
			$kode_prodi	= $this->input->post('kode_prodi');
			
			$program_studi_set = $this->rdb->QueryToArray(
				"SELECT nm_jenjang, nm_program_studi FROM program_studi ps
				JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
				JOIN jenjang j ON j.id_jenjang = ps.id_jenjang
				JOIN perguruan_tinggi pt ON pt.id_perguruan_tinggi = f.id_perguruan_tinggi
				WHERE pt.npsn = '{$this->satuan_pendidikan['npsn']}' AND ps.kode_program_studi = '{$kode_prodi}'");
				
			$this->smarty->assign('jenis_sinkronisasi', 'Mata Kuliah '.$program_studi_set[0]['NM_JENJANG'].' '.$program_studi_set[0]['NM_PROGRAM_STUDI']);
			$this->smarty->assign('url', site_url('sync/proses/'.$mode));
		}
		
		if ($mode == 'link_program_studi')
		{
			$this->smarty->assign('jenis_sinkronisasi', 'Link Program Studi');
			$this->smarty->assign('url', site_url('sync/proses/'.$mode));
		}
		
		if ($mode == 'link_mahasiswa')
		{
			$this->smarty->assign('jenis_sinkronisasi', 'Link Mahasiswa');
			$this->smarty->assign('url', site_url('sync/proses/'.$mode));
		}
		
		if ($mode == 'link_mahasiswa_pt')
		{
			$this->smarty->assign('jenis_sinkronisasi', 'Link Mahasiswa PT');
			$this->smarty->assign('url', site_url('sync/proses/'.$mode));
		}
		
		// Internal UMAHA
		if ($mode == 'link_mahasiswa_pt2')
		{
			$this->smarty->assign('jenis_sinkronisasi', 'Cek Mahasiswa PT belum ada di Sistem Langitan');
			$this->smarty->assign('url', site_url('sync/proses/'.$mode));
		}
		
		// Internal UMAHA
		if ($mode == 'link_lulus')
		{
			$this->smarty->assign('jenis_sinkronisasi', 'Update lulusan ke Sistem Langitan');
			$this->smarty->assign('url', site_url('sync/proses/'.$mode));
		}
		
		if ($mode == 'hapus_mk_kurikulum')
		{
			$this->smarty->assign('jenis_sinkronisasi', 'Hapus Mata Kuliah Kurikulum');
			$this->smarty->assign('url', site_url('sync/proses/'.$mode));
		}
		
		$this->smarty->display('sync/start.tpl');
	}
	
	/**
	 * Pemrosesan sinkronisasi
	 */
	function proses($mode)
	{
		// Force delete
		if ($_SERVER['REQUEST_METHOD'] != 'POST') { return; }
		
		if ($mode == 'mahasiswa')
		{
			$this->proses_mahasiswa();
		}
		
		else if ($mode == 'mata_kuliah')
		{
			$this->proses_mata_kuliah();
		}
		
		else if ($mode == 'link_program_studi')
		{
			$this->proses_link_program_studi();
		}
		
		else if ($mode == 'link_mahasiswa')
		{
			$this->proses_link_mahasiswa();
		}
		
		else if ($mode == 'link_mahasiswa_pt')
		{
			$this->proses_link_mahasiswa_pt();
		}
		
		else if ($mode == 'link_mahasiswa_pt2')
		{
			$this->proses_link_mahasiswa_pt2();
		}
		
		else if ($mode == 'link_lulus')
		{
			$this->proses_link_lulus();
		}
		
		else if ($mode == 'hapus_mk_kurikulum')
		{
			$this->proses_hapus_mk_kurikulum();
		}
		
		else 
		{
			echo json_encode(array('status' => 'done', 'message' => 'Not Implemented()'));
		}
	}
	
	
	private function proses_mahasiswa()
	{
		$result = array('status'=> '', 'time' => '', 'message' => '', 'nextUrl' => site_url('sync/proses/'. $this->uri->segment(3)), 'params'	=> '');
		
		$mode	= isset($_POST['mode']) ? $_POST['mode'] : MODE_AMBIL_DATA_LANGITAN;
		
		// -----------------------------------
		// Ambil data untuk Insert
		// -----------------------------------
		if ($mode == MODE_AMBIL_DATA_LANGITAN)
		{
			// Filter Prodi & Angkatan
			$kode_prodi = $this->input->post('kode_prodi');
			$angkatan	= $this->input->post('angkatan');
			
			// Mendapatkan id_sms
			$response = $this->feeder->GetRecord($this->token, FEEDER_SMS, "id_sp = '{$this->satuan_pendidikan['id_sp']}' AND trim(kode_prodi) = '{$kode_prodi}'");
			$sms = $response['result'];
			
			// Ambil mahasiswa yg akan insert
			$mahasiswa_set = $this->rdb->QueryToArray(
				"SELECT 
					m.id_mhs,
					
					/* Informasi Mahasiswa */
					p.nm_pengguna as nm_pd,
					decode(p.kelamin_pengguna, 1, 'L', 2, 'P', NULL, 'L') as jk, /* default Laki-Laki */
					null as nisn, 
					null as nik,
					NVL((SELECT nm_kota FROM kota WHERE kota.id_kota = m.LAHIR_KOTA_MHS), 'Belum Terekam') as tmpt_lahir,
					NVL(to_char(tgl_lahir_pengguna, 'YYYY-MM-DD'), '1900-01-01') as tgl_lahir,
					NVL((select id_feeder from agama where agama.id_agama = p.id_agama), 1) as id_agama,  /* default Islam */
					0 as id_kk,
					'{$this->satuan_pendidikan['id_sp']}' as id_sp,
					
					/* Info tempat tinggal */
					SUBSTR(COALESCE(alamat_asal_mhs, alamat_mhs), 1, 80) as jln,
					null as rt, 
					null as rw, 
					null as nm_dsn, 
					null as ds_kel,
					'000000' as id_wil,
					null as kode_pos,
					null as id_jns_tinggal,
					null as id_alat_transport,
					null as telepon_rumah,
					SUBSTR(mobile_mhs, 1, 20) as telepon_seluler,
					COALESCE(p.email_alternate, p.email_pengguna) as email,
					
					/* Other Info */
					0 as a_terima_kps,
					null as no_kps,
					'A' as stat_pd,
					
					/* Informasi Ayah */
					nm_ayah_mhs as nm_ayah,
					null as tgl_lahir_ayah,
					null as id_jenjang_pendidikan_ayah,
					null as id_pekerjaan_ayah,
					null as id_penghasilan_ayah,
					NVL(null, 0) as id_kebutuhan_khusus_ayah,
					
					/* Informasi Ibu */
					coalesce(nm_ibu_mhs, 'Belum Terekam') as nm_ibu_kandung,
					null as tgl_lahir_ibu,
					null as id_jenjang_pendidikan_ibu,
					null as id_pekerjaan_ibu,
					null as id_penghasilan_ibu,
					NVL(null, 0) as id_kebutuhan_khusus_ibu,
					
					/* Informasi Wali */
					null as nm_wali,
					null as tgl_lahir_wali,
					null as id_jenjang_pendidikan_wali,
					null as id_pekerjaan_wali,
					null as id_penghasilan_wali,
					
					'ID' as kewarganegaraan
				FROM mahasiswa m
				JOIN pengguna p ON p.id_pengguna = m.id_pengguna
				JOIN program_studi ps ON ps.id_program_studi = m.id_program_studi
				JOIN perguruan_tinggi pt ON pt.id_perguruan_tinggi = p.id_perguruan_tinggi
				WHERE 
					pt.npsn = '{$this->satuan_pendidikan['npsn']}' AND
					ps.kode_program_studi = '{$kode_prodi}' AND
					m.thn_angkatan_mhs = '{$angkatan}' AND
					m.id_mhs NOT IN (SELECT id_mhs FROM feeder_mahasiswa_pt)
				ORDER BY m.nim_mhs ASC");
					
			$mahasiswa_pt_set = $this->rdb->QueryToArray(
				"SELECT 
					m.id_mhs,
					'{$sms['id_sms']}' as id_sms,
					NULL as id_pd,
					'{$this->satuan_pendidikan['id_sp']}' as id_sp,
					
					/* Jenis pendaftaran */
					(SELECT kj.id_jns_daftar FROM admisi A
					JOIN jalur j ON j.id_jalur = A.id_jalur
					JOIN kode_jalur kj ON kj.kode_jalur = j.kode_jalur
					WHERE A.id_jalur IS NOT NULL and a.id_mhs = m.id_mhs) as id_jns_daftar,
					
					m.nim_mhs as nipd,
					m.thn_angkatan_mhs||'-09-01' as tgl_masuk_sp,
					1 as a_pernah_paud,
					1 as a_pernah_tk,
					
					/* Semester Mulai */
					(SELECT thn_akademik_semester||decode(group_semester, 'Ganjil','1','Genap','2') FROM admisi A
					JOIN semester s ON s.id_semester = a.id_semester
					WHERE A.id_jalur IS NOT NULL and a.id_mhs = m.id_mhs) as mulai_smt
					
				FROM mahasiswa m
				JOIN pengguna p ON p.id_pengguna = m.id_pengguna
				JOIN program_studi ps ON ps.id_program_studi = m.id_program_studi
				JOIN perguruan_tinggi pt ON pt.id_perguruan_tinggi = p.id_perguruan_tinggi
				WHERE 
					pt.npsn = '{$this->satuan_pendidikan['npsn']}' AND
					ps.kode_program_studi = '{$kode_prodi}' AND
					m.thn_angkatan_mhs = '{$angkatan}' AND
					m.id_mhs NOT IN (SELECT id_mhs FROM feeder_mahasiswa_pt)
				ORDER BY m.nim_mhs ASC");
					
			// simpan ke cache
			xcache_set('mahasiswa_insert_set', $mahasiswa_set);
			xcache_set('mahasiswa_pt_insert_set', $mahasiswa_pt_set);
			
			$result['message'] = 'Ambil data Sistem Langitan yang akan di proses Entri. Jumlah data: ' . count($mahasiswa_set);
			$result['status'] = SYNC_STATUS_PROSES;
			
			// ganti parameter
			$_POST['mode'] = MODE_AMBIL_DATA_LANGITAN_2;
			$result['params'] = http_build_query($_POST);
		}
		// -----------------------------------
		// Ambil data untuk Update
		// -----------------------------------
		else if ($mode == MODE_AMBIL_DATA_LANGITAN_2)
		{
			// Filter Prodi & Angkatan
			$kode_prodi = $this->input->post('kode_prodi');
			$angkatan	= $this->input->post('angkatan');
			
			// Mendapatkan id_sms
			$response = $this->feeder->GetRecord($this->token, FEEDER_SMS, "id_sp = '{$this->satuan_pendidikan['id_sp']}' AND trim(kode_prodi) = '{$kode_prodi}'");
			$sms = $response['result'];
			
			// Ambil mahasiswa yg akan UPDATE
			$mahasiswa_set = $this->rdb->QueryToArray(
				"SELECT 
					m.id_mhs, fm.id_pd,
					
					/* Informasi Mahasiswa : nama, tmpt & lahir, ibu kandung di exclude dr update */
					decode(p.kelamin_pengguna, 1, 'L', 2, 'P', NULL, 'L') as jk, /* default Laki-Laki */
					null as nisn, 
					null as nik,
					NVL((select id_feeder from agama where agama.id_agama = p.id_agama), 1) as id_agama,  /* default Islam */
					0 as id_kk,
					'{$this->satuan_pendidikan['id_sp']}' as id_sp,
					
					/* Info tempat tinggal */
					SUBSTR(COALESCE(alamat_asal_mhs, alamat_mhs), 1, 80) as jln,
					null as rt, 
					null as rw, 
					null as nm_dsn, 
					null as ds_kel,
					'000000' as id_wil,
					null as kode_pos,
					null as id_jns_tinggal,
					null as id_alat_transport,
					null as telepon_rumah,
					SUBSTR(mobile_mhs, 1, 20) as telepon_seluler,
					COALESCE(p.email_alternate, p.email_pengguna) as email,
					
					/* Other Info */
					0 as a_terima_kps,
					null as no_kps,
					'A' as stat_pd,
					
					/* Informasi Ayah */
					nm_ayah_mhs as nm_ayah,
					null as tgl_lahir_ayah,
					null as id_jenjang_pendidikan_ayah,
					null as id_pekerjaan_ayah,
					null as id_penghasilan_ayah,
					NVL(null, 0) as id_kebutuhan_khusus_ayah,
					
					/* Informasi Ibu */
					null as tgl_lahir_ibu,
					null as id_jenjang_pendidikan_ibu,
					null as id_pekerjaan_ibu,
					null as id_penghasilan_ibu,
					NVL(null, 0) as id_kebutuhan_khusus_ibu,
					
					/* Informasi Wali */
					null as nm_wali,
					null as tgl_lahir_wali,
					null as id_jenjang_pendidikan_wali,
					null as id_pekerjaan_wali,
					null as id_penghasilan_wali,
					
					'ID' as kewarganegaraan
				FROM mahasiswa m
				JOIN pengguna p ON p.id_pengguna = m.id_pengguna
				JOIN program_studi ps ON ps.id_program_studi = m.id_program_studi
				JOIN perguruan_tinggi pt ON pt.id_perguruan_tinggi = p.id_perguruan_tinggi
				JOIN feeder_mahasiswa fm ON fm.id_mhs = m.id_mhs
				WHERE 
					pt.npsn = '{$this->satuan_pendidikan['npsn']}' AND
					ps.kode_program_studi = '{$kode_prodi}' AND
					m.thn_angkatan_mhs = '{$angkatan}' AND
					(m.id_mhs IN (SELECT id_mhs FROM feeder_mahasiswa_pt WHERE last_sync < last_update) OR m.id_mhs IN (SELECT id_mhs FROM feeder_mahasiswa WHERE last_sync < last_update))
				ORDER BY 1 ASC");
					
			$mahasiswa_pt_set = $this->rdb->QueryToArray(
				"SELECT 
					m.id_mhs, fm.id_reg_pd,
					'{$sms['id_sms']}' as id_sms,
					NULL as id_pd,
					'{$this->satuan_pendidikan['id_sp']}' as id_sp,
					
					/* Jenis pendaftaran */
					(SELECT kj.id_jns_daftar FROM admisi A
					JOIN jalur j ON j.id_jalur = A.id_jalur
					JOIN kode_jalur kj ON kj.kode_jalur = j.kode_jalur
					WHERE A.id_jalur IS NOT NULL and a.id_mhs = m.id_mhs) as id_jns_daftar,
					
					m.nim_mhs as nipd,
					m.thn_angkatan_mhs||'-09-01' as tgl_masuk_sp,  /* Default tanggal masuk 1 September */
					1 as a_pernah_paud,
					1 as a_pernah_tk,
					
					/* Semester Mulai */
					(SELECT thn_akademik_semester||decode(group_semester, 'Ganjil','1','Genap','2') FROM admisi A
					JOIN semester s ON s.id_semester = a.id_semester
					WHERE A.id_jalur IS NOT NULL and a.id_mhs = m.id_mhs) as mulai_smt
					
				FROM mahasiswa m
				JOIN pengguna p ON p.id_pengguna = m.id_pengguna
				JOIN program_studi ps ON ps.id_program_studi = m.id_program_studi
				JOIN perguruan_tinggi pt ON pt.id_perguruan_tinggi = p.id_perguruan_tinggi
				JOIN feeder_mahasiswa_pt fm ON fm.id_mhs = m.id_mhs
				WHERE 
					pt.npsn = '{$this->satuan_pendidikan['npsn']}' AND
					ps.kode_program_studi = '{$kode_prodi}' AND
					m.thn_angkatan_mhs = '{$angkatan}' AND
					(m.id_mhs IN (SELECT id_mhs FROM feeder_mahasiswa_pt WHERE last_sync < last_update) OR m.id_mhs IN (SELECT id_mhs FROM feeder_mahasiswa WHERE last_sync < last_update))
				ORDER BY m.nim_mhs ASC");
					
			// simpan ke cache
			xcache_set('mahasiswa_update_set', $mahasiswa_set);
			xcache_set('mahasiswa_pt_update_set', $mahasiswa_pt_set);
			
			$result['message'] = 'Ambil data Sistem Langitan yang akan di proses Update. Jumlah data: ' . count($mahasiswa_set);
			$result['status'] = SYNC_STATUS_PROSES;
			
			// ganti parameter
			$_POST['mode'] = MODE_SYNC;
			$result['params'] = http_build_query($_POST);
		}
		// ----------------------------------------------
		// Proses Sinkronisasi dari data yg sudah diambil
		// ----------------------------------------------
		else if ($mode == MODE_SYNC)
		{
			$index_proses = isset($_POST['index_proses']) ? $_POST['index_proses'] : 0;
			
			// Ambil dari cache
			$mahasiswa_insert_set = xcache_get('mahasiswa_insert_set');
			$mahasiswa_pt_insert_set = xcache_get('mahasiswa_pt_insert_set');
			$jumlah_insert = count($mahasiswa_insert_set);
			
			// Ambil dari cache
			$mahasiswa_update_set = xcache_get('mahasiswa_update_set');
			$mahasiswa_pt_update_set = xcache_get('mahasiswa_pt_update_set');
			$jumlah_update = count($mahasiswa_update_set);
			
			// Waktu Sinkronisasi
			$time_sync = date('Y-m-d H:i:s');
			
			// --------------------------------
			// Proses Insert
			// --------------------------------
			if ($index_proses < $jumlah_insert)
			{
				// Proses dalam bentuk key lowercase
				$mahasiswa_insert = array_change_key_case($mahasiswa_insert_set[$index_proses], CASE_LOWER);
				$mahasiswa_pt_insert = array_change_key_case($mahasiswa_pt_insert_set[$index_proses], CASE_LOWER);
				
				// Simpan id_mhs untuk update data di langitan
				$id_mhs = $mahasiswa_insert['id_mhs'];
				
				// Hilangkan id_mhs
				unset($mahasiswa_insert['id_mhs']);
				unset($mahasiswa_pt_insert['id_mhs']);
				
				// Cleansing data
				if ($mahasiswa_insert['jk'] == '*') unset($mahasiswa_insert['jk']);
				if ($mahasiswa_insert['kode_pos'] == '') unset($mahasiswa_insert['kode_pos']);
				if ( ! filter_var($mahasiswa_insert['email'], FILTER_VALIDATE_EMAIL)) unset($mahasiswa_insert['email']);
				if ($mahasiswa_insert['rt'] == '') unset($mahasiswa_insert['rt']);
				if ($mahasiswa_insert['rw'] == '') unset($mahasiswa_insert['rw']);
				if ($mahasiswa_insert['id_jns_tinggal'] == '') unset($mahasiswa_insert['id_jns_tinggal']);
				if ($mahasiswa_insert['id_alat_transport'] == '') unset($mahasiswa_insert['id_alat_transport']);
				if ($mahasiswa_insert['tgl_lahir_ayah'] == '') unset($mahasiswa_insert['tgl_lahir_ayah']);
				if ($mahasiswa_insert['id_jenjang_pendidikan_ayah'] == '') unset($mahasiswa_insert['id_jenjang_pendidikan_ayah']);
				if ($mahasiswa_insert['id_pekerjaan_ayah'] == '') unset($mahasiswa_insert['id_pekerjaan_ayah']);
				if ($mahasiswa_insert['id_penghasilan_ayah'] == '') unset($mahasiswa_insert['id_penghasilan_ayah']);
				if ($mahasiswa_insert['tgl_lahir_ibu'] == '') unset($mahasiswa_insert['tgl_lahir_ibu']);
				if ($mahasiswa_insert['id_jenjang_pendidikan_ibu'] == '') unset($mahasiswa_insert['id_jenjang_pendidikan_ibu']);
				if ($mahasiswa_insert['id_pekerjaan_ibu'] == '') unset($mahasiswa_insert['id_pekerjaan_ibu']);
				if ($mahasiswa_insert['id_penghasilan_ibu'] == '') unset($mahasiswa_insert['id_penghasilan_ibu']);
				if ($mahasiswa_insert['tgl_lahir_wali'] == '') unset($mahasiswa_insert['tgl_lahir_wali']);
				if ($mahasiswa_insert['id_jenjang_pendidikan_wali'] == '') unset($mahasiswa_insert['id_jenjang_pendidikan_wali']);
				if ($mahasiswa_insert['id_pekerjaan_wali'] == '') unset($mahasiswa_insert['id_pekerjaan_wali']);
				if ($mahasiswa_insert['id_penghasilan_wali'] == '') unset($mahasiswa_insert['id_penghasilan_wali']);
				
				// Entri ke Feeder Mahasiswa
				$insert_result = $this->feeder->InsertRecord($this->token, FEEDER_MAHASISWA, json_encode($mahasiswa_insert));
				
				// Jika berhasil insert, terdapat return id_pd
				if (isset($insert_result['result']['id_pd']))
				{
					// FK id_pd
					$mahasiswa_pt_insert['id_pd'] = $insert_result['result']['id_pd'];
					
					// Entri ke Feeder Mahasiswa_PT
					$insert_pt_result = $this->feeder->InsertRecord($this->token, FEEDER_MAHASISWA_PT, json_encode($mahasiswa_pt_insert));
					
					// Jika berhasil insert, terdapat return id_reg_pd
					if (isset($insert_pt_result['result']['id_reg_pd']))
					{	
						// Pesan Insert, nipd (nim) mengambil dari mahasiswa_pt_insert
						$result['message'] = ($index_proses + 1) . " Insert {$mahasiswa_pt_insert['nipd']} : Berhasil";
						
						// status sandbox
						$is_sandbox = ($this->session->userdata('is_sandbox') == TRUE) ? '1' : '0';
						
						// Melakukan update ke DB Langitan id_pd dan id_reg_pd hasil insert
						$this->rdb->Query(
							"INSERT INTO feeder_mahasiswa (ID_PD, ID_MHS, LAST_SYNC, LAST_UPDATE, IS_SANDBOX) 
								VALUES ('{$mahasiswa_pt_insert['id_pd']}', {$id_mhs}, to_date('{$time_sync}', 'YYYY-MM-DD HH24:MI:SS'), to_date('{$time_sync}', 'YYYY-MM-DD HH24:MI:SS'), {$is_sandbox})");
						
						$this->rdb->Query(
							"INSERT INTO feeder_mahasiswa_pt (ID_REG_PD, ID_MHS, LAST_SYNC, LAST_UPDATE, IS_SANDBOX) 
								VALUES ('{$insert_pt_result['result']['id_reg_pd']}', {$id_mhs}, to_date('{$time_sync}', 'YYYY-MM-DD HH24:MI:SS'), to_date('{$time_sync}', 'YYYY-MM-DD HH24:MI:SS'), {$is_sandbox})");
					}
					else // saat insert mahasiswa_pt gagal
					{
						// Pesan Insert, nipd mengambil dari mahasiswa_pt_insert
						$result['message'] = ($index_proses + 1) . ' Insert ' . $mahasiswa_pt_insert['nipd'] . ' : ' . json_encode($insert_pt_result['result']);
						
						// Hapus lagi agar tidak terjadi penumpukan
						$this->feeder->DeleteRecord($this->token, FEEDER_MAHASISWA, json_encode(array('id_pd' => $insert_result['result']['id_pd'])));
					}
				}
				else // Saat insert mahasiswa Gagal
				{
					// Pesan Insert, nipd mengambil dari mahasiswa_pt_insert
					$result['message'] = ($index_proses + 1) . " Insert {$mahasiswa_pt_insert['nipd']} : Gagal. ({$insert_result['result']['error_code']}) {$insert_result['result']['error_desc']}";
				}
				
				$result['status'] = SYNC_STATUS_PROSES;
				
				// ganti parameter
				$_POST['index_proses'] = $index_proses + 1;
				$result['params'] = http_build_query($_POST);
			}
			// --------------------------------
			// Proses Update
			// --------------------------------
			else if ($index_proses < ($jumlah_insert + $jumlah_update))
			{
				// index berjalan dikurangi jumlah data insert utk mendapatkan index update
				$index_proses -= $jumlah_insert;
				
				// Proses dalam bentuk key lowercase
				$mahasiswa_update = array_change_key_case($mahasiswa_update_set[$index_proses], CASE_LOWER);
				$mahasiswa_pt_update = array_change_key_case($mahasiswa_pt_update_set[$index_proses], CASE_LOWER);
				
				// Simpan id_mhs untuk update data di langitan
				$id_mhs		= $mahasiswa_update['id_mhs'];
				$id_pd		= $mahasiswa_update['id_pd'];
				$id_reg_pd	= $mahasiswa_pt_update['id_reg_pd'];
				
				// Hilangkan id_mhs & id_pd & id_reg_pd
				unset($mahasiswa_update['id_mhs']);
				unset($mahasiswa_pt_update['id_mhs']);
				unset($mahasiswa_update['id_pd']);
				unset($mahasiswa_pt_update['id_reg_pd']);
				
				// Cleansing data
				if ($mahasiswa_update['jk'] == '*') unset($mahasiswa_update['jk']);
				if ($mahasiswa_update['kode_pos'] == '') unset($mahasiswa_update['kode_pos']);
				if ( ! filter_var($mahasiswa_update['email'], FILTER_VALIDATE_EMAIL)) unset($mahasiswa_update['email']);
				if ($mahasiswa_update['rt'] == '') unset($mahasiswa_update['rt']);
				if ($mahasiswa_update['rw'] == '') unset($mahasiswa_update['rw']);
				if ($mahasiswa_update['id_jns_tinggal'] == '') unset($mahasiswa_update['id_jns_tinggal']);
				if ($mahasiswa_update['id_alat_transport'] == '') unset($mahasiswa_update['id_alat_transport']);
				if ($mahasiswa_update['tgl_lahir_ayah'] == '') unset($mahasiswa_update['tgl_lahir_ayah']);
				if ($mahasiswa_update['id_jenjang_pendidikan_ayah'] == '') unset($mahasiswa_update['id_jenjang_pendidikan_ayah']);
				if ($mahasiswa_update['id_pekerjaan_ayah'] == '') unset($mahasiswa_update['id_pekerjaan_ayah']);
				if ($mahasiswa_update['id_penghasilan_ayah'] == '') unset($mahasiswa_update['id_penghasilan_ayah']);
				if ($mahasiswa_update['tgl_lahir_ibu'] == '') unset($mahasiswa_update['tgl_lahir_ibu']);
				if ($mahasiswa_update['id_jenjang_pendidikan_ibu'] == '') unset($mahasiswa_update['id_jenjang_pendidikan_ibu']);
				if ($mahasiswa_update['id_pekerjaan_ibu'] == '') unset($mahasiswa_update['id_pekerjaan_ibu']);
				if ($mahasiswa_update['id_penghasilan_ibu'] == '') unset($mahasiswa_update['id_penghasilan_ibu']);
				if ($mahasiswa_update['tgl_lahir_wali'] == '') unset($mahasiswa_update['tgl_lahir_wali']);
				if ($mahasiswa_update['id_jenjang_pendidikan_wali'] == '') unset($mahasiswa_update['id_jenjang_pendidikan_wali']);
				if ($mahasiswa_update['id_pekerjaan_wali'] == '') unset($mahasiswa_update['id_pekerjaan_wali']);
				if ($mahasiswa_update['id_penghasilan_wali'] == '') unset($mahasiswa_update['id_penghasilan_wali']);
				
				// Build data format
				$data_update = array(
					'key'	=> array('id_pd' => $id_pd),
					'data'	=> $mahasiswa_update
				);
				
				// Update ke Feeder Mahasiswa
				$update_result = $this->feeder->UpdateRecord($this->token, FEEDER_MAHASISWA, json_encode($data_update));
				
				// Jika tidak ada masalah update
				if ($update_result['result']['error_code'] == 0)
				{
					$result['message'] = ($index_proses + 1) . " Update {$mahasiswa_pt_update['nipd']} : Berhasil";
					
					// Saat sandbox
					if ($this->session->userdata('is_sandbox'))
					{
						$this->rdb->Query("UPDATE feeder_mahasiswa SET last_sync_sandbox = to_date('{$time_sync}','YYYY-MM-DD HH24:MI:SS') WHERE id_mhs = {$id_mhs}");
						$this->rdb->Query("UPDATE feeder_mahasiswa_pt SET last_sync_sandbox = to_date('{$time_sync}','YYYY-MM-DD HH24:MI:SS') WHERE id_mhs = {$id_mhs}");
					}
					else
					{
						$this->rdb->Query("UPDATE feeder_mahasiswa SET last_sync = to_date('{$time_sync}','YYYY-MM-DD HH24:MI:SS') WHERE id_mhs = {$id_mhs}");
						$this->rdb->Query("UPDATE feeder_mahasiswa_pt SET last_sync = to_date('{$time_sync}','YYYY-MM-DD HH24:MI:SS') WHERE id_mhs = {$id_mhs}");
					}
				}
				// Jika terdapat masalah update
				else
				{
					$result['message'] = ($index_proses + 1) . " Update {$mahasiswa_pt_update['nipd']} : Gagal. ";
					$result['message'] .= "({$update_result['result']['error_code']}) {$update_result['result']['error_desc']}";
					$result['message'] .= "\r\n" . json_encode($data_update);
				}
				
				// Status proses
				$result['status'] = SYNC_STATUS_PROSES;
				
				// meneruskan index proses ditambah lagi dengan jumlah data insert
				$index_proses += $jumlah_insert;
				
				// ganti parameter
				$_POST['index_proses'] = $index_proses + 1;
				$result['params'] = http_build_query($_POST);
			}
			// --------------------------------
			// Selesai
			// --------------------------------
			else
			{
				$result['message'] = "Selesai";
				$result['status'] = SYNC_STATUS_DONE;
			}
		}
		
		echo json_encode($result);
	}
	
	
	private function proses_mata_kuliah()
	{
		$result = array('status'=> '', 'time' => '', 'message' => '', 'nextUrl' => site_url('sync/proses/'. $this->uri->segment(3)), 'params'	=> '');
		
		$mode	= isset($_POST['mode']) ? $_POST['mode'] : MODE_AMBIL_DATA_LANGITAN;
		
		if ($mode == MODE_AMBIL_DATA_LANGITAN)
		{
			// Filter Prodi & Angkatan
			$kode_prodi = $this->input->post('kode_prodi');
			
			// Mendapatkan id_sms
			$response = $this->feeder->GetRecord($this->token, FEEDER_SMS, "id_sp = '{$this->satuan_pendidikan['id_sp']}' AND trim(kode_prodi) = '{$kode_prodi}'");
			$sms = $response['result'];
			
			// Ambil mata kuliah yang akan insert
			
			// SAMPAI SINI
		}
	    else if ($mode == MODE_AMBIL_DATA_LANGITAN_2)
		{
			
		}
		else if ($mode == MODE_SYNC)
		{
			
		}
		
		echo json_encode($result);
	}
	
	private function proses_link_program_studi()
	{
		$result = array(
			'status'	=> '',
			'time'		=> '',
			'message'	=> '',
			'nextUrl'	=> '',
			'params'	=> ''
		);
		
		$format_time = "%d/%m/%Y %H:%M:%S";
		
		// Ambil program studi PT Feeder
		$response = $this->feeder->GetRecordset($this->token, FEEDER_SMS, "id_sp = '{$this->satuan_pendidikan['id_sp']}'");
		$sms_set = $response['result'];
		
		// Ambil prodi langitan yg belum link
		$program_studi_set = $this->rdb->QueryToArray(
			"SELECT id_program_studi, kode_program_studi FROM program_studi ps
			JOIN fakultas f on f.id_fakultas = ps.id_fakultas
			JOIN perguruan_tinggi pt on pt.id_perguruan_tinggi = f.id_perguruan_tinggi
			WHERE pt.npsn = '{$this->satuan_pendidikan['npsn']}' AND ps.id_program_studi NOT IN (SELECT id_program_studi FROM feeder_sms)");
		
		$jumlah_sync = 0;
		
		// Pastikan ada data
		if (count($sms_set) > 0 && count($program_studi_set) > 0)
		{
			foreach ($sms_set as $sms)
			{
				foreach ($program_studi_set as $prodi)
				{
					if (trim($sms['kode_prodi']) == ($prodi['KODE_PROGRAM_STUDI']))
					{
						$sql = "INSERT INTO feeder_sms (id_sms, id_program_studi) VALUES ('{$sms['id_sms']}','{$prodi['ID_PROGRAM_STUDI']}')";
						$this->rdb->Query($sql);
						$jumlah_sync++;
						break;
					}
				}
			}
			
			$result['message'] = 'Berhasil melakukan link ' . $jumlah_sync . ' program studi';
		}
		else
		{
			$result['message'] = 'Tidak ada data yang di link';
		}
		
		$result['status'] = 'done';
		$result['time'] = strftime($format_time);
		
		echo json_encode($result);
	}
	
	private function proses_link_mahasiswa()
	{
		$result = array('status'=> '', 'time' => '', 'message' => '', 'nextUrl' => site_url('sync/proses/'. $this->uri->segment(3)), 'params'	=> '');
		
		$mode	= isset($_POST['mode']) ? $_POST['mode'] : MODE_AMBIL_DATA_LANGITAN;
		
		if ($mode == MODE_AMBIL_DATA_LANGITAN)
		{
			$mahasiswa_set = $this->rdb->QueryToArray(
				"SELECT m.id_mhs, m.nim_mhs, fm.id_pd FROM mahasiswa m
				JOIN pengguna p ON p.id_pengguna = m.id_pengguna
				JOIN perguruan_tinggi pt ON pt.id_perguruan_tinggi = p.id_perguruan_tinggi
				LEFT JOIN feeder_mahasiswa fm ON fm.id_mhs = m.id_mhs
				WHERE 
					pt.npsn = '{$this->satuan_pendidikan['npsn']}'
				ORDER BY 1 ASC");
				
			// simpan ke cache
			xcache_set('mahasiswa_set', $mahasiswa_set);
			
			$result['message'] = 'Ambil data langitan selesai. Jumlah data: ' . count($mahasiswa_set);
			$result['status'] = SYNC_STATUS_PROSES;
			
			// ganti parameter
			$_POST['mode'] = MODE_SYNC;
			$result['params'] = http_build_query($_POST);
		}
		else if ($mode == MODE_SYNC)
		{
			$index_proses = isset($_POST['index_proses']) ? $_POST['index_proses'] : 0;
			
			// Ambil dari cache
			$mahasiswa_set = xcache_get('mahasiswa_set');
			
			// Jika masih dalam rentang index, di proses
			if ($index_proses < count($mahasiswa_set))
			{
				// Ambil row mahasiswa_pt
				$response = $this->feeder->GetRecord($this->token, FEEDER_MAHASISWA, "id_pd = '{$mahasiswa_set[$index_proses]['ID_PD']}'");
				
				// Jika ada
				if (isset($response['result']['id_pd']))
				{
					// Jika sudah ada di Feeder_Mahasiswa -> Update id_pd2 (temp)
					if ($mahasiswa_set[$index_proses]['ID_PD'] != '')
					{
						// Update
						$updated = $this->rdb->Query(
							"UPDATE feeder_mahasiswa set id_pd2 = '{$response['result']['id_pd']}' where id_pd = '{$mahasiswa_set[$index_proses]['ID_PD']}'");
						
						$result["message"] = "Update {$mahasiswa_set[$index_proses]['NIM_MHS']} ==> {$response['result']['id_pd']} : " .
							($updated ? "Berhasil" : "Gagal");
					}
					else // Jika belum, insert
					{
						$inserted = $this->rdb->Query(
							"INSERT INTO feeder_mahasiswa (id_pd, id_mhs, last_sync, last_update) "
							. "VALUES ('{$response['result']['id_pd']}', {$mahasiswa_set[$index_proses]['ID_MHS']}, sysdate, sysdate)");
							
						$result["message"] = "Insert {$mahasiswa_set[$index_proses]['NIM_MHS']} ==> {$response['result']['id_pd']} : " .
							($inserted ? "Berhasil" : "Gagal");
					}
					
				}
				else
				{
					$result['message'] = "{$mahasiswa_set[$index_proses]['NIM_MHS']} tidak ada di Feeder";
				}
				
				$result['status'] = SYNC_STATUS_PROSES;
				
				// ganti parameter
				$_POST['index_proses'] = $index_proses + 1;
				$result['params'] = http_build_query($_POST);
			}
			else
			{
				$result['status'] = SYNC_STATUS_DONE;
				$result['message'] = 'Selesai';
			}
		}
		
		echo json_encode($result);
	}
	
	private function proses_link_mahasiswa_pt()
	{
		$result = array('status'=> '', 'time' => '', 'message' => '', 'nextUrl' => site_url('sync/proses/'. $this->uri->segment(3)), 'params'	=> '');
		
		$mode	= isset($_POST['mode']) ? $_POST['mode'] : MODE_AMBIL_DATA_LANGITAN;
		
		if ($mode == MODE_AMBIL_DATA_LANGITAN)
		{
			$mahasiswa_set = $this->rdb->QueryToArray(
				"SELECT m.id_mhs, m.nim_mhs, nm_pengguna FROM mahasiswa m
				JOIN pengguna p ON p.id_pengguna = m.id_pengguna
				JOIN perguruan_tinggi pt ON pt.id_perguruan_tinggi = p.id_perguruan_tinggi
				WHERE 
					pt.npsn = '{$this->satuan_pendidikan['npsn']}' AND 
					m.id_mhs NOT IN (SELECT id_mhs FROM feeder_mahasiswa_pt)
				ORDER BY 2 ASC");
				
			// simpan ke cache
			xcache_set('mahasiswa_set', $mahasiswa_set);
			
			$result['message'] = 'Ambil data langitan selesai. Jumlah data: ' . count($mahasiswa_set);
			$result['status'] = SYNC_STATUS_PROSES;
			
			// ganti parameter
			$_POST['mode'] = MODE_SYNC;
			$result['params'] = http_build_query($_POST);
		}
		else if ($mode == MODE_SYNC)
		{
			$index_proses = isset($_POST['index_proses']) ? $_POST['index_proses'] : 0;
			
			// Ambil dari cache
			$mahasiswa_set = xcache_get('mahasiswa_set');
			
			// Jika masih dalam rentang index, di proses
			if ($index_proses < count($mahasiswa_set))
			{
				// Ambil row mahasiswa_pt
				$response = $this->feeder->GetRecord($this->token, FEEDER_MAHASISWA_PT, "nipd = '{$mahasiswa_set[$index_proses]['NIM_MHS']}'");
				
				// Jika ada
				if (isset($response['result']['id_reg_pd']))
				{
					/*
					$inserted = $this->rdb->Query(
						"INSERT INTO feeder_mahasiswa_pt (id_reg_pd, id_mhs, last_sync) "
						. "VALUES ('{$response['result']['id_reg_pd']}', {$mahasiswa_set[$index_proses]['ID_MHS']}, sysdate)");
					
					$result['message'] = "Update {$mahasiswa_set[$index_proses]['NIM_MHS']} ==> {$response['result']['id_reg_pd']} : " . 
						($inserted ? 'Berhasil' : 'Gagal');
					*/
					
					$result['message'] = "{$mahasiswa_set[$index_proses]['NIM_MHS']} ada di Feeder";
				}
				else //jika tidak ada
				{
					$result['message'] = "{$mahasiswa_set[$index_proses]['NIM_MHS']} \"{$mahasiswa_set[$index_proses]['NM_PENGGUNA']}\" tidak ada di Feeder";
				}
				
				$result['status'] = SYNC_STATUS_PROSES;
				
				// ganti parameter
				$_POST['index_proses'] = $index_proses + 1;
				$result['params'] = http_build_query($_POST);
			}
			else
			{
				$result['status'] = SYNC_STATUS_DONE;
				$result['message'] = 'Selesai';
			}
		}
			
		echo json_encode($result);
	}
	
	private function proses_link_mahasiswa_pt2()
	{
		$result = array('status'=> '', 'time' => '', 'message' => '', 'nextUrl' => site_url('sync/proses/'. $this->uri->segment(3)), 'params'	=> '');
		
		$mode	= isset($_POST['mode']) ? $_POST['mode'] : MODE_AMBIL_DATA_FEEDER;
		
		if ($mode == MODE_AMBIL_DATA_FEEDER)
		{
			$response = $this->feeder->GetCountRecordset($this->token, FEEDER_MAHASISWA_PT);
			
			// simpan ke cache
			xcache_set('jumlah_'.FEEDER_MAHASISWA_PT, $response['result']);
			
			$result['message'] = 'Ambil data feeder selesai. Jumlah data yg akan diproses : ' . $response['result'];
			$result['status'] = SYNC_STATUS_PROSES;
			
			// ganti parameter
			$_POST['mode'] = MODE_SYNC;
			$result['params'] = http_build_query($_POST);
		}
		else if ($mode == MODE_SYNC)
		{
			$index_proses = isset($_POST['index_proses']) ? $_POST['index_proses'] : 0;
			
			// ambil dari cache
			$total_data = xcache_get('jumlah_'.FEEDER_MAHASISWA_PT);
			
			if ($index_proses < $total_data)
			{
				// Ambil per row di feeder
				$response = $this->feeder->GetRecordset($this->token, FEEDER_MAHASISWA_PT, null, '1', '1', $index_proses);
				$mahasiswa_pt = $response['result'][0];

				// Cek di langitan berdasarkan id_reg_pd
				$response = $this->rdb->QueryToArray(
					"SELECT COUNT(*) AS jumlah FROM feeder_mahasiswa_pt WHERE id_reg_pd = '{$mahasiswa_pt['id_reg_pd']}'");
				
				// Jika ada
				if ($response[0]['JUMLAH'] > 0)
				{
					$result['message'] = "{$mahasiswa_pt['nipd']} ==> Ada";
				}
				else
				{
					$result['message'] = "{$mahasiswa_pt['nipd']} ==> Tidak ada";
				}
				
				$result['status'] = SYNC_STATUS_PROSES;
				
				// ganti parameter
				$_POST['index_proses'] = $index_proses + 1;
				$result['params'] = http_build_query($_POST);
			}
			else
			{
				$result['status'] = SYNC_STATUS_DONE;
				$result['message'] = 'Selesai';
			}
		}
		
		echo json_encode($result);
	}
	
	private function proses_link_lulus()
	{
		$result = array('status'=> '', 'time' => '', 'message' => '', 'nextUrl' => site_url('sync/proses/'. $this->uri->segment(3)), 'params'	=> '');
		
		$mode	= isset($_POST['mode']) ? $_POST['mode'] : MODE_AMBIL_DATA_FEEDER;
		
		if ($mode == MODE_AMBIL_DATA_FEEDER)
		{
			$response = $this->feeder->GetCountRecordset($this->token, FEEDER_MAHASISWA_PT, "ket_keluar = 'Lulus'");
			
			// simpan ke cache
			xcache_set('jumlah_'.FEEDER_MAHASISWA_PT, $response['result']);
			
			$result['message'] = 'Ambil data feeder selesai. Jumlah data yg akan diproses : ' . $response['result'];
			$result['status'] = SYNC_STATUS_PROSES;
			
			// ganti parameter
			$_POST['mode'] = MODE_SYNC;
			$result['params'] = http_build_query($_POST);
		}
		else if ($mode == MODE_SYNC)
		{
			$index_proses = isset($_POST['index_proses']) ? $_POST['index_proses'] : 0;
			
			// ambil dari cache
			$total_data = xcache_get('jumlah_'.FEEDER_MAHASISWA_PT);
			
			if ($index_proses < $total_data)
			{
				// Ambil per row di feeder
				$response = $this->feeder->GetRecordset($this->token, FEEDER_MAHASISWA_PT, "ket_keluar = 'Lulus'", '1', '1', $index_proses);
				$mahasiswa_pt = $response['result'][0];
				
				// Ambil data langitan by id_reg_pd
				$response = $this->rdb->QueryToArray(
					"SELECT fm.id_mhs, ps.id_jenjang FROM feeder_mahasiswa_pt fm
					JOIN mahasiswa m ON m.id_mhs = fm.id_mhs
					JOIN program_studi ps ON ps.id_program_studi = m.id_program_studi
					WHERE fm.id_reg_pd = '{$mahasiswa_pt['id_reg_pd']}'");
				$id_mhs		= $response[0]['ID_MHS'];
				$id_jenjang	= $response[0]['ID_JENJANG'];
				
				if ($id_mhs)
				{
					// Konversi tahun keluar ke ID_SEMESTER
					// 2014 >> 197, 2015 >> 209
					$tahun_keluar = substr($mahasiswa_pt['tgl_keluar'], 0, 4);
					if ($tahun_keluar == '2014') $id_semester = 197;
					else if ($tahun_keluar == '2015') $id_semester = 209;
					
					// Update Mahasiswa
					$updated_1 = $this->rdb->Query("UPDATE mahasiswa SET status_akademik_mhs = 4 WHERE id_mhs = {$id_mhs}");
					
					// ambil row admisi lulus
					$admisi = $this->rdb->QueryToArray("SELECT COUNT(*) as jumlah FROM admisi WHERE id_mhs = {$id_mhs} AND status_akd_mhs = 4");
					
					// Jika belum punya row admisi
					if ($admisi[0]['JUMLAH'] == 0)
					{
						// Insert Admisi Lulus (id status akd = 4)
						$inserted_1 = $this->rdb->Query(
							"INSERT INTO admisi (
								id_mhs, id_semester, status_akd_mhs, status_apv, 
								tgl_usulan, tgl_apv, 
								no_ijasah,
								keterangan, id_pengguna)
							VALUES (
								{$id_mhs}, {$id_semester}, 4, 1,
								to_date('{$mahasiswa_pt['tgl_keluar']}', 'YYYY-MM-DD'), to_date('{$mahasiswa_pt['tgl_keluar']}', 'YYYY-MM-DD'),
								'{$mahasiswa_pt['no_seri_ijazah']}',
								'Lulusan UMAHA Tahun {$tahun_keluar}', 60)");
					}
					else
					{
						$inserted_1 = TRUE;
					}

					// Konversi ID Periode wisuda
					if ($tahun_keluar == '2014' && $id_jenjang == '1') { $id_periode_wisuda = 67; }
					if ($tahun_keluar == '2014' && $id_jenjang == '5') { $id_periode_wisuda = 68; }
					if ($tahun_keluar == '2015' && $id_jenjang == '1') { $id_periode_wisuda = 65; }
					if ($tahun_keluar == '2015' && $id_jenjang == '5') { $id_periode_wisuda = 69; }
					
					// Cleansing petik 
					$mahasiswa_pt['judul_skripsi'] = str_replace("'", "''", $mahasiswa_pt['judul_skripsi']);
					// Format Tgl SK Yudisium
					if ($mahasiswa_pt['tgl_sk_yudisium'])
						$mahasiswa_pt['tgl_sk_yudisium'] = "to_date('{$mahasiswa_pt['tgl_sk_yudisium']}','YYYY-MM-DD')";
					else
						$mahasiswa_pt['tgl_sk_yudisium'] = 'NULL';
							
					// ambil row pengajuan wisuda
					$pengajuan_wisuda = $this->rdb->QueryToArray("SELECT count(*) as jumlah FROM pengajuan_wisuda WHERE id_mhs = {$id_mhs}");
					
					// Jika belum ada row pengajuan wisuda
					if ($pengajuan_wisuda[0]['JUMLAH'] == 0)
					{
						// Insert Pengajuan Wisuda
						$sql_insert_2 = "INSERT INTO pengajuan_wisuda (
								id_mhs, judul_ta, yudisium, id_periode_wisuda,
								no_ijasah, sk_yudisium, tgl_sk_yudisium, ipk)
							VALUES (
								{$id_mhs}, '{$mahasiswa_pt['judul_skripsi']}', 2, {$id_periode_wisuda},
								'{$mahasiswa_pt['no_seri_ijazah']}', '{$mahasiswa_pt['sk_yudisium']}', {$mahasiswa_pt['tgl_sk_yudisium']}, '{$mahasiswa_pt['ipk']}')";
						$inserted_2 = $this->rdb->Query($sql_insert_2);
					}
					else
					{
						$inserted_2 = TRUE;
					}
					
					$result['message'] = "Proses {$mahasiswa_pt['nipd']}: ".
						"Update Mahasiswa = " . ($updated_1 ? 'Berhasil. ' : 'GAGAL. ') .
						"Insert Admisi = " . ($inserted_1 ? 'Berhasil. ' : 'GAGAL. ') .
						"Insert Wisuda = " . ($inserted_2 ? 'Berhasil. ' : "GAGAL. {$sql_insert_2}");
				}
				else
				{
					$result['message'] = 'Gagal baca id_mhs';
				}
				
				$result['status'] = SYNC_STATUS_PROSES;
				
				// ganti parameter
				$_POST['index_proses'] = $index_proses + 1;
				$result['params'] = http_build_query($_POST);
			}
			else
			{
				$result['status'] = SYNC_STATUS_DONE;
				$result['message'] = 'Selesai';
			}
		}
		
		echo json_encode($result);
	}
	
	/**
	 * Masih gagal memfilter jumlah MK per kurikulum
	 */
	private function proses_hapus_mk_kurikulum()
	{
		$offset = isset($_POST['offset']) ? $_POST['offset'] : 0;
		$limit  = 10;
		
		// Jika 
		if ($offset == 0)
		{
			// Ambil jumlah data yang akan dihapus
			$response = $this->feeder->GetCountRecordset($this->token, FEEDER_MK_KURIKULUM, "id_kurikulum_sp = '{$_POST['id_kurikulum_sp']}'");
			
			// naikan offset
			$_POST['message'] = print_r($response, true);
			$_POST['offset'] = $offset + $limit;
		}
		
		$result = array(
			'status'	=> '',
			'time'		=> '',
			'message'	=> '',
			'nextUrl'	=> '',
			'params'	=> ''
		);
		
		$result['nextUrl'] = site_url('sync/proses/hapus_mk_kurikulum');
		$result['params'] = http_build_query($_POST);
		
		$result['status'] = 'done';
		$result['time'] = strftime("%d/%m/%Y %H:%M:%S");
		
		echo json_encode($result);
	}
	
	function hapus_mk_kurikulum()
	{
		// Ambil Prodi dari cache
		$sms_set = xcache_get(FEEDER_SMS.'_set');
		$this->smarty->assign('sms_set', $sms_set);
		
		// Ambil jenjang dari cache
		$jenjang_pendidikan_set = xcache_get(FEEDER_JENJANG_PENDIDIKAN.'_set');
		$this->smarty->assign('jenjang_pendidikan_set', $jenjang_pendidikan_set);
		
		$this->smarty->display('sync/hapus_mk_kurikulum.tpl');
	}
	
	/**
	 * AJAX Request
	 * @param type $id_sms
	 */
	function ambil_kurikulum($id_sms)
	{
		$response = $this->feeder->GetRecordset($this->token, FEEDER_KURIKULUM, "id_sms = '{$id_sms}'");
		echo json_encode($response['result']);
	}
	
	
	function hapus_iwan()
	{
		$nim	= '143213133';
		$id_pd	= 'a4d804e4-d072-4e0c-9037-d0311b898bc7';
		
		// Ambil mahasiswa_pt iwan
		$response = $this->feeder->GetRecord($this->token, FEEDER_MAHASISWA_PT, "nipd = '{$nim}'");
		$mahasiswa_pt = $response['result'];
		
		// Ambil kuliah_mahasiswa
		$response = $this->feeder->GetRecordset($this->token, FEEDER_KULIAH_MAHASISWA, "id_reg_pd = '{$mahasiswa_pt['id_reg_pd']}'");
		$kuliah_mahasiswa_set = $response['result'];
		
		// ----------------------
		// Hapus Kuliah Mahasiswa : id_reg_pd, id_smt
		// ----------------------
		if (count($kuliah_mahasiswa_set) > 0)
		{
			foreach ($kuliah_mahasiswa_set as $km)
			{
				$data[] = array('id_reg_pd' => $km['id_reg_pd'], 'id_smt' => $km['id_smt']);
			}

			$data_json = json_encode($data);
			// $response = $this->feeder->DeleteRecordset($this->token, FEEDER_KULIAH_MAHASISWA, $data_json);
			
			//print_r($response);
		}
	}
	
	function coba()
	{
		var_dump(filter_var("Ngawur", FILTER_SANITIZE_EMAIL));
		
		//echo json_encode(array('x' => false));
	}
}
