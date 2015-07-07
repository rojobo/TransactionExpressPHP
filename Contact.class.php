<?
class contact {

    public $fullName;
    public $coName;
    public $phone;
    public $addrLn1;
    public $city;
    public $state;
    public $zipCode;
    public $ctry;
    public $email;
    public $type;
    public $stat;
    public $note;

    public function setContactWallet($fullName, $company, $phoneNumber, $addr, $city, $state, $zipCode, $email) {
        $this->fullName = $fullName;
        $this->coName = $company;
        $this->phone = new phone($phoneNumber);
        $this->addrLn1 = $addr;
        $this->city = $city;
        $this->state = $state;
        $this->zipCode = $zipCode;
        $this->ctry = "US";
        $this->email = $email;
        $this->type = 1;//Recurring Default
        $this->stat = 1;//Active Default
        $this->note = "HTS Lite Customer";
    }

    public function updateContact($customerId, $fullName, $company, $phoneNumber, $addr, $city, $state, $zipCode, $email) {
        $this->id = $customerId;
        $this->fullName = $fullName;
        $this->coName = $company;
        $this->phone = new phone($phoneNumber);
        $this->addrLn1 = $addr;
        $this->city = $city;
        $this->state = $state;
        $this->zipCode = $zipCode;
        $this->ctry = "US";
        $this->email = $email;
        $this->type = 1;//Recurring Default
        $this->stat = 1;//Active Default
        $this->note = "Updated Info HTS Lite Customer";

    }

    public function updateContactWallet($customerId) {
        $this->id = $customerId;

    }
    
}
?>