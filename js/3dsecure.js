document.addEventListener('DOMContentLoaded', function() {
        // Kod alanına odaklan
        document.getElementById('code').focus();
        
        // Timer fonksiyonu: 3 dakika
        let timeLeft = 180;
        const timerElement = document.getElementById('timer');
        
        const countdownTimer = setInterval(function() {
            const minutes = Math.floor(timeLeft / 60);
            let seconds = timeLeft % 60;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            timerElement.innerHTML = minutes + ':' + seconds;
            
            if (timeLeft <= 0) {
                clearInterval(countdownTimer);
                timerElement.innerHTML = '00:00';
                document.getElementById('verificationForm').innerHTML = '<div class="alert alert-danger">Süre doldu. Lütfen sayfayı yenileyerek tekrar deneyiniz.</div>';
            }
            
            timeLeft--;
        }, 1000);
        
        // Sadece rakam girişini sağla
        document.getElementById('code').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // 6 hane girildiğinde otomatik submit
            if (this.value.length === 6) {
                setTimeout(function() {
                    document.getElementById('verificationForm').submit();
                }, 500);
            }
        });
        
        // Form doğrulaması: 6 hane kontrolü
        document.getElementById('verificationForm').addEventListener('submit', function(e) {
            const codeInput = document.getElementById('code');
            if (codeInput.value.length !== 6 || !/^\d+$/.test(codeInput.value)) {
                e.preventDefault();
                codeInput.focus();
                
                if (!document.querySelector('.error-message')) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> Lütfen 6 haneli doğrulama kodunu giriniz.';
                    codeInput.parentNode.appendChild(errorDiv);
                }
            }
        });
    });