{extends file='home_layout.tpl'}
{block name='body-content'}
	<div class="row">
		<div class="col-md-12">
			<div class="page-header">
				<h2>Data Tabel {$ci->uri->segment(3, 0)}</h2>
			</div>

			<p><a href="{site_url('webservice/list_table')}">Back</a></p>
			
			<table class="table table-bordered table-condensed" style="width: auto">
				<thead>
					<tr>
						{foreach $column_set as $column}
							<th>{$column.column_name}</th>
						{/foreach}
					</tr>
				</thead>
				<tbody>
					{foreach $data_set as $data}
						<tr>
							{foreach $column_set as $column}
								<td>{$data[$column.column_name]}</td>
							{/foreach}
						</tr>
					{/foreach}
				</tbody>
			</table>
		</div>
	</div>
{/block}