# XAMPP HTTPS Kurulumu

## 1. XAMPP Control Panel'i Açın

## 2. Apache Config > httpd-ssl.conf
```
# Bu satırı bulun ve düzenleyin:
Listen 443 ssl
ServerName localhost:443
DocumentRoot "C:/xampp/htdocs"
```

## 3. httpd.conf dosyasında aktif edin:
```
# Bu satırın başındaki # işaretini kaldırın:
Include conf/extra/httpd-ssl.conf
LoadModule ssl_module modules/mod_ssl.so
```

## 4. Apache'yi yeniden başlatın

## 5. https://192.168.1.137/kuryefullsistem/ adresini kullanın

## Alternatif: ngrok kullanın (Daha kolay)
```
1. ngrok.com'dan indirin
2. ngrok http 80
3. Verilen https linkini kullanın
```

