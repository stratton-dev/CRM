import DOMPurify from 'dompurify'

/**
 * Sanitize untrusted HTML before rendering with v-html.
 * Used for inbound email bodies and any author-supplied rich text — strips
 * <script>, event handlers, iframes, etc. so a malicious email/post can't run
 * JS in the CRM origin (which would expose the Supabase JWT in localStorage).
 */
export function sanitizeHtml(dirty: string | null | undefined): string {
  if (!dirty) return ''
  return DOMPurify.sanitize(String(dirty), {
    FORBID_TAGS: ['script', 'iframe', 'object', 'embed', 'form', 'base'],
    FORBID_ATTR: ['onerror', 'onload', 'onclick', 'onmouseover', 'formaction'],
    ADD_ATTR: ['target'],
  })
}
