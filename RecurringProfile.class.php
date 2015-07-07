<?
class recurProf {

	public $type;
	public $recur;
    public $recurringProfileId;

    public function addRecurringProfilePayment($amount, $startDate, $billingCycle, $pmtType) {//This is used to create the customer wallet and profile altogether
        $this->type = 0;//Add
        $this->recur = new recur();
        $this->recur->addRecurringPayment($amount, $startDate, $billingCycle, $pmtType);
    }

    public function updateRecurringProfilePayment($recurringProfileId, $amount, $startDate, $billingCycle, $customerId, $walletId, $mode) {
        $this->type = 1;//Update
        $this->recurProfId = $recurringProfileId;
        $this->recur = new recur();
        $this->recur->updateRecurringPayment($amount, $startDate, $billingCycle, $customerId, $walletId, $mode);
    }

    public function addRecurringProfile($amount, $startDate, $billingCycle, $customerId, $walletId) {//This is used to create a recurring profile only.
        $this->type = 0;//Add
        $this->recur = new recur();
        $this->recur->addNewRecurringProfile($amount, $startDate, $billingCycle, $customerId, $walletId);
    }

}
?>