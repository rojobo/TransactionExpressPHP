<?
class ach {

	public $bankRtNr;
	public $acctType;
	public $accountNum;

    public function __construct($routingNum) {
        $this->bankRtNr = $routingNum;
    }

    public function setAccountType($type) {
        $this->acctType = $type;
    }

    public function setAccountNumber($accountNum) {
        $this->acctNr = $accountNum;
    }

}
?>