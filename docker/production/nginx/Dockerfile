# =============================================================================
# Stage 1: Build frontend assets
# =============================================================================
FROM node:22-alpine AS assets

WORKDIR /app

# Copy package files first for better layer caching
COPY package.json package-lock.json* ./
RUN npm ci --prefer-offline

# Copy the rest of the app and build
COPY . .
RUN npm run build

# =============================================================================
# Stage 2: Nginx production image
# =============================================================================
FROM nginx:alpine AS production

# Copy custom Nginx configuration
COPY docker/production/nginx/nginx.conf /etc/nginx/nginx.conf

# Copy Laravel's public directory (built assets only — PHP requests proxy to php-fpm)
COPY --from=assets /app/public /var/www/html/public

WORKDIR /var/www/html/public

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD wget -qO- http://localhost/health || exit 1

CMD ["nginx", "-g", "daemon off;"]
