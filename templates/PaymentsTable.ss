<table class="table">
	<thead>
		<tr>
			<th>Date</th>
			<th>Status</th>
			<th>Amount</th>
			<th>Status</th>
		</tr>
	</thead>
	<tbody>
		<% loop $Me %>
			<tr>
				<td>$Created.Nice</td>
				<td>$GatewayTitle</td>
				<td>$Money.Nice</td>
				<td>$Status</td>
			</tr>
		<% end_loop %>
	</tbody>
</table>