from flask import Flask, request, jsonify
from flask_cors import CORS
from ultralytics import YOLO
import cv2
import numpy as np
from PIL import Image
import io

app = Flask(__name__)
CORS(app)  # Enables CORS for all routes

# Load your trained YOLO model
model = YOLO('best.pt')

@app.route('/detect', methods=['POST'])
def detect_objects():
    try:
        # Get image file from the request
        file = request.files['image']
        
        # Convert to PIL Image
        pil_image = Image.open(io.BytesIO(file.read()))
        
        # Convert PIL image to OpenCV format
        cv_image = cv2.cvtColor(np.array(pil_image), cv2.COLOR_RGB2BGR)
        
        # Run inference
        results = model(cv_image)
        
        # Process results
        predictions = []
        for r in results:
            boxes = r.boxes
            for box in boxes:
                x1, y1, x2, y2 = box.xyxy[0].tolist()
                confidence = float(box.conf[0])
                class_id = int(box.cls[0])
                class_name = model.names[class_id]
                
                predictions.append({
                    "class": class_name,
                    "confidence": confidence,
                    "x": float((x1 + x2) / 2),
                    "y": float((y1 + y2) / 2),
                    "width": float(x2 - x1),
                    "height": float(y2 - y1)
                })
        
        return jsonify({"predictions": predictions})
    
    except Exception as e:
        return jsonify({"error": str(e)}), 500

if __name__ == '__main__':
    app.run(host = "172.20.14.41", port = 5000 ,debug=True)

