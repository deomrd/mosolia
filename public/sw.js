// Service worker AvecXY.
// - Les fichiers statiques (css, js, icone) sont gardes en cache : l'application s'ouvre vite.
// - Les pages ne sont JAMAIS mises en cache : elles contiennent des donnees privees et doivent
//   toujours venir du serveur. Sans reseau, on affiche la page "hors ligne".
const VERSION = 'avecxy-2';
const HORS_LIGNE = 'hors-ligne.html';

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll([HORS_LIGNE, 'assets/icone.svg'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((cles) => Promise.all(cles.filter((k) => k !== VERSION).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const requete = e.request;
  if (requete.method !== 'GET') { return; }
  const url = new URL(requete.url);
  if (url.origin !== self.location.origin) { return; }

  if (requete.mode === 'navigate') {
    e.respondWith(fetch(requete).catch(() => caches.match(HORS_LIGNE)));
    return;
  }
  if (url.pathname.includes('/assets/')) {
    e.respondWith(caches.open(VERSION).then((cache) => cache.match(requete).then((enCache) => {
      const reseau = fetch(requete).then((reponse) => {
        if (reponse.ok) { cache.put(requete, reponse.clone()); }
        return reponse;
      }).catch(() => enCache);
      return enCache || reseau;
    })));
  }
});
