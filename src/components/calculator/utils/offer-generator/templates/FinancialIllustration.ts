import { header, footerHtml, formatPLN, safeText } from '../common';
import { RenderContext } from '../types';

export const FinancialIllustration = (ctx: RenderContext) => {
  const {
    meta,
    statsSelected,
    employeeCount,
    isPlus,
    contracts,
    monthlyFeesAfterWages,
    annualFeesAfterWages,
    threeYearEffect,
    avgPerEmployee,
  } = ctx;

  const pageIndex = 1;

  return `
      <div class="page">
        ${header('Ilustracja finansowa oszczędności', ctx.date, ctx.firma, ctx.advisor)}
        <div class="page-body page-pad">
          <div class="grid-two">
            <div>
              <div class="section-title">Kalkulacja przewidywalnych oszczędności Eliton Prime<sup>TM</sup></div>
              <div class="info-card" style="margin-bottom: 12px;">
                <div class="info-label">Numer kalkulacji</div>
                <div class="info-value">${ctx.offerNumber}</div>
                <div class="info-label" style="margin-top: 8px;">Data opracowania:</div>
                <div class="info-value">${ctx.date}</div>
                <div class="info-label" style="margin-top: 8px;">Osoba reprezentująca firmę podczas spotkania</div>
                <div class="info-value">${safeText(ctx.firma.osobaKontaktowa)}</div>
                <div class="info-value small" style="margin-top: 8px;">Kalkulacje wykonane za zgodą Firmy: ${safeText(ctx.firma.nazwa)} na podstawie danych podanych na spotkaniu.</div>
              </div>
              <div class="section-title">Oferta Eliton Prime<sup>TM</sup></div>
              <div class="grid-two">
                <div class="kpi-card highlight">
                  <div class="kpi-label">Prognozowana oszczędność roczna</div>
                  <div class="kpi-val">${formatPLN(statsSelected.oszczednoscRoczna)}</div>
                  <div class="kpi-sub">Netto po prowizji</div>
                </div>
                <div class="kpi-card">
                  <div class="kpi-label">Prognozowana oszczędność miesięczna</div>
                  <div class="kpi-val">${formatPLN(statsSelected.oszczednoscMiesieczna)}</div>
                  <div class="kpi-sub">Netto po prowizji</div>
                </div>
                <div class="kpi-card">
                <div class="kpi-label">Średnia oszczędność generowana z jednego pracownika</div>
                <div class="kpi-val">${formatPLN(avgPerEmployee)}</div>
                <div class="kpi-sub">Miesięcznie</div>
              </div>
              <div class="kpi-card">
                <div class="kpi-label">Efekt oszczędności 3-letni</div>
                <div class="kpi-val">${formatPLN(threeYearEffect)}</div>
                <div class="kpi-sub">Prognoza 36 mies.</div>
              </div>
              </div>
              <div style="margin-top: 10px; display: flex; gap: 10px;">
                <span class="badge-success">${employeeCount} pracowników objętych programem</span>
                <span class="badge-blue">Model ${isPlus ? 'WIN-WIN' : 'STANDARD'}</span>
              </div>
              <div class="info-value small">
              Oferta Eliton Prime<sup>TM</sup> i Eliton Prime<sup>TM</sup> PLUS
              <ul>
                <li> dane do kalkulacji zakładają wysokości wynagrodzeń przeszłych pokazując możliwości przyszłych o szczędności.</li>
                <li> umowa główna jest	umową otwartą opartą na comiesięcznych nowych kalkulacjach adekwatnych do wysokości prognozowanych wypłat.</li>
                <li> opcja PLUS gwarantuje podwyżki w wysokości +5% wynagrodzenia netto dla każdego pracownika korzystającego z modelu Eliton Prime<sup>TM</sup> FINANSOWANE PRZEZ STRATTONPRIME.</li>
              </div>
           </div>
            <div>
              <div class="section-title">Aktualne opłaty związane z zatrudieniem pracowników w Państwa firmie.</div>
              <table class="fin-table">
                <thead>
                  <tr>
                    <th>Rodzaj umowy</th>
                    <th>Liczba</th>
                    <th>Koszt miesięczny</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>UOP</td>
                    <td>${contracts.UOP.count}</td>
                    <td>${formatPLN(contracts.UOP.koszt)}</td>
                  </tr>
                  <tr>
                    <td>UZ</td>
                    <td>${contracts.UZ.count}</td>
                    <td>${formatPLN(contracts.UZ.koszt)}</td>
                  </tr>
                  <tr class="row-total">
                    <td colspan="2">Suma</td>
                    <td>${formatPLN(statsSelected.totalCostStandard)}</td>
                  </tr>
                  <tr>
                    <td colspan="2">Miesięczne opłaty za wszystkich pracowników po odjęciu wynagrodzeń.</td>
                    <td>${formatPLN(monthlyFeesAfterWages)}</td>
                  </tr>
                  <tr>
                    <td colspan="2">Roczne opłaty za wszystkich pracowników po odjęciu wynagrodzeń.</td>
                    <td>${formatPLN(annualFeesAfterWages)}</td>
                  </tr>
                </tbody>
              </table>
              <div class="section-title" style="margin-top: 16px;">Informacje o Państwa firmie</div>
              <div class="grid-two">
                <div class="info-card">
                  <div class="info-label">Branża</div>
                  <div class="info-value">${safeText(ctx.firma.branza)}</div>
                </div>
                <div class="info-card">
                  <div class="info-label">Benefity</div>
                  <div class="info-value">${safeText(ctx.firma.benefity)}</div>
                </div>
                <div class="info-card">
                  <div class="info-label">Udział w projekcie</div>
                  <div class="info-value">${safeText(ctx.firma.udzialWProjekcie)}</div>
                </div>
                <div class="info-card">
                  <div class="info-label">Przeszłe oszczędności</div>
                  <div class="info-value">${safeText(ctx.firma.oszczednosciPrzeszle)}</div>
                </div>
                <div class="info-card">
                  <div class="info-label">Aktualne oszczędności</div>
                  <div class="info-value">${safeText(ctx.firma.oszczednosciAktualne)}</div>
                </div>
                <div class="info-card">
                  <div class="info-label">Planowane inwestycje</div>
                  <div class="info-value">${safeText(ctx.firma.inwestycjePlanowane)}</div>
                </div>
                <div class="info-card">
                  <div class="info-label">Deklarowana kwota oszczędności</div>
                  <div class="info-value">${safeText(ctx.firma.kwotaOszczednosciDeklarowana)}</div>
                </div>
                <div class="info-card">
                  <div class="info-label">Zadłużenia</div>
                  <div class="info-value">${safeText(ctx.firma.zadluzenia)}</div>
                </div>
                <div class="info-card">
                  <div class="info-label">Ryczałt / VAT</div>
                  <div class="info-value">${safeText(ctx.firma.ryczaltVat)}</div>
                </div>
              </div>
            </div>
          </div>
          ${footerHtml(pageIndex, meta)}
        </div>
      </div>
  `;
};
