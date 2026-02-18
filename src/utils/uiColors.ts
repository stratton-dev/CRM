export type NotificationTone = {
  icon: string
  className: string
}

export const notificationTones: Record<string, NotificationTone> = {
  CRITICAL: { icon: 'exclamation-triangle', className: 'text-amber-600' },
  INFO: { icon: 'info', className: 'text-sky-600' },
  WARNING: { icon: 'bolt', className: 'text-amber-500' },
  TASK: { icon: 'list', className: 'text-indigo-600' },
  NOTE: { icon: 'document-text', className: 'text-gray-500' },
  REMINDER: { icon: 'clock', className: 'text-blue-500' },
  CONTACT: { icon: 'phone', className: 'text-indigo-500' },
  ATTENTION: { icon: 'exclamation-circle', className: 'text-amber-600 font-bold' },
  REPRIMAND: { icon: 'x-circle', className: 'text-red-600 font-bold' },
}

export const getNotificationTone = (type?: string): NotificationTone => {
  if (!type) return notificationTones.NOTE
  return notificationTones[type] || notificationTones.NOTE
}

export type StatusTone = {
  icon: string
  label: string
  className: string
}

export const autentiStatusTones: Record<string, StatusTone> = {
  SENT: { icon: 'envelope', label: 'Wysłano', className: 'bg-blue-100 text-blue-800' },
  VIEWED: { icon: 'eye', label: 'Obejrzano', className: 'bg-yellow-100 text-yellow-800' },
  SIGNED: { icon: 'check-circle', label: 'Podpisano', className: 'bg-green-100 text-green-800' },
  REJECTED: { icon: 'x-circle', label: 'Odrzucono', className: 'bg-red-100 text-red-800' },
}

export const getAutentiStatusTone = (status?: string): StatusTone => {
  if (!status) return autentiStatusTones.REJECTED
  return autentiStatusTones[status] || autentiStatusTones.REJECTED
}
