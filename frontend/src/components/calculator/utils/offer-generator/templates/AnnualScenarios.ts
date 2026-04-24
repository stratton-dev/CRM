import { header, footerHtml, formatPLN } from '../common';
import { RenderContext } from '../types';

export const AnnualScenarios = (ctx: RenderContext) => {
  const { annualScenarios, statsStandard, statsPlus, meta } = ctx;
  const pageIndex = 3;

  return `
      <div class="page">
        ${header('Scenariusze rocznych oszczędności', ctx.date, ctx.firma, ctx.advisor)}
      <div class="page-body page-pad">
          <div class="info-card" style="margin-bottom: 12px;">
            <div class="info-label">Oferta Eliton Prime<sup>TM</sup></div>
            <div class="info-value small" style="margin-top: 6px;">
              Wysokość prezentowanych oszczędności oraz efektów finansowych wynikających z wdrożenia modelu Eliton Prime<sup>TM</sup> ma charakter orientacyjny i została obliczona na podstawie danych przekazanych przez Klienta, obowiązujących przepisów
              prawa oraz założeń przyjętych na dzień sporządzenia niniejszej ilustracji.<br/>
              W przypadku zmiany parametrów wejściowych, w szczególności: struktury zatrudnienia, rodzaju umów, wysokości
              wynagrodzeń, liczby pracowników objętych modelem, przepisów prawa pracy, podatkowego lub ubezpieczeniowego,
              wartości prezentowanych oszczędności mogą ulec zmianie.<br/>
              Całkowity koszt zatrudnienia po wdrożeniu modelu Eliton Prime<sup>TM</sup> nie będzie wyższy niż koszt zatrudnienia w&nbspaktualnym systemie, przy zachowaniu zgodności z&nbsp§2 ust. 1 pkt 26 Rozporządzenia MPiPS oraz obowiązkiem wykazania przychodu w PIT-11 z zaliczką 12% podatku dochodowego.<br/>
              Analiza została przygotowana przy założeniu: zachowania obecnych wynagrodzeń netto pracowników, pełnej zgodności
              wdrożenia z dokumentacją opracowaną przez Stratton Prime, standardowego profilu ryzyka podatkowego i&nbsp
              ubezpieczeniowego, wdrożenia modelu zgodnie z rekomendacjami doradczymi.
            </div>
          </div>
          <div class="section-title">Scenariusze roczne</div>
          <table class="fin-table">
            <thead>
              <tr>
                <th>Wariant</th>
                <th>Oszczędność roczna</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Aktualne rozliczenie</td>
                <td>0 zł</td>
              </tr>
              <tr>
                <td>Eliton Prime<sup>TM</sup> Standard</td>
                <td>${formatPLN(statsStandard.oszczednoscRoczna)}</td>
              </tr>
              <tr>
                <td>Eliton Prime<sup>TM</sup> Plus</td>
                <td>${formatPLN(statsPlus.oszczednoscRoczna)}</td>
              </tr>
            </tbody>
          </table>
          <div class="section-title" style="margin-top: 16px;">Przewidywane wartości skumulowanych oszczędności rocznych</div>
          <div class="chart-wrap">
            <canvas id="yearByYearChart"></canvas>
          </div>
          <div class="section-title" style="margin-top: 16px;">Prognoza wieloletnia</div>
          <table class="fin-table">
            <thead>
              <tr>
                <th>Horyzont</th>
                <th>Oszczędność łączna</th>
              </tr>
            </thead>
            <tbody>
              ${annualScenarios.map((row) => `
                <tr>
                  <td>${row.years} ${row.years === 1 ? 'rok' : row.years < 5 ? 'lata' : 'lat'}</td>
                  <td>${formatPLN(row.total)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
          <div class="section-title" style="margin-top: 16px;">Ważna informacja prawna</div>
          <div class="legal-box">
            Oszczędności obliczone są w oparciu o aktualne stawki. Faktury za usługę podlegają standardowym zasadom
            księgowym i mogą być rozliczane zgodnie z przepisami podatkowymi.
          </div>
          ${footerHtml(pageIndex, meta)}
        </div>
      </div>
  `;
};
