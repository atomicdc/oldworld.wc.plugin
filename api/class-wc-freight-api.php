<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Freight_API
{
    public $request;

    public $response;

    private $items;

    private $writer;

    public function __construct($data)
    {
        //$xml = $this->build($data)->toXML();
        //$this->request = $this->request($xml);
        $writer = new XMLWriter();
    }

    public function build($data = [])
    {


        //return $this->buildXML($data);
        return $this;
    }

    private function request($payload)
    {


        return $XMLRate;
    }


}