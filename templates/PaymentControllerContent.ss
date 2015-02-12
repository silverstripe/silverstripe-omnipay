<% if CurrentPayment.UserMessages %>
	<table class="table">
		<% loop CurrentPayment.UserMessages %>
			<tr>
				<td>$Created.Nice</td>
				<td>$Message</td>
			</tr>
		<% end_loop %>
	</table>
<% end_if %>
<p>
	Making payment for $Amount.Nice<% if CurrentPayment %> via the $CurrentPayment.GatewayTitle method<% end_if %>.
</p>