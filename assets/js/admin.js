/* ============================================================
   PANEL ADMINISTRATIVO - toda la informacion proviene de la
   BASE DE DATOS via la API. Solo accesible con rol 'admin'
   (el backend lo restringe, requiriendo require_admin).
   ============================================================ */

var adminCategories = [];

/* Acceso: solo si el usuario logueado es admin */
function initAdmin() {
  loadMe().then(function (u) {
    if (!u || u.rol !== 'admin') {
      document.getElementById('admin-login-panel').style.display = 'block';
      document.getElementById('admin-dashboard').style.display = 'none';
      return;
    }
    /* Usuario admin desde la propia sesion: entra directo */
    document.getElementById('admin-login-panel').style.display = 'none';
    document.getElementById('admin-dashboard').style.display = 'block';
    setupAdmin();
    renderDashboard();
  });
}

function setupAdmin() {
  /* Cargar categorias para el CRUD de productos */
  apiGet('categorias').then(function (data) {
    adminCategories = data.categorias || [];
  });

  /* Tabs */
  document.querySelectorAll('.admin-tab-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.admin-tab-btn').forEach(function (b) { b.classList.remove('active'); });
      document.querySelectorAll('.admin-tab-content').forEach(function (c) { c.classList.remove('active'); });
      this.classList.add('active');
      var tabEl = document.getElementById('tab-' + this.dataset.tab);
      if (tabEl) tabEl.classList.add('active');
      if (this.dataset.tab === 'overview') renderDashboard();
      if (this.dataset.tab === 'products') renderAdminProducts();
      if (this.dataset.tab === 'orders') renderAdminOrders();
      applyTranslations();
    });
  });
}

function statusLabel(st) {
  var map = {
    pendiente: 'status_pendiente',
    procesando: 'status_procesando',
    enviado: 'status_enviado',
    entregado: 'status_entregado',
    cancelado: 'status_cancelado'
  };
  return map[st] ? t(map[st]) : st;
}

/* ============ RESUMEN / DASHBOARD ============ */
function renderDashboard() {
  apiGet('dashboard').then(function (d) {
    var dateEl = document.getElementById('dash-date');
    if (dateEl) dateEl.innerHTML = '<i class="fas fa-calendar-day"></i> ' +
      new Date().toLocaleDateString(currentLang() === 'es' ? 'es' : 'en', { weekday: 'long', day: 'numeric', month: 'long' });

    var cards = document.getElementById('dash-cards');
    if (cards) {
      cards.innerHTML =
        dashCard('fa-users', 'admin_total_users', d.total_usuarios, 'var(--blue-g)') +
        dashCard('fa-box', 'admin_total_products', d.total_productos, 'var(--cyan)') +
        dashCard('fa-cart-shopping', 'admin_total_orders', d.total_pedidos, 'var(--gold)') +
        dashCard('fa-triangle-exclamation', 'admin_low_stock', d.inventario_bajo.length, 'var(--red-g)');
    }

    /* Estado de pedidos por estado */
    var statusEl = document.getElementById('dash-status');
    if (statusEl) {
      var pe = d.pedidos_por_estado || {};
      var colors = { pendiente:'#F59E0B', procesando:'var(--blue-g)', enviado:'var(--cyan)', entregado:'#10B981', cancelado:'var(--red-g)' };
      var rows = ['pendiente','procesando','enviado','entregado','cancelado'].map(function (s) {
        return '<tr><td><span style="color:' + colors[s] + ';">' + statusLabel(s) + '</span></td><td><b>' + (pe[s] || 0) + '</b></td></tr>';
      }).join('');
      statusEl.innerHTML = '<table class="report-table"><tr><th>' + t('admin_order_status') + '</th><th>' + t('admin_total_orders') + '</th></tr>' + rows + '</table>';
    }

    /* Ultimos pedidos */
    var lastEl = document.getElementById('dash-last-orders');
    if (lastEl) {
      var lp = d.ultimos_pedidos || [];
      lastEl.innerHTML = lp.length
        ? '<table class="report-table"><tr><th>' + t('nav_products') + '</th><th>' + t('profile_username') + '</th><th>' + t('cart_total') + '</th><th>' + t('admin_order_status') + '</th></tr>' +
          lp.map(function (p) {
            return '<tr><td style="color:var(--gold);">' + p.codigo + '</td><td>' + escapeHtml(p.username) + '</td><td>$' + Number(p.total).toFixed(2) + ' USD</td><td>' + statusLabel(p.estado) + '</td></tr>';
          }).join('') + '</table>'
        : '<div class="empty-state"><i class="fas fa-box-open"></i><p>' + t('admin_no_data') + '</p></div>';
    }

    /* Productos con inventario bajo */
    var lowEl = document.getElementById('dash-low-stock');
    if (lowEl) {
      lowEl.innerHTML = d.inventario_bajo.length
        ? '<table class="report-table"><tr><th>' + t('nav_products') + '</th><th>' + t('nav_home') + '</th><th>Alert</th></tr>' +
          d.inventario_bajo.map(function (p) {
            return '<tr><td>' + escapeHtml(p.nombre) + '</td><td>' + p.cantidad + '</td><td>' + t('admin_low_stock') + '</td></tr>';
          }).join('') + '</table>'
        : '<div class="empty-state"><i class="fas fa-check-circle" style="color:var(--green);"></i><p>' + t('admin_no_data') + '</p></div>';
    }
  }).catch(function (err) {
    if (/permisos|login/i.test(err.message)) {
      document.getElementById('admin-login-panel').style.display = 'block';
      document.getElementById('admin-dashboard').style.display = 'none';
    }
  });
}

