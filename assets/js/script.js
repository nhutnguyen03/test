// Main JavaScript file for Coffee Shop Management System

document.addEventListener('DOMContentLoaded', function() {
    // Initialize clock if clock element exists
    const clockElement = document.getElementById('clock');
    if (clockElement) {
        updateClock();
        setInterval(updateClock, 1000);
    }

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

// Object to store orders for each table
let tableOrders = {};

// POS System Functions
function initPOS() {
    const tableItems = document.querySelectorAll('.table-item');
    const categoryItems = document.querySelectorAll('.category-item');
    const productItems = document.querySelectorAll('.product-item');
    const paymentMethods = document.querySelectorAll('.payment-method');
    
    // Table selection
    if (tableItems.length > 0) {
        tableItems.forEach(table => {
            table.addEventListener('click', function() {
                // Save current order if a table was previously selected
                const previousTableId = document.getElementById('selected_table_id').value;
                if (previousTableId) {
                    saveCurrentTableOrder(previousTableId);
                }
                
                tableItems.forEach(t => t.classList.remove('selected'));
                this.classList.add('selected');
                
                const tableId = this.getAttribute('data-id');
                document.getElementById('selected_table_id').value = tableId;
                
                // Load table-specific order or create a new one
                loadTableOrder(tableId);
                
                document.querySelector('.order-section').style.display = 'block';
            });
        });
    }
    
    // Initialize close and exit buttons
    const closeOrderBtn = document.getElementById('close_order_btn');
    if (closeOrderBtn) {
        closeOrderBtn.addEventListener('click', function() {
            const tableId = document.getElementById('selected_table_id').value;
            if (tableId) {
                // Remove order for this table
                delete tableOrders[tableId];
                clearOrderForm();
                
                // Remove occupied styling
                const tableItem = document.querySelector(`.table-item[data-id="${tableId}"]`);
                if (tableItem) {
                    tableItem.classList.remove('occupied');
                    tableItem.classList.remove('selected');
                }
                
                document.querySelector('.order-section').style.display = 'none';
                document.getElementById('selected_table_id').value = '';
            }
        });
    }
    
    const exitOrderBtn = document.getElementById('exit_order_btn');
    if (exitOrderBtn) {
        exitOrderBtn.addEventListener('click', function() {
            const tableId = document.getElementById('selected_table_id').value;
            if (tableId) {
                // Save current order for this table
                saveCurrentTableOrder(tableId);
                
                // Mark table as occupied if it has items
                const hasItems = document.querySelectorAll('.order-item').length > 0;
                if (hasItems) {
                    const tableItem = document.querySelector(`.table-item[data-id="${tableId}"]`);
                    if (tableItem) {
                        tableItem.classList.add('occupied');
                    }
                }
                
                // Hide order section
                document.querySelector('.order-section').style.display = 'none';
                document.getElementById('selected_table_id').value = '';
                tableItems.forEach(t => t.classList.remove('selected'));
            }
        });
    }
    
    // Category selection
    if (categoryItems.length > 0) {
        categoryItems.forEach(category => {
            category.addEventListener('click', function() {
                categoryItems.forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                
                const categoryId = this.getAttribute('data-id');
                filterProductsByCategory(categoryId);
            });
        });
    }
    
    // Product selection
    if (productItems.length > 0) {
        productItems.forEach(product => {
            product.addEventListener('click', function() {
                const productId = this.getAttribute('data-id');
                const productName = this.querySelector('.product-name').textContent;
                const productPrice = parseFloat(this.getAttribute('data-price')); // Đảm bảo lấy giá trị số
                const productSize = this.getAttribute('data-size');
                
                addProductToOrder(productId, productName, productPrice, productSize);
            });
        });
    }
    
    // Payment method selection
    if (paymentMethods.length > 0) {
        paymentMethods.forEach(method => {
            method.addEventListener('click', function() {
                paymentMethods.forEach(m => m.classList.remove('active'));
                this.classList.add('active');
                
                const paymentMethod = this.getAttribute('data-method');
                document.getElementById('payment_method').value = paymentMethod;
                
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
    
    // Initialize promo code application and display
    const applyPromoBtn = document.getElementById('apply_promo_btn');
    if (applyPromoBtn) {
        applyPromoBtn.addEventListener('click', applyPromoCode);
    }
    
    const showPromosBtn = document.getElementById('show_promos_btn');
    if (showPromosBtn) {
        showPromosBtn.addEventListener('click', togglePromosDisplay);
    }
    
    // Initialize remove promo button
    const removePromoBtn = document.getElementById('remove_promo_btn');
    if (removePromoBtn) {
        removePromoBtn.addEventListener('click', removePromoCode);
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

// Update order total
function updateOrderTotal() {
    const subtotalElements = document.querySelectorAll('.item-subtotal');
    let total = 0;
    
    subtotalElements.forEach(element => {
        const subtotalText = element.textContent.replace(/[^\d]/g, ''); // Loại bỏ ký tự không phải số
        const subtotal = parseFloat(subtotalText) || 0; // Đảm bảo không bị NaN
        total += subtotal;
    });
    
    const discountElement = document.getElementById('discount_value');
    if (discountElement && discountElement.value) {
        const discount = parseFloat(discountElement.value) || 0;
        total -= discount;
        if (total < 0) total = 0;
    }
    
    const totalElement = document.querySelector('.order-total-value');
    if (totalElement) {
        totalElement.textContent = formatCurrency(total);
    }
    
    const totalInput = document.getElementById('total_price');
    if (totalInput) {
        totalInput.value = total;
    }
}

// Add product to order
function addProductToOrder(productId, productName, productPrice, productSize) {
    // Check if product is already in the order
    const existingItem = document.querySelector(`.order-item[data-id="${productId}"]`);
    
    if (existingItem) {
        updateItemQuantity(existingItem, 1);
        
        const quantityElement = existingItem.querySelector('.item-quantity');
        const quantity = parseInt(quantityElement.textContent);
        const quantityInput = existingItem.querySelector(`input[name="quantity[${productId}]"]`);
        quantityInput.value = quantity;
    } else {
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
    
    updateOrderTotal();
}

// Update item quantity
function updateItemQuantity(orderItem, change) {
    const quantityElement = orderItem.querySelector('.item-quantity');
    const priceElement = orderItem.querySelector('.item-price');
    const subtotalElement = orderItem.querySelector('.item-subtotal');
    const quantityInput = orderItem.querySelector(`input[name^="quantity"]`);
    
    let quantity = parseInt(quantityElement.textContent);
    const price = parseFloat(priceElement.textContent.replace(/[^\d]/g, '')); // Lấy giá trị số, không chia thêm
    
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

// Toggle promo codes display
function togglePromosDisplay() {
    const availablePromos = document.getElementById('available_promos');
    
    if (availablePromos.style.display === 'none') {
        // Fetch and display available promo codes
        fetchAvailablePromos();
        availablePromos.style.display = 'block';
    } else {
        availablePromos.style.display = 'none';
    }
}

// Fetch available promo codes
function fetchAvailablePromos() {
    const availablePromos = document.getElementById('available_promos');
    availablePromos.innerHTML = '<p>Đang tải mã khuyến mãi...</p>';
    
    // Use the simplified endpoint for better error reporting
    const timestamp = new Date().getTime(); // Thêm timestamp để tránh cache
    const apiPath = window.apiBasePath ? 
                  window.apiBasePath + 'get_promos_simple.php?t=' + timestamp : 
                  '../staff/api/get_promos_simple.php?t=' + timestamp;
    
    console.log('Using API path:', apiPath);
    
    fetch(apiPath)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Promo data received:', data);
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            if (data.promos && data.promos.length > 0) {
                displayPromoCodes(data.promos);
            } else {
                availablePromos.innerHTML = '<p>Không có mã khuyến mãi nào hiện hành.</p>';
            }
        })
        .catch(error => {
            console.error('Error fetching promo codes:', error);
            availablePromos.innerHTML = `<p>Lỗi khi tải mã khuyến mãi: ${error.message}</p>`;
        });
}

// Display promo codes
function displayPromoCodes(promos) {
    const availablePromos = document.getElementById('available_promos');
    availablePromos.innerHTML = '';
    
    promos.forEach(promo => {
        const promoItem = document.createElement('div');
        promoItem.classList.add('promo-item');
        promoItem.setAttribute('data-promo-id', promo.promo_id);
        promoItem.setAttribute('data-promo-code', promo.promo_code);
        promoItem.setAttribute('data-discount-value', promo.discount_value);
        
        const endDate = new Date(promo.end_date);
        const formattedDate = endDate.toLocaleDateString('vi-VN');
        
        promoItem.innerHTML = `
            <div class="promo-item-header">
                <span class="promo-code">${promo.promo_code}</span>
                <span class="promo-expiry">HSD: ${formattedDate}</span>
            </div>
            <div class="promo-description">${promo.description || 'Không có mô tả'}</div>
            <div class="promo-value">Giảm: ${formatCurrency(promo.discount_value)}</div>
        `;
        
        promoItem.addEventListener('click', function() {
            selectPromoCode(promoItem);
        });
        
        availablePromos.appendChild(promoItem);
    });
}

// Select a promo code
function selectPromoCode(promoItem) {
    const promoCode = promoItem.getAttribute('data-promo-code');
    const promoId = promoItem.getAttribute('data-promo-id');
    const discountValue = promoItem.getAttribute('data-discount-value');
    
    // Fill the promo code input
    document.getElementById('promo_code').value = promoCode;
    
    // Apply the promo
    document.getElementById('promo_id').value = promoId;
    document.getElementById('discount_value').value = discountValue;
    
    // Update discount info
    updateDiscountInfo(promoCode, discountValue);
    
    // Hide the promo display
    document.getElementById('available_promos').style.display = 'none';
    
    // Update order total
    updateOrderTotal();
    
    // Show success message
    alert(`Đã áp dụng mã khuyến mãi "${promoCode}"`);
}

// Remove promo code
function removePromoCode() {
    // Clear promo code input
    document.getElementById('promo_code').value = '';
    document.getElementById('promo_id').value = '';
    document.getElementById('discount_value').value = '0';
    
    // Hide discount info
    document.getElementById('discount_info').style.display = 'none';
    
    // Update order total
    updateOrderTotal();
    
    // Notify user
    alert('Đã bỏ mã khuyến mãi');
}

// Update discount info display
function updateDiscountInfo(promoCode, discountValue) {
    const discountInfo = document.getElementById('discount_info');
    const discountContent = discountInfo.querySelector('.discount-info-content');
    
    discountContent.textContent = `Mã: ${promoCode} - Giảm: ${formatCurrency(discountValue)}`;
    discountInfo.style.display = 'flex';
}

// Apply promo code (modified to work with manual input as well)
function applyPromoCode() {
    const promoCodeInput = document.getElementById('promo_code');
    const promoCode = promoCodeInput.value.trim();
    
    if (!promoCode) {
        alert('Vui lòng nhập mã khuyến mãi');
        return;
    }
    
    // Determine API path - use window.apiBasePath if defined, otherwise use default path
    const apiPath = window.apiBasePath ? window.apiBasePath + 'validate_promo.php' : '../staff/api/validate_promo.php';
    
    fetch(apiPath, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `promo_code=${promoCode}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.valid) {
            document.getElementById('promo_id').value = data.promo_id;
            document.getElementById('discount_value').value = data.discount_value;
            
            // Update discount info
            updateDiscountInfo(promoCode, data.discount_value);
            
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
    const submitButton = document.querySelector('button[name="submit_order"]');
    
    console.log('Starting checkout process...');
    console.log('Table ID:', tableId);
    console.log('Payment Method:', paymentMethod);
    console.log('Order items count:', orderItems.length);
    console.log('Submit button found:', !!submitButton);
    
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
    
    if (paymentMethod !== 'Tiền mặt') {
        const transactionCode = document.getElementById('transaction_code').value;
        if (!transactionCode) {
            alert('Vui lòng nhập mã giao dịch');
            return;
        }
    }
    
    // Use the submit button directly instead of form submit
    if (submitButton) {
        console.log('Submitting order form using button click...');
        submitButton.click();
    } else {
        console.error('Submit button not found! Falling back to form submit...');
        // Fallback to form submit
        orderForm.submit();
    }
}

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
}

// Save current table order
function saveCurrentTableOrder(tableId) {
    const orderItems = document.querySelectorAll('.order-item');
    if (orderItems.length > 0) {
        // Create a structure to store order items
        const orderData = {
            items: [],
            promoId: document.getElementById('promo_id').value,
            promoCode: document.getElementById('promo_code').value,
            discountValue: document.getElementById('discount_value').value,
            paymentMethod: document.getElementById('payment_method').value,
            orderNotes: document.getElementById('order_notes').value
        };
        
        // Store each order item
        orderItems.forEach(item => {
            const itemData = {
                productId: item.getAttribute('data-id'),
                productName: item.querySelector('.item-name').textContent,
                price: item.querySelector('.item-price').textContent,
                quantity: parseInt(item.querySelector('.item-quantity').textContent),
                subtotal: item.querySelector('.item-subtotal').textContent
            };
            orderData.items.push(itemData);
        });
        
        // Save the order for this table
        tableOrders[tableId] = orderData;
        
        // Mark table as occupied
        const tableItem = document.querySelector(`.table-item[data-id="${tableId}"]`);
        if (tableItem) {
            tableItem.classList.add('occupied');
        }
    }
}

// Load table order
function loadTableOrder(tableId) {
    // Clear current order first
    clearOrderForm();
    
    // Load the order for this table if it exists
    if (tableOrders[tableId]) {
        const orderData = tableOrders[tableId];
        
        // Set promo and payment information
        document.getElementById('promo_id').value = orderData.promoId || '';
        document.getElementById('promo_code').value = orderData.promoCode || '';
        document.getElementById('discount_value').value = orderData.discountValue || '0';
        document.getElementById('payment_method').value = orderData.paymentMethod || '';
        
        // Set order notes if available
        if (orderData.orderNotes) {
            document.getElementById('order_notes').value = orderData.orderNotes;
        }
        
        // Show discount info if applicable
        if (orderData.discountValue && parseFloat(orderData.discountValue) > 0 && orderData.promoCode) {
            updateDiscountInfo(orderData.promoCode, orderData.discountValue);
        }
        
        // Restore payment method selection
        if (orderData.paymentMethod) {
            const paymentMethods = document.querySelectorAll('.payment-method');
            paymentMethods.forEach(method => {
                if (method.getAttribute('data-method') === orderData.paymentMethod) {
                    method.classList.add('active');
                    
                    // Show transaction code field if necessary
                    if (orderData.paymentMethod !== 'Tiền mặt') {
                        const transactionCodeField = document.getElementById('transaction_code_field');
                        if (transactionCodeField) {
                            transactionCodeField.style.display = 'block';
                        }
                    }
                }
            });
        }
        
        // Add each item to the order
        const orderItems = document.querySelector('.order-items');
        orderData.items.forEach(item => {
            const orderItem = document.createElement('div');
            orderItem.classList.add('order-item');
            orderItem.setAttribute('data-id', item.productId);
            
            // Extract product name and size from full name
            const nameParts = item.productName.match(/(.*) \((.*)\)/);
            const productName = nameParts ? nameParts[1] : item.productName;
            const productSize = nameParts ? nameParts[2] : '';
            
            orderItem.innerHTML = `
                <div class="item-details">
                    <span class="item-name">${item.productName}</span>
                    <span class="item-price">${item.price}</span>
                </div>
                <div class="item-actions">
                    <button type="button" class="btn-decrease">-</button>
                    <span class="item-quantity">${item.quantity}</span>
                    <button type="button" class="btn-increase">+</button>
                    <span class="item-subtotal">${item.subtotal}</span>
                    <button type="button" class="btn-remove">×</button>
                </div>
                <input type="hidden" name="product_id[]" value="${item.productId}">
                <input type="hidden" name="quantity[${item.productId}]" value="${item.quantity}">
                <input type="hidden" name="price[${item.productId}]" value="${item.price.replace(/[^\d]/g, '')}">
            `;
            
            orderItems.appendChild(orderItem);
            
            // Add event listeners for buttons
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
        });
        
        // Update the total
        updateOrderTotal();
    }
}

// Clear the order form
function clearOrderForm() {
    // Clear order items
    const orderItems = document.querySelector('.order-items');
    if (orderItems) {
        orderItems.innerHTML = '';
    }
    
    // Reset promo and discount
    document.getElementById('promo_id').value = '';
    document.getElementById('promo_code').value = '';
    document.getElementById('discount_value').value = '0';
    document.getElementById('payment_method').value = '';
    
    // Reset payment method selection
    const paymentMethods = document.querySelectorAll('.payment-method');
    paymentMethods.forEach(method => {
        method.classList.remove('active');
    });
    
    // Clear notes
    const notesField = document.getElementById('order_notes');
    if (notesField) {
        notesField.value = '';
    }
    
    // Hide discount info
    const discountInfo = document.getElementById('discount_info');
    if (discountInfo) {
        discountInfo.style.display = 'none';
    }
    
    // Hide transaction code field
    const transactionCodeField = document.getElementById('transaction_code_field');
    if (transactionCodeField) {
        transactionCodeField.style.display = 'none';
    }
    
    // Update total
    updateOrderTotal();
}