<?
class pmt {

    public $type;
    public $card;
    public $ach;
    public $status;
    public $ordNr;
    public $desc;
    public $indCode;
    //var1 = card # or routing #
    //var2 = exp date or checking #
    //var3 = type (debit/credit or checking/savings)
    public function setWalletPayment($paymentType, $var1, $var2, $var3) {
        $this->type = 0;//Add
        if($paymentType == 'credit') {
            $this->card = new card($var1);
            $this->card->setExpirationDate($var2);
            $this->card->setCardType($var3);
        }
        else if($paymentType == 'checking') {
            $this->ach = new ach($var1);
            $this->ach->setAccountNumber($var2);
            $this->ach->setAccountType($var3);
        }
        $this->status = 1;//Active by default
        $this->setWalletDescription("DEFAULT");//DEFAULT since this is what they have signed up with
    }

    public function updateWalletPayment($walletId, $paymentType, $var1, $var2, $var3, $desc) {
        $this->id = $walletId;
        $this->type = 1;//Update
        if($paymentType == 'credit') {
            $this->card = new card($var1);
            $this->card->setExpirationDate($var2);
            $this->card->setCardType($var3);
            $this->indCode = 2;//E-commerce cards sales only
        }
        else if($paymentType == 'checking') {
            $this->ach = new ach($var1);
            $this->ach->setAccountNumber($var2);
            $this->ach->setAccountType($var3);
        }
        $this->status = 1;//Active by default
        $this->setWalletDescription($desc);
    }

    public function addWalletPayment($paymentType, $var1, $var2, $var3, $desc) {
        $this->type = 0;//Add
        if($paymentType == 'credit') {
            $this->card = new card($var1);
            $this->card->setExpirationDate($var2);
            $this->card->setCardType($var3);
            $this->indCode = 2;//E-commerce cards sales only
        }
        else if($paymentType == 'checking') {
            $this->ach = new ach($var1);
            $this->ach->setAccountNumber($var2);
            $this->ach->setAccountType($var3);
        }
        $this->status = 1;//Active by default
        $this->setWalletDescription($desc);
    }

    public function setOrderNumber($number) {
        $this->ordNr = $number;
    }

    public function setWalletDescription($desc) {
        $this->desc = $desc;
    }
    
}
?>