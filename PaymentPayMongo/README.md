Checkout API Implementation
How do I implement the Checkout API?

Suggest Edits
The Checkout API is used to accept these payment methods:

Category	Payment Method
Credit & Debit Card payments	Straight card payments
E-Wallets	Maya
GCash
GrabPay
Buy Now, Pay Later	BillEase
Direct Online Banking	BPI Online
UnionBank Online
BDO Online via Brankas
Landbank Online via Brankas
Metrobank Online via Brankas
QR Code Payments	QRPh
  

The Checkout API is the easiest way to integrate PayMongo into your custom made website or app. Building an integration with the Checkout API involves two actions: creating a Checkout Session and redirecting the customer to the Checkout Session. To fully automate your payment journey, we recommend the use of webhooks to listen each time a payment has been made.

Implementation
📘
In navigating this guide, you will encounter API References. API References are pages where you can test out and view the fields for each API call. These pages also contains a description on the available fields.

1. Checking allowed payment methods
API Reference
The allowed payment methods might be different for each account. You can call the Retrieve list of possible merchant payment methods endpoint to view which payment methods are eligible for your account.

 

2. Creating a Checkout Session from the server-side
API Reference
To start receiving payments with the Checkout API, you must create a Checkout Session object. You must create your Checkout Session from your backend using your secret API key. One order or transaction should have a unique Checkout Session object. When filling up the payment_method_types field, kindly make sure that this matches with your allowed payment methods. You could opt to filter out the payment method type. Send the response to the client-side. The checkout_url will be used to redirect the customer to a PayMongo checkout page.

Optional: We recommend you to store the Checkout Session object's ID (example: cs_zmkoMv8GQBSdaPZfJrmFnifG) in your database. This would allow you to retrieve the Checkout Session object using your Secret key.

 

3. Redirecting the customer for payment
Using the checkout_url response from the first step, redirect the customer to the PayMongo checkout page.

Note: Using iframes might lead to inconsistent behavior; this is due to our payment providers setting their own CORS policies.

JavaScript

// The example is using axios as the REST client on the client-side.

//  This is the call to your server side when creating a Checkout Session
//  Note that this is entirely up to your implementation
axios.post(
  'https://yourserver.com/create-a-checkout',
  {
    data: {
      your_custom_order_parameters: {
        items: [{
          item: "Bag",
          quantity: 2,
          amount: 30000
				}],
      }
    }
  },
).then(function(response) {
  
  // Parsing the response and redirecting the customer for authentication
  const checkout_url = response.data.attributes.checkout_url

	// Option 1: similar behavior as an HTTP redirect
	window.location.replace(checkout_url);

	// Option 2: similar behavior as clicking on a link
	window.location.href = checkout_url;
})
After redirecting the customer to the PayMongo Checkout, the customer would be able to pay with the payment method of their choice.

If the payment is successful, the customer would be redirected to the nominated success_url or a generic payment success screen.

If the payment failed, the customer would be redirected to back to the payment method selection page.

 

3. Finish
The payment should reflect on your PayMongo dashboard.

If you've enabled the send_email_receipt field, your customer would receive an email receipt after a successful transaction.

You could use Webhooks to listen to the status of the payment in your backend. With webhooks, we'll be sending your servers a POST message for your nominated events. Relevant webhook events are payment.paid, payment.failed, checkout_session.payment.paid.

🚧
Webhooks

You should not create a webhook in your code. You can try using API tools such as Postman or curl to create your webhook once. Please take note that you should not create multiple webhooks for every source that will be created. One webhook with events payment.paid and payment.failed or one webhook with the checkout_session.payment.paid event is enough to receive the payment information.

Event notifications will be sent to your registered webhook endpoint containing relevant payment information as shown below:

checkout_session.payment.paid Event for a successful Checkout Session payment

