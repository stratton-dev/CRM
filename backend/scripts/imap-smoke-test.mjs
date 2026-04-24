import { ImapFlow } from 'imapflow'

const env = process.env
const host = env.IMAP_HOST
const port = Number(env.IMAP_PORT || 993)
const secure = env.IMAP_SECURE !== '0'
const user = env.IMAP_USER
const pass = env.IMAP_PASS

if (!host || !user || !pass) {
  console.error('Missing IMAP_HOST/IMAP_USER/IMAP_PASS')
  process.exit(2)
}

const client = new ImapFlow({
  host,
  port,
  secure,
  auth: { user, pass },
  logger: false,
  keepalive: {
    interval: 30000,
    idleInterval: 300000,
    forceNoop: true,
  },
  socketTimeout: 30000,
  greetingTimeout: 15000,
})

const run = async () => {
  try {
    await client.connect()
    const folders = []
    for await (const box of client.list()) {
      folders.push(box.path)
    }
    console.log(JSON.stringify({ ok: true, folders }, null, 2))
    await client.logout()
    process.exit(0)
  } catch (err) {
    console.error(err?.message || String(err))
    try {
      if (client.connected) await client.logout()
    } catch {}
    process.exit(1)
  }
}

run()
