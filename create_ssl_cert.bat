@echo off
echo SSL Sertifikası Olusturuluyor...
echo ================================

cd /d "C:\xampp\apache"

echo.
echo 1. SSL dizinlerini olusturuyor...
if not exist "conf\ssl.crt" mkdir "conf\ssl.crt"
if not exist "conf\ssl.key" mkdir "conf\ssl.key"

echo.
echo 2. Self-signed sertifika olusturuluyor...
echo (Sorulari bos birakabilirsiniz, sadece ENTER basin)
echo.

bin\openssl req -new -x509 -days 365 -nodes -out conf\ssl.crt\server.crt -keyout conf\ssl.key\server.key -config conf\openssl.cnf

echo.
echo 3. Sertifika olusturuldu!
echo.
echo Dosyalar:
echo - conf\ssl.crt\server.crt
echo - conf\ssl.key\server.key
echo.
echo Simdi XAMPP Control Panel'den Apache'yi yeniden baslatin.
echo.
pause