{
  "data":{
    "id":"evt_YMqJAS2eZ1V1p5wzzqXP4uEC",
    "type":"event",
    "attributes":{
      "type":"checkout_session.payment.paid",
      "livemode":true,
      "data":{
        "id":"cs_CbFCTDfxvMFNjwjVi26Uzhtj",
        "type":"checkout_session",
        "attributes":{
          "billing":{
            "address":{
              "city":"Taguig",
              "country":"PH",
              "line1":"address line 1",
              "line2":"address line 2",
              "postal_code":"1234",
              "state":"PH-MNL"
            },
            "email":"john.doe@paymongo.com",
            "name":"John doe",
            "phone":null
          },
          "checkout_url":"https://checkout.paymongo.com/cs_CbFCTasdvMFNjwjVi26Uzhtj#fneklwafoifdlsa123f5v1=",
          "client_key":"cs_CbFCTDfxvMFNjwjVi26Uzhtj_client_YyXPYEejtNMmeZfcg5AAzXQ8",
          "description":"The beanie products.",
          "line_items":[
            {
              "amount":550,
              "currency":"PHP",
              "description":"A fresh bag of coffee beanines.",
              "images":[
                "https://images.unsplash.com/photo-1612346903007-b5ac8bb135bb?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1740&q=80"
              ],
              "name":"Beanines",
              "quantity":100
            },
            {
              "amount":125000,
              "currency":"PHP",
              "description":"A locally grown Jasmine Rice.",
              "images":[
                "https://images.unsplash.com/photo-1612346903007-b5ac8bb135bb?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1740&q=80"
              ],
              "name":"Jasmine Rice 25KG",
              "quantity":1
            }
          ],
          "livemode":true,
          "merchant":"Paymongo Test Account",
          "payments":[
            {
              "id":"pay_gPSJ6SB24SVEa5hH8LrXBtd4",
              "type":"payment",
              "attributes":{
                "access_url":null,
                "amount":180000,
                "balance_transaction_id":"bal_txn_ck7GWJfM19q5YFQKFqo17vbu",
                "billing":{
                  "address":{
                    "city":"Taguig",
                    "country":"PH",
                    "line1":"Address 1",
                    "line2":"",
                    "postal_code":"1234",
                    "state":"Metro Manila"
                  },
                  "email":"juan.delacruz@paymongo.com",
                  "name":"Customer Name",
                  "phone":""
                },
                "currency":"PHP",
                "description":"The beanie products.",
                "disputed":false,
                "external_reference_number":null,
                "fee":9000,
                "foreign_fee":1800,
                "livemode":true,
                "net_amount":169393,
                "origin":"api",
                "payment_intent_id":"pi_aJsHfCD2AmR9V5KBvtkW8XY2",
                "payout":null,
                "source":{
                  "id":"card_7EAQrE19W7ESJZkQdoQiy6gj",
                  "type":"card",
                  "brand":"mastercard",
                  "country":"BJ",
                  "last4":"2346"
                },
                "statement_descriptor":"Paymongo Test Account",
                "status":"paid",
                "tax_amount":193,
                "metadata":{
                  "customer_number":"42jn1i53",
                  "remarks":"Customer cannot receive items during weekends or evening",
                  "notes":"Additional packaging. Include free samples"
                },
                "refunds":[
                  
                ],
                "taxes":[
                  {
                    "amount":193,
                    "currency":"PHP",
                    "inclusive":true,
                    "name":"Withholding Tax",
                    "type":"withholding_tax",
                    "value":"200_bps"
                  },
                  {
                    "amount":1157,
                    "currency":"PHP",
                    "inclusive":true,
                    "name":"VAT",
                    "type":"vat",
                    "value":"1200_bps"
                  }
                ],
                "available_at":1671526800,
                "created_at":1671438226,
                "credited_at":1671613200,
                "paid_at":1671438228,
                "updated_at":1671438228
              }
            }
          ],
          "payment_intent":{
            "id":"pi_aJsHfCD2AmR9V5KBvtkW8XY2",
            "type":"payment_intent",
            "attributes":{
              "amount":180000,
              "capture_type":"automatic",
              "client_key":"pi_aJsHfCD2AmR9V5KBvtkW8XY2_client_HBSX2uMBbpapxbPVhsy6zdJz",
              "currency":"PHP",
              "description":"The beanie products.",
              "livemode":true,
              "statement_descriptor":"Paymongo Test Account",
              "status":"succeeded",
              "last_payment_error":null,
              "payment_method_allowed":[
                "card",
                "gcash"
              ],
              "payments":[
                {
                  "id":"pay_gPSJ6SB24SVEa5hH8LrXBtd4",
                  "type":"payment",
                  "attributes":{
                    "access_url":null,
                    "amount":180000,
                    "balance_transaction_id":"bal_txn_ck7GWJfM19q5YFQKFqo17vbu",
                    "billing":{
                      "address":{
                        "city":"Taguig",
                        "country":"PH",
                        "line1":"Address 1",
                        "line2":"",
                        "postal_code":"1234",
                        "state":"Metro Manila"
                      },
                      "email":"juan.delacruz@paymongo.com",
                      "name":"Customer Name",
                      "phone":""
                    },
                    "currency":"PHP",
                    "description":"The beanie products.",
                    "disputed":false,
                    "external_reference_number":null,
                    "fee":9000,
                    "foreign_fee":1800,
                    "livemode":true,
                    "net_amount":169393,
                    "origin":"api",
                    "payment_intent_id":"pi_aJsHfCD2AmR9V5KBvtkW8XY2",
                    "payout":null,
                    "source":{
                      "id":"card_7EAQrE19W7ESJZkQdoQiy6gj",
                      "type":"card",
                      "brand":"mastercard",
                      "country":"BJ",
                      "last4":"2346"
                    },
                    "statement_descriptor":"Paymongo Test Account",
                    "status":"paid",
                    "tax_amount":193,
                    "metadata":{
                      "customer_number":"42jn1i53",
                      "remarks":"Customer cannot receive items during weekends or evening",
                      "notes":"Additional packaging. Include free samples"
                    },
                    "refunds":[
                      
                    ],
                    "taxes":[
                      {
                        "amount":193,
                        "currency":"PHP",
                        "inclusive":true,
                        "name":"Withholding Tax",
                        "type":"withholding_tax",
                        "value":"200_bps"
                      },
                      {
                        "amount":1157,
                        "currency":"PHP",
                        "inclusive":true,
                        "name":"VAT",
                        "type":"vat",
                        "value":"1200_bps"
                      }
                    ],
                    "available_at":1671526800,
                    "created_at":1671438226,
                    "credited_at":1671613200,
                    "paid_at":1671438228,
                    "updated_at":1671438228
                  }
                }
              ],
              "next_action":null,
              "payment_method_options":{
                "card":{
                  "request_three_d_secure":"any",
                  "installments":{
                    "enabled":true
                  }
                }
              },
              "metadata":{
                "remarks":"Customer cannot receive items during weekends or evening",
                "customer_number":"42jn1i53",
                "notes":"Additional packaging. Include free samples"
              },
              "setup_future_usage":null,
              "created_at":1671437933,
              "updated_at":1671438228
            }
          },
          "payment_method_types":[
            "card",
            "gcash",
            "atome"
          ],
          "reference_number":"m2m39sj43h5lfFSA1sd",
          "send_email_receipt":true,
          "show_description":true,
          "show_line_items":true,
          "status":"active",
          "success_url":"https://google.com",
          "created_at":1671437933,
          "updated_at":1671437933,
          "metadata":{
            "notes":"Additional packaging. Include free samples",
            "customer_number":"42jn1i53",
            "remarks":"Customer cannot receive items during weekends or evening"
          }
        }
      },
      "previous_data":{
        
      },
      "created_at":1674197593,
      "updated_at":1674197593
    }
  }
}
payment.paid Event for successful payments

