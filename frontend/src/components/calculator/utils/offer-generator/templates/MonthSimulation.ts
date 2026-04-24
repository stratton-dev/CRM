import { header, footerHtml, formatPLN } from '../common';
import { RenderContext } from '../types';

export const MonthSimulation = (ctx: RenderContext) => {
  const { monthSimulation, base, statsStandard, statsPlus, meta } = ctx;
  const pageIndex = 4;

  const currentTotalCost = base.standard.kosztPracodawcy;

  return `
      <div class="page">
        ${header('Symulacja miesiąc do miesiąca', ctx.date, ctx.firma, ctx.advisor)}
        <div class="page-body page-pad">
          <div class="muted" style="margin-bottom: 10px;">
            Opłaty na przestrzeni 10 lat
          </div>
          <div class="chart-wrap" style="margin-bottom: 12px;">
            <canvas id="tenYearComparisonMonth"></canvas>
          </div>
          <div class="muted" style="margin-bottom: 10px;">
            Symulacja miesiąc do miesiąca
          </div>
          <table class="fin-table">
            <thead>
              <tr>
                <th>Miesiąc</th>
                <th>Koszt bez modelu narastająco</th>
                <th>Koszt Eliton Prime<sup>TM</sup> narastająco</th>
                <th>Koszt Eliton Prime<sup>TM</sup> Plus narastająco</th>
                <th>Oszczędność Eliton Prime<sup>TM</sup> narastająco</th>
                <th>Oszczędność Eliton Prime<sup>TM</sup> Plus narastająco</th>
              </tr>
            </thead>
            <tbody>
              ${monthSimulation.map((row) => `
                <tr>
                  <td>${row.name}</td>
                  <td>${formatPLN(currentTotalCost * row.monthIndex)}</td>
                  <td>${formatPLN(statsStandard.totalCostModel * row.monthIndex)}</td>
                  <td>${formatPLN(statsPlus.totalCostModel * row.monthIndex)}</td>
                  <td>${formatPLN(statsStandard.oszczednoscMiesieczna * row.monthIndex)}</td>
                  <td>${formatPLN(statsPlus.oszczednoscMiesieczna * row.monthIndex)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
          ${footerHtml(pageIndex, meta)}
        </div>
      </div>
  `;
};
