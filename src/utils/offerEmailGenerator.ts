import type { Firma } from '@/components/calculator/models/company'

function isTruthy(val: string | undefined | null): boolean {
  if (!val) return false
  const lower = val.trim().toLowerCase()
  return lower === 'tak' || lower === 'true' || lower === 'yes' || lower === '1'
}

function isFalsy(val: string | undefined | null): boolean {
  if (!val) return false
  const lower = val.trim().toLowerCase()
  return lower === 'nie' || lower === 'false' || lower === 'no' || lower === '0'
}

function isRyczalt(val: string | undefined | null): boolean {
  if (!val) return false
  const lower = val.trim().toLowerCase()
  return lower.includes('ryczalt') || lower.includes('ryczałt')
}

function p(text: string): string {
  return `<p>${text.replace(/\n/g, '<br>')}</p>`
}

/** Pusta linia widoczna w edytorze HTML (contenteditable) */
function spacer(count = 1): string {
  return Array(count).fill('<p><br></p>').join('\n')
}

/**
 * Generuje temat i treść HTML e-maila ofertowego na podstawie danych ankiety klienta.
 */
export function generateOfferEmailBody(firma: Firma): { subject: string; body: string } {
  const branza = firma.branza || '—'
  const subject = `Analiza optymalizacji kosztów pracy – ${branza} | kolejny krok`

  // ── WSTĘP ────────────────────────────────────────────────────────────────
  const intro = [
    p('Szanowni Państwo,'),
    spacer(4),
    p(
      'Bardzo dziękuję za poświęcony czas i szczerość podczas naszej rozmowy – to właśnie takie rozmowy pozwalają nam przygotować analizę, która naprawdę ma sens i odpowiada na realne potrzeby firmy.<br>' +
      'Na podstawie przekazanych przez Państwa informacji widzę konkretne obszary, w których możemy wspólnie wypracować oszczędności. Poniżej znajdą Państwo kilka spostrzeżeń, które chciałem przekazać jeszcze przed naszym kolejnym krokiem.'
    ),
  ].join('\n')

  // ── SEKCJE WARUNKOWE ─────────────────────────────────────────────────────
  const conditionalParts: string[] = []

  // 1. FORMA OPODATKOWANIA
  if (isRyczalt(firma.ryczaltVat)) {
    conditionalParts.push(
      p(
        'Państwa firma rozlicza się ryczałtem – to forma, która ma swoje zalety, ale przy rosnącej liczbie pracowników i kosztach pracy bywa mniej korzystna, niż mogłoby się wydawać. ' +
        'Warto przyjrzeć się temu dokładniej, bo niekiedy zmiana struktury rozliczeń przynosi zaskakująco duże oszczędności – i to bez żadnych rewolucji w codziennym funkcjonowaniu firmy.'
      )
    )
  } else if (firma.ryczaltVat) {
    conditionalParts.push(
      p(
        'Rozliczenie VAT daje Państwu solidną podstawę do optymalizacji – to forma, która przy właściwym podejściu pozwala skutecznie redukować rzeczywiste obciążenia kosztowe. ' +
        'Widzę tutaj kilka konkretnych możliwości, o których chętnie opowiem podczas prezentacji.'
      )
    )
  }

  // 2. BENEFITY PRACOWNICZE
  if (isTruthy(firma.benefity)) {
    conditionalParts.push(
      p(
        'Cieszę się, że oferujecie Państwo benefity – to naprawdę ważny element w dzisiejszym rynku pracy. Jednocześnie wielu pracodawców nie zdaje sobie sprawy, że sposób, w jaki te benefity są ustrukturyzowane, ma ogromny wpływ na ich koszt dla firmy. ' +
        'Pokażemy Państwu, jak zachować ten sam poziom atrakcyjności dla pracowników, płacąc za to mniej.'
      )
    )
  } else if (isFalsy(firma.benefity)) {
    conditionalParts.push(
      p(
        'Brak benefitów to temat, który warto przemyśleć – nie dlatego, że „tak wypada", ale dlatego, że właściwie dobrane benefity mogą być w 100% kosztem firmowym i jednocześnie zwiększyć atrakcyjność pracodawcy bez podnoszenia wynagrodzeń brutto. ' +
        'To jeden z obszarów, który omówimy w prezentacji.'
      )
    )
  }

  // 3. PLANOWANE INWESTYCJE
  if (isTruthy(firma.inwestycjePlanowane)) {
    conditionalParts.push(
      p(
        'To dobra wiadomość – planowanie inwestycji to idealny moment, żeby zadbać o ich optymalną strukturę kosztową. Istnieją instrumenty, które pozwalają legalnie obniżyć koszt inwestycji nawet o kilkanaście procent. ' +
        'Warto to uwzględnić już na etapie planowania, bo po fakcie możliwości są znacznie mniejsze.'
      )
    )
  }

  // 4. SKŁADKI ZUS
  if (isTruthy(firma.zusWysokie)) {
    conditionalParts.push(
      p(
        'Rozumiem, że składki ZUS są dla Państwa realnym obciążeniem – i właśnie tutaj mamy największe doświadczenie. Nie chodzi o żadne sztuczki, ale o legalne mechanizmy, które wiele firm po prostu pomija, bo nie ma czasu ich analizować. ' +
        'Przygotujemy Państwu konkretne wyliczenia pokazujące, ile można odzyskać – bez zmian w strukturze zatrudnienia.'
      )
    )
  }

  // 5. WDRAŻANIE OSZCZĘDNOŚCI
  if (isTruthy(firma.wdrazaOszczednosci)) {
    conditionalParts.push(
      p(
        'Skoro już aktywnie pracujecie Państwo nad redukcją kosztów, to jesteście na świetnej pozycji wyjściowej. Nasza analiza często odkrywa dodatkowe obszary, które mimo najlepszych chęci umykają w codziennym zarządzaniu. ' +
        'Chętnie pokażę Państwu, co jeszcze można wycisnąć – nie jako krytykę tego, co robicie, ale jako naturalne uzupełnienie.'
      )
    )
  } else if (isFalsy(firma.wdrazaOszczednosci)) {
    conditionalParts.push(
      p(
        'Rozumiem, że do tej pory nie było ku temu okazji lub odpowiednich narzędzi. Właśnie po to jesteśmy – firmy o podobnym profilu i wielkości osiągają dzięki naszej współpracy oszczędności rzędu kilku do kilkunastu procent rocznych kosztów pracy, często już w pierwszych miesiącach. ' +
        'To realne liczby, które pokażemy w prezentacji.'
      )
    )
  }

  // 6. ZADŁUŻENIE FIRMY
  const dlugi = firma.zadluzenia?.trim().toLowerCase() ?? ''
  const isDebt = dlugi === 'yes' || dlugi === 'tak' || dlugi === 'true' || dlugi === '1'
  if (isDebt) {
    conditionalParts.push(
      p(
        'Doceniam, że podzielili się Państwo tą informacją – wiem, że to nie jest łatwy temat. Jednocześnie chcę powiedzieć wprost: to nie jest przeszkoda, a czasem wręcz dodatkowy argument za tym, żeby szybko zadziałać w obszarze kosztów pracy. ' +
        'Zmniejszenie bieżących obciążeń potrafi realnie poprawić płynność i dać firmie oddech potrzebny do ustabilizowania sytuacji.'
      )
    )
  }

  // 7. NAJWIĘKSZE WYZWANIE
  const wyzwanie = firma.wyzwanieKlienta?.trim()
  if (wyzwanie) {
    conditionalParts.push(
      p(
        `Wspomniałeś, że największym wyzwaniem jest dla Państwa w tej chwili: „${wyzwanie}". ` +
        'Temu zagadnieniu poświęcimy szczególną uwagę – zanim się spotkamy, przeanalizuję tę sytuację pod kątem dostępnych rozwiązań, żeby przyjść z konkretnymi propozycjami, a nie ogólnikami.'
      )
    )
  }

  // ── ZAKOŃCZENIE ──────────────────────────────────────────────────────────
  const footer = [
    p(
      'Uprzejmie prosimy o przesłanie listy płac (bez danych wrażliwych), uzupełnionej w załączonym pliku Excel.<br>' +
      'Dzięki temu będziemy mogli:<br>' +
      '– przygotować precyzyjne wyliczenia,<br>' +
      '– pokazać realne oszczędności dla Państwa firmy,<br>' +
      '– dopasować rozwiązanie dokładnie do Państwa sytuacji.'
    ),
    spacer(2),
    p('W załączeniu przekazujemy dokument obejmujący zgody, na których udzielenie wyrazili Państwo zgodę (kontakt telefoniczny, e-mail oraz RODO).'),
    spacer(1),
    p('W razie jakichkolwiek pytań pozostaję do dyspozycji. Szczególnie zachęcam do połączenia mnie z osobą odpowiedzialną za kadry i księgowość – to pozwoli nam działać sprawnie i bez zbędnych opóźnień.'),
    spacer(1),
    p('Jeszcze raz dziękuję za spotkanie i naprawdę liczę na dalszą współpracę.'),
    spacer(1),
    p('Z poważaniem,'),
  ].join('\n')

  // ── SKŁADANIE CAŁOŚCI ─────────────────────────────────────────────────────
  // Schemat: WSTĘP [2] [sekcje warunkowe z 1 enterem między nimi] [2] ZAKOŃCZENIE
  let body = intro

  if (conditionalParts.length > 0) {
    body += '\n' + spacer(2) + '\n'
    body += conditionalParts.join('\n' + spacer(1) + '\n')
    body += '\n' + spacer(2) + '\n'
  } else {
    body += '\n' + spacer(2) + '\n'
  }

  body += footer

  return { subject, body }
}

