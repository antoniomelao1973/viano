const loginForm = document.querySelector('#login-form');

async function requestJson(url, options = {}) {
  const response = await fetch(url, { credentials: 'same-origin', ...options });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(payload.error || 'Não foi possível concluir o pedido.');
  return payload;
}

loginForm?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const button = loginForm.querySelector('button[type="submit"]');
  const notice = document.querySelector('#login-notice');
  button.disabled = true;
  notice.textContent = 'A validar credenciais…';
  notice.className = 'notice';
  try {
    const form = new FormData(loginForm);
    const result = await requestJson('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: form.get('email'), password: form.get('password') }),
    });
    window.location.assign(result.user.perfil === 'gestor' ? 'manager.php' : 'driver.php');
  } catch (error) {
    notice.textContent = error.message;
    notice.className = 'notice error';
  } finally {
    button.disabled = false;
  }
});

document.querySelectorAll('[data-logout]').forEach((button) => {
  button.addEventListener('click', async () => {
    try {
      await requestJson('api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('[data-csrf]')?.dataset.csrf || '' },
        body: JSON.stringify({ action: 'logout' }),
      });
    } finally {
      window.location.assign('index.php');
    }
  });
});