<?php
class phone {

	public $type;
	public $nr;

    public function __construct($number) {
        $this->type = 0;//default to home phone
        $this->nr = $number;
    }

}
?>