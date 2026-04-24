import { header, footerHtml, formatPLN, splitProvision } from '../common';
import { RenderContext } from '../types';

export const LegalAndBenefits = (ctx: RenderContext) => {
  const { statsSelected, isPlus, meta } = ctx;
  const pageIndex = 6;

  const totalProvision = statsSelected.prowizja;
  const selectedSplit = splitProvision(totalProvision, isPlus);
  const feeAmount = selectedSplit.fee;
  const raiseAmount = selectedSplit.raise;
  const adminAmount = selectedSplit.admin;


  return `
      <div class="page">
        ${header('Gwarancje / korzyści / konstrukcja prawna', ctx.date, ctx.firma, ctx.advisor)}
        <div class="page-body page-pad">
          <div class="grid-two">
            <div>
              <div class="section-title">Podział prowizji</div>
              <div class="info-card" style="margin-bottom: 12px;">
                <div class="info-label">Struktura prowizyjna</div>
                <div class="info-value">
                  ${isPlus
                    ? `Opłata za usługę: ${formatPLN(feeAmount)}; Podwyżki: ${formatPLN(raiseAmount)}; Administracja: ${formatPLN(adminAmount)}.`
                    : `Opłata za usługę: ${formatPLN(totalProvision)}.`}
                </div>
              </div>
              <div class="info-card">
                <div class="info-label">Podstawy prawne</div>
                <div class="info-value">Model oparty o interpretacje podatkowe i przepisy prawa pracy.</div>
              </div>
            </div>
            <div>
              <div class="section-title">Korzyści</div>
              <div class="info-card" style="margin-bottom: 12px;">
                <div class="info-label">Bezpieczeństwo</div>
                <div class="info-value">Pełna dokumentacja i wsparcie w czasie wdrożenia.</div>
              </div>
              <div class="info-card" style="margin-bottom: 12px;">
                <div class="info-label">Transparentność</div>
                <div class="info-value">Czytelne zestawienia dla działu kadr i pracowników.</div>
              </div>
              <div class="info-card">
                <div class="info-label">Kontrola</div>
                <div class="info-value">Miesięczne raporty oszczędności i kosztów.</div>
              </div>
            </div>
          </div>
          ${footerHtml(pageIndex, meta)}
        </div>
      </div>
  `;
};
