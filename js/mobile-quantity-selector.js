// mobile-quantity-selector.js - Ürün miktarı artırma/azaltma işlemleri
document.addEventListener('DOMContentLoaded', function() {
    // Ürün kartlarındaki ekleme butonlarına tıklama olayı
    setupProductButtons();
    
    // Sepet ekledikten sonra gösterilecek popup ve mobil arayüz
    setupMobileCartInteraction();
    
    // Dinamik olarak eklenen içerikler için Event Delegation kullanıyoruz
    document.addEventListener('click', function(e) {
        // Sepetteki artırma/azaltma butonları için (Event Delegation)
        if (e.target.classList.contains('quantity-increase')) {
            const productId = e.target.getAttribute('data-id');
            increaseQuantity(productId);
        } else if (e.target.classList.contains('quantity-decrease')) {
            const productId = e.target.getAttribute('data-id');
            decreaseQuantity(productId);
        }
    });
});

// Ürünlere tıklama olayını ekle
function setupProductButtons() {
    document.querySelectorAll('.add-button').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            const productPrice = parseFloat(this.getAttribute('data-product-price'));
            const productImg = this.getAttribute('data-product-img') || '';
            
            // Mobil arayüzde ürün popup'ını göster
            if (window.innerWidth < 768) {
                showMobileProductPopup(productId, productName, productPrice);
            }
        });
    });
}

// Mobil sepet etkileşimlerini kur
function setupMobileCartInteraction() {
    const mobileCartPopup = document.getElementById('mobileCartPopup');
    const mobileCartIndicator = document.getElementById('mobileCartIndicator');
    const mobileCheckoutBar = document.getElementById('mobileCheckoutBar');
    
    // Popup'ı kapatma işlemleri
    if (mobileCartPopup) {
        document.getElementById('continueShopping').addEventListener('click', function() {
            mobileCartPopup.classList.remove('active');
        });
        
        document.getElementById('goToCheckout').addEventListener('click', function() {
            mobileCartPopup.classList.remove('active');
            // Mobil sepet özeti açılabilir veya ilgili sayfaya yönlendirilebilir
        });
        
        // Popup dışına tıklayınca kapatma
        mobileCartPopup.addEventListener('click', function(e) {
            if (e.target === mobileCartPopup) {
                mobileCartPopup.classList.remove('active');
            }
        });
    }
    
    // Mobil sepet göstergesine tıklama
    if (mobileCartIndicator) {
        mobileCartIndicator.addEventListener('click', function() {
            window.location.href = 'cart.php';
        });
    }
    
    // Mobil sepet onaylama butonuna tıklama
    if (mobileCheckoutBar) {
        const checkoutButton = mobileCheckoutBar.querySelector('.btn-checkout');
        if (checkoutButton) {
            checkoutButton.addEventListener('click', function() {
                window.location.href = 'checkout.php';
            });
        }
    }
}

// Miktar artırma fonksiyonu
function increaseQuantity(productId) {
    const cart = getCartFromStorage();
    const itemIndex = cart.items.findIndex(item => item.id === productId);
    
    if (itemIndex !== -1) {
        cart.items[itemIndex].quantity += 1;
        cart.items[itemIndex].total = cart.items[itemIndex].price * cart.items[itemIndex].quantity;
        
        // Arayüzdeki miktarı güncelle
        const quantityInput = document.querySelector(`.quantity-input[data-id="${productId}"]`);
        if (quantityInput) {
            quantityInput.value = cart.items[itemIndex].quantity;
        }
        
        // Toplamları hesapla ve kaydet
        calculateCartTotals(cart);
        saveCartToStorage(cart);
        updateCartUI(cart);
    }
}

// Miktar azaltma fonksiyonu
function decreaseQuantity(productId) {
    const cart = getCartFromStorage();
    const itemIndex = cart.items.findIndex(item => item.id === productId);
    
    if (itemIndex !== -1 && cart.items[itemIndex].quantity > 1) {
        cart.items[itemIndex].quantity -= 1;
        cart.items[itemIndex].total = cart.items[itemIndex].price * cart.items[itemIndex].quantity;
        
        // Arayüzdeki miktarı güncelle
        const quantityInput = document.querySelector(`.quantity-input[data-id="${productId}"]`);
        if (quantityInput) {
            quantityInput.value = cart.items[itemIndex].quantity;
        }
        
        // Toplamları hesapla ve kaydet
        calculateCartTotals(cart);
        saveCartToStorage(cart);
        updateCartUI(cart);
    }
}

