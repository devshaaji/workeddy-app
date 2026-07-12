FROM nginx:1.27-alpine

COPY infrastructure/docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY public/ /var/www/html/public/
