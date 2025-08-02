@echo off
REM Batch file to run the PHP ingredient extractor
REM Usage: get_ingredients.bat "Recipe Name"
REM Example: get_ingredients.bat "Chicken Biryani"

if "%~1"=="" (
    echo.
    echo ❌ Error: Please provide a recipe name
    echo Usage: get_ingredients.bat "Recipe Name"
    echo Example: get_ingredients.bat "Chicken Biryani"
    echo.
    pause
    exit /b 1
)

REM Check if PHP is available
php --version >nul 2>&1
if errorlevel 1 (
    echo.
    echo ❌ Error: PHP is not installed or not in PATH
    echo Please install PHP and add it to your system PATH
    echo.
    pause
    exit /b 1
)

REM Run the PHP script
php "%~dp0get_ingredients.php" "%~1"

if errorlevel 1 (
    echo.
    echo ❌ Script execution failed
    pause
)

echo.
echo ✅ Script completed successfully
pause