const app = document.querySelector('#driver-app');
const csrf = app.dataset.csrf;
let locationWatch = null;
let lastLocationSent = 0;

async function api(url, options = {}) {
  const headers = new Headers(options.headers || {});
  if (options.body) headers.set('Content-Type', 'application/json');
  if (options.method && options.method !== 'GET') headers.set('X-CSRF-Token', csrf);
  const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...options, headers });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.error || 'Não foi possível concluir o pedido.');
  return data;
}

function setNotice(message, type = '') {
  const notice = document.querySelector('#driver-notice');
  notice.textContent = message;
  notice.className = `notice${type ? ` ${type}` : ''}`;
}

function makeButton(label, icon, className, action) {
  const button = document.createElement('button');
  button.type = 'button';
  button.className = `button ${className}`;
  const iconElement = document.createElement('i');
  iconElement.className = `bi ${icon}`;
  iconElement.setAttribute('aria-hidden', 'true');
  button.append(iconElement, document.createTextNode(` ${label}`));
  button.addEventListener('click', action);
  return button;
}

async function updateStop(deliveryId, action, button) {
  button.disabled = true;
  setNotice('A atualizar a paragem…');
  try {
    await api('api/atualizar_estado.php', { method: 'POST', body: JSON.stringify({ entrega_id: deliveryId, acao: action }) });
    setNotice(action === 'cheguei' ? 'Chegada registada.' : 'Estado da entrega atualizado.', 'success');
    await refresh();
  } catch (error) {
    setNotice(error.message, 'error');
    button.disabled = false;
  }
}

function renderRoute(route) {
  const list = document.querySelector('#stop-list');
  const empty = document.querySelector('#empty-route');
  list.replaceChildren();
  empty.hidden = Boolean(route);
  if (!route) {
    document.querySelector('#route-subtitle').textContent = 'Sem entregas pendentes neste momento.';
    document.querySelector('#route-distance').textContent = '-- km';
    document.querySelector('#route-time').textContent = '-- min';
    document.querySelector('#route-state').textContent = 'Sem rota';
    document.querySelector('#progress-bar').style.width = '0%';
    document.querySelector('#progress-label').textContent = '0 de 0';
    stopLocationTracking();
    return;
  }

  const stops = route.paragens || [];
  const completed = Number(route.concluidas);
  document.querySelector('#route-subtitle').textContent = `${stops.length} paragem${stops.length === 1 ? '' : 'ns'} na rota de hoje`;
  document.querySelector('#route-distance').textContent = `${Number(route.distancia_total_km).toLocaleString('pt-PT')} km`;
  document.querySelector('#route-time').textContent = `${route.tempo_estimado_min} min`;
  document.querySelector('#route-state').textContent = route.estado === 'em_curso' ? 'Em curso' : 'Planeada';
  document.querySelector('#progress-bar').style.width = `${route.percentagem}%`;
  document.querySelector('#progress-label').textContent = `${completed} de ${stops.length} concluídas`;

  const nextStop = stops.find((stop) => !['entregue', 'falhada'].includes(stop.entrega_estado));
  stops.forEach((stop) => {
    const done = ['entregue', 'falhada'].includes(stop.entrega_estado);
    const isCurrent = nextStop && Number(nextStop.entrega_id) === Number(stop.entrega_id);
    const item = document.createElement('article');
    item.className = `stop${done ? ' done' : ''}`;
    const number = document.createElement('span');
    number.className = 'stop-number';
    number.textContent = done ? '\u2713' : stop.ordem_sequencia;
    const card = document.createElement('div');
    card.className = `stop-card${isCurrent ? ' current' : ''}`;
    const title = document.createElement('h3');
    title.textContent = stop.cliente_nome;
    const address = document.createElement('address');
    address.textContent = stop.morada_destino;
    card.append(title, address);
    const meta = document.createElement('div');
    meta.className = 'stop-meta';
    if (stop.hora_prevista) meta.append(Object.assign(document.createElement('span'), { textContent: `Prevista ${new Intl.DateTimeFormat('pt-PT', { hour: '2-digit', minute: '2-digit' }).format(new Date(stop.hora_prevista.replace(' ', 'T')))}` }));
    if (stop.telefone_contacto) {
      const phone = document.createElement('a');
      phone.href = `tel:${stop.telefone_contacto}`;
      phone.textContent = `Ligar ${stop.telefone_contacto}`;
      meta.append(phone);
    }
    if (stop.notas) meta.append(Object.assign(document.createElement('span'), { textContent: stop.notas }));
    card.append(meta);

    if (!done && isCurrent) {
      const actions = document.createElement('div');
      actions.className = 'stop-actions';
      const navigation = document.createElement('a');
      navigation.className = 'button button-light';
      navigation.target = '_blank';
      navigation.rel = 'noopener noreferrer';
      navigation.href = `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(`${stop.latitude},${stop.longitude}`)}&travelmode=driving`;
      const navigationIcon = document.createElement('i');
      navigationIcon.className = 'bi bi-geo-alt';
      navigationIcon.setAttribute('aria-hidden', 'true');
      navigation.append(navigationIcon, document.createTextNode(' Iniciar navegação'));
      actions.append(navigation);
      if (!stop.hora_chegada) {
        actions.append(makeButton('Cheguei', 'bi-geo', 'button-primary', (event) => updateStop(stop.entrega_id, 'cheguei', event.currentTarget)));
      } else {
        actions.append(makeButton('Entregue com sucesso', 'bi-check2', 'button-primary', (event) => updateStop(stop.entrega_id, 'entregue', event.currentTarget)));
        actions.append(makeButton('Destinatário ausente', 'bi-person-x', 'button-danger', (event) => updateStop(stop.entrega_id, 'falhada', event.currentTarget)));
      }
      card.append(actions);
    } else if (done) {
      const status = document.createElement('div');
      status.className = 'stop-meta';
      status.textContent = stop.entrega_estado === 'entregue' ? 'Entrega concluída' : 'Entrega falhada';
      card.append(status);
    }
    item.append(number, card);
    list.append(item);
  });
  startLocationTracking();
}

async function refresh() {
  try {
    const data = await api('api/estado_rotas.php');
    renderRoute(data.rota);
  } catch (error) {
    setNotice(error.message, 'error');
  }
}

function stopLocationTracking() {
  if (locationWatch !== null && navigator.geolocation) {
    navigator.geolocation.clearWatch(locationWatch);
    locationWatch = null;
  }
}

function startLocationTracking() {
  if (locationWatch !== null || !navigator.geolocation) return;
  locationWatch = navigator.geolocation.watchPosition(async ({ coords }) => {
    if (Date.now() - lastLocationSent < 15000) return;
    lastLocationSent = Date.now();
    try {
      await api('api/localizacao.php', {
        method: 'POST',
        body: JSON.stringify({ latitude: coords.latitude, longitude: coords.longitude }),
      });
    } catch (error) {
      setNotice(error.message, 'error');
    }
  }, () => setNotice('Ative a localização para partilhar a posição com o gestor.', 'error'), { enableHighAccuracy: true, maximumAge: 10000, timeout: 15000 });
}

refresh();
window.setInterval(refresh, 12000);