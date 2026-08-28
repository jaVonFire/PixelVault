/* ============================================================
   AUTENTICACION - conecta con la API (backend PHP + MySQL)
   Roles: 'cliente' | 'admin'. El backend valida las credenciales
   (password_hash) y restringe acciones de admin.
   ============================================================ */

/* Usuario logueado (cache local de la sesion del servidor) */
var currentUser = null;

/* Carga el usuario actual desde el backend (session PHP) */
function loadMe() {
  return apiGet('me').then(function (data) {
    /* El endpoint 'me' devuelve el usuario directo; login/register lo
       envuelven en {user: {...}}. Aceptamos ambos formatos. */
    currentUser = (data && data.user) ? data.user : (data || null);
    return currentUser;
  }).catch(function () {
    currentUser = null;
    return null;
  });
}

function isAdmin() {
  return currentUser && currentUser.rol === 'admin';
}

function syncRatingUI() {
  updateRateButtonState();
  if (typeof ratingAreaEl !== 'undefined' && ratingAreaEl) ratingAreaEl.classList.remove('expanded');
}

function updateAuthUI() {
  var area = document.getElementById('navUserArea');
  var navAuthBtn = document.getElementById('nav-auth');
  var navAdminBtn = document.getElementById('nav-admin');
  if (!area) return;

  if (currentUser) {
    area.innerHTML = '<div class="nav-user" id="navUser">' +
      '<div class="nav-user-chip" id="navUserChip">' +
        '<div class="nav-user-avatar">' + escapeHtml((currentUser.username ? currentUser.username.charAt(0) : '?').toUpperCase()) + '</div>' +
        '<span class="nav-user-name">' + escapeHtml(currentUser.username) + '</span>' +
        (currentUser.rol === 'admin' ? '<span class="nav-user-role">ADMIN</span>' : '') +
        '<i class="fas fa-chevron-down nav-user-arrow"></i>' +
      '</div>' +
      '<div class="user-dropdown">' +
        '<button onclick="showSection(\'profile\');closeUserDrop();"><i class="fas fa-id-badge" style="color:var(--cyan);"></i> ' + t('nav_profile') + '</button>' +
        '<button onclick="showSection(\'profile\',\'settings\');closeUserDrop();"><i class="fas fa-gear" style="color:var(--blue-g);"></i> ' + t('nav_settings') + '</button>' +
        '<button class="danger" onclick="logout()"><i class="fas fa-right-from-bracket"></i> ' + t('nav_logout') + '</button>' +
      '</div></div>';
    var chip = document.getElementById('navUserChip');
    if (chip) {
      chip.addEventListener('click', function (e) {
        e.stopPropagation();
        document.getElementById('navUser').classList.toggle('open');
      });
    }
    if (navAuthBtn) navAuthBtn.style.display = 'none';
    if (navAdminBtn) navAdminBtn.style.display = currentUser.rol === 'admin' ? '' : 'none';
  } else {
    area.innerHTML = '';
    if (navAuthBtn) navAuthBtn.style.display = '';
    if (navAdminBtn) navAdminBtn.style.display = '';
  }
  if (typeof updateCartBadge === 'function') updateCartBadge();
}

function closeUserDrop() {
  var el = document.getElementById('navUser');
  if (el) el.classList.remove('open');
}
document.addEventListener('click', function () { closeUserDrop(); });

function showAuthTab(which) {
  document.getElementById('tabbtn-login').classList.toggle('active', which === 'login');
  document.getElementById('tabbtn-register').classList.toggle('active', which === 'register');
  document.getElementById('auth-login').classList.toggle('active', which === 'login');
  document.getElementById('auth-register').classList.toggle('active', which === 'register');
}
function goToRegister() { showSection('auth', 'register'); }

function authError(id, msg) {
  var el = document.getElementById(id);
  if (!el) return;
  el.textContent = msg;
  el.style.display = 'block';
  setTimeout(function () { el.style.display = 'none'; }, 5000);
}

function getVal(id) {
  var el = document.getElementById(id);
  return el ? el.value : '';
}
function setVal(id, v) {
  var el = document.getElementById(id);
  if (el) el.value = v;
}

