FROM php:8.4-cli

# Install required packages and dev tools
RUN apt-get update && apt-get install -y \
    curl \
    time \
    bc \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install XHProf extension
RUN pecl install xhprof \
    && echo "extension=xhprof.so" > /usr/local/etc/php/conf.d/xhprof.ini

# Set PHP memory limit to 1GB for large CSV processing
RUN echo "memory_limit = 1G" > /usr/local/etc/php/conf.d/memory.ini

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /app

# Verify setup
RUN php -m | grep -v fastcsv && echo "Using native PHP SplFileObject fallback"
RUN php -m | grep xhprof && echo "XHProf extension loaded successfully"

CMD ["sleep", "infinity"] 