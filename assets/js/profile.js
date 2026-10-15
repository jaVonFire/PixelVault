/* ============================================================
   PERFIL - datos y pedidos provenientes de la BASE DE DATOS
   ============================================================ */

function getUserXP() {
  /* XP igual a la suma de los totales de pedidos del usuario */
  var xp = 0;
  if (currentUser && profileOrders) {
    profileOrders.forEach(function (o) { xp += Number(o.total); });
  }
  return Math.round(xp);
}
function xpForLevel(n) { return Math.pow(n - 1, 2) * 100; }
function userLevel() { var xp = getUserXP(); return Math.floor(Math.sqrt(xp / 100)) + 1; }

var profileOrders = [];
var profileOrdersFilter = 'all';

/* Construye las <option> de un select comparando con el valor actual.
   Si se pasa emptyLabel, agrega una opcion vacia al inicio. */
function opt(list, current, emptyLabel) {
  var html = '';
  if (emptyLabel !== undefined) {
    html += '<option value=""' + ((current === null || current === undefined || current === '') ? ' selected' : '') + '>' + emptyLabel + '</option>';
  }
  html += list.filter(function (x) { return x !== ''; }).map(function (x) {
    return '<option value="' + escapeHtml(x) + '"' + (String(x) === String(current) ? ' selected' : '') + '>' + escapeHtml(x) + '</option>';
  }).join('');
  return html;
}

function fmtDate(s) {
  if (!s) return '';
  var d = new Date(s);
  return d.toLocaleDateString('es');
}
function fmtDateTime(s) {
  if (!s) return '';
  return new Date(s).toLocaleString('es');
}

