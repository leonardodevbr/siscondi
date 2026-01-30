/**
 * Registra o Service Worker para notificações push e opcionalmente solicita permissão.
 * Para o backend enviar push: é necessário configurar Web Push (VAPID) e um endpoint
 * que envie notificações quando houver nova solicitação de diária pendente.
 */
let swRegistration = null;

export function usePushNotifications() {
  async function register() {
    if (!('serviceWorker' in navigator)) return null;
    try {
      const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
      swRegistration = reg;
      return reg;
    } catch (e) {
      console.warn('Service Worker registration failed:', e);
      return null;
    }
  }

  async function requestPermission() {
    if (!('Notification' in window)) return 'denied';
    if (Notification.permission === 'granted') return 'granted';
    if (Notification.permission === 'denied') return 'denied';
    const permission = await Notification.requestPermission();
    return permission;
  }

  return { register, requestPermission, get registration() { return swRegistration; } };
}
