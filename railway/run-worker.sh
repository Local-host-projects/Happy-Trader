#!/bin/bash
set -e
php artisan schedule:work &
php artisan queue:work --sleep=3 --tries=3 --max-time=3600 &
php artisan queue:work --sleep=3 --tries=3 --max-time=3600 &
php artisan queue:work --sleep=3 --tries=3 --max-time=3600 &
wait