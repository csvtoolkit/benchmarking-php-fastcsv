# FastCSV Benchmark Suite Makefile
# Automates container management and benchmark execution

.PHONY: help build up down clean prepare-data verify-data benchmark-all benchmark-read benchmark-write benchmark-both compare logs status

# Default target
help: ## Show this help message
	@echo "FastCSV Benchmark Suite"
	@echo "======================="
	@echo ""
	@echo "Available targets:"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'
	@echo ""
	@echo "Quick start:"
	@echo "  make setup      # Build containers and prepare test data"
	@echo "  make benchmark  # Run all benchmarks"
	@echo "  make compare    # Compare FastCSV vs Native performance"

# Container Management
build: ## Build all Docker containers
	@echo "🔨 Building Docker containers..."
	docker-compose build

up: ## Start all containers in background
	@echo "🚀 Starting containers..."
	docker-compose up -d
	@echo "✅ Containers started"
	@make status

down: ## Stop and remove all containers
	@echo "🛑 Stopping containers..."
	docker-compose down
	@echo "✅ Containers stopped"

restart: down up ## Restart all containers

status: ## Show container status
	@echo "📊 Container Status:"
	@docker-compose ps

logs: ## Show logs from all containers
	docker-compose logs -f

# Data Management
prepare-data: up ## Prepare all test data files
	@echo "📁 Preparing test data..."
	docker-compose exec benchmark python3 /app/shared/prepare_test_data.py
	@echo "✅ Test data prepared"

prepare-data-force: up ## Force regenerate all test data files
	@echo "📁 Force preparing test data..."
	docker-compose exec benchmark python3 /app/shared/prepare_test_data.py --force
	@echo "✅ Test data force prepared"

verify-data: up ## Verify existing test data files
	@echo "🔍 Verifying test data..."
	docker-compose exec benchmark python3 /app/shared/prepare_test_data.py --verify

