<?
class cust {

	public $type;
	public $contact;
	public $pmt;

    public function setCustomerWallet($fullName, $company, $phoneNumber, $addr, $city, $state, $zipCode, $email, $paymentType, $var1, $var2, $var3) {
        $this->type = 0;//Add
        $this->contact = new contact();
        $this->contact->setContactWallet($fullName, $company, $phoneNumber, $addr, $city, $state, $zipCode, $email);
        $this->pmt = new pmt();
        $this->pmt->setWalletPayment($paymentType, $var1, $var2, $var3);
    }

    public function updateCustomer($customerId, $fullName, $company, $phoneNumber, $addr, $city, $state, $zipCode, $email) {
        $this->type = 1;//Update
        $this->contact = new contact();
        $this->contact->updateContact($customerId, $fullName, $company, $phoneNumber, $addr, $city, $state, $zipCode, $email);
    }

    public function updateWallet($customerId, $walletId, $paymentType, $var1, $var2, $var3, $desc) {
        $this->contact = new contact();
        $this->contact->updateContactWallet($customerId);
        $this->pmt = new pmt();
        $this->pmt->updateWalletPayment($walletId, $paymentType, $var1, $var2, $var3, $desc);
    }

    public function addWallet($customerId, $paymentType, $var1, $var2, $var3, $desc) {
        $this->contact = new contact();
        $this->contact->updateContactWallet($customerId);
        $this->pmt = new pmt();
        $this->pmt->addWalletPayment($paymentType, $var1, $var2, $var3, $desc);
    }


}
?>