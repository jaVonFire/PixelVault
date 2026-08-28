/* ============================================================
   STORAGE (solo preferencias de interfaz del cliente)
   Los datos del negocio (usuarios, productos, carrito, pedidos,
   inventario) viven en la BASE DE DATOS y se acceden via la API.
   Aqui solo se persisten preferencias de visualizacion:
   la moneda elegida por el usuario.
   ============================================================ */
function getStore(k, d) {
  try {
    var v = JSON.parse(localStorage.getItem('pv_' + k));
    return (v === null || v === undefined) ? d : v;
  } catch (e) { return d; }
}
function setStore(k, v) {
  localStorage.setItem('pv_' + k, JSON.stringify(v));
}

/* Moneda de visualizacion (por defecto USD) */
var currentCurrency = getStore('currency', 'USD');

/* Al registrarse por pais se asigna moneda por defecto */
var PAIS_MONEDA = {
  'Mexico':'MXN','Colombia':'COP','Argentina':'ARS','Chile':'CLP','Peru':'PEN',
  'Bolivia':'BOB','Uruguay':'UYU','Paraguay':'PYG','Guatemala':'GTQ','Costa Rica':'CRC',
  'Republica Dominicana':'DOP','Venezuela':'VES','Brasil':'BRL','Espana':'EUR','Otro':'USD'
};
