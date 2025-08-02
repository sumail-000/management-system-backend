# PowerShell Debug script for 500 error on Hostinger
Write-Host "=== Laravel 500 Error Debug Script ===" -ForegroundColor Green
Write-Host "Date: $(Get-Date)" -ForegroundColor Yellow
Write-Host ""

# Commands to run on the server via SSH
$commands = @"
# 1. Check Laravel logs
echo "1. Checking Laravel logs..."
echo "----------------------------------------"
tail -20 ~/domains/yournutritionsy.com/laravel_backend/storage/logs/laravel.log
echo ""

# 2. Check if .env file exists and key settings
echo "2. Checking .env file..."
echo "----------------------------------------"
if [ -f ~/domains/yournutritionsy.com/laravel_backend/.env ]; then
    echo ".env file exists"
    echo "APP_ENV: `$(grep '^APP_ENV=' ~/domains/yournutritionsy.com/laravel_backend/.env || echo 'Not set')`"
    echo "APP_DEBUG: `$(grep '^APP_DEBUG=' ~/domains/yournutritionsy.com/laravel_backend/.env || echo 'Not set')`"
    echo "APP_KEY exists: `$(grep '^APP_KEY=' ~/domains/yournutritionsy.com/laravel_backend/.env | wc -l)`"
    echo "DB_CONNECTION: `$(grep '^DB_CONNECTION=' ~/domains/yournutritionsy.com/laravel_backend/.env || echo 'Not set')`"
else
    echo ".env file NOT FOUND - This is likely the problem!"
fi
echo ""

# 3. Test Laravel bootstrap with error details
echo "3. Testing Laravel bootstrap..."
echo "----------------------------------------"
cd ~/domains/yournutritionsy.com/public_html/api
php -r "error_reporting(E_ALL); ini_set('display_errors', 1); try { require '../../../laravel_backend/bootstrap/app.php'; echo 'Bootstrap: OK\n'; } catch (Exception `$e) { echo 'Bootstrap Error: ' . `$e->getMessage() . '\n'; } catch (Error `$e) { echo 'Fatal Error: ' . `$e->getMessage() . '\n'; }"
echo ""

# 4. Check storage permissions
echo "4. Checking storage permissions..."
echo "----------------------------------------"
ls -la ~/domains/yournutritionsy.com/laravel_backend/storage/
echo "Logs directory:"
ls -la ~/domains/yournutritionsy.com/laravel_backend/storage/logs/
echo ""

# 5. Check bootstrap cache
echo "5. Checking bootstrap cache..."
echo "----------------------------------------"
ls -la ~/domains/yournutritionsy.com/laravel_backend/bootstrap/cache/ 2>/dev/null || echo "Bootstrap cache directory missing"
echo ""

# 6. Test database connection
echo "6. Testing database connection..."
echo "----------------------------------------"
cd ~/domains/yournutritionsy.com/laravel_backend
php -r "require 'vendor/autoload.php'; `$app = require 'bootstrap/app.php'; try { `$pdo = `$app->make('db')->connection()->getPdo(); echo 'Database: Connected\n'; } catch (Exception `$e) { echo 'Database Error: ' . `$e->getMessage() . '\n'; }"
echo ""

# 7. Check if routes are cached
echo "7. Checking route cache..."
echo "----------------------------------------"
cd ~/domains/yournutritionsy.com/laravel_backend
ls -la bootstrap/cache/routes* 2>/dev/null || echo "No route cache found"
echo ""

# 8. Clear all caches
echo "8. Clearing Laravel caches..."
echo "----------------------------------------"
cd ~/domains/yournutritionsy.com/laravel_backend
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear
echo "Caches cleared"
echo ""

# 9. Generate app key if missing
echo "9. Generating app key..."
echo "----------------------------------------"
cd ~/domains/yournutritionsy.com/laravel_backend
php artisan key:generate
echo ""

# 10. Test API again
echo "10. Testing API after fixes..."
echo "----------------------------------------"
curl -s -o /dev/null -w 'HTTP Status: %{http_code}\n' https://yournutritionsy.com/api/membership-plans
echo ""

echo "=== Debug Complete ==="
"@

Write-Host "Copy and paste these commands into your SSH terminal:" -ForegroundColor Cyan
Write-Host $commands

Write-Host ""
Write-Host "=== Quick Fix Commands ===" -ForegroundColor Green
Write-Host "If .env file is missing, run these commands on the server:" -ForegroundColor Yellow
Write-Host ""
Write-Host "cd ~/domains/yournutritionsy.com/laravel_backend" -ForegroundColor White
Write-Host "cp .env.example .env" -ForegroundColor White
Write-Host "php artisan key:generate" -ForegroundColor White
Write-Host "php artisan config:clear" -ForegroundColor White
Write-Host "chmod -R 755 storage bootstrap/cache" -ForegroundColor White
Write-Host ""
Write-Host "Then test the API again with:" -ForegroundColor Yellow
Write-Host "curl https://yournutritionsy.com/api/membership-plans" -ForegroundColor White