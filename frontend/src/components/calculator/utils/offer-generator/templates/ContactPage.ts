import { header, footerHtml } from '../common';
import { RenderContext } from '../types';

export const ContactPage = (ctx: RenderContext) => {
  const { advisor, offerNumber, date, meta } = ctx;
  const pageIndex = 8;
  // TODO: Fix advisor info access if needed. in header() ctx.advisor is used.
  // The original used `advisorName`, `advisorEmail`, `advisorPhone` safeText'd.
  // In `RenderContext`, `advisor` has { name, email, phone }.

  return `
      <div class="page">
        ${header('Firmy podobne i kontakt', date, ctx.firma, advisor)}
        <div class="page-body page-pad">
          <div class="section-title">Firmy podobne i osiągnięcia</div>
          <div class="muted" style="margin-bottom: 18px;">
            Referencje z branż produkcyjnych, usługowych i logistycznych dostępne są na życzenie. Wyniki wdrożeń
            obejmują średnie oszczędności rzędu 12–18% w skali roku.
          </div>
          <div class="section-title">Kontakt</div>
          <div class="grid-two">
            <div class="info-card">
              <div class="info-label">Opiekun oferty</div>
              <div class="info-value">${advisor.name || '-'}</div>
              <div class="info-value">${advisor.email || '-'}</div>
              <div class="info-value">${advisor.phone || '-'}</div>
            </div>
            <div class="info-card">
              <div class="info-label">Numer oferty</div>
              <div class="info-value">${offerNumber || '-'}</div>
              <div class="info-label" style="margin-top: 8px;">Data sporządzenia</div>
              <div class="info-value">${date}</div>
            </div>
          </div>
          ${footerHtml(pageIndex, meta)}
        </div>
      </div>
  `;
};
