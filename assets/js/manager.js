const app = document.querySelector('#manager-app');
const csrf = app.dataset.csrf;
const selectedDeliveries = new Set();
const markers = new Map();
let map;
let latestData = { entregas_pendentes: [], entregadores: [], rotas: [] };

async function api(url, options = {}) {
  const headers = new Headers(options.headers || {});
  if (options.body) headers.set('Content-Type', 'application/json');
  if (options.method && options.method !== 'GET') headers.set('X-CSRF-Token', csrf);
  const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...options, headers });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.error || 'Não foi possível concluir o pedido.');
  return data;
}

function setNotice(id, text, type = '') {
  const node = document.getElementById(id);
  node.textContent = text;
  node.className = `notice${type ? ` ${type}` : ''}`;
}

function appendText(parent, tag, text, className = '') {
  const node = document.createElement(tag);
  node.textContent = text;
  if (className) node.className = className;
  parent.append(node);
  return node;
}

function renderPending(deliveries, drivers) {
  const list = document.querySelector('#pending-list');
  const driverSelect = document.querySelector('#driver-select');
  const selectedDriver = driverSelect.value;
  list.replaceChildren();
  driverSelect.replaceChildren(new Option('Escolher estafeta', ''));
  drivers.filter((driver) => driver.estado_disponibilidade === 'disponivel').forEach((driver) => {
    driverSelect.add(new Option(driver.nome, driver.id));
  });
  driverSelect.value = selectedDriver;
  if (!deliveries.length) {
    appendText(list, 'p', 'Não existem entregas pendentes.', 'empty-state');
  }
  deliveries.forEach((delivery) => {
    const label = document.createElement('label');
    label.className = 'delivery-option';
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.value = delivery.id;
    checkbox.checked = selectedDeliveries.has(String(delivery.id));
    checkbox.addEventListener('change', () => {
      if (checkbox.checked) selectedDeliveries.add(String(delivery.id));
      else selectedDeliveries.delete(String(delivery.id));
      updateSelectionCount();
    });
    const details = document.createElement('span');
    appendText(details, 'strong', delivery.cliente_nome);
    appendText(details, 'span', delivery.morada_destino);
    label.append(checkbox, details);
    list.append(label);
  });
  updateSelectionCount();
}

function updateSelectionCount() {
  const count = selectedDeliveries.size;
  document.querySelector('#selection-count').textContent = `${count} selecionada${count === 1 ? '' : 's'}`;
  document.querySelector('#optimize-button').disabled = count === 0 || !document.querySelector('#driver-select').value;
}

function renderRoutes(routes) {
  const list = document.querySelector('#route-list');
  list.replaceChildren();
  if (!routes.length) {
    appendText(list, 'div', 'Ainda não existem rotas ativas.', 'panel empty-state');
    return;
  }
  routes.forEach((route) => {
    const item = document.createElement('article');
    item.className = 'panel route-item';
    const summary = document.createElement('div');
    summary.className = 'route-summary';
    appendText(summary, 'strong', route.entregador_nome);
    appendText(summary, 'span', route.estado === 'em_curso' ? 'Em curso' : 'Planeada', 'state-pill');
    item.append(summary);
    const meta = document.createElement('div');
    meta.className = 'route-meta';
    appendText(meta, 'span', `${route.concluidas}/${route.total_paragens} paragens`);
    appendText(meta, 'span', `${Number(route.distancia_total_km).toLocaleString('pt-PT')} km`);
    appendText(meta, 'span', `${route.tempo_estimado_min} min`);
    item.append(meta);
    const track = document.createElement('div');
    track.className = 'progress-track';
    const bar = document.createElement('div');
    bar.className = 'progress-bar';
    bar.style.width = `${route.percentagem}%`;
    track.append(bar);
    item.append(track);
    list.append(item);
  });
}

function renderMetrics(data) {
  const available = data.entregadores.filter((driver) => driver.estado_disponibilidade === 'disponivel').length;
  const totalStops = data.rotas.reduce((sum, route) => sum + Number(route.total_paragens), 0);
  const doneStops = data.rotas.reduce((sum, route) => sum + Number(route.concluidas), 0);
  document.querySelector('#metric-pending').textContent = data.entregas_pendentes.length;
  document.querySelector('#metric-available').textContent = available;
  document.querySelector('#metric-routes').textContent = data.rotas.length;
  document.querySelector('#metric-completed').textContent = `${totalStops ? Math.round(doneStops / totalStops * 100) : 0}%`;
}

