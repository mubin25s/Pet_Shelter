@echo off
echo Starting Pet Shelter Backend Server...
echo Access the app at http://localhost:8000 (Backend)
echo Access the frontend via your Live Server
"C:\xampp\php\php.exe" -S localhost:8000 -t "%~dp0."
pause
