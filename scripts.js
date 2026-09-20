/* scripts.js — lógica del frontend. Cada página declara su nombre en <body data-page="..."> */

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const money = (n) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n);
const parseDT = (s) => new Date(String(s).replace(' ', 'T'));
const fmtTime = (s) => parseDT(s).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
const fmtDay = (s) => parseDT(s).toLocaleDateString('es-MX', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
const $ = (sel) => document.querySelector(sel);

async function api(url, options = {}) {
  const res = await fetch(url, { credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, ...options });
  let data = {};
  try { data = await res.json(); } catch (_) { /* respuesta sin JSON */ }
  if (!res.ok) {
    const err = new Error(data.error || 'No se pudo completar la solicitud.');
    err.status = res.status;
    throw err;
  }
  return data;
}
const post = (url, body) => api(url, { method: 'POST', body: JSON.stringify(body) });

function showMsg(text, type = 'error', target = '#msg') {
  const el = $(target);
  if (!el) return;
  el.textContent = text;
  el.className = 'msg ' + type;
  el.hidden = false;
}
function clearMsg(target = '#msg') { const el = $(target); if (el) el.hidden = true; }

/* ---------- Barra de navegación (pestañas entre servicios) ---------- */
async function renderNav(page) {
  const tabs = [
    ['index', 'index.html', 'Inicio'],
    ['search', 'search.html', 'Buscar vuelos'],
    ['reservations', 'reservations.html', 'Mis reservas'],
  ];
  let user = null;
  try { user = (await api('auth.php?action=me')).user; } catch (_) { /* sin sesión */ }

  const lis = tabs.map(([id, href, label]) =>
    `<li><a class="tab ${page === id ? 'activa' : ''}" href="${href}">${label}</a></li>`).join('');
  const sesion = user
    ? `<span>Hola, ${esc(user.name)}</span><button id="btn-salir" type="button">Salir</button>`
    : `<a class="tab ${page === 'login' ? 'activa' : ''}" href="login.html">Iniciar sesión</a>
       <a class="tab ${page === 'register' ? 'activa' : ''}" href="register.html">Registrarse</a>`;

  $('#nav').className = 'nav';
  $('#nav').innerHTML = `<a class="marca" href="index.html">QUETZALCOATL <span>AEROLÍNEA</span></a>
    <ul>${lis}</ul><div class="sesion">${sesion}</div>`;

  const salir = $('#btn-salir');
  if (salir) salir.addEventListener('click', async () => { await post('auth.php?action=logout', {}); location.href = 'index.html'; });
  return user;
}

/* ---------- Registro ---------- */
function initRegister() {
  $('#form-registro').addEventListener('submit', async (e) => {
    e.preventDefault();
    clearMsg();
    const f = new FormData(e.target);
    if (f.get('password') !== f.get('password2')) return showMsg('Las contraseñas no coinciden.');
    try {
      await post('auth.php?action=register', { name: f.get('name'), email: f.get('email'), password: f.get('password') });
      location.href = 'login.html?registered=1';
    } catch (err) { showMsg(err.message); }
  });
}

/* ---------- Login ---------- */
function initLogin() {
  const params = new URLSearchParams(location.search);
  if (params.get('registered')) showMsg('Cuenta creada. Inicia sesión para continuar.', 'ok');
  if (params.get('next')) showMsg('Inicia sesión para continuar con tu reserva.', 'error');
  $('#form-login').addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = new FormData(e.target);
    try {
      await post('auth.php?action=login', { email: f.get('email'), password: f.get('password') });
      const next = params.get('next');
      location.href = next && /^[a-z_]+\.html$/.test(next) ? next : 'search.html';
    } catch (err) { showMsg(err.message); }
  });
}

