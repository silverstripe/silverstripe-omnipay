<?php

class PurchaseRequest extends GatewayRequestMessage{}
class PurchasedResponse extends GatewayResponseMessage{}
class PurchaseRedirectResponse extends GatewayRedirectResponseMessage{}
class PurchaseError extends GatewayErrorMessage{}

class CompletePurchaseRequest extends GatewayRequestMessage{}
//PurchasedResponse
class CompletePurchaseError extends GatewayErrorMessage{}
