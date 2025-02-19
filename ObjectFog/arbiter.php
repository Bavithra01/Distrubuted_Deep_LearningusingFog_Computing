<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Origin: *"); // Allows requests from any origin
header("Access-Control-Allow-Methods: POST, GET, OPTIONS"); // Allowed methods
header("Access-Control-Allow-Headers: Content-Type, Authorization");

session_start();

// Parse config.txt for IPs 
$file = fopen("config.txt", "r");
$line = fgets($file);
$choiceArray = explode(" ", $line);
$localIP = $_SERVER['SERVER_ADDR'];
		
// Initialize choices
$toMaster = true;	
if(preg_replace('/\s+/', '', $choiceArray[0]) == "DisableMaster"){
	$toMaster = false;	
}
$toAneka = true;	
if(preg_replace('/\s+/', '', $choiceArray[1]) == "DisableAneka"){
	$toAneka = false;	
}

$ips = array();
while(($line = fgets($file)) !== false){
  array_push($ips, $line);
}
	
// Initialize loads array to store loads of workers
$loads = array();
// For each IP, get load from load.php
foreach($ips as $ip){
	$ip = preg_replace('/\s+/', '', $ip);
	$dataFromExternalServer = @file_get_contents("http://".$ip."/ObjectFog/load.php");
	if($dataFromExternalServer != FALSE){
		$dataFromExternalServer = preg_replace('/\s+/', '', $dataFromExternalServer);	
		$my_var = 0.0 + $dataFromExternalServer;
	} else{
		$my_var = 100;
	}
	array_push($loads, $my_var);	
	// If any load < 80% then toMaster and toAneka = false
  	if($my_var <= 0.8){
  		$toMaster = false;
  		$toAneka = false;
  	}
}
	
if($toMaster && $toAneka){
	$toAneka = false;
}

if(sizeof($loads) == 0){
	$toMaster = true;	
}	
	
$result = array();

if(!$toMaster && !$toAneka){
	// Work given to worker with least load
	$min = 100;
	$minindex = 0;
	foreach($loads as $index => $load){
		if ($min > $load){
			$min = $load;
			$minindex = $index;
		}		
	}
	$ipworker = preg_replace('/\s+/', '', $ips[$minindex]);
	$result['workerIP'] = $ipworker;
}
elseif($toAneka) {
	// Work done to Aneka
	$file = fopen("cloud.txt", "r");
	$line = fgets($file);
	$ipworker = preg_replace('/\s+/', '', $line);
	$result['workerIP'] = "cloud";
}
else{
	// Work done by master
	$result['workerIP'] = $localIP;
}

// Handle image upload
if(isset($_FILES['image']) && $_FILES['image']['error'] == 0){
    $start_time = microtime(true);
    
    $image_tmp = $_FILES['image']['tmp_name'];
    
    if($result['workerIP'] == $localIP) {
        // Process locally
        $upload_dir = "./uploads/";
        $image_name = $_FILES['image']['name'];
        $upload_path = $upload_dir . $image_name;
        move_uploaded_file($image_tmp, $upload_path);
        
        // Execute the Python script
        $output = shell_exec("python3 retinopathy_model.py $upload_path");
        $prediction_data = json_decode($output, true);
        
        unlink($upload_path);
    } else {
        // Send to worker node or cloud
        $ch = curl_init("http://" . $result['workerIP'] . ":5000/detect");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array('image' => new CURLFile($image_tmp)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $prediction_data = json_decode($response, true);
    }
    
    $result['prediction'] = $prediction_data['predictions'];
     

    
    $end_time = microtime(true);
    $result['totalTime'] = ($end_time - $start_time) * 1000; // Convert to milliseconds
} else {
    $result['prediction'] = "Error: No image uploaded";
    $result['executionTime'] = 0;
    $result['totalTime'] = 0;
}
echo json_encode($result);
?>
