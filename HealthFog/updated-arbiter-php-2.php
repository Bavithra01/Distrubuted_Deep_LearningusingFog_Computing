<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: *");
header('Content-Type: application/json');

session_start();

$result = array();

try {
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
        $dataFromExternalServer = @file_get_contents("http://".$ip."/HealthFog/load.php");
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

    // Handle image upload and processing
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0){
        $start_time = microtime(true);
        
        $image_tmp = $_FILES['image']['tmp_name'];
        
        if($result['workerIP'] == $localIP) {
            // Process locally
            $output = shell_exec("python3 retinopathy_model.py $image_tmp 2>&1");
            $prediction_data = json_decode($output, true);
            
            if ($prediction_data === null) {
                throw new Exception("Failed to parse Python script output: " . $output);
            }
            $result['prediction'] = $prediction_data['result'];
            $result['executionTime'] = $prediction_data['execution_time'];
        } else {
            // Send to worker node or cloud
            $ch = curl_init("http://" . $result['workerIP'] . "/HealthFog/exec.php");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, array('image' => new CURLFile($image_tmp)));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            if ($response === false) {
                throw new Exception("cURL error: " . curl_error($ch));
            }
            $prediction_data = json_decode($response, true);
            if ($prediction_data === null) {
                throw new Exception("Failed to parse worker response: " . $response);
            }
            $result['prediction'] = $prediction_data['result'];
            $result['executionTime'] = $prediction_data['execution_time'];
            curl_close($ch);
        }
        
        $end_time = microtime(true);
        $result['totalTime'] = ($end_time - $start_time) * 1000; // Convert to milliseconds
    } else {
        throw new Exception(isset($_FILES['image']) ? "Upload error: " . $_FILES['image']['error'] : "No image uploaded");
    }
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result);
?>
