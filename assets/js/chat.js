/* ============================================================
   CHATBOT - responde en el idioma activo (ES/EN)
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

function addBotMessage(text) {
  var time = new Date().toLocaleTimeString(currentLang() === 'es' ? 'es' : 'en', { hour: '2-digit', minute: '2-digit' });
  var div = document.createElement('div');
  div.className = 'chat-msg bot';
  div.innerHTML = text + '<span class="time">' + time + '</span>';
  chatMessagesEl.appendChild(div);
  chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
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

/* ===== MOTOR DEL BOT (bilingue) ===== */
var botKnowledge = {
  es: [
    { keys: ['hola','hey','hi','hello','buenas','buenos','saludos','que tal','como estas','buen dia'],
      responses: ['Hola! En que puedo ayudarte hoy?', 'Saludos, gamer! Que necesitas?'] },
    { keys: ['carrito','carro','cart','agregar','compra','comprar','checkout','pedido'],
      responses: ['Para comprar inicia sesion. Agrega productos con el boton AGREGAR y abre tu carrito con el boton azul flotante.', 'Tu carrito esta en la base de datos de tu cuenta: agregalo con AGREGAR y finaliza la compra.'] },
    { keys: ['moneda','monedas','divisa','pesos','dolares','cambio','conversion','precio en','cuanto es en'],
      responses: ['Usa el selector de moneda en la parte superior derecha (17 monedas). Los precios se actualizan al instante.'] },
    { keys: ['login','iniciar sesion','sesion','loguear','entrar','cuenta','registrarme','registrar','password','contrasena','perfil'],
      responses: ['Ve a Mi Cuenta > Crear Cuenta. Tu contrasena se guarda con hash binario, nunca en texto plano.'] },
    { keys: ['producto','productos','catalogo','catalog','que venden','tienda','inventario','stock'],
      responses: ['Tenemos juegos, accesorios, merch y coleccionables, todos en la base de datos. Visita Productos!'] },
    { keys: ['juego','juegos','game','nuevo','titulo'],
      responses: ['Zelda TotK, GoW Ragnarok, Cyberpunk 2077, GTA V y mas, todos con stock real.'] },
    { keys: ['precio','costo','cuanto vale','cuanto cuesta','precios','oferta','barato'],
      responses: ['Los precios estan en USD y se muestran en tu moneda. De $14.99 a $349.99.'] },
    { keys: ['envio','envios','shipping','entrega','delivery','llega'],
      responses: ['Envios a toda Latinoamerica y Espana, 3-7 dias habiles.'] },
    { keys: ['devolucion','reembolso','garantia','defecto'],
      responses: ['30 dias para devoluciones en productos sellados.'] },
    { keys: ['pago','pagar','tarjeta','paypal','transferencia','visa','mastercard'],
      responses: ['Aceptamos Visa, Mastercard, PayPal y transferencia. Tus datos son sensibles y van protegidos.'] },
    { keys: ['dato','datos','privacidad','seguridad','informacion','proteccion'],
      responses: ['Clasificamos datos en 4 niveles: publicos, semi-privados, privados y sensibles. Tu contrasena va hasheada.'] },
    { keys: ['stock','disponible','agotado','disponibilidad','inventario'],
      responses: ['Cada producto muestra su stock real de la base de datos. El carrito respeta el inventario.'] },
    { keys: ['nivel','xp','experiencia','recompensa','puntos','vip'],
      responses: ['Cada compra te da XP igual al total en dolares. Tu nivel aparece en tu perfil.'] },
    { keys: ['gracias','thank','genial','perfecto','ok','bien','entendido','excelente'],
      responses: ['De nada! Recuerda calificar con el boton estrella.'] },
    { keys: ['adios','bye','chao','nos vemos','hasta luego'],
      responses: ['Hasta luego, gamer! Buena partida!'] }
  ],
  en: [
    { keys: ['hello','hi','hey','good morning','good afternoon','how are you'],
      responses: ['Hello! How can I help you today?', 'Hi gamer! What do you need?'] },
    { keys: ['cart','checkout','order','buy','shop'],
      responses: ['Log in to buy. Add products with the ADD button and open your cart with the floating blue button.', 'Your cart is stored in the database linked to your account.'] },
    { keys: ['currency','money','dollar','peso','price in','exchange','conversion'],
      responses: ['Use the currency selector at the top right (17 currencies). Prices update instantly.'] },
    { keys: ['login','log in','sign in','register','account','password','profile'],
      responses: ['Go to My Account > Register. Your password is hashed, never stored as plain text.'] },
    { keys: ['product','products','catalog','store','inventory','stock'],
      responses: ['We have games, accessories, merch and collectibles, all in the database. Visit Products!'] },
    { keys: ['game','games','new','title'],
      responses: ['Zelda TotK, GoW Ragnarok, Cyberpunk 2077, GTA V and more, all with real stock.'] },
    { keys: ['price','cost','how much','prices','offer'],
      responses: ['Prices are in USD and shown in your currency. From $14.99 to $349.99.'] },
    { keys: ['shipping','delivery','arrive'],
      responses: ['Shipping to all Latin America and Spain, 3-7 business days.'] },
    { keys: ['return','refund','warranty','defect'],
      responses: ['30 days for returns on sealed products.'] },
    { keys: ['payment','pay','card','paypal','transfer','visa','mastercard'],
      responses: ['We accept Visa, Mastercard, PayPal and bank transfer. Your data is sensitive and protected.'] },
    { keys: ['data','privacy','security','information','protection'],
      responses: ['We classify data in 4 levels: public, semi-private, private and sensitive. Your password is hashed.'] },
    { keys: ['stock','available','sold out','availability','inventory'],
      responses: ['Each product shows its real stock from the database. The cart respects inventory.'] },
    { keys: ['level','xp','experience','reward','points','vip'],
      responses: ['Each purchase gives you XP equal to the dollar total. Your level shows on your profile.'] },
    { keys: ['thanks','thank','great','perfect','ok','good','understood','excellent'],
      responses: ['You are welcome! Remember to rate us with the star button.'] },
    { keys: ['bye','goodbye','see you','later'],
      responses: ['See you, gamer! Have a great game!'] }
  ]
};
var fallbackResponses = {
  es: ['Interesante! Puedo ayudarte con productos, carrito, monedas, envios, pagos y cuenta.',
       'No tengo la respuesta exacta, pero puedo orientarte en compras, envios y atencion.'],
  en: ['Interesting! I can help you with products, cart, currencies, shipping, payments and account.',
       'I don\'t have the exact answer, but I can guide you with purchases, shipping and support.']
};

function getBotResponse(input) {
  var lang = currentLang();
  var lower = input.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  var best = null, score = 0;
  (botKnowledge[lang] || botKnowledge.es).forEach(function (t) {
    var s = 0;
    t.keys.forEach(function (k) { if (lower.indexOf(k) !== -1) s++; });
    if (s > score) { score = s; best = t; }
  });
  if (best) return best.responses[Math.floor(Math.random() * best.responses.length)];
  return (fallbackResponses[lang] || fallbackResponses.es)[Math.floor(Math.random() * 2)];
}

function sendChat() {
  var text = chatInputEl.value.trim();
  if (!text) return;
  addUserMessage(text);
  chatInputEl.value = '';
  var typing = document.createElement('div');
  typing.className = 'chat-msg bot'; typing.id = 'typing';
  typing.innerHTML = '<i class="fas fa-ellipsis fa-beat-fade"></i> ...';
  chatMessagesEl.appendChild(typing);
  chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
  setTimeout(function () {
    var el = document.getElementById('typing');
    if (el) el.remove();
    addBotMessage(getBotResponse(text));
  }, 500 + Math.random() * 700);
}
