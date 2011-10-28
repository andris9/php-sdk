<?php
$_GET = json_decode('{"billing_type":"MO","country":"EE","currency":"EUR","keyword":"FOR TEST","message":"tere tere","message_id":"5a4c47e43def7955a5d375fb19446fd0","operator":"Elisa","price":"0.32","sender":"37251940072","service_id":"c4b756ca6da4a88fa5c61181aa484b08","shortcode":"1311","sig":"9029c81198aacedc47dfc2b2289e5b87","status":"pending","test":"true"}', true);

require_once("../php-sdk/fortumo.php");

header("content-type: text/plain; charset=utf-8");

$fortumo = new Fortumo(array(
    'serviceId' => 'c4b756ca6da4a88fa5c61181aa484b08',
    'secret' => 'b1ec1bfba48bff06485e1291e5748471',
    'allowedIPAddresses' => $_SERVER["REMOTE_ADDR"]
));

if($payment = $fortumo->validateRequest()){
    echo "Payment from {$payment['sender']}\n";
    print_r($payment);
}else{
    echo "Error: " . $fortumo->getError();
}

echo "\n\n";

$countries = $fortumo->getAvailableCountries();

print_r($countries);

echo "\n\n";

print_r($fortumo->getCountryInformation());

echo "\n\n";

$fortumo->setServiceXML("http://api.fortumo.com/api/services/2/c4b756ca6da4a88fa5c61181aa484b08.b17e2534250149da6d7ff605a574ecaa.xml", false);

$countries = $fortumo->getAvailableCountries();

print_r($countries);

echo "\n\n";

foreach($countries as $code=>$name){
    print_r($fortumo->getCountryInformation($code));
}