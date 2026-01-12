#!/bin/bash
cd /home/ce528895/public_html
/usr/bin/php artisan schedule:run >> /dev/null 2>&1
