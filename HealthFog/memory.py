import psutil

# Get the total, available, and used memory in the system
memory_info = psutil.virtual_memory()

# Extract total memory, available memory, and used memory
total_memory = memory_info.total / (1024 ** 2)  # Convert bytes to MB
available_memory = memory_info.available / (1024 ** 2)  # Convert bytes to MB
used_memory = memory_info.used / (1024 ** 2)  # Convert bytes to MB

print(total_memory, available_memory, used_memory)

