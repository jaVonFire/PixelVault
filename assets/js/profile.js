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
    '<button class="ptab-btn" id="ptabbtn-settings" onclick="showProfileTab(\'settings\')"><i class="fas fa-gear"></i> ' + t('profile_settings') + '</button>' +
  '</div>' +

  '<div class="ptab-content active" id="ptab-data">' +
    '<div class="data-info"><i class="fas fa-shield-halved" style="color:var(--green);margin-right:8px;"></i> ' + t('profile_db_note') + '</div>' +
    '<div class="data-block"><h4><span class="dot" style="background:#10B981;"></span> ' + t('profile_public') + '</h4>' +
      '<div class="data-grid">' +
        '<div class="data-field"><div class="df-label">' + t('profile_username') + '</div><div class="df-val">@' + escapeHtml(u.username) + '</div></div>' +
        '<div class="data-field"><div class="df-label">' + t('profile_displayname') + '</div><div class="df-val">' + (u.displayname ? escapeHtml(u.displayname) : '-') + '</div></div>' +
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
        '<div class="data-field"><div class="df-label">' + t('profile_payment') + '</div><div class="df-val">' + t('profile_not_registered') + '</div></div>' +
      '</div></div>' +
  '</div>' +

  '<div class="ptab-content" id="ptab-orders">' +
    (profileOrders.length > 0 ? profileOrders.map(function (o) {
      var itemsHtml = (o.items || []).map(function (it) {
        return '<span class="oi-qty">x' + it.cantidad + '</span> ' + escapeHtml(it.nombre_producto);
      }).join('<br>');
      return '<div class="order-card">' +
        '<div class="order-head"><div><div class="order-id">' + o.codigo + '</div><div class="order-date">' + fmtDateTime(o.creado_el) + '</div></div>' +
        '<div class="order-total"><div class="df-label" style="font-size:9px;color:var(--txt-d);">' + t('profile_order_total') + '</div><div class="ot-val">' + formatPrice(o.total) + '</div></div></div>' +
        '<div class="order-items">' + itemsHtml + '</div>' +
        '<div style="margin-top:10px;"><span class="tag sem">' + t('status_' + o.estado) + '</span></div>' +
      '</div>';
    }).join('') :
    '<div class="empty-state"><i class="fas fa-box-open"></i><p>' + t('profile_no_orders') + '</p><button class="btn-primary" style="font-size:9px;padding:10px 16px;" onclick="showSection(\'products\')">' + t('profile_go_catalog') + '</button></div>') +
  '</div>' +

  '<div class="ptab-content" id="ptab-settings">' +
    '<div class="data-block"><h4><i class="fas fa-coins" style="color:var(--gold);"></i> ' + t('profile_currency_pref') + '</h4>' +
      '<p style="font-size:12px;color:var(--txt-d);margin-bottom:12px;">' + t('profile_currency_note') + '</p>' +
      '<select class="form-input" id="setCurrency" onchange="onCurrencyChange(this.value)">' +
        Object.keys(CURRENCIES).map(function (c) {
          return '<option value="' + c + '" ' + (currentCurrency === c ? 'selected' : '') + '>' + CURRENCIES[c].name + ' (' + c + ')</option>';
        }).join('') +
      '</select></div>' +
    '<div class="data-block"><h4><i class="fas fa-pen" style="color:var(--blue-g);"></i> ' + t('profile_edit') + '</h4>' +
      '<div class="form-row">' +
        '<div class="form-group"><label>' + t('profile_displayname') + ' <span class="tag pub">' + t('profile_public_tag') + '</span></label><input type="text" class="form-input" id="set-displayname" value="' + (u.displayname || '') + '"></div>' +
      '</div>' +
      '<div class="form-row">' +
        '<div class="form-group"><label>' + t('profile_email') + ' <span class="tag sem">SEMI</span></label><input type="email" class="form-input" id="set-email" value="' + escapeHtml(u.email) + '"></div>' +
        '<div class="form-group"><label>' + t('profile_platform') + ' <span class="tag sem">SEMI</span></label>' +
          '<select class="form-input" id="set-platform">' +
            ['', 'PC', 'PlayStation', 'Xbox', 'Nintendo Switch', 'Mobile'].map(function (p) { return '<option ' + (u.plataforma === p ? 'selected' : '') + '>' + p + '</option>'; }).join('') +
          '</select></div>' +
      '</div>' +
      '<div class="form-row">' +
        '<div class="form-group"><label>' + t('profile_phone') + ' <span class="tag pri">PRIV</span></label><input type="tel" class="form-input" id="set-phone" value="' + (u.telefono || '') + '"></div>' +
        '<div class="form-group"><label>' + t('profile_address') + ' <span class="tag pri">PRIV</span></label><input type="text" class="form-input" id="set-address" value="' + (u.direccion || '') + '"></div>' +
      '</div>' +
      '<button class="btn-green" style="font-size:9px;padding:10px 18px;" onclick="saveSettings()"><i class="fas fa-floppy-disk"></i> ' + t('profile_save') + '</button>' +
      '<p style="font-size:10px;color:var(--txt-d);margin-top:10px;">' + t('profile_sens_not_editable') + '</p>' +
    '</div>' +
  '</div>';
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
  apiSend('PUT', 'me', {
    displayname: document.getElementById('set-displayname').value.trim(),
    email: document.getElementById('set-email').value.trim(),
    plataforma: document.getElementById('set-platform').value,
    telefono: document.getElementById('set-phone').value.trim(),
    direccion: document.getElementById('set-address').value.trim()
  }).then(function (data) {
    if (data.user) currentUser = data.user;
    updateAuthUI();
    renderProfile();
    showToast(t('profile_saved'), 'success');
  }).catch(function (err) {
    showToast(err.message, 'error');
  });
}