function handleRegister(e) {
  e.preventDefault();
  var country = getVal('reg-country');
  var moneda = (country && PAIS_MONEDA[country]) ? PAIS_MONEDA[country] : 'USD';
  var username = getVal('reg-username').trim();
  var email = getVal('reg-email').trim();
  var pass = getVal('reg-pass');
  var pass2 = getVal('reg-pass2');

  /* Validacion en el cliente (el backend valida de nuevo) */
  if (!/^[A-Za-z0-9_.]{3,30}$/.test(username)) {
    return authError('reg-error', 'Usuario invalido: usa 3-30 caracteres (letras, numeros, punto, guion bajo).');
  }
  if (pass !== pass2) {
    return authError('reg-error', 'Las contrasenas no coinciden.');
  }
  if (pass.length < 8) {
    return authError('reg-error', 'La contrasena debe tener al menos 8 caracteres.');
  }

  var payload = {
    username: username,
    displayname: getVal('reg-displayname').trim(),
    email: email,
    password: pass,
    password2: pass2,
    pais: country,
    edad: getVal('reg-age'),
    plataforma: getVal('reg-platform'),
    telefono: getVal('reg-phone').trim(),
    direccion: getVal('reg-address').trim(),
    moneda: moneda
  };

  setLoading('reg-btn', true);
  apiSend('POST', 'register', payload).then(function (data) {
    currentUser = data.user;
    currentCurrency = moneda;
    setStore('currency', moneda);
    setVal('currencySelect', currentCurrency);
    document.getElementById('auth-register').querySelector('form').reset();
    resetStrength();
    updateAuthUI();
    if (typeof syncRatingUI === 'function') syncRatingUI();
    if (typeof refreshAllPrices === 'function') refreshAllPrices();
    if (typeof updateCartBadge === 'function') updateCartBadge();
    showSection('profile');
    if (typeof showToast === 'function') showToast(t('auth_welcome', { name: currentUser.username }), 'success');
  }).catch(function (err) {
    authError('reg-error', err.message);
  }).finally(function () {
    setLoading('reg-btn', false);
  });
}

function handleLogin(e) {
  e.preventDefault();
  if (getVal('login-user').trim() === '' || getVal('login-pass') === '') {
    return authError('login-error', 'Completa ambos campos.');
  }
  setLoading('login-btn', true);
  apiSend('POST', 'login', {
    username: getVal('login-user').trim(),
    password: getVal('login-pass')
  }).then(function (data) {
    currentUser = data.user;
    if (currentUser.moneda) {
      currentCurrency = currentUser.moneda;
      setStore('currency', currentCurrency);
      setVal('currencySelect', currentCurrency);
    }
    setVal('login-pass', '');
    updateAuthUI();
    if (typeof syncRatingUI === 'function') syncRatingUI();
    if (typeof refreshAllPrices === 'function') refreshAllPrices();
    if (typeof updateCartBadge === 'function') updateCartBadge();
    showSection('profile');
    if (typeof showToast === 'function') showToast(t('auth_welcome_back', { name: currentUser.username }), 'success');
  }).catch(function (err) {
    authError('login-error', err.message);
  }).finally(function () {
    setLoading('login-btn', false);
  });
}

/* Estado de carga de los botones de formulario */
function setLoading(id, on) {
  var btn = document.getElementById(id);
  if (!btn) return;
  btn.disabled = on;
  if (on) {
    btn.dataset.label = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Procesando...</span>';
  } else if (btn.dataset.label) {
    btn.innerHTML = btn.dataset.label;
    btn.disabled = false;
    delete btn.dataset.label;
  }
}

/* Mostrar / ocultar contrasena */
function togglePass(id, btn) {
  var input = document.getElementById(id);
  if (!input) return;
  var show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  if (btn) {
    btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
  }
}

/* Indicador de fuerza de contrasena */
function passScore(p) {
  var s = 0;
  if (p.length >= 8) s++;
  if (p.length >= 12) s++;
  if (/[A-Z]/.test(p) && /[a-z]/.test(p)) s++;
  if (/\d/.test(p)) s++;
  if (/[^A-Za-z0-9]/.test(p)) s++;
  return s;
}
function onPassStrength(v) {
  var bar = document.getElementById('strength-bar');
  var txt = document.getElementById('strength-txt');
  if (!bar || !txt) return;
  var s = passScore(v);
  if (!v) { bar.className = 'strength-bar'; txt.textContent = ''; return; }
  var levels = ['MUY DEBIL', 'DEBIL', 'REGULAR', 'BUENA', 'FUERTE', 'EXCELENTE'];
  var colors = ['var(--red-g)', 'var(--red-g)', 'var(--gold)', 'var(--cyan)', 'var(--green)', 'var(--green)'];
  bar.className = 'strength-bar lvl' + s;
  bar.style.width = (s * 20) + '%';
  bar.style.background = colors[s];
  txt.textContent = levels[s];
  txt.style.color = colors[s];
  onPassMatch();
}
function onPassMatch() {
  var el = document.getElementById('reg-pass-match');
  var p1 = document.getElementById('reg-pass') ? document.getElementById('reg-pass').value : '';
  var p2 = document.getElementById('reg-pass2') ? document.getElementById('reg-pass2').value : '';
  if (!el) return;
  if (!p2) { el.textContent = ''; return; }
  el.textContent = p1 === p2 ? 'Las contrasenas coinciden' : 'Las contrasenas no coinciden';
  el.classList.toggle('ok', p1 === p2);
  el.classList.toggle('invalid', p1 !== p2);
}
function resetStrength() {
  var bar = document.getElementById('strength-bar');
  var txt = document.getElementById('strength-txt');
  var match = document.getElementById('reg-pass-match');
  if (bar) { bar.className = 'strength-bar'; bar.style.width = '0'; }
  if (txt) { txt.textContent = ''; }
  if (match) { match.textContent = ''; }
}

function logout() {
  apiSend('POST', 'logout', {}).finally(function () {
    currentUser = null;
    updateAuthUI();
    syncRatingUI();
    renderCart();
    showSection('home');
    showToast(t('auth_logout_msg'), 'info');
  });
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}
