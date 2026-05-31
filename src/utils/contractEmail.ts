/**
 * Generator HTML draftu umowy ramowej współpracy Stratton Prime.
 * Używany dla:
 *   - PDF do pobrania (klient + załącznik maila)
 *   - Tekst draftu maila
 *
 * Wszystkie dane klienta podstawiane dynamicznie z karty CRM.
 */

interface ContractClientData {
  name?: string
  nip?: string
  regon?: string | null
  krs?: string | null
  street?: string
  buildingNr?: string
  localeNr?: string
  zip?: string
  city?: string
  contactName?: string
  contactEmail?: string
  contactPhone?: string
  contacts?: Array<{ name?: string; email?: string; phone?: string; is_decision_maker?: boolean; isDecisionMaker?: boolean }>
  employeesTotal?: number
  serviceFeePercent?: number
}

interface AgentData {
  name?: string
  email?: string
  phone?: string
}

function fmtDate(d: Date = new Date()): string {
  return d.toLocaleDateString('pl-PL', { day: '2-digit', month: 'long', year: 'numeric' })
}

function pickPrimaryContact(client: ContractClientData) {
  const list = Array.isArray(client.contacts) ? client.contacts : []
  return (
    list.find((c) => c.is_decision_maker || c.isDecisionMaker)
    || list[0]
    || { name: client.contactName, email: client.contactEmail, phone: client.contactPhone }
  )
}

export function pickContactEmail(client: ContractClientData): string {
  const c = pickPrimaryContact(client)
  return c?.email || client.contactEmail || ''
}

export function pickContactName(client: ContractClientData): string {
  const c = pickPrimaryContact(client)
  return c?.name || client.contactName || ''
}

/**
 * Pełny HTML umowy ramowej (gotowy do konwersji HTML → PDF przez backend
 * mailbox attachment z `convert_to_pdf: true`).
 */
