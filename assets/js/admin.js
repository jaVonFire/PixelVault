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
      if (this.dataset.tab === 'users') renderAdminUsers();
      if (this.dataset.tab === 'chat') renderAdminChat();
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
    /* El producto no esta en memoria: recargar catalogo y reintentar */
    loadProducts(true).then(function () {
      var reloaded = allProducts.find(function (x) { return String(x.id) === String(id); });
      if (reloaded) {
        showProductForm(id);
      } else {
        showToast('No se encontro el producto con ID ' + id, 'error');
        renderAdminProducts();
      }
    });
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

/* ============ USUARIOS (lista + eliminar) ============
   El administrador NO crea ni edita usuarios: solo ve el listado
   y puede ELIMINARLOS. Al eliminar un usuario se borran tambien
   sus pedidos (el backend lo hace en una transaccion). */
function renderAdminUsers() {
  var cont = document.getElementById('admin-users');
  cont.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>' + t('loading') + '</p></div>';

  apiGet('clientes').then(function (data) {
    var users = data.clientes || [];
    var yoId = currentUser ? String(currentUser.id) : '';

    var head = '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px;">' +
      '<h3 style="font-size:13px;color:var(--blue-g);"><i class="fas fa-users"></i> ' + t('admin_users_title') + ' (' + users.length + ')</h3>' +
      '</div>';

    var rows = users.map(function (u) {
      var esYo = String(u.id) === yoId;
      var rolTag = u.rol === 'admin'
        ? '<span class="tag sen">' + t('admin_role_admin') + '</span>'
        : '<span class="tag pub">' + t('admin_role_cliente') + '</span>';
      var acciones = esYo
        ? '<span style="font-size:10px;color:var(--txt-d);">' + t('admin_user_self') + '</span>'
        : '<button class="reveal-btn" style="color:var(--red-g);" onclick="deleteUser(' + u.id + ',\'' + encodeURIComponent(u.username) + '\')"><i class="fas fa-trash-can"></i> ' + t('admin_action_delete') + '</button>';
      return '<tr>' +
        '<td>' + u.id + '</td>' +
        '<td>@' + escapeHtml(u.username) + '</td>' +
        '<td>' + (u.displayname ? escapeHtml(u.displayname) : '-') + '</td>' +
        '<td>' + escapeHtml(u.email) + '</td>' +
        '<td>' + rolTag + '</td>' +
        '<td>' + (u.pais ? escapeHtml(u.pais) : '-') + '</td>' +
        '<td>' + new Date(u.creado_el).toLocaleDateString(currentLang() === 'es' ? 'es' : 'en') + '</td>' +
        '<td style="white-space:nowrap;">' + acciones + '</td>' +
      '</tr>';
    }).join('');

    cont.innerHTML = head +
      '<div class="data-info"><i class="fas fa-shield-halved" style="color:var(--gold);margin-right:8px;"></i> ' + t('admin_users_note') + '</div>' +
      '<div style="overflow-x:auto;"><table class="report-table">' +
      '<tr><th>ID</th><th>' + t('profile_username') + '</th><th>' + t('profile_displayname') + '</th><th>' + t('profile_email') + '</th><th>' + (currentLang() === 'es' ? 'Rol' : 'Role') + '</th><th>' + (currentLang() === 'es' ? 'Pais' : 'Country') + '</th><th>' + t('chat_date') + '</th><th>' + (currentLang() === 'es' ? 'Acciones' : 'Actions') + '</th></tr>' +
      (rows || '<tr><td colspan="8">' + t('admin_no_data') + '</td></tr>') +
      '</table></div>';
  }).catch(function (err) {
    cont.innerHTML = '<p style="color:var(--red-g);">' + err.message + '</p>';
  });
}

function deleteUser(id, usernameEnc) {
  var username = decodeURIComponent(usernameEnc || '');
  var ok = confirm(t('admin_confirm_delete_user', { name: username }));
  if (!ok) return;
  apiSend('DELETE', 'clientes', null, { id: id }).then(function (data) {
    showToast(data.message || t('admin_delete_user_ok'), 'success');
    renderAdminUsers();
  }).catch(function (err) {
    showToast(err.message, 'error');
  });
}

/* ============ BITACORA DEL CHATBOT IA (la escribe n8n) ============ */
var CHAT_FUENTE_COLOR = { n8n: 'var(--green)', guardarrail: 'var(--gold)', respaldo: 'var(--red-g)' };