// Mobil ürün popup'ını göster
function showMobileProductPopup(productId, productName, productPrice) {
    const popup = document.getElementById('mobileCartPopup');
    if (popup) {
        document.getElementById('mobileCartProductName').textContent = productName;
        document.getElementById('mobileCartProductPrice').textContent = formatMoney(productPrice);
        popup.classList.add('active');
        
        // Belirli bir süre sonra otomatik kapat
        setTimeout(() => {
            popup.classList.remove('active');
        }, 3000);
    }
}

// Para formatı
function formatMoney(amount) {
    return amount.toFixed(2).replace('.', ',') + ' TL';
}

// Local Storage'dan sepeti al
function getCartFromStorage() {
    const cartData = localStorage.getItem('yemeksepeti_cart');
    if (cartData) {
        return JSON.parse(cartData);
    }
    return { items: [], subtotal: 0, discount: 0, deliveryFee: 14.90, total: 0 };
}

// Sepeti Local Storage'a kaydet
function saveCartToStorage(cart) {
    localStorage.setItem('yemeksepeti_cart', JSON.stringify(cart));
}

// Sepet toplamlarını hesapla
function calculateCartTotals(cart) {
    cart.subtotal = cart.items.reduce((total, item) => total + item.total, 0);
    cart.total = cart.subtotal + cart.deliveryFee - cart.discount;
    return cart;
}

// Sepet arayüzünü güncelle
function updateCartUI(cart) {
    // Sepet sayısını güncelle
    const itemCount = cart.items.reduce((count, item) => count + item.quantity, 0);
    
    // Masaüstü sepet sayacı
    const cartCountElements = document.querySelectorAll('#cartCount, #cartItemCount');
    cartCountElements.forEach(element => {
        if (element) {
            if (element.id === 'cartItemCount') {
                element.textContent = `(${itemCount})`;
            } else {
                element.textContent = itemCount;
                element.style.display = itemCount > 0 ? 'inline-block' : 'none';
            }
        }
    });
    
    // Mobil sepet sayacı ve göstergesi
    const mobileCartCount = document.getElementById('mobileCartCount');
    const mobileCartIndicator = document.getElementById('mobileCartIndicator');
    const mobileCheckoutBar = document.getElementById('mobileCheckoutBar');
    const mobileCartItemCount = document.getElementById('mobileCartItemCount');
    const mobileCartTotal = document.getElementById('mobileCartTotal');
    
    if (mobileCartCount) mobileCartCount.textContent = itemCount;
    if (mobileCartIndicator) mobileCartIndicator.style.display = itemCount > 0 ? 'flex' : 'none';
    if (mobileCheckoutBar) mobileCheckoutBar.style.display = itemCount > 0 ? 'flex' : 'none';
    if (mobileCartItemCount) mobileCartItemCount.textContent = `${itemCount} Ürün`;
    if (mobileCartTotal) mobileCartTotal.textContent = formatMoney(cart.total);
    
    // Sepet fiyat bilgilerini güncelle
    document.querySelectorAll('#subtotal, #deliveryFee, #discount, #total').forEach(el => {
        if (!el) return;
        let value = 0;
        switch (el.id) {
            case 'subtotal': value = cart.subtotal; break;
            case 'deliveryFee': value = cart.deliveryFee; break;
            case 'discount': value = cart.discount; break;
            case 'total': value = cart.total; break;
        }
        el.textContent = formatMoney(value);
    });
    
    // Minimum sepet tutarı kontrolü
    const checkoutButton = document.getElementById('checkoutButton');
    const minOrderMessage = document.getElementById('minOrderMessage');
    const MIN_ORDER_AMOUNT = 150;
    
    if (checkoutButton && minOrderMessage) {
        if (cart.subtotal >= MIN_ORDER_AMOUNT) {
            checkoutButton.disabled = false;
            minOrderMessage.innerHTML = 'Minimum sipariş tutarına ulaştınız';
            minOrderMessage.parentElement.classList.remove('bg-warning-subtle');
            minOrderMessage.parentElement.classList.add('bg-success-subtle');
        } else {
            checkoutButton.disabled = true;
            const remaining = MIN_ORDER_AMOUNT - cart.subtotal;
            minOrderMessage.innerHTML = `${formatMoney(remaining)} daha ürün ekleyin`;
            minOrderMessage.parentElement.classList.add('bg-warning-subtle');
            minOrderMessage.parentElement.classList.remove('bg-success-subtle');
        }
    }
}