{
    "data": {
        "id": "evt_9w6KTxQY3hmuDQaALHoAZnRp",
        "type": "event",
        "attributes": {
            "type": "payment.paid",
            "livemode": false,
            "data": {
                "id": "pay_gPSJ6SB24SVEa5hH8LrXBtd4",
                "type": "payment",
                "attributes": {
                    "access_url": null,
                    "amount": 180000,
                    "balance_transaction_id": "bal_txn_ck7GWJfM19q5YFQKFqo17vbu",
                    "billing": {
                        "address": {
                            "city": "Taguig",
                            "country": "PH",
                            "line1": "Address 1",
                            "line2": "",
                            "postal_code": "1234",
                            "state": "Metro Manila"
                        },
                        "email": "juan.delacruz@paymongo.com",
                        "name": "Customer Name",
                        "phone": ""
                    },
                    "currency": "PHP",
                    "description": "The beanie products.",
                    "disputed": false,
                    "external_reference_number": null,
                    "fee": 9000,
                    "foreign_fee": 1800,
                    "livemode": true,
                    "net_amount": 169393,
                    "origin": "api",
                    "payment_intent_id": "pi_aJsHfCD2AmR9V5KBvtkW8XY2",
                    "payout": null,
                    "source": {
                        "id": "card_7EAQrE19W7ESJZkQdoQiy6gj",
                        "type": "card",
                        "brand": "mastercard",
                        "country": "BJ",
                        "last4": "2346"
                    },
                    "statement_descriptor": "Paymongo Test Account",
                    "status": "paid",
                    "tax_amount": 193,
                    "metadata": {
                        "customer_number": "42jn1i53",
                        "remarks": "Customer cannot receive items during weekends or evening",
                        "notes": "Additional packaging. Include free samples"
                    },
                    "refunds": [],
                    "taxes": [
                        {
                            "amount": 193,
                            "currency": "PHP",
                            "inclusive": true,
                            "name": "Withholding Tax",
                            "type": "withholding_tax",
                            "value": "200_bps"
                        },
                        {
                            "amount": 1157,
                            "currency": "PHP",
                            "inclusive": true,
                            "name": "VAT",
                            "type": "vat",
                            "value": "1200_bps"
                        }
                    ],
                    "available_at": 1671526800,
                    "created_at": 1671438226,
                    "credited_at": 1671613200,
                    "paid_at": 1671438228,
                    "updated_at": 1671438228
                }
            }
        }
    }
}
👍
You can determine if a payment is successful or not by checking the event type or the status of the payment object.