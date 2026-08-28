/* ============================================================
   Cliente de la API REST
   Todas las peticiones van al backend PHP (api/index.php),
   que a su vez consulta la base de datos MySQL.
   ============================================================ */
/* Ruta base de la API. Como las vistas viven en /views/, se detecta
   la ubicacion de la pagina actual para resolver ../api/index.php. */
var API_BASE = (/[\/\\]views[\/\\]/.test(window.location.pathname) ? '../' : '') + 'api/index.php';

function apiGet(action, params) {
  return apiRequest('GET', action, null, params);
}
function apiSend(method, action, body, params) {
  return apiRequest(method, action, body, params);
}

function apiRequest(method, action, body, params) {
  var url = API_BASE + '?action=' + encodeURIComponent(action);
  if (params) {
    Object.keys(params).forEach(function (k) {
      if (params[k] !== undefined && params[k] !== null && params[k] !== '') {
        url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
      }
    });
  }
  var opts = { method: method, credentials: 'same-origin', headers: {} };
  if (body) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(body);
  }
  return fetch(url, opts).then(function (res) {
    return res.json().then(function (data) {
      if (!res.ok) {
        throw new Error(data.error || 'Error del servidor');
      }
      return data;
    });
  });
}
