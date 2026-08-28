/* ============================================================
   CARRITO - persistente en la BASE DE DATOS (tabla carrito)
   Todas las operaciones van al backend via la API.
   ============================================================ */
var cartOpen = false;
var cartCurrentItems = [];

function cartEls() {
  return {
    badge: document.getElementById('cartBadge'),
    countLbl: document.getElementById('cartCountLbl'),
    body: document.getElementById('cartBody'),
    footer: document.getElementById('cartFooter'),
    window: document.getElementById('cartWindow')
  };
}

function openCart() {
  if (chatOpen) closeChat();
  cartOpen = true;
  cartEls().window.classList.add('visible');
  renderCart();
}
function closeCart() { cartOpen = false; cartEls().window.classList.remove('visible'); }

document.getElementById('cartToggle').addEventListener('click', function (e) {
  e.stopPropagation();
  cartOpen ? closeCart() : openCart();
});
document.getElementById('cartClose').addEventListener('click', function (e) { e.stopPropagation(); closeCart(); });

function cartCount() {
  var n = 0;
  cartCurrentItems.forEach(function (i) { n += i.cantidad; });
  return n;
}

function updateCartBadge() {
  var el = cartEls().badge;
  if (!el) return;
  var n = cartCount();
  el.style.display = (n > 0 && currentUser) ? 'flex' : 'none';
  el.textContent = n;
}

/* Carga el carrito desde la BD */
function refreshCart() {
  if (!currentUser) { cartCurrentItems = []; return Promise.resolve([]); }
  return apiGet('carrito').then(function (data) {
    cartCurrentItems = data.items || [];
    return cartCurrentItems;
  }).catch(function () {
    cartCurrentItems = [];
    return cartCurrentItems;
  }).then(function () {
    updateCartBadge();
    if (cartOpen) renderCart();
  });
}

function addToCart(pid) {
  if (!currentUser) {
    showToast(t('cart_you_need'), 'error');
    showSection('auth');
    return;
  }
  apiSend('POST', 'carrito', { producto_id: pid, cantidad: 1 }).then(function (data) {
    var p = allProducts.find(function (x) { return x.id === pid; });
    showToast((p ? p.nombre : 'Producto') + ' ' + t('cart_added'), 'success');
    return refreshCart();
  }).catch(function (err) {
    showToast(err.message, 'error');
  });
}

function changeQty(pid, delta) {
  var item = cartCurrentItems.find(function (i) { return i.id === pid; });
  if (!item) return;
  var nq = item.cantidad + delta;
  if (nq < 1) return;
  apiSend('PUT', 'carrito', { producto_id: pid, cantidad: nq }).then(function () {
    return refreshCart();
  }).catch(function (err) {
    showToast(err.message, 'error');
  });
}

function removeFromCart(pid) {
  apiSend('DELETE', 'carrito', { producto_id: pid }).then(function () {
    showToast(t('cart_removed'), 'info');
    return refreshCart();
  }).catch(function (err) { showToast(err.message, 'error'); });
}

function cartTotals() {
  var total = 0, items = 0;
  cartCurrentItems.forEach(function (it) {
    total += Number(it.precio) * it.cantidad;
    items += it.cantidad;
  });
  return { total: total, items: items };
}

