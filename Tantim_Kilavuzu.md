
# KuryeFullSistem: Akıllı Kurye ve Sipariş Yönetim Platformu

**Sürüm 1.0 | Tanıtım ve Pazarlama Kılavuzu**

---

## 1. KuryeFullSistem Nedir?

KuryeFullSistem, restoran, market, kafe gibi kendi kurye ağıyla paket servisi yapan işletmeler için geliştirilmiş, uçtan uca bir sipariş, kurye ve operasyon yönetimi yazılımıdır. Manuel süreçleri dijitalleştirir, verimliliği artırır ve müşteri memnuniyetini en üst düzeye çıkarır.

Web tabanlı yönetici paneli ve kuryeler için özel mobil uygulaması ile tüm teslimat sürecini tek bir noktadan kontrol etmenizi sağlar.

`[Görsel: Farklı cihazlarda (Masaüstü, Tablet, Telefon) çalışan platformun kolaj görüntüsü]`

---

## 2. Temel Özellikler ve Modüller

KuryeFullSistem, işletmenizin ihtiyaçlarına göre şekillendirilmiş üç ana modülden oluşur:

### a) Yönetici (Admin) Paneli
Operasyonun kalbi olan bu panelden tüm süreci yönetebilirsiniz.

*   **Canlı Harita Takibi:** Tüm kuryelerinizi anlık olarak harita üzerinde izleyin, konum geçmişlerini görüntüleyin.
*   **Sipariş Yönetimi:** Gelen tüm siparişleri tek ekranda görün, manuel sipariş oluşturun ve kurye ataması yapın.
*   **Kurye Yönetimi:** Kuryelerinizi sisteme ekleyin, performanslarını takip edin, hesaplarını yönetin.
*   **Mekan (Restoran/Şube) Yönetimi:** Sisteme bağlı şubelerinizi veya restoranlarınızı yönetin.
*   **Detaylı Raporlama:** Finansal raporlar, sipariş yoğunluk haritaları, kurye performans raporları gibi birçok veriyle işinizi analiz edin.
*   **Otomatik Atama:** Gelen siparişi mekana veya adrese en yakın, uygun durumdaki kuryeye otomatik olarak atayın.

`[Görsel: Yönetici Paneli Ana Ekranı - Canlı harita ve sipariş listesi bir arada]`

### b) Mekan (Restoran/Şube) Paneli
Şubelerinizin veya anlaşmalı restoranlarınızın kullandığı, daha sadeleştirilmiş arayüzdür.

*   **Yeni Sipariş Oluşturma:** Telefonla veya farklı kanallardan gelen siparişleri sisteme hızla girin.
*   **Sipariş Takibi:** Kendi oluşturdukları siparişlerin durumunu (kurye atandı, yolda, teslim edildi vb.) anlık olarak takip edin.
*   **Raporlar:** Kendi sipariş ve ciro raporlarına erişim sağlayın.

`[Görsel: Mekan Paneli - Yeni sipariş oluşturma formu]`

### c) Kurye Mobil Uygulaması (Flutter ile Android & iOS)
Kuryelerinizin sahadaki en büyük yardımcısıdır.

*   **Yeni Sipariş Bildirimleri:** Anlık bildirimlerle yeni siparişlerden anında haberdar olun.
*   **Sipariş Kabul/Red:** Gelen siparişleri kabul etme veya reddetme.
*   **Durum Güncelleme:** Siparişi teslim alma, yola çıkma, teslim etme gibi adımları tek dokunuşla güncelleyin.
*   **Navigasyon Entegrasyonu:** Teslimat adresine Google Haritalar veya Yandex Navigasyon ile kolayca rota oluşturun.
*   **Kazanç Takibi:** Tamamladığı siparişlerden elde ettiği kazancı anlık olarak görüntüleyin.
*   **Çalışma Durumu:** "Müsait" veya "Meşgul" olarak durumunu kolayca değiştirin.

