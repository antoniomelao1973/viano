<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
$user = $_SESSION['user'] ?? null;
if (!is_array($user) || ($user['perfil'] ?? '') !== 'entregador') {
    header('Location: index.html');
    exit;
}
?>
<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#173d32">
  <title>Viano | A minha rota</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/viano.css" rel="stylesheet">
  <script src="assets/js/tailwind-config.js"></script>
  <script src="https://cdn.tailwindcss.com/3.4.17"></script>
</head>
<body class="antialiased">
  <main class="driver-main min-h-screen" id="driver-app" data-csrf="<?= h(csrf_token()) ?>">
    <header class="driver-header">
      <a class="brand no-underline" href="driver.php"><span class="brand-mark">V</span><span class="brand-name">viano</span></a>
      <div class="user-row"><div class="user-meta text-right"><strong><?= h($user['nome']) ?></strong><span>Estafeta</span></div><button class="logout-icon" type="button" data-logout title="Terminar sessão" aria-label="Terminar sessão"><i class="bi bi-box-arrow-right" aria-hidden="true"></i></button></div>
    </header>
    <section class="driver-hero">
      <p class="eyebrow">A minha rota</p>
      <h1 id="route-heading">Bom trabalho, <?= h($user['nome']) ?>.</h1>
      <p id="route-subtitle">A carregar as paragens atribuídas…</p>
      <div class="driver-progress"><div class="progress-track"><div class="progress-bar" id="progress-bar" style="width:0%"></div></div><span id="progress-label">0 de 0</span></div>
    </section>
    <section class="driver-stats" aria-label="Resumo da rota">
      <article class="driver-stat"><span>Distância planeada</span><strong id="route-distance">-- km</strong></article>
      <article class="driver-stat"><span>Tempo estimado</span><strong id="route-time">-- min</strong></article>
    </section>
    <section aria-labelledby="stops-heading">
      <div class="section-title"><h2 id="stops-heading">Paragens</h2><span class="state-pill" id="route-state">A carregar</span></div>
      <div class="stop-list" id="stop-list"></div>
      <div class="driver-empty" id="empty-route" hidden><h2>Sem rota ativa</h2><p>Quando o gestor lhe atribuir uma rota, as paragens aparecem aqui.</p></div>
      <p class="notice" id="driver-notice" role="status" aria-live="polite"></p>
    </section>
  </main>
  <script type="module" src="assets/js/app.js"></script>
  <script type="module" src="assets/js/driver.js"></script>
</body>
</html>