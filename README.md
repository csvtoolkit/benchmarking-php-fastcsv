# CSV Performance Benchmark Suite

This directory contains a comprehensive benchmarking system for comparing FastCSV extension performance against native SplFileObject implementation, designed to provide accurate and credible performance measurements.

## 🎯 **What This Demonstrates**

- **FastCSV Extension**: 3.6x to 4.8x faster performance for read operations
- **Combined Operations**: 1.6x to 2.9x faster for read/write operations
- **Memory Efficiency**: Both implementations use constant memory (streaming)
- **Intelligent Fallback**: Seamless performance optimization in csv-helper package

## 📊 **Latest Benchmark Results** (June 27, 2025)

### Performance Comparison

| Operation | Data Size | FastCSV | SplFileObject | Speed Improvement |
|-----------|-----------|---------|---------------|-------------------|
| **Read**  | Small     | 3.67ms  | 15.03ms       | **4.1x faster**   |
|           | Medium    | 176ms   | 640ms         | **3.6x faster**   |
|           | Large     | 1,987ms | 9,469ms       | **4.8x faster**   |
| **Both**  | Small     | 22.8ms  | 35.5ms        | **1.6x faster**   |
|           | Medium    | 591ms   | 1,469ms       | **2.5x faster**   |
|           | Large     | 7,089ms | 20,513ms      | **2.9x faster**   |

### Throughput Achievements
- **FastCSV Read**: 272K-568K records/second
- **SplFileObject Read**: 67K-156K records/second
- **FastCSV Combined**: 88K-339K records/second
- **SplFileObject Combined**: 56K-136K records/second

### Key Findings
- ✅ **Read Operations**: FastCSV shows 3.6x to 4.8x performance improvement
- ✅ **Scalability**: Performance advantage increases with data size
- ✅ **Memory Efficiency**: Constant ~2MB peak memory usage regardless of file size
- ✅ **Consistency**: FastCSV shows lower standard deviation, indicating more predictable performance

## 📁 **Repository Structure**

```
bench/
├── .gitignore              # Excludes generated results and data
├── README.md               # This file
├── Makefile               # Automated benchmark commands
├── docker-compose.yml     # Container orchestration
├── shared/                # Shared benchmark code
│   ├── benchmark.php      # Main benchmark script
│   ├── prepare_test_data.py # Test data generation
│   └── composer.json      # PHP dependencies
├── results/               # Generated benchmark results (Git ignored)
│   └── .gitkeep          # Preserves directory structure
├── data/                  # Generated test data (Git ignored)
│   └── .gitkeep          # Preserves directory structure
├── app-fastcsv/          # FastCSV container config
├── app-native/           # Native SplFileObject container config
└── benchmark/            # Orchestrator container config
```

**Note**: The `results/` and `data/` directories are excluded from Git via `.gitignore` since they contain generated files that can be recreated.

## Architecture

The benchmark suite uses a multi-container Docker setup:

- **app-fastcsv**: PHP 8.4 container with FastCSV extension installed
- **app-native**: PHP 8.4 container without FastCSV (uses SplFileObject fallback)
- **benchmark**: Orchestrator container with Python and Docker CLI for running tests
- **Shared volumes**: All containers share the same codebase and data directories

## Key Features

### 🎯 **Credible Benchmarking**
- **Separate data preparation**: Test CSV files are pre-generated using Python
- **No data generation during benchmarks**: Ensures pure performance measurement
- **Statistical accuracy**: 3 iterations per test with median calculations
- **Consistent test data**: Same files used across all implementations

### 📊 **Comprehensive Testing**
- **Multiple data sizes**: Small (1K rows), Medium (100K rows), Large (1M rows)
- **Various operations**: Read, Write, Both
- **Detailed metrics**: Execution time, memory usage, throughput, consistency
- **Comparative analysis**: Performance scaling and efficiency trends

### 🔬 **Advanced Analysis**
- **Statistical measures**: Mean, median, standard deviation
- **Scalability analysis**: How performance changes with data size
- **Memory efficiency**: Per-record memory usage
- **Consistency assessment**: Performance variability analysis