function updateMap(data) {
  if (!map) return;
  const next = new Map();
  data.entregas_pendentes.forEach((delivery) => {
    const key = `delivery-${delivery.id}`;
    next.set(key, { position: { lat: Number(delivery.latitude), lng: Number(delivery.longitude) }, title: `${delivery.cliente_nome} · ${delivery.morada_destino}`, color: '#e8785b' });
  });
  data.entregadores.forEach((driver) => {
    if (driver.latitude === null || driver.longitude === null) return;
    const key = `driver-${driver.id}`;
    next.set(key, { position: { lat: Number(driver.latitude), lng: Number(driver.longitude) }, title: `${driver.nome} · ${driver.estado_disponibilidade}`, color: '#187458' });
  });
  for (const [key, marker] of markers) {
    if (!next.has(key)) {
      marker.setMap(null);
      markers.delete(key);
    }
  }
  const bounds = new google.maps.LatLngBounds();
  next.forEach((details, key) => {
    let marker = markers.get(key);
    if (!marker) {
      marker = new google.maps.Marker({ map, position: details.position, title: details.title, icon: { path: google.maps.SymbolPath.CIRCLE, scale: 8, fillColor: details.color, fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 2 } });
      markers.set(key, marker);
    } else {
      marker.setPosition(details.position);
      marker.setTitle(details.title);
    }
    bounds.extend(details.position);
  });
  if (!bounds.isEmpty()) map.fitBounds(bounds, 50);
}

async function loadMap() {
  const key = app.dataset.mapsKey;
  if (!key) return;
  const notice = document.querySelector('#map-notice');
  try {
    await new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&v=weekly`;
      script.onload = resolve;
      script.onerror = reject;
      document.head.append(script);
    });
    map = new google.maps.Map(document.querySelector('#manager-map'), {
      center: { lat: 38.7223, lng: -9.1393 }, zoom: 11, mapTypeControl: false,
      streetViewControl: false, fullscreenControl: true, clickableIcons: false,
    });
    notice.hidden = true;
    updateMap(latestData);
  } catch {
    notice.textContent = 'Não foi possível carregar o Google Maps. Verifique a chave e as restrições do domínio.';
  }
}

async function refresh() {
  try {
    latestData = await api('api/estado_rotas.php');
    renderPending(latestData.entregas_pendentes, latestData.entregadores);
    renderRoutes(latestData.rotas);
    renderMetrics(latestData);
    updateMap(latestData);
  } catch (error) {
    setNotice('route-notice', error.message, 'error');
  }
}

document.querySelector('#delivery-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const button = event.currentTarget.querySelector('button[type="submit"]');
  const batchText = document.querySelector('#batch-input').value.trim();
  let items;
  if (batchText) {
    items = batchText.split(/\r?\n/).filter(Boolean).map((line) => {
      const [cliente_nome = '', morada_destino = '', telefone_contacto = '', notas = ''] = line.split(';').map((part) => part.trim());
      return { cliente_nome, morada_destino, telefone_contacto, notas };
    });
  } else {
    items = [{
      cliente_nome: document.querySelector('#customer-name').value.trim(),
      morada_destino: document.querySelector('#customer-address').value.trim(),
      telefone_contacto: document.querySelector('#customer-phone').value.trim(),
      notas: document.querySelector('#delivery-notes').value.trim(),
    }];
  }
  if (items.length > 50 || items.some((item) => !item.cliente_nome || !item.morada_destino)) {
    setNotice('delivery-notice', 'Cada linha deve conter nome e morada; o máximo é 50.', 'error');
    return;
  }
  button.disabled = true;
  setNotice('delivery-notice', `A geocodificar ${items.length} entrega(s)…`);
  try {
    const result = await api('api/entregas.php', { method: 'POST', body: JSON.stringify({ items }) });
    const failures = result.erros?.length || 0;
    setNotice('delivery-notice', `${result.criados.length} entrega(s) guardada(s).${failures ? ` ${failures} linha(s) com erro.` : ''}`, failures ? 'error' : 'success');
    if (failures) console.warn('Linhas rejeitadas:', result.erros);
    event.currentTarget.reset();
    await refresh();
  } catch (error) {
    setNotice('delivery-notice', error.message, 'error');
  } finally {
    button.disabled = false;
  }
});

document.querySelector('#driver-select').addEventListener('change', updateSelectionCount);
document.querySelector('#optimize-button').addEventListener('click', async (event) => {
  const button = event.currentTarget;
  button.disabled = true;
  setNotice('route-notice', 'A consultar a matriz de distâncias e a otimizar a sequência…');
  try {
    const result = await api('api/otimizar_rota.php', {
      method: 'POST',
      body: JSON.stringify({ entregador_id: document.querySelector('#driver-select').value, entrega_ids: [...selectedDeliveries] }),
    });
    selectedDeliveries.clear();
    setNotice('route-notice', `Rota #${result.rota_id} atribuída: ${result.ordem_entregas.length} paragens, ${result.distancia_total_km} km.`, 'success');
    await refresh();
  } catch (error) {
    setNotice('route-notice', error.message, 'error');
  } finally {
    updateSelectionCount();
  }
});

document.querySelector('#refresh-button').addEventListener('click', refresh);
document.querySelector('#today-date').textContent = new Intl.DateTimeFormat('pt-PT', { dateStyle: 'long' }).format(new Date());
refresh();
loadMap();
window.setInterval(refresh, 15000);