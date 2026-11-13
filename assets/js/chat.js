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
var visionImageBlob = null, visionPreviewUrl = null, visionBusy = false;

function initChatVisionControls() {
  var inputArea = chatInputEl.parentNode;
  var fileInput = document.createElement('input');
  fileInput.type = 'file';
  fileInput.accept = 'image/jpeg,image/png,image/webp';
  fileInput.style.display = 'none';
  fileInput.setAttribute('aria-label', t('chat_vision_button'));

  var button = document.createElement('button');
  button.type = 'button';
  button.className = 'chat-vision-button';
  button.innerHTML = '<i class="fas fa-camera"></i>';
  button.title = t('chat_vision_button');
  button.setAttribute('aria-label', t('chat_vision_button'));
  button.addEventListener('click', function (e) {
    e.stopPropagation();
    if (!currentUser) { showToast(t('chat_vision_login'), 'error'); return; }
    fileInput.click();
  });
  inputArea.insertBefore(button, chatInputEl);
  inputArea.appendChild(fileInput);

  var panel = document.createElement('div');
  panel.className = 'chat-vision-preview';
  panel.hidden = true;
  panel.innerHTML = '<div class="chat-vision-preview-row"><img alt=""><div class="chat-vision-preview-copy"></div></div>' +
    '<label class="chat-vision-consent"><input type="checkbox"><span></span></label>' +
    '<div class="chat-vision-preview-actions"><button type="button" class="chat-vision-cancel"></button><button type="button" class="chat-vision-analyze" disabled></button></div>';
  panel.querySelector('.chat-vision-preview-copy').textContent = t('chat_vision_selected');
  panel.querySelector('.chat-vision-consent span').textContent = t('chat_vision_consent');
  panel.querySelector('.chat-vision-cancel').textContent = t('cancel');
  panel.querySelector('.chat-vision-analyze').textContent = t('chat_vision_analyze');
  chatWindowEl.insertBefore(panel, inputArea);

  var consent = panel.querySelector('.chat-vision-consent input');
  var analyze = panel.querySelector('.chat-vision-analyze');
  consent.addEventListener('change', function () { analyze.disabled = !consent.checked || !visionImageBlob || visionBusy; });
  panel.querySelector('.chat-vision-cancel').addEventListener('click', clearChatVisionSelection);
  analyze.addEventListener('click', submitChatVisionImage);
  fileInput.addEventListener('change', function () {
    var file = fileInput.files && fileInput.files[0];
    fileInput.value = '';
    if (!file) return;
    clearChatVisionSelection();
    if (!/^image\/(jpeg|png|webp)$/i.test(file.type) || file.size > 15728640) {
      showToast(t('chat_vision_bad_file'), 'error');
      return;
    }
    compressChatVisionImage(file).then(function (blob) {
      visionImageBlob = blob;
      if (visionPreviewUrl) URL.revokeObjectURL(visionPreviewUrl);
      visionPreviewUrl = URL.createObjectURL(blob);
      panel.querySelector('img').src = visionPreviewUrl;
      panel.hidden = false;
      consent.checked = false;
      analyze.disabled = true;
    }).catch(function () {
      visionImageBlob = null;
      showToast(t('chat_vision_bad_file'), 'error');
    });
  });
}

function compressChatVisionImage(file) {
  return new Promise(function (resolve, reject) {
    var objectUrl = URL.createObjectURL(file);
    var img = new Image();
    img.onload = function () {
      var width = img.naturalWidth, height = img.naturalHeight;
      URL.revokeObjectURL(objectUrl);
      if (!width || !height || width * height > 40000000) { reject(new Error('invalid_image')); return; }
      var scale = Math.min(1, 1280 / Math.max(width, height));
      var canvas = document.createElement('canvas');
      canvas.width = Math.max(1, Math.round(width * scale));
      canvas.height = Math.max(1, Math.round(height * scale));
      var ctx = canvas.getContext('2d');
      if (!ctx) { reject(new Error('canvas_unavailable')); return; }
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
      var qualities = [0.82, 0.68, 0.54];
      function encode(index) {
        if (index >= qualities.length) {
          if (Math.max(canvas.width, canvas.height) <= 640) { reject(new Error('too_large')); return; }
          canvas.width = Math.max(1, Math.round(canvas.width * 0.8));
          canvas.height = Math.max(1, Math.round(canvas.height * 0.8));
          ctx = canvas.getContext('2d');
          ctx.fillStyle = '#ffffff';
          ctx.fillRect(0, 0, canvas.width, canvas.height);
          ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
          encode(0);
          return;
        }
        canvas.toBlob(function (blob) {
          if (!blob) { reject(new Error('encode_failed')); return; }
          if (blob.size > 1700000) { encode(index + 1); return; }
          resolve(blob);
        }, 'image/jpeg', qualities[index]);
      }
      encode(0);
    };
    img.onerror = function () { URL.revokeObjectURL(objectUrl); reject(new Error('invalid_image')); };
    img.src = objectUrl;
  });
}

