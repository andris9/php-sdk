## Usage

    require "php-sdk/fortumo.php";
    
    $fortumo = new Fortumo(array(
        "serviceId" => "YOUR_SERVICE_ID",
        "secret" => "YOUR_SERVICE_SECRET"
    ));
    
    // Validate payment request
    $payment = $fortumo->validateRequest();
    
    if($payment){
        echo "Successful payment from {$payment['sender']}";
    }else{
        echo "Error: " . $fortumo->getError();
    }