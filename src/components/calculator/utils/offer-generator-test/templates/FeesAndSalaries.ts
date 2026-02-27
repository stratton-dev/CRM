import { header, footerHtml, formatPLN, splitProvision } from '../common';
import { RenderContext } from '../types';

export const FeesAndSalaries = (ctx: RenderContext) => {
  const {
    base,
    statsStandard,
    statsPlus,
    monthlyFeesAfterWages,
    isPlus,
    prowizjaProc,
    employeeCount,
    meta,
  } = ctx;

  const pageIndex = 2;

  const currentNetto = base.standard.netto;
  const currentBrutto = base.standard.brutto;
  const currentEmployerContrib = base.standard.zusPracodawca;
  const currentEmployeeContrib = base.standard.zusPracownik;
  const currentTotalCost = base.standard.kosztPracodawcy;

  const modelNetto = base.stratton.netto;
  const modelBrutto = base.stratton.brutto;
  const modelEmployerContrib = base.stratton.zusPracodawca;
  const modelEmployeeContrib = base.stratton.zusPracownik;
  const modelBaseCost = base.stratton.kosztPracodawcy;

  const totalProvision = ctx.statsSelected.prowizja;
  const standardSplit = splitProvision(statsStandard.prowizja, false);
  const plusSplit = splitProvision(statsPlus.prowizja, true);

  return `
      <div class="page">
        ${header('Wizualizacja opłat i wynagrodzeń', ctx.date, ctx.firma, ctx.advisor)}
      <div class="page-body page-pad">
            <div class="info-value small"> Niniejsza ilustracja przedstawia potencjał finansowy wynikający z wdrożenia modelu wynagradzania Eliton Prime<sup>TM</sup> 
            w Państwa firmie. KALKULACJA została przygotowana w oparciu o przekazane dane dotyczące struktury wynagrodzeń 
            oraz obowiązujące przepisy prawa pracy i podatkowego.
            </div>
         <div class="grid-two" style="margin-bottom:16px;">
            <div style="font-size:9px;">
            Model Eliton Prime<sup>TM</sup> pozwala na:
            <ul>
              <li>redukcję pozapłacowych kosztów zatrudnienia,</li>
              <li>zachowanie lub zwiększenie wynagrodzeń netto pracowników,</li>
              <li>pełną zgodność z obowiązującymi przepisami,</li>
              <li>poprawę płynności finansowej przedsiębiorstwa.</li>
            </div>
            <div class="info-card">
              <div class="info-label">ROCZNE OPŁATY ZA WSZYSTKICH PRACOWNIKÓW PO ODJĘCIU PENSJI: </div>
              <div class="info-value" style="margin-bottom:8px;">${formatPLN(monthlyFeesAfterWages * 12)}</div>
              <div class="info-label" style="margin-bottom:8px;">MIESIĘCZNE OPŁATY ZA WSZYSTKICH PRACOWNIKÓW PO ODJĘCIU PENSJI:</div>
              <div class="info-value">${formatPLN(monthlyFeesAfterWages)}</div>
            </div>
          </div>
          <div class="section-title">Wizualizacja opłat i wynagrodzeń po wdrożeniu Eliton Prime<sup>TM</sup></div>
          <table class="compare-table">
            <thead>
              <tr>
                <th>Pozycja</th>
                <th>Tak aktualnie rozlicza się Państwa firma</th>
                <th>Eliton Prime<sup>TM</sup> Standard</th>
                <th>Rekomendujemy Eliton Prime<sup>TM</sup> Plus</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>SUMA wynagrodzeń NETTO</td>
                <td>${formatPLN(currentNetto)}</td>
                <td>${formatPLN(modelNetto)}</td>
                <td>${formatPLN(modelNetto)}</td>
              </tr>
              <tr>
                <td>SUMA wynagrodzeń BRUTTO</td>
                <td>${formatPLN(currentBrutto)}</td>
                <td>${formatPLN(modelBrutto)}</td>
                <td>${formatPLN(modelBrutto)}</td>
              </tr>
              <tr>
                <td>Składki pracodawcy</td>
                <td>${formatPLN(currentEmployerContrib)}</td>
                <td>${formatPLN(modelEmployerContrib)}</td>
                <td>${formatPLN(modelEmployerContrib)}</td>
              </tr>
              <tr>
                <td>Składki pracownika (społeczna + zdrowotna)</td>
                <td>${formatPLN(currentEmployeeContrib)}</td>
                <td>${formatPLN(modelEmployeeContrib)}</td>
                <td>${formatPLN(modelEmployeeContrib)}</td>
              </tr>
              <tr class="row-total">
                <td>SUMA AKTUALNEGO KOSZTU ZATRUDNIENIA (wynagrodzenie + składki + podatek)</td>
                <td>${formatPLN(currentTotalCost)}</td>
                <td>${formatPLN(modelBaseCost)}</td>
                <td>${formatPLN(modelBaseCost)}</td>
              </tr>
              <tr>
                <td>Wdrożenie Eliton Prime<sup>TM</sup> w Państwa firmie</td>
                <td>—</td>
                <td>0 zł</td>
                <td>0 zł</td>
              </tr>
              <tr>
                <td>Oszczędność miesięczna po wdrożeniu modelu</td>
                <td>—</td>
                <td>${formatPLN(statsStandard.oszczednoscMiesieczna)}</td>
                <td>${formatPLN(statsPlus.oszczednoscMiesieczna)}</td>
              </tr>
              <tr>
                <td>Oszczędność roczna przy comiesięcznej współpracy</td>
                <td>—</td>
                <td>${formatPLN(statsStandard.oszczednoscRoczna)}</td>
                <td>${formatPLN(statsPlus.oszczednoscRoczna)}</td>
              </tr>
              <tr>
                <td>+ 4% Podwyżki dla pracowników</td>
                <td>—</td>
                <td>${formatPLN(standardSplit.raise)}</td>
                <td>${formatPLN(plusSplit.raise)}</td>
              </tr>
              <tr>
                <td>+ 2% Bonus dla działu księgowo-kadrowego</td>
                <td>—</td>
                <td>${formatPLN(standardSplit.admin)}</td>
                <td>${formatPLN(plusSplit.admin)}</td>
              </tr>
              <tr>
                <td>Opłata success fee za obsługę modelu</td>
                <td>—</td>
                <td>${formatPLN(standardSplit.fee)}</td>
                <td>${formatPLN(plusSplit.fee)}</td>
              </tr>
              <tr class="row-total">
                <td>Całkowity koszt pracodawcy (wynagrodzenie + składki + podatek + Eliton Prime<sup>TM</sup>)</td>
                <td>${formatPLN(currentTotalCost)}</td>
                <td>${formatPLN(statsStandard.totalCostModel)}</td>
                <td>${formatPLN(statsPlus.totalCostModel)}</td>
              </tr>
            </tbody>
          </table>
          <div class="info-card" style="margin-top: 12px;">
            <div class="info-value small" style="margin-top: 0;">
              <strong>Oferta Eliton Prime<sup>TM</sup> i Eliton Prime<sup>TM</sup> PLUS</strong><br/>
              Patrząc na powyższe zestawienie widzimy możliwość wygenerowania dla Państwa firmy oszczędności na poziomie
              <strong>${formatPLN(statsStandard.oszczednoscMiesieczna)}</strong> miesięcznie. Przy wyborze Eliton Prime<sup>TM</sup> Plus
              gwarantujemy podwyżki dla wszystkich pracowników na poziomie <strong>${formatPLN(plusSplit.raise)}</strong> oraz
              za wsparcie działu administracji dodatkowy bonus w wysokości <strong>${formatPLN(plusSplit.admin)}</strong>.
              Biorąc pod uwagę aktualny model rozliczania, podejmując z nami współpracę oszczędzają Państwo kapitał na inwestycję,
              podnoszą wynagrodzenia pracowników oraz otrzymają Państwo fakturę kosztową.
            </div>
          </div>
          <div class="section-title" style="margin-top: 16px;">Ważna informacja prawna</div>
          <div class="legal-box">
            Powyższe wyliczenia oparte są na obowiązujących przepisach podatkowych. W kalkulacji uwzględniamy wymagane
            składki oraz podatki i zapewniamy pełną zgodność rozliczeń z aktualnym stanem prawnym.
          </div>
          ${footerHtml(pageIndex, meta)}
        </div>
      </div>
  `;
};
