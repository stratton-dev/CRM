import { describe, it, expect } from 'vitest'
import { isChunkLoadError, shouldReloadForChunkError } from '@/router/chunkReload'

describe('isChunkLoadError', () => {
  it('detects the Chromium message', () => {
    expect(isChunkLoadError('Failed to fetch dynamically imported module: https://x/DashboardView-AbC1.js')).toBe(true)
  })
  it('detects the Firefox message', () => {
    expect(isChunkLoadError('error loading dynamically imported module')).toBe(true)
  })
  it('detects the Safari message', () => {
    expect(isChunkLoadError('Importing a module script failed.')).toBe(true)
  })
  it('detects the Vite CSS preload message', () => {
    expect(isChunkLoadError('Unable to preload CSS for /assets/x.css')).toBe(true)
  })
  it('ignores unrelated errors', () => {
    expect(isChunkLoadError('TypeError: Cannot read properties of undefined')).toBe(false)
    expect(isChunkLoadError(undefined)).toBe(false)
    expect(isChunkLoadError(null)).toBe(false)
  })
})

describe('shouldReloadForChunkError', () => {
  const now = 1_000_000

  it('reloads on first chunk error (no previous reload)', () => {
    expect(shouldReloadForChunkError('Failed to fetch dynamically imported module', now, null)).toBe(true)
  })

  it('does NOT reload again within the cooldown (prevents loop)', () => {
    expect(shouldReloadForChunkError('Failed to fetch dynamically imported module', now, now - 2000)).toBe(false)
  })

  it('reloads again after the cooldown elapses', () => {
    expect(shouldReloadForChunkError('Failed to fetch dynamically imported module', now, now - 20000)).toBe(true)
  })

  it('never reloads for non-chunk errors', () => {
    expect(shouldReloadForChunkError('some other error', now, null)).toBe(false)
  })
})
