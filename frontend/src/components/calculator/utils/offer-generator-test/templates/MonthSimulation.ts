import { header, footerHtml, formatPLN } from '../common';
import { RenderContext } from '../types';

export const MonthSimulation = (ctx: RenderContext) => {
  const { monthSimulation, statsSelected, meta } = ctx;
  const pageIndex = 4;

  const monthlySavings = statsSelected.oszczednoscMiesieczna;
  
  // Calculate max value for chart scaling
  const maxCumulative = monthSimulation[monthSimulation.length - 1].cumulative;
  
  // Chart dimensions
  const width = 600;
  const height = 150;
  const padding = 20;
  
  // Generate SVG path points
  // X axis: 0 to 11 (12 months)
  // Y axis: 0 to maxCumulative
  const points = monthSimulation.map((row: any, index: number) => {
    const x = (index / (monthSimulation.length - 1)) * width;
    const y = height - (row.cumulative / maxCumulative) * height;
    return `${x},${y}`;
  }).join(' ');

  // Create area path (line points + bottom corners)
  const areaPath = `0,${height} ${points} ${width},${height}`;

  return `
    <div class="page">
      ${header('PŁYNNOŚĆ I CASHFLOW', ctx.date, ctx.firma, ctx.advisor)}
      
      <div class="page-body page-pad" style="font-family: 'DM Sans', sans-serif;">
        
        <!-- SECTION 1: HEADER -->
        <div style="margin-bottom: 24px;">
            <h1 style="font-size: 24px; font-weight: 700; color: #0B1020; margin-bottom: 4px; letter-spacing: -0.5px;">
                Symulacja Płynności Finansowej
            </h1>
            <p style="font-size: 13px; color: #64748B; margin: 0;">
                Prognoza oszczędności netto dla <span style="font-weight: 600; color: #334155;">${ctx.firma.nazwa || 'Firmy'}</span> po wdrożeniu modelu.
            </p>
        </div>

        <!-- SECTION 2: CHART (Visual) -->
        <div style="background: white; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; margin-bottom: 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 16px;">
                <div style="font-size: 11px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.05em;">Skumulowane oszczędności netto (PLN)</div>
                <div style="font-size: 24px; font-weight: 700; color: #10b981; font-family: 'Inter', monospace;">
                    ${formatPLN(maxCumulative)} <span style="font-size: 14px; color: #94A3B8; font-weight: 400;">(Rok 1)</span>
                </div>
            </div>
            
            <div style="height: 150px; width: 100%; position: relative;">
                 <!-- SVG Chart -->
                 <svg viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" style="width: 100%; height: 100%; overflow: visible;">
                     <defs>
                         <linearGradient id="chartGradient_${pageIndex}" x1="0" x2="0" y1="0" y2="1">
                             <stop offset="0%" stop-color="#10b981" stop-opacity="0.2"/>
                             <stop offset="100%" stop-color="#10b981" stop-opacity="0"/>
                         </linearGradient>
                     </defs>
                     
                     <!-- Area -->
                     <path d="M${points} L${width},${height} L0,${height} Z" fill="url(#chartGradient_${pageIndex})" stroke="none" />
                     
                     <!-- Line -->
                     <polyline points="${points}" fill="none" stroke="#10b981" stroke-width="2" vector-effect="non-scaling-stroke" />
                     
                     <!-- End Point Dot -->
                     <circle cx="${width}" cy="0" r="4" fill="#10b981" stroke="white" stroke-width="2" />
                 </svg>
            </div>
             <div style="display: flex; justify-content: space-between; margin-top: 8px; font-size: 10px; color: #94A3B8; font-family: 'Inter', sans-serif;">
                <div>Start</div>
                <div>Miesiąc 6</div>
                <div>Rok 1</div>
             </div>
        </div>

        <!-- SECTION 3: TABLE -->
        <div style="background: white; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden; margin-bottom: 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                <thead>
                    <tr style="background-color: #F8FAFC; border-bottom: 1px solid #E2E8F0;">
                        <th style="padding: 12px 24px; font-weight: 600; color: #64748B; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em;">Miesiąc</th>
                        <th style="padding: 12px 24px; font-weight: 600; color: #64748B; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; text-align: right;">Oszczędność Miesięczna</th>
                        <th style="padding: 12px 24px; font-weight: 600; color: #10b981; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; text-align: right;">Zysk Skumulowany</th>
                    </tr>
                </thead>
                <tbody style="font-family: 'Inter', monospace; color: #334155;">
                    ${monthSimulation.map((row: any, index: number) => {
                        const isLast = index === monthSimulation.length - 1;
                        const bgStyle = index % 2 === 0 ? 'background-color: #ffffff;' : 'background-color: #F8FAFC;';
                        const rowStyle = isLast 
                            ? 'background-color: #ECFDF5; border-top: 2px solid #10b981;' 
                            : `border-bottom: 1px solid #F1F5F9; ${bgStyle}`;
                        
                        const textClass = isLast ? 'font-weight: 700; color: #047857;' : 'color: #334155;';
                        const amountClass = isLast ? 'font-weight: 700; color: #059669;' : 'color: #10b981; font-weight: 500;';

                        return `
                        <tr style="${rowStyle}">
                            <td style="padding: 10px 24px; ${isLast ? 'font-weight: 700; color: #064e3b;' : ''}">${row.name}</td>
                            <td style="padding: 10px 24px; text-align: right; color: #64748B;">${formatPLN(monthlySavings)}</td>
                            <td style="padding: 10px 24px; text-align: right; ${amountClass}">${formatPLN(row.cumulative)}</td>
                        </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        </div>

        <!-- SECTION 4: ARGUMENT NEGACZA -->
        <div style="border: 1px solid #E2E8F0; background-color: #FFF; padding: 20px; border-radius: 8px; display: flex; gap: 16px; align-items: flex-start; margin-bottom: 24px;">
            <div style="min-width: 40px; height: 40px; background-color: #FEF3C7; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #D97706;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 18h6"></path>
                    <path d="M10 22h4"></path>
                    <path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 16.5 8 4.5 4.5 0 0 0 12 3.5 4.5 4.5 0 0 0 7.5 8c0 1.57.5 3.03 1.41 4.5l.09.15c.75.75 1.25 1.51 1.41 2.5"></path>
                </svg>
            </div>
            <div>
                <div style="font-size: 14px; font-weight: 700; color: #1E293B; margin-bottom: 4px; font-family: 'DM Sans', sans-serif;">Samofinansowanie projektu</div>
                <div style="font-size: 13px; color: #475569; line-height: 1.5; font-family: 'Inter', sans-serif;">
                    Dzięki modelowi Success Fee i natychmiastowym efektom, wdrożenie Eliton Prime™ nie wymaga angażowania budżetu inwestycyjnego. Projekt finansuje się z wypracowanych nadwyżek od pierwszego rozliczenia listy płac.
                </div>
            </div>
        </div>
        
        <div style="margin-top: auto; color: #94A3B8; font-size: 10px; font-family: 'DM Sans', sans-serif; display: flex; justify-content: flex-end;">
            Zweryfikowano przez dział analityczny Stratton Prime
        </div>

      </div>
      
      ${footerHtml(pageIndex, ctx.meta)}
    </div>
  `;
};
