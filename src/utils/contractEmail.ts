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
 *
 * Treść 1:1 z oficjalnego dokumentu „Umowa_Ramowa_Wspolpracy_ElitonPrime_v2
 * _rev KZS.pdf" (wersja marzec 2026), z podstawionymi danymi klienta:
 *   - nazwa, adres siedziby, KRS, REGON, NIP, osoba reprezentująca
 *   - email do otrzymywania faktur (kontakt)
 *   - stawka opłaty serwisowej (% z karty klienta lub default 22)
 */
export function buildContractHtml(client: ContractClientData): string {
  const today = new Date()
  const year = today.getFullYear()
  const dateStr = fmtDate(today)
  const fee = client.serviceFeePercent != null ? `${client.serviceFeePercent}` : '22'
  const contactName = pickContactName(client) || '………………………………………………………'
  const contactEmail = pickContactEmail(client) || '………………………………………'

  const addrLine = [
    client.street,
    client.buildingNr,
    client.localeNr,
  ].filter(Boolean).join(' ').trim()
  const cityLine = [client.zip, client.city].filter(Boolean).join(' ').trim()
  const fullAddr = [addrLine, cityLine].filter(Boolean).join(', ').trim()

  return `<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<title>Umowa Ramowa Współpracy — ${escapeHtml(client.name || 'Klient')}</title>
<style>
  @page { size: A4; margin: 0; }
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body {
    font-family: 'Calibri', 'Segoe UI', 'Arial', sans-serif;
    color: #1e293b;
    font-size: 10.5pt;
    line-height: 1.45;
    /* explicit padding = wizualne marginesy zachowywane przy html2canvas
       (które ignoruje @page margin). Top 18mm / boki 16mm / dół 24mm. */
    padding: 68px 60px 90px 60px;
  }

  /* Page header (every page) */
  .page-header {
    border-bottom: 2px solid #1e3a5f;
    padding-bottom: 6px;
    margin-bottom: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 8pt;
    font-weight: 700;
    color: #1e3a5f;
  }
  .page-header .right { font-weight: 400; color: #475569; }

  /* Title block */
  h1.doc-title {
    font-size: 32pt;
    font-weight: 900;
    text-align: center;
    color: #1e293b;
    margin: 20px 0 6px;
    letter-spacing: -0.5px;
  }
  .doc-subtitle {
    text-align: center;
    font-size: 11pt;
    font-style: italic;
    color: #1e3a5f;
    margin: 0 0 4px;
  }
  .doc-note {
    text-align: center;
    font-size: 10pt;
    color: #64748b;
    margin: 0 0 22px;
  }

  /* Parties block */
  .parties { margin-bottom: 18px; }
  .parties .intro { margin: 8px 0; }
  .party-block {
    margin: 10px 0;
    padding: 6px 0;
  }
  .party-block strong { font-weight: 700; }
  .a-sep { text-align: center; font-style: italic; margin: 8px 0; }

  /* Sections */
  section { margin-top: 18px; page-break-inside: avoid; }
  section h2 {
    font-size: 13pt;
    font-weight: 800;
    color: #1e293b;
    border-bottom: 1.5px solid #1e3a5f;
    padding-bottom: 4px;
    margin: 0 0 10px;
  }
  section p { margin: 6px 0; text-align: justify; }
  section ol, section ul { margin: 6px 0 6px 20px; padding: 0; }
  section li { margin: 4px 0; text-align: justify; }
  .num { font-weight: 700; }
  .iban { font-weight: 800; text-align: center; font-size: 12pt; margin: 8px 0; letter-spacing: 1px; }
  .highlight-box {
    background: #f1f5f9;
    border-left: 3px solid #1e3a5f;
    padding: 10px 14px;
    margin: 12px 0;
    font-style: italic;
    color: #1e293b;
  }

  /* Signatures table */
  .sig-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 28px;
  }
  .sig-table th, .sig-table td {
    border: 1px solid #1e293b;
    padding: 16px 12px;
    text-align: center;
    vertical-align: middle;
  }
  .sig-table th {
    background: #f1f5f9;
    font-size: 11pt;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  .sig-table td.sig-cell { height: 56px; font-style: italic; color: #475569; }
  .sig-line { border-bottom: 1px solid #1e293b; width: 80%; margin: 0 auto 6px; height: 32px; }

  /* Footer */
  .doc-footer {
    margin-top: 30px;
    padding-top: 8px;
    border-top: 1px solid #e2e8f0;
    text-align: center;
    font-size: 8.5pt;
    color: #94a3b8;
  }

  .strong { font-weight: 700; }
  .blank { color: #94a3b8; }
  .field { display: inline-block; min-width: 180px; border-bottom: 1px dotted #94a3b8; padding: 0 4px; }
</style>
</head>
<body>

<div class="page-header">
  <span>ELITON PRIME™ | UMOWA RAMOWA WSPÓŁPRACY</span>
  <span class="right">Stratton Prime Sp. z o.o. | stratton-prime.pl</span>
</div>

<h1 class="doc-title">UMOWA RAMOWA WSPÓŁPRACY</h1>
<p class="doc-subtitle">Eliton Prime™ / Eliton Benefits System (EBS)</p>
<p class="doc-note">WZÓR – do uzupełnienia przed podpisaniem</p>

<div class="parties">
  <p class="intro">Umowa zawarta w dniu <span class="strong">${dateStr}</span> w Warszawie, pomiędzy:</p>

  <div class="party-block">
    <p><strong>Stratton Prime Sp. z o.o.</strong> z siedzibą przy ul. Junony 23/11, 80-299 Gdańsk, wpisaną do rejestru przedsiębiorców Krajowego Rejestru Sądowego prowadzonego przez Sąd Rejestrowy dla Gdańska-Południe w Gdańsku, XIII Wydział Gospodarczy Krajowego Rejestru Sądowego pod numerem KRS: 0001169520, NIP: 5842867357, REGON: 541537557, reprezentowaną przez: <strong>Natalię Juszkiewicz – Prezesa Zarządu</strong>, zwaną dalej <strong>„Usługodawcą"</strong> oraz <strong>„Sprzedawcą"</strong></p>
  </div>

  <p class="a-sep">a</p>

  <div class="party-block">
    <p><strong>${escapeHtml(client.name || '____________________________________')}</strong> z siedzibą w ${escapeHtml(client.city || '________________________')}, adres: ${escapeHtml(fullAddr || '_________________________')}, wpisaną do rejestru KRS pod numerem ${escapeHtml(client.krs || '____________________')}, REGON: ${escapeHtml(client.regon || '_________________')}, NIP: ${escapeHtml(client.nip || '_____________________')}, reprezentowaną przez: <strong>${escapeHtml(contactName)}</strong> zwaną dalej <strong>„Klientem"</strong> oraz <strong>„Kupującym"</strong></p>
  </div>

  <p>o następującej treści:</p>
</div>

<section>
  <h2>§ 1 Przedmiot Umowy</h2>
  <p><span class="num">1.</span> Przedmiotem umowy jest stała współpraca polegająca na zakupie oraz sprzedaży voucherów cyfrowych (znaków legitymacyjnych) uprawniających do korzystania z usług i towarów dostępnych w zamkniętym katalogu na platformie <strong>Eliton Benefits System (EBS)</strong>, prowadzonej przez Sprzedawcę.</p>
  <p><span class="num">2.</span> Vouchery dystrybuowane w ramach niniejszej umowy stanowią znaki legitymacyjne w rozumieniu art. 921¹⁵ Kodeksu cywilnego oraz spełniają warunki zwolnienia z podstawy wymiaru składek ZUS na podstawie §2 ust. 1 pkt 26 rozporządzenia MPiPS z dnia 18 grudnia 1998 r. (Dz.U.1998.161.1106).</p>
  <p><span class="num">3.</span> Wartość transakcji voucherów będących przedmiotem umowy będzie każdorazowo ustalana na podstawie Załącznika nr 1 (umowa zlecenia nabycia voucherów).</p>
</section>

<section>
  <h2>§ 2 Wykonywanie zobowiązania</h2>
  <p><span class="num">1.</span> Sprzedawca zobowiązuje się do przeprowadzenia bezpłatnego szkolenia dla działu księgowego oraz działu kadrowo-płacowego Kupującego, obejmującego:</p>
  <ol type="a" style="margin-left: 24px;">
    <li>zasady funkcjonowania voucherów i platformy EBS,</li>
    <li>sposób ich przydzielania pracownikom/zleceniobiorcom,</li>
    <li>podstawowe kwestie podatkowe i prawne (ZUS, PIT, VAT), mające charakter wyłącznie informacyjny i niewiążący,</li>
    <li>obsługę platformy benefitowej EBS i raportowanie wewnętrzne.</li>
  </ol>
  <p><span class="num">2.</span> Szkolenie zostanie przeprowadzone w formie zdalnej lub stacjonarnej, według ustaleń stron i w terminie przez nie uzgodnionym. Szkolenie odbędzie się po zawarciu niniejszej umowy.</p>
  <p><span class="num">3.</span> Strony zgodnie ustalają, że od momentu założenia kont indywidualnych na platformie EBS przez dedykowanego opiekuna, wszelkie wsparcie techniczne w zakresie jego obsługi zapewnia Pomoc Techniczna. Wszelkie pytania, problemy techniczne oraz zgłoszenia związane z funkcjonowaniem portfela należy kierować do:</p>
  <p style="margin-left: 24px;"><strong>Pomoc Techniczna:</strong> bok@stratton-prime.pl</p>
  <p><span class="num">4.</span> Wszelkie procedury związane z ewentualną rezygnacją uczestnika z programu EBS prowadzone są wyłącznie przez operatora platformy EBS bezpośrednio z uczestnikiem, na warunkach Regulaminu EBS. Kupujący (pracodawca/zleceniodawca) nie jest stroną tych procedur i nie uczestniczy w żadnych rozliczeniach związanych z rezygnacją.</p>
  <p><span class="num">5.</span> Sprzedawca oświadcza, że nie świadczy usług doradztwa podatkowego ani prawnego. Kupujący ponosi wyłączną odpowiedzialność za sposób rozliczenia voucherów zgodnie z obowiązującymi przepisami prawa.</p>
</section>

<section>
  <h2>§ 3 Płatność</h2>
  <p><span class="num">1.</span> Rozliczenie każdego zamówienia realizowanego na podstawie Załącznika nr 1 następuje na podstawie dwóch odrębnych dokumentów księgowych:</p>
  <ol type="a" style="margin-left: 24px;">
    <li><strong>noty księgowej</strong> — wystawianej przez Sprzedawcę na wartość nabytych voucherów, stanowiącej iloczyn liczby voucherów i ich wartości jednostkowej (1 voucher = 1 PLN). Nota księgowa nie zawiera podatku VAT, gdyż voucher stanowi bon wieloprzeznaczeniowy (MPV) w rozumieniu art. 8b ustawy z dnia 11 marca 2004 r. o podatku od towarów i usług — obowiązek podatkowy w zakresie VAT powstaje wyłącznie w momencie realizacji vouchera przez uczestnika u dostawcy usługi;</li>
    <li><strong>faktury VAT</strong> — wystawianej przez Sprzedawcę za usługę obsługi i serwisu programu EBS, w wysokości <strong>${fee}%</strong> wartości netto voucherów wskazanej w nocie księgowej, powiększonej o podatek VAT według stawki właściwej dla tej usługi.</li>
  </ol>
  <p><span class="num">2.</span> Sprzedawca zobowiązuje się do wystawienia i przesłania obu dokumentów jednocześnie, drogą elektroniczną, na adres e-mail Kupującego:</p>
  <p style="margin-left: 24px;"><strong>e-mail Sprzedawcy:</strong> faktury@stratton-prime.pl</p>
  <p style="margin-left: 24px;"><strong>e-mail Kupującego:</strong> ${escapeHtml(contactEmail)}</p>
  <p><span class="num">3.</span> Kupujący dokonuje płatności za oba dokumenty łącznie, przelewem na konto Sprzedawcy w Millennium Bank:</p>
  <p class="iban">IBAN PL 66 1160 2202 0000 0006 6619 4064</p>
  <p>w terminie <strong>7 dni</strong> od daty otrzymania przez Kupującego obu dokumentów.</p>
  <p><span class="num">4.</span> Wynagrodzenie Sprzedawcy obejmuje wszystkie koszty realizacji umowy leżące po jego stronie.</p>
  <p><span class="num">5.</span> W przypadku opóźnienia w płatności Sprzedawca ma prawo:</p>
  <ol type="a" style="margin-left: 24px;">
    <li>naliczyć odsetki ustawowe za opóźnienie w transakcjach handlowych,</li>
    <li>wstrzymać realizację kolejnych zamówień,</li>
    <li>czasowo zablokować dostęp do platformy EBS.</li>
  </ol>
</section>

<section>
  <h2>§ 4 Brak ryzyka – cena zakupu voucherów</h2>
  <p><span class="num">1.</span> Strony zgodnie oświadczają, że cena zakupu voucherów wynosi 1 voucher = 1 PLN w dniu podpisania umowy zlecenia nabycia voucherów (Załącznik nr 1) i pozostanie niezmieniona przez minimum 12 miesięcy od daty zakupu.</p>
  <p><span class="num">2.</span> Jednoczesne podpisanie Zlecenia zakupu stanowi gwarancję, że Kupujący nie ponosi żadnego ryzyka związanego z wartością vouchera. Transakcja ma charakter zamknięty, a jej warunki są z góry określone i niezmienne niezależnie od późniejszych zmian cenowych.</p>
  <p><span class="num">3.</span> Kupujący potwierdza, że rozumie powyższe warunki i ma świadomość, iż nie ponosi ryzyka utraty wartości nabytych voucherów.</p>
</section>

<section>
  <h2>§ 5 Odpowiedzialność</h2>
  <p><span class="num">1.</span> Odpowiedzialność Sprzedawcy wobec Kupującego z tytułu niewykonania lub nienależytego wykonania Umowy ograniczona jest do łącznej wysokości wynagrodzenia netto zapłaconego przez Kupującego na rzecz Sprzedawcy w okresie 3 miesięcy poprzedzających zdarzenie powodujące szkodę.</p>
  <p><span class="num">2.</span> Sprzedawca nie ponosi odpowiedzialności za:</p>
  <ol type="a" style="margin-left: 24px;">
    <li>utracone korzyści <em>(lucrum cessans)</em>,</li>
    <li>szkody pośrednie, następcze lub wynikowe,</li>
    <li>decyzje podatkowe, księgowe lub kadrowe Kupującego,</li>
    <li>działania lub zaniechania uczestników programu,</li>
    <li>brak możliwości realizacji voucherów wynikający z przyczyn leżących po stronie dostawców usług lub towarów dostępnych w katalogu EBS.</li>
  </ol>
  <p><span class="num">3.</span> Przerwy techniczne, aktualizacje oraz awarie nie stanowią niewykonania umowy.</p>
  <p><span class="num">4.</span> Sprzedawca nie ponosi odpowiedzialności za:</p>
  <ol type="a" style="margin-left: 24px;">
    <li>przerwy wynikające z działania siły wyższej,</li>
    <li>problemy po stronie użytkownika,</li>
    <li>działanie dostawców zewnętrznych.</li>
  </ol>
</section>

<section>
  <h2>§ 6 Zawiadomienia</h2>
  <p><span class="num">1.</span> Wszystkie zawiadomienia i inne informacje wymagane przez niniejszą Umowę będą pisemne i będą uważane za właściwie doręczone jeżeli:</p>
  <ol type="a" style="margin-left: 24px;">
    <li>przesłane pocztą kurierską (za zwrotnym potwierdzeniem odbioru),</li>
    <li>przekazane pocztą elektroniczną zgodnie z adresami:</li>
  </ol>
  <p style="margin-left: 48px;"><strong>Sprzedawca:</strong> biuro@stratton-prime.pl</p>
  <p style="margin-left: 48px;"><strong>Klient:</strong> ${escapeHtml(contactEmail)}</p>
</section>

<section>
  <h2>§ 7 Oświadczenia i gwarancje</h2>
  <p><span class="num">1.</span> Klient przeczytał, rozumie i akceptuje postanowienia niniejszej umowy w całości, bez jakichkolwiek zastrzeżeń i uzupełnień.</p>
  <p><span class="num">2.</span> Klient otrzymał wystarczającą ilość informacji o voucherach i platformie EBS, aby podjąć świadomą decyzję o ich zakupie.</p>
  <p><span class="num">3.</span> Klient przyjmuje do wiadomości i akceptuje, że voucher nie jest:</p>
  <ol type="a" style="margin-left: 24px;">
    <li>detalicznym produktem zbiorowego inwestowania w rozumieniu Rozporządzenia PRIIP (UE) nr 1286/2014,</li>
    <li>jednostką uczestnictwa ani certyfikatem inwestycyjnym w rozumieniu ustawy z 27.05.2004 r. o funduszach inwestycyjnych,</li>
    <li>dokumentem osobistym, na żądanie lub wydawanym na okaziciela w rozumieniu art. 174 Kodeksu Spółek Handlowych,</li>
    <li>instrumentem finansowym w rozumieniu art. 2 pkt 1 ustawy z 29.07.2005 r. o obrocie instrumentami finansowymi.</li>
  </ol>
  <p><span class="num">4.</span> Klient przyjmuje do wiadomości i akceptuje, że proces dystrybucji vouchera nie stanowi: działalności w zakresie zarządzania funduszami inwestycyjnymi, oferty publicznej, usług płatniczych, działalności bankowej, działalności ubezpieczeniowej ani żadnej innej działalności regulowanej lub koncesjonowanej, w rozumieniu właściwych przepisów prawa polskiego i unijnego.</p>
  <p><span class="num">5.</span> Strony zgodnie oświadczają, że voucher jest znakiem legitymacyjnym zgodnie z art. 921¹⁵ Kodeksu cywilnego, który nie spełnia przesłanek określonych w Rozporządzeniu Parlamentu Europejskiego i Rady (UE) 2023/1114 (MiCA), a w szczególności nie jest walutą wirtualną w rozumieniu art. 2 ust. 2 pkt 26 ustawy o przeciwdziałaniu praniu pieniędzy oraz finansowaniu terroryzmu (ustawa AML).</p>
  <div class="highlight-box">
    Voucher jest przypisany imiennie do uczestnika programu; nie może być przekazany osobie trzeciej ani wymieniony na środki płatnicze u pracodawcy. Architektura platformy EBS technicznie wyklucza cesję, sprzedaż i wymianę vouchera na gotówkę u pracodawcy – co stanowi warunek kwalifikacji AML/KNF oraz ZUS.
  </div>
</section>

<section>
  <h2>§ 8 Obowiązywanie Umowy</h2>
  <p><span class="num">1.</span> Niniejsza Umowa wchodzi w życie z dniem jej podpisania.</p>
  <p><span class="num">2.</span> Umowa niniejsza została zawarta na czas nieokreślony.</p>
  <p><span class="num">3.</span> Umowa może zostać wypowiedziana przez każdą ze Stron z zachowaniem 1-miesięcznego okresu wypowiedzenia.</p>
  <p><span class="num">4.</span> Stronom przysługuje prawo do odstąpienia od Umowy ze skutkiem natychmiastowym w przypadku naruszenia postanowień niniejszej Umowy przez Strony, w okresie 7 dni od uzyskania informacji o takim naruszeniu.</p>
</section>

<section>
  <h2>§ 9 Postanowienia końcowe</h2>
  <p><span class="num">1.</span> Wszelkie zmiany niniejszej Umowy wymagają dla swej skuteczności formy pisemnej. W sprawach nieuregulowanych niniejszą Umową zastosowanie znajdą przepisy prawa polskiego, w szczególności ustawy z 23.04.1964 r. – Kodeks cywilny (t.j. Dz. U. z ${year} r. poz. 1071 z późn. zm.).</p>
  <p><span class="num">2.</span> Wszelkie spory wynikłe pomiędzy Stronami na skutek zawarcia niniejszej Umowy rozstrzygane będą przez sąd powszechny właściwy ze względu na siedzibę Sprzedawcy.</p>
  <p><span class="num">3.</span> Umowa niniejsza została sporządzona w dwóch jednobrzmiących egzemplarzach, po jednym dla każdej ze Stron.</p>
  <p><span class="num">4.</span> W razie sprzeczności między postanowieniami niniejszej Umowy Ramowej a postanowieniami Załącznika nr 1, rozstrzygające znaczenie mają postanowienia niniejszej Umowy Ramowej, chyba że Załącznik nr 1 wyraźnie stanowi inaczej.</p>
</section>

<table class="sig-table">
  <thead>
    <tr>
      <th>SPRZEDAWCA</th>
      <th>KUPUJĄCY</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="sig-cell">
        <div style="height: 32px;"></div>
        <strong>Stratton Prime Sp. z o.o.</strong><br>
        <em>Natalia Juszkiewicz – Prezes Zarządu</em>
      </td>
      <td class="sig-cell">
        <div class="sig-line"></div>
        <em>imię, nazwisko, stanowisko</em>
      </td>
    </tr>
  </tbody>
</table>

<div class="doc-footer">
  Stratton Prime Sp. z o.o. • stratton-prime.pl • Eliton Prime™ • Marzec 2026
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
