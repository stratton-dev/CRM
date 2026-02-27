import { header, footerHtml, formatPLN } from '../common';
import { RenderContext } from '../types';

export const PayrollTable = (ctx: RenderContext) => {
  const { payrollRows, meta } = ctx;
  const pageIndex = 5;

  return `
      <div class="page">
        ${header('Tabela listy płac (10 pracowników)', ctx.date, ctx.firma, ctx.advisor)}
        <div class="page-body page-pad">
          <div class="muted" style="margin-bottom: 10px;">
            Tabela listy płac dla 10 pracowników w modelu Eliton
          </div>
          <table class="small-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Pracownik</th>
                <th>UOP/UZ</th>
                <th>Brutto standard</th>
                <th>Netto standard</th>
                <th>Netto Eliton</th>
                <th>Oszczędność (mies.)</th>
              </tr>
            </thead>
            <tbody>
              ${payrollRows.map((row) => `
                <tr>
                  <td>${row.index}</td>
                  <td>${row.name || `Pracownik ${row.index}`}</td>
                  <td>${row.contract}</td>
                  <td>${formatPLN(row.standardBrutto)}</td>
                  <td>${formatPLN(row.standardNetto)}</td>
                  <td>${formatPLN(row.modelNetto)}</td>
                  <td>${formatPLN(row.oszczednosc)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
          ${footerHtml(pageIndex, meta)}
        </div>
      </div>
  `;
};
