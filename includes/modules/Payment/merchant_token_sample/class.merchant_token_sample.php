<?php

/*
 * HostBill example cc processing module with Card tokenization support
 * Also known as Offsite Credit Cards processing (i.e. AuthorizeNet CIM)
 *
 * How tokenization works?
- Client enters his credit card details in your clientarea during purchase
- HostBill send those details to gateway (using capture_token), to capture payment for due invoice
- In return gateway provides re-usable token, that represents related credit card stored in gateway secure environment
- HostBill removes credit card data from database, leaving last 4 digits and token safely encrypted in local DB
- On next purchases/recurring charges for that customer HostBill will pass this token into gateway instead of full credit card details
 *
 * @see http://dev.hostbillapp.com/dev-kit/payment-gateway-modules/merchant-gateways-modules/
 *
 * 2012 HostBill -  Complete Client Management, Support and Billing Software
 * http://hostbillapp.com
 */

class Merchant_Token_Sample extends TokenPaymentModule {

    /**
     * Default module name to be displayed in adminarea
     */
    protected $modname = 'Sample Merchant Gateway Module with Token support.';
    /**
     * Description to be displayed in admianrea
     */
    protected $description = 'Credit Card Token';

    /**
     * List of currencies supported by gateway - if module supports all currencies - leave empty
     * @var array
     */
    protected $supportedCurrencies = array('USD', 'CAD', 'EUR', 'GBP');
   
    /**
     * Configuration array - types allowed: check, input, select
     */
    protected $configuration = array(
        'API Login' => array(
            'value' => '',
            'type' => 'input'
        ),
        'Transaction Key' => array(
            'value' => '',
            'type' => 'input'
        ),
        'MD5 Hash' => array(
            'value' => '',
            'type' => 'input'
        ),
        'Enable Test Mode' => array(
            'value' => '1',
            'type' => 'check'
        )
    );

    /**
     * Client choose to remove his credit card, or enter new card.
     * Old token should be remotely deleted - if method below is available it will be called
     * before entering new card details
     *
     * @param array $ccdetails Old credit card details
     * $ccdetails['token'] - token to remove
     */
    public function token_delete($ccdetails) {

        $options=array();
        $options['x_login'] = $this->configuration['API Login']['value'];
        $options['x_tran_key'] = $this->configuration['Transaction Key']['value'];
        $options['x_card_token'] = $ccdetails['token'];
        $options['x_action'] = 'Remove Token';

         $this->processData($options);
    }

    /**
     *  HostBill will call this method to attempt to charge/capture payment from credit card
     *
     * @param array $ccdetails An array with credit card details, contains following keys:
     * $ccdetails['cardnum'] - credit card number
     * $ccdetails['expdate'] - expiration date in format MMYY - i.e. 1112
     * $ccdetails['cardtype'] - CC type, ie. 'Visa'
     * If CVV is passed it will be available under:
     * $ccdetails['cvv']
     *
     * If card already been tokenized cardnum will consist only last 4 digits, and new element
     * $ccdetails['token'] - token to capture payment from
     * 
     * @return boolean True if card was charged
     */
    public function capture_token($ccdetails) {

        $options=array();
        $options['x_login'] = $this->configuration['API Login']['value'];
        $options['x_tran_key'] = $this->configuration['Transaction Key']['value'];


        /* CUSTOMER INFORMATION */
        $options['x_first_name'] = $this->client['firstname'];
        $options['x_last_name'] = $this->client['lastname'];
        $options['x_address'] = $this->client['address1'];
        $options['x_city'] = $this->client['city'];
        $options['x_state'] = $this->client['state'];
        $options['x_zip'] = $this->client['postcode'];
        $options['x_country'] = $this->client['country'];
        $options['x_phone'] = $this->client['phonenumber'];
        $options['x_email'] = $this->client['email'];
        $options['x_cust_id'] = $this->client['client_id'];


        /* ORDER INFORMATION */
        $options['x_invoice_num'] = $this->invoice_id;
        $options['x_description'] = $this->subject;
        $options['x_amount'] = $this->amount;


        /* CREDIT CARD INFORMATION */
        // we have token available, use it against payment gateway
        if ($ccdetails['token']) {
            $options['x_card_token'] = $ccdetails['token'];
        } else {
           $options['x_card_num'] = $ccdetails['cardnum'];
            $options['x_exp_date'] = $ccdetails['expdate'];    //MMYY
             if($ccdetails['cvv']) {
               //this is manual payment, client passed cvv code
              $options['x_card_code'] = $ccdetails['cvv'];
            }
        }


        //
        //SEND details to your credit card processor to validate and attempt to charge
        //
        $response = $this->processData($options);

        switch ($response['code']) {
            case 1:
                //charge succeeded, add transaction and log it
                $this->logActivity(array(
                    'output' => $response,
                    'result' => PaymentModule::PAYMENT_SUCCESS
                ));
                $this->addTransaction(array(
                    'client_id' => $this->client['client_id'],
                    'invoice_id' => $this->invoice_id,
                    'description' => "Payment for invoice ".$this->invoice_id,
                    'number' => $response['Transaction ID'],
                    'in' => $this->amount,
                    'fee' => '0'
                ));

                //Store token only if client allowed to - $ccetails['store']==true
                if($response['Token'] && $ccdetails['store']) {
                    return $response['Token'];  //return token to be stored
                } else {
                   return true; //capture success
                }
                break;

            case 2:
                $this->logActivity(array(
                    'output' => $response,
                    'result' => PaymentModule::PAYMENT_FAILURE
                ));
                return false;

                break;
        }
    }

    /**
     * This OPTIONAL helper function can be called from capture method,
     * i.e. connect to gateway API using CURL
     */
    private function processData($options) {
        //send data to cc processor, parse response
    }

}

