# Translating the Omnipay Module

If you're using this module and notice that your required language isn't translated yet, please consider helping to translate this module. Head over to https://www.transifex.com/silvershop/silverstripe-omnipay/ to get started.

## Gateway names

Gateway names can be translated by translating `Gateway.GatewayName` in your language files.
Here's a list of the common Gateway names:

```yaml
  # Instead of providing the gateway names directly from the gateways, try to remove information that is
  # unnecessary to the end user. The end-user doesn't care if the gateway is PayPal Express, Pro or REST, all that
  # matters is "PayPal". Therefore all the PayPal gateways have the same title.
  Gateway:
    2Checkout: 2Checkout
    Agms: AGMS
    Alipay_Bank: 'Alipay Bank'
    Alipay_Dual: 'AliPay Dual Func'
    Alipay_Express: 'Alipay Express'
    Alipay_MobileExpress: 'Alipay Mobile Express'
    Alipay_Secured: 'Alipay Secured'
    Alipay_WapExpress: 'Alipay Wap Express'
    AuthorizeNet_AIM: 'Authorize.Net AIM'
    AuthorizeNet_DPM: 'Authorize.Net DPM'
    AuthorizeNet_SIM: 'Authorize.Net SIM'
    BarclaysEpdq_Essential: Barclaycard
    Buckaroo_CreditCard: 'Buckaroo Credit Card'
    Buckaroo_Ideal: 'Buckaroo iDeal'
    Buckaroo_PayPal: 'Buckaroo PayPal'
    Cardgate: Cardgate
    CardSave: Cardsave
    CheckoutCom: 'Checkout.com'
    Coinbase: coinbase
    Creditcall: creditcall
    Cybersource: CyberSource
    Cybersource_Cybersource: CyberSource # This is the SOAP Gateway
    DataCash: DataCash
    Dummy: Dummy
    Ecopayz: ecoPayz
    Eway_Direct: eWAY
    Eway_RapidDirect: eWAY
    Eway_Rapid: eWAY
    Eway_RapidShared: eWAY
    Fasapay: FasaPay
    Fatzebra_Fatzebra: 'Fat Zebra'
    FirstData_Connect: 'First Data'
    FirstData_Global: 'First Data'
    Globalcloudpay: Globalcloudpay
    GoCardless: GoCardless
    Helcim_Direct: Helcim
    Helcim_HostedPages: Helcim
    Helcim_JS: 'Helcim.JS'
    Komoju: Komoju
    Manual: Invoice
    Migs_ThreeParty: 'MIGS 3-Party'
    Migs_TwoParty: 'MIGS 2-Party'
    Mollie: Mollie
    Multicards: Multicards
    MultiSafepay: MultiSafepay
    Netaxept: Netaxept
    NetBanx: NetBanx
    Neteller: Neteller
    NMI_DirectPost: 'NMI Direct Post'
    Pacnet: Pacnet
    Pagarme: 'Pagar.me'
    PayFast: PayFast
    Payflow_Pro: Payflow
    PaymentExpress_PxPay: 'Payment Express'
    PaymentExpress_PxPost: 'Payment Express'
    PaymentSense: PaymentSense
    PaymentWall: PaymentWall
    PayPal_Express: PayPal
    PayPal_Pro: PayPal
    PayPal_Rest: PayPal
    PayPro: PayPro
    Paysafecard: paysafecard
    Paytrace_Check: 'PayTrace Check'
    Paytrace_CreditCard: 'PayTrace Credit Card'
    PayU: PayU
    NestPay: NestPay
    Pin: Pin
    Realex_Remote: Realex
    SagePay_Direct: 'Sage Pay'
    SagePay_Server: 'Sage Pay'
    SecurePay_DirectPost: SecurePay
    SecurePay_SecureXML: SecurePay
    SecureTrading: SecureTrading
    SecPay: 'SecPay (PayPoint.net)'
    Sisow: Sisow
    Skrill: Skrill
    Stripe: Stripe
    TargetPay_Directebanking: 'TargetPay Directebanking'
    TargetPay_Ideal: 'TargetPay iDEAL'
    TargetPay_Mrcash: 'TargetPay MrCash'
    UnionPay_Express: UnionPay
    UnionPay_LegacyMobile: UnionPay
    UnionPay_LegacyQuickPay: UnionPay
    WeChat_Express: WeChat
    WePay: WePay
    Wirecard: Wirecard
    WorldPay: WorldPay
    WorldPayXML: WorldPay
    Veritrans_VTWeb: 'Veritrans VT-Web'
    YandexMoney: 'Yandex.Money'
    YandexMoneyIndividual: 'Yandex.Money Individual'
```