function dashCard(icon, labelKey, val, color) {
  return '<div class="dash-card"><i class="fas ' + icon + '" style="color:' + color + ';"></i><div class="val">' + val + '</div><div class="lbl">' + t(labelKey) + '</div></div>';
}

/* ============ CRUD DE PRODUCTOS ============ */
function renderAdminProducts() {
  var cont = document.getElementById('admin-products');
  var head = '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px;">' +
    '<h3 style="font-size:13px;color:var(--blue-g);">CRUD - ' + t('nav_products') + '</h3>' +
    '<button class="btn-green" style="font-size:9px;padding:8px 14px;" onclick="showProductForm()"><i class="fas fa-plus"></i> ' + t('admin_action_create') + '</button>' +
    '</div>';

  apiGet('productos', { todos: 1 }).then(function (data) {
    var prods = data.productos || [];
    var rows = prods.map(function (p) {
      var cat = adminCategories.find(function (c) { return String(c.id) === String(p.categoria_id); });
      return '<tr>' +
        '<td>' + p.id + '</td>' +
        '<td>' + escapeHtml(p.nombre) + '</td>' +
        '<td>' + (cat ? escapeHtml(cat.nombre) : p.categoria_id) + '</td>' +
        '<td>$' + Number(p.precio).toFixed(2) + '</td>' +
        '<td>' + p.cantidad + '</td>' +
        '<td>' + p.stock_minimo + '</td>' +
        '<td><span class="tag ' + (p.estado === 'activo' ? 'pub' : 'sen') + '">' + t('estado_' + p.estado) + '</span></td>' +
        '<td style="white-space:nowrap;">' +
          '<button class="reveal-btn" onclick="showProductForm(' + p.id + ')">' + t('admin_action_edit') + '</button> ' +
          '<button class="reveal-btn" style="color:var(--red-g);" onclick="deleteProduct(' + p.id + ')">' + t('admin_action_delete') + '</button>' +
        '</td>' +
      '</tr>';
    }).join('');

    cont.innerHTML = head +
      '<div style="overflow-x:auto;"><table class="report-table">' +
      '<tr><th>ID</th><th>' + (currentLang() === 'es' ? 'Nombre' : 'Name') + '</th><th>' + (currentLang() === 'es' ? 'Categoria' : 'Category') + '</th><th>' + t('cart_total') + '</th><th>Stock</th><th>Min</th><th>' + (currentLang() === 'es' ? 'Estado' : 'Status') + '</th><th>' + (currentLang() === 'es' ? 'Acciones' : 'Actions') + '</th></tr>' +
      (rows || '<tr><td colspan="8">' + t('admin_no_data') + '</td></tr>') +
      '</table></div>';
  }).catch(function (err) { cont.innerHTML = '<p style="color:var(--red-g);">' + err.message + '</p>'; });
}

