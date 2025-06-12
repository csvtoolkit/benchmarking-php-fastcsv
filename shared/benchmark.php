<?php

require_once __DIR__ . '/vendor/autoload.php';

use CsvToolkit\Factories\CsvFactory;

// Memory limit is now set in Docker container to 1GB

/**
 * Benchmark script that tests CSV operations using csv-helper
 * This will automatically use FastCSV extension if available, otherwise fallback to SplFileObject
 */

$operation = $argv[1] ?? 'read';
$dataSize = $argv[2] ?? 'all';  // Default to 'all' to run all sizes
$iterations = (int)($argv[3] ?? 3);  // Default to 3 iterations

// Configuration
$configs = [
    'small' => ['rows' => 1000, 'cols' => 5],
    'medium' => ['rows' => 100000, 'cols' => 10],
    'large' => ['rows' => 1000000, 'cols' => 15]
];

$implementation = CsvFactory::isFastCsvAvailable() ? 'FastCSV' : 'SplFileObject';

echo "=== FastCSV Comprehensive Benchmark ===\n";
echo "Implementation: {$implementation}\n";
echo "Operation: {$operation}\n";
echo "Iterations per test: {$iterations}\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// Determine which sizes to run
$sizesToRun = [];
if ($dataSize === 'all') {
    $sizesToRun = array_keys($configs);
    echo "Running all data sizes: " . implode(', ', $sizesToRun) . "\n\n";
} else {
    if (!isset($configs[$dataSize])) {
        echo "Error: Unknown data size '{$dataSize}'. Available: " . implode(', ', array_keys($configs)) . "\n";
        exit(1);
    }
    $sizesToRun = [$dataSize];
    echo "Running single data size: {$dataSize}\n\n";
}

// Master results storage
$masterResults = [
    'metadata' => [
        'operation' => $operation,
        'implementation' => $implementation,
        'iterations_per_test' => $iterations,
        'php_version' => PHP_VERSION,
        'memory_limit' => ini_get('memory_limit'),
        'timestamp' => date('Y-m-d H:i:s'),
        'implementation_info' => CsvFactory::getImplementationInfo()
    ],
    'test_results' => [],
    'comparative_analysis' => []
];

// Run benchmarks for each size
foreach ($sizesToRun as $currentSize) {
    $config = $configs[$currentSize];
    
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║ Testing: {$currentSize} ({$config['rows']} rows × {$config['cols']} columns)" . str_repeat(' ', max(0, 25 - strlen($currentSize) - strlen($config['rows']) - strlen($config['cols']))) . "║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n";
    
    $testResults = runSizeTest($operation, $currentSize, $config, $iterations);
    $masterResults['test_results'][$currentSize] = $testResults;
    
    // Display summary for this size
    displaySizeSummary($currentSize, $testResults);
    
    echo "\n" . str_repeat("─", 70) . "\n\n";
}

// Generate comparative analysis
$masterResults['comparative_analysis'] = generateComparativeAnalysis($masterResults['test_results']);

// Save comprehensive results
saveComprehensiveResults($masterResults, $operation, $implementation);

// Display final comparative summary
displayComparativeSummary($masterResults);

