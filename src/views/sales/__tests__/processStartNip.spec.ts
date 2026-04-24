import { describe, expect, it } from 'vitest'
import { mapNipReservationState } from '../processStartNip'

describe('mapNipReservationState', () => {
  it('maps same-context reservation to no conflict', () => {
    const result = mapNipReservationState({
      reserved: true,
      conflict: false,
      reserved_by_current_context: true,
      owner_name: 'Jan Kowalski',
      valid_until: '2026-02-28',
    })

    expect(result.conflict).toBe(false)
    expect(result.reservedByCurrentContext).toBe(true)
    expect(result.message).toBeNull()
  })

  it('maps foreign reservation to conflict with owner/date message', () => {
    const result = mapNipReservationState({
      reserved: true,
      conflict: true,
      reserved_by_current_context: false,
      owner_name: 'Anna Nowak',
      valid_until: '2026-03-03',
    })

    expect(result.conflict).toBe(true)
    expect(result.reservedByCurrentContext).toBe(false)
    expect(result.message).toContain('Anna Nowak')
    expect(result.message).toContain('2026-03-03')
  })

  it('keeps backward compatibility when only reserved is returned', () => {
    const result = mapNipReservationState({
      reserved: true,
    })

    expect(result.conflict).toBe(true)
    expect(result.message).toBe('NIP jest już zarezerwowany.')
  })
})

