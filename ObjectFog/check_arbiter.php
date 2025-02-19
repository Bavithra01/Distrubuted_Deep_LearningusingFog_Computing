<?php
ini_set('display_errors', 0);  // Suppress errors to avoid breaking JSON responses
ini_set('display_startup_errors', 0);
error_reporting(0);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: *");
header('Content-Type: application/json');

session_start();

$result = array();

try {
    // Handle GET request for metrics
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $metricsData = array(
            'detection_history' => array_map(function($month) {
                return array(
                    'month' => $month,
                    'detections' => rand(100, 1000)
                );
            }, ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']),
            'total_images_processed' => rand(500, 1000),
            'average_detection_time' => rand(100, 500) / 100,
            'average_objects_per_image' => rand(1, 5)
        );
        echo json_encode($metricsData);
        exit();
    }

    // Initialize config and load management
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

    // Load management
    $loads = array();
    foreach ($ips as $ip) {
        $dataFromExternalServer = @file_get_contents("http://".$ip."/ObjectFog/load.php");
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
            // Process locally for classification
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
            // Send to worker node or cloud for detection
            $curl = curl_init("http://". $result['workerIP'] .":5000/detect");
            $cfile = new CURLFile($image_tmp, $_FILES['image']['type'], $_FILES['image']['name']);
           $data = array('image' => $cfile);

           curl_setopt($curl, CURLOPT_POST, true);
           curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
           curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($curl);

            
            if ($response === false) {
                throw new Exception("cURL error: " . curl_error($ch));
            }

            curl_close($curl);
            $detection_data = json_decode($response, true);
            if ($detection_data === null) {
                throw new Exception("Failed to parse worker response: " . $response);
            }
	    $result['predictions'] = $detection_data['predictions'];
	    $result["totalTime"]=(microtime(true)-$start_time)*1000
           
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