function renderProfile() {
  var el = document.getElementById('profile-content');
  if (!currentUser) { showSection('auth'); return; }
  var u = currentUser;
  var lvl = userLevel();
  var xp = getUserXP();
  var curLvlXp = xpForLevel(lvl);
  var nextLvlXp = xpForLevel(lvl + 1);
  var pct = Math.min(100, Math.round((xp - curLvlXp) / Math.max(1, (nextLvlXp - curLvlXp)) * 100));

  el.innerHTML =
  '<div class="profile-header">' +
    '<div class="profile-avatar-big">' + escapeHtml((u.username ? u.username.charAt(0) : '?').toUpperCase()) + '</div>' +
    '<div class="profile-head-info">' +
      '<h2>' + (u.displayname ? escapeHtml(u.displayname) : escapeHtml(u.username)) + '</h2>' +
      '<div class="pmeta"><i class="fas fa-at"></i> @' + escapeHtml(u.username) + ' <span class="tag pub">' + t('profile_public_tag') + '</span></div>' +
      '<div class="pmeta"><i class="fas fa-gamepad"></i> ' + (u.plataforma || '-') + '</div>' +
      '<div class="pmeta"><i class="fas fa-calendar"></i> ' + t('profile_since', { date: fmtDate(u.creado_el) }) + ' &middot; ' + t('nav_currency') + ': <b style="color:var(--gold);">' + (u.moneda || 'USD') + '</b></div>' +
    '</div>' +
    '<div class="level-box">' +
      '<div class="lvl-title"><i class="fas fa-bolt"></i> ' + t('profile_level', { n: lvl }) + '</div>' +
      '<div class="xp-bar-track"><div class="xp-bar-fill" style="width:' + pct + '%;"></div></div>' +
      '<div class="lvl-xp">' + t('profile_xp', { xp: xp, n: (nextLvlXp - xp), lvl: (lvl + 1) }) + '</div>' +
    '</div>' +
  '</div>' +

  '<div class="profile-tabs">' +
    '<button class="ptab-btn active" id="ptabbtn-data" onclick="showProfileTab(\'data\')"><i class="fas fa-id-card"></i> ' + t('profile_data') + '</button>' +
    '<button class="ptab-btn" id="ptabbtn-orders" onclick="showProfileTab(\'orders\')"><i class="fas fa-box"></i> ' + t('profile_orders') + ' (' + profileOrders.length + ')</button>' +
    '<button class="ptab-btn" id="ptabbtn-settings" onclick="showProfileTab(\'settings\')"><i class="fas fa-sliders"></i> ' + t('profile_config_tab') + '</button>' +
  '</div>' +

  '<div class="ptab-content active" id="ptab-data">' +
    '<div class="data-info"><i class="fas fa-shield-halved" style="color:var(--green);margin-right:8px;"></i> ' + t('profile_db_note') + '</div>' +
    '<div class="data-block"><h4><span class="dot" style="background:#10B981;"></span> ' + t('profile_public') + '</h4>' +
      '<div class="data-grid">' +
        '<div class="data-field"><div class="df-label">' + t('profile_username') + '</div><div class="df-val">@' + escapeHtml(u.username) + '</div></div>' +
        '<div class="data-field"><div class="df-label">' + t('profile_displayname') + '</div><div class="df-val">' + (u.displayname ? escapeHtml(u.displayname) : '-') + '</div></div>' +
        '<div class="data-field"><div class="df-label">' + t('profile_favgame') + '</div><div class="df-val">' + (u.juego_favorito ? escapeHtml(u.juego_favorito) : '-') + '</div></div>' +
      '</div></div>' +
    '<div class="data-block"><h4><span class="dot" style="background:var(--blue-g);"></span> ' + t('profile_semi') + '</h4>' +
      '<div class="data-grid">' +
        '<div class="data-field"><div class="df-label">' + t('profile_email') + '</div><div class="df-val">' + escapeHtml(u.email) + '</div></div>' +
        '<div class="data-field"><div class="df-label">' + t('profile_country') + '</div><div class="df-val">' + (u.pais || '-') + '</div></div>' +
        '<div class="data-field"><div class="df-label">' + t('profile_age') + '</div><div class="df-val">' + (u.edad || '-') + '</div></div>' +
        '<div class="data-field"><div class="df-label">' + t('profile_platform') + '</div><div class="df-val">' + (u.plataforma || '-') + '</div></div>' +
      '</div></div>' +
    '<div class="data-block"><h4><span class="dot" style="background:#F59E0B;"></span> ' + t('profile_private') + '</h4>' +
      '<div class="data-grid">' +
        '<div class="data-field"><div class="df-label">' + t('profile_phone') + '</div><div class="df-val">' + (u.telefono || t('profile_not_registered')) + '</div></div>' +
        '<div class="data-field"><div class="df-label">' + t('profile_address') + '</div><div class="df-val">' + (u.direccion || t('profile_not_registered')) + '</div></div>' +
      '</div></div>' +
    '<div class="data-block"><h4><span class="dot" style="background:var(--red-g);"></span> ' + t('profile_sensitive') + '</h4>' +
      '<div class="data-grid">' +
        '<div class="data-field"><div class="df-label">' + t('profile_payment') + '</div><div class="df-val">' + (u.metodo_pago ? escapeHtml(u.metodo_pago) : t('profile_not_registered')) + '</div></div>' +
      '</div></div>' +
  '</div>' +

  '<div class="ptab-content" id="ptab-orders">' +
    (profileOrders.length > 0
      ? ordersDashboardHtml()
      : '<div class="empty-state"><i class="fas fa-box-open"></i><p>' + t('profile_no_orders') + '</p><button class="btn-primary" style="font-size:9px;padding:10px 16px;" onclick="showSection(\'products\')">' + t('profile_go_catalog') + '</button></div>') +
  '</div>' +

  '<div class="ptab-content" id="ptab-settings">' +
  (isAdmin()
    ? '<div class="data-info"><i class="fas fa-lock" style="color:var(--gold);margin-right:8px;"></i> ' + t('profile_config_admin') + '</div>'
    : (function () {
        var countries = ['Mexico','Colombia','Argentina','Chile','Peru','Bolivia','Uruguay','Paraguay','Guatemala','Costa Rica','Republica Dominicana','Venezuela','Brasil','Espana','Otro'];
        var ages = ['13-17','18-24','25-34','35-44','45+'];
        var plats = ['PC','PlayStation','Xbox','Nintendo Switch','Mobile'];
        var pays = ['', 'Visa','Mastercard','PayPal','Transferencia','Efectivo'];
        var mono = u.moneda || currentCurrency;

        return '<div class="data-block"><h4><i class="fas fa-id-card" style="color:var(--blue-g);"></i> ' + t('profile_config') + '</h4>' +
          '<p style="font-size:12px;color:var(--txt-d);margin-bottom:12px;">' + t('profile_config_note') + '</p>' +
          '<div class="form-row">' +
            '<div class="form-group"><label>' + t('profile_username') + ' <span class="tag pub">' + t('profile_public_tag') + '</span></label><input type="text" class="form-input" id="set-username" value="' + escapeHtml(u.username) + '"></div>' +
            '<div class="form-group"><label>' + t('profile_displayname') + ' <span class="tag pub">' + t('profile_public_tag') + '</span></label><input type="text" class="form-input" id="set-displayname" value="' + (u.displayname ? escapeHtml(u.displayname) : '') + '"></div>' +
          '</div>' +
          '<div class="form-row">' +
            '<div class="form-group"><label>' + t('profile_favgame') + ' <span class="tag pub">' + t('profile_public_tag') + '</span></label><input type="text" class="form-input" id="set-favgame" value="' + (u.juego_favorito ? escapeHtml(u.juego_favorito) : '') + '"></div>' +
            '<div class="form-group"><label>' + t('profile_email') + ' <span class="tag sem">SEMI</span></label><input type="email" class="form-input" id="set-email" value="' + escapeHtml(u.email) + '"></div>' +
          '</div>' +
          '<div class="form-row">' +
            '<div class="form-group"><label>' + t('profile_country') + ' <span class="tag sem">SEMI</span></label><select class="form-input" id="set-pais">' + opt(countries, u.pais, t('sel_country')) + '</select></div>' +
            '<div class="form-group"><label>' + t('profile_age') + ' <span class="tag sem">SEMI</span></label><select class="form-input" id="set-age">' + opt(ages, u.edad, t('sel_country')) + '</select></div>' +
          '</div>' +
          '<div class="form-row">' +
            '<div class="form-group"><label>' + t('profile_platform') + ' <span class="tag sem">SEMI</span></label><select class="form-input" id="set-platform">' + opt(plats, u.plataforma, t('sel_country')) + '</select></div>' +
            '<div class="form-group"><label>' + t('profile_payment') + ' <span class="tag sen">SENS</span></label><select class="form-input" id="set-payment">' + opt(pays, u.metodo_pago, t('sel_country')) + '</select></div>' +
          '</div>' +
          '<div class="form-row">' +
            '<div class="form-group"><label>' + t('profile_phone') + ' <span class="tag pri">PRIV</span></label><input type="tel" class="form-input" id="set-phone" value="' + (u.telefono ? escapeHtml(u.telefono) : '') + '"></div>' +
            '<div class="form-group"><label>' + t('profile_address') + ' <span class="tag pri">PRIV</span></label><input type="text" class="form-input" id="set-address" value="' + (u.direccion ? escapeHtml(u.direccion) : '') + '"></div>' +
          '</div>' +
          '<div class="form-row">' +
            '<div class="form-group"><label>' + t('profile_currency_pref') + '</label><select class="form-input" id="set-moneda">' +
              Object.keys(CURRENCIES).map(function (c) {
                return '<option value="' + c + '"' + (mono === c ? ' selected' : '') + '>' + CURRENCIES[c].name + ' (' + c + ')</option>';
              }).join('') +
            '</select></div>' +
          '</div>' +
          '<button class="btn-green" style="font-size:9px;padding:10px 18px;" onclick="saveSettings()"><i class="fas fa-floppy-disk"></i> ' + t('profile_save') + '</button>' +
        '</div>' +

        '<div class="data-block"><h4><i class="fas fa-key" style="color:var(--gold);"></i> ' + t('profile_change_pass') + '</h4>' +
          '<p style="font-size:12px;color:var(--txt-d);margin-bottom:12px;">' + t('profile_change_pass_note') + '</p>' +
          '<div class="form-row">' +
            '<div class="form-group"><label>' + t('profile_current_pass') + '</label><input type="password" class="form-input" id="pw-current" autocomplete="current-password"></div>' +
            '<div class="form-group"><label>' + t('profile_new_pass') + '</label><input type="password" class="form-input" id="pw-new" autocomplete="new-password"></div>' +
          '</div>' +
          '<div class="form-row">' +
            '<div class="form-group"><label>' + t('profile_confirm_pass') + '</label><input type="password" class="form-input" id="pw-confirm" autocomplete="new-password"></div>' +
          '</div>' +
          '<button class="btn-secondary" style="font-size:9px;padding:10px 18px;" onclick="changePassword()"><i class="fas fa-key"></i> ' + t('profile_pass_btn') + '</button>' +
        '</div>';
      })()
  ) +
  '</div>';

  renderOrdersList();
}

