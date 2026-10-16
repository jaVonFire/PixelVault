/* ============================================================
   NOTIFICACIONES - Recordatorios de carrito (campanita)
   Las notificaciones las CREA el workflow de n8n
   "PixelVault - Recordatorio Carrito" (Schedule cada minuto) que
   escribe en la tabla `notificacion`. Este modulo:
     - las lee desde la API (?action=notificaciones),
     - muestra la campanita con el contador de no leidas,
     - hace sonar una campanita (Web Audio) cuando llega una nueva,
     - permite marcarlas como leidas.
   Visible siempre (como el carrito/chat): el admin la oculta y el
   sondeo solo corre para clientes logueados.
   ============================================================ */

var notifItems = [];
var notifOpen = false;
var notifTimer = null;
var notifLastUnread = 0;
var notifFirstLoad = true;
var notifAudioCtx = null;
var notifPollMs = 10000;

function notifEl(id) { return document.getElementById(id); }

function initNotifications(user) {
  var toggle = notifEl('notifToggle');
  if (!toggle) return;

  /* Permanente como el carrito/chat: nace visible (HTML) y solo se oculta
     para el admin (applyRoleVisibility). Para el invitado queda visible. */
  if (user && user.rol === 'admin') return;

  toggle.style.display = 'flex';
  document.body.classList.add('has-notif');
  toggle.addEventListener('click', function (e) { e.stopPropagation(); toggleNotif(); });
  toggle.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleNotif(); }
  });
  var close = notifEl('notifClose');
  if (close) close.addEventListener('click', function (e) { e.stopPropagation(); closeNotif(); });
  var markAll = notifEl('notifMarkAll');
  if (markAll) markAll.addEventListener('click', function (e) { e.stopPropagation(); markAllRead(); });

  document.addEventListener('click', function (e) {
    if (!notifOpen) return;
    var w = notifEl('notifWindow');
    if (w && w.classList.contains('visible') && !w.contains(e.target) && !toggle.contains(e.target)) closeNotif();
  });

  if (!user) return;                    /* invitado: campanita visible, sin sondeo */

  refreshNotifications();
  notifTimer = setInterval(refreshNotifications, notifPollMs);
}

function toggleNotif() { notifOpen ? closeNotif() : openNotif(); }

function openNotif() {
  var w = notifEl('notifWindow');
  if (!w) return;
  /* Cierra carrito/chat para no superponer ventanas */
  if (typeof closeCart === 'function') { try { closeCart(); } catch (e) {} }
  if (typeof closeChat === 'function') { try { closeChat(); } catch (e) {} }
  notifOpen = true;
  w.classList.add('visible');
  renderNotifications();
}

function closeNotif() {
  var w = notifEl('notifWindow');
  if (!w) return;
  notifOpen = false;
  w.classList.remove('visible');
}

function refreshNotifications() {
  apiGet('notificaciones').then(function (data) {
    notifItems = data.items || [];
    var unread = parseInt(data.no_leidas, 10) || 0;
    updateNotifBadge(unread);
    if (notifOpen) renderNotifications();

    /* Aviso sonoro solo si AUMENTARON las no leidas (no en la 1a carga) */
    if (!notifFirstLoad && unread > notifLastUnread) {
      playNotifSound();
      ringBell();
      if (typeof showToast === 'function' && notifItems.length) {
        showToast('<b>' + escapeHtml(notifItems[0].titulo) + '</b>', 'info');
      }
    }
    notifLastUnread = unread;
    notifFirstLoad = false;
  }).catch(function () { /* sesion expirada u offline: ignorar */ });
}

function updateNotifBadge(n) {
  var badge = notifEl('notifBadge');
  if (!badge) return;
  if (n > 0) {
    badge.textContent = n > 9 ? '9+' : n;
    badge.style.display = 'flex';
  } else {
    badge.style.display = 'none';
  }
}

function ringBell() {
  var toggle = notifEl('notifToggle');
  if (!toggle) return;
  toggle.classList.remove('ring');
  void toggle.offsetWidth;                 /* reinicia la animacion */
  toggle.classList.add('ring');
  setTimeout(function () { toggle.classList.remove('ring'); }, 650);
}

