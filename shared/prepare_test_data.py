#!/usr/bin/env python3
"""
Test Data Preparation Script for FastCSV Benchmarks

This script pre-generates all CSV test files to ensure benchmark credibility.
No data generation happens during actual benchmarking.
"""

import csv
import os
import sys
import time
from pathlib import Path
import argparse

# Test configurations matching benchmark.php
CONFIGS = {
    'small': {'rows': 1000, 'cols': 5},
    'medium': {'rows': 100000, 'cols': 10},
    'large': {'rows': 1000000, 'cols': 15}
}

def create_test_file(filepath, rows, cols, size_name):
    """Create a single test CSV file with specified dimensions."""
    print(f"  Creating {size_name} dataset: {rows:,} rows × {cols} columns... ", end="", flush=True)
    
    start_time = time.time()
    
    # Ensure directory exists
    os.makedirs(os.path.dirname(filepath), exist_ok=True)
    
    with open(filepath, 'w', newline='', encoding='utf-8') as csvfile:
        writer = csv.writer(csvfile)
        
        # Write header
        header = [f"column_{i+1}" for i in range(cols)]
        writer.writerow(header)
        
        # Write data rows
        for row_num in range(1, rows + 1):
            row_data = [f"test_data_{row_num}_{col_num+1}" for col_num in range(cols)]
            writer.writerow(row_data)
            
            # Progress indicator for large files
            if rows > 50000 and row_num % 50000 == 0:
                print(f"{row_num:,}...", end="", flush=True)
    
    end_time = time.time()
    file_size = os.path.getsize(filepath)
    
    print(f"Done ({end_time - start_time:.1f}s, {format_bytes(file_size)})")

def format_bytes(bytes_val):
    """Format bytes into human readable format."""
    for unit in ['B', 'KB', 'MB', 'GB']:
        if bytes_val < 1024.0:
            return f"{bytes_val:.1f} {unit}"
        bytes_val /= 1024.0
    return f"{bytes_val:.1f} TB"

def verify_test_file(filepath, expected_rows, expected_cols):
    """Verify that a test file has the expected structure."""
    try:
        with open(filepath, 'r', newline='', encoding='utf-8') as csvfile:
            reader = csv.reader(csvfile)
            
            # Check header
            header = next(reader)
            if len(header) != expected_cols:
                return False, f"Header has {len(header)} columns, expected {expected_cols}"
            
            # Count rows (including header)
            row_count = 1  # Already read header
            for _ in reader:
                row_count += 1
            
            expected_total_rows = expected_rows + 1  # +1 for header
            if row_count != expected_total_rows:
                return False, f"File has {row_count} rows, expected {expected_total_rows}"
            
            return True, "OK"
    except Exception as e:
        return False, f"Error reading file: {e}"

def main():
    parser = argparse.ArgumentParser(description='Prepare CSV test data for benchmarking')
    parser.add_argument('--data-dir', default='/app/data', 
                       help='Directory to store test data (default: /app/data)')
    parser.add_argument('--sizes', nargs='*', choices=list(CONFIGS.keys()) + ['all'],
                       default=['all'], help='Which sizes to generate (default: all)')
    parser.add_argument('--verify', action='store_true',
                       help='Verify existing files instead of creating new ones')
    parser.add_argument('--force', action='store_true',
                       help='Overwrite existing files')
    
    args = parser.parse_args()
    
    # Determine which sizes to process
    if 'all' in args.sizes:
        sizes_to_process = list(CONFIGS.keys())
    else:
        sizes_to_process = args.sizes
    
    print("=== FastCSV Test Data Preparation ===")
    print(f"Data directory: {args.data_dir}")
    print(f"Sizes to process: {', '.join(sizes_to_process)}")
    
    if args.verify:
        print("Mode: Verification")
    else:
        print("Mode: Generation" + (" (force overwrite)" if args.force else ""))
    
    print()
    
    # Process each size
    total_files = 0
    total_size = 0
    errors = []
    
    for size_name in sizes_to_process:
        config = CONFIGS[size_name]
        filename = f"test_{size_name}_{config['rows']}x{config['cols']}.csv"
        filepath = os.path.join(args.data_dir, filename)
        
        print(f"📁 Processing {size_name} dataset:")
        
        if args.verify:
            # Verification mode
            if os.path.exists(filepath):
                print(f"  Verifying {filename}... ", end="", flush=True)
                is_valid, message = verify_test_file(filepath, config['rows'], config['cols'])
                if is_valid:
                    file_size = os.path.getsize(filepath)
                    print(f"✅ {message} ({format_bytes(file_size)})")
                    total_files += 1
                    total_size += file_size
                else:
                    print(f"❌ {message}")
                    errors.append(f"{filename}: {message}")
            else:
                print(f"  ❌ File not found: {filename}")
                errors.append(f"{filename}: File not found")
        else:
            # Generation mode
            if os.path.exists(filepath) and not args.force:
                file_size = os.path.getsize(filepath)
                print(f"  ⏭️  Skipping {filename} (already exists, {format_bytes(file_size)})")
                total_files += 1
                total_size += file_size
            else:
                try:
                    create_test_file(filepath, config['rows'], config['cols'], size_name)
                    file_size = os.path.getsize(filepath)
                    total_files += 1
                    total_size += file_size
                    
                    # Quick verification
                    print(f"  Verifying... ", end="", flush=True)
                    is_valid, message = verify_test_file(filepath, config['rows'], config['cols'])
                    if is_valid:
                        print("✅ Valid")
                    else:
                        print(f"❌ {message}")
                        errors.append(f"{filename}: {message}")
                        
                except Exception as e:
                    print(f"❌ Error: {e}")
                    errors.append(f"{filename}: {e}")
        
        print()
    
    # Summary
    print("=" * 60)
    print("📊 Summary:")
    print(f"  Files processed: {total_files}")
    print(f"  Total data size: {format_bytes(total_size)}")
    
    if errors:
        print(f"  ❌ Errors: {len(errors)}")
        for error in errors:
            print(f"    • {error}")
        sys.exit(1)
    else:
        print("  ✅ All files ready for benchmarking!")
    
    print()
    print("🚀 Ready to run benchmarks:")
    print("  docker-compose exec benchmark php benchmark.php read")
    print("  docker-compose exec benchmark php benchmark.php write")
    print("  docker-compose exec benchmark php benchmark.php both")

if __name__ == "__main__":
    main() 