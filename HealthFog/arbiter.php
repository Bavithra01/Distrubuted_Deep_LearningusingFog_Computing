<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    $toMaster = !(preg_replace('/\s+/', '', $choiceArray[0]) == "DisableMaster");
    $toAneka = !(preg_replace('/\s+/', '', $choiceArray[1]) == "DisableAneka");

    $ips = array();
    while (($line = fgets($file)) !== false) {
        array_push($ips, trim($line));
    }

    // Initialize loads array to store loads of workers
    $loads = array();
    foreach ($ips as $ip) {
        $dataFromExternalServer = @file_get_contents("http://".$ip."/HealthFog/load.php");
        $my_var = ($dataFromExternalServer !== FALSE) ? (float)trim($dataFromExternalServer) : 100.0;
        array_push($loads, $my_var);
        if ($my_var <= 0.8) {
            $toMaster = false;
            $toAneka = false;
        }
    }

    if ($toMaster && $toAneka) {
        $toAneka = false;
    }

    if (count($loads) == 0) {
        $toMaster = true;    
    }    

    if (!$toMaster && !$toAneka) {
        // Work given to worker with least load
        $min = min($loads);
        $minindex = array_search($min, $loads);
        $ipworker = trim($ips[$minindex]);
        $result['workerIP'] = $ipworker;
    } elseif ($toAneka) {
        // Work done to Aneka
        $file = fopen("cloud.txt", "r");
        $line = fgets($file);
        $ipworker = trim($line);
        $result['workerIP'] = "cloud";
    } else {
        // Work done by master
        $result['workerIP'] = $localIP;
    }

    // Handle image upload and processing
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $start_time = microtime(true);
        $image_tmp = $_FILES['image']['tmp_name'];

        // Arbiter processing start time
        $arbiter_start_time = microtime(true);

        if ($result['workerIP'] == $localIP) {
            // Process locally
            $output = shell_exec("python3 retinopathy_model.py $image_tmp 2>&1");
            $json_start = strpos($output, '{');
            $json_end = strrpos($output, '}') + 1;

            if ($json_start !== false && $json_end !== false) {
                $json_response = substr($output, $json_start, $json_end - $json_start);
                $prediction_data = json_decode($json_response, true);

                if ($prediction_data === null) {
                    throw new Exception("Failed to parse Python script output: " . $output);
                }
                $result['prediction'] = $prediction_data['result'];
                $result['executionTime'] = $prediction_data['execution_time'];
            } else {
                throw new Exception("No valid JSON found in Python output: " . $output);
            }
        } else {
            // Send to worker node or cloud
            $ch = curl_init("http://". $result['workerIP'] ."/HealthFog/exec.php");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, array('image' => new CURLFile($image_tmp)));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            
            if ($response === false) {
                throw new Exception("cURL error: " . curl_error($ch));
            }

            curl_close($ch);
            $prediction_data = json_decode($response, true);
            if ($prediction_data === null) {
                throw new Exception("Failed to parse worker response: " . $response);
            }
            $result['prediction'] = $prediction_data['result'];
            $result['executionTime'] = $prediction_data['execution_time'];
        }

        // Arbiter processing end time and calculation
        $arbiter_time = (microtime(true) - $arbiter_start_time) * 1000; // Arbiter time in ms
        $result['arbiter_time'] = $arbiter_time;

        // Total time calculation
        $end_time = microtime(true);
        $result['totalTime'] = ($end_time - $start_time) * 1000; // Convert to milliseconds

        // Calculate jitter as the difference between totalTime and execution time
        $result['jitter'] = abs($result['totalTime'] - $result['executionTime']);
    } else {
        throw new Exception(isset($_FILES['image']) ? "Upload error: " . $_FILES['image']['error'] : "No image uploaded");
    }
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result);
?>

