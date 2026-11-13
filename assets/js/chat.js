/* ============================================================
   CHATBOT - responde en el idioma activo (ES/EN)
   Flujo:  chat.js -> api/index.php?action=chat -> PHP -> Gemini o bot local
   ============================================================ */
var chatOpen = false, chatInitialized = false, currentRating = 0;
var chatToggleBtn = document.getElementById('chatToggle');
var chatWindowEl = document.getElementById('chatWindow');
var chatRateBtn = document.getElementById('chatRateBtn');
var chatInputEl = document.getElementById('chatInput');
var chatMessagesEl = document.getElementById('chatMessages');
var chatBadgeEl = document.getElementById('chatBadge');
var ratingAreaEl = document.getElementById('ratingArea');

function openChat() {
  if (cartOpen) closeCart();
  chatOpen = true;
  chatWindowEl.classList.add('visible');
  if (chatBadgeEl) chatBadgeEl.style.display = 'none';
  if (!chatInitialized) {
    addBotMessage(t('chat_welcome'));
    chatInitialized = true;
  }
  updateRateButtonState();
  setTimeout(function () { chatInputEl.focus(); }, 300);
}
function closeChat() { chatOpen = false; chatWindowEl.classList.remove('visible'); }
chatToggleBtn.addEventListener('click', function (e) { e.stopPropagation(); chatOpen ? closeChat() : openChat(); });
document.getElementById('chatClose').addEventListener('click', function (e) { e.stopPropagation(); closeChat(); });
document.getElementById('chatSend').addEventListener('click', function (e) { e.stopPropagation(); sendChat(); });
chatInputEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); sendChat(); } });

function addBotMessage(text, fuente) {
  var time = new Date().toLocaleTimeString(currentLang() === 'es' ? 'es' : 'en', { hour: '2-digit', minute: '2-digit' });
  var div = document.createElement('div');
  div.className = 'chat-msg bot';
  /* El texto puede venir de un modelo de IA: se sanea antes de insertarlo. */
  var body = sanitizeRich(text);
  if (fuente) body += '<span class="src src-' + escapeHtml(fuente === 'local' ? 'local' : fuente) + '">' + chatBadge(fuente) + '</span>';
  div.innerHTML = body + '<span class="time">' + time + '</span>';
  chatMessagesEl.appendChild(div);
  chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
}
/* Etiqueta de origen: deja ver si respondio Gemini o las reglas locales. */
function chatBadge(fuente) {
  var map = {
    gemini: 'chat_badge_ai',
    n8n: 'chat_badge_legacy',
    guardarrail: 'chat_badge_guard',
    respaldo: 'chat_badge_fallback',
    local: 'chat_badge_local'
  };
  return escapeHtml(t(map[fuente] || 'chat_badge_local'));
}
/* Escapa el HTML y solo rehabilita etiquetas simples SIN atributos. */
function sanitizeRich(text) {
  var allowed = { b: 1, strong: 1, i: 1, em: 1, u: 1, br: 1, p: 1, ul: 1, ol: 1, li: 1 };
  return escapeHtml(String(text == null ? '' : text)).replace(/&lt;(\/?)([a-z0-9]+)\s*&gt;/gi, function (m, slash, tag) {
    tag = tag.toLowerCase();
    return allowed[tag] ? '<' + (slash ? '/' : '') + tag + '>' : m;
  });
}
function addUserMessage(text) {
  var time = new Date().toLocaleTimeString(currentLang() === 'es' ? 'es' : 'en', { hour: '2-digit', minute: '2-digit' });
  var div = document.createElement('div');
  div.className = 'chat-msg user';
  div.innerHTML = escapeHtml(text) + '<span class="time">' + time + '</span>';
  chatMessagesEl.appendChild(div);
  chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
}

/* ===== CALIFICACION (una por dia por cuenta) ===== */
function hasRatedToday() {
  var key = currentUser ? 'rated_' + currentUser.id : 'rated_guest';
  return getStore(key, '') === todayStr();
}
function todayStr() {
  var d = new Date();
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}
function updateRateButtonState() {
  if (!chatRateBtn) return;
  chatRateBtn.classList.toggle('rated', hasRatedToday());
  chatRateBtn.title = hasRatedToday() ? t('chat_rated_today') : t('chat_rate_btn');
}
function toggleRatingPanel() {
  if (ratingAreaEl.classList.contains('expanded')) ratingAreaEl.classList.remove('expanded');
  else { renderRatingPanel(); ratingAreaEl.classList.add('expanded'); }
}
chatRateBtn.addEventListener('click', function (e) { e.stopPropagation(); toggleRatingPanel(); });

