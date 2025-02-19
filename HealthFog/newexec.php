<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: *");
header('Content-Type: application/json');

$result = array();

try {
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0){
        $start_time = microtime(true);
        
        $image_tmp = $_FILES['image']['tmp_name'];
        
        // Call Python script for image processing
        $output = shell_exec("python3 retinopathy_model.py $image_tmp 2>&1");
        
        // Extract JSON response from the Python output
        $json_start = strpos($output, '{');
        $json_end = strrpos($output, '}') + 1;

        if ($json_start !== false && $json_end !== false) {
            $json_response = substr($output, $json_start, $json_end - $json_start);
            $prediction_data = json_decode($json_response, true);

            if ($prediction_data === null) {
                throw new Exception("Failed to parse Python script output: " . $output);
            }

            $result['result'] = $prediction_data['result'];
            $result['execution_time'] = $prediction_data['execution_time'];
            
            $end_time = microtime(true);
            $result['total_time'] = ($end_time - $start_time) * 1000; // Convert to milliseconds
        } else {
            throw new Exception("No valid JSON found in Python output: " . $output);
        }
    } else {
        throw new Exception(isset($_FILES['image']) ? "Upload error: " . $_FILES['image']['error'] : "No image uploaded");
    }
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result);
?>