function renderAdminChat() {
  var cont = document.getElementById('admin-chat');
  cont.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>' + t('loading') + '</p></div>';
  apiGet('chat_log').then(function (d) {
    var maxDia = (d.por_dia || []).reduce(function (m, x) { return Math.max(m, Number(x.n)); }, 0) || 1;

    var barras = (d.por_dia || []).map(function (x) {
      var pct = Math.round((Number(x.n) / maxDia) * 100);
      return '<div style="display:flex;align-items:center;gap:8px;font-size:10px;">' +
        '<span style="width:78px;color:var(--txt-d);">' + x.dia + '</span>' +
        '<div style="flex:1;height:14px;background:var(--bg);border:1px solid var(--border);">' +
        '<div style="height:100%;width:' + pct + '%;background:var(--blue);"></div></div>' +
        '<b style="width:30px;text-align:right;">' + x.n + '</b></div>';
    }).join('') || '<p style="font-size:11px;color:var(--txt-d);">' + t('admin_no_data') + '</p>';

    var fuentes = ['n8n', 'guardarrail', 'respaldo'].map(function (f) {
      var n = (d.fuentes && d.fuentes[f]) || 0;
      var pct = d.total ? Math.round((n / d.total) * 100) : 0;
      return '<tr><td><span style="color:' + CHAT_FUENTE_COLOR[f] + ';">&#9679;</span> ' + t('chat_fuente_' + f) + '</td>' +
        '<td>' + n + '</td><td>' + pct + '%</td></tr>';
    }).join('');

    var ultimos = (d.ultimos || []).map(function (r) {
      return '<tr>' +
        '<td style="font-size:10px;color:var(--txt-d);white-space:nowrap;">' + new Date(r.creado_el).toLocaleString(currentLang() === 'es' ? 'es' : 'en') + '</td>' +
        '<td>' + (r.usuario ? escapeHtml(r.usuario) : '<i style="color:var(--txt-d);">anonimo</i>') + '</td>' +
        '<td style="max-width:260px;">' + escapeHtml(r.mensaje) + '</td>' +
        '<td style="max-width:320px;color:var(--txt-d);">' + escapeHtml(r.reply) + '</td>' +
        '<td style="color:' + (CHAT_FUENTE_COLOR[r.fuente] || 'var(--txt)') + ';font-size:10px;">' + t('chat_fuente_' + r.fuente) + '</td>' +
        '</tr>';
    }).join('');

    cont.innerHTML =
      '<h3 style="font-size:13px;color:var(--blue-g);margin-bottom:14px;"><i class="fas fa-robot"></i> ' + t('admin_chatlog_title') + '</h3>' +
      '<div class="dash-cards" style="margin-bottom:16px;">' +
        dashCard('fa-message-dots', 'admin_chatlog_total', d.total, 'var(--blue)') +
        dashCard('fa-bolt', 'admin_chatlog_today', d.hoy, 'var(--gold)') +
        dashCard('fa-comments', 'admin_chatlog_convs', d.conversaciones, 'var(--green)') +
        dashCard('fa-user-check', 'admin_chatlog_users', d.con_cuenta, 'var(--txt)') +
      '</div>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;" class="admin-2col">' +
        '<div class="report-section"><h3><i class="fas fa-chart-simple"></i> ' + t('admin_chatlog_7days') + '</h3>' + barras + '</div>' +
        '<div class="report-section"><h3><i class="fas fa-circle-half-stroke"></i> ' + t('admin_chatlog_sources') + '</h3>' +
          '<table class="report-table"><tr><th>' + t('admin_chatlog_source') + '</th><th>#</th><th>%</th></tr>' + fuentes + '</table>' +
          '<p style="font-size:9px;color:var(--txt-d);margin-top:10px;">n8n = ' + t('chat_fuente_n8n') +
          ' | ' + t('chat_fuente_guardarrail') + ' | ' + t('chat_fuente_respaldo') + '</p></div>' +
      '</div>' +
      '<div class="report-section" style="margin-top:16px;"><h3><i class="fas fa-clock-rotate-left"></i> ' + t('admin_chatlog_last') + '</h3>' +
        (ultimos
          ? '<div style="overflow-x:auto;"><table class="report-table"><tr><th>' + t('chat_date') + '</th>' +
            '<th>' + t('profile_username') + '</th><th>' + t('admin_chatlog_question') + '</th><th>' + t('admin_chatlog_answer') + '</th><th>' + t('admin_chatlog_source') + '</th></tr>' +
            ultimos + '</table></div>'
          : '<div class="empty-state"><i class="fas fa-inbox"></i><p>' + t('admin_no_data') + '</p></div>') +
      '</div>';
  }).catch(function (err) {
    cont.innerHTML = '<p style="color:var(--red-g);">' + err.message + '</p>';
  });
}
