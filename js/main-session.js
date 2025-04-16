// js/main-session.js
document.addEventListener('DOMContentLoaded', function() {
    const cart = {
        // Bu alanlar localStorage'da tutulmuyorsa da, UI güncelleme için referans
        items: [],
        subtotal: 0,
        discount: 0,
        deliveryFee: 14.90,
        total: 0,
        
        init: function() {
            this.setupEventListeners();
            this.fetchCart();  // Sunucudan mevcut sepeti çek
        },
        
        // Sunucudan sepeti çek
        fetchCart: function() {
            // Boş POST ile, action olmadan sepeti döndürebilir
            fetch('cart-processor.php', { method: 'POST' })
            .then(res => res.json())
            .then(data => {
                this.updateLocalCart(data);
                this.updateCartUI();
                // Mobil göstergeleri güncelle
                this.updateMobileUI();
            })
            .catch(err => console.error('fetchCart error:', err));
        },
        
        // Sunucudan dönen cart verisini, this.items vb. alanlara at
        updateLocalCart: function(serverCart) {
            this.items = [];
            if (serverCart.items) {
                // object => array dönüştür
                for (let key in serverCart.items) {
                    this.items.push(serverCart.items[key]);
                }
            }
            this.subtotal = serverCart.subtotal || 0;
            this.discount = serverCart.discount || 0;
            this.deliveryFee = serverCart.deliveryFee || 14.90;
            this.total = serverCart.total || 0;
        },
        
        // Sunucuya ürün ekleme isteği
        addItem: function(id, name, price, img) {
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('product_id', id);
            formData.append('product_name', name);
            formData.append('product_price', price);
            formData.append('product_img', img);
            
            fetch('cart-processor.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                // data => güncel sepet
                this.updateLocalCart(data);
                this.updateCartUI();
                // Mobil veya masaüstü için uygun bildirim göster
                if (this.isMobile()) {
                    this.showMobilePopup(name, price);
                    this.updateMobileUI();
                } else {
                    this.showNotification(`${name} sepete eklendi!`);
                }
            })
            .catch(err => console.error('addItem error:', err));
        },
        
        // Sunucuya miktar güncelleme isteği
        updateItemQuantity: function(id, quantity) {
            if (quantity < 1) quantity = 1;
            
            const formData = new FormData();
            formData.append('action', 'updateQuantity');
            formData.append('product_id', id);
            formData.append('quantity', quantity);
            
            fetch('cart-processor.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                this.updateLocalCart(data);
                this.updateCartUI();
                this.updateMobileUI();
            })
            .catch(err => console.error('updateItemQuantity error:', err));
        },
        
        // Sunucuya ürün silme isteği
        removeItem: function(id) {
            const formData = new FormData();
            formData.append('action', 'remove');
            formData.append('product_id', id);
            
            fetch('cart-processor.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                this.updateLocalCart(data);
                this.updateCartUI();
                this.updateMobileUI();
                this.showNotification(`Ürün sepetten çıkarıldı.`);
            })
            .catch(err => console.error('removeItem error:', err));
        },
        
        // Sunucuya sepet temizleme isteği
        clearCart: function() {
            const formData = new FormData();
            formData.append('action', 'clear');
            
            fetch('cart-processor.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                this.updateLocalCart(data);
                this.updateCartUI();
                this.updateMobileUI();
                this.showNotification('Sepet temizlendi.');
            })
            .catch(err => console.error('clearCart error:', err));
        },
        
        // Mobil arayüzünü güncelleme
        updateMobileUI: function() {
            const itemCount = this.items.reduce((count, item) => count + item.quantity, 0);
            const mobileCartCount = document.getElementById('mobileCartCount');
            const mobileCartItemCount = document.getElementById('mobileCartItemCount');
            const mobileCartTotal = document.getElementById('mobileCartTotal');
            const mobileCartIndicator = document.getElementById('mobileCartIndicator');
            const mobileCheckoutBar = document.getElementById('mobileCheckoutBar');
            
            // Mobil sepet göstergesi güncelle
            if (mobileCartCount) {
                mobileCartCount.textContent = itemCount;
                if (itemCount > 0) {
                    mobileCartCount.style.display = 'inline-block';
                    if (mobileCartIndicator) mobileCartIndicator.style.display = 'flex';
                } else {
                    mobileCartCount.style.display = 'none';
                    if (mobileCartIndicator) mobileCartIndicator.style.display = 'none';
                }
            }
            
            // Mobil alt bar ürün sayısı ve toplam
            if (mobileCartItemCount) {
                mobileCartItemCount.textContent = `${itemCount} Ürün`;
            }
            
            if (mobileCartTotal) {
                mobileCartTotal.textContent = this.formatMoney(this.total);
            }
            
            // Mobil checkout bar göster/gizle
            if (mobileCheckoutBar) {
                mobileCheckoutBar.style.display = itemCount > 0 ? 'flex' : 'none';
            }
        },
        
        // Ürün ekledikten sonra mobil popup göster
        showMobilePopup: function(productName, productPrice) {
            const mobileCartPopup = document.getElementById('mobileCartPopup');
            const mobileCartProductName = document.getElementById('mobileCartProductName');
            const mobileCartProductPrice = document.getElementById('mobileCartProductPrice');
            
            if (mobileCartPopup && mobileCartProductName && mobileCartProductPrice) {
                mobileCartProductName.textContent = productName;
                mobileCartProductPrice.textContent = this.formatMoney(parseFloat(productPrice));
                mobileCartPopup.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        },
        
        // Mobil popup'ı kapat
        hideMobilePopup: function() {
            const mobileCartPopup = document.getElementById('mobileCartPopup');
            if (mobileCartPopup) {
                mobileCartPopup.classList.remove('active');
                document.body.style.overflow = '';
            }
        },
        
        // Mobil cihaz tespiti
        isMobile: function() {
            // Ekran genişliği kontrolü
            const mobileWidth = window.innerWidth < 768;
            
            // Ek olarak user agent kontrolü
            const mobileAgent = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            
            return mobileWidth || mobileAgent;
        },
        
        // Arayüzü güncelle - orijinal main.js mantığı
        updateCartUI: function() {
            // Ürün sayısını hesapla
            const itemCount = this.items.reduce((count, item) => count + item.quantity, 0);
            
            // Tüm cart-count elementlerini güncelle
            const cartCountElements = document.querySelectorAll('.cart-count, #cartCount');
            cartCountElements.forEach(elem => {
                elem.textContent = itemCount;
                elem.style.display = itemCount > 0 ? 'inline-block' : 'none';
            });
            
            // Yan panel
            const cartItemsContainer = document.getElementById('cartItems');
            const cartEmptyMessage = document.getElementById('cartEmpty');
            if (cartItemsContainer && cartEmptyMessage) {
                if (this.items.length === 0) {
                    cartItemsContainer.style.display = 'none';
                    cartEmptyMessage.style.display = 'block';
                } else {
                    cartItemsContainer.style.display = 'block';
                    cartEmptyMessage.style.display = 'none';
                    
                    let html = '';
                    this.items.forEach(item => {
                        html += `
                        <div class="cart-item">
                            <div class="row align-items-center">
                                <div class="col-md-2">
                                    <img src="${item.image || 'images/default.jpg'}" alt="${item.name}" class="img-fluid rounded">
                                </div>
                                <div class="col-md-4">
                                    <h5>${item.name}</h5>
                                </div>
                                <div class="col-md-2">
                                    <div class="price">${this.formatMoney(item.price)}</div>
                                </div>
                                <div class="col-md-2">
                                    <div class="quantity-selector">
                                        <button class="btn btn-sm btn-outline-secondary quantity-decrease" data-id="${item.id}">-</button>
                                        <input type="number" class="form-control quantity-input" value="${item.quantity}" min="1" data-id="${item.id}">
                                        <button class="btn btn-sm btn-outline-secondary quantity-increase" data-id="${item.id}">+</button>
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <div class="price">${this.formatMoney(item.total)}</div>
                                </div>
                                <div class="col-md-1 text-end">
                                    <button class="btn btn-sm btn-outline-danger remove-item" data-id="${item.id}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        `;
                    });
                    cartItemsContainer.innerHTML = html;
                    
                    // Olayları ekle
                    this.setupCartEvents();
                }
            }
            
            // Toplam değerleri güncelle
            const subtotalElement = document.getElementById('subtotal');
            const deliveryFeeElement = document.getElementById('deliveryFee');
            const discountElement = document.getElementById('discount');
            const totalElement = document.getElementById('total');
            
            if (subtotalElement) subtotalElement.textContent = this.formatMoney(this.subtotal);
            if (deliveryFeeElement) deliveryFeeElement.textContent = this.formatMoney(this.deliveryFee);
            if (discountElement) discountElement.textContent = this.formatMoney(this.discount);
            if (totalElement) totalElement.textContent = this.formatMoney(this.total);
            
            // Minimum sipariş tutarı kontrolü
            const checkoutButton = document.getElementById('checkoutButton');
            const minOrderMessage = document.getElementById('minOrderMessage');
            const MIN_ORDER_AMOUNT = 150;
            if (checkoutButton && minOrderMessage) {
                if (this.subtotal >= MIN_ORDER_AMOUNT) {
                    checkoutButton.disabled = false;
                    minOrderMessage.innerHTML = 'Minimum sipariş tutarına ulaştınız';
                    minOrderMessage.parentElement.classList.remove('bg-warning-subtle');
                    minOrderMessage.parentElement.classList.add('bg-success-subtle');
                } else {
                    checkoutButton.disabled = true;
                    const remaining = MIN_ORDER_AMOUNT - this.subtotal;
                    minOrderMessage.innerHTML = `${this.formatMoney(remaining)} daha ürün ekleyin`;
                    minOrderMessage.parentElement.classList.add('bg-warning-subtle');
                    minOrderMessage.parentElement.classList.remove('bg-success-subtle');
                }
            }
        },
        
        // Sepet sayfasındaki olayları kur
        setupCartEvents: function() {
            document.querySelectorAll('.remove-item').forEach(button => {
                button.addEventListener('click', e => {
                    const id = e.currentTarget.getAttribute('data-id');
                    this.removeItem(id);
                });
            });
            document.querySelectorAll('.quantity-decrease').forEach(button => {
                button.addEventListener('click', e => {
                    const id = e.currentTarget.getAttribute('data-id');
                    const item = this.items.find(x => x.id === id);
                    if (item && item.quantity > 1) {
                        this.updateItemQuantity(id, item.quantity - 1);
                    }
                });
            });
            document.querySelectorAll('.quantity-increase').forEach(button => {
                button.addEventListener('click', e => {
                    const id = e.currentTarget.getAttribute('data-id');
                    const item = this.items.find(x => x.id === id);
                    if (item) {
                        this.updateItemQuantity(id, item.quantity + 1);
                    }
                });
            });
            document.querySelectorAll('.quantity-input').forEach(input => {
                input.addEventListener('change', e => {
                    const id = e.currentTarget.getAttribute('data-id');
                    let quantity = parseInt(e.currentTarget.value);
                    if (isNaN(quantity) || quantity < 1) {
                        quantity = 1;
                        e.currentTarget.value = 1;
                    }
                    this.updateItemQuantity(id, quantity);
                });
            });
        },
        
        // Genel olay dinleyicileri
        setupEventListeners: function() {
            // add-to-cart, add-button
            document.querySelectorAll('.add-to-cart, .add-button').forEach(button => {
                button.addEventListener('click', e => {
                    e.preventDefault();
                    const id = button.getAttribute('data-product-id');
                    const name = button.getAttribute('data-product-name');
                    const price = parseFloat(button.getAttribute('data-product-price'));
                    const img = button.getAttribute('data-product-img');
                    this.addItem(id, name, price, img);
                });
            });
            
            // "Sepeti Temizle" butonu
            const clearCartButton = document.getElementById('clearCart');
            if (clearCartButton) {
                clearCartButton.addEventListener('click', e => {
                    e.preventDefault();
                    if (confirm('Sepetinizi temizlemek istediğinize emin misiniz?')) {
                        this.clearCart();
                    }
                });
            }
            
            // Sepet butonu, sidebar açma-kapama
            const cartButton = document.getElementById('cartButton');
            const cartSidebar = document.getElementById('cartSidebar');
            const cartOverlay = document.getElementById('cartOverlay');
            const closeCartButton = document.getElementById('closeCart');
            
            if (cartButton && cartSidebar && cartOverlay && closeCartButton) {
                cartButton.addEventListener('click', () => {
                    if (this.isMobile()) {
                        // Mobilde checkout sayfasına git
                        window.location.href = 'checkout.php';
                    } else {
                        // Masaüstünde sidebar aç
                        cartSidebar.classList.add('active');
                        cartOverlay.classList.add('active');
                        document.body.style.overflow = 'hidden';
                    }
                });
                
                closeCartButton.addEventListener('click', () => {
                    cartSidebar.classList.remove('active');
                    cartOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                });
                
                cartOverlay.addEventListener('click', () => {
                    cartSidebar.classList.remove('active');
                    cartOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }
            
            // Mobil pop-up butonları
            const continueShopping = document.getElementById('continueShopping');
            const goToCheckout = document.getElementById('goToCheckout');
            const mobileCartIndicator = document.getElementById('mobileCartIndicator');
            
            if (continueShopping) {
                continueShopping.addEventListener('click', () => {
                    this.hideMobilePopup();
                });
            }
            
            if (goToCheckout) {
                goToCheckout.addEventListener('click', () => {
                    window.location.href = 'checkout.php';
                });
            }
            
            if (mobileCartIndicator) {
                mobileCartIndicator.addEventListener('click', () => {
                    window.location.href = 'checkout.php';
                });
            }
            
            // Pencere boyutu değiştiğinde mobil/masaüstü görünümü güncelle
            window.addEventListener('resize', () => {
                // Mobil popup açıksa ve artık masaüstü görünümündeyse kapat
                if (!this.isMobile()) {
                    this.hideMobilePopup();
                }
            });
            
            // Checkout butonu
            const checkoutButton = document.getElementById('checkoutButton');
            if (checkoutButton) {
                checkoutButton.addEventListener('click', () => {
                    // Sepet boş mu kontrol et
                    if (this.items.length === 0) {
                        alert('Sepetiniz boş! Lütfen ürün ekleyin.');
                        return;
                    }
                    // Minimum tutar
                    const MIN_ORDER_AMOUNT = 150;
                    if (this.subtotal < MIN_ORDER_AMOUNT) {
                        const remaining = MIN_ORDER_AMOUNT - this.subtotal;
                        alert(`Minimum sipariş tutarına ulaşmadınız. Lütfen ${this.formatMoney(remaining)} daha ürün ekleyin.`);
                        return;
                    }
                    // checkout.php'ye yönlendir
                    window.location.href = 'checkout.php';
                });
            }
        },
        
        // Para formatı
        formatMoney: function(amount) {
            return amount.toFixed(2).replace('.', ',') + ' TL';
        },
        
        // Bildirim
        showNotification: function(message) {
            let toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toastContainer';
                toastContainer.style.position = 'fixed';
                toastContainer.style.bottom = '20px';
                toastContainer.style.right = '20px';
                toastContainer.style.zIndex = '1050';
                document.body.appendChild(toastContainer);
            }
            const toast = document.createElement('div');
            toast.className = 'toast show';
            toast.role = 'alert';
            toast.ariaLive = 'assertive';
            toast.ariaAtomic = 'true';
            toast.style.minWidth = '250px';
            toast.innerHTML = `
                <div class="toast-header">
                    <i class="fas fa-shopping-cart me-2 text-primary"></i>
                    <strong class="me-auto">Sepet Bildirimi</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            `;
            toastContainer.appendChild(toast);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.remove();
                }, 500);
            }, 3000);
        }
    };
    
    // Başlat
    cart.init();
});