<?php

require_once("xml2array.php");

/**
* Provides needed functions to validate payment requests
*
* @author Andris Reinman <andris.reinman@gmail.com>
*/

class Fortumo{
    
    /**
     * Version
     */
    const VERSION = "0.1.0";
    
    /**
     * Service secret for validating signatures
     * 
     * @var string
     */    
    protected $secret;
    
    /**
     * Service ID
     * 
     * @var string
     */
    protected $serviceId;
    
    /**
     * List of allowed IP addresses for making payment requests 
     */
    protected $allowedIPAddresses = array(
        '81.20.151.38',
        '81.20.148.122',
        '79.125.125.1',
        '209.20.83.207');

    /**
     * Service data parsed from the XML settings file
     */
    protected $serviceData = array();

    /**
     * Error string for the last invalid operation
     * 
     * @var string
     */
    protected $lastError;
    
    /**
     * Constructor for a Fortumo service
     * 
     * Configuration:
     * - serviceId: the service ID
     * - secret: the service secret
     * - allowedIPAddresses: list of IP addresses allowed to make payment requests
     * 
     * @param array $settings The service configuration
     */
    public function __construct($settings){
        $settings = (array)$settings;
        
        if(!empty($settings["serviceId"])){
            $this->serviceId = trim($settings["serviceId"]);
        }
        
        if(!empty($settings["secret"])){
            $this->secret = trim($settings["secret"]);
        }
        
        if(!empty($settings["allowedIPAddresses"])){
            $this->allowedIPAddresses = (array)$settings["allowedIPAddresses"];
        }
    }
    
    /**
     * Validates a payment request
     * 
     * @param array $arrayParams Request params to be verified, defaults to $_GET
     * @return array the request array 
     */
    public function validateRequest($arrayParams = false){
        
        if(!$arrayParams){
            $arrayParams = $_GET;
        }
        
        if(empty($this->secret)){
            $this->lastError = "Secret not set";
            return false;
        }

        if(empty($arrayParams["sig"]) || empty($arrayParams["service_id"])){
            $this->lastError = "Empty request";
            return false;
        }

        if(!in_array($_SERVER['REMOTE_ADDR'], $this->allowedIPAddresses)){
            $this->lastError = "Unknown IP";
            return false;
        }

        if(!empty($this->serviceId) && $this->serviceId != $arrayParams["service_id"]){
            $this->lastError = "Service ID mismatch";
            return false;
        }

        if(!self::checkSignature($arrayParams)){
            $this->lastError = "Signature mismatch";
            return false;
        }

        return $this->buildResponse($arrayParams);
    }
    
    /**
     * Returns the error message for the last invalid operation
     * 
     * @return string the error message
     */
    public function getError(){
        return $this->lastError;
    }

    /**
     * Load service settings from a service XML file
     * 
     * @param string $filename the filename (or URL) for the XML settings
     * @param boolean $onlyApproved should only approved countries to be included, defaults to true
     */
    public function setServiceXML($filename, $onlyApproved = true){
        $this->setServiceXMLString(file_get_contents($filename), $onlyApproved);
    }

    /**
     * Load service settings from a service XML string
     * 
     * @param string $xml the XML file as a string
     * @param boolean $onlyApproved should only approved countries to be included, defaults to true
     */
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

    /**
     * Generates a list of available countries, return data is in the
     * form of array("CODE"=>"Name") where CODE is a 2 letter country code
     * and Name is the name of the country. List is sorted by country names
     * 
     * @return array List of the available countries
     */
    public function getAvailableCountries(){

        $countryList = array();
        foreach($this->serviceData as $code => $country){
            $countryList[$code] = $country["_"]["name"];
        }

        asort($countryList);

        return $countryList;
    }

    /**
     * Returns a hash about available operators, prices etc. for a specified
     * country. If country is not set, first available will be used.
     * 
     * @param string $country 2 letter country code
     * @return array Information about a country
     */
    public function getCountryInformation($country = false){
        if(!$country){
            $keys = array_keys($this->serviceData);
            $country = count($keys) ? $keys[0] : false;
        }else{
            $country = trim(strtoupper($country));
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

    /**
     * Builds a response object to be returned on successful request validation
     * 
     * @return array Response object
     */
    protected function buildResponse($arrayParams) {
        unset($arrayParams["sig"]);
        return $arrayParams;
    }
    
    /**
     * Checks if the signature matches
     * 
     * @param array List of params, that will be used to generate a signature
     * @return boolean Signature matches or not
     */
    protected function checkSignature($arrayParams) {
        ksort($arrayParams);

        $str = '';
        foreach ($arrayParams as $key => $value) {
            if($key != 'sig') {
                $str .= "$key=$value";
            }
        }

        $str .= $this->secret;

        $signature = md5($str);

        return $arrayParams['sig'] == $signature;
    }
    
}