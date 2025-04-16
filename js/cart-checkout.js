// cart-checkout.js
document.addEventListener('DOMContentLoaded', function() {
    // Initialize cart from localStorage if available
    let cart = JSON.parse(localStorage.getItem('yemeksepetiCart')) || [];
    
    // Update UI based on cart status
    updateCartUI();
    
    // Add event listeners for checkout buttons
    const checkoutButtons = document.querySelectorAll('#checkoutButton');
    checkoutButtons.forEach(button => {
        button.addEventListener('click', function() {
            window.location.href = 'checkout.php';
        });
    });
    
    // Add event listener for "Sepeti Onayla" button in mobile view
    const mobileCheckoutBar = document.getElementById('mobileCheckoutBar');
    if (mobileCheckoutBar) {
        const checkoutLink = mobileCheckoutBar.querySelector('.btn-checkout');
        if (checkoutLink) {
            checkoutLink.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Save cart data to localStorage before redirecting
                localStorage.setItem('yemeksepetiCart', JSON.stringify(cart));
                
                // Redirect to checkout page
                window.location.href = 'checkout.php';
            });
        }
    }
    
    // Function to add item to cart
    window.addToCart = function(productId, productName, productPrice, productImg, quantity) {
        // Check if product already exists in cart
        const existingProductIndex = cart.findIndex(item => item.id === productId);
        
        if (existingProductIndex !== -1) {
            // Update quantity if product exists
            cart[existingProductIndex].quantity += quantity;
        } else {
            // Add new product to cart
            cart.push({
                id: productId,
                name: productName,
                price: parseFloat(productPrice),
                img: productImg,
                quantity: quantity
            });
        }
        
        // Save cart to localStorage
        localStorage.setItem('yemeksepetiCart', JSON.stringify(cart));
        
        // Update UI
        updateCartUI();
        
        // Show mobile cart popup with product details
        showMobileCartPopup(productName, productPrice);
    };
    
    // Function to update item quantity in cart
    window.updateCart = function(productId, quantity) {
        // Find product in cart
        const existingProductIndex = cart.findIndex(item => item.id === productId);
        
        if (existingProductIndex !== -1) {
            // Update quantity
            cart[existingProductIndex].quantity = quantity;
            
            // Save cart to localStorage
            localStorage.setItem('yemeksepetiCart', JSON.stringify(cart));
            
            // Update UI
            updateCartUI();
        }
    };
    
    // Function to remove item from cart
    window.removeFromCart = function(productId) {
        // Remove product from cart
        cart = cart.filter(item => item.id !== productId);
        
        // Save cart to localStorage
        localStorage.setItem('yemeksepetiCart', JSON.stringify(cart));
        
        // Update UI
        updateCartUI();
        
        // If cart is empty, hide mobile cart indicator and checkout bar
        if (cart.length === 0) {
            updateMobileCartIndicator(false);
        }
    };
    
    // Function to update cart UI
    function updateCartUI() {
        // Update cart count
        updateCartCount();
        
        // Update cart items display
        updateCartItems();
        
        // Update cart summary
        updateCartSummary();
        
        // Update mobile UI
        updateMobileUI();
    }
    
    // Function to update cart count
    function updateCartCount() {
        const cartCount = document.getElementById('cartCount');
        const cartItemCount = document.getElementById('cartItemCount');
        
        // Calculate total items in cart
        const totalItems = cart.reduce((total, item) => total + item.quantity, 0);
        
        // Update desktop cart count
        if (cartCount) {
            cartCount.textContent = totalItems;
            cartCount.style.display = totalItems > 0 ? 'inline-block' : 'none';
        }
        
        // Update cart sidebar item count
        if (cartItemCount) {
            cartItemCount.textContent = `(${totalItems})`;
        }
    }
    
    // Function to update cart items display
    function updateCartItems() {
        const cartItems = document.getElementById('cartItems');
        const cartEmpty = document.getElementById('cartEmpty');
        
        if (cartItems && cartEmpty) {
            // Clear current items
            cartItems.innerHTML = '';
            
            // Show/hide empty cart message
            if (cart.length === 0) {
                cartEmpty.style.display = 'block';
                cartItems.style.display = 'none';
            } else {
                cartEmpty.style.display = 'none';
                cartItems.style.display = 'block';
                
                // Add each item to cart display
                cart.forEach(item => {
                    const itemElement = document.createElement('div');
                    itemElement.className = 'cart-item';
                    itemElement.innerHTML = `
                        <div class="d-flex align-items-center mb-3">
                            <div class="cart-item-img me-2">
                                <img src="${item.img}" alt="${item.name}" class="img-fluid" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                            </div>
                            <div class="cart-item-details flex-grow-1">
                                <div class="d-flex justify-content-between">
                                    <div class="cart-item-title">${item.name}</div>
                                    <button class="btn-remove" data-product-id="${item.id}">×</button>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <div class="cart-item-quantity">
                                        <button class="btn-quantity-cart minus" data-product-id="${item.id}">-</button>
                                        <span class="quantity-value">${item.quantity}</span>
                                        <button class="btn-quantity-cart plus" data-product-id="${item.id}">+</button>
                                    </div>
                                    <div class="cart-item-price">${(item.price * item.quantity).toFixed(2)} TL</div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    cartItems.appendChild(itemElement);
                });
                
                // Add event listeners for quantity buttons and remove buttons
                addCartItemEventListeners();
            }
        }
    }
    
    // Function to add event listeners for cart item buttons
    function addCartItemEventListeners() {
        // Quantity decrease buttons
        const minusButtons = document.querySelectorAll('.btn-quantity-cart.minus');
        minusButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const item = cart.find(item => item.id === productId);
                
                if (item && item.quantity > 1) {
                    item.quantity--;
                    updateCart(productId, item.quantity);
                } else if (item && item.quantity === 1) {
                    removeFromCart(productId);
                }
            });
        });
        
        // Quantity increase buttons
        const plusButtons = document.querySelectorAll('.btn-quantity-cart.plus');
        plusButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const item = cart.find(item => item.id === productId);
                
                if (item) {
                    item.quantity++;
                    updateCart(productId, item.quantity);
                }
            });
        });
        
        // Remove buttons
        const removeButtons = document.querySelectorAll('.btn-remove');
        removeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                removeFromCart(productId);
            });
        });
    }
    
    // Function to update cart summary
    function updateCartSummary() {
        const subtotalElement = document.getElementById('subtotal');
        const deliveryFeeElement = document.getElementById('deliveryFee');
        const discountElement = document.getElementById('discount');
        const totalElement = document.getElementById('total');
        const checkoutButton = document.getElementById('checkoutButton');
        const minOrderMessage = document.getElementById('minOrderMessage');
        
        // Calculate subtotal
        const subtotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
        
        // Set delivery fee (free if subtotal is above threshold)
        const deliveryFee = subtotal >= 150 ? 0 : 10;
        
        // Calculate discount (for example, 10% off if subtotal is above 200)
        const discount = subtotal >= 200 ? subtotal * 0.1 : 0;
        
        // Calculate total
        const total = subtotal + deliveryFee - discount;
        
        // Update UI elements
        if (subtotalElement) subtotalElement.textContent = `${subtotal.toFixed(2)} TL`;
        if (deliveryFeeElement) deliveryFeeElement.textContent = deliveryFee > 0 ? `${deliveryFee.toFixed(2)} TL` : 'Ücretsiz';
        if (discountElement) discountElement.textContent = discount > 0 ? `-${discount.toFixed(2)} TL` : '0,00 TL';
        if (totalElement) totalElement.textContent = `${total.toFixed(2)} TL`;
        
        // Update checkout button and min order message
        if (checkoutButton && minOrderMessage) {
            const minOrderAmount = 150;
            
            if (subtotal >= minOrderAmount) {
                checkoutButton.disabled = false;
                checkoutButton.classList.remove('btn-secondary');
                checkoutButton.classList.add('btn-red');
                minOrderMessage.innerHTML = 'Siparişiniz hazır!';
                minOrderMessage.className = 'min-order-info p-2 mt-3 text-center text-success';
            } else {
                checkoutButton.disabled = true;
                checkoutButton.classList.remove('btn-red');
                checkoutButton.classList.add('btn-secondary');
                const remaining = minOrderAmount - subtotal;
                minOrderMessage.innerHTML = `Minimum sipariş tutarına ${remaining.toFixed(2)} TL kaldı`;
                minOrderMessage.className = 'min-order-info p-2 mt-3 text-center text-danger';
            }
        }
    }
    
    // Function to update mobile UI
    function updateMobileUI() {
        updateMobileCartIndicator(cart.length > 0);
        updateMobileCheckoutBar();
    }
    
    // Function to update mobile cart indicator
    function updateMobileCartIndicator(show) {
        const mobileCartIndicator = document.getElementById('mobileCartIndicator');
        const mobileCartCount = document.getElementById('mobileCartCount');
        const mobileCheckoutBar = document.getElementById('mobileCheckoutBar');
        
        if (mobileCartIndicator && mobileCartCount) {
            // Calculate total items in cart
            const totalItems = cart.reduce((total, item) => total + item.quantity, 0);
            
            // Update count
            mobileCartCount.textContent = totalItems;
            
            // Show/hide indicator
            mobileCartIndicator.style.display = show ? 'flex' : 'none';
        }
        
        if (mobileCheckoutBar) {
            mobileCheckoutBar.style.display = show ? 'flex' : 'none';
        }
    }
    
    // Function to update mobile checkout bar
    function updateMobileCheckoutBar() {
        const mobileCartItemCount = document.getElementById('mobileCartItemCount');
        const mobileCartTotal = document.getElementById('mobileCartTotal');
        
        if (mobileCartItemCount && mobileCartTotal) {
            // Calculate total items in cart
            const totalItems = cart.reduce((total, item) => total + item.quantity, 0);
            
            // Calculate total price
            const total = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
            
            // Update UI
            mobileCartItemCount.textContent = `${totalItems} Ürün`;
            mobileCartTotal.textContent = `${total.toFixed(2)} TL`;
        }
    }
    
    // Function to show mobile cart popup
    function showMobileCartPopup(productName, productPrice) {
        const mobileCartPopup = document.getElementById('mobileCartPopup');
        const mobileCartProductName = document.getElementById('mobileCartProductName');
        const mobileCartProductPrice = document.getElementById('mobileCartProductPrice');
        
        if (mobileCartPopup && mobileCartProductName && mobileCartProductPrice) {
            mobileCartProductName.textContent = productName;
            mobileCartProductPrice.textContent = `${parseFloat(productPrice).toFixed(2)} TL`;
            
            mobileCartPopup.style.display = 'block';
            
            // Hide popup after 3 seconds
            setTimeout(() => {
                mobileCartPopup.style.display = 'none';
            }, 3000);
        }
    }
    
    // Add event listeners for mobile cart popup buttons
    const continueShoppingBtn = document.getElementById('continueShopping');
    if (continueShoppingBtn) {
        continueShoppingBtn.addEventListener('click', function() {
            const mobileCartPopup = document.getElementById('mobileCartPopup');
            if (mobileCartPopup) {
                mobileCartPopup.style.display = 'none';
            }
        });
    }
    
    const goToCheckoutBtn = document.getElementById('goToCheckout');
    if (goToCheckoutBtn) {
        goToCheckoutBtn.addEventListener('click', function() {
            window.location.href = 'checkout.php';
        });
    }
    
    // Add event listener for mobile cart indicator
    const mobileCartIndicator = document.getElementById('mobileCartIndicator');
    if (mobileCartIndicator) {
        mobileCartIndicator.addEventListener('click', function() {
            // Show cart sidebar
            const cartSidebar = document.getElementById('cartSidebar');
            const cartOverlay = document.getElementById('cartOverlay');
            
            if (cartSidebar && cartOverlay) {
                cartSidebar.classList.add('show');
                cartOverlay.style.display = 'block';
            }
        });
    }
});