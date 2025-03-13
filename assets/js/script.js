// Main JavaScript file for Coffee Shop Management System

document.addEventListener('DOMContentLoaded', function() {
    // Login form validation
    const loginForm = document.querySelector('.login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('Vui lòng nhập tên đăng nhập và mật khẩu');
            }
        });
    }
    
    // Initialize POS system if on POS page
    initPOS();
});

// POS System Functions
function initPOS() {
    const tableItems = document.querySelectorAll('.table-item');
    const categoryItems = document.querySelectorAll('.category-item');
    const productItems = document.querySelectorAll('.product-item');
    const paymentMethods = document.querySelectorAll('.payment-method');
    
    // Table selection
    if (tableItems) {
        tableItems.forEach(table => {
            table.addEventListener('click', function() {
                // Remove selected class from all tables
                tableItems.forEach(t => t.classList.remove('selected'));
                // Add selected class to clicked table
                this.classList.add('selected');
                
                // Get table ID
                const tableId = this.getAttribute('data-id');
                document.getElementById('selected_table_id').value = tableId;
                
                // Show order section
                document.querySelector('.order-section').style.display = 'block';
            });
        });
    }
    
    // Category selection
    if (categoryItems) {
        categoryItems.forEach(category => {
            category.addEventListener('click', function() {
                // Remove active class from all categories
                categoryItems.forEach(c => c.classList.remove('active'));
                // Add active class to clicked category
                this.classList.add('active');
                
                // Get category ID
                const categoryId = this.getAttribute('data-id');
                
                // Filter products by category
                filterProductsByCategory(categoryId);
            });
        });
    }
    
    // Product selection
    if (productItems) {
        productItems.forEach(product => {
            product.addEventListener('click', function() {
                // Get product details
                const productId = this.getAttribute('data-id');
                const productName = this.querySelector('.product-name').textContent;
                const productPrice = parseFloat(this.getAttribute('data-price'));
                const productSize = this.getAttribute('data-size');
                
                // Add product to order
                addProductToOrder(productId, productName, productPrice, productSize);
            });
        });
    }
    
    // Payment method selection
    if (paymentMethods) {
        paymentMethods.forEach(method => {
            method.addEventListener('click', function() {
                // Remove active class from all payment methods
                paymentMethods.forEach(m => m.classList.remove('active'));
                // Add active class to clicked payment method
                this.classList.add('active');
                
                // Get payment method
                const paymentMethod = this.getAttribute('data-method');
                document.getElementById('payment_method').value = paymentMethod;
                
                // Show transaction code field if payment method is not cash
                const transactionCodeField = document.getElementById('transaction_code_field');
                if (transactionCodeField) {
                    if (paymentMethod !== 'Tiền mặt') {
                        transactionCodeField.style.display = 'block';
                    } else {
                        transactionCodeField.style.display = 'none';
                    }
                }
            });
        });
    }
    
    // Initialize checkout button
    const checkoutBtn = document.getElementById('checkout_btn');
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', processCheckout);
    }
    
    // Initialize promo code application
    const applyPromoBtn = document.getElementById('apply_promo_btn');
    if (applyPromoBtn) {
        applyPromoBtn.addEventListener('click', applyPromoCode);
    }
}

// Filter products by category
function filterProductsByCategory(categoryId) {
    const productItems = document.querySelectorAll('.product-item');
    
    productItems.forEach(product => {
        const productCategory = product.getAttribute('data-category');
        
        if (categoryId === 'all' || productCategory === categoryId) {
            product.style.display = 'block';
        } else {
            product.style.display = 'none';
        }
    });
}

// Add product to order
function addProductToOrder(productId, productName, productPrice, productSize) {
    // Check if product already exists in order
    const existingItem = document.querySelector(`.order-item[data-id="${productId}"]`);
    
    if (existingItem) {
        // Update quantity
        const quantityElement = existingItem.querySelector('.item-quantity');
        let quantity = parseInt(quantityElement.textContent);
        quantity++;
        quantityElement.textContent = quantity;
        
        // Update subtotal
        const subtotalElement = existingItem.querySelector('.item-subtotal');
        const subtotal = quantity * productPrice;
        subtotalElement.textContent = formatCurrency(subtotal);
        
        // Update hidden input
        const quantityInput = document.querySelector(`input[name="quantity[${productId}]"]`);
        quantityInput.value = quantity;
    } else {
        // Create new order item
        const orderItems = document.querySelector('.order-items');
        
        const orderItem = document.createElement('div');
        orderItem.classList.add('order-item');
        orderItem.setAttribute('data-id', productId);
        
        orderItem.innerHTML = `
            <div class="item-details">
                <span class="item-name">${productName} (${productSize})</span>
                <span class="item-price">${formatCurrency(productPrice)}</span>
            </div>
            <div class="item-actions">
                <button type="button" class="btn-decrease">-</button>
                <span class="item-quantity">1</span>
                <button type="button" class="btn-increase">+</button>
                <span class="item-subtotal">${formatCurrency(productPrice)}</span>
                <button type="button" class="btn-remove">×</button>
            </div>
            <input type="hidden" name="product_id[]" value="${productId}">
            <input type="hidden" name="quantity[${productId}]" value="1">
            <input type="hidden" name="price[${productId}]" value="${productPrice}">
        `;
        
        orderItems.appendChild(orderItem);
        
        // Add event listeners for quantity buttons
        const decreaseBtn = orderItem.querySelector('.btn-decrease');
        const increaseBtn = orderItem.querySelector('.btn-increase');
        const removeBtn = orderItem.querySelector('.btn-remove');
        
        decreaseBtn.addEventListener('click', function() {
            updateItemQuantity(orderItem, -1);
        });
        
        increaseBtn.addEventListener('click', function() {
            updateItemQuantity(orderItem, 1);
        });
        
        removeBtn.addEventListener('click', function() {
            orderItem.remove();
            updateOrderTotal();
        });
    }
    
    // Update order total
    updateOrderTotal();
}

