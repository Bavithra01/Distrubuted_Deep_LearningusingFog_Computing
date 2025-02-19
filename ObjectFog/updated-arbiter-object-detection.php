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

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $image_tmp = $_FILES['image']['tmp_name'];

        $start_time = microtime(true);

        $curl = curl_init('http://localhost:5001/detect');
        $cfile = new CURLFile($image_tmp, $_FILES['image']['type'], $_FILES['image']['name']);
        $data = array('image' => $cfile);

        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($curl);

        if ($response === false) {
            throw new Exception("Local processing error: " . curl_error($curl));
        }

        curl_close($curl);

        $detection_data = json_decode($response, true);
        if ($detection_data === null) {
            throw new Exception("Failed to parse local detection response");
        }

        $result['predictions'] = $detection_data['predictions'];
        $result['totalTime'] = (microtime(true) - $start_time) * 1000;  // Time in milliseconds
    } else {
        throw new Exception(isset($_FILES['image']) ? "Upload error: " . $_FILES['image']['error'] : "No image uploaded");
    }
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result);
?>

