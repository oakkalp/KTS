@echo off
echo XAMPP HTTPS Kurulum Scripti
echo ============================

echo.
echo 1. Apache durduruldu mu? (XAMPP Control Panel'den Stop yapın)
pause

echo.
echo 2. httpd.conf dosyasını açıyor...
notepad "C:\xampp\apache\conf\httpd.conf"

echo.
echo httpd.conf dosyasında şu satırları bulun ve başındaki # işaretini kaldırın:
echo.
echo #LoadModule ssl_module modules/mod_ssl.so
echo #Include conf/extra/httpd-ssl.conf
echo.
echo Bu satırlar şöyle olmalı:
echo LoadModule ssl_module modules/mod_ssl.so
echo Include conf/extra/httpd-ssl.conf
echo.
pause

echo.
echo 3. httpd-ssl.conf dosyasını açıyor...
notepad "C:\xampp\apache\conf\extra\httpd-ssl.conf"

echo.
echo httpd-ssl.conf dosyasında şu satırları kontrol edin:
echo.
echo Listen 443 ssl
echo ServerName localhost:443
echo DocumentRoot "C:/xampp/htdocs"
echo.
pause

echo.
echo 4. Şimdi XAMPP Control Panel'den Apache'yi Start yapın
echo 5. https://localhost/kuryefullsistem/ adresini test edin
echo 6. https://192.168.1.137/kuryefullsistem/ adresini test edin
echo.
echo SSL sertifika uyarısı çıkarsa "Gelişmiş" > "Devam et" deyin
echo.
pause

