<?php

class AuthorizeRequest extends GatewayRequestMessage{

}
class AuthorizedResponse extends GatewayResponseMessage{

}
class AuthorizeRedirectResponse extends GatewayRedirectResponseMessage{

}
class AuthorizeError extends GatewayErrorMessage{

}

class CompleteAuthorizeRequest extends GatewayRequestMessage{

}
//AuthorizedResponse
class CompleteAuthorizeError extends GatewayErrorMessage{

}

class CaptureRequest extends GatewayRequestMessage{

}
class CapturedResponse extends GatewayResponseMessage{

}
class CaptureError extends GatewayErrorMessage{
	
}
