export function notifyAuthFailure(status) {
  if (status !== 401 || !localStorage.getItem('user_session')) return
  window.dispatchEvent(new CustomEvent('auth-expired'))
}
