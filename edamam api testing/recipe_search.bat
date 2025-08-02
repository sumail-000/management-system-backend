@echo off
REM Batch file to run PHP Recipe Search Script
REM Usage: recipe_search.bat "search query"
REM Example: recipe_search.bat "chicken curry"

if "%~1"=="" (
    echo.
    echo ❌ Error: Please provide a search query
    echo Usage: recipe_search.bat "Search Query"
    echo Example: recipe_search.bat "chicken curry"
    echo.
    pause
    exit /b 1
)

php recipe_search.php "%~1" %2
pause