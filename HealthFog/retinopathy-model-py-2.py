import sys
import time
import json
import os
from tensorflow.keras.models import load_model
from tensorflow.keras.preprocessing import image
import numpy as np

def predict_retinopathy(image_path):
    start_time = time.time()
    
    # Load the trained model
    model = load_model('retinopathy_model.h5')
    
    # Preprocess the image
    img = image.load_img(image_path, target_size=(224, 224))
    img_array = image.img_to_array(img)
    img_array = np.expand_dims(img_array, axis=0)
    img_array /= 255.
    
    # Make prediction
    prediction = model.predict(img_array)
    
    # Interpret the prediction
    classes = ['No DR', 'Mild', 'Moderate', 'Severe', 'Proliferative DR']
    predicted_class = classes[np.argmax(prediction)]
    
    end_time = time.time()
    execution_time = (end_time - start_time) * 1000  # Convert to milliseconds
    
    return {"result": f"Predicted class: {predicted_class}", "execution_time": execution_time}

if __name__ == "__main__":
    image_path = sys.argv[1]
    result = predict_retinopathy(image_path)
    print(json.dumps(result))