function renderRatingPanel() {
  ratingAreaEl.innerHTML = '<div class="rating-inner">' +
    '<p>' + t('chat_rate_title') + '</p>' +
    (currentUser ? '<div class="rating-who">' + t('chat_rate_as') + ' <b>@' + escapeHtml(currentUser.username) + '</b></div>' : '') +
    '<div class="stars" id="ratingStars">' + [1, 2, 3, 4, 5].map(function (n) { return '<i class="fas fa-star" data-val="' + n + '"></i>'; }).join('') + '</div>' +
    '<textarea class="rating-comment" id="ratingComment" placeholder="' + t('chat_rate_comment') + '"></textarea>' +
    '<button class="btn-rate" id="btnRate">' + t('chat_rate_send') + '</button>' +
    '<span class="rating-note"><i class="fas fa-clock"></i> ' + t('chat_rate_note') + '</span>' +
  '</div>';
  currentRating = 0;
  bindRatingEvents();
}
function bindRatingEvents() {
  var starsEl = document.getElementById('ratingStars');
  var btnRate = document.getElementById('btnRate');
  if (!starsEl || !btnRate) return;
  var icons = starsEl.querySelectorAll('i');
  function paint(val) {
    icons.forEach(function (s) {
      var sv = parseInt(s.getAttribute('data-val'), 10);
      sv <= val ? s.classList.add('active') : s.classList.remove('active');
    });
  }
  icons.forEach(function (star) {
    star.addEventListener('click', function () { currentRating = parseInt(this.getAttribute('data-val'), 10); paint(currentRating); });
    star.addEventListener('mouseenter', function () { paint(parseInt(this.getAttribute('data-val'), 10)); });
  });
  starsEl.addEventListener('mouseleave', function () { paint(currentRating); });
  btnRate.addEventListener('click', function (e) { e.stopPropagation(); submitRating(); });
}
function submitRating() {
  if (hasRatedToday()) { showToast(t('chat_rated_today'), 'error'); renderRatingPanel(); return; }
  if (currentRating === 0) { showToast(t('chat_rate_require'), 'error'); return; }
  var key = currentUser ? 'rated_' + currentUser.id : 'rated_guest';
  setStore(key, todayStr());
  setStore('lastRatingStars', currentRating);
  addBotMessage(currentUser ? t('chat_thanks', { name: currentUser.username }) : t('chat_thanks_guest'));
  showToast(t('chat_thanks_toast'), 'success');
  updateRateButtonState();
  ratingAreaEl.classList.remove('expanded');
}

function getBotResponse(input) {
  return currentLang() === 'en'
    ? 'I could not reach the assistant service, so I will not guess. Please try again in a moment.'
    : 'No pude conectar con el asistente y prefiero no adivinar. Inténtalo de nuevo en un momento.';
}

function sendChat() {
  var text = chatInputEl.value.trim();
  if (!text) return;
  addUserMessage(text);
  chatInputEl.value = '';
  showTyping();

  /* El backend PHP consulta el modelo; si falla, responde el bot local. */
  apiSend('POST', 'chat', { mensaje: text, lang: currentLang() })
    .then(function (r) {
      hideTyping();
      addBotMessage(r.reply, r.fuente);
    })
    .catch(function () {
      hideTyping();
      addBotMessage(getBotResponse(text), 'respaldo');
    });
}

/* ===== INDICADOR DE ESCRITURA ===== */
function showTyping() {
  hideTyping();
  var typing = document.createElement('div');
  typing.className = 'chat-msg bot';
  typing.id = 'typing';
  typing.innerHTML = '<i class="fas fa-ellipsis fa-beat-fade"></i> ...';
  chatMessagesEl.appendChild(typing);
  chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
}
function hideTyping() {
  var el = document.getElementById('typing');
  if (el) el.remove();
}
