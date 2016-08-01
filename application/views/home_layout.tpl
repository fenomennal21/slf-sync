<!DOCTYPE html>
<html lang="id">
	<head>
		<title>{block name='title'}{/block}SL-F Sync</title>
		<meta charset="UTF-8">
		<meta name="description" content="LF Sync" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta name="msapplication-config" content="none"/>
		
		<!-- Source Sans Pro Font -->
		<link href='https://fonts.googleapis.com/css?family=Source+Sans+Pro' rel='stylesheet' type='text/css'>
		
		<!-- Bootstrap -->
		<link href="{base_url('assets/css/bootstrap.min.css')}" rel="stylesheet">
		
		<style type="text/css">
			body { padding-top: 40px; }
		</style>
		
		{if $ci->session->userdata('is_sandbox')}
		<style type="text/css">
			.navbar-inverse {
				background-color: #b94a48;
				border-color: #E7E7E7;
			}
			
			.navbar-inverse .navbar-brand {
				color: #ddd;
			}
			
			.navbar-inverse .navbar-nav > li > a {
				color: #ddd;
			}
		</style>	
		{/if}

		<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
		<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
		<!--[if lt IE 9]>
		  <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
		  <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
		<![endif]-->
		{block name='head'}{/block}
	</head>
	
	<body>
		<div class="navbar navbar-default navbar-fixed-top navbar-inverse">
			<div class="container">
				<div class="navbar-header">
					<a href="{site_url('home')}" class="navbar-brand">SL-F Sync</a>
					<button class="navbar-toggle" type="button" data-toggle="collapse" data-target="#navbar-main">
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					</button>
				</div>
					
				<div class="navbar-collapse collapse" id="navbar-main">
					<ul class="nav navbar-nav">
						<li>
							<a href="{site_url('home')}">Status</a>
						</li>
						<li class="dropdown">
							<a href="#" class="dropdown-toggle" data-toggle="dropdown">WSDL <i class="caret"></i></a>
							<ul class="dropdown-menu">
								<li><a href="{site_url('webservice/list_table')}">Daftar Tabel</a></li>
								<li><a href="{site_url('webservice/dictionary')}">Detail Tabel</a></li>
							</ul>
						</li>
						<li>
							<a href="#" class="dropdown-toggle" data-toggle="dropdown">Sync <i class="caret"></i></a>
							<ul class="dropdown-menu">
								<li><a href="{site_url('sync/mahasiswa')}">Sync Mahasiswa</a></li>
								<li><a href="{site_url('sync/mata_kuliah')}">Sync Mata Kuliah</a></li>
								<li role="separator" class="divider"></li>
								<li><a href="{site_url('sync/link_program_studi')}">Link Program Studi</a></li>
								<li><a href="{site_url('sync/link_mahasiswa')}">Link Mahasiswa</a></li>
								<li><a href="{site_url('sync/link_mahasiswa_pt')}">Link Mahasiswa PT</a></li>
								<li role="separator" class="divider"></li>
								<li><a href="{site_url('sync/hapus_mk_kurikulum')}">Hapus MK Kurikulum</a></li>
								<li><a href="{site_url('sync/hapus_iwan')}">Hapus Iwan</a></li>
							</ul>
							
						</li>
					</ul>
					<ul class="nav navbar-nav navbar-right">
						<li>
							<a href="{site_url('auth/logout')}">Logout</a>
						</li>
					</ul>
				</div>

			</div>
		</div>
		
		<div class="container">
		{block name='body-content'}{/block}
		</div>
		
		{if isset($debug)}<pre>{$debug}</pre>{/if}

		<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
		<script src="{base_url('assets/js/jquery-1.12.3.min.js')}"></script>
		<!-- Include all compiled plugins (below), or include individual files as needed -->
		<script src="{base_url('assets/js/bootstrap.min.js')}"></script>
		{block name='footer-script'}{/block}
	</body>
</html>