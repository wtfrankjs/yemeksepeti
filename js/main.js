// js/main.js - Sepet işlemleri ve arayüz güncellemeleri
document.addEventListener('DOMContentLoaded', function() {
    const cart = {
        items: [],
        subtotal: 0,
        discount: 0,
        deliveryFee: 14.90,
        total: 0,
        
        init: function() {
            this.loadFromStorage();
            this.updateCartUI();
            this.setupEventListeners();
        },
        
        // Local Storage'dan sepeti yükle
        loadFromStorage: function() {
            const cartData = localStorage.getItem('yemeksepeti_cart');
            if (cartData) {
                const savedCart = JSON.parse(cartData);
                this.items = savedCart.items || [];
                this.subtotal = savedCart.subtotal || 0;
                this.discount = savedCart.discount || 0;
                this.deliveryFee = savedCart.deliveryFee || 14.90;
                this.total = savedCart.total || 0;
            }
        },
        
        // Sepeti Local Storage'a kaydet
        saveToStorage: function() {
            localStorage.setItem('yemeksepeti_cart', JSON.stringify({
                items: this.items,
                subtotal: this.subtotal,
                discount: this.discount,
                deliveryFee: this.deliveryFee,
                total: this.total
            }));
        },
        
        // Ürün ekle
        addItem: function(id, name, price, quantity = 1, image = '') {
            const existingItem = this.items.find(item => item.id === id);
            if (existingItem) {
                existingItem.quantity += quantity;
                existingItem.total = existingItem.price * existingItem.quantity;
            } else {
                this.items.push({
                    id: id,
                    name: name,
                    price: price,
                    quantity: quantity,
                    image: image,
                    total: price * quantity
                });
            }
            
            this.calculateTotals();
            this.saveToStorage();
            this.updateCartUI();
            this.showNotification(`${name} sepete eklendi!`);
        },
        
        // Ürün miktarını güncelle
        updateItemQuantity: function(id, quantity) {
            const item = this.items.find(item => item.id === id);
            if (item) {
                item.quantity = quantity;
                item.total = item.price * item.quantity;
                this.calculateTotals();
                this.saveToStorage();
                this.updateCartUI();
            }
        },
        
        // Ürün sil
        removeItem: function(id) {
            const index = this.items.findIndex(item => item.id === id);
            if (index !== -1) {
                const removedItem = this.items[index];
                this.items.splice(index, 1);
                this.calculateTotals();
                this.saveToStorage();
                this.updateCartUI();
                this.showNotification(`${removedItem.name} sepetten çıkarıldı.`);
            }
        },
        
        // Sepeti temizle
        clearCart: function() {
            this.items = [];
            this.calculateTotals();
            this.saveToStorage();
            this.updateCartUI();
            this.showNotification('Sepet temizlendi.');
        },
        
        // Toplam değerleri hesapla
        calculateTotals: function() {
            this.subtotal = this.items.reduce((total, item) => total + item.total, 0);
            this.total = this.subtotal + this.deliveryFee - this.discount;
        },
        
        // Sepet arayüzünü güncelle
        updateCartUI: function() {
            const cartCountElements = document.querySelectorAll('.cart-count, #cartCount');
            const itemCount = this.items.reduce((count, item) => count + item.quantity, 0);
            cartCountElements.forEach(element => {
                element.textContent = itemCount;
                element.style.display = itemCount > 0 ? 'inline-block' : 'none';
            });
            
            const cartItemCount = document.getElementById('cartItemCount');
            if (cartItemCount) {
                cartItemCount.textContent = `(${itemCount})`;
            }
            
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
                    this.setupCartEvents();
                }
            }
            
            const subtotalElement = document.getElementById('subtotal');
            const deliveryFeeElement = document.getElementById('deliveryFee');
            const discountElement = document.getElementById('discount');
            const totalElement = document.getElementById('total');
            if (subtotalElement) subtotalElement.textContent = this.formatMoney(this.subtotal);
            if (deliveryFeeElement) deliveryFeeElement.textContent = this.formatMoney(this.deliveryFee);
            if (discountElement) discountElement.textContent = this.formatMoney(this.discount);
            if (totalElement) totalElement.textContent = this.formatMoney(this.total);
            
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
                    const item = this.items.find(item => item.id === id);
                    if (item && item.quantity > 1) {
                        this.updateItemQuantity(id, item.quantity - 1);
                    }
                });
            });
            document.querySelectorAll('.quantity-increase').forEach(button => {
                button.addEventListener('click', e => {
                    const id = e.currentTarget.getAttribute('data-id');
                    const item = this.items.find(item => item.id === id);
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
        
        // Genel olay dinleyicilerini kur
        setupEventListeners: function() {
            document.querySelectorAll('.add-to-cart, .add-button').forEach(button => {
                button.addEventListener('click', e => {
                    e.preventDefault();
                    const id = e.currentTarget.getAttribute('data-product-id');
                    const name = e.currentTarget.getAttribute('data-product-name');
                    const price = parseFloat(e.currentTarget.getAttribute('data-product-price'));
                    const image = e.currentTarget.getAttribute('data-product-img');
                    this.addItem(id, name, price, 1, image);
                });
            });
            const clearCartButton = document.getElementById('clearCart');
            if (clearCartButton) {
                clearCartButton.addEventListener('click', e => {
                    e.preventDefault();
                    if (confirm('Sepetinizi temizlemek istediğinize emin misiniz?')) {
                        this.clearCart();
                    }
                });
            }
            const cartButton = document.getElementById('cartButton');
            const cartSidebar = document.getElementById('cartSidebar');
            const cartOverlay = document.getElementById('cartOverlay');
            const closeCartButton = document.getElementById('closeCart');
            if (cartButton && cartSidebar && cartOverlay && closeCartButton) {
                cartButton.addEventListener('click', () => {
                    cartSidebar.classList.add('active');
                    cartOverlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
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
            const checkoutButton = document.getElementById('checkoutButton');
            if (checkoutButton) {
                checkoutButton.addEventListener('click', () => {
                    if (this.items.length === 0) {
                        alert('Sepetiniz boş! Lütfen ürün ekleyin.');
                        return;
                    }
                    const MIN_ORDER_AMOUNT = 150;
                    if (this.subtotal < MIN_ORDER_AMOUNT) {
                        const remaining = MIN_ORDER_AMOUNT - this.subtotal;
                        alert(`Minimum sipariş tutarına ulaşmadınız. Lütfen ${this.formatMoney(remaining)} daha ürün ekleyin.`);
                        return;
                    }
                    this.saveToStorage();
                    sessionStorage.setItem('checkout_cart', localStorage.getItem('yemeksepeti_cart'));
                    window.location.href = 'checkout.php';
                });
            }
        },
        
        // Para formatı
        formatMoney: function(amount) {
            return amount.toFixed(2).replace('.', ',') + ' TL';
        },
        
        // Bildirim göster
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
            document.getElementById('toastContainer').appendChild(toast);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.remove();
                }, 500);
            }, 3000);
        }
    };
    
    // Sepeti başlat
    cart.init();
    
    // Bootstrap tooltip gibi bileşenleri etkinleştir (varsa)
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(tooltipTriggerEl => {
        if (window.bootstrap && window.bootstrap.Tooltip) {
            new window.bootstrap.Tooltip(tooltipTriggerEl);
        }
    });
    
    // Dil seçimi menüsü (varsa)
    const langToggle = document.getElementById('langToggle');
    const langDropdown = document.getElementById('langDropdown');
    if (langToggle && langDropdown) {
        langToggle.addEventListener('click', function() {
            langDropdown.classList.toggle('show');
        });
        document.querySelectorAll('.lang-option').forEach(option => {
            option.addEventListener('click', function() {
                const lang = this.getAttribute('data-lang');
                document.getElementById('currentLang').textContent = lang;
                document.querySelectorAll('.lang-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                this.classList.add('selected');
                langDropdown.classList.remove('show');
            });
        });
        document.addEventListener('click', function(e) {
            if (langDropdown.classList.contains('show') && !langToggle.contains(e.target) && !langDropdown.contains(e.target)) {
                langDropdown.classList.remove('show');
            }
        });
    }
});
