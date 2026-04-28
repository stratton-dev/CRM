import { ImapFlow } from 'imapflow'
import { simpleParser } from 'mailparser'
import nodemailer from 'nodemailer'
import { createRequire } from 'module'

const require = createRequire(import.meta.url)
const MailComposer = require('nodemailer/lib/mail-composer')

const readStdin = async () => {
  const chunks = []
  for await (const chunk of process.stdin) {
    chunks.push(chunk)
  }
  return Buffer.concat(chunks).toString('utf8')
}

const escapeHtml = (value) => value
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')
  .replace(/'/g, '&#39;')

const isDebug = process.env.IMAP_DEBUG === '1'
const logDebug = (level, message, meta = undefined) => {
  if (!isDebug) return
  const payload = {
    level,
    message,
    meta,
    time: new Date().toISOString(),
  }
  process.stderr.write(`${JSON.stringify(payload)}\n`)
}

const imapLogger = isDebug ? {
  info: (msg, meta) => logDebug('info', msg, meta),
  debug: (msg, meta) => logDebug('debug', msg, meta),
  warn: (msg, meta) => logDebug('warn', msg, meta),
  error: (msg, meta) => logDebug('error', msg, meta),
} : false

const hasFlag = (flags, flag) => flags instanceof Set ? flags.has(flag) : Array.isArray(flags) && flags.includes(flag)

const toSafeNumberOrString = (value) => {
  if (typeof value === 'bigint') {
    const max = BigInt(Number.MAX_SAFE_INTEGER)
    if (value <= max) return Number(value)
    return value.toString()
  }
  return value
}

const withTimeout = (promise, ms, label) => new Promise((resolve, reject) => {
  if (!ms || ms <= 0) return promise.then(resolve).catch(reject)
  const timer = setTimeout(() => {
    reject(new Error(`IMAP ${label} timeout after ${ms}ms`))
  }, ms)
  promise.then((value) => {
    clearTimeout(timer)
    resolve(value)
  }).catch((error) => {
    clearTimeout(timer)
    reject(error)
  })
})

const checkElapsedTimeout = (startedAt, limitMs, label) => {
  if (!limitMs || limitMs <= 0) return
  const elapsed = Date.now() - startedAt
  if (elapsed > limitMs) {
    throw new Error(`IMAP ${label} timeout after ${limitMs}ms`)
  }
}

const connectImap = async (config, timings = undefined) => {
  const client = new ImapFlow({
    host: config.host,
    port: config.port,
    secure: config.secure,
    auth: config.auth,
    logger: imapLogger,
    keepalive: {
      interval: 30000,
      idleInterval: 300000,
      forceNoop: true,
    },
    socketTimeout: 30000,
    greetingTimeout: 15000,
  })
  client.on('error', (err) => logDebug('error', 'imapflow error', { message: err?.message }))
  client.on('close', () => logDebug('info', 'imapflow close'))
  const connectTimeoutMs = Number(process.env.IMAP_CONNECT_TIMEOUT_MS || 15000)
  const startedAt = Date.now()
  await withTimeout(client.connect(), connectTimeoutMs, 'connect')
  if (timings) timings.connect_ms = Date.now() - startedAt
  return client
}

const ensureConnected = async (client) => {
  if (client.usable) return
  logDebug('info', 'imapflow reconnecting')
  await client.connect()
}

const listMessages = async ({ config, params }) => {
  const timings = params?.diagnostics ? {} : undefined
  const client = await connectImap(config, timings)
  try {
    await ensureConnected(client)
    const lock = await client.getMailboxLock(params.folderName)
    try {
      const mailbox = client.mailbox || {}
      const searchTimeoutMs = Number(process.env.IMAP_SEARCH_TIMEOUT_MS || 15000)
      const searchStart = Date.now()
      const searchResult = await withTimeout(client.search({ all: true }, { uid: true }), searchTimeoutMs, 'search')
      if (timings) timings.search_ms = Date.now() - searchStart
      const uids = Array.isArray(searchResult) ? searchResult.map(toSafeNumberOrString) : []
      const limit = Number(params.limit) || 50
      const recent = uids.slice(-limit)
      if (recent.length === 0) {
        return {
          messages: [],
          meta: {
            folderName: params.folderName,
            total: toSafeNumberOrString(mailbox.exists || 0),
            uidCount: uids.length,
            uidNext: toSafeNumberOrString(mailbox.uidNext || null),
            uidValidity: toSafeNumberOrString(mailbox.uidValidity || null),
          },
        }
      }

      const messages = []
      const fetchTimeoutMs = Number(process.env.IMAP_FETCH_TIMEOUT_MS || 20000)
      const fetchStart = Date.now()
      const fetchIterator = client.fetch(recent, { uid: true, envelope: true, flags: true, internalDate: true }, { uid: true })
      for await (const msg of fetchIterator) {
        checkElapsedTimeout(fetchStart, fetchTimeoutMs, 'fetch')
        const from = msg.envelope?.from?.[0]
        const to = msg.envelope?.to?.[0]
        messages.push({
          id: `${params.folderKey}:${toSafeNumberOrString(msg.uid)}`,
          fromName: from?.name || from?.address || '',
          fromEmail: from?.address || '',
          toEmail: to?.address || '',
          subject: msg.envelope?.subject || '(bez tematu)',
          body: '',
          attachments: [],
          date: (msg.internalDate || new Date()).toISOString(),
          read: hasFlag(msg.flags, '\\Seen'),
          folder: params.folderKey,
        })
      }
      if (timings) timings.fetch_ms = Date.now() - fetchStart
      return {
        messages,
        meta: {
          folderName: params.folderName,
          total: toSafeNumberOrString(mailbox.exists || messages.length),
          uidCount: uids.length,
          uidNext: toSafeNumberOrString(mailbox.uidNext || null),
          uidValidity: toSafeNumberOrString(mailbox.uidValidity || null),
          timings,
        },
      }
    } finally {
      lock.release()
    }
  } finally {
    if (client.usable) {
      await client.logout().catch(() => {})
    }
  }
}

const listFolders = async ({ config, params }) => {
  const timings = params?.diagnostics ? {} : undefined
  const client = await connectImap(config, timings)
  try {
    await ensureConnected(client)
    const listTimeoutMs = Number(process.env.IMAP_LIST_TIMEOUT_MS || 15000)
    const listStart = Date.now()
    const mailboxes = await withTimeout(client.list(), listTimeoutMs, 'list')
    if (timings) timings.list_ms = Date.now() - listStart
    if (mailboxes.length === 0) {
      throw new Error('IMAP: lista folderów pusta (sprawdź dane logowania lub uprawnienia).')
    }
    const folders = mailboxes.map((box) => ({
      path: box.path,
      name: box.name || box.path,
      flags: box.flags || [],
      listed: box.listed !== false,
      specialUse: box.specialUse || null,
    }))
    if (timings) {
      return { folders, meta: { timings } }
    }
    return folders
  } finally {
    if (client.usable) {
      await client.logout().catch(() => {})
    }
  }
}

const markRead = async ({ config, params }) => {
  const client = await connectImap(config)
  try {
    await ensureConnected(client)
    const lock = await client.getMailboxLock(params.folderName)
    try {
      await client.mailboxOpen(params.folderName, { readOnly: false })
      await client.messageFlagsAdd(params.uid, ['\\Seen'], { uid: true })
    } finally {
      lock.release()
    }
  } finally {
    if (client.usable) {
      await client.logout().catch(() => {})
    }
  }
}

const testConnection = async ({ config }) => {
  const client = await connectImap(config)
  try {
    await ensureConnected(client)
    await client.noop()
    return true
  } finally {
    if (client.usable) {
      await client.logout().catch(() => {})
    }
  }
}

const sendMessage = async ({ config, params }) => {
  const fromName = params.from_name || config.smtp.from?.name || undefined
  const fromEmail = params.from_email || config.smtp.from?.email || config.smtp.auth?.user
  if (!fromEmail) {
    throw new Error('Missing from email')
  }

  const mailOptions = {
    from: fromName ? `${fromName} <${fromEmail}>` : fromEmail,
    to: params.to,
    subject: params.subject,
    html: params.body,
    attachments: Array.isArray(params.attachments)
      ? params.attachments.map((attachment) => ({
          filename: attachment.filename || 'attachment',
          content: attachment.content || '',
          contentType: attachment.content_type || attachment.contentType || 'application/octet-stream',
          encoding: attachment.encoding || 'base64',
        }))
      : undefined,
  }

  const transport = nodemailer.createTransport({
    host: config.smtp.host,
    port: config.smtp.port,
    secure: config.smtp.secure,
    auth: config.smtp.auth,
  })

  const info = await transport.sendMail(mailOptions)

  const composer = new MailComposer(mailOptions)
  const raw = await composer.compile().build()

  if (config.imap && config.folderName) {
    const client = await connectImap(config.imap)
    try {
      await client.append(config.folderName, raw, ['\\Seen'], new Date())
    } finally {
      await client.logout().catch(() => {})
    }
  }

  return { messageId: info.messageId, response: info.response }
}

const getMessageBody = async ({ config, params }) => {
  const client = await connectImap(config)
  try {
    await ensureConnected(client)
    const lock = await client.getMailboxLock(params.folderName)
    try {
      const uid = params.uid
      const fetchTimeoutMs = Number(process.env.IMAP_BODY_TIMEOUT_MS || 45000)
      const fetchStart = Date.now()
      const fetchIterator = client.fetch([uid], { uid: true, envelope: true, flags: true, internalDate: true, source: true }, { uid: true })
      for await (const msg of fetchIterator) {
        checkElapsedTimeout(fetchStart, fetchTimeoutMs, 'body fetch')
        const parsed = await simpleParser(msg.source)
        const from = parsed.from?.value?.[0]
        const to = parsed.to?.value?.[0]
        const fromName = from?.name || msg.envelope?.from?.[0]?.name || from?.address || ''
        const fromEmail = from?.address || msg.envelope?.from?.[0]?.address || ''
        const toEmail = to?.address || msg.envelope?.to?.[0]?.address || ''
        const body = parsed.html || parsed.textAsHtml || (parsed.text ? `<pre>${escapeHtml(parsed.text)}</pre>` : '')
        const attachments = Array.isArray(parsed.attachments)
          ? parsed.attachments.map((attachment) => ({
              filename: attachment.filename || '',
              contentType: attachment.contentType || '',
              size: Number(attachment.size || 0),
              cid: attachment.cid || '',
              isInline: attachment.contentDisposition === 'inline',
            }))
          : []
        return {
          id: `${params.folderKey}:${toSafeNumberOrString(msg.uid)}`,
          fromName,
          fromEmail,
          toEmail,
          subject: parsed.subject || msg.envelope?.subject || '(bez tematu)',
          body,
          attachments,
          date: (parsed.date || msg.internalDate || new Date()).toISOString(),
          read: hasFlag(msg.flags, '\\Seen'),
          folder: params.folderKey,
        }
      }
      throw new Error('Message not found: ' + uid)
    } finally {
      lock.release()
    }
  } finally {
    if (client.usable) {
      await client.logout().catch(() => {})
    }
  }
}

const main = async () => {
  try {
    const input = await readStdin()
    const payload = JSON.parse(input)

    if (payload.action === 'list') {
      const data = await listMessages(payload)
      process.stdout.write(JSON.stringify({ data }))
      return
    }

    if (payload.action === 'folders') {
      const data = await listFolders(payload)
      process.stdout.write(JSON.stringify({ data }))
      return
    }

    if (payload.action === 'markRead') {
      await markRead(payload)
      process.stdout.write(JSON.stringify({ data: true }))
      return
    }

    if (payload.action === 'test') {
      const data = await testConnection(payload)
      process.stdout.write(JSON.stringify({ data }))
      return
    }

    if (payload.action === 'getBody') {
      const data = await getMessageBody(payload)
      process.stdout.write(JSON.stringify({ data }))
      return
    }

    if (payload.action === 'send') {
      const data = await sendMessage(payload)
      process.stdout.write(JSON.stringify({ data }))
      return
    }

    process.stdout.write(JSON.stringify({ error: 'Unknown action' }))
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error)
    const responseText = error?.responseText ?? error?.response ?? undefined
    const extra = responseText ? ` | server: ${responseText}` : ''
    process.stdout.write(JSON.stringify({ error: message + extra }))
  }
}

main()
