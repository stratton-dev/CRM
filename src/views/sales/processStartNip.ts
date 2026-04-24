export type NipCheckPayload = {
  reserved?: boolean
  conflict?: boolean
  reserved_by_current_context?: boolean
  owner_name?: string | null
  valid_until?: string | null
}

export const mapNipReservationState = (payload: NipCheckPayload | null | undefined) => {
  const conflict = Boolean(payload?.conflict ?? payload?.reserved)
  const reservedByCurrentContext = Boolean(payload?.reserved_by_current_context)

  let message: string | null = null
  if (conflict) {
    const ownerName = payload?.owner_name ? ` (${payload.owner_name})` : ''
    const validUntil = payload?.valid_until ? ` do ${payload.valid_until}` : ''
    message = `NIP jest już zarezerwowany${ownerName}${validUntil}.`
  }

  return {
    conflict,
    reservedByCurrentContext,
    message,
  }
}

