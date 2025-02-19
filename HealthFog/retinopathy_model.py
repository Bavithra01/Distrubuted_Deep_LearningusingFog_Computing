import sys
import time
import json
import os
from tensorflow.keras.models import load_model
from tensorflow.keras.preprocessing import image
import numpy as np

# Suppress TensorFlow warnings
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '2'

def predict_retinopathy(image_path):
    start_time = time.time()
    
    # Load the trained model
    model = load_model('retinopathy_model.h5')
    
    # Preprocess the image (without normalization)
    img = image.load_img(image_path, target_size=(224, 224))
    img_array = image.img_to_array(img)
    img_array = np.expand_dims(img_array, axis=0)
    
    # Make prediction
    prediction = model.predict(img_array)
    
    # Define classes
    classes = ["Mild", "Moderate","No_DR", "Severe", "Proliferate_DR"]
    
    # Get the predicted class
    predicted_class = classes[np.argmax(prediction)]
    
    end_time = time.time()
    execution_time = (end_time - start_time) * 1000  # Convert to milliseconds
    
    return {"result": f"Predicted class: {predicted_class}", "execution_time": execution_time}

if __name__ == "__main__":
    image_path = sys.argv[1]
    result = predict_retinopathy(image_path)
    print(json.dumps(result, indent=4))

