@ECHO OFF
set /p PORT=Masukkan port webserver : 
php\php.exe -c php.ini -S 0.0.0.0:%PORT%