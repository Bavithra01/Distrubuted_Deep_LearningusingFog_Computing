document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();

    var formData = new FormData();
    var fileInput = document.getElementById('imageUpload');
    formData.append('image', fileInput.files[0]);

    var masterIP = document.getElementById('masterIP').value.trim(); // Get and trim the IP
    var startTime = performance.now(); // To calculate response time

    // Ensure the masterIP is correctly encoded
    fetch(`http://${encodeURIComponent(masterIP)}/HealthFog/arbiter.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        var responseTime = performance.now() - startTime; // Calculate response time

        // Display raw response
        document.getElementById('rawResponse').innerHTML = `<h3>Raw Response:</h3><pre>${text}</pre>`;

        try {
            const data = JSON.parse(text);
            console.log('Server response:', data);

            if (data.error) {
                document.getElementById('result').innerHTML = 'Error: ' + data.error;
            } else {
                document.getElementById('result').innerHTML = 'Prediction: ' + data.prediction;
                document.getElementById('executionTime').innerHTML = 'Execution Time: ' + (data.executionTime || 'Not available') + ' ms';
                document.getElementById('totalTime').innerHTML = 'Total Time: ' + (data.totalTime || 'Not available') + ' ms';
                document.getElementById('arbiterTime').innerHTML = 'Arbiter Time: ' + (data.arbiter_time || 'Not available') + ' ms';
                document.getElementById('responseTime').innerHTML = 'Response Time: ' + responseTime.toFixed(2) + ' ms';

                // Calculate jitter (difference between total time and response time)
                const jitter = Math.abs(data.totalTime - responseTime).toFixed(2);
                document.getElementById('jitter').innerHTML = 'Jitter: ' + jitter + ' ms';

                document.getElementById('workSentTo').innerHTML = 'Work sent to: ' + (data.workerIP || 'Not available');

                // Store metrics for the graph
                window.metricsData = {
                    latency: responseTime,
                    jitter: parseFloat(jitter),
                    arbiterTime: data.arbiter_time || 0,
                    executionTime: data.executionTime || 0,
                    totalTime: data.totalTime || 0
                };
            }
        } catch (e) {
            document.getElementById('result').innerHTML = 'An error occurred while processing the response. Please try again.';
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        document.getElementById('result').innerHTML = 'A network or server error occurred. Please try again.';
    });
});

// Show Graph Button Event Listener
document.getElementById('showGraphButton').addEventListener('click', function() {
    if (window.metricsData) {
        const ctx = document.getElementById('metricsChart').getContext('2d');

        // Show the canvas
        document.getElementById('metricsChart').style.display = 'block';

        // Destroy previous chart if it exists
        if (window.barChart) {
            window.barChart.destroy();
        }

        // Create a new bar chart
        window.barChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Latency', 'Jitter', 'Arbiter Time', 'Execution Time', 'Total Time'],
                datasets: [{
                    label: 'Time (ms)',
                    data: [
                        window.metricsData.latency,
                        window.metricsData.jitter,
                        window.metricsData.arbiterTime,
                        window.metricsData.executionTime,
                        window.metricsData.totalTime
                    ],
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(153, 102, 255, 0.2)',
                    ],
                    borderColor: [
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(153, 102, 255, 1)',
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    } else {
        alert('No metrics data available to display. Please submit an image first.');
    }
});

