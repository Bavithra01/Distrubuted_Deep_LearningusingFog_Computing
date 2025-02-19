<?php
// Get system load
$load = sys_getloadavg();
$systemLoad = $load[0]; // 1 minute load average

// Get CPU usage
$cpuUsage = 0;
if (function_exists('shell_exec')) {
    $output = shell_exec('top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk \'{print 100 - $1}\'');
    $cpuUsage = floatval($output);
}

// Get memory usage
$memoryUsage = 0;
if (function_exists('memory_get_usage')) {
    $memoryUsage = memory_get_usage(true) / memory_get_peak_usage(true);
}

// Calculate overall load (you can adjust the weights as needed)
$overallLoad = ($systemLoad * 0.5) + ($cpuUsage * 0.3) + ($memoryUsage * 0.2);

// Normalize to 0-1 range
$normalizedLoad = min(max($overallLoad / 100, 0), 1);

echo $normalizedLoad;
?>

