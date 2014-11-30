<p>Making payment for $Amount.Nice<% if CurrentPayment %> via the $CurrentPayment.Gateway method<% end_if %>.</p>
<% if Payable.PaymentHistory && not CurrentPayment %>
	<h4>Previous attempts:</h4>
	<% with Payable.PaymentHistory %>
		<% include PaymentsTable %>
	<% end_with %>
<% end_if %>