/* ============================================================
   MIS PEDIDOS - dashboard, filtros, tarjetas y detalle
   ============================================================ */
function statusMeta(estado) {
  var m = {
    pendiente:  { ico: 'fa-hourglass-half' },
    procesando: { ico: 'fa-gears' },
    enviado:    { ico: 'fa-truck-fast' },
    entregado:  { ico: 'fa-circle-check' },
    cancelado:  { ico: 'fa-ban' }
  };
  return m[estado] || { ico: 'fa-circle' };
}

function ordersDashboardHtml() {
  var spent = 0, last = null;
  profileOrders.forEach(function (o) {
    if (o.estado !== 'cancelado') spent += Number(o.total);
    if (!last || new Date(o.creado_el) > new Date(last.creado_el)) last = o;
  });

  var html = '<div class="orders-stats">' +
    '<div class="ostat"><div class="ostat-ico gold"><i class="fas fa-box"></i></div><div><div class="ostat-val">' + profileOrders.length + '</div><div class="ostat-lbl">' + t('profile_stat_orders') + '</div></div></div>' +
    '<div class="ostat"><div class="ostat-ico green"><i class="fas fa-coins"></i></div><div><div class="ostat-val">' + formatPrice(spent) + '</div><div class="ostat-lbl">' + t('profile_stat_spent') + '</div></div></div>' +
    '<div class="ostat"><div class="ostat-ico blue"><i class="fas fa-clock-rotate-left"></i></div><div><div class="ostat-val">' + (last ? fmtDate(last.creado_el) : '-') + '</div><div class="ostat-lbl">' + t('profile_stat_last') + '</div></div></div>' +
  '</div>';

  var chips = '<button class="ofilter" id="ofilter-all" onclick="setOrdersFilter(\'all\')">' + t('profile_filter_all') + ' (' + profileOrders.length + ')</button>';
  ['pendiente', 'procesando', 'enviado', 'entregado', 'cancelado'].forEach(function (s) {
    var n = profileOrders.filter(function (o) { return o.estado === s; }).length;
    if (n > 0) {
      chips += '<button class="ofilter" id="ofilter-' + s + '" onclick="setOrdersFilter(\'' + s + '\')">' + t('status_' + s) + ' (' + n + ')</button>';
    }
  });
  html += '<div class="orders-filters">' + chips + '</div>';
  html += '<div id="orders-list"></div>';
  return html;
}