export function buildContractHtml(client: ContractClientData): string {
  const today = new Date()
  const year = today.getFullYear()
  const offerNum = `STR/${year}/${String(client.nip || '0000').slice(0, 4)}`
  const dateStr = fmtDate(today)
  const employees = client.employeesTotal ?? '—'
  const fee = client.serviceFeePercent != null ? `${client.serviceFeePercent}` : '22'
  const contactName = pickContactName(client) || '[osoba upoważniona]'

  const addrLine = [
    client.street,
    client.buildingNr,
    client.localeNr,
  ].filter(Boolean).join(' ').trim()
  const cityLine = [client.zip, client.city].filter(Boolean).join(' ').trim()

  return `<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<title>Umowa Ramowa Współpracy — ${escapeHtml(client.name || 'Klient')}</title>
<style>
  @page { size: A4; margin: 18mm 16mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Georgia', 'Times New Roman', serif; color: #1e293b; font-size: 11pt; line-height: 1.55; }
  .header { text-align: center; margin-bottom: 24px; }
  .header h1 { font-size: 16pt; font-weight: 800; text-transform: uppercase; margin: 0 0 6px; letter-spacing: 1px; }
  .header .num { color: #64748b; font-size: 10pt; font-family: 'Courier New', monospace; }
  .draft-mark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); font-size: 96pt; color: rgba(220, 38, 38, 0.10); font-weight: 900; pointer-events: none; z-index: 0; letter-spacing: 8px; }
  .parties { margin-bottom: 24px; }
  .parties p { margin: 4px 0; }
  .party-box { margin: 12px 0 12px 18px; padding-left: 14px; border-left: 3px solid #C5A059; }
  .party-box .name { font-weight: 800; }
  section { margin-top: 18px; }
  section h2 { font-size: 10pt; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #94a3b8; padding-bottom: 4px; margin: 0 0 8px; }
  section p { margin: 6px 0; text-align: justify; }
  .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 60px; }
  .sig { text-align: center; }
  .sig .line { border-bottom: 2px dotted #1e293b; height: 56px; margin-bottom: 6px; background: rgba(197, 160, 89, 0.04); }
  .sig .label { font-size: 9pt; text-transform: uppercase; font-weight: 700; letter-spacing: 2px; color: #475569; }
  .footer { margin-top: 32px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 8pt; color: #94a3b8; text-align: center; line-height: 1.4; }
  .strong { font-weight: 800; }
  .gold { color: #C5A059; }
</style>
</head>
<body>
<div class="draft-mark">DRAFT</div>

<div class="header">
  <h1>Umowa Ramowa o Współpracy</h1>
  <p class="num">Nr ${offerNum} · ${dateStr}</p>
</div>

<div class="parties">
  <p>Zawarta w dniu <span class="strong">${dateStr}</span> w Gdańsku, pomiędzy:</p>

  <div class="party-box">
    <p class="name">Stratton Prime Sp. z o.o.</p>
    <p>ul. Nowy Świat 42/44, 80-299 Gdańsk</p>
    <p>NIP: 5842867357, REGON: 525000000</p>
    <p>zwaną dalej <span class="strong">„Zleceniobiorcą"</span>,</p>
  </div>

  <p>a</p>

  <div class="party-box">
    <p class="name">${escapeHtml(client.name || '[Nazwa Firmy]')}</p>
    <p>${escapeHtml(addrLine || '[Adres]')}${addrLine && cityLine ? ', ' : ''}${escapeHtml(cityLine)}</p>
    <p>NIP: ${escapeHtml(client.nip || '[NIP]')}${client.regon ? ' · REGON: ' + escapeHtml(client.regon) : ''}${client.krs ? ' · KRS: ' + escapeHtml(client.krs) : ''}</p>
    <p>reprezentowaną przez: <span class="strong">${escapeHtml(contactName)}</span></p>
    <p>zwaną dalej <span class="strong">„Zleceniodawcą"</span>.</p>
  </div>
</div>

<section>
  <h2>§1 Przedmiot Umowy</h2>
  <p>1. Przedmiotem niniejszej umowy jest świadczenie przez Zleceniobiorcę usług optymalizacji kosztów pracowniczych oraz udostępnienie autorskiego systemu benefitowego <span class="strong gold">Eliton Prime™</span>.</p>
  <p>2. Zleceniobiorca zobowiązuje się do rzetelnego wykonywania powierzonych zadań, zgodnie z przyjętym harmonogramem wdrożenia i wewnętrzną dokumentacją systemu.</p>
  <p>3. Usługa realizowana jest w modelu opartym o akty prawne obowiązujące w polskim systemie prawnym nieprzerwanie od 1998 roku (Rozp. MPiPS z dnia 18.12.1998 r. §2 ust. 1 pkt 26).</p>
</section>

<section>
  <h2>§2 Oświadczenia Stron</h2>
  <p>1. Zleceniodawca oświadcza, że zatrudnia pracowników (${employees} os.) i jest uprawniony do zawarcia niniejszej umowy.</p>
  <p>2. Strony ustalają, że wdrożenie systemu obejmie ${employees} użytkowników w pierwszym etapie.</p>
  <p>3. Zleceniobiorca oświadcza, że dysponuje wiedzą, doświadczeniem oraz infrastrukturą umożliwiającymi należyte wykonanie umowy.</p>
</section>

<section>
  <h2>§3 Wynagrodzenie</h2>
  <p>1. Z tytułu realizacji umowy Zleceniodawca zapłaci Zleceniobiorcy wynagrodzenie prowizyjne (opłatę serwisową) w wysokości <span class="strong">${fee}%</span> wartości netto zamówionych świadczeń.</p>
  <p>2. Płatność nastąpi na podstawie faktury VAT w terminie 7 dni od dnia wystawienia.</p>
  <p>3. Faktury wystawiane są przez Zleceniobiorcę w systemie EBS po zakończeniu każdego miesiąca rozliczeniowego.</p>
</section>

<section>
  <h2>§4 Postanowienia Końcowe</h2>
  <p>1. Umowa zostaje zawarta na czas nieokreślony z 1-miesięcznym okresem wypowiedzenia.</p>
  <p>2. Wszelkie zmiany umowy wymagają formy pisemnej lub elektronicznej pod rygorem nieważności.</p>
  <p>3. W sprawach nieuregulowanych niniejszą umową stosuje się przepisy Kodeksu Cywilnego oraz innych obowiązujących aktów prawa polskiego.</p>
  <p>4. Umowę sporządzono w dwóch jednobrzmiących egzemplarzach po jednym dla każdej ze Stron.</p>
</section>

<div class="signatures">
  <div class="sig">
    <div class="line"></div>
    <div class="label">Zleceniodawca</div>
  </div>
  <div class="sig">
    <div class="line"></div>
    <div class="label">Zleceniobiorca</div>
  </div>
</div>

<div class="footer">
  Stratton Prime Sp. z o.o. · ul. Nowy Świat 42/44 · 80-299 Gdańsk · NIP: 5842867357 · biuro@stratton-prime.pl · www.stratton-prime.pl
</div>
</body>
</html>`
}

