<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html lang="en">
    <head>
        <meta http-equiv="refresh" content="2;url={$ReturnURL}" />
        <title><%t WorldPayExtension.Processing "Processing" %></title>
    </head>

    <body>
        <div style="font-family:sans-serif;text-align:center;" class="payment-processing">
            <h1><%t WorldPayExtension.Processing "Processing" %></h1>

            <p>
                <%t WorldPayExtension.RedirectingToStore "We are now redirecting you, if you are not redirected automatically then click the link below." %>
            </p>

            <p>
                <a href="{$ReturnURL}"><%t WorldPayExtension.ReturnToStore "Return To Merchant's Store" %></a>
            </p>

            <WPDISPLAY ITEM="banner">
        </div>
    </body>
</html>
