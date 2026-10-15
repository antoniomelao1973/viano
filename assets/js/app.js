const loginForm = document.querySelector('#login-form');
const setupForm = document.querySelector('#setup-form');
let csrfToken = '';

async function requestJson(url, options = {}) {
  const response = await fetch(url, { credentials: 'same-origin', ...options });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(payload.error || 'Não foi possível concluir o pedido.');
  return payload;
}

async function initializeAccessPage() {
  if (!loginForm || !setupForm) return;

  const result = await requestJson('api/auth.php');
  csrfToken = result.csrf;
  if (result.user) {
    window.location.assign(result.user.perfil === 'gestor' ? 'manager.php' : 'driver.php');
    return;
  }

  if (result.managerSetupNeeded) {
    setupForm.hidden = false;
  } else {
    loginForm.hidden = false;
  }
}

initializeAccessPage().catch(() => {
  if (loginForm) loginForm.hidden = false;
});

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

setupForm?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const button = setupForm.querySelector('button[type="submit"]');
  const notice = document.querySelector('#setup-notice');
  const form = new FormData(setupForm);
  button.disabled = true;
  notice.textContent = 'A criar conta…';
  notice.className = 'notice';
  try {
    const result = await requestJson('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({
        action: 'setup_manager',
        nome: form.get('nome'),
        email: form.get('email'),
        password: form.get('password'),
      }),
    });
    window.location.assign('manager.php');
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
      window.location.assign('index.html');
    }
  });
});