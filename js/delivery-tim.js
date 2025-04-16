// delivery-time.js
document.addEventListener('DOMContentLoaded', function() {
    // Teslimat zamanını güncelleyen fonksiyon
    function updateDeliveryTime() {
        // Şu anki tarih ve saati al
        const now = new Date();
        
        // Teslimat başlangıç zamanını hesapla (şu anki saat + 1 saat)
        const deliveryStartTime = new Date(now);
        deliveryStartTime.setHours(now.getHours() + 1);
        
        // Teslimat bitiş zamanını hesapla (başlangıç zamanı + 15 dakika)
        const deliveryEndTime = new Date(deliveryStartTime);
        deliveryEndTime.setHours(now.getHours() + 2);
        
        // Saatleri 24 saat formatında biçimlendir
        const startHour = String(deliveryStartTime.getHours()).padStart(2, '0');
        const startMinute = String(deliveryStartTime.getMinutes()).padStart(2, '0');
        
        const endHour = String(deliveryEndTime.getHours()).padStart(2, '0');
        const endMinute = String(deliveryEndTime.getMinutes()).padStart(2, '0');
        
        // Tarihi Türkçe stilde biçimlendir
        const months = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
        const day = deliveryStartTime.getDate();
        const month = months[deliveryStartTime.getMonth()];
        
        // Teslimat zamanı metnini oluştur
        const deliveryTimeStr = `${day} ${month}, ${startHour}:${startMinute} - ${endHour}:${endMinute}`;
        
        console.log("Teslimat zamanı güncellendi:", deliveryTimeStr);
        
        // delivery-time sınıfına sahip tüm elementleri güncelle
        const deliveryTimeElements = document.querySelectorAll('.delivery-zaman');
        if (deliveryTimeElements.length > 0) {
            deliveryTimeElements.forEach(element => {
                element.textContent = deliveryTimeStr;
            });
            console.log("Teslimat zamanı elementleri güncellendi, bulunan element sayısı:", deliveryTimeElements.length);
        } else {
            console.error("Teslimat zamanı elementleri bulunamadı!");
        }
    }
    
    // Teslimat zamanını hemen güncelle
    updateDeliveryTime();
    
    // Teslimat zamanını her dakika güncelle
    setInterval(updateDeliveryTime, 60000);
});