function showProductForm(id) {
  var cont = document.getElementById('admin-products');
  var isEdit = !!id;
  var prod = isEdit ? allProducts.find(function (x) { return String(x.id) === String(id); }) : null;
  if (isEdit && !prod) {
    /* puede venir de la lista con todos; buscar de nuevo */
    var row = null;
    return;
  }

  var catOptions = adminCategories.map(function (c) {
    return '<option value="' + c.id + '" ' + (prod && String(prod.categoria_id) === String(c.id) ? 'selected' : '') + '>' + escapeHtml(c.nombre) + '</option>';
  }).join('');

  cont.innerHTML =
    '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px;">' +
      '<h3 style="font-size:13px;color:var(--blue-g);">' + (isEdit ? t('admin_action_edit') : t('admin_action_create')) + '</h3>' +
      '<button class="btn-secondary" style="font-size:9px;padding:8px 14px;" onclick="renderAdminProducts()"><i class="fas fa-arrow-left"></i> ' + t('co_back_cart') + '</button>' +
    '</div>' +
    '<div class="data-block">' +
      '<div class="form-row"><div class="form-group"><label>' + (currentLang() === 'es' ? 'Nombre' : 'Name') + '</label><input class="form-input" id="pf-nombre" value="' + (prod ? escapeHtml(prod.nombre) : '') + '"></div>' +
      '<div class="form-group"><label>' + (currentLang() === 'es' ? 'Categoria' : 'Category') + '</label>' +
        '<select class="form-input" id="pf-cat">' + catOptions + '</select></div></div>' +
      '<div class="form-group"><label>' + (currentLang() === 'es' ? 'Descripcion' : 'Description') + '</label><textarea class="form-input" id="pf-desc" rows="2">' + (prod ? escapeHtml(prod.descripcion || '') : '') + '</textarea></div>' +
      '<div class="form-row">' +
        '<div class="form-group"><label>' + t('cart_total') + ' (USD)</label><input type="number" step="0.01" min="0" class="form-input" id="pf-precio" value="' + (prod ? prod.precio : '') + '"></div>' +
        '<div class="form-group"><label>Stock</label><input type="number" min="0" class="form-input" id="pf-cantidad" value="' + (prod ? prod.cantidad : 0) + '"></div>' +
      '</div>' +
      '<div class="form-row">' +
        '<div class="form-group"><label>Stock Min</label><input type="number" min="0" class="form-input" id="pf-min" value="' + (prod ? prod.stock_minimo : 5) + '"></div>' +
        '<div class="form-group"><label>Imagen (URL)</label><input class="form-input" id="pf-imagen" value="' + (prod ? escapeHtml(prod.imagen || '') : '') + '"></div>' +
      '</div>' +
      '<div class="form-row">' +
        '<div class="form-group"><label>' + (currentLang() === 'es' ? 'Estado' : 'Status') + '</label><select class="form-input" id="pf-estado">' +
          '<option value="activo" ' + (prod && prod.estado === 'activo' ? 'selected' : '') + '>' + t('estado_activo') + '</option>' +
          '<option value="inactivo" ' + (prod && prod.estado === 'inactivo' ? 'selected' : '') + '>' + t('estado_inactivo') + '</option>' +
        '</select></div>' +
      '</div>' +
      '<button class="btn-green" style="font-size:10px;padding:10px 20px;" onclick="saveProduct(' + (isEdit ? "'" + id + "'" : 'null') + ')"><i class="fas fa-floppy-disk"></i> ' + t('save') + '</button>' +
    '</div>';
}

