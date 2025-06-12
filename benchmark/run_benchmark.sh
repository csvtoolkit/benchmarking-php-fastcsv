#!/bin/bash

set -e

echo "=== FastCSV vs Native PHP Benchmark Suite ==="
echo "Starting benchmark at $(date)"

# Create results directory
mkdir -p /results

# Initialize CSV results file
echo "container,implementation,operation,data_size,execution_time_ms,memory_used_bytes,peak_memory_bytes,wall_time_seconds" > /results/benchmark_results.csv

# Test configurations
operations=("read" "write" "both")
data_sizes=("small" "medium" "large")
containers=("fastcsv-bench" "native-bench")

# Function to run a single benchmark
run_benchmark() {
    local container=$1
    local operation=$2
    local data_size=$3
    
    echo ""
    echo "--- Running: $container | $operation | $data_size ---"
    
    # Install dependencies in the container if not already done
    docker exec "$container" bash -c "
        cd /app/shared && 
        if [ ! -d vendor ]; then 
            composer install --no-dev --optimize-autoloader
        fi
    "
    
    # Measure wall time
    start_time=$(date +%s.%N)
    
    # Run the benchmark and capture output
    output=$(docker exec "$container" /usr/bin/time -f "%e,%M,%P" bash -c "
        cd /app/shared && 
        php benchmark.php $operation $data_size 2>&1
    " 2>&1)
    
    end_time=$(date +%s.%N)
    wall_time=$(echo "$end_time - $start_time" | bc)
    
    # Extract timing info from /usr/bin/time (last line)
    timing_line=$(echo "$output" | tail -n 1)
    IFS=',' read elapsed_time max_memory cpu_percent <<< "$timing_line"
    
    # Extract implementation info from PHP output
    implementation="unknown"
    if echo "$output" | grep -q "FastCSV Extension"; then
        implementation="FastCSV"
    elif echo "$output" | grep -q "SplFileObject Fallback"; then
        implementation="SplFileObject"
    fi
    
    # Extract execution time and memory from PHP output
    execution_time=$(echo "$output" | grep "Execution time:" | grep -o '[0-9.]*' | head -1)
    memory_info=$(echo "$output" | grep "Peak memory:" | grep -o '[0-9.]* [A-Z]*')
    
    # Convert memory to bytes (simple conversion for KB/MB/GB)
    memory_bytes="0"
    if [[ $memory_info =~ ([0-9.]+)\ ([A-Z]+) ]]; then
        value="${BASH_REMATCH[1]}"
        unit="${BASH_REMATCH[2]}"
        case $unit in
            "KB") memory_bytes=$(echo "$value * 1024" | bc);;
            "MB") memory_bytes=$(echo "$value * 1024 * 1024" | bc);;
            "GB") memory_bytes=$(echo "$value * 1024 * 1024 * 1024" | bc);;
            *) memory_bytes=$value;;
        esac
    fi
    
    # Memory from /usr/bin/time is in KB, convert to bytes
    max_memory_bytes=$(echo "$max_memory * 1024" | bc)
    
    # Save to CSV
    echo "$container,$implementation,$operation,$data_size,$execution_time,$memory_bytes,$max_memory_bytes,$wall_time" >> /results/benchmark_results.csv
    
    # Print summary
    echo "Implementation: $implementation"
    echo "Execution time: ${execution_time}ms"
    echo "Peak memory: $memory_info"
    echo "Wall time: ${wall_time}s"
    
    # Save detailed output
    echo "$output" > "/results/${container}_${operation}_${data_size}_detailed.log"
    
    # Brief pause between tests
    sleep 2
}

# Run all benchmarks
for container in "${containers[@]}"; do
    echo "Preparing container: $container"
    
    # Wait for container to be ready
    timeout=30
    while ! docker exec "$container" echo "Container ready" >/dev/null 2>&1; do
        echo "Waiting for $container to be ready..."
        sleep 1
        timeout=$((timeout - 1))
        if [ $timeout -eq 0 ]; then
            echo "ERROR: Container $container not ready after 30 seconds"
            exit 1
        fi
    done
    
    for operation in "${operations[@]}"; do
        for data_size in "${data_sizes[@]}"; do
            run_benchmark "$container" "$operation" "$data_size"
        done
    done
done

echo ""
echo "=== Benchmark Complete ==="
echo "Results saved to /results/benchmark_results.csv"
echo ""

# Generate summary report
echo "=== Performance Summary ==="
echo "Results by implementation and operation:"
echo ""

# Print CSV header
echo "Implementation,Operation,Data Size,Avg Time (ms),Memory Efficiency"

# Process results (simple analysis)
while IFS=',' read -r container implementation operation data_size exec_time memory peak_memory wall_time; do
    if [ "$container" != "container" ]; then  # Skip header
        printf "%-15s %-10s %-10s %10s ms %15s\n" "$implementation" "$operation" "$data_size" "$exec_time" "$(echo "scale=2; $memory / 1024 / 1024" | bc)MB"
    fi
done < /results/benchmark_results.csv

echo ""
echo "Detailed logs available in /results/"
echo "Benchmark completed at $(date)" 