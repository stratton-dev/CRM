import { header, footerHtml, formatPLN, safeText } from '../common';
import { RenderContext } from '../types';

export const PayrollTable = (ctx: RenderContext) => {
  const { payrollRows, meta } = ctx;
  const pageIndex = 5;

  // We limit to 10 rows as per design
  const rows = payrollRows.slice(0, 10);

  // Calculate Average Net Increase
  const totalNetIncrease = rows.reduce((acc: number, row: any) => acc + (row.modelNetto - row.standardNetto), 0);
  const avgNetIncrease = rows.length > 0 ? totalNetIncrease / rows.length : 0;
  
  // Calculate Avg % Increase
  const totalNetBase = rows.reduce((acc: number, row: any) => acc + row.standardNetto, 0);
  const percentIncrease = totalNetBase > 0 ? (totalNetIncrease / totalNetBase) * 100 : 0;
  
  const formattedPercent = percentIncrease.toLocaleString('pl-PL', { maximumFractionDigits: 1 });

  return `
    <div class="page">
      ${header('KORZYŚĆ PRACOWNIKA', ctx.date, ctx.firma, ctx.advisor)}
      
      <div class="page-body page-pad" style="font-family: 'DM Sans', sans-serif;">
        
        <!-- SECTION 1: HEADER -->
        <div style="margin-bottom: 32px;">
            <h1 style="font-size: 24px; font-weight: 700; color: #0B1020; margin-bottom: 4px; letter-spacing: -0.5px;">
                Korzyść Pracownika
            </h1>
            <p style="font-size: 13px; color: #64748B; margin: 0;">
                Symulacja wzrostu wynagrodzeń netto dla wybranych stanowisk w <span style="font-weight: 600; color: #334155;">${ctx.firma.nazwa || 'Twojej Firmie'}</span>.
            </p>
        </div>

        <!-- SECTION 2: TABLE -->
        <div style="background: white; border-radius: 12px; border: 1px solid #E2E8F0; overflow: hidden; margin-bottom: 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                <thead>
                    <tr style="background-color: #0B1020; color: white;">
                        <th style="padding: 14px 24px; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; font-family: 'Inter', sans-serif;">Stanowisko / Pracownik</th>
                        <th style="padding: 14px 24px; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; text-align: right; font-family: 'Inter', sans-serif;">Brutto (Umowa)</th>
                        <th style="padding: 14px 24px; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; text-align: right; font-family: 'Inter', sans-serif;">Netto Obecnie</th>
                        <th style="padding: 14px 24px; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; text-align: right; background-color: #064e3b; font-family: 'Inter', sans-serif;">Netto Eliton Prime™</th>
                        <th style="padding: 14px 24px; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; text-align: right; color: #34D399; font-family: 'Inter', sans-serif;">Zysk Pracownika (m-c)</th>
                    </tr>
                </thead>
                <tbody style="font-family: 'Inter', monospace; color: #334155;">
                    ${rows.map((row: any, index: number) => {
                        const netIncrease = row.modelNetto - row.standardNetto;
                        const isEven = index % 2 === 0;
                        const bgStyle = isEven ? 'background-color: #ffffff;' : 'background-color: #F8FAFC;';
                        
                        return `
                        <tr style="border-bottom: 1px solid #F1F5F9; ${bgStyle}">
                            <td style="padding: 12px 24px; font-weight: 600; color: #1E293B; font-family: 'DM Sans', sans-serif;">
                                ${safeText(row.name)} <span style="font-weight: 400; color: #94A3B8; font-size: 11px; margin-left: 4px;">(${row.contract})</span>
                            </td>
                            <td style="padding: 12px 24px; text-align: right; color: #64748B;">${formatPLN(row.standardBrutto)}</td>
                            <td style="padding: 12px 24px; text-align: right; color: #64748B;">${formatPLN(row.standardNetto)}</td>
                            <td style="padding: 12px 24px; text-align: right; font-weight: 700; color: #065f46; background-color: #f0fdf4;">${formatPLN(row.modelNetto)}</td>
                            <td style="padding: 12px 24px; text-align: right;">
                                <span style="display: inline-block; padding: 4px 8px; border-radius: 9999px; background-color: #DCFCE7; color: #166534; font-weight: 700; font-size: 11px; font-family: 'Inter', sans-serif;">
                                    +${formatPLN(netIncrease)}
                                </span>
                            </td>
                        </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        </div>

        <!-- SECTION 3: HIGHLIGHT BOX -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px;">
             <!-- Left: Avg Increase -->
             <div style="background-color: #0B1020; color: white; border-radius: 12px; padding: 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
                 <div>
                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #94A3B8; margin-bottom: 6px; font-weight: 600;">Średni wzrost netto</div>
                    <div style="font-size: 28px; font-weight: 700; color: #34D399; font-family: 'Inter', monospace;">+${formattedPercent}%</div>
                 </div>
                 <div style="width: 48px; height: 48px; background-color: rgba(52, 211, 153, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #34D399;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                 </div>
             </div>

             <!-- Right: Emp Cost -->
             <div style="background-color: white; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                 <div>
                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #64748B; margin-bottom: 6px; font-weight: 600;">Koszt pracodawcy</div>
                    <div style="font-size: 28px; font-weight: 700; color: #0B1020; font-family: 'Inter', monospace;">BEZ ZMIAN</div>
                 </div>
                 <div style="width: 48px; height: 48px; background-color: #F1F5F9; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #64748B;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                 </div>
             </div>
        </div>

        <!-- SECTION 4: TRUST/COMMUNICATION -->
        <div style="display: flex; gap: 16px; align-items: flex-start; padding: 20px; background-color: #F8FAFC; border-radius: 8px; border: 1px solid #E2E8F0;">
             <div style="min-width: 32px; height: 32px; color: #059669; display: flex; align-items: center; justify-content: center;">
                <!-- Shield Icon -->
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
             </div>
             <div>
                <div style="font-size: 14px; font-weight: 700; color: #1E293B; margin-bottom: 4px; font-family: 'DM Sans', sans-serif;">Bezpieczeństwo i Komunikacja</div>
                <div style="font-size: 13px; color: #475569; line-height: 1.5; font-family: 'Inter', sans-serif;">
                    Udział w modelu jest dla pracownika w pełni dobrowolny. Przygotowujemy kompletną dokumentację i prowadzimy warsztaty z zespołem, aby każdy rozumiał korzyści płynące z nowego modelu wynagradzania.
                </div>
             </div>
        </div>

      </div>
      
      ${footerHtml(pageIndex, ctx.meta)}
    </div>
  `;
};
