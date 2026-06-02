web: php artisan serve --host=0.0.0.0 --port=$PORT
worker: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
scheduler: while true; do php artisan schedule:run; sleep 60; done