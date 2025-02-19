document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('demoForm');
    const result = document.getElementById('result');
    const chartContainer = document.getElementById('chartContainer');
    let metricsChart;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const serverIP = document.getElementById('serverIP').value;
        const imageFile = document.getElementById('imageUpload').files[0];

        if (!serverIP || !imageFile) {
            result.innerHTML = '<p class="error">Please fill in all fields.</p>';
            return;
        }

        const formData = new FormData();
        formData.append('image', imageFile);

        result.innerHTML = '<p>Analyzing image...</p>';
        chartContainer.style.display = 'none';

        try {
            const response = await fetch(`http://${serverIP}/HealthFog/arbiter.php`, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();
	    console.log(data)

            if (data.error) {
                result.innerHTML = `<p class="error">Error: ${data.error}</p>`;
            } else {
                result.innerHTML = `
                    <h3>Analysis Results</h3>
                    <p><strong>Prediction:</strong> ${data.prediction}</p>
                    <p><strong>Execution Time:</strong> ${data.executionTime || 'N/A'} ms</p>
                    <p><strong>Arbiter Time:</strong> ${data.arbiter_time || 'N/A'} ms</p>
                    <p><strong>Latency:</strong> ${data.totalTime || 'N/A'} ms</p>
                    <p><strong>Jitter:</strong> ${data.jitter || 'N/A'} ms</p>
                    <p><strong>Response Time:</strong> ${(parseFloat(data.totalTime) + parseFloat(data.arbiter_time)).toFixed(2) || 'N/A'} ms</p>
                    <p><strong>Worker IP:</strong> ${data.workerIP || 'N/A'}</p>
                `;

                updateChart(data);
            }
        } catch (error) {
            console.error('Error:', error);
            result.innerHTML = '<p class="error">An error occurred while processing your request. Please try again.</p>';
        }
    });

    function updateChart(data) {
        const ctx = document.getElementById('metricsChart').getContext('2d');
        
        if (metricsChart) {
            metricsChart.destroy();
        }

        const responseTime = (parseFloat(data.totalTime) + parseFloat(data.arbiter_time)).toFixed(2);

        metricsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Execution Time', 'Arbiter Time', 'Latency', 'Jitter', 'Response Time'],
                datasets: [{
                    label: 'Time (ms)',
                    data: [
                        data.executionTime || 0,
                        data.arbiter_time || 0,
                        data.totalTime || 0,
                        data.jitter || 0,
                        responseTime || 0
                    ],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Time (ms)'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Performance Metrics'
                    },
                    legend: {
                        display: false
                    }
                }
            }
        });

        chartContainer.style.display = 'block';
    }

    // Smooth scrolling for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });

    // Animate features on scroll
    const featureCards = document.querySelectorAll('.feature-card');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate');
            }
        });
    }, { threshold: 0.5 });

    featureCards.forEach(card => {
        observer.observe(card);
    });
});