function saveProduct(id) {
  var payload = {
    nombre: document.getElementById('pf-nombre').value.trim(),
    descripcion: document.getElementById('pf-desc').value.trim(),
    categoria_id: parseInt(document.getElementById('pf-cat').value, 10),
    precio: parseFloat(document.getElementById('pf-precio').value) || 0,
    cantidad: parseInt(document.getElementById('pf-cantidad').value, 10) || 0,
    stock_minimo: parseInt(document.getElementById('pf-min').value, 10) || 5,
    imagen: document.getElementById('pf-imagen').value.trim(),
    estado: document.getElementById('pf-estado').value
  };

  if (!payload.nombre || !payload.categoria_id) { showToast(t('profile_email_in_use').replace('email','nombre'), 'error'); return; }

  apiSend(id ? 'PUT' : 'POST', 'productos', payload, id ? { id: id } : null)
    .then(function (data) {
      showToast(data.message, 'success');
      return loadProducts(true);
    }).then(function () { renderAdminProducts(); });
}

function deleteProduct(id) {
  if (!confirm(t('admin_action_delete') + ' ID ' + id + '?')) return;
  apiSend('DELETE', 'productos', null, { id: id }).then(function (data) {
    showToast(data.message, 'success');
    return loadProducts(true);
  }).then(function () { renderAdminProducts(); });
}

/* ============ PEDIDOS (consulta + cambio de estado) ============ */
function renderAdminOrders() {
  var cont = document.getElementById('admin-orders');
  apiGet('pedidos').then(function (data) {
    var pedidos = data.pedidos || [];
    var rows = pedidos.map(function (p) {
      var items = (p.items || []).map(function (it) {
        return '<span class="oi-qty">x' + it.cantidad + '</span> ' + escapeHtml(it.nombre_producto);
      }).join('<br>');
      return '<tr>' +
        '<td style="color:var(--gold);">' + p.codigo + '</td>' +
        '<td>' + escapeHtml(p.username) + '</td>' +
        '<td>' + items + '</td>' +
        '<td>$' + Number(p.total).toFixed(2) + '</td>' +
        '<td>' + new Date(p.creado_el).toLocaleString(currentLang() === 'es' ? 'es' : 'en') + '</td>' +
        '<td><select class="form-input" style="padding:6px;" onchange="changeOrderStatus(' + p.id + ', this.value)">' +
          ['pendiente','procesando','enviado','entregado','cancelado'].map(function (s) {
            return '<option value="' + s + '" ' + (p.estado === s ? 'selected' : '') + '>' + statusLabel(s) + '</option>';
          }).join('') +
        '</select></td>' +
      '</tr>';
    }).join('');

    cont.innerHTML = pedidos.length
      ? '<div style="overflow-x:auto;"><table class="report-table">' +
        '<tr><th>Codigo</th><th>' + t('profile_username') + '</th><th>Items</th><th>' + t('cart_total') + '</th><th>Fecha</th><th>' + t('admin_order_status') + '</th></tr>' +
        rows + '</table></div>'
      : '<div class="empty-state"><i class="fas fa-box-open"></i><p>' + t('admin_no_data') + '</p></div>';
  }).catch(function (err) { cont.innerHTML = '<p style="color:var(--red-g);">' + err.message + '</p>'; });
}

function changeOrderStatus(id, estado) {
  apiSend('PUT', 'pedido_estado', { pedido_id: id, estado: estado }).then(function (data) {
    showToast(data.message, 'success');
  }).catch(function (err) { showToast(err.message, 'error'); });
}
