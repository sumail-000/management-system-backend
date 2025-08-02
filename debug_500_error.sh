#!/bin/bash

# Debug script for 500 error on Hostinger
echo "=== Laravel 500 Error Debug Script ==="
echo "Date: $(date)"
echo ""

# 1. Check Laravel logs
echo "1. Checking Laravel logs..."
echo "----------------------------------------"
tail -20 ~/domains/yournutritionsy.com/laravel_backend/storage/logs/laravel.log
echo ""

# 2. Check PHP error logs
echo "2. Checking PHP error logs..."
echo "----------------------------------------"
tail -20 ~/domains/yournutritionsy.com/public_html/error_log 2>/dev/null || echo "No PHP error log found"
echo ""

# 3. Test Laravel bootstrap
echo "3. Testing Laravel bootstrap..."
echo "----------------------------------------"
cd ~/domains/yournutritionsy.com/public_html/api
php -r "try { require '../../../laravel_backend/bootstrap/app.php'; echo 'Bootstrap: OK\n'; } catch (Exception \$e) { echo 'Bootstrap Error: ' . \$e->getMessage() . '\n'; }"
echo ""

# 4. Check .env file
echo "4. Checking .env file..."
echo "----------------------------------------"
if [ -f ~/domains/yournutritionsy.com/laravel_backend/.env ]; then
    echo ".env file exists"
    echo "APP_ENV: $(grep '^APP_ENV=' ~/domains/yournutritionsy.com/laravel_backend/.env || echo 'Not set')"
    echo "APP_DEBUG: $(grep '^APP_DEBUG=' ~/domains/yournutritionsy.com/laravel_backend/.env || echo 'Not set')"
    echo "APP_KEY: $(grep '^APP_KEY=' ~/domains/yournutritionsy.com/laravel_backend/.env | cut -c1-20)..."
    echo "DB_CONNECTION: $(grep '^DB_CONNECTION=' ~/domains/yournutritionsy.com/laravel_backend/.env || echo 'Not set')"
else
    echo ".env file NOT FOUND!"
fi
echo ""

# 5. Check database connection
echo "5. Testing database connection..."
echo "----------------------------------------"
cd ~/domains/yournutritionsy.com/laravel_backend
php artisan tinker --execute="try { DB::connection()->getPdo(); echo 'Database: Connected\n'; } catch (Exception \$e) { echo 'Database Error: ' . \$e->getMessage() . '\n'; }"
echo ""

# 6. Check file permissions
echo "6. Checking file permissions..."
echo "----------------------------------------"
echo "API directory permissions:"
ls -la ~/domains/yournutritionsy.com/public_html/api/
echo ""
echo "Storage directory permissions:"
ls -la ~/domains/yournutritionsy.com/laravel_backend/storage/
echo ""
echo "Bootstrap cache permissions:"
ls -la ~/domains/yournutritionsy.com/laravel_backend/bootstrap/cache/ 2>/dev/null || echo "Bootstrap cache directory not found"
echo ""

# 7. Test specific API endpoint with detailed error
echo "7. Testing API endpoint with error details..."
echo "----------------------------------------"
echo "Testing /api/membership-plans:"
curl -v -H "Accept: application/json" -H "Content-Type: application/json" https://yournutritionsy.com/api/membership-plans 2>&1 | grep -E "HTTP|content-type|error"
echo ""

# 8. Check Laravel routes
echo "8. Checking Laravel routes..."
echo "----------------------------------------"
cd ~/domains/yournutritionsy.com/laravel_backend
php artisan route:list | grep -i "membership" || echo "No membership routes found"
echo ""

# 9. Check composer autoload
echo "9. Checking composer autoload..."
echo "----------------------------------------"
cd ~/domains/yournutritionsy.com/laravel_backend
composer dump-autoload --optimize 2>&1 || echo "Composer command failed"
echo ""

# 10. Generate Laravel key if missing
echo "10. Checking/generating Laravel key..."
echo "----------------------------------------"
cd ~/domains/yournutritionsy.com/laravel_backend
php artisan key:generate --show 2>&1 || echo "Key generation failed"
echo ""

echo "=== Debug Complete ==="
echo "Please review the output above to identify the 500 error cause."
echo "Common issues:"
echo "- Missing .env file or APP_KEY"
echo "- Database connection problems"
echo "- File permission issues"
echo "- Missing composer dependencies"
echo "- Laravel cache issues"*/