function runSizeTest($operation, $dataSize, $config, $iterations) {
    $results = [
        'metadata' => [
            'data_size' => $dataSize,
            'rows' => $config['rows'],
            'cols' => $config['cols'],
            'iterations' => $iterations
        ],
        'iterations' => [],
        'summary' => []
    ];
    
    // Run iterations for this size
    for ($i = 1; $i <= $iterations; $i++) {
        echo "  → Iteration {$i}/{$iterations}... ";
        
        // Force garbage collection for accurate baseline
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        // Take baseline measurements (both logical and real memory)
        $startMemoryLogical = memory_get_usage(false);
        $startMemoryReal = memory_get_usage(true);
        $startPeakReal = memory_get_peak_usage(true);
        $startTime = microtime(true);
        
        $iterationResult = runBenchmark($operation, $config, $i, $dataSize);
        
        $endTime = microtime(true);
        $endMemoryLogical = memory_get_usage(false);
        $endMemoryReal = memory_get_usage(true);
        $peakMemoryReal = memory_get_peak_usage(true);
        
        // Calculate actual memory usage differences
        $memoryUsedLogical = $endMemoryLogical - $startMemoryLogical;
        $memoryUsedReal = $endMemoryReal - $startMemoryReal;
        $peakMemoryIncrease = $peakMemoryReal - $startPeakReal;
        
        $iterationData = [
            'iteration' => $i,
            'execution_time_ms' => round(($endTime - $startTime) * 1000, 2),
            'memory_used_logical_bytes' => $memoryUsedLogical,
            'memory_used_real_bytes' => $memoryUsedReal,
            'peak_memory_increase_bytes' => $peakMemoryIncrease,
            'peak_memory_total_bytes' => $peakMemoryReal,
            'baseline_memory_real_bytes' => $startMemoryReal,
            'records_processed' => $iterationResult['records'] ?? 0,
            'throughput_records_per_sec' => $iterationResult['records'] ? round($iterationResult['records'] / (($endTime - $startTime)), 2) : 0
        ];
        
        $results['iterations'][] = $iterationData;
        
        // Show meaningful memory information
        if ($memoryUsedReal > 1024) {
            echo "{$iterationData['execution_time_ms']}ms, " . formatBytes($memoryUsedReal) . " used, " . formatBytes($peakMemoryReal) . " peak\n";
        } else {
            echo "{$iterationData['execution_time_ms']}ms, " . formatBytes($peakMemoryReal) . " peak\n";
        }
        
        // Clear memory between iterations
        unset($iterationResult);
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        // Brief pause between iterations
        if ($i < $iterations) {
            usleep(250000); // 0.25 second (reduced pause)
        }
    }
    
    // Calculate summary statistics
    $executionTimes = array_column($results['iterations'], 'execution_time_ms');
    $memoryUsagesLogical = array_column($results['iterations'], 'memory_used_logical_bytes');
    $memoryUsagesReal = array_column($results['iterations'], 'memory_used_real_bytes');
    $peakMemoryIncreases = array_column($results['iterations'], 'peak_memory_increase_bytes');
    $peakMemoriesTotal = array_column($results['iterations'], 'peak_memory_total_bytes');
    $throughputs = array_column($results['iterations'], 'throughput_records_per_sec');
    
    $results['summary'] = [
        'avg_execution_time_ms' => round(array_sum($executionTimes) / count($executionTimes), 2),
        'min_execution_time_ms' => min($executionTimes),
        'max_execution_time_ms' => max($executionTimes),
        'median_execution_time_ms' => calculateMedian($executionTimes),
        'std_dev_execution_time_ms' => round(calculateStdDev($executionTimes), 2),
        
        // Memory statistics - now more accurate
        'avg_memory_used_logical_bytes' => round(array_sum($memoryUsagesLogical) / count($memoryUsagesLogical)),
        'avg_memory_used_real_bytes' => round(array_sum($memoryUsagesReal) / count($memoryUsagesReal)),
        'avg_peak_memory_increase_bytes' => round(array_sum($peakMemoryIncreases) / count($peakMemoryIncreases)),
        'avg_peak_memory_total_bytes' => round(array_sum($peakMemoriesTotal) / count($peakMemoriesTotal)),
        'median_peak_memory_total_bytes' => calculateMedian($peakMemoriesTotal),
        'max_memory_used_real_bytes' => max($memoryUsagesReal),
        
        'avg_throughput_records_per_sec' => round(array_sum($throughputs) / count($throughputs), 2),
        'median_throughput_records_per_sec' => calculateMedian($throughputs),
        'max_throughput_records_per_sec' => max($throughputs),
        
        // Performance metrics - using more accurate memory measurements
        'avg_time_per_record_ms' => round((array_sum($executionTimes) / count($executionTimes)) / $config['rows'], 4),
        'memory_per_record_bytes_logical' => round((array_sum($memoryUsagesLogical) / count($memoryUsagesLogical)) / $config['rows'], 2),
        'memory_per_record_bytes_real' => round((array_sum($memoryUsagesReal) / count($memoryUsagesReal)) / $config['rows'], 2)
    ];
    
    return $results;
}

function runBenchmark($operation, $config, $iteration, $dataSize) {
    switch ($operation) {
        case 'read':
            return benchmarkRead($config, $iteration, $dataSize);
        case 'write':
            return benchmarkWrite($config, $iteration, $dataSize);
        case 'both':
            $writeResult = benchmarkWrite($config, $iteration, $dataSize);
            $readResult = benchmarkRead($config, $iteration, $dataSize);
            return ['records' => $writeResult['records'] + $readResult['records']];
        default:
            echo "Unknown operation: {$operation}\n";
            exit(1);
    }
}

function benchmarkRead($config, $iteration, $dataSize) {
    $filename = "/app/data/test_{$dataSize}_{$config['rows']}x{$config['cols']}.csv";
    
    if (!file_exists($filename)) {
        echo "ERROR: Test data file not found: {$filename}\n";
        echo "   Please run: python3 prepare_test_data.py\n";
        exit(1);
    }
    
    $reader = CsvFactory::createReader($filename);
    $recordCount = 0;
    
    // Traditional pattern - but the underlying issue is in CsvReader::nextRecord()
    // which calls getRecordCount() on every call, causing O(n²) performance
    while (($record = $reader->nextRecord()) !== false) {
        $recordCount++;
        unset($record);
    }
    
    // Clean up reader object  
    unset($reader);
    
    return ['records' => $recordCount];
}