function clearChatVisionSelection() {
  visionImageBlob = null;
  if (visionPreviewUrl) URL.revokeObjectURL(visionPreviewUrl);
  visionPreviewUrl = null;
  var panel = chatWindowEl.querySelector('.chat-vision-preview');
  if (!panel) return;
  panel.hidden = true;
  panel.querySelector('img').removeAttribute('src');
  panel.querySelector('.chat-vision-consent input').checked = false;
  panel.querySelector('.chat-vision-analyze').disabled = true;
}

function submitChatVisionImage() {
  var panel = chatWindowEl.querySelector('.chat-vision-preview');
  if (!currentUser) { showToast(t('chat_vision_login'), 'error'); return; }
  if (!visionImageBlob || !panel.querySelector('.chat-vision-consent input').checked || visionBusy) return;
  visionBusy = true;
  panel.querySelector('.chat-vision-analyze').disabled = true;
  addUserMessage(t('chat_vision_user_message'));
  showTyping();
  var form = new FormData();
  form.append('image', visionImageBlob, 'product-photo.jpg');
  form.append('privacy_consent', '1');
  form.append('lang', currentLang());
  apiUpload('vision_analizar', form).then(function (result) {
    hideTyping();
    addChatVisionResult(result);
    clearChatVisionSelection();
  }).catch(function (error) {
    hideTyping();
    addBotMessage(error.message || t('chat_vision_error'));
    panel.querySelector('.chat-vision-analyze').disabled = !panel.querySelector('.chat-vision-consent input').checked;
  }).then(function () { visionBusy = false; });
}

function addChatVisionResult(result) {
  var div = document.createElement('div');
  div.className = 'chat-msg bot chat-vision-result';
  var title = document.createElement('strong');
  title.textContent = t('chat_vision_result_title');
  div.appendChild(title);
  if (result.description) {
    var description = document.createElement('p');
    description.textContent = result.description;
    div.appendChild(description);
  }
  if (!result.candidates || !result.candidates.length) {
    var none = document.createElement('p');
    none.textContent = t('chat_vision_no_match');
    div.appendChild(none);
  } else {
    if (result.low_confidence) {
      var note = document.createElement('p');
      note.className = 'chat-vision-low-confidence';
      note.textContent = t('chat_vision_low_confidence');
      div.appendChild(note);
    }
    result.candidates.forEach(function (product) {
      var card = document.createElement('div');
      card.className = 'chat-vision-product';
      var name = document.createElement('strong');
      name.textContent = product.nombre;
      card.appendChild(name);
      var details = document.createElement('p');
      details.textContent = product.categoria + ' · USD ' + Number(product.precio).toFixed(2) + ' · ' + t('chat_vision_stock') + ': ' + Number(product.stock);
      card.appendChild(details);
      var confidence = document.createElement('small');
      confidence.textContent = t('chat_vision_confidence') + ': ' + Math.round(Number(product.confidence) * 100) + '%';
      card.appendChild(confidence);

      if (currentUser && currentUser.rol === 'cliente') {
        var add = document.createElement('button');
        add.type = 'button';
        add.className = 'chat-vision-add';
        add.textContent = Number(product.stock) > 0 ? t('chat_vision_add') : t('chat_vision_out_of_stock');
        add.disabled = Number(product.stock) < 1;
        add.addEventListener('click', function () {
          add.disabled = true;
          apiSend('POST', 'vision_confirmar', { vision_id: Number(result.id), producto_id: Number(product.product_id) })
            .then(function () {
              add.textContent = t('chat_vision_added');
              if (typeof refreshCart === 'function') refreshCart();
              showToast(t('chat_vision_added'), 'success');
            })
            .catch(function (error) {
              add.disabled = Number(product.stock) < 1;
              showToast(error.message || t('chat_vision_add_error'), 'error');
            });
        });
        card.appendChild(add);
      } else {
        var adminNote = document.createElement('small');
        adminNote.className = 'chat-vision-admin-note';
        adminNote.textContent = t('chat_vision_admin_note');
        card.appendChild(adminNote);
      }
      div.appendChild(card);
    });
  }
  var time = document.createElement('span');
  time.className = 'time';
  time.textContent = new Date().toLocaleTimeString(currentLang() === 'es' ? 'es' : 'en', { hour: '2-digit', minute: '2-digit' });
  div.appendChild(time);
  chatMessagesEl.appendChild(div);
  chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
}

initChatVisionControls();

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
