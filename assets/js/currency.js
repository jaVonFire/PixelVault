/* ============================================================
   MONEDA
   Solo afecta la VISUALIZACION de precios. La moneda del usuario
   se guarda en la base de datos al registrarse; aqui se persiste
   la preferencia local de visualizacion.
   ============================================================ */
function formatPrice(usd) {
  var c = CURRENCIES[currentCurrency];
  var val = usd * c.rate;
  var opts = c.dec === 0 ? { maximumFractionDigits: 0 } : { minimumFractionDigits: 2, maximumFractionDigits: 2 };
  return c.symbol + ' ' + val.toLocaleString('es-MX', opts);
}

function onCurrencyChange(code) {
  currentCurrency = code;
  setStore('currency', code);
  refreshAllPrices();
  showToast(t('currency_changed', { name: CURRENCIES[code].name }), 'info');
}

function refreshAllPrices() {
  renderProducts(currentFilter);
  renderCart();
  if (typeof renderProfile === 'function' && document.getElementById('profile-content')) renderProfile();
}