function benchmarkWrite($config, $iteration, $dataSize) {
    $filename = "/app/data/output_{$dataSize}_{$config['rows']}x{$config['cols']}_iter{$iteration}.csv";
    
    $writer = CsvFactory::createWriter($filename);
    
    // Write header
    $header = [];
    for ($i = 1; $i <= $config['cols']; $i++) {
        $header[] = "column_{$i}";
    }
    $writer->write($header);
    unset($header);
    
    // Write data rows
    for ($row = 1; $row <= $config['rows']; $row++) {
        $record = [];
        for ($col = 1; $col <= $config['cols']; $col++) {
            $record[] = "value_{$row}_{$col}";
        }
        $writer->write($record);
        unset($record); // Free each record immediately
    }
    
    unset($writer);
    
    return ['records' => $config['rows']];
}

function displaySizeSummary($dataSize, $results) {
    $summary = $results['summary'];
    $metadata = $results['metadata'];
    
    echo "\n  Results Summary for {$dataSize}:\n";
    echo "  ├─ Execution time: {$summary['median_execution_time_ms']}ms (median), {$summary['avg_execution_time_ms']}ms (avg) ±{$summary['std_dev_execution_time_ms']}ms\n";
    echo "  ├─ Throughput: " . number_format($summary['median_throughput_records_per_sec']) . " records/sec (median)\n";
    
    // Show memory information more accurately
    if ($summary['avg_memory_used_real_bytes'] > 1024) {
        echo "  ├─ Memory used: " . formatBytes($summary['avg_memory_used_real_bytes']) . " (real), " . formatBytes($summary['avg_memory_used_logical_bytes']) . " (logical)\n";
        echo "  ├─ Memory efficiency: {$summary['memory_per_record_bytes_real']} bytes/record (real)\n";
    } else {
        echo "  ├─ Memory used: <1KB (streaming - constant memory usage)\n";
        echo "  ├─ Memory efficiency: Constant (streaming)\n";
    }
    
    echo "  ├─ Peak memory: " . formatBytes($summary['avg_peak_memory_total_bytes']) . " (total)\n";
    echo "  └─ Time per record: {$summary['avg_time_per_record_ms']}ms\n";
}

function generateComparativeAnalysis($testResults) {
    $analysis = [
        'scalability' => [],
        'efficiency_trends' => [],
        'performance_ratios' => []
    ];
    
    $sizes = array_keys($testResults);
    
    // Analyze scalability (how performance changes with data size)
    for ($i = 1; $i < count($sizes); $i++) {
        $prevSize = $sizes[$i-1];
        $currentSize = $sizes[$i];
        
        $prevResults = $testResults[$prevSize];
        $currentResults = $testResults[$currentSize];
        
        $rowsRatio = $currentResults['metadata']['rows'] / $prevResults['metadata']['rows'];
        $timeRatio = $currentResults['summary']['median_execution_time_ms'] / $prevResults['summary']['median_execution_time_ms'];
        $memoryRatio = $currentResults['summary']['avg_peak_memory_total_bytes'] / $prevResults['summary']['avg_peak_memory_total_bytes'];
        
        $analysis['scalability'][] = [
            'from' => $prevSize,
            'to' => $currentSize,
            'data_size_ratio' => round($rowsRatio, 2),
            'time_ratio' => round($timeRatio, 2),
            'memory_ratio' => round($memoryRatio, 2),
            'efficiency_score' => round($rowsRatio / $timeRatio, 2) // Higher is better
        ];
    }
    
    return $analysis;
}

