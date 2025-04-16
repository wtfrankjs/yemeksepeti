document.addEventListener('DOMContentLoaded', function() {
    // Sepet verilerini local storage'dan al
    function getCart() {
        const cartData = localStorage.getItem('yemeksepeti_cart');
        return cartData ? JSON.parse(cartData) : { items: [], subtotal: 0, discount: 0, deliveryFee: 14.90, total: 0 };
    }

    // Para formatı
    function formatMoney(amount) {
        return amount.toFixed(2).replace('.', ',') + ' TL';
    }

    // Sepet verilerini form'a ekle
    function updateCartData() {
        const cart = getCart();
        const cartDataInput = document.getElementById('cartData');
        if (cartDataInput) {
            cartDataInput.value = JSON.stringify(cart);
            console.log("Sepet verileri forma eklendi.");
        } else {
            console.log("cartData input elementı bulunamadı!");
        }
        return cart;
    }

    // Sipariş özeti
    function updateOrderSummary() {
        const cart = updateCartData();
        
        // Boş sepet kontrolü
        if (!cart.items || cart.items.length === 0) {
            const orderSummaryItems = document.getElementById('orderSummaryItems');
            if (orderSummaryItems) {
                orderSummaryItems.innerHTML = '<div class="alert alert-warning">Sepetinizde ürün bulunmamaktadır.</div>';
                
                const completeOrderBtn = document.getElementById('completeOrderBtn');
                if (completeOrderBtn) {
                    completeOrderBtn.disabled = true;
                }
            }
            return;
        }
        
        // Ürünleri listele
        const orderSummaryItems = document.getElementById('orderSummaryItems');
        if (orderSummaryItems) {
            let itemsHtml = '';
            cart.items.forEach(item => {
                itemsHtml += `
                <div class="checkout-item d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <img src="${item.image || 'images/default.jpg'}" alt="${item.name}" class="checkout-item-image">
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0">${item.name}</h6>
                        <small class="text-muted">${formatMoney(item.price)} x ${item.quantity}</small>
                    </div>
                    <div class="ms-3 fw-bold">
                        ${formatMoney(item.total)}
                    </div>
                </div>`;
            });
            
            orderSummaryItems.innerHTML = itemsHtml;
        }
        
        // Özet bilgileri güncelle
        const summarySubtotal = document.getElementById('summarySubtotal');
        if (summarySubtotal) summarySubtotal.textContent = formatMoney(cart.subtotal);
        
        const summaryDeliveryFee = document.getElementById('summaryDeliveryFee');
        if (summaryDeliveryFee) summaryDeliveryFee.textContent = formatMoney(cart.deliveryFee);
        
        const summaryDiscount = document.getElementById('summaryDiscount');
        if (summaryDiscount) summaryDiscount.textContent = formatMoney(cart.discount);
        
        // Bahşiş kontrolü
        const tipAmount = document.getElementById('tipAmount');
        if (tipAmount) {
            const tipAmountValue = parseFloat(tipAmount.value);
            const tipRow = document.querySelector('.tip-row');
            const summaryTip = document.getElementById('summaryTip');
            
            if (tipAmountValue > 0) {
                if (summaryTip) summaryTip.textContent = formatMoney(tipAmountValue);
                if (tipRow) {
                    tipRow.style.display = 'flex';
                }
                cart.total += tipAmountValue;
            } else {
                if (tipRow) {
                    tipRow.style.display = 'none';
                }
            }
        }
        
        const summaryTotal = document.getElementById('summaryTotal');
        if (summaryTotal) summaryTotal.textContent = formatMoney(cart.total);
    }

    // Bahşiş butonları
    const tipButtons = document.querySelectorAll('.tip-button');
    if (tipButtons.length > 0) {
        tipButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Aktif sınıfı tüm butonlardan kaldır
                tipButtons.forEach(btn => btn.classList.remove('active'));
                
                // Tıklanan butona aktif sınıfı ekle
                this.classList.add('active');
                
                // Bahşiş miktarını güncelle
                const amount = parseFloat(this.getAttribute('data-amount'));
                const tipAmount = document.getElementById('tipAmount');
                if (tipAmount) {
                    tipAmount.value = amount;
                }
                
                // Özeti güncelle
                updateOrderSummary();
            });
        });
    }

    // SEPETTE350 kuponu uygulama
    const applyCouponBtn = document.getElementById('applySEPETTE350');
    if (applyCouponBtn) {
        applyCouponBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const couponCode = document.getElementById('couponCode');
            if (couponCode) {
                couponCode.value = 'SEPETTE350';
                
                const applyButton = document.querySelector('button[name="apply_coupon"]');
                if (applyButton) {
                    applyButton.click();
                }
            }
        });
    }

    // Kart numarası formatı
    const cardNumberInput = document.getElementById('cardNumber');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            let formatted = '';
            
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formatted += ' ';
                }
                formatted += value[i];
            }
            
            e.target.value = formatted.slice(0, 19);
        });
    }

    // Son kullanma tarihi formatı
    const expiryDateInput = document.getElementById('expiryDate');
    if (expiryDateInput) {
        expiryDateInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length > 2) {
                value = value.slice(0, 2) + '/' + value.slice(2, 4);
            }
            
            e.target.value = value;
        });
    }

    // CVV sadece rakam
    const cvvInput = document.getElementById('cvv');
    if (cvvInput) {
        cvvInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 3);
        });
    }

    // Form gönderilmeden önce kontrol
    const checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            // Form action'ı kontrol et
            if (checkoutForm.getAttribute('action') !== 'checkout.php') {
                checkoutForm.setAttribute('action', 'checkout.php');
            }
            
            const cart = getCart();
            
            if (!cart.items || cart.items.length === 0) {
                e.preventDefault();
                alert('Sepetinizde ürün bulunmamaktadır.');
                return false;
            }
            
            // Kart numarası kontrolü
            if (cardNumberInput) {
                const cardNumber = cardNumberInput.value.replace(/\s/g, '');
                if (cardNumber.length !== 16) {
                    e.preventDefault();
                    alert('Lütfen geçerli bir kart numarası giriniz.');
                    return false;
                }
            }
            
            // Son kullanma tarihi formatı kontrolü
            if (expiryDateInput) {
                const expiryDate = expiryDateInput.value;
                if (!expiryDate.match(/^\d{2}\/\d{2}$/)) {
                    e.preventDefault();
                    alert('Lütfen son kullanma tarihini AA/YY formatında giriniz.');
                    return false;
                }
            }
            
            // CVV kontrolü
            if (cvvInput) {
                const cvv = cvvInput.value;
                if (cvv.length !== 3) {
                    e.preventDefault();
                    alert('Lütfen 3 haneli CVV kodunu giriniz.');
                    return false;
                }
            }
            
            // Sepet verilerini güncelle
            updateCartData();
        });
    }

    // İlk yüklemede özeti güncelle
    if (document.getElementById('orderSummaryItems')) {
        updateOrderSummary();
    }
});