clean-data: ## Remove all test data files
	@echo "🧹 Cleaning test data..."
	docker-compose exec benchmark rm -rf /app/data/*
	@echo "✅ Test data cleaned"

# Individual Benchmarks
benchmark-read-fastcsv: up ## Run read benchmarks on FastCSV
	@echo "📖 Running FastCSV read benchmarks..."
	docker-compose exec app-fastcsv php /app/shared/benchmark.php read

benchmark-read-native: up ## Run read benchmarks on Native PHP
	@echo "📖 Running Native PHP read benchmarks..."
	docker-compose exec app-native php /app/shared/benchmark.php read

benchmark-write-fastcsv: up ## Run write benchmarks on FastCSV
	@echo "✏️  Running FastCSV write benchmarks..."
	docker-compose exec app-fastcsv php /app/shared/benchmark.php write

benchmark-write-native: up ## Run write benchmarks on Native PHP
	@echo "✏️  Running Native PHP write benchmarks..."
	docker-compose exec app-native php /app/shared/benchmark.php write

benchmark-both-fastcsv: up ## Run read+write benchmarks on FastCSV
	@echo "🔄 Running FastCSV read+write benchmarks..."
	docker-compose exec app-fastcsv php /app/shared/benchmark.php both

benchmark-both-native: up ## Run read+write benchmarks on Native PHP
	@echo "🔄 Running Native PHP read+write benchmarks..."
	docker-compose exec app-native php /app/shared/benchmark.php both

# Comprehensive Benchmarks
benchmark-read: benchmark-read-fastcsv benchmark-read-native ## Run read benchmarks on both implementations

benchmark-write: benchmark-write-fastcsv benchmark-write-native ## Run write benchmarks on both implementations

benchmark-both: benchmark-both-fastcsv benchmark-both-native ## Run read+write benchmarks on both implementations

benchmark-all: benchmark-read benchmark-write benchmark-both ## Run all benchmark scenarios

# Quick benchmark targets
benchmark: prepare-data benchmark-all ## Prepare data and run all benchmarks

compare: benchmark-read ## Run read benchmarks and show comparison
	@echo ""
	@echo "🔍 Benchmark Comparison Complete!"
	@echo "📊 Results saved in ./results/ directory"
	@echo "💡 Check the CSV files for detailed comparison data"

# Setup and Teardown
setup: build up prepare-data ## Complete setup: build, start, and prepare data
	@echo ""
	@echo "🎉 Setup complete! Ready to run benchmarks."
	@echo ""
	@echo "Next steps:"
	@echo "  make benchmark     # Run all benchmarks"
	@echo "  make compare       # Compare implementations"
	@echo "  make logs          # View container logs"

teardown: down clean ## Stop containers and clean up
	@echo "🧹 Teardown complete"

# Development helpers
shell-fastcsv: up ## Open shell in FastCSV container
	docker-compose exec app-fastcsv sh

shell-native: up ## Open shell in Native container
	docker-compose exec app-native sh

shell-benchmark: up ## Open shell in benchmark container
	docker-compose exec benchmark sh

# Specific size benchmarks
benchmark-small: up ## Run benchmarks on small dataset only
	@echo "📊 Running small dataset benchmarks..."
	docker-compose exec app-fastcsv php /app/shared/benchmark.php read small
	docker-compose exec app-native php /app/shared/benchmark.php read small

benchmark-medium: up ## Run benchmarks on medium dataset only
	@echo "📊 Running medium dataset benchmarks..."
	docker-compose exec app-fastcsv php /app/shared/benchmark.php read medium
	docker-compose exec app-native php /app/shared/benchmark.php read medium

benchmark-large: up ## Run benchmarks on large dataset only
	@echo "📊 Running large dataset benchmarks..."
	docker-compose exec app-fastcsv php /app/shared/benchmark.php read large
	docker-compose exec app-native php /app/shared/benchmark.php read large

# Custom iterations
benchmark-read-5: up ## Run read benchmarks with 5 iterations
	@echo "📖 Running read benchmarks (5 iterations)..."
	docker-compose exec app-fastcsv php /app/shared/benchmark.php read all 5
	docker-compose exec app-native php /app/shared/benchmark.php read all 5

benchmark-write-5: up ## Run write benchmarks with 5 iterations
	@echo "✏️  Running write benchmarks (5 iterations)..."
	docker-compose exec app-fastcsv php /app/shared/benchmark.php write all 5
	docker-compose exec app-native php /app/shared/benchmark.php write all 5

# Results management
show-results: ## Show latest benchmark results
	@echo "📊 Latest Benchmark Results:"
	@echo ""
	@ls -la results/ | head -10
	@echo ""
	@echo "📈 Latest CSV summaries:"
	@find results/ -name "summary_*.csv" -type f -exec ls -la {} \; | tail -5

clean-results: ## Clean all benchmark results
	@echo "🧹 Cleaning benchmark results..."
	rm -rf results/*
	@echo "✅ Results cleaned"

# Validation and testing
validate-setup: up ## Validate that everything is set up correctly
	@echo "🔍 Validating setup..."
	@echo "1. Checking containers..."
	@docker-compose ps
	@echo ""
	@echo "2. Checking FastCSV extension..."
	@docker-compose exec app-fastcsv php -r "echo extension_loaded('fastcsv') ? '✅ FastCSV loaded' : '❌ FastCSV not loaded'; echo PHP_EOL;"
	@echo ""
	@echo "3. Checking csv-helper library..."
	@docker-compose exec app-fastcsv php -r "require '/app/shared/vendor/autoload.php'; use CsvToolkit\Factories\CsvFactory; echo CsvFactory::isFastCsvAvailable() ? '✅ FastCSV available via csv-helper' : '❌ FastCSV not available'; echo PHP_EOL;"
	@echo ""
	@echo "4. Checking test data..."
	@docker-compose exec benchmark python3 /app/shared/prepare_test_data.py --verify || echo "⚠️  Test data not prepared - run 'make prepare-data'"
	@echo ""
	@echo "✅ Validation complete"

# Performance monitoring
monitor: ## Monitor container resource usage
	@echo "📊 Monitoring container resources (Ctrl+C to stop)..."
	docker stats

# Quick development cycle
dev: setup validate-setup ## Development setup with validation
	@echo ""
	@echo "🚀 Development environment ready!"
	@echo ""
	@echo "Available commands:"
	@echo "  make benchmark-small    # Quick test with small dataset"
	@echo "  make shell-fastcsv      # Debug FastCSV container"
	@echo "  make shell-native       # Debug Native container"
	@echo "  make logs               # View all logs"

# Cleanup
clean: down ## Stop containers and clean up everything
	@echo "🧹 Cleaning up..."
	docker-compose down -v --remove-orphans
	docker system prune -f
	@echo "✅ Cleanup complete"

# Documentation
docs: ## Show benchmark documentation
	@echo "📚 FastCSV Benchmark Suite Documentation"
	@echo "========================================"
	@echo ""
	@cat README.md | head -50
	@echo ""
	@echo "📖 For full documentation, see: README.md"

# Advanced benchmarking
benchmark-stress: up ## Run stress test with large dataset and many iterations
	@echo "💪 Running stress test..."
	docker-compose exec app-fastcsv php /app/shared/benchmark.php both large 10
	docker-compose exec app-native php /app/shared/benchmark.php both large 10

benchmark-memory: up ## Run memory-focused benchmarks
	@echo "🧠 Running memory benchmarks..."
	@echo "FastCSV Memory Usage:"
	docker-compose exec app-fastcsv php -d memory_limit=256M /app/shared/benchmark.php read large 3
	@echo ""
	@echo "Native Memory Usage:"
	docker-compose exec app-native php -d memory_limit=256M /app/shared/benchmark.php read large 3

# CI/CD helpers
ci-test: build up prepare-data benchmark-small ## Minimal test for CI/CD
	@echo "✅ CI test completed successfully"

ci-full: setup benchmark-all ## Full CI/CD test suite
	@echo "✅ Full CI test completed successfully" 