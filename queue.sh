#!/bin/bash
cd /home/ce528895/public_html
/usr/bin/php artisan queue:work --once --sleep=3 --tries=3 --timeout=120 >> /dev/null 2>&1
