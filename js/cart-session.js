// js/cart-session.js

document.addEventListener('DOMContentLoaded', function() {
    // Sepet yan panelini güncelle
    function updateCartUI(cart) {
        // cart parametresi => { items: {...}, subtotal, discount, deliveryFee, total }
        
        // Ürün sayısını hesapla
        let itemCount = 0;
        for (const key in cart.items) {
            itemCount += cart.items[key].quantity;
        }
        
        // Ürün sayısını göster
        const cartCountElem = document.getElementById('cartCount');
        if (cartCountElem) {
            cartCountElem.textContent = itemCount;
            cartCountElem.style.display = itemCount > 0 ? 'inline-block' : 'none';
        }
        
        // Sidebar içinde liste
        const cartItemsContainer = document.getElementById('cartItems');
        const cartEmpty = document.getElementById('cartEmpty');
        if (itemCount === 0) {
            // Boş sepet
            if (cartItemsContainer) cartItemsContainer.style.display = 'none';
            if (cartEmpty) cartEmpty.style.display = 'block';
        } else {
            if (cartItemsContainer) {
                cartItemsContainer.style.display = 'block';
                if (cartEmpty) cartEmpty.style.display = 'none';
                
                let html = '';
                for (const key in cart.items) {
                    const item = cart.items[key];
                    html += `
                    <div class="cart-item">
                        <div class="row align-items-center">
                            <div class="col-3">
                                <img src="${item.image}" alt="${item.name}" class="img-fluid rounded">
                            </div>
                            <div class="col-5">
                                <h5 class="fs-6">${item.name}</h5>
                                <small>${formatMoney(item.price)} x ${item.quantity}</small>
                            </div>
                            <div class="col-2">
                                <strong>${formatMoney(item.total)}</strong>
                            </div>
                            <div class="col-2 text-end">
                                <button class="btn btn-sm btn-outline-danger remove-item" data-id="${item.id}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    `;
                }
                cartItemsContainer.innerHTML = html;
                
                // Silme butonlarına olay ekle
                cartItemsContainer.querySelectorAll('.remove-item').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const productId = this.getAttribute('data-id');
                        removeItem(productId);
                    });
                });
            }
        }
        
        // Özet bilgileri
        document.getElementById('subtotal').textContent = formatMoney(cart.subtotal);
        document.getElementById('deliveryFee').textContent = formatMoney(cart.deliveryFee);
        document.getElementById('discount').textContent = formatMoney(cart.discount);
        document.getElementById('total').textContent = formatMoney(cart.total);
    }
    
    // Para formatlama
    function formatMoney(amount) {
        return amount.toFixed(2).replace('.', ',') + ' TL';
    }
    
    // Ürün ekleme (AJAX)
    function addItem(productId, productName, productPrice, productImg) {
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('product_id', productId);
        formData.append('product_name', productName);
        formData.append('product_price', productPrice);
        formData.append('product_img', productImg);
        
        fetch('cart-processor.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(cart => {
            console.log('Ürün eklendi, güncel sepet:', cart);
            updateCartUI(cart);
        })
        .catch(err => console.error(err));
    }
    
    // Ürün silme (AJAX)
    function removeItem(productId) {
        const formData = new FormData();
        formData.append('action', 'remove');
        formData.append('product_id', productId);
        
        fetch('cart-processor.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(cart => {
            console.log('Ürün silindi, güncel sepet:', cart);
            updateCartUI(cart);
        })
        .catch(err => console.error(err));
    }
    
    // Sepeti temizleme (AJAX)
    function clearCart() {
        const formData = new FormData();
        formData.append('action', 'clear');
        
        fetch('cart-processor.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(cart => {
            console.log('Sepet temizlendi, güncel sepet:', cart);
            updateCartUI(cart);
        })
        .catch(err => console.error(err));
    }
    
    // "Sepeti Temizle" butonu varsa
    const clearCartButton = document.getElementById('clearCartButton');
    if (clearCartButton) {
        clearCartButton.addEventListener('click', function() {
            clearCart();
        });
    }
    
    // "Sepete Ekle" butonları
    document.querySelectorAll('.add-button').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            const productPrice = this.getAttribute('data-product-price');
            const productImg = this.getAttribute('data-product-img');
            addItem(productId, productName, productPrice, productImg);
        });
    });
    
    // Sidebar açma-kapama
    const cartButton = document.getElementById('cartButton');
    const cartSidebar = document.getElementById('cartSidebar');
    const cartOverlay = document.getElementById('cartOverlay');
    const closeCart = document.getElementById('closeCart');
    
    if (cartButton && cartSidebar && cartOverlay && closeCart) {
        cartButton.addEventListener('click', function() {
            cartSidebar.classList.add('active');
            cartOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
        closeCart.addEventListener('click', function() {
            cartSidebar.classList.remove('active');
            cartOverlay.classList.remove('active');
            document.body.style.overflow = '';
        });
        cartOverlay.addEventListener('click', function() {
            cartSidebar.classList.remove('active');
            cartOverlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
    
    // İlk açılışta sepeti sunucudan çekmek isterseniz:
    // Bunu isterseniz "action=info" gibi bir endpoint yapıp sunucuya ekleyebilirsiniz.
    // Burada basit tutup, sepeti "sayfa yenilenene kadar" sıfırdan başlatıyoruz derseniz, 
    // ek bir "fetch" gerekmez. (İsteğe bağlıdır.)
});
// Fonksiyon: Mobil popup'ı göster

  // Mobil tespit (basit ekran genişliği kontrolü)
  function isMobile() {
    return window.innerWidth <= 576;
  }
  
  
  // Mevcut event listener'ları mobil için değiştirelim:
  // Ürün ekleme butonları zaten addItem() kullanıyor, yukarıdaki fonksiyon bu eklemeyi içeriyor.
  
  // Sepet simgesi için: Mobilde direkt checkout.php'ye yönlendir.
  document.addEventListener('DOMContentLoaded', function() {
    const cartButton = document.getElementById('cartButton');
    if (cartButton) {
      cartButton.addEventListener('click', function() {
        if (isMobile()) {
          window.location.href = 'checkout.php';
        } else {
          // Masaüstü için mevcut sidebar açma fonksiyonu (örneğin, openSidebar())
          cart.openSidebar();
        }
      });
    }
    
    // Popup içindeki butonlar:
    const goToCheckoutBtn = document.getElementById('goToCheckout');
    const continueShoppingBtn = document.getElementById('continueShopping');
    if (goToCheckoutBtn) {
      goToCheckoutBtn.addEventListener('click', function() {
        window.location.href = 'checkout.php';
      });
    }
    if (continueShoppingBtn) {
      continueShoppingBtn.addEventListener('click', function() {
        hideMobilePopup();
      });
    }
  });
  