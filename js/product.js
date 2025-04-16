// js/product.js - Ürün detay sayfası için miktar kontrolleri ve sepete ekleme
document.addEventListener('DOMContentLoaded', function() {
    const decreaseQty = document.getElementById('decrease-qty');
    const increaseQty = document.getElementById('increase-qty');
    const quantityInput = document.getElementById('quantity');
    const addToCartBtn = document.getElementById('addToCartBtn');
    
    // Varsayılan ürün bilgileri (gerçek uygulamada API veya URL parametrelerinden alınabilir)
    const productInfo = {
        id: "19",
        name: "President Cheddar'lı Üçgen Peynir 100 g",
        price: 41.56,
        originalPrice: 48.90,
        discount: 15,
        img: "images/peynir.jpeg"
    };

    if (decreaseQty) {
        decreaseQty.addEventListener('click', () => {
            let currentValue = parseInt(quantityInput.value);
            if (currentValue > 1) {
                quantityInput.value = currentValue - 1;
            }
        });
    }

    if (increaseQty) {
        increaseQty.addEventListener('click', () => {
            let currentValue = parseInt(quantityInput.value);
            if (currentValue < 10) {
                quantityInput.value = currentValue + 1;
            }
        });
    }

    if (quantityInput) {
        quantityInput.addEventListener('input', () => {
            quantityInput.value = quantityInput.value.replace(/[^0-9]/g, '');
            if (quantityInput.value === '' || parseInt(quantityInput.value) < 1) {
                quantityInput.value = 1;
            }
            if (parseInt(quantityInput.value) > 10) {
                quantityInput.value = 10;
            }
        });
    }

    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', () => {
            const quantity = parseInt(quantityInput.value);
            let cart;
            const cartJson = localStorage.getItem('yemeksepeti_cart');
            if (cartJson) {
                cart = JSON.parse(cartJson);
            } else {
                cart = {
                    items: [],
                    subtotal: 0,
                    deliveryFee: 14.90,
                    discount: 0,
                    total: 0
                };
            }
            
            const existingItemIndex = cart.items.findIndex(item => item.id === productInfo.id);
            if (existingItemIndex !== -1) {
                cart.items[existingItemIndex].quantity += quantity;
                cart.items[existingItemIndex].total = cart.items[existingItemIndex].quantity * cart.items[existingItemIndex].price;
            } else {
                cart.items.push({
                    id: productInfo.id,
                    name: productInfo.name,
                    price: productInfo.price,
                    image: productInfo.img,
                    quantity: quantity,
                    total: productInfo.price * quantity
                });
            }
            
            updateCart(cart);
            localStorage.setItem('yemeksepeti_cart', JSON.stringify(cart));
            showNotification(productInfo.name + ' sepete eklendi!');
        });
    }
    
    function updateCart(cart) {
        cart.subtotal = cart.items.reduce((total, item) => total + item.total, 0);
        cart.deliveryFee = cart.subtotal >= 250 ? 0 : 14.90;
        cart.discount = Math.round(cart.subtotal * 0.05 * 100) / 100;
        cart.total = cart.subtotal + cart.deliveryFee - cart.discount;
        updateCartCount(cart.items.reduce((count, item) => count + item.quantity, 0));
    }
    
    function updateCartCount(count) {
        const cartCountElements = document.querySelectorAll('.cart-count');
        cartCountElements.forEach(element => {
            element.textContent = count;
            element.style.display = count > 0 ? 'inline-block' : 'none';
        });
    }
    
    function showNotification(message) {
        let toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toastContainer';
            toastContainer.style.position = 'fixed';
            toastContainer.style.bottom = '20px';
            toastContainer.style.right = '20px';
            toastContainer.style.zIndex = '9999';
            document.body.appendChild(toastContainer);
        }
        const toast = document.createElement('div');
        toast.className = 'toast show';
        toast.style.backgroundColor = '#fff';
        toast.style.borderLeft = '4px solid #28a745';
        toast.style.boxShadow = '0 0.5rem 1rem rgba(0, 0, 0, 0.15)';
        toast.style.margin = '10px';
        toast.style.minWidth = '300px';
        toast.innerHTML = `
            <div class="toast-header">
                <strong class="me-auto"><i class="fas fa-shopping-cart text-success me-2"></i>Sepete Eklendi</strong>
                <small>Şimdi</small>
                <button type="button" class="btn-close" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        `;
        toastContainer.appendChild(toast);
        toast.querySelector('.btn-close').addEventListener('click', () => {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.remove();
            }, 500);
        });
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.remove();
            }, 500);
        }, 5000);
    }
    
    const cartJson = localStorage.getItem('yemeksepeti_cart');
    if (cartJson) {
        const cart = JSON.parse(cartJson);
        const itemCount = cart.items.reduce((count, item) => count + item.quantity, 0);
        updateCartCount(itemCount);
    }
});
