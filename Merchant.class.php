<?php
class merc {

	public $id;
	public $regKey;
	public $inType;
	public $prodType;

    public function __construct($id, $regKey) {
        $this->id = $id;
        $this->regKey = $regKey;
        $this->inType = 1;
    }

    public function setProdType($prodType) {
    	$this->prodType = $prodType;

    }
    
}
?>