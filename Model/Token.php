<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\Worldpay\Model;

/** 
 *  processing the Request of saved card
 */
class Token
{
    /**
     * @var \Sapient\Worldpay\Logger\WorldpayLogger
     */
    protected $wplogger;

    /**
     * @var \Sapient\Worldpay\Model\Request
     */
    protected $_request;

    /**
     * Constructor
     *
     * @param \Sapient\Worldpay\Logger\WorldpayLogger $wplogger
     * @param \Sapient\Worldpay\Helper\Data $worldpayhelper
     * @param \Sapient\Worldpay\Model\Request $request   
     */
    public function __construct(
        \Sapient\Worldpay\Logger\WorldpayLogger $wplogger,
        \Sapient\Worldpay\Helper\Data $worldpayhelper,
        \Sapient\Worldpay\Model\Request $request
    ) {
        $this->wplogger = $wplogger;
        $this->_request = $request;
        $this->worldpayhelper = $worldpayhelper;
    }

     /**
      * Process the request for Saved Card
      *
      * @param array $customerData
      * @param array $paymentData   
      * @return SimpleXMLElement 
      */
    public function getPaymentToken($customerData, $paymentData)
    {
        $this->wplogger->info("*** TOKEN Method called [befor order place]");
        $xmlTokenParams =  array(
            'merchantCode'     => $this->worldpayhelper->getMerchantCode($paymentDetails['additional_data']['cc_type']),
            'authenticatedShopperID'        => $customerData['id'],
            'tokenScope'     => "shopper",
            'tokenEventReference' => 'jkd',
            'tokenReason'     => 'ClothesDepartment',
            'paymentDetails'   => $this->_getPaymentDetails($paymentData),
            'customerAddress'      => $customerData['addresses'][0],
            'acceptHeader'     => php_sapi_name() !== "cli" ? $_SERVER['HTTP_ACCEPT'] : '',
            'userAgentHeader'  => php_sapi_name() !== "cli" ? $_SERVER['HTTP_USER_AGENT'] : '',
            'method'           => $paymentData['method'],
            'orderStoreId'     => $customerData['store_id']
        );
        $this->xmlDirectOrderToken = new \Sapient\Worldpay\Model\XmlBuilder\DirectOrderToken();

        $orderSimpleXml = $this->xmlDirectOrderToken->build(
            $xmlTokenParams['merchantCode'],
            $xmlTokenParams['authenticatedShopperID'],
            $xmlTokenParams['tokenScope'],
            $xmlTokenParams['tokenEventReference'],
            $xmlTokenParams['tokenReason'],
            $xmlTokenParams['paymentDetails'],
            $xmlTokenParams['customerAddress'],
            $xmlTokenParams['acceptHeader'],
            $xmlTokenParams['userAgentHeader'],
            $xmlTokenParams['method'],
            $xmlTokenParams['orderStoreId']
        );
        //default return remove it
        return $this->_sendRequest(
            dom_import_simplexml($orderSimpleXml)->ownerDocument,
            $this->worldpayhelper->getXmlUsername($paymentDetails['additional_data']['cc_type']),
            $this->worldpayhelper->getXmlPassword($paymentDetails['additional_data']['cc_type'])
        );
    }

    /**
     * @param array $paymentDetails
     * @return array $details
     */
    private function _getPaymentDetails($paymentDetails)
    {
        $method = $paymentDetails['method'];
        if (isset($paymentDetails['encryptedData'])) {
            $details = array(
                'encryptedData' => $paymentDetails['encryptedData']
            );
        } else {
            $details = array(
                'paymentType' => $paymentDetails['additional_data']['cc_type'],
                'cardNumber' => $paymentDetails['additional_data']['cc_number'],
                'expiryMonth' => $paymentDetails['additional_data']['cc_exp_month'],
                'expiryYear' => $paymentDetails['additional_data']['cc_exp_year'],
                'cardHolderName' => $paymentDetails['additional_data']['cc_name'],
            );
            if (isset($paymentDetails['additional_data']['cc_cid'])) {
                $details['cvc'] = $paymentDetails['additional_data']['cc_cid'];
            }
        }
        $details['sessionId'] = session_id();
        return $details;
    }

    /**
     * call api to process send request
     *
     * @param SimpleXMLElement $xml
     * @param string  $username
     * @param string  $password
     * @return SimpleXMLElement $response
     */
    protected function _sendRequest($xml, $username, $password)
    {
        $response = $this->_request->sendRequest($xml, $username, $password);
        $this->_checkForError($response);
        return $response;
    }
    
    /**
     * Check error while processing the request
     *
     * @param SimpleXMLElement $xml
     * @throws Exception 
     */
    protected function _checkForError($response)
    {
        $paymentService = new \SimpleXmlElement($response);
        $error = $paymentService->xpath('//error');
        if ($error) {
            $this->_wplogger->error('An error occurred while sending the request');
            $this->_wplogger->error('Error (code ' . $error[0]['code'] . '): ' . $error[0]);
            throw new Exception();
        }
    }
}
