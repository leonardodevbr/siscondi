import { openDB } from 'idb';

export const dbPromise = openDB('cultura-cafarnaum', 1, {
  upgrade(db) {
    if (!db.objectStoreNames.contains('drafts')) {
      db.createObjectStore('drafts', { keyPath: 'id' });
    }
    if (!db.objectStoreNames.contains('syncQueue')) {
      const store = db.createObjectStore('syncQueue', { keyPath: 'id' });
      store.createIndex('createdAt', 'createdAt');
    }
  },
});

export async function pendingCount() {
  return (await dbPromise).count('syncQueue');
}
