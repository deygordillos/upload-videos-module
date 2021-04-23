<?php

namespace App\Estructure\DTO;

/* ELECTRONIC FENCE */
class EfenceDTO {

    /* VARIABLE */
    private $varName;
    private $varValue;

    function __construct() {
        ;
    }

    public function set($name, $value) {
        $this->$name = $value;
    }

    public function get($name) {
        return $this->$name;
    }
}
