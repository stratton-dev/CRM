// Executive Summary Template
import { RenderContext } from '../types';
import { formatPLN, header, footerHtml } from '../common';

export const ExecutiveSummary = (ctx: RenderContext) => {
  const { statsSelected, firma, date, advisor, meta, payrollRows } = ctx;
  const yearlySavings = formatPLN(statsSelected.oszczednoscRoczna);
  const monthlySavings = formatPLN(statsSelected.oszczednoscMiesieczna);

  // Calculate Average Net Increase dynamically
  // If payrollRows is missing or empty, fallback to 10% or some default
  let avgNetIncreasePercentStr = '+10%'; 
  if (payrollRows && payrollRows.length > 0) {
      const rows = payrollRows; // Use all rows for average
      const totalNetIncrease = rows.reduce((acc: number, row: any) => acc + (row.modelNetto - row.standardNetto), 0);
      const totalNetBase = rows.reduce((acc: number, row: any) => acc + row.standardNetto, 0);
      const percentIncrease = totalNetBase > 0 ? (totalNetIncrease / totalNetBase) * 100 : 0;
      avgNetIncreasePercentStr = `+${percentIncrease.toLocaleString('pl-PL', { maximumFractionDigits: 1 })}%`;
  }


  return `
    <div class="page executive-summary-page">
      ${header('PODSUMOWANIE STRATEGICZNE', date, firma, advisor)}
      
      <div class="page-body summary-content page-pad" style="margin-top: 16px;">
        
        <!-- SECTION 1: DIAGNOZA (The Pain) -->
        <div class="summary-card pain-section">
          <div class="card-header">
            <div class="card-label">SYTUACJA BIEŻĄCA ${firma.nazwa ? firma.nazwa.toUpperCase() : 'TWOJEJ FIRMY'}</div>
            <div class="card-icon-pain">
               <!-- Alert Triangle Icon -->
               <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
          </div>
          <div class="card-body">
            ${firma.aiDiagnoza ? firma.aiDiagnoza : `Analiza wykazała, że obecna struktura wynagrodzeń w <span style="font-weight: 600;">${firma.nazwa || 'Państwa firmie'}</span> generuje nieefektywność kosztową na poziomie <span class="highlight-pain">${monthlySavings} miesięcznie</span>. 
            Przy rosnącej presji płacowej, brak optymalizacji ogranicza Państwa zdolność do reinwestycji w rozwój zespołu.`}
          </div>
        </div>

        <!-- SECTION 2: ROZWIĄZANIE (The Bridge) -->
        <div class="summary-card solution-section">
           <div class="card-header">
            <div class="card-label">MODEL ELITON PRIME™</div>
             <div class="card-icon-solution">
               <!-- Layers/Bridge Icon -->
               <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
            </div>
          </div>
           <div class="card-body">
              Wdrażamy bezpieczny prawnie mechanizm dystrybucji wynagrodzeń, który obniża pozapłacowe koszty pracy (ZUS/Podatki), przekierowując te środki bezpośrednio do budżetu firmy oraz do portfeli pracowników.
           </div>
        </div>

        <!-- SECTION 3: KLUCZOWE KORZYŚCI (The Result) -->
        <div class="summary-card benefits-section">
           <div class="card-header">
              <div class="card-label">KLUCZOWE KORZYŚCI WDROŻENIA</div>
           </div>
           
           <div class="benefits-grid">
              <div class="benefit-item">
                 <div class="benefit-icon text-green">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                 </div>
                 <div class="benefit-value text-green">${yearlySavings}</div>
                 <div class="benefit-desc">Roczne oszczędności w budżecie płacowym</div>
              </div>

              <div class="benefit-item">
                  <div class="benefit-icon text-blue">
                     <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                  </div>
                 <div class="benefit-value text-blue">${avgNetIncreasePercentStr} Netto</div>
                 <div class="benefit-desc">Średni wzrost wynagrodzenia dla pracowników bez kosztów po stronie firmy</div>
              </div>

              <div class="benefit-item">
                  <div class="benefit-icon text-navy">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                  </div>
                 <div class="benefit-value text-navy">0 PLN Ryzyka</div>
                 <div class="benefit-desc">Model oparty wyłącznie na Success Fee (płacisz tylko od realnych oszczędności)</div>
              </div>
              
              <!-- Fourth item for grid balance, or make it 3 cols and center? Prompt said grid-cols-3. 
                   Wait, prompt had 4 items in text: 
                   1. +503k savings
                   2. +10% netto
                   3. 0 risk
                   4. Full Safety
                   So 3 cols grid might be tricky for 4 items unless 4th spans or is separate.
                   Or maybe row 1: 3 cols, row 2: 1 col?
                   Actually prompt said: "Use a grid-cols-3 layout for the benefits section." 
                   But listed 4 items...
                   I will put the 4th item (Safety) as a full-width footer or alongside if space permits.
                   Let's do a 2x2 grid or just adjust to fit nicely. 
                   Actually, CSS grid can handle auto-fit. 
                   Let's check the prompt again: "Use a grid-cols-3 layout". 
                   Maybe the 4th item is less emphasized or the user made a typo.
                   I'll put the 4th item in as well.
              -->
           </div>
           
           <div class="benefit-safety-row">
              <div class="safety-icon text-gold">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              </div>
              <div class="safety-text">
                 <strong>Pełne Bezpieczeństwo</strong> – Rozwiązanie oparte o aktualne interpretacje KIS i orzecznictwo ZUS.
              </div>
           </div>

        </div>

      </div>
      ${footerHtml(2, meta)}
    </div>
  `;
};