/**
 * Treść maila wysyłana razem z draftem umowy.
 * Format HTML — zgodny z RichTextEditor mailboxa.
 */
export function buildContractEmailBody(client: ContractClientData, agent?: AgentData): string {
  const contactName = pickContactName(client)
  const greeting = contactName ? `Szanowny/a ${escapeHtml(contactName)}` : 'Szanowni Państwo'
  const companyLabel = client.name ? escapeHtml(client.name) : 'Państwa firmy'
  const agentName = escapeHtml(agent?.name || 'Doradca Stratton Prime')
  const agentEmail = escapeHtml(agent?.email || 'biuro@stratton-prime.pl')
  const agentPhone = agent?.phone ? `<br>tel. ${escapeHtml(agent.phone)}` : ''

  return `<p>${greeting},</p>
<p>w załączeniu przesyłam <strong>draft Umowy Ramowej Współpracy</strong> przygotowany na podstawie danych ${companyLabel}.</p>

<p>Bardzo proszę o:</p>
<ul>
<li>weryfikację danych rejestrowych Państwa firmy w nagłówku umowy (nazwa, NIP, adres, osoba reprezentująca),</li>
<li>zapoznanie się z treścią umowy — wszystkie kluczowe paragrafy (przedmiot, oświadczenia, wynagrodzenie, postanowienia końcowe) zostały dopasowane do uzgodnień z naszej rozmowy,</li>
<li>odesłanie podpisanej elektronicznie wersji na adres <strong>biuro@stratton-prime.pl</strong>.</li>
</ul>

<p><strong>Eliton Prime™</strong> to autorski model wynagradzania w pełni zgodny z polskim prawem (Rozp. MPiPS z 1998 r., wyrok SN II UK 337/09, interpretacje ZUS), który pozwala obniżyć koszty zatrudnienia bez naruszania wynagrodzenia netto pracowników. Dziesiątki polskich firm już dziś korzystają z tego rozwiązania, raportując oszczędności rzędu kilkudziesięciu procent kosztów ZUS/PIT.</p>

<p><strong>Podpisanie umowy to ostatni krok</strong> przed uruchomieniem programu w Państwa organizacji — pełne wdrożenie zaczyna się w ciągu 14 dni od otrzymania podpisanego egzemplarza.</p>

<p>W razie pytań lub konieczności doprecyzowania któregokolwiek z zapisów — jestem do Państwa dyspozycji.</p>

<p>Z wyrazami szacunku,<br>
<strong>${agentName}</strong><br>
Stratton Prime Sp. z o.o.<br>
${agentEmail}${agentPhone}</p>`
}

export function buildContractFileName(client: ContractClientData): string {
  const slug = (client.name || 'klient')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '')
    .slice(0, 40) || 'klient'
  return `Umowa-Ramowa-Wspolpracy-Eliton-Prime-${slug}.pdf`
}

function escapeHtml(s: string | undefined | null): string {
  if (!s) return ''
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}
