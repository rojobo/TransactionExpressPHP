<?php

    /*
    Created: 4/08/2015
    Author: Bryan Rojo
    */
    error_reporting(E_ALL);
    ini_set('precision', '19');
    ini_set('display_errors', '1');
    ini_set('soap.wsdl_cache_enabled', '0');
    ini_set('soap.wsdl_cache_ttl','0');
    $backDir =  str_replace("/TransactionExpress", "", __DIR__);
    require_once __DIR__ .'/Merchant.class.php';
    require_once __DIR__ .'/Phone.class.php';
    require_once __DIR__ .'/Card.class.php';
    require_once __DIR__ .'/BankAccount.class.php';
    require_once __DIR__ .'/Payment.class.php';
    require_once __DIR__ .'/Contact.class.php';
    require_once __DIR__ .'/Customer.class.php';
    require_once __DIR__ .'/RecurringProfile.class.php';
    require_once __DIR__ .'/RecurringPayment.class.php';
    require_once __DIR__ .'/Wallet.class.php';
    require_once __DIR__ .'/FindCustomer.class.php';
    require_once __DIR__ .'/FindRecurringProfile.class.php';
    require_once __DIR__ .'/FindWallet.class.php';
    require_once $backDir .'/Agents/Agents.class.php';
    require_once $backDir .'/Users/Users.class.php';
    require_once $backDir .'/Company/Company.class.php';
    require_once $backDir .'/Database/Database.class.php';
    require_once $backDir .'/Provider/Provider.class.php';

    class TransactionExpress {

        private $environment,$connection,$security;
        private $merchantId = "7777777777";//TESTING  
        private $approvalCodes = array('00','11','08');
        private $regKey = "HHHHHHHHHHHH";//TESTING  
        private $client;

        public function __construct($environment,$connection,$security) {
            $this->environment = $environment;
            $this->connection = $connection;
            $this->security = $security;
            //$this->client = new SoapClient("https://ws.transactionexpress.com/portal/merchantframework/MerchantWebServices-v1?wsdl", array('trace'=>1, 'exceptions'=>1)); //PRODUCTION ONLY
            $this->client = new SoapClient("https://ws.cert.transactionexpress.com/portal/merchantframework/MerchantWebServices-v1?wsdl", array('trace'=>1, 'exceptions'=>1)); //TESTING ONLY
        }

        public function post_new_recurring_payment($POST) {//This creates a customer, wallet and recurring profile, if the payment gets declined we use appropriate handlers, if its successful we create database, users, etc and store trans express credentials.
            $amount = $this->calculateFee($POST,false);

            if( !empty($POST["walletId"]) && !empty($POST["recurringProfileId"]) && !empty($POST["customerId"]) ) {//User already exist so we are just updating it.
                $updateBilling = $this->post_update_billing_info($POST);
                if ($updateBilling["response"] == "success") {
                    $updatePayment = $this->post_update_payment_info($POST);
                    if ($updatePayment["response"] == "success") {
                        $saleResult = $this->post_manual_wallet_payment($POST, $amount);
                        return $saleResult;
                    }
                    else {//This is a very rare scenario but If update payment throws an exception during registration it is most likely because they switched from a credit card to ACH or viceversa we have to make sure we set the status back to inactive.
                        $POST["recurProfStat"] = 1;
                        $setWalletToInactive = $this->post_update_plan_info($POST);
                        return $updatePayment;
                    }
                }
                else {
                    return $updateBilling;
                }

            }
            else {//User profile has not been created create a new customer, wallet and recurring profile

                $name = $POST["billingName"] . ' ' . $POST["billingLastName"];

                $params = array(
                    "cust" => new cust()
                );

                if($POST["paymentRadio"] == 'credit') {
                    $pmtType = 5;
                    $params["cust"]->setCustomerWallet($name, $POST["company"], $POST["billingPhone"], $POST["billingAddr"], $POST["billingCity"], $POST["billingState"], $POST["billingZip"], $POST["email"], $POST["paymentRadio"], $POST["ccNumber"], ($POST["expYear"].$POST["expMonth"]), $POST["ccType"]);
                }
                else if($POST["paymentRadio"] == 'checking') {
                    $pmtType = 4;
                    $params["cust"]->setCustomerWallet($name, $POST["company"], $POST["billingPhone"], $POST["billingAddr"], $POST["billingCity"], $POST["billingState"], $POST["billingZip"], $POST["email"], $POST["paymentRadio"], $POST["routingNumber"], $POST["accountNumber"], $POST["acctType"]);
                }

                $params['merc'] = new merc($this->merchantId, $this->regKey);
                $params['merc']->setProdType($pmtType);
                $date1month = $this->getW3CDate();
                $params['recurProf'] = new recurProf();
                $params['recurProf']->addRecurringProfilePayment($amount, $date1month, 30, $pmtType);

                try {
                    $result = $this->client->__soapCall("UpdtRecurrProf", array("parameters" => $params));
                    if( in_array( $result->rspCode,  $this->approvalCodes) ) {
                        $POST["recurringProfileId"] = $result->id;
                        $POST["customerId"] = $result->custId;
                        $POST["walletId"] = $result->pmtId;
                        $saleResult = $this->post_manual_wallet_payment($POST, $amount);
                        return $saleResult;
                    }
                    else {
                        return array("response"=>"error","result"=>$result);
                    }

                }
                catch(SoapFault $e) {
                    $result = array("message"=>$e->faultstring,"code"=>$e->faultcode,"request"=>$this->client->__getLastRequest(),"response"=>$this->client->__getLastResponse());
                    return array("response"=>"exception","result"=>$result);

                }
            }

        }

        public function post_recurring_profile_details($POST){ //Update our transexpress table with the IDs we get back from trans express
            $recurringProfileId = $POST['recurringProfileId'];
            $customerId = $POST['customerId'];
            $walletId = $POST['walletId'];

            $insertData = array("recurringProfileId"=>$recurringProfileId,"customerId"=>$customerId,"walletId"=>$walletId,"entryby"=>"AUTOMATED SIGNUP PROCESS");

            $insert = $this->connection->format_query($insertData);

            $sql = "INSERT INTO reference.transexpress SET $insert,tstampentry=NOW()";
            $result = $this->connection->query($sql);

            return array("result"=>!$this->connection->query_failed($result),"response"=>$result);
        }

        public function post_manual_wallet_payment($POST, $amt) { //Charges user using their funding source, if the payment fails we go back to the shopping cart show them an error message and wait for an updated method of payment.
            if($POST["paymentRadio"] == 'credit') {
                $params = array(
                    "merc" => new merc($this->merchantId, $this->regKey),
                    "tranCode" => 14,//Manual transaction code
                    "reqAmt" => $amt,
                    "indCode" => 2,//e-commerce
                    "recurMan" => new recurMan($POST['walletId'])
                );
            }
            else if($POST["paymentRadio"] == 'checking') {
                $params = array(
                    "merc" => new merc($this->merchantId, $this->regKey),
                    "tranCode" => 14,//Manual transaction code
                    "reqAmt" => $amt,
                    "recurMan" => new recurMan($POST['walletId'])
                );
            }

            try {
                $walletSale = $this->client->__soapCall("SendTran", array("parameters" => $params));
                if( in_array( $walletSale->rspCode,  $this->approvalCodes) ) {
                    $create_client_response =$this->post_create_client($POST);

                    $return = array("response"=>"success","result"=>$walletSale,"creation_response"=>"success");

                    if ($create_client_response['result'] === false) {
                        $return["creation_response"] = "error";
                        $return['creation_error'] = $create_client_response['error'];
                    }

                    return $return;
                }
                else {
                    return  array("response"=>"error","result"=>$walletSale,"customerId"=>(string)$POST["customerId"],"walletId"=>(string)$POST["walletId"],"recurringProfileId"=>(string)$POST["recurringProfileId"]);
                }

            }
            catch (Exception $e) {
                $result = array("code"=>$e->faultcode,"message"=>$e->getMessage(),"desc"=>$this->client->__getLastResponse(),"request"=>$this->client->__getLastRequest());
                return array("response"=>"exception","result"=>$result);

            }


        }


        public function get_find_customer_details($GET) { //Fetch Customer billing info, wallet, and recurring profile details
            $params = array(
                "merc" => new merc($this->merchantId, $this->regKey),
                "type" => 1,
                "recurProfCrta" => new recurProfCrta($GET["recurringProfileId"]),
                "custCrta" => new custCrta($GET["customerId"]),
                "pmtCrta" => new pmtCrta($GET["walletId"])
            );

            try {
                $customerDetails = $this->client->__soapCall("FndRecurrProf", array("parameters" => $params));
                return  array("response"=>"success","result"=>$customerDetails);

            }
            catch (Exception $e) {
                $result = array("code"=>$e->faultcode,"message"=>$e->getMessage(),"desc"=>$this->client->__getLastResponse(),"request"=>$this->client->__getLastRequest());
                return array("response"=>"exception","result"=>$result);

            }

        }

        public function get_find_plan_details($GET) { //Fetch Customer recurring profile details
            $params = array(
                "merc" => new merc($this->merchantId, $this->regKey),
                "type" => 1,
                "recurProfCrta" => new recurProfCrta($GET["recurringProfileId"])
            );

            try {
                $planDetails = $this->client->__soapCall("FndRecurrProf", array("parameters" => $params));
                return  array("response"=>"success","result"=>$planDetails);

            }
            catch (Exception $e) {
                $result = array("code"=>$e->faultcode,"message"=>$e->getMessage(),"desc"=>$this->client->__getLastResponse(),"request"=>$this->client->__getLastRequest());
                return array("response"=>"exception","result"=>$result);

            }

        }

        public function post_update_billing_info($POST) {//Update existing customer
            $fullName = $POST["billingName"] . ' ' . $POST["billingLastName"];
            $params = array(
                "merc" => new merc($this->merchantId, $this->regKey),
                "cust" => new cust()
            );

            $params["cust"]->updateCustomer($POST["customerId"], $fullName, $POST["company"], $POST["billingPhone"], $POST["billingAddr"], $POST["billingCity"], $POST["billingState"], $POST["billingZip"], $POST["email"]);

            try {
                $result = $this->client->__soapCall("UpdtRecurrProf", array("parameters" => $params));
                if( in_array( $result->rspCode,  $this->approvalCodes) ) {
                    return  array("response"=>"success", "result"=>$result);
                }
                else {
                    return array("response"=>"error", "result"=>$result);
                }
            }
            catch (Exception $e) {
                $result = array("code"=>$e->faultcode,"message"=>$e->getMessage(),"desc"=>$this->client->__getLastResponse(),"request"=>$this->client->__getLastRequest());
                return array("response"=>"exception","result"=>$result);

            }

        }

        public function post_update_payment_info($POST) {//Update Existing Wallet
            $params = array(
                "merc" => new merc($this->merchantId, $this->regKey),
                "cust" => new cust()
            );

            if($POST["paymentRadio"] == 'credit') {
                $pmtType = 5;
                $params["cust"]->updateWallet($POST["customerId"], $POST["walletId"], $POST["paymentRadio"], $POST["ccNumber"], ($POST["expYear"].$POST["expMonth"]), $POST["ccType"], $POST["acctNameCard"]);
            }
            else if($POST["paymentRadio"] == 'checking') {
                $pmtType = 4;
                $params["cust"]->updateWallet($POST["customerId"], $POST["walletId"], $POST["paymentRadio"], $POST["routingNumber"], $POST["accountNumber"], $POST["acctType"], $POST["acctNameBank"]);
            }

            $params['merc']->setProdType($pmtType);

            try {
                $result = $this->client->__soapCall("UpdtRecurrProf", array("parameters" => $params));
                if( in_array( $result->rspCode,  $this->approvalCodes) ) {
                    return  array("response"=>"success","result"=>$result);
                }
                else {
                    return array("response"=>"error","result"=>$result);
                }
            }
            catch (Exception $e) {
                $result = array("code"=>$e->faultcode,"message"=>$e->getMessage(),"desc"=>$this->client->__getLastResponse(),"request"=>$this->client->__getLastRequest());
                return array("response"=>"exception","result"=>$result);

            }

        }

        public function post_update_plan_info($POST) {//Updates the existing recurring profile plan
            $params = array(
                "merc" => new merc($this->merchantId, $this->regKey),
                "recurProf" => new recurProf()
            );
            if (isset($POST["paymentRadio"])) {//if this is a throrough update or just a cancelation
                $amount = $this->calculateFee($POST,false);
            }
            else {
                $amount = $this->calculateFee($POST,true);
            }

            $billingCycle = 30;// charge every 4 weeks

            $params['recurProf']->updateRecurringProfilePayment($POST["recurringProfileId"], $amount, $POST["startDt"], $billingCycle, $POST["customerId"], $POST["walletId"], $POST["recurProfStat"]);

            try {
                $result = $this->client->__soapCall("UpdtRecurrProf", array("parameters" => $params));
                if( in_array( $result->rspCode,  $this->approvalCodes) ) {
                    return  array("response"=>"success","result"=>$result);
                }
                else {
                    return array("response"=>"error","result"=>$result);
                }
            }
            catch (Exception $e) {
                $result = array("code"=>$e->faultcode,"message"=>$e->getMessage(),"desc"=>$this->client->__getLastResponse(),"request"=>$this->client->__getLastRequest());
                return array("response"=>"exception","result"=>$result);

            }

        }

        public function post_add_new_wallet($POST) {//Add new wallet
            $params = array(
                "merc" => new merc($this->merchantId, $this->regKey),
                "cust" => new cust()
            );

            if($POST["paymentRadio"] == 'credit') {
                $pmtType = 5;
                $params["cust"]->addWallet($POST["customerId"], $POST["paymentRadio"], $POST["ccNumber"], ($POST["expYear"].$POST["expMonth"]), $POST["ccType"], $POST["acctNameCard"]);
            }
            else if($POST["paymentRadio"] == 'checking') {
                $pmtType = 4;
                $params["cust"]->addWallet($POST["customerId"], $POST["paymentRadio"], $POST["routingNumber"], $POST["accountNumber"], $POST["acctType"], $POST["acctNameBank"]);
            }

            $params['merc']->setProdType($pmtType);

            try {
                $result = $this->client->__soapCall("UpdtRecurrProf", array("parameters" => $params));
                if( in_array( $result->rspCode,  $this->approvalCodes) ) {
                    $POST["newWalletId"] = $result->pmtId;//TEMPORARILY HOLD NEW WALLET
                    $this->updateTransExpressWallet($POST);//UPDATE TRAN EXPRESS TABLE
                    $POST["recurProfStat"] = '1';//SET CURRENT PLAN TO INACTIVE
                    $this->post_update_plan_info($POST);//CALL FUNCTION TO UPDATE PLAN AND SET IT TO INACTIVE
                    $POST["walletId"] = $POST["newWalletId"];//RE-ASSIGN NEW WALLET ID TO WALLET ID
                    $this->post_add_new_recurring_profile($POST);//CREATE A NEW RECURRING PROFILE WITH THE NEWLY CREATED WALLET
                    return  array("response"=>"success","result"=>$result);
                }
                else {
                    return array("response"=>"error","result"=>$result);
                }
            }
            catch (Exception $e) {
                $result = array("code"=>$e->faultcode,"message"=>$e->getMessage(),"desc"=>$this->client->__getLastResponse(),"request"=>$this->client->__getLastRequest());
                return array("response"=>"exception","result"=>$result);

            }

        }

        public function post_add_new_recurring_profile($POST) {//Creates a new recurring profile plan
            $params = array(
                "merc" => new merc($this->merchantId, $this->regKey),
                "recurProf" => new recurProf()
            );

            if (isset($POST["paymentRadio"])) {//if this is a throrough update or just a cancelation
                $amount = $this->calculateFee($POST,false);
            }
            else {
                $amount = $this->calculateFee($POST,true);
            }

            $billingCycle = 30;// charge every 4 weeks
            
            if(!isset($POST["startDt"])) {
                $POST["startDt"] = $this->getW3CDate();//first recurring payment in 4 weeks
            }

            $params['recurProf']->addRecurringProfile($amount, $POST["startDt"], $billingCycle, $POST["customerId"], $POST["walletId"]);

            try {
                $result = $this->client->__soapCall("UpdtRecurrProf", array("parameters" => $params));
                if ( $result->rspCode == '00' ) {
                    $POST["recurringProfileId"] = $result->id;
                    $this->updateTransExpressProfile($POST);
                    return  array("response"=>"success","result"=>$result);
                }
            }
            catch (Exception $e) {
                $result = array("code"=>$e->faultcode,"message"=>$e->getMessage(),"desc"=>$this->client->__getLastResponse(),"request"=>$this->client->__getLastRequest());
                return array("response"=>"exception","result"=>$result);

            }

        }

        public function updateTransExpressProfile($POST) {//updates the local transexpress table with newly added profile id
            $recurringProfileId = $POST['recurringProfileId'];
            $customerId = $POST['customerId'];
            $name = $this->security->get_full_name();

            $sql = "UPDATE reference.transexpress
                    SET recurringProfileId = '$recurringProfileId',
                    tstampedit = NOW(),
                    editby = '$name'
                    WHERE customerId = '$customerId'";

            $this->connection->query($sql);

        }

        public function updateTransExpressWallet($POST) {//updates the local transexpress table with newly added wallet id
            $walletId = $POST['newWalletId'];
            $customerId = $POST['customerId'];
            $name = $this->security->get_full_name();

            $sql = "UPDATE reference.transexpress
                    SET walletId = '$walletId',
                    tstampedit = NOW(),
                    editby = '$name'
                    WHERE customerId = '$customerId'";

            $this->connection->query($sql);

        }

        public function getW3CDate() {//Return date in w3c format 30 days from now Pacific Time.
            $date = new DateTime();
            $futureDate = $date->add(new DateInterval("P30D"));
            $futureDate->setTimezone(new DateTimeZone('America/Los_Angeles'));
            $futureDate = $futureDate->format(DateTime::W3C);
            return $futureDate;
        }

        public function calculateFee($POST,$update) {//Return amount in cents and leading 0.
            if (!$update) {
                if($POST["paymentRadio"] == 'credit') {//Determine payment type
                    $ccFee = ( $POST["amount"] * 0.03 );//charge 3.00% to all credit card transactions
                    $ccFee = round($ccFee, 2);
                }
                else if($POST["paymentRadio"] == 'checking') {
                    $ccFee = 0;
                }
                $amount = ( $POST["amount"] + $ccFee )*(100);//convert to cents
                $amount = round($amount, 2);
                $amount = '0' . $amount;//leading 0 required
                return $amount;
            }
            else {
                $amount = ( $POST["amount"] )*(100);//convert to cents
                $amount = round($amount, 2);
                $amount = '0' . $amount;//leading 0 required
                return $amount;
            }
        }

        public function post_send_confirmation_email($POST) {//shoot email to client who just signed up
            $email = $POST["email"];
            $name = $POST['name'];
            $username = $POST["username"];
            $password = $POST["password"];
            $to = $email;
            $subject = "Your HealthTrust Software Account Information";

            $message = "<html><head><title>Message</title></head><body>";
            $message .= "Welcome " . $name . "!<br />";
            $message .= "Your account has been created. <br /> To use the system, go to <br /> <br /><a href='http://www.healthtrustglobal.com'>HealthTrustGlobal.com</a> <br /> <br /> Username:" . $username . " <br />Password:" . $password . " <br /> <br /> For support or help, contact us via email at <a href='mailto:support@healthtrustsoftware.com' target='_top'>support@healthtrustsoftware.com</a>. <br /> <br /> Enjoy!";
            $message .= "</body></html>";

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=utf-8\r\n";
            $headers .= "From: support@healthtrustsoftware.com\r\n";
            $headers .= "Reply-To: support@healthtrustsoftware.com\r\n";
            ini_set('SMTP','10.0.0.6');
            ini_set('sendmail_from','support@healthtrustsoftware.com');
            if(mail($to, $subject, $message, $headers)) {
                return array("response"=>"success");
            }
            else {
                return array("response"=>"error");
            }

        }

        public function post_send_plan_update_email($POST) {//shoot email to client with their updated invoice once number of licenses change
            $email = $POST["email"];
            $name = $POST['name'];
            $username = $POST["username"];
            $password = $POST["password"];
            $to = $email;
            $subject = "Your HealthTrust Software Account Information";

            $message = "<html><head><title>Message</title></head><body>";
            $message .= "Welcome " . $name . "!<br />";
            $message .= "Your account has been created. <br /> To use the system go to <br /> <br /><a href='http://www.healthtrustglobal.com'>HealthTrustGlobal.com</a> <br /> <br /> Username:" . $username . " <br />Password:" . $password . " <br /> <br /> For support or help send e-mail to <a href='mailto:support@healthtrustsoftware.com' target='_top'>support@healthtrustsoftware.com</a>. <br /> <br /> Enjoy!";
            $message .= "</body></html>";

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=utf-8\r\n";
            $headers .= "From: support@healthtrustsoftware.com\r\n";
            $headers .= "Reply-To: support@healthtrustsoftware.com\r\n";
            ini_set('SMTP','10.0.0.6');
            ini_set('sendmail_from','support@healthtrustsoftware.com');
            if(mail($to, $subject, $message, $headers)) {
                return array("response"=>"success");
            }
            else {
                return array("response"=>"error");
            }

        }

        public function post_update_monthly_fee($POST) {
            $customer = $this->get_find_customer_details($POST);
            $state = $customer["result"]->cust->contact->state;
            $currentFee = $customer["result"]->recurProf->recur->amt;
            $currentFee = $currentFee/100;//CONVERT TO WHOLE DOLLARS
            $numberOfUsers = $POST["numberOfLicenses"];
            $hasPayroll = $POST["payrollAddon"] == "B" ? true : false;
            $effectiveDate = $customer["result"]->recurProf->recur->nextProcDt;
            if ($state == "TX") { //IF TEXAS WE HAVE TO TAX
                if ($hasPayroll) {
                    $pricePerUser = 21.65;
                }
                else {
                    $pricePerUser = 18.40;
                }
            }
            else {
                if ($hasPayroll) {
                    $pricePerUser = 20.00;
                }
                else {
                    $pricePerUser = 17.00;
                }
            }
            $netPrice = ($pricePerUser * $numberOfUsers);//this is what the user should currently be paying given their rate and their active users
            //make sure current fee does not exceed current users
            if ( $currentFee == $netPrice ) { //IF THEY ARE CURRENTLY PAYING WHAT THEY ARE SUPPOSSED TO THEN GO AHEAD AND CHARGE FOR ADDING A USER
                $POST["startDt"] = $effectiveDate;
                $POST["recurProfStat"] = 0;
                $POST["amount"] = $currentFee + $pricePerUser;
                return $this->post_update_plan_info($POST);
            }
            elseif( $currentFee < $netPrice ) { //THIS IS A CHECK TO GET CAUGHT UP AS FAR AS THE TOTAL USERS AND THEIR CURRENT PRICE IN CASE THEY ARE PAYING LESS THAN THEY ARE SUPPOSSED TO
                $POST["startDt"] = $effectiveDate;
                $POST["recurProfStat"] = 0;
                $POST["amount"] = $netPrice;
                return $this->post_update_plan_info($POST);
            }
            else {
                return array("response" => "No update needed.");
            }

        }

        public function post_create_client($POST) {
            $ignoreCaps = array("password");

            foreach ($POST as $POST_KEY => $POST_VALUE) {
                if (!in_array($POST_KEY,$ignoreCaps)) {
                    $POST[$POST_KEY] = strtoupper($POST_VALUE);
                }
            }

            $POST['billingPhone'] = preg_replace("/[^0-9]/","",$POST['billingPhone']);
            if (strlen($POST['billingPhone']) === 10) {
                $POST['billingPhone'] = "(" . substr( $POST['billingPhone'] , 0 , 3 ) . ")" . substr( $POST['billingPhone'] , 3 , 3 ) . "-" . substr( $POST['billingPhone'] , -4 );
            }

            $error = "";

            $Database = new Database($this->environment,$this->connection,$this->security);
            $Agent = new Agents($this->environment,$this->connection,$this->security);
            $User = new Users($this->environment,$this->connection,$this->security);
            $Company = new Company($this->environment,$this->connection,$this->security);
            $Provider = new Provider($this->environment,$this->connection,$this->security);

            if (!isset($POST['clientid'])) {

                $return_trans = $this->post_recurring_profile_details($POST);
                if ($return_trans['result']) {
                    $type = "create";
                    $return_client = $Company->post_automated_register_new_client($POST);
                }
                else {
                    $error = "Failed to insert the transaction record. Reason: " . $return_trans['response'];
                }
            }
            else {
                $type = "update";
                $return_client = $Company->get_automated_register_client_info($POST);

                $nameSplit = explode(" ",$return_client['response']['contact']);
                $POST['name'] = $nameSplit[0];
                $POST['lname'] = $nameSplit[1];
                $POST['title'] = "ADMINISTRATOR";
                $POST['email'] = $return_client['response']['email'];
                $POST['billingPhone'] = $return_client['response']['phone1'];
                $POST['addonBox'] = $return_client['response']['clienttype'];
                $POST['agency'] = $return_client['response']['cotype'];
                $POST['licenseNum'] = $return_client['response']['userlicensenum'];
            }

            if (empty($error)) {
                if ($return_client['result']) {
                    $POST['clientid'] = isset($return_client['clientid']) ? $return_client['clientid'] : $return_client['response']['id'];
                    $return_database = $Database->post_automated_register_new_database($POST);
                    if ($return_database['result']) {
                        $POST['qdatabase'] = $return_database['qdatabase'];
                        $return_provider = $Provider->post_automated_register_new_provider($POST);
                        if ($return_provider['result']) {
                            $return_agent = $Agent->post_automated_register_new_agent($POST);
                            if ($return_agent['result']) {
                                $POST['agentid'] = $return_agent['agentid'];
                                $return_user = $User->post_automated_register_new_user($POST);
                                if ($return_user['result']) {
                                    $POST['userid'] = $return_user['userid'];
                                    $return_company = $Company->post_automated_register_new_company($POST);
                                    if ($return_company['result']) {
                                        $return_client = $Company->post_automated_register_activate_client($POST);
                                        $this->post_send_confirmation_email($POST);//send confirmation email
                                        if (!$return_user['result']) {
                                            $error = "Failed to activate the client. Reason: " . $return_client['response'];
                                        }
                                    }
                                    else {
                                        $error = "Failed to update company information. Reason: " . $return_company['response'];
                                    }
                                }
                                else {
                                    $error = "Failed to create the user record. Reason: " . $return_user['userid'];
                                }
                            }
                            else {
                                $error = "Failed to create the agent record. Reason: " . $return_agent['agentid'];
                            }
                        }
                        else {
                            $error = "Failed to create the provider record. Reason: " . $return_provider['provid'];
                        }
                    }
                    else {
                        $error = $return_database['error'];
                    }
                }
                else {
                    $error = "Failed to $type the client record. Reason: " . $return_client['clientid'];
                }
            }

            if (!empty($error)) {
                $Database->post_automated_register_remove_database($POST);
                $User->post_automated_register_remove_user($POST);
                // we don't want to remove the client database reference because that one will hold the transaction express client id...since they have already paid at this point, we don't want to lose that.
            }

            return array("result"=>empty($error),"error"=>$error);
        }
    }
?>