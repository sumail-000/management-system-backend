# Hostinger Setup Guide for Laravel + React

## Current Issue
The 500 error occurs because the API subdirectory setup needs the correct file paths and configuration for Hostinger's shared hosting structure.

## Required Server Structure
```
/home/u508498272/domains/yournutritionsy.com/
│
├── laravel_backend/                    ← Laravel backend (API)
│   ├── app/
│   ├── routes/api.php
│   ├── vendor/
│   ├── bootstrap/
│   ├── storage/
│   └── public/index.php (original)
│
└── public_html/                        ← Web accessible directory
    ├── index.html                      ← React frontend
    ├── assets/                         ← React build files
    └── api/                            ← Laravel API endpoint
        ├── index.php                   ← Modified for subdirectory
        └── .htaccess                   ← API routing rules
```

## Step-by-Step Fix

### 1. Upload Files to Server

**Upload the corrected `index.php` to `/public_html/api/`:**
- Take the file `api_index.php` from this project
- Rename it to `index.php`
- Upload to: `/home/u508498272/domains/yournutritionsy.com/public_html/api/index.php`

**Upload the `.htaccess` file to `/public_html/api/`:**
- Take the file `api_htaccess.txt` from this project
- Rename it to `.htaccess`
- Upload to: `/home/u508498272/domains/yournutritionsy.com/public_html/api/.htaccess`

### 2. Set Correct Permissions

Run these commands on your server:

```bash
# Set storage permissions
chmod -R 755 ~/domains/yournutritionsy.com/laravel_backend/storage
chmod -R 755 ~/domains/yournutritionsy.com/laravel_backend/bootstrap/cache

# Set API directory permissions
chmod -R 755 ~/domains/yournutritionsy.com/public_html/api
```

### 3. Verify File Structure

Ensure your server has this exact structure:

```
public_html/api/index.php    ← Contains the corrected paths
public_html/api/.htaccess    ← Contains Laravel routing rules
```

### 4. Test the Setup

**Test API directly:**
```
https://yournutritionsy.com/api/membership-plans
```

**Test main website:**
```
https://yournutritionsy.com
```

## Key Changes Made

### In `api/index.php`:
```php
// OLD (incorrect paths):
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

// NEW (correct paths for subdirectory):
require __DIR__.'/../../../laravel_backend/vendor/autoload.php';
$app = require_once __DIR__.'/../../../laravel_backend/bootstrap/app.php';
```

### In `api/.htaccess`:
- Added proper Laravel routing
- Added CORS headers for API access
- Added security headers
- Added OPTIONS request handling

## Expected Results

After implementing these changes:

✅ **Website loads:** `https://yournutritionsy.com` shows React frontend
✅ **API works:** `https://yournutritionsy.com/api/membership-plans` returns JSON
✅ **No 500 errors:** Both frontend and API function properly
✅ **CORS resolved:** Frontend can communicate with API

## Troubleshooting

If you still get 500 errors:

1. **Check file permissions:**
   ```bash
   ls -la ~/domains/yournutritionsy.com/public_html/api/
   ```

2. **Check Laravel logs:**
   ```bash
   tail -f ~/domains/yournutritionsy.com/laravel_backend/storage/logs/laravel.log
   ```

3. **Verify paths in index.php:**
   ```bash
   cat ~/domains/yournutritionsy.com/public_html/api/index.php
   ```

4. **Test Laravel bootstrap:**
   ```bash
   cd ~/domains/yournutritionsy.com/public_html/api
   php -r "require '../../../laravel_backend/vendor/autoload.php'; echo 'Autoload OK\n';"
   ```

## Security Notes

- The Laravel backend directory is outside `public_html` (secure)
- Only the API endpoint is exposed through `public_html/api/`
- Sensitive files (.env, vendor/, etc.) are not web-accessible
- CORS is configured to only allow your domain

## Next Steps

1. Upload the corrected files to your server
2. Set the proper permissions
3. Test both the website and API
4. Monitor Laravel logs for any remaining issues

The 500 error should be completely resolved after these changes.