`[Görsel: Kurye mobil uygulamasının ana ekranı ve sipariş detay ekranından görüntüler]`

---

## 3. Entegrasyonlar: Gücünüze Güç Katın!

KuryeFullSistem, popüler yemek ve market sipariş platformları ile tam entegre çalışarak tüm siparişlerinizi tek bir kanalda toplar. Bu sayede her platform için ayrı bir ekran takip etme derdiniz son bulur.

`[Görsel: Yemeksepeti, Trendyol Yemek, Migros Yemek ve Getir logolarının KuryeFullSistem logosu ile birleştiği bir görsel]`

*   **Yemeksepeti Entegrasyonu:** Yemeksepeti'nden gelen siparişleriniz otomatik olarak KuryeFullSistem'e düşer. Sipariş bilgileri, adresi ve tutarı ile hazır bir şekilde kurye ataması bekler.
*   **Trendyol Yemek Entegrasyonu:** Trendyol Yemek siparişleriniz anında panelinizde! Otomatik kurye atama algoritmamız ile en yakındaki kuryeniz saniyeler içinde siparişi üstlenir.
*   **Migros Yemek Entegrasyonu:** Migros Yemek'ten gelen siparişler de aynı şekilde sisteminize aktarılır ve teslimat süreci başlar.
*   **Getir Entegrasyonu:** Getir'in size yönlendirdiği siparişleri de sistem üzerinden yönetin, tüm operasyonunuzu tek bir çatı altında birleştirin.

**Nasıl Çalışır?**
Entegrasyon sayesinde bu platformlardan gelen siparişler, sistem tarafından otomatik olarak alınır ve "Yeni Siparişler" ekranına düşer. Yönetici onayıyla veya tam otomatik modda, sipariş en uygun kuryeye anında atanır.

---

## 4. Sistem Nasıl Çalışır? (Adım Adım Sipariş Akışı)

1.  **Sipariş Oluşur:** Sipariş, entegre platformlardan (Yemeksepeti vb.) veya manuel olarak Mekan/Admin panelinden sisteme girilir.
2.  **Kurye Atanır:** Sistem, adrese en yakın ve müsait durumdaki kuryeyi otomatik olarak belirler ve siparişi atar. (Manuel atama da mümkündür).
3.  **Kurye Bilgilendirilir:** Kuryenin mobil uygulamasına anlık bildirim ve sesli uyarı ile yeni sipariş düşer.
4.  **Kurye Harekete Geçer:** Kurye siparişi kabul eder, mekana gidip siparişi teslim alır ve durumu günceller.
5.  **Canlı Takip Başlar:** Müşteri (opsiyonel) ve yönetici, kuryenin konumunu haritadan canlı olarak izleyebilir.
6.  **Teslimat Tamamlanır:** Kurye siparişi müşteriye teslim eder ve uygulama üzerinden "Teslim Edildi" olarak işaretler.
7.  **Raporlama:** Siparişin tüm detayları (teslimat süresi, kurye bilgisi, tutar vb.) raporlanmak üzere sisteme kaydedilir.

---

## 5. Neden KuryeFullSistem?

*   **Verimlilik:** Kurye atama ve takip süreçlerini otomatikleştirerek zamandan tasarruf edin.
*   **Maliyet Kontrolü:** Kurye performansını ve yakıt/mesafe verimliliğini analiz ederek maliyetlerinizi düşürün.
*   **Müşteri Memnuniyeti:** Anlık takip ve hızlı teslimat ile müşterilerinize daha iyi bir deneyim sunun.
*   **Tam Kontrol:** Tüm operasyonunuzu tek bir ekrandan yönetmenin rahatlığını yaşayın.
*   **Ölçeklenebilirlik:** İşletmeniz büyüdükçe artan sipariş ve kurye sayısını kolayca yönetin.

**KuryeFullSistem ile teslimat operasyonunuzu geleceğe taşıyın!**

Daha fazla bilgi ve demo talebi için bizimle iletişime geçin.