## Quick Start

### 🚀 **Using Makefile (Recommended)**

The easiest way to run benchmarks is using the provided Makefile:

```bash
# Complete setup and run all benchmarks
make setup benchmark

# Or step by step:
make setup          # Build containers and prepare test data
make compare        # Run read benchmarks on both implementations
make benchmark-all  # Run all benchmark scenarios

# Quick help
make help           # Show all available commands
```

### 📋 **Common Makefile Commands**

| Command | Description |
|---------|-------------|
| `make setup` | Build containers and prepare test data |
| `make benchmark` | Prepare data and run all benchmarks |
| `make compare` | Compare FastCSV vs Native performance |
| `make benchmark-read` | Run read benchmarks on both implementations |
| `make benchmark-write` | Run write benchmarks on both implementations |
| `make benchmark-small` | Quick test with small dataset only |
| `make validate-setup` | Verify everything is configured correctly |
| `make show-results` | Display latest benchmark results |
| `make clean` | Stop containers and clean up |

### 🔧 **Manual Docker Commands**

If you prefer manual control:

#### 1. Build and Start Containers

```bash
# Build all containers
docker-compose build

# Start containers in background
docker-compose up -d
```

#### 2. Prepare Test Data

**Important**: Always prepare test data before running benchmarks to ensure credible results.

```bash
# Generate all test data sizes
docker-compose exec benchmark python3 /app/shared/prepare_test_data.py

# Generate specific sizes only
docker-compose exec benchmark python3 /app/shared/prepare_test_data.py --sizes small medium

# Force regenerate existing files
docker-compose exec benchmark python3 /app/shared/prepare_test_data.py --force

# Verify existing test data
docker-compose exec benchmark python3 /app/shared/prepare_test_data.py --verify
```

#### 3. Run Benchmarks

```bash
# Test FastCSV extension (all sizes, 3 iterations each)
docker-compose exec app-fastcsv php /app/shared/benchmark.php read

# Test native SplFileObject (all sizes, 3 iterations each)
docker-compose exec app-native php /app/shared/benchmark.php read

# Test specific size with custom iterations
docker-compose exec app-fastcsv php /app/shared/benchmark.php read medium 5

# Test write operations
docker-compose exec app-fastcsv php /app/shared/benchmark.php write

# Test both read and write
docker-compose exec app-fastcsv php /app/shared/benchmark.php both
```

## Test Data Configuration

The benchmark uses three predefined data sizes:

| Size   | Rows    | Columns | Approx. File Size |
|--------|---------|---------|-------------------|
| Small  | 1,000   | 5       | ~50 KB           |
| Medium | 100,000 | 10      | ~25 MB           |
| Large  | 1,000,000| 15     | ~400 MB          |

### Data Preparation Script Options

```bash
python3 prepare_test_data.py [OPTIONS]

Options:
  --data-dir DIR     Directory to store test data (default: /app/data)
  --sizes SIZE...    Which sizes to generate: small, medium, large, all (default: all)
  --verify          Verify existing files instead of creating new ones
  --force           Overwrite existing files
  --help            Show help message
```

## Benchmark Script Usage

```bash
php benchmark.php [OPERATION] [SIZE] [ITERATIONS]

Arguments:
  OPERATION    read, write, both (default: read)
  SIZE         small, medium, large, all (default: all)
  ITERATIONS   Number of iterations per test (default: 3)

Examples:
  php benchmark.php                    # Read all sizes, 3 iterations each
  php benchmark.php write              # Write all sizes, 3 iterations each
  php benchmark.php read large 5       # Read large size, 5 iterations
  php benchmark.php both small         # Read+write small size, 3 iterations
```

## Results and Analysis

### Output Files

Results are saved in the `/app/results` directory:

- **Detailed JSON**: `comprehensive_{implementation}_{operation}_{timestamp}.json`
- **CSV Summary**: `summary_{implementation}_{operation}_{timestamp}.csv`

### Sample Output

