<?php

/*
 * xmlToArrayParser code from http://www.php.net/manual/en/ref.xml.php by "Glen at ITIstudios dot ca"
 *
 * Code from PHP.net comments is licensed under Creative Commons Attribution 3.0 Unported (CC BY 3.0)
 * http://creativecommons.org/licenses/by/3.0/legalcode
 *
 * More information - http://www.php.net/license/index.php#doc-lic
 *
 */

/**
 * Convert an xml file to an associative array (including the tag attributes):
 *
 * @param Str $xml file/string.
 */
class xmlToArrayParser {
    /**
     * The array created by the parser which can be assigned to a variable with: $varArr = $domObj->array.
     *
     * @var Array
     */
    public    $array;
    protected $parser;
    protected $pointer;

    /**
     * $domObj = new xmlToArrayParser($xml);
     *
     * @param Str $xml file/string
     */
    public function __construct($xml) {
        $this->pointer =& $this->array;
        $this->parser = xml_parser_create("UTF-8");
        xml_set_object($this->parser, $this);
        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);
        xml_set_element_handler($this->parser, "tag_open", "tag_close");
        xml_set_character_data_handler($this->parser, "cdata");
        xml_parse($this->parser, ltrim($xml));
    }
    
    public function setServiceXML($filename, $onlyApproved = true){
        $this->setServiceXMLString(file_get_contents($filename), $onlyApproved);
    }

    public function setServiceXMLString($xml, $onlyApproved = true){
        $domObj = new xmlToArrayParser($xml);
        $domArr = $domObj->array;
        $this->serviceData = array();

        if(!$domArr["services_api_response"]){
            $this->lastError = "Invalid XML";
            return;
        }

        if($domArr["services_api_response"]["status"]["code"] !== "0"){
            $this->lastError = "Invalid status code";
            return;
        }

        if($this->serviceId && $this->serviceId != $domArr["services_api_response"]["service"]["_"]["id"]){
            $this->lastError = "Invalid service code in XML";
            return;
        }

        $countryList = array();
        foreach($domArr["services_api_response"]["service"]["countries"]["country"] as $country){
            if(!$country || ($onlyApproved && $country["_"]["approved"] !== "true"))continue;
            $countryList[$country["_"]["code"]] = $country;
        };

        $this->serviceData = $countryList;
    }

    public function getAvailableCountries(){

        $countryList = array();
        foreach($this->serviceData as $code => $country){
            $countryList[$code] = $country["_"]["name"];
        }

        asort($countryList);

        return $countryList;
    }

    public function getCountryInformation($country = false){
        if(!$country){
            $keys = array_keys($this->serviceData);
            $country = count($keys) ? $keys[0] : false;
        }

        if(!$country || !$this->serviceData[$country]){
            return false;
        }

        $countryInformation = $this->serviceData[$country]["_"];

        // same settings for all operators
        if($this->serviceData[$country]["prices"]["price"]["_"]["all_operators"] === "true"){
            $countryInformation["all_operators"] = array_merge($this->serviceData[$country]["prices"]["price"]["_"],
                $this->serviceData[$country]["prices"]["price"]["message_profile"]["_"]);
            unset($countryInformation["all_operators"]["all_operators"]);
        }else{
            $countryInformation["all_operators"] = false;
        }

        if($this->serviceData[$country]["prices"]["price"]["_"]){
            $priceData = array($this->serviceData[$country]["prices"]["price"]);
        }else{
            $priceData = $this->serviceData[$country]["prices"]["price"];
        }



        $operators = array();

        if($priceData){
            foreach($priceData as $price){

                $keywordData = $price["_"];

                if($price["message_profile"]["operator"]["_"]){
                    $operatorArray = array($price["message_profile"]["operator"]);
                }else{
                    $operatorArray = $price["message_profile"]["operator"];
                }

                if($operatorArray){
                    foreach($operatorArray as $operator){
                        $operator = array_merge($keywordData, $price["message_profile"]["_"], $operator["_"]);
                        unset($operator["all_operators"]);
                        $operators[] = $operator;
                    }
                }
            }
        }

        $countryInformation["operators"] = $operators;

        $countryInformation["promotional_text"] = $this->serviceData[$country]["promotional_text"];
        unset($countryInformation["promotional_text"]["cdata"]);

        return $countryInformation;
    }

    protected function tag_open($parser, $tag, $attributes) {
        $this->convert_to_array($tag, '_');
        $idx=$this->convert_to_array($tag, 'cdata');

        if(isset($idx)){
            $this->pointer[$tag][$idx] = Array('@idx' => $idx,'@parent' => &$this->pointer);
            $this->pointer =& $this->pointer[$tag][$idx];
        }else{
            $this->pointer[$tag] = Array('@parent' => &$this->pointer);
            $this->pointer =& $this->pointer[$tag];
        }

        if(!empty($attributes)){
            $this->pointer['_'] = $attributes;
        }
    }

    /**
     * Adds the current elements content to the current pointer[cdata] array.
     */
    protected function cdata($parser, $cdata) {
        if(isset($this->pointer['cdata'])){
            $this->pointer['cdata'] .= $cdata;
        }else{
            $this->pointer['cdata'] = $cdata;
        }
    }

    protected function tag_close($parser, $tag) {
        $current = & $this->pointer;

        if(isset($this->pointer['@idx'])){
            unset($current['@idx']);
        }

        $this->pointer = & $this->pointer['@parent'];

        unset($current['@parent']);

        if(isset($current['cdata']) && count($current) == 1){
            $current = $current['cdata'];
        }else if(empty($current['cdata'])){
            unset($current['cdata']);
        }
    }

    /**
     * Converts a single element item into array(element[0]) if a second element of the same name is encountered.
     */
    protected function convert_to_array($tag, $item) {

        if(isset($this->pointer[$tag][$item])) {
            $content = $this->pointer[$tag];
            $this->pointer[$tag] = array((0) => $content);
            $idx = 1;
        }else if (isset($this->pointer[$tag])) {
            $idx = count($this->pointer[$tag]);
            if(!isset($this->pointer[$tag][0])) {
                foreach ($this->pointer[$tag] as $key => $value) {
                        unset($this->pointer[$tag][$key]);
                        $this->pointer[$tag][0][$key] = $value;
                }
            }
        }else{
            $idx = null;
        }

        return $idx;
    }
}