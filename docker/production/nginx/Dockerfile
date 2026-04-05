# =============================================================================
# Stage 1: Build frontend assets
# =============================================================================
FROM node:22-alpine AS assets

WORKDIR /var/www/html

# Copy package files first for better layer caching
COPY package.json package-lock.json* ./
RUN npm ci

# Copy the rest of the app and build
COPY . .
RUN npm run build

# =============================================================================
# Stage 2: Nginx production image
# =============================================================================
FROM nginx:alpine AS production

# Copy custom Nginx configuration
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf

# Copy Laravel's public directory (PHP files + built assets)
COPY --from=assets /var/www/html/public /var/www/html/public

WORKDIR /var/www/html/public

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