/* ---------- Búsqueda y reserva ---------- */
function initSearch(user) {
  const form = $('#form-busqueda');
  const lista = $('#resultados');
  const dialog = $('#dlg-reserva');
  let seleccionado = null;
  let pasajeros = 1;

  api('search_flights.php?action=cities').then(({ cities }) => {
    const opts = cities.map((c) => `<option>${esc(c)}</option>`).join('');
    $('#origin').insertAdjacentHTML('beforeend', opts);
    $('#destination').insertAdjacentHTML('beforeend', opts);
  });
  $('#date').min = new Date().toISOString().slice(0, 10);

  async function buscar() {
    clearMsg();
    const q = new URLSearchParams(new FormData(form));
    for (const [k, v] of [...q]) if (v === '') q.delete(k);
    try {
      const { flights } = await api('search_flights.php?' + q.toString());
      pasajeros = Number(new FormData(form).get('passengers')) || 1;
      if (!flights.length) { lista.innerHTML = '<p class="vacio">No hay vuelos con esos criterios. Prueba con otra fecha o destino.</p>'; return; }
      lista.innerHTML = flights.map((v) => `
        <article class="tarjeta vuelo">
          <div>
            <div class="ruta">
              <div><div class="hora">${fmtTime(v.departure)}</div><div class="ciudad">${esc(v.origin)}</div></div>
              <div class="linea"></div>
              <div><div class="hora">${fmtTime(v.arrival)}</div><div class="ciudad">${esc(v.destination)}</div></div>
            </div>
            <div class="meta">Vuelo ${esc(v.flight_number)} · ${fmtDay(v.departure)} · ${v.seats_available} asientos disponibles</div>
          </div>
          <div class="precio"><strong>${money(v.price)}</strong><small>por pasajero</small><br>
            <button type="button" data-id="${v.id}" style="margin-top:.5rem">Reservar</button></div>
        </article>`).join('');
      lista._flights = Object.fromEntries(flights.map((v) => [v.id, v]));
    } catch (err) { showMsg(err.message); }
  }
  form.addEventListener('submit', (e) => { e.preventDefault(); buscar(); });

  lista.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-id]');
    if (!btn) return;
    if (!user) { location.href = 'login.html?next=search.html'; return; }
    seleccionado = lista._flights[btn.dataset.id];
    $('#dlg-msg').hidden = true;
    $('#dlg-resumen').innerHTML = `<strong>${esc(seleccionado.origin)} → ${esc(seleccionado.destination)}</strong><br>
      Vuelo ${esc(seleccionado.flight_number)} · ${fmtDay(seleccionado.departure)} ${fmtTime(seleccionado.departure)}<br>
      ${pasajeros} pasajero(s) · Total: <strong>${money(seleccionado.price * pasajeros)}</strong>`;
    dialog.showModal();
  });
  $('#dlg-cancelar').addEventListener('click', () => dialog.close());

  $('#form-reserva').addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = new FormData(e.target);
    try {
      const r = await post('reserve_flight.php', {
        flight_id: seleccionado.id, passengers: pasajeros,
        card_name: f.get('card_name'), card_number: f.get('card_number'),
        card_exp: f.get('card_exp'), card_cvv: f.get('card_cvv'),
      });
      dialog.close();
      e.target.reset();
      showMsg(`${r.message} Código de reserva: ${r.reservation.code}. Total pagado: ${money(r.reservation.total)}.`, 'ok');
      buscar();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (err) {
      if (err.status === 401) { location.href = 'login.html?next=search.html'; return; }
      showMsg(err.message, 'error', '#dlg-msg');
    }
  });
}

/* ---------- Gestión de reservas ---------- */
function initReservations(user) {
  const lista = $('#lista-reservas');
  if (!user) { location.href = 'login.html?next=reservations.html'; return; }

  async function cargar() {
    try {
      const { reservations } = await api('manage_reservations.php');
      if (!reservations.length) { lista.innerHTML = '<p class="vacio">Todavía no tienes reservas. <a href="search.html">Busca un vuelo</a>.</p>'; return; }
      lista.innerHTML = reservations.map((r) => {
        const activa = r.status === 'confirmada' && parseDT(r.departure) > new Date();
        return `<article class="tarjeta">
          <div class="ruta">
            <div><div class="hora">${fmtTime(r.departure)}</div><div class="ciudad">${esc(r.origin)}</div></div>
            <div class="linea"></div>
            <div><div class="hora">${fmtTime(r.arrival)}</div><div class="ciudad">${esc(r.destination)}</div></div>
          </div>
          <div class="meta">Reserva <strong>${esc(r.code)}</strong> · Vuelo ${esc(r.flight_number)} · ${fmtDay(r.departure)}</div>
          <div class="meta">${r.passengers} pasajero(s) · ${money(r.total)} · tarjeta •••• ${esc(r.card_last4)} · pago ${esc(r.payment_status)}</div>
          <div class="acciones">
            <span class="etiqueta ${esc(r.status)}">${esc(r.status)}</span>
            ${activa ? `<button class="secundario" data-accion="update" data-id="${r.id}" data-p="${r.passengers}">Cambiar pasajeros</button>
                        <button class="peligro" data-accion="cancel" data-id="${r.id}">Cancelar reserva</button>` : ''}
          </div></article>`;
      }).join('');
    } catch (err) { showMsg(err.message); }
  }

  lista.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-accion]');
    if (!btn) return;
    clearMsg();
    try {
      let r;
      if (btn.dataset.accion === 'cancel') {
        if (!confirm('¿Cancelar esta reserva? Se reembolsará el pago.')) return;
        r = await post('manage_reservations.php', { action: 'cancel', id: Number(btn.dataset.id) });
      } else {
        const n = prompt('Nuevo número de pasajeros (1 a 9):', btn.dataset.p);
        if (n === null) return;
        r = await post('manage_reservations.php', { action: 'update', id: Number(btn.dataset.id), passengers: Number(n) });
      }
      showMsg(r.message, 'ok');
      cargar();
    } catch (err) { showMsg(err.message); }
  });
  cargar();
}

/* ---------- Arranque ---------- */
document.addEventListener('DOMContentLoaded', async () => {
  const page = document.body.dataset.page;
  const user = await renderNav(page);
  if (page === 'register') initRegister();
  if (page === 'login') initLogin();
  if (page === 'search') initSearch(user);
  if (page === 'reservations') initReservations(user);
});
