{extends file='home_layout.tpl'}
{block name='head'}
	<style>tr.jumlahdata > td { font-size: 140% }</style>
{/block}
{block name='body-content'}
	<div class="row">
		<div class="col-md-12">
			<div class="page-header">
				<h2>Sinkronisasi Mata Kuliah</h2>
			</div>

			<table class="table table-bordered table-condensed" style="width: auto">
				<thead>
					<tr>
						<th>Mata Kuliah</th>
						<th>Feeder</th>
						<th>Sistem Langitan</th>
						<th>Link</th>
						<th></th>
						<th>Update</th>
						<th>Insert</th>
					</tr>
				</thead>
				<tbody>
					<tr class="jumlahdata">
						<td>Jumlah Data</td>
						<td class="text-center">{$jumlah.feeder}</td>
						<td class="text-center">{$jumlah.langitan}</td>
						<td class="text-center">{$jumlah.linked}</td>
						<td></td>
						<td class="text-center text-warning">&#8710;{$jumlah.update}</td>
						<td class="text-center text-success">+{$jumlah['insert']}</td>
					</tr>
				</tbody>
			</table>

			<form class="form-horizontal" action="{$url_sync}" method="post">
				<fieldset>

					<!-- Form Name -->
					<legend>Filter Data</legend>

					<!-- Select Basic -->
					<div class="form-group">
						<label class="col-md-2 control-label" for="selectbasic">Program Studi</label>
						<div class="col-md-4">
							<select name="kode_prodi" class="form-control" required>
								<option value=""></option>
								{foreach $program_studi_set as $ps}
									<option value="{$ps.KODE_PROGRAM_STUDI}">{$ps.NM_JENJANG} {$ps.NM_PROGRAM_STUDI}</option>
								{/foreach}
							</select>
						</div>
					</div>

					<!-- Button -->
					<div class="form-group">
						<label class="col-md-4 control-label" for="singlebutton"></label>
						<div class="col-md-4">
							<input type="submit" class="btn btn-primary" value="Proses Sinkronisasi" />
						</div>
					</div>
					
				</fieldset>
			</form>


			<form action="{$url_sync}" method="post">
				
			</form>
		</div>
	</div>
{/block}
{block name='footer-script'}
	<script type="text/javascript">
		$(function(){
			
			var refreshTable = function(kodeProdi) {
				var url = '{site_url('sync/mata_kuliah_data')}/'+kodeProdi;
				
				if (kodeProdi !== null)
				{
					$.getJSON(url, function(data){
						console.log(data);
						$('tr.jumlahdata td:eq(1)').text(data.feeder);
						$('tr.jumlahdata td:eq(2)').text(data.langitan);
						$('tr.jumlahdata td:eq(3)').text(data.linked);
						$('tr.jumlahdata td:eq(5)').text('∆' + data.update);
						$('tr.jumlahdata td:eq(6)').text('+' + data.insert);
					});
				}
				
			};
			
			$('select[name=kode_prodi]').on('change', function(){
				refreshTable($('select[name=kode_prodi]').val());
			});
		});
	</script>
{/block}