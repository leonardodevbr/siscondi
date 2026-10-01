import { dbPromise } from './db';
import api from '../services/api';

export async function enqueueOperation(operation) {
  const db = await dbPromise;
  const item = {
    id: operation.id || crypto.randomUUID(),
    createdAt: new Date().toISOString(),
    status: 'pending',
    ...operation,
  };
  await db.put('syncQueue', item);
  return item;
}

export async function flushSyncQueue() {
  if (!navigator.onLine) return { synced: 0 };
  const db = await dbPromise;
  const items = await db.getAll('syncQueue');
  if (!items.length) return { synced: 0 };

  const { data } = await api.post('/sync/batch', { operations: items });
  const accepted = new Set((data.results || []).filter(r => r.status === 'synced' || r.status === 'duplicate').map(r => r.id));
  for (const id of accepted) await db.delete('syncQueue', id);
  return data;
}
