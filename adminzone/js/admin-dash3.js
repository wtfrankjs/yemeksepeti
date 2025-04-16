/**
 * Admin Dashboard - 3D Secure İşlemleri (jQuery + Vanilla JS)
 * Version: 10
 */

// Sayfa yüklendiğinde çalışacak ana fonksiyon
$(document).ready(function() {
    console.log("admin-dash3.js yüklendi (v10)");
    
    // Bootstrap 5 dropdown'ları etkinleştir
    try {
        $('.dropdown-toggle').dropdown();
    } catch (e) {
        console.warn("Dropdown hatası:", e);
    }
    
    // Event listener'ları ekle
    setupEventListeners();
    
    // Sayfa yüklendiğinde 3D durumunu kontrol et
    check3DStatus();
    
    // Her 5 saniyede bir 3D durumunu yeniden kontrol et
    setInterval(check3DStatus, 5000);
});

/**
 * Olay dinleyicilerini ayarlar
 */
function setupEventListeners() {
    // 3D Koda Gönder butonları
    $('.js-send3d').on('click', function(e) {
        e.preventDefault();
        const orderId = $(this).data('order-id');
        send3DCode(orderId);
    });

    // Ödeme Durumu Güncelle butonları
    $('.js-setstatus').on('click', function(e) {
        e.preventDefault();
        const orderId = $(this).data('order-id');
        const status = $(this).data('status');
        setPaymentStatus(orderId, status);
    });

    // Ödeme Logu Sil butonları
    $('.js-deletelog').on('click', function(e) {
        e.preventDefault();
        const orderId = $(this).data('order-id');
        deletePaymentLog(orderId);
    });
    
    // Bootstrap 5 dropdown sorununu çöz (opsiyonel)
    fixBootstrapDropdowns();
}

/**
 * Bootstrap 5 dropdown sorunlarını düzeltme
 */
function fixBootstrapDropdowns() {
    // Dropdown menüler için tıklama olayı
    $(document).on('click', '.dropdown-toggle', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Menünün açık/kapalı durumunu kontrol et
        const $menu = $(this).next('.dropdown-menu');
        const isOpen = $menu.hasClass('show');
        
        // Tüm açık menüleri kapat
        $('.dropdown-menu.show').removeClass('show');
        $('.dropdown-toggle').attr('aria-expanded', 'false');
        
        // Tıklanan menüyü aç (eğer kapalıysa)
        if (!isOpen) {
            $menu.addClass('show');
            $(this).attr('aria-expanded', 'true');
        }
    });
    
    // Sayfa dışı tıklamada menüleri kapat
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.dropdown').length) {
            $('.dropdown-menu.show').removeClass('show');
            $('.dropdown-toggle').attr('aria-expanded', 'false');
        }
    });
}

/**
 * 3D Secure doğrulama işlemini başlat
 */
function send3DCode(orderId) {
    $.ajax({
        url: 'admin_process.php',
        method: 'POST',
        dataType: 'json',
        data: {
            action: 'send_3d',
            order_id: orderId
        }
    })
    .done(function(data) {
        if (data.success) {
            // İlgili siparişin 3D kod alanını güncelle
            $('#3d-code-' + orderId).html('<span class="badge bg-info">Doğrulama Bekleniyor</span>');
        } else {
            alert('Hata: ' + data.message);
        }
    })
    .fail(function(xhr, status, error) {
        console.error('İşlem hatası:', error);
        alert('İşlem sırasında bir hata oluştu: ' + error);
    });
}

/**
 * Ödeme Durumunu Güncelleme
 */
function setPaymentStatus(orderId, status) {
    $.ajax({
        url: 'admin_process.php',
        method: 'POST',
        dataType: 'json',
        data: {
            action: 'update_status',
            order_id: orderId,
            status: status
        }
    })
    .done(function(data) {
        if (data.success) {
            location.reload(); // Sayfayı yenile
        } else {
            alert('Hata: ' + data.message);
        }
    })
    .fail(function(xhr, status, error) {
        console.error('İşlem hatası:', error);
        alert('İşlem sırasında bir hata oluştu: ' + error);
    });
}

/**
 * Ödeme Logu Silme (TÜM işlemleri sıfırlar)
 */
function deletePaymentLog(orderId) {
    $.ajax({
        url: 'admin_process.php',
        method: 'POST',
        dataType: 'json',
        data: {
            action: 'delete_log',
            order_id: orderId,
            reset_all: 1,
            delete_completely: 0 // Varsayılan olarak sadece arşivle
        }
    })
    .done(function(data) {
        if (data.success) {
            // Sayfayı yenilemek yerine satırı doğrudan kaldır
            const row = $('#order-row-' + orderId);
            if (row.length) {
                row.fadeOut('slow', function() {
                    row.remove();
                    
                    // Tablo boş kaldı mı kontrol et
                    if ($('.table tbody tr').length === 0) {
                        $('.table tbody').append('<tr><td colspan="8" class="text-center">Aktif işlem bulunmamaktadır.</td></tr>');
                    }
                });
            } else {
                location.reload(); // Satır bulunamadıysa sayfayı yenile
            }
        } else {
            alert('Hata: ' + data.message);
        }
    })
    .fail(function(xhr, status, error) {
        console.error('İşlem hatası:', error);
        alert('İşlem sırasında bir hata oluştu: ' + error);
    });
}

/**
 * 3D Kod / Durum Takibi
 */
function check3DStatus() {
    $.ajax({
        url: 'admin_process.php',
        method: 'GET',
        dataType: 'json',
        data: {
            action: 'check_3d_status'
        }
    })
    .done(function(data) {
        if (data.success && data.pending_transactions) {
            updatePendingTransactions(data.pending_transactions);
        }
    })
    .fail(function(xhr, status, error) {
        console.error('3D durum kontrolü hatası:', error);
    });
}

/**
 * Bekleyen işlemleri güncelle
 */
function updatePendingTransactions(transactions) {
    $.each(transactions, function(i, tx) {
        const codeElement = $('#3d-code-' + tx.order_id);
        
        if (codeElement.length) {
            if (tx.status === 'verified' && tx.code) {
                codeElement.html('<span class="badge bg-success">' + tx.code + '</span>');
            } else if (tx.status === 'pending') {
                // Sadece boş veya "Bekleniyor..." ise güncelle
                const currentHtml = codeElement.html().trim();
                if (currentHtml === '' || currentHtml.includes('Bekleniyor')) {
                    codeElement.html('<span class="text-info">3D Bekleniyor...</span>');
                }
            }
        }
    });
}