import { header, footerHtml, formatPLN, safeText } from '../common';
import { RenderContext } from '../types';

export const FinancialIllustration = (ctx: RenderContext) => {
  const {
    meta,
    statsSelected,
    statsStandard,
    employeeCount,
    isPlus,
    contracts,
    monthlyFeesAfterWages,
    annualFeesAfterWages,
    threeYearEffect,
    avgPerEmployee,
  } = ctx;

  const pageIndex = 3; 

  // Calculations for the table
  // Row 1: Gross Wages (Koszt brutto wynagrodzeń)
  // We assume statsStandard.brutto is the total gross wages currently.
  // In the new model (statsSelected), gross wages might appear differently purely on paper if we change structure, 
  // but usually "Koszt brutto wynagrodzeń" refers to what employees earn gross or cost of salaries.
  // If the model preserves gross, it should be similar.
  // Let's use statsStandard.brutto for Current and statsSelected.brutto for New.
  
  const row1_Current = statsSelected.standard.brutto;
  const row1_New = statsSelected.stratton.brutto;
  const row1_Diff = row1_Current - row1_New;
  // If Difference is 0, we show 0 zł.

  // Row 2: ZUS Employer (Składki ZUS (Pracodawca))
  // statsStandard.zusPracodawca vs statsSelected.zusPracodawca
  const row2_Current = statsSelected.standard.zusPracodawca;
  const row2_New = statsSelected.stratton.zusPracodawca;
  const row2_Diff = row2_Current - row2_New;

  // Row 3: Other (Inne obciążenia)
  // Total Employer Cost = Brutto + ZUS + Others.
  // We check if (Total - Brutto - ZUS) > 0.
  
  const calcOther = (total: number, brutto: number, zus: number) => {
      const other = total - brutto - zus;
      return other > 1 ? other : 0; // Tolerance for float errors
  };

  const row3_Current = calcOther(statsSelected.totalCostStandard, row1_Current, row2_Current);
  const row3_New = calcOther(statsSelected.totalCostModel, row1_New, row2_New);
  const row3_Diff = row3_Current - row3_New;

  // Summary
  const totalCurrent = statsSelected.totalCostStandard;
  const totalNew = statsSelected.totalCostModel;
  
  // Recalculations done above using totalCostModel


  const totalSavings = totalCurrent - totalNew;
  
  const savings12m = formatPLN(totalSavings);
  const savings5y = formatPLN(totalSavings * 5); // Simple projection

  return `
    <div class="page">
      ${header('ANALIZA FINANSOWA', ctx.date, ctx.firma, ctx.advisor)}
      
      <div class="page-body page-pad" style="font-family: 'DM Sans', sans-serif;">
        
        <!-- SECTION 1 -->
        <div style="margin-bottom: 24px; border-bottom: 1px solid #E2E8F0; padding-bottom: 16px;">
            <h1 style="font-size: 24px; font-weight: 700; color: #0f172a; margin-bottom: 4px; letter-spacing: -0.5px;">
                Analiza Efektywności Kosztowej
            </h1>
             <p style="font-size: 13px; color: #64748b; margin: 0;">
                Zestawienie kosztów dla podmiotu: <span style="font-weight: 600; color: #334155;">${ctx.firma.nazwa || 'Firma'}</span> (NIP: ${safeText(ctx.firma.nip)})
             </p>
        </div>

        <!-- SECTION 2: TABELA -->
        <div style="background: white; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); overflow: hidden; margin-bottom: 40px; border: 1px solid #e2e8f0;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 16px 24px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600;">Składnik kosztowy</th>
                        <th style="padding: 16px 24px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; text-align: right;">Stan Obecny (Rocznie)</th>
                        <th style="padding: 16px 24px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #047857; font-weight: 700; text-align: right; background-color: #ecfdf5;">Model Eliton Prime™</th>
                        <th style="padding: 16px 24px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #059669; font-weight: 700; text-align: right;">Różnica (Zysk)</th>
                    </tr>
                </thead>
                <tbody style="font-family: 'Inter', monospace; color: #334155; font-size: 13px;">
                    <!-- Row 1 -->
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 16px 24px; font-weight: 500; color: #0f172a; font-family: 'DM Sans', sans-serif;">Koszt brutto wynagrodzeń</td>
                        <td style="padding: 16px 24px; text-align: right; font-variant-numeric: tabular-nums;">${formatPLN(row1_Current)}</td>
                        <td style="padding: 16px 24px; text-align: right; font-variant-numeric: tabular-nums; background-color: #f0fdf4; color: #065f46;">${formatPLN(row1_New)}</td>
                        <td style="padding: 16px 24px; text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; color: #94a3b8;">${formatPLN(Math.abs(row1_Diff))}</td>
                    </tr>
                    <!-- Row 2 -->
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 16px 24px; font-weight: 500; color: #0f172a; font-family: 'DM Sans', sans-serif;">Składki ZUS (Pracodawca)</td>
                        <td style="padding: 16px 24px; text-align: right; font-variant-numeric: tabular-nums;">${formatPLN(row2_Current)}</td>
                        <td style="padding: 16px 24px; text-align: right; font-variant-numeric: tabular-nums; background-color: #f0fdf4; color: #065f46;">${formatPLN(row2_New)}</td>
                        <td style="padding: 16px 24px; text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; color: #059669;">+${formatPLN(row2_Diff)}</td>
                    </tr>
                    <!-- Row 3 -->
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                         <td style="padding: 16px 24px; font-weight: 500; color: #0f172a; font-family: 'DM Sans', sans-serif;">Inne obciążenia (Fundusze/PPK)</td>
                        <td style="padding: 16px 24px; text-align: right; font-variant-numeric: tabular-nums;">${formatPLN(row3_Current)}</td>
                        <td style="padding: 16px 24px; text-align: right; font-variant-numeric: tabular-nums; background-color: #f0fdf4; color: #065f46;">${formatPLN(row3_New)}</td>
                        <td style="padding: 16px 24px; text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; color: #059669;">${row3_Diff > 0 ? '+' : ''}${formatPLN(row3_Diff)}</td>
                    </tr>
                    <!-- Summary -->
                    <tr style="background-color: #0f172a; color: white;">
                        <td style="padding: 20px 24px; font-weight: 700; text-transform: uppercase; font-family: 'DM Sans', sans-serif; letter-spacing: 0.05em;">CAŁKOWITY KOSZT</td>
                        <td style="padding: 20px 24px; text-align: right; font-variant-numeric: tabular-nums; color: #94a3b8; font-weight: 500;">${formatPLN(totalCurrent)}</td>
                        <td style="padding: 20px 24px; text-align: right; font-variant-numeric: tabular-nums; background-color: rgba(255,255,255,0.05); color: #34d399; font-weight: 700;">${formatPLN(totalNew)}</td>
                        <td style="padding: 20px 24px; text-align: right; font-variant-numeric: tabular-nums; color: #34d399; font-weight: 700; font-size: 15px; border-left: 1px solid rgba(255,255,255,0.1);">+${savings12m}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- SECTION 3: MULTIPLIER -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 40px;">
            <!-- Box 1 -->
            <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 12px; padding: 24px; color: white; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2); position: relative; overflow: hidden;">
                 <div style="position: relative; z-index: 10;">
                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #d1fae5; margin-bottom: 8px; font-weight: 600;">Oszczędność 12 m-cy</div>
                    <div style="font-size: 32px; font-weight: 700; font-family: 'Inter', monospace; letter-spacing: -1px;">${savings12m}</div>
                 </div>
                 <!-- Decorative transparency -->
                 <div style="position: absolute; top: -10px; right: -10px; width: 100px; height: 100px; background: white; opacity: 0.1; border-radius: 50%;"></div>
            </div>

            <!-- Box 2 -->
             <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; color: #0f172a; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); position: relative;">
                 <div style="position: relative; z-index: 10;">
                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #64748b; margin-bottom: 8px; font-weight: 700;">Prognoza 5 lat</div>
                    <div style="font-size: 32px; font-weight: 700; font-family: 'Inter', monospace; letter-spacing: -1px; color: #0f172a;">${savings5y}</div>
                    <div style="font-size: 10px; color: #94a3b8; margin-top: 8px;">Skumulowana wartość przy stałych parametrach.</div>
                 </div>
            </div>
        </div>

        <!-- SECTION 4: DISCLAIMER -->
        <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 16px; border-radius: 0 8px 8px 0; margin-bottom: 20px;">
             <p style="margin: 0 0 4px 0; color: #92400e; font-size: 13px; font-weight: 600;">Uwaga eksperta:</p>
             <p style="margin: 0; color: #b45309; font-size: 12px; line-height: 1.5;">
                Model nie zakłada obniżenia pensji brutto pracowników. Całość oszczędności generowana jest poprzez optymalizację klina podatkowo-składkowego zgodnie z aktualnymi przepisami.
             </p>
        </div>

        <div style="font-size: 10px; color: #cbd5e1; text-align: center; margin-top: auto;">
            Symulacja na podstawie audytu wstępnego ${safeText(ctx.firma.nazwa)}. Wartości mogą ulec zmianie w zależności od finalnej struktury zatrudnienia.
        </div>

      </div>
      
      ${footerHtml(pageIndex, ctx.meta)}
    </div>
  `;
};