function renderCart() {
  var els = cartEls();
  var tot = cartTotals();
  els.countLbl.textContent = tot.items > 0 ? '(' + tot.items + ' ' + t('cart_items') + ')' : '';

  if (!currentUser) {
    els.body.innerHTML = '<div class="cart-msg"><i class="fas fa-lock"></i><p>' +
      t('cart_login_1') + '<br><b>' + t('cart_login_2') + '</b><br>' + t('cart_login_3') + '</p>' +
      '<button class="btn-green" style="font-size:9px;padding:10px 18px;" onclick="closeCart();showSection(\'auth\');">' + t('cart_login_btn') + '</button></div>';
    els.footer.innerHTML = '';
    updateCartBadge();
    return;
  }

  if (cartCurrentItems.length === 0) {
    els.body.innerHTML = '<div class="cart-msg"><i class="fas fa-cart-shopping"></i><p>' +
      t('cart_empty') + '</p>' +
      '<button class="btn-primary" style="font-size:9px;padding:10px 18px;" onclick="closeCart();showSection(\'products\');">' + t('cart_see_products') + '</button></div>';
    els.footer.innerHTML = '';
    updateCartBadge();
    return;
  }

  var html = '';
  cartCurrentItems.forEach(function (ci) {
    var atMax = ci.cantidad >= ci.stock;
    html += '<div class="cart-item">' +
      '<img class="cart-item-img" src="' + ci.imagen + '" alt="">' +
      '<div class="cart-item-info">' +
        '<div class="cart-item-name">' + escapeHtml(ci.nombre) + '</div>' +
        '<div class="cart-item-price">' + formatPrice(ci.precio) + ' ' + t('per_unit') + '</div>' +
        '<div class="cart-item-subtotal">' + formatPrice(ci.precio * ci.cantidad) + '</div>' +
        (atMax ? '<div class="stock-limit-note"><i class="fas fa-triangle-exclamation"></i> ' + t('max_stock', { n: ci.stock }) + '</div>' : '') +
      '</div>' +
      '<div class="qty-controls">' +
        '<button class="qty-btn" ' + (ci.cantidad <= 1 ? 'disabled' : '') + ' onclick="changeQty(' + ci.id + ',-1)"><i class="fas fa-minus"></i></button>' +
        '<span class="qty-num">' + ci.cantidad + '</span>' +
        '<button class="qty-btn" ' + (atMax ? 'disabled' : '') + ' onclick="changeQty(' + ci.id + ',1)"><i class="fas fa-plus"></i></button>' +
      '</div>' +
      '<button class="cart-item-remove" onclick="removeFromCart(' + ci.id + ')" title="' + t('cart_remove') + '"><i class="fas fa-trash-can"></i></button>' +
    '</div>';
  });
  els.body.innerHTML = html;
  els.footer.innerHTML =
    '<div class="cart-total-row"><span class="ct-label">' + t('cart_total') + ' (' + currentCurrency + ')</span><span class="ct-val">' + formatPrice(tot.total) + '</span></div>' +
    '<button class="btn-primary" style="width:100%;" onclick="startCheckout()"><i class="fas fa-credit-card"></i> ' + t('cart_checkout') + '</button>';
  updateCartBadge();
}

/* CHECKOUT */
function startCheckout() {
  if (!currentUser) { showToast(t('cart_you_need'), 'error'); showSection('auth'); return; }
  var tot = cartTotals();
  if (tot.items === 0) return;
  var els = cartEls();
  var itemsHtml = '';
  cartCurrentItems.forEach(function (ci) {
    itemsHtml += '<div class="co-row"><span>' + escapeHtml(ci.nombre) + ' x' + ci.cantidad + '</span><b>' + formatPrice(ci.precio * ci.cantidad) + '</b></div>';
  });
  els.body.innerHTML = '<div class="checkout-summary">' +
    '<h5>' + t('co_summary') + '</h5>' + itemsHtml +
    '<div class="co-row" style="border-top:1px solid var(--border);margin-top:8px;padding-top:10px;"><span><b>' + t('cart_total') + '</b></span><b style="color:var(--red-g);font-size:15px;">' + formatPrice(tot.total) + '</b></div>' +
    '<h5 style="margin-top:16px;">' + t('co_ship_to') + '</h5>' +
    '<div class="co-row"><span>' + t('co_address') + '</span><b>' + (currentUser.direccion || t('co_no_address')) + '</b></div>' +
    '<p style="font-size:10px;color:var(--txt-d);margin-top:12px;"><i class="fas fa-lock" style="color:var(--green);"></i> ' + t('co_note') + '</p>' +
  '</div>';
  els.footer.innerHTML =
    '<button class="btn-green" style="width:100%;margin-bottom:8px;" onclick="confirmCheckout()"><i class="fas fa-check"></i> ' + t('co_confirm') + '</button>' +
    '<button class="btn-secondary" style="width:100%;font-size:9px;padding:9px;" onclick="renderCart()">' + t('co_back_cart') + '</button>';
}

function confirmCheckout() {
  apiSend('POST', 'pedidos', {}).then(function (data) {
    var tot = cartTotals();
    var xpGained = Math.round(tot.total);
    var els = cartEls();
    els.body.innerHTML = '<div class="success-panel">' +
      '<div class="sp-icon"><i class="fas fa-circle-check"></i></div>' +
      '<h4>' + t('co_success_title') + '</h4>' +
      '<div class="order-code">' + data.codigo + '</div>' +
      '<p>Total: <b style="color:var(--red-g);">' + formatPrice(data.total) + '</b><br>' +
      t('co_xp', { n: xpGained }) + '<br>' +
      t('co_email_note', { email: currentUser.email || '' }) + '</p>' +
      '<button class="btn-primary" style="font-size:9px;padding:10px 16px;" onclick="closeCart();showSection(\'profile\',\'orders\');">' + t('co_see_orders') + '</button> ' +
      '<button class="btn-secondary" style="font-size:9px;padding:10px 16px;" onclick="closeCart();showSection(\'products\');">' + t('co_keep_buying') + '</button>' +
    '</div>';
    els.footer.innerHTML = '';
    cartCurrentItems = [];
    updateCartBadge();
    renderFeatured();
    renderProducts(currentFilter);
    showToast(data.message, 'success');
  }).catch(function (err) {
    showToast(err.message, 'error');
    renderCart();
  });
}
