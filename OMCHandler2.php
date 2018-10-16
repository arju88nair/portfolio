<?php
require_once "modules/SalesOrder/handlers/AbstractBRMHandler.php";
require_once 'libraries/omc/BRMCustServices_v2.php';
require_once 'libraries/omc/pcmOpCustCommitCustomerRequest.php';

/**
 * Copyright (C) Covalense Technologies - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by nar, 4/6/18 3:04 PM
 */
class OMCHandler extends AbstractBRMHandler
{
    private $_customerValues;

// Data mapper class for creating data objects for the Sales order/Accounts creation

    function dataMapper()
    {
        global $current_user;

        $accountId = $this->_data->get('account_id');


        $account = vtws_retrieve(vtws_getWebserviceEntityId('Accounts', $accountId), $current_user);
        $accountNo = $account['account_no'];
        $accountName = $account['accountname'];
        $accountInfo = new stdClass();
        $accountInfo->account_NO = $accountNo;
        $accountInfo->business_TYPE = "1";
        $accountInfo->currency = "840";
        $accountInfo->poid = '0.0.0.1 /account -1 0';
        $accountInfo->elem = '0';

        $balanceInfo = new stdClass();
        $balanceInfo->BILLINFO = '';
        $balanceInfo->LIMIT = [
            'elem' => '840',
            'CREDIT_FLOOR' => '',
            'CREDIT_LIMIT' => "100000",
            'CREDIT_THRESHOLDS' => "100000",
            'CREDIT_THRESHOLDS_FIXED' => true
        ];
        $balanceInfo->NAME = 'Balance Group Account';
        $balanceInfo->poid = '0.0.0.1 /balance_group -1 0';

        $billInfo = new stdClass();
        $billInfo->BAL_INFO = '';
        $billInfo->BILL_WHEN = "1";
        $billInfo->BILLINFO_ID = 'Bill Unit(1)';
        $billInfo->PAY_TYPE = "10001";
        $billInfo->PAYINFO = '';
        $billInfo->poid = '0.0.0.1 /billinfo -1 0';

        $localeObj = new stdClass();
        $localeObj->LOCALE = 'en_US';

        $contactId = $this->_data->get('contact_id');
        $contact = vtws_retrieve(vtws_getWebserviceEntityId('Contacts', $contactId), $current_user);

        $inventoryInfo = new stdClass();
        $inventoryInfo->elem = "0";
        $inventoryInfo->delivery_DESCR = $contact['email'];
        $inventoryInfo->delivery_PREFER = "0";
        $inventoryInfo->email_ADDR = $contact['email'];
        $inventoryInfo->address = $contact['mailingstreet'];
         $inventoryInfo->state = $contact['mailingstate'];
        $inventoryInfo->country = $contact['mailingcountry'];
        $inventoryInfo->zip = $contact['mailingzip'];
        $inventoryInfo->city = $contact['mailingcity'];

        $inventoryInfo->inv_TERMS = "0";
        $inventoryInfo->name = $contact['firstname'];
        //the following 3 values cannot be changed as OMC is not allowing to


        $inheritedInfoObj = new stdClass();
        $inheritedInfoObj->inv_INFO = $inventoryInfo;

        $payinfoObj = new stdClass();
        $payinfoObj->elem = "0";
        $payinfoObj->inherited_INFO = $inheritedInfoObj;
        $payinfoObj->inv_TYPE = '1';
        $payinfoObj->pay_TYPE = '10001';
        $payinfoObj->poid = '0.0.0.1 /payinfo/invoice -1 0';

        //get the related line item
        $data = $this->_data;
        if (isset($data->focus->column_fields['LineItems'])) {
            $relatedProducts = $data->focus->column_fields['LineItems'];
        } else {
            $salesorder = vtws_retrieve(vtws_getWebserviceEntityId('SalesOrder', $data->focus->id), $this->_user);
            $relatedProducts = $salesorder['LineItems'];
        }
        
        $productDealObj = [];
        foreach ($relatedProducts as $index => $relatedProduct) {
            $wsProductId = $relatedProduct["productid"];
            list($tabId, $entityId) = explode("x", $wsProductId);

            if (empty($entityId)) {
                //the entityId is set as crmid when coming directly from webservice create
                $entityType = $relatedProduct['entitytype'];
                $entityId = $relatedProduct["productid"];
            }

            $product = $this->_getProductInfo($entityId);

            $arr = [];
            $arr = new stdClass();
            $arr->elem = $index;
            $arr->status = "1";
            $arr->descr = "purchased";
            $arr->product_OBJ = $product['cf_976'];
            $arr->quantity = $relatedProduct['quantity'];
            $productDealObj[] = $arr;
        }


        if (empty($product)) return false;

        $productObj = new stdClass();
        $productObj->NAME = $product['productname'];
        $productObj->DESCR = $product['description'];
        $productObj->PRODUCT_OBJ = $product['poid'];
        $productObj->QUANTITY = "1";
        $productObj->STATUS = "1";

        $dealsObj = new stdClass();
        $dealsObj->ACCOUNT_OBJ = '0.0.0.1 /account -1 0';
        $dealsObj->DEAL_OBJ = '0.0.0.1 /deal 341283 0';
        $dealsObj->START_T = '1970-01-01T00:00:00Z';
        $dealsObj->END_T = '1970-01-01T00:00:00Z';
        $dealsObj->NAME = 'end2end_scenario5_bundle';
        $dealsObj->PRODUCTS = $productObj;


        $servicesObj = new stdClass();
        $servicesObj->elem = "0";
        $servicesObj->login = $accountName . $account['account_no'];
        $servicesObj->passwd_CLEAR = "Cvly@12$";
        $servicesObj->service_OBJ = "0.0.0.1 {$product['website']} -1 0";//"0.0.0.1 /service/cloud/ip -1 0";

        $productDeal = new stdClass();

        /*$productDealObj = new stdClass();
        $productDealObj->elem = "0";
        $productDealObj->status = "1";
        $productDealObj->descr = "purchased";
        $productDealObj->product_OBJ = "0.0.0.1 /product 2344272 1";
        $productDealObj->quantity = "1";
         */
        $productDeal->products = $productDealObj;

        $servicesObj->deal_INFO = $productDeal;
        $productDeal->poid = "0.0.0.1 /deal -1 0";
        $productDeal->start_T = "1970-01-01T00:00:00";
        $productDeal->end_T = "1970-01-01T00:00:00";
        $productDeal->name =$this->_data->get('subject');


//        $dealsProduct->products=$productDealObj;


        $inputFlist = new stdClass();
        $inputFlist->acctinfo = $accountInfo;
        $inputFlist->poid = "0.0.0.1 /plan -1 0";
        $inputFlist->end_T =null;
        $inputFlist->flags = "0";
        $inputFlist->nameinfo = array([
            'elem' => '1',
            'address' => $contact['mailingstreet'],
            'city' => $contact['mailingcity'],
            'contact_TYPE' => 'Account holder',
            'email_ADDR' => $contact['email'],
            'first_NAME' => $contact['firstname'],
            'last_NAME' => $contact['lastname'],

            //the following 3 values should not be changed as OMC does not allow
            'state' => $contact['mailingstate'],
            'country' =>$contact['mailingcountry'],
            'zip' => $contact['mailingzip'],
        ]);
        $inputFlist->payinfo = array($payinfoObj);

//        $inputFlist->BAL_INFO = $balanceInfo;
//        $inputFlist->BILLINFO = $billInfo;
//        $inputFlist->DESCR = $account['description'];
//        $inputFlist->FLAGS = 0;
//        $inputFlist->LOCALES = $localeObj;
//
        $inputFlist->services = array($servicesObj);
//        $inputFlist->Order_Flist = null;

        $customerValues = new stdClass();
        $customerValues->flags = "0";
        $customerValues->function_name = "CUST_COMMIT_CUSTOMER";
        $customerValues->Create_Cust_Flist = $inputFlist;

        $this->_customerValues = $customerValues;
    }

    function process()
    {
        $dataMapper = $this->dataMapper();
        $status = $this->_data->get('sostatus');
        if ($status === 'Created') {
            $customerValues = $this->_customerValues;
//            echo "<pre>";
//            print_r($customerValues);
//            echo "</pre>";
//            die;

//            $obj = new BRMCustServices_v2(['trace' => 1], 'http://apps.us.oracle.com:8011/BrmWebServices/BRMCustServices_v2?wsdl');

            try {

                $writer = new ActiveMQWriter();
                $writer->setTargetQueue('OmcQueue');
                $status = $writer->write(json_encode($customerValues));
                if ($status === false) {
                    $failureManager = new FailureManager();
                    $failureManager->addToQueue('WriteToActiveMQ', json_encode($customerValues));
                }
                return $status;
            } catch (Exception $e) {
                echo "s";
                die;
                return false;
            }
        }
    }


}