function orderProgressHtml(estado) {
  var steps = [
    { k: 'pendiente',  ico: 'fa-receipt',      label: t('profile_step_created') },
    { k: 'procesando', ico: 'fa-gears',        label: t('profile_step_processing') },
    { k: 'enviado',    ico: 'fa-truck-fast',   label: t('profile_step_shipped') },
    { k: 'entregado',  ico: 'fa-circle-check', label: t('profile_step_delivered') }
  ];
  var idx = 0;
  steps.forEach(function (s, i) { if (s.k === estado) idx = i; });
  return '<div class="order-progress">' + steps.map(function (s, i) {
    var cls = i < idx ? 'done' : (i === idx ? 'active' : '');
    return '<div class="ostep ' + cls + '"><div class="odot"><i class="fas ' + s.ico + '"></i></div><div class="olabel">' + s.label + '</div></div>';
  }).join('') + '</div>';
}

function orderCardHtml(o) {
  var meta = statusMeta(o.estado);
  var items = o.items || [];
  var count = 0;
  items.forEach(function (it) { count += Number(it.cantidad); });

  var rows = items.map(function (it) {
    var sub = Number(it.precio) * Number(it.cantidad);
    return '<div class="oitem">' +
      '<span class="oitem-name">' + escapeHtml(it.nombre_producto) + '</span>' +
      '<span class="oitem-qty">x' + it.cantidad + '</span>' +
      '<span class="oitem-price">' + formatPrice(it.precio) + '</span>' +
      '<span class="oitem-sub">' + formatPrice(sub) + '</span>' +
    '</div>';
  }).join('');

  var progress = o.estado === 'cancelado'
    ? '<div class="order-cancelled"><i class="fas fa-ban"></i> ' + t('status_cancelado') + '</div>'
    : orderProgressHtml(o.estado);

  return '<div class="order-card">' +
    '<div class="order-head">' +
      '<div>' +
        '<div class="order-id"><i class="fas fa-receipt"></i> ' + escapeHtml(o.codigo) +
          ' <button class="order-copy" title="' + t('profile_order_copy') + '" onclick="copyOrderCode(\'' + escapeHtml(o.codigo) + '\')"><i class="fas fa-copy"></i></button></div>' +
        '<div class="order-date"><i class="fas fa-calendar-day"></i> ' + fmtDateTime(o.creado_el) + '</div>' +
      '</div>' +
      '<div class="order-head-right">' +
        '<span class="order-status ' + o.estado + '"><i class="fas ' + meta.ico + '"></i> ' + t('status_' + o.estado) + '</span>' +
        '<div class="order-total"><div class="df-label" style="font-size:9px;color:var(--txt-d);">' + t('profile_order_total') + '</div><div class="ot-val">' + formatPrice(o.total) + '</div></div>' +
      '</div>' +
    '</div>' +
    progress +
    '<div class="order-items-toggle" onclick="toggleOrderItems(' + o.id + ')"><i class="fas fa-list"></i> ' + t('profile_order_items_count', { n: count }) + ' <i class="fas fa-chevron-down" id="order-chev-' + o.id + '"></i></div>' +
    '<div class="order-items-list" id="order-items-' + o.id + '" style="display:none;">' + rows + '</div>' +
  '</div>';
}

