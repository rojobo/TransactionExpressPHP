<?php
class card {

    public $pan;
    public $xprDt;
    public $dbtOrCdt;
    public $sec;

    public function __construct($cardNumber) {
        $this->pan = $cardNumber;
    }

    public function setExpirationDate($date) {
        $this->xprDt = $date;
    }

    public function setCardType($type) {
        $this->dbtOrCdt = $type;
    }

    public function setCVV2($cvv) {
        $this->sec = $cvv;
    }
    
}