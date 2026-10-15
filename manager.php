<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
$user = $_SESSION['user'] ?? null;
if (!is_array($user) || ($user['perfil'] ?? '') !== 'gestor') {
    header('Location: index.html');
    exit;
}
?>
<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#173d32">
  <title>Viano | Centro de operações</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/viano.css" rel="stylesheet">
  <script src="assets/js/tailwind-config.js"></script>
  <script src="https://cdn.tailwindcss.com/3.4.17"></script>
</head>
<body class="antialiased">
  <div class="layout min-h-screen" id="manager-app" data-csrf="<?= h(csrf_token()) ?>" data-maps-key="<?= h(google_browser_key()) ?>">
    <aside class="sidebar">
      <a class="brand no-underline" href="manager.php"><span class="brand-mark">V</span><span class="brand-name">viano</span></a>
      <p class="side-label">Operações</p>
      <nav aria-label="Navegação principal">
        <a class="side-link active" href="#overview"><i class="bi bi-grid-1x2 side-icon" aria-hidden="true"></i> Visão geral</a>
        <a class="side-link" href="#deliveries"><i class="bi bi-box-seam side-icon" aria-hidden="true"></i> Entregas</a>
        <a class="side-link" href="#routes"><i class="bi bi-signpost-2 side-icon" aria-hidden="true"></i> Rotas ativas</a>
      </nav>
      <div class="side-bottom">
        <div class="user-row">
          <span class="avatar"><?= h(strtoupper(substr($user['nome'], 0, 1))) ?></span>
          <div class="user-meta"><strong><?= h($user['nome']) ?></strong><span>Gestor</span></div>
          <button class="logout-icon" type="button" data-logout title="Terminar sessão" aria-label="Terminar sessão"><i class="bi bi-box-arrow-right" aria-hidden="true"></i></button>
        </div>
      </div>
    </aside>
    <main class="main" id="overview">
      <header class="page-top">
        <div><p class="eyebrow"><span id="today-date"></span></p><h1>Centro de operações</h1></div>
        <div class="top-meta"><span class="live-pill"><span class="live-dot"></span> Atualização ao vivo</span></div>
      </header>
      <section class="metrics" aria-label="Resumo de operações">
        <article class="metric"><div><span class="metric-label">Entregas pendentes</span><strong class="metric-value" id="metric-pending">0</strong></div><span class="metric-mark"><i class="bi bi-box-seam" aria-hidden="true"></i></span></article>
        <article class="metric"><div><span class="metric-label">Estafetas disponíveis</span><strong class="metric-value" id="metric-available">0</strong></div><span class="metric-mark"><i class="bi bi-person-check" aria-hidden="true"></i></span></article>
        <article class="metric"><div><span class="metric-label">Rotas em curso</span><strong class="metric-value" id="metric-routes">0</strong></div><span class="metric-mark"><i class="bi bi-signpost-split" aria-hidden="true"></i></span></article>
        <article class="metric"><div><span class="metric-label">Paragens concluídas</span><strong class="metric-value" id="metric-completed">0%</strong></div><span class="metric-mark"><i class="bi bi-check2-circle" aria-hidden="true"></i></span></article>
      </section>
      <div class="manager-grid">
        <section class="panel" aria-labelledby="map-title">
          <div class="panel-head"><div><h2 id="map-title">Mapa de operações</h2><p>Entregas por atribuir e posição dos estafetas</p></div><button class="icon-button" id="refresh-button" type="button" title="Atualizar agora" aria-label="Atualizar agora"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button></div>
          <div class="map-wrap"><div id="manager-map" role="application" aria-label="Mapa de entregas e estafetas"></div><div class="map-notice" id="map-notice">Configure GOOGLE_MAPS_BROWSER_KEY para ativar o mapa interativo.</div></div>
          <div class="map-legend"><span class="legend-item"><i class="legend-dot"></i> Entrega pendente</span><span class="legend-item"><i class="legend-dot driver"></i> Estafeta</span></div>
        </section>
        <div>
          <section class="panel" id="deliveries">
            <div class="panel-head"><div><h2>Nova entrega</h2><p>As moradas são geocodificadas ao guardar.</p></div></div>
            <form class="panel-body" id="delivery-form">
              <div class="form-grid">
                <div class="field"><label for="customer-name">Nome do cliente</label><input id="customer-name" maxlength="120" autocomplete="name"></div>
                <div class="field"><label for="customer-phone">Telefone</label><input id="customer-phone" maxlength="32" autocomplete="tel"></div>
                <div class="field field-full"><label for="customer-address">Morada de entrega</label><input id="customer-address" maxlength="500" autocomplete="street-address"></div>
                <div class="field field-full"><label for="delivery-notes">Notas</label><input id="delivery-notes" maxlength="5000" placeholder="Opcional"></div>
                <div class="field field-full"><label for="batch-input">Importar várias entregas</label><textarea id="batch-input" placeholder="Uma entrega por linha: nome;morada;telefone;notas"></textarea><span class="field-hint">Separador ponto e vírgula. Até 50 linhas por envio.</span></div>
              </div>
              <p class="notice" id="delivery-notice" role="status" aria-live="polite"></p>
              <button class="button button-primary" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i> Guardar entrega(s)</button>
            </form>
          </section>
          <section class="panel section-space" aria-labelledby="dispatch-title">
            <div class="panel-head"><div><h2 id="dispatch-title">Planear uma rota</h2><p>Escolha entregas pendentes e um estafeta livre.</p></div></div>
            <div class="panel-body">
              <div class="field"><label for="driver-select">Estafeta</label><select id="driver-select"><option value="">A carregar estafetas…</option></select></div>
              <div class="section-title"><h3>Entregas disponíveis</h3><p id="selection-count">0 selecionadas</p></div>
              <div class="delivery-list" id="pending-list"><div class="empty-state">A carregar entregas…</div></div>
              <p class="notice" id="route-notice" role="status" aria-live="polite"></p>
              <button class="button button-primary" id="optimize-button" type="button" disabled><i class="bi bi-magic" aria-hidden="true"></i> Otimizar e atribuir</button>
            </div>
          </section>
        </div>
      </div>
      <section class="section-space" id="routes">
        <div class="section-title"><div><p class="eyebrow">Acompanhamento</p><h2>Rotas ativas</h2></div><p>Progresso atualizado automaticamente</p></div>
        <div class="route-list" id="route-list"><div class="panel empty-state">Ainda não existem rotas ativas.</div></div>
      </section>
    </main>
  </div>
  <script type="module" src="assets/js/app.js"></script>
  <script type="module" src="assets/js/manager.js"></script>
</body>
</html>