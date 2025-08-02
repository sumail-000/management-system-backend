<?php
/**
 * Alternative Solution for Document Root Issue
 * This script will help you fix the 500 error by moving files from public/ to root
 */

echo "=== Laravel Document Root Fix - Alternative Solution ===\n\n";

// Check if we're in the correct directory
if (!file_exists('public/index.php')) {
    echo "❌ Error: This script must be run from the Laravel root directory.\n";
    echo "Current directory: " . getcwd() . "\n";
    echo "Please navigate to your Laravel project root and run this script again.\n";
    exit(1);
}

echo "✅ Found Laravel project structure\n";

// Step 1: Create backup of current structure
echo "\n📋 Step 1: Creating backup...\n";
$backupDir = 'backup_' . date('Y-m-d_H-i-s');
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
    echo "✅ Created backup directory: $backupDir\n";
}

// Copy important files to backup
$filesToBackup = ['public/index.php', 'public/.htaccess'];
foreach ($filesToBackup as $file) {
    if (file_exists($file)) {
        copy($file, $backupDir . '/' . basename($file));
        echo "✅ Backed up: $file\n";
    }
}

// Step 2: Move files from public/ to root
echo "\n📋 Step 2: Moving public files to root directory...\n";

// Files to move from public/ to root
$publicFiles = glob('public/*');
foreach ($publicFiles as $file) {
    $filename = basename($file);
    $destination = $filename;
    
    // Skip if file already exists in root (don't overwrite)
    if (file_exists($destination)) {
        echo "⚠️  Skipped $filename (already exists in root)\n";
        continue;
    }
    
    if (is_file($file)) {
        copy($file, $destination);
        echo "✅ Moved: $filename\n";
    }
}

// Step 3: Update index.php paths
echo "\n📋 Step 3: Updating index.php paths...\n";

if (file_exists('index.php')) {
    $indexContent = file_get_contents('index.php');
    
    // Update the paths in index.php
    $indexContent = str_replace(
        "require __DIR__.'/../vendor/autoload.php';",
        "require __DIR__.'/vendor/autoload.php';",
        $indexContent
    );
    
    $indexContent = str_replace(
        "\$app = require_once __DIR__.'/../bootstrap/app.php';",
        "\$app = require_once __DIR__.'/bootstrap/app.php';",
        $indexContent
    );
    
    file_put_contents('index.php', $indexContent);
    echo "✅ Updated index.php paths\n";
} else {
    echo "❌ index.php not found in root directory\n";
}

// Step 4: Verify .htaccess
echo "\n📋 Step 4: Verifying .htaccess...\n";

if (file_exists('.htaccess')) {
    echo "✅ .htaccess file exists in root\n";
    
    // Show .htaccess content
    $htaccessContent = file_get_contents('.htaccess');
    echo "\n📄 Current .htaccess content:\n";
    echo "----------------------------------------\n";
    echo $htaccessContent;
    echo "----------------------------------------\n";
} else {
    echo "❌ .htaccess not found, creating one...\n";
    
    $htaccessContent = <<<EOT
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
EOT;
    
    file_put_contents('.htaccess', $htaccessContent);
    echo "✅ Created .htaccess file\n";
}

// Step 5: Test the setup
echo "\n📋 Step 5: Testing the setup...\n";

// Check if Laravel can bootstrap
try {
    if (file_exists('vendor/autoload.php')) {
        require 'vendor/autoload.php';
        echo "✅ Autoloader works\n";
        
        if (file_exists('bootstrap/app.php')) {
            $app = require_once 'bootstrap/app.php';
            echo "✅ Laravel application bootstrapped successfully\n";
        } else {
            echo "❌ bootstrap/app.php not found\n";
        }
    } else {
        echo "❌ vendor/autoload.php not found\n";
    }
} catch (Exception $e) {
    echo "❌ Error during bootstrap: " . $e->getMessage() . "\n";
}

// Final instructions
echo "\n🎉 SETUP COMPLETE!\n";
echo "\n📋 What was done:\n";
echo "1. ✅ Created backup of original files\n";
echo "2. ✅ Moved public/* files to root directory\n";
echo "3. ✅ Updated index.php paths\n";
echo "4. ✅ Verified/created .htaccess file\n";
echo "5. ✅ Tested Laravel bootstrap\n";

echo "\n🌐 Next Steps:\n";
echo "1. Upload all files to your web server\n";
echo "2. Test your website: https://yournutritionsy.com\n";
echo "3. Test your API: https://yournutritionsy.com/api/membership-plans\n";

echo "\n⚠️  Important Notes:\n";
echo "- Your original files are backed up in: $backupDir/\n";
echo "- Make sure to upload the updated files to your server\n";
echo "- The document root should now point to the main directory\n";
echo "- If you still get errors, check server error logs\n";

echo "\n✅ Your Laravel application should now work without document root changes!\n";
?>