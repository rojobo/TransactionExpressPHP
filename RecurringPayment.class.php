<?
class recur {

	public $recurProfStat;
	public $dbtOrCdt;
	public $amt;
	public $nrOfPmt;
	public $startDt;
	public $blngCyc;
	public $indCode;
    public $custId;
    public $pmtId;

    public function addRecurringPayment($amount, $startDate, $billingCycle, $pmtType) {
        $this->recurProfStat = 1;//Active by Default
        $this->dbtOrCdt = 0;//Recurring debit transaction by Default
        $this->amt = $amount;
        //$this->nrOfPmt = $numPayments; //not set since this will be indifinetily
        $this->startDt = $startDate;
        $this->blngCyc = $billingCycle;
        if($pmtType == 5) {//This is the Industry Code that overrides the merchant profile informationfor this particular recurring profile’s transactions. Applies to credit cardrecurring profiles only.
        	$this->indCode = 0;//eCOMMERCE
        }
    }

    public function updateRecurringPayment($amount, $startDate, $billingCycle, $customerId, $walletId, $mode) {
        if( $mode == '1') {
            $this->recurProfStat = 0;//1 for Active by Default 0 for Inactive
        }
        elseif( $mode == '0' ) {
            $this->recurProfStat = 1;//1 for Active by Default 0 for Inactive
        }
        $this->dbtOrCdt = 0;//Recurring debit transaction by Default
        $this->amt = $amount;
        //$this->nrOfPmt = $numPayments; //not set since this will be indifinetily
        $this->startDt = $startDate;
        $this->blngCyc = $billingCycle;
        $this->custId = $customerId;
        $this->pmtId = $walletId;
    }

    public function addNewRecurringProfile($amount, $startDate, $billingCycle, $customerId, $walletId) {
        $this->recurProfStat = 1;//Active by Default
        $this->dbtOrCdt = 0;//Recurring debit transaction by Default
        $this->amt = $amount;
        //$this->nrOfPmt = $numPayments; //not set since this will be indifinetily
        $this->startDt = $startDate;
        $this->blngCyc = $billingCycle;
        $this->custId = $customerId;
        $this->pmtId = $walletId;
    }
    
}
?>