function renderNotifications() {
  var body = notifEl('notifBody');
  if (!body) return;

  if (!notifItems.length) {
    body.innerHTML = '<div class="notif-empty"><i class="fas fa-bell-slash"></i>' + t('notif_empty') + '</div>';
    return;
  }

  var html = '';
  notifItems.forEach(function (n) {
    var icon = n.tipo === 'carrito_2' ? 'fa-fire' : 'fa-cart-shopping';
    html += '<div class="notif-item ' + (parseInt(n.leida, 10) === 0 ? 'unread' : '') + '" onclick="openNotifItem(' + n.id + ')">' +
              '<div class="notif-item-icon"><i class="fas ' + icon + '"></i></div>' +
              '<div class="notif-item-body">' +
                '<div class="notif-item-title">' + escapeHtml(n.titulo) + '</div>' +
                '<div class="notif-item-msg">' + escapeHtml(n.mensaje) + '</div>' +
                '<div class="notif-item-time">' + notifTimeAgo(n.creado_el) + '</div>' +
              '</div>' +
            '</div>';
  });
  body.innerHTML = html;
}

/* Al tocar una notificacion: marcar leida y llevar al carrito */
function openNotifItem(id) {
  markNotifRead(id).then(function () {
    closeNotif();
    if (typeof openCart === 'function') { try { openCart(); } catch (e) {} }
  }).catch(function () {});
}

function markNotifRead(id) {
  return apiSend('POST', 'notificaciones', { id: id }).then(function () {
    for (var i = 0; i < notifItems.length; i++) {
      if (parseInt(notifItems[i].id, 10) === parseInt(id, 10)) notifItems[i].leida = 1;
    }
    var unread = notifItems.filter(function (n) { return parseInt(n.leida, 10) === 0; }).length;
    notifLastUnread = unread;
    updateNotifBadge(unread);
    if (notifOpen) renderNotifications();
  });
}

function markAllRead() {
  return apiSend('POST', 'notificaciones', { all: true }).then(function () {
    notifItems.forEach(function (n) { n.leida = 1; });
    notifLastUnread = 0;
    updateNotifBadge(0);
    renderNotifications();
  }).catch(function () {});
}

/* ---- Sonido de campanita (Web Audio, sin archivos) ---- */
function notifInitAudio() {
  if (!notifAudioCtx) {
    try {
      var AC = window.AudioContext || window.webkitAudioContext;
      notifAudioCtx = AC ? new AC() : null;
    } catch (e) { notifAudioCtx = null; }
  }
  return notifAudioCtx;
}

function playNotifSound() {
  var ctx = notifInitAudio();
  if (!ctx) return;
  if (ctx.state === 'suspended') { try { ctx.resume(); } catch (e) {} }
  var now = ctx.currentTime;
  /* Dos tonos ascendentes tipo "ding-dong" */
  [[988, 0], [1319, 0.16]].forEach(function (pair) {
    var osc = ctx.createOscillator();
    var gain = ctx.createGain();
    osc.type = 'sine';
    osc.frequency.value = pair[0];
    osc.connect(gain);
    gain.connect(ctx.destination);
    var t0 = now + pair[1];
    gain.gain.setValueAtTime(0.0001, t0);
    gain.gain.exponentialRampToValueAtTime(0.22, t0 + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.4);
    osc.start(t0);
    osc.stop(t0 + 0.42);
  });
}

/* ---- "hace X" a partir de una fecha MySQL (YYYY-MM-DD HH:MM:SS) ---- */
function notifTimeAgo(str) {
  if (!str) return '';
  var d = new Date(String(str).replace(' ', 'T'));
  if (isNaN(d.getTime())) return '';
  var secs = Math.floor((Date.now() - d.getTime()) / 1000);
  if (secs < 0) secs = 0;
  var es = currentLang() === 'es';
  if (secs < 60) return es ? 'hace un momento' : 'just now';
  var mins = Math.floor(secs / 60);
  if (mins < 60) return es ? ('hace ' + mins + ' min') : (mins + ' min ago');
  var hrs = Math.floor(mins / 60);
  if (hrs < 24) return es ? ('hace ' + hrs + ' h') : (hrs + ' h ago');
  var days = Math.floor(hrs / 24);
  return es ? ('hace ' + days + ' d') : (days + ' d ago');
}
