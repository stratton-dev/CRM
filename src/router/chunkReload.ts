// Resilience for lazy-loaded route chunks after a frontend deploy.
//
// When a new build is deployed, the previously hashed asset filenames (e.g.
// DashboardView-AbC123.js) are replaced. A browser tab still running the OLD
// build that navigates to a not-yet-loaded route tries to fetch the old chunk,
// which now 404s → the dynamic import rejects → the router renders nothing
// (blank screen). A single full reload pulls the fresh index.html + new chunk
// names and recovers. F5 does this manually; this lets us do it automatically.
//
// Browsers phrase the failure differently, so we match several patterns.

const CHUNK_ERROR_PATTERNS = [
  'failed to fetch dynamically imported module', // Chromium
  'error loading dynamically imported module', // Firefox
  'importing a module script failed', // Safari
  'unable to preload css', // Vite CSS preload
  'dynamically imported module', // generic fallback
]

export function isChunkLoadError(message: unknown): boolean {
  const msg = String(message ?? '').toLowerCase()
  return CHUNK_ERROR_PATTERNS.some((pattern) => msg.includes(pattern))
}

/**
 * Decide whether to trigger a recovery reload for a (possibly) stale-chunk error.
 * A timestamp guard prevents an infinite reload loop when the chunk is genuinely
 * unfetchable (e.g. offline) rather than just stale.
 */
export function shouldReloadForChunkError(
  message: unknown,
  now: number,
  lastReloadAt: number | null,
  cooldownMs = 10000,
): boolean {
  if (!isChunkLoadError(message)) return false
  if (lastReloadAt != null && now - lastReloadAt < cooldownMs) return false
  return true
}
