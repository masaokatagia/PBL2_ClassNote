(function () {
  function getCookie(name) {
    const pattern = new RegExp('(?:^|;\\s*)' + name.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&') + '=([^;]*)');
    const match = document.cookie.match(pattern);
    return match ? decodeURIComponent(match[1]) : null;
  }

  function updateAuthButton() {
    const authLink = document.querySelector('.js-auth-link');
    if (!authLink) return;

    const isLoggedIn = Boolean(getCookie('user_id'));
    authLink.textContent = isLoggedIn ? 'ログアウト' : 'ログイン';
    authLink.href = isLoggedIn ? 'logout.php' : 'login.php';
    authLink.dataset.authState = isLoggedIn ? 'logged-in' : 'logged-out';
  }

  if (!window.getCookie) {
    window.getCookie = getCookie;
  }
  window.updateAuthButton = updateAuthButton;

  if (document.readyState === 'loading') {
    window.addEventListener('DOMContentLoaded', updateAuthButton);
  } else {
    updateAuthButton();
  }
})();
