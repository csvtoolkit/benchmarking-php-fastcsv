FROM alpine:latest

# Install required tools
RUN apk add --no-cache \
    docker-cli \
    bash \
    bc \
    curl \
    jq \
    python3 \
    py3-pip

# Set working directory
WORKDIR /benchmark

# Copy benchmark scripts
COPY . .

# Make scripts executable
RUN chmod +x *.sh

CMD ["./run_benchmark.sh"] 