// Update item quantity
function updateItemQuantity(orderItem, change) {
    const quantityElement = orderItem.querySelector('.item-quantity');
    const priceElement = orderItem.querySelector('.item-price');
    const subtotalElement = orderItem.querySelector('.item-subtotal');
    const quantityInput = orderItem.querySelector(`input[name^="quantity"]`);
    
    let quantity = parseInt(quantityElement.textContent);
    const price = parseFloat(priceElement.textContent.replace(/[^\d]/g, '')) / 1000;
    
    quantity += change;
    
    if (quantity <= 0) {
        orderItem.remove();
    } else {
        quantityElement.textContent = quantity;
        const subtotal = quantity * price;
        subtotalElement.textContent = formatCurrency(subtotal);
        quantityInput.value = quantity;
    }
    
    updateOrderTotal();
}

// Update order total
function updateOrderTotal() {
    const subtotalElements = document.querySelectorAll('.item-subtotal');
    let total = 0;
    
    subtotalElements.forEach(element => {
        const subtotal = parseFloat(element.textContent.replace(/[^\d]/g, '')) / 1000;
        total += subtotal;
    });
    
    // Apply discount if promo code is applied
    const discountElement = document.getElementById('discount_value');
    if (discountElement && discountElement.value) {
        const discount = parseFloat(discountElement.value);
        total -= discount;
        
        // Ensure total is not negative
        if (total < 0) total = 0;
    }
    
    // Update total display
    const totalElement = document.querySelector('.order-total-value');
    if (totalElement) {
        totalElement.textContent = formatCurrency(total);
    }
    
    // Update hidden total input
    const totalInput = document.getElementById('total_price');
    if (totalInput) {
        totalInput.value = total;
    }
}

// Apply promo code
function applyPromoCode() {
    const promoCodeInput = document.getElementById('promo_code');
    const promoCode = promoCodeInput.value.trim();
    
    if (!promoCode) {
        alert('Vui lòng nhập mã khuyến mãi');
        return;
    }
    
    // Send AJAX request to validate promo code
    fetch('api/validate_promo.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `promo_code=${promoCode}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.valid) {
            // Update discount value
            document.getElementById('discount_value').value = data.discount_value;
            document.getElementById('promo_id').value = data.promo_id;
            
            // Show discount info
            const discountInfo = document.getElementById('discount_info');
            discountInfo.textContent = `Giảm giá: ${formatCurrency(data.discount_value)}`;
            discountInfo.style.display = 'block';
            
            // Update order total
            updateOrderTotal();
            
            alert('Áp dụng mã khuyến mãi thành công!');
        } else {
            alert('Mã khuyến mãi không hợp lệ hoặc đã hết hạn!');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Đã xảy ra lỗi khi kiểm tra mã khuyến mãi');
    });
}

// Process checkout
function processCheckout() {
    const orderForm = document.getElementById('order_form');
    const tableId = document.getElementById('selected_table_id').value;
    const paymentMethod = document.getElementById('payment_method').value;
    const orderItems = document.querySelectorAll('.order-item');
    
    // Validate order
    if (!tableId) {
        alert('Vui lòng chọn bàn');
        return;
    }
    
    if (orderItems.length === 0) {
        alert('Vui lòng thêm sản phẩm vào đơn hàng');
        return;
    }
    
    if (!paymentMethod) {
        alert('Vui lòng chọn phương thức thanh toán');
        return;
    }
    
    // If payment method is not cash, validate transaction code
    if (paymentMethod !== 'Tiền mặt') {
        const transactionCode = document.getElementById('transaction_code').value;
        if (!transactionCode) {
            alert('Vui lòng nhập mã giao dịch');
            return;
        }
    }
    
    // Submit form
    orderForm.submit();
}

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
}