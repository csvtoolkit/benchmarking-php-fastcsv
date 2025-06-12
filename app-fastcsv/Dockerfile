FROM php:8.4-cli

# Install required packages and dev tools
RUN apt-get update && apt-get install -y \
    curl \
    time \
    bc \
    git \
    build-essential \
    autoconf \
    && rm -rf /var/lib/apt/lists/*

# Clone and build FastCSV extension from GitHub with submodules
RUN git clone --recursive https://github.com/csvtoolkit/FastCSV-ext.git /tmp/fastcsv-ext \
    && cd /tmp/fastcsv-ext \
    && phpize \
    && ./configure --enable-fastcsv \
    && make \
    && make install \
    && echo "extension=fastcsv.so" > /usr/local/etc/php/conf.d/fastcsv.ini \
    && rm -rf /tmp/fastcsv-ext

# Install XHProf extension
RUN pecl install xhprof \
    && echo "extension=xhprof.so" > /usr/local/etc/php/conf.d/xhprof.ini

# Set PHP memory limit to 1GB for large CSV processing
RUN echo "memory_limit = 1G" > /usr/local/etc/php/conf.d/memory.ini

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /app

# Verify extensions are loaded
RUN php -m | grep fastcsv || (echo "FastCSV extension not loaded!" && exit 1)
RUN php -m | grep xhprof && echo "XHProf extension loaded successfully"

CMD ["sleep", "infinity"] 