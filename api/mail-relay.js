// Stratton Prime — przekaźnik poczty wychodzącej (mail relay) jako funkcja serverless Vercel.
//
// DLACZEGO: Railway (backend CRM) ma ZABLOKOWANE wychodzące porty SMTP (465/587),
// więc nie może wysłać poczty bezpośrednio. Ta funkcja stoi na Vercel (outbound SMTP
// dozwolony), odbiera gotowy MIME po HTTPS od backendu i wysyła go przez SMTP home.pl
// autentykując się danymi skrzynki. Kontrakt POST jest identyczny jak `php-api/relay/mail-relay.php`
// oraz to, co wysyła `scripts/imapflow-mailbox.mjs::sendMessage`:
//   { secret, host, port, secure, user, pass, mail_from, rcpt:[...], raw_base64 }
//
// Bezpieczeństwo: dostęp tylko z poprawnym sekretem (porównanie w stałym czasie).
// Sekret = MAIL_RELAY_SECRET (ten sam co na Railway); fallback = wartość z repo.

import nodemailer from 'nodemailer'

const SECRET = process.env.MAIL_RELAY_SECRET || 'sprelay_9Fk2Lq7Px4Nv8Bz6Wt1Yd5Hc3Rj0Mg'

function safeEqual(a, b) {
  if (typeof a !== 'string' || typeof b !== 'string' || a.length !== b.length) return false
  let mismatch = 0
  for (let i = 0; i < a.length; i++) mismatch |= a.charCodeAt(i) ^ b.charCodeAt(i)
  return mismatch === 0
}

export default async function handler(req, res) {
  res.setHeader('Content-Type', 'application/json; charset=utf-8')

  if (req.method !== 'POST') {
    return res.status(405).json({ ok: false, error: 'method not allowed' })
  }

  let body = req.body
  if (typeof body === 'string') {
    try { body = JSON.parse(body) } catch { body = null }
  }
  if (!body || typeof body !== 'object') {
    return res.status(400).json({ ok: false, error: 'bad json' })
  }

  if (!safeEqual(String(body.secret || ''), SECRET)) {
    return res.status(403).json({ ok: false, error: 'forbidden' })
  }

  const host = String(body.host || 'serwer2690202.home.pl')
  const port = Number(body.port || 465)
  const secure = body.secure !== undefined ? Boolean(body.secure) : port === 465
  const user = String(body.user || '')
  const pass = String(body.pass || '')
  const mailFrom = String(body.mail_from || user)
  const rcpt = Array.isArray(body.rcpt)
    ? body.rcpt
    : String(body.rcpt || '').split(/[,;]/).map((s) => s.trim()).filter(Boolean)
  const rawB64 = String(body.raw_base64 || '')

  if (!user || !pass || rcpt.length === 0 || !rawB64) {
    return res.status(400).json({ ok: false, error: 'missing fields (user/pass/rcpt/raw_base64)' })
  }

  const raw = Buffer.from(rawB64, 'base64')

  try {
    const transporter = nodemailer.createTransport({
      host,
      port,
      secure,
      auth: { user, pass },
      connectionTimeout: 8000,
      greetingTimeout: 8000,
      socketTimeout: 9000,
      tls: { rejectUnauthorized: false }, // home.pl bywa kapryśne z certem na aliasach
    })

    const info = await transporter.sendMail({
      envelope: { from: mailFrom, to: rcpt },
      raw,
    })

    return res.status(200).json({ ok: true, messageId: info.messageId || null })
  } catch (err) {
    return res.status(502).json({ ok: false, error: String((err && err.message) || err) })
  }
}