function saveComprehensiveResults($masterResults, $operation, $implementation) {
    $resultsDir = '/app/results';
    if (!is_dir($resultsDir)) {
        mkdir($resultsDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    
    // Save detailed JSON results
    $jsonFile = "{$resultsDir}/comprehensive_{$implementation}_{$operation}_{$timestamp}.json";
    file_put_contents($jsonFile, json_encode($masterResults, JSON_PRETTY_PRINT));
    
    // Save CSV summary
    $csvFile = "{$resultsDir}/summary_{$implementation}_{$operation}_{$timestamp}.csv";
    $csvData = [
        ['implementation', 'operation', 'data_size', 'rows', 'cols', 'median_time_ms', 'avg_time_ms', 'std_dev_ms', 'median_throughput_rps', 'memory_used_real_kb', 'memory_used_logical_kb', 'peak_memory_total_mb', 'time_per_record_ms', 'memory_per_record_real_bytes']
    ];
    
    foreach ($masterResults['test_results'] as $size => $results) {
        $csvData[] = [
            $implementation,
            $operation,
            $size,
            $results['metadata']['rows'],
            $results['metadata']['cols'],
            $results['summary']['median_execution_time_ms'],
            $results['summary']['avg_execution_time_ms'],
            $results['summary']['std_dev_execution_time_ms'],
            $results['summary']['median_throughput_records_per_sec'],
            round($results['summary']['avg_memory_used_real_bytes'] / 1024, 2),
            round($results['summary']['avg_memory_used_logical_bytes'] / 1024, 2),
            round($results['summary']['avg_peak_memory_total_bytes'] / 1024 / 1024, 2),
            $results['summary']['avg_time_per_record_ms'],
            $results['summary']['memory_per_record_bytes_real']
        ];
    }
    
    $csvWriter = CsvFactory::createWriter($csvFile);
    foreach ($csvData as $row) {
        $csvWriter->write($row);
    }
    
    echo "\nResults saved:\n";
    echo "  - Detailed: {$jsonFile}\n";
    echo "  - Summary: {$csvFile}\n";
}

function displayComparativeSummary($masterResults) {
    echo "\n╔══════════════════════════════════════════════════════════════╗\n";
    echo "║                    COMPARATIVE ANALYSIS                     ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n";
    
    $implementation = $masterResults['metadata']['implementation'];
    $operation = $masterResults['metadata']['operation'];
    
    echo "\nPerformance Summary ({$implementation} - {$operation}):\n";
    echo "┌─────────────┬─────────────┬─────────────┬─────────────┬─────────────┐\n";
    echo "│ Data Size   │ Median Time │ Throughput  │ Memory Used │ Peak Memory │\n";
    echo "├─────────────┼─────────────┼─────────────┼─────────────┼─────────────┤\n";
    
    foreach ($masterResults['test_results'] as $size => $results) {
        $summary = $results['summary'];
        $memoryUsed = $summary['avg_memory_used_real_bytes'] > 1024 ? 
            formatBytes($summary['avg_memory_used_real_bytes']) : 
            '<1KB';
        
        printf("│ %-11s │ %8.2fms │ %8.0f/s │ %10s │ %10s │\n",
            $size,
            $summary['median_execution_time_ms'],
            $summary['median_throughput_records_per_sec'],
            $memoryUsed,
            formatBytes($summary['avg_peak_memory_total_bytes'])
        );
    }
    echo "└─────────────┴─────────────┴─────────────┴─────────────┴─────────────┘\n";
    
    // Scalability analysis
    if (isset($masterResults['comparative_analysis']['scalability']) && !empty($masterResults['comparative_analysis']['scalability'])) {
        echo "\nScalability Analysis:\n";
        foreach ($masterResults['comparative_analysis']['scalability'] as $scale) {
            echo "  • {$scale['from']} → {$scale['to']}: ";
            echo "{$scale['data_size_ratio']}x data → {$scale['time_ratio']}x time ";
            echo "(efficiency: {$scale['efficiency_score']}x)\n";
        }
    }
    
    echo "\nImplementation Details:\n";
    if ($implementation === 'FastCSV') {
        echo "  - Using FastCSV extension\n";
    } else {
        echo "  - Using SplFileObject fallback\n";
    }
    
    // Find best performing size
    $bestThroughput = 0;
    $bestSize = '';
    foreach ($masterResults['test_results'] as $size => $results) {
        if ($results['summary']['median_throughput_records_per_sec'] > $bestThroughput) {
            $bestThroughput = $results['summary']['median_throughput_records_per_sec'];
            $bestSize = $size;
        }
    }
    
    if ($bestSize) {
        echo "  - Highest throughput: {$bestSize} size (" . number_format($bestThroughput) . " records/sec)\n";
    }
    
    echo "\n" . str_repeat("═", 70) . "\n";
}

function calculateMedian($values) {
    sort($values);
    $count = count($values);
    $middle = floor($count / 2);
    
    if ($count % 2 === 0) {
        return ($values[$middle - 1] + $values[$middle]) / 2;
    } else {
        return $values[$middle];
    }
}

function calculateStdDev($values) {
    $mean = array_sum($values) / count($values);
    $variance = array_sum(array_map(function($x) use ($mean) {
        return pow($x - $mean, 2);
    }, $values)) / count($values);
    
    return sqrt($variance);
}

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
} 