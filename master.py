from flask import Flask, request, jsonify
from flask_cors import CORS  # Import CORS
import requests  # To communicate with slave nodes

app = Flask(__name__)
CORS(app)  # Enable CORS for all routes

# Example slave nodes (assumes they are running on the same machine)
SLAVE_NODES = [
    'http://localhost:5001/predict',
    'http://localhost:5002/predict'
]

def forward_request_to_slave(data):
    # Distribute to slave nodes (e.g., round-robin or other strategy)
    for slave_node in SLAVE_NODES:
        try:
            response = requests.post(slave_node, json=data)
            if response.status_code == 200:
                return response.json()  # Return the first successful response
        except Exception as e:
            print(f"Error connecting to slave node {slave_node}: {e}")
    return {"error": "All slave nodes are unavailable"}

@app.route('/predict', methods=['POST'])
def master_predict():
    data = request.get_json()
    
    # Forward the request to one of the slave nodes
    result = forward_request_to_slave(data)
    
    # Return the result back to the client
    return jsonify(result)

if __name__ == "__main__":
    app.run(debug=True, port=5004)  # Master node listens on port 5000