function renderOrdersList() {
  var box = document.getElementById('orders-list');
  if (!box) return;
  var f = profileOrdersFilter;
  var list = profileOrders.filter(function (o) { return f === 'all' || o.estado === f; });

  document.querySelectorAll('.ofilter').forEach(function (b) { b.classList.remove('active'); });
  var ab = document.getElementById('ofilter-' + f);
  if (ab) ab.classList.add('active');

  if (list.length === 0) {
    box.innerHTML = '<div class="empty-state"><i class="fas fa-filter"></i><p>' + t('profile_filter_empty') + '</p></div>';
    return;
  }
  box.innerHTML = list.map(orderCardHtml).join('');
}

function setOrdersFilter(f) {
  profileOrdersFilter = f;
  renderOrdersList();
}

function toggleOrderItems(id) {
  var box = document.getElementById('order-items-' + id);
  var chev = document.getElementById('order-chev-' + id);
  if (!box) return;
  var open = box.style.display !== 'none';
  box.style.display = open ? 'none' : 'block';
  if (chev) {
    chev.classList.toggle('fa-chevron-down', open);
    chev.classList.toggle('fa-chevron-up', !open);
  }
}

function copyOrderCode(code) {
  var done = function () { showToast(t('profile_order_copied'), 'success'); };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(code).then(done).catch(done);
  } else {
    done();
  }
}

function showProfileTab(tab) {
  document.querySelectorAll('.ptab-btn').forEach(function (b) { b.classList.remove('active'); });
  document.querySelectorAll('.ptab-content').forEach(function (c) { c.classList.remove('active'); });
  var btn = document.getElementById('ptabbtn-' + tab);
  if (btn) btn.classList.add('active');
  var ct = document.getElementById('ptab-' + tab);
  if (ct) ct.classList.add('active');
}

function saveSettings() {
  if (!currentUser) return;
  var val = function (id) { var el = document.getElementById(id); return el ? el.value : ''; };
  var moneda = val('set-moneda');

  apiSend('PUT', 'me', {
    username: val('set-username').trim(),
    displayname: val('set-displayname').trim(),
    juego_favorito: val('set-favgame').trim(),
    email: val('set-email').trim(),
    pais: val('set-pais'),
    edad: val('set-age'),
    plataforma: val('set-platform'),
    telefono: val('set-phone').trim(),
    direccion: val('set-address').trim(),
    moneda: moneda,
    metodo_pago: val('set-payment')
  }).then(function (data) {
    if (data.user) currentUser = data.user;
    if (moneda) { currentCurrency = moneda; setStore('currency', moneda); }
    updateAuthUI();
    refreshAllPrices();
    showProfileTab('settings');
    showToast(t('profile_saved'), 'success');
  }).catch(function (err) {
    showToast(err.message, 'error');
  });
}

function changePassword() {
  if (!currentUser) return;
  var cur = document.getElementById('pw-current').value;
  var nw = document.getElementById('pw-new').value;
  var cf = document.getElementById('pw-confirm').value;

  if (!cur || !nw) { showToast(t('profile_pass_required'), 'error'); return; }
  if (nw !== cf) { showToast(t('profile_pass_mismatch'), 'error'); return; }

  apiSend('PUT', 'password', {
    current_password: cur,
    new_password: nw,
    password2: cf
  }).then(function (data) {
    document.getElementById('pw-current').value = '';
    document.getElementById('pw-new').value = '';
    document.getElementById('pw-confirm').value = '';
    showToast(data.message || t('profile_pass_changed'), 'success');
  }).catch(function (err) {
    showToast(err.message, 'error');
  });
}