```
=== FastCSV Comprehensive Benchmark ===
Implementation: FastCSV
Operation: read
Iterations per test: 3

╔══════════════════════════════════════════════════════════════╗
║ Testing: small (1,000 rows × 5 columns)                     ║
╚══════════════════════════════════════════════════════════════╝
  → Iteration 1/3... 3.67ms, 2.0 MB peak
  → Iteration 2/3... 3.89ms, 2.0 MB peak
  → Iteration 3/3... 3.45ms, 2.0 MB peak

  📊 Results Summary for small:
  ├─ Execution time: 3.67ms (median), 3.67ms (avg) ±0.22ms
  ├─ Throughput: 272,410 records/sec (median)
  ├─ Memory used: <1KB (streaming - constant memory usage)
  ├─ Memory efficiency: Constant (streaming)
  ├─ Peak memory: 2.0 MB (total)
  └─ Time per record: 0.0037ms

╔══════════════════════════════════════════════════════════════╗
║                    COMPARATIVE ANALYSIS                     ║
╚══════════════════════════════════════════════════════════════╝

📈 Performance Summary (FastCSV - read):
┌─────────────┬─────────────┬─────────────┬─────────────┬─────────────┐
│ Data Size   │ Median Time │ Throughput  │ Memory Used │ Peak Memory │
├─────────────┼─────────────┼─────────────┼─────────────┼─────────────┤
│ small       │     3.67ms  │  272,410/s  │      <1KB   │     2.0MB   │
│ medium      │   176.04ms  │  568,049/s  │      <1KB   │     2.0MB   │
│ large       │  1,987.23ms │  503,212/s  │      <1KB   │     2.0MB   │
└─────────────┴─────────────┴─────────────┴─────────────┴─────────────┘

📊 Scalability Analysis:
  • small → medium: 100.0x data → 47.9x time (efficiency: 2.09x)
  • medium → large: 10.0x data → 11.3x time (efficiency: 0.89x)

🎯 Recommendations:
  ✅ Using FastCSV extension - optimal performance achieved
  📊 Use these results as baseline for performance comparisons

```

## Comparing Implementations

To compare FastCSV vs native performance:

```bash
# Run both implementations with same parameters
docker-compose exec app-fastcsv php /app/shared/benchmark.php read > fastcsv_results.txt
docker-compose exec app-native php /app/shared/benchmark.php read > native_results.txt

# Compare the CSV summary files
# Results are saved with timestamps in /app/results/
```

## Development and Debugging

### Container Access

```bash
# Access FastCSV container
docker-compose exec app-fastcsv sh

# Access native container  
docker-compose exec app-native sh

# Access benchmark container
docker-compose exec benchmark sh
```

### Verify FastCSV Installation

```bash
# Check if FastCSV extension is loaded
docker-compose exec app-fastcsv php -m | grep -i csv
docker-compose exec app-fastcsv php -r "var_dump(extension_loaded('fastcsv'));"

# Check implementation info
docker-compose exec app-fastcsv php -r "
require '/app/shared/vendor/autoload.php';
use CsvToolkit\Helpers\ExtensionHelper;
var_dump(ExtensionHelper::getFastCsvInfo());
"
```

### Troubleshooting

**Test data not found error:**
```bash
# Make sure test data is prepared
docker-compose exec benchmark python3 /app/shared/prepare_test_data.py --verify
```

**Permission issues:**
```bash
# Fix permissions
docker-compose exec benchmark chmod -R 755 /app/data /app/results
```

**Memory issues with large datasets:**
```bash
# Check PHP memory limit
docker-compose exec app-fastcsv php -r "echo ini_get('memory_limit');"

# Monitor container resources
docker stats
```

## Best Practices

1. **Always prepare test data first** before running benchmarks
2. **Use consistent iterations** (3 or 5) for statistical accuracy
3. **Run benchmarks multiple times** on different days for consistency
4. **Monitor system resources** during large dataset tests
5. **Compare results** between FastCSV and native implementations
6. **Document your findings** with timestamps and system specifications

This setup ensures credible, reproducible benchmarks that accurately measure FastCSV performance without any interference from data generation overhead. 