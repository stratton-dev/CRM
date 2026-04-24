// Common styles for the offer PDF
export const offerStyles = `
  :root {
    --navy: #0B1020;
    --navy-light: #162035;
    --gold: #C6A15B;
    --gold-light: #E5C585;
    --white: #FFFFFF;
    --gray-bg: #F9FAFB;
    --gray-border: #E5E7EB;
    --text-main: #1F2937;
    --text-muted: #64748B;
    --success: #059669;
    --danger: #b91c1c;
  }

  @page { margin: 0; }

  body {
    font-family: 'DM Sans', sans-serif;
    margin: 0;
    padding: 0;
    background: #333;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .page {
    background: var(--white);
    margin: 20px auto;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    display: flex;
    flex-direction: column;
    page-break-after: always;
  }

  @media print {
    body { background: white; }
    .page { margin: 0; box-shadow: none; page-break-after: always; }
  }

  h1, h2, h3 { font-family: 'DM Sans', sans-serif; color: var(--navy); margin: 0; text-transform: uppercase; letter-spacing: 1px; }
  .text-gold { color: var(--gold); }
  .text-navy { color: var(--navy); }
  .muted { color: #64748b; font-size: 10pt; line-height: 1.6; }

  .page-header {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    padding: 18px 40px 12px;
    border-bottom: 1px solid #e2e8f0;
    background: white;
    z-index: 10;
  }

  .header-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 120px;
    gap: 12px;
    align-items: start;
    overflow: hidden;
  }

  .header-grid > div:nth-child(3) {
    text-align: right;
  }

  .header-label {
    font-size: 8pt;
    text-transform: uppercase;
    color: #94a3b8;
    letter-spacing: 1px;
  }

  .header-value {
    font-size: 10pt;
    font-weight: 600;
    color: var(--navy);
    white-space: normal;
    overflow-wrap: break-word;
    max-width: 100%;
  }

  .header-sub {
    font-size: 9pt;
    color: #64748b;
    margin-top: 2px;
    white-space: normal;
    overflow-wrap: break-word;
    max-width: 100%;
  }

  .header-logo {
    text-align: right;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
  }

  .header-logo img {
    max-height: 80px;
    object-fit: contain;
  }

  .header-logo-sub {
    font-size: 8pt;
    color: #94a3b8;
    font-family: monospace;
  }

  .header-page {
    font-size: 9pt;
    color: #94a3b8;
    text-align: right;
    font-family: monospace;
    margin-top: 6px;
  }

  .page-body { margin-top: 86px; height: calc(100% - 86px); }
  .page-pad { padding: 28px 40px; }

  .grid-two { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
  .grid-three { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }

  .info-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; background: #f8fafc; }
  .info-label { font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; }
  .info-value { font-size: 12px; font-weight: 600; color: var(--navy); margin-top: 6px; }
  .info-value.small { font-size: 9px; font-weight: 400; color: var(--navy); margin-top: 6px; }

  .kpi-card { background: #f8fafc; padding: 14px; border-radius: 10px; border: 1px solid #e2e8f0; }
  .kpi-label { font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; }
  .kpi-val { font-size: 18px; font-weight: 800; color: var(--navy); margin-top: 6px; }
  .kpi-sub { font-size: 9px; color: #94a3b8; margin-top: 2px; }

  .kpi-card.highlight { background: var(--navy); color: white; border: none; }
  .kpi-card.highlight .kpi-label { color: rgba(255,255,255,0.6); }
  .kpi-card.highlight .kpi-val { color: var(--gold); }
  .kpi-card.highlight .kpi-sub { color: rgba(255,255,255,0.4); }

  .fin-table { width: 100%; border-collapse: collapse; font-size: 9pt; }
  .fin-table th { text-align: left; padding: 10px 12px; border-bottom: 2px solid var(--navy); color: var(--navy); font-weight: 700; text-transform: uppercase; font-size: 8pt; background: #f1f5f9; }
  .fin-table td { padding: 9px 12px; border-bottom: 1px solid #e2e8f0; color: #334155; }
  .col-highlight { background: rgba(198, 161, 91, 0.08); font-weight: 600; color: var(--navy); }
  .row-total td { font-weight: 800; border-top: 2px solid var(--navy); background: #f8fafc; font-size: 10pt; color: var(--navy); }

  .small-table { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
  .small-table th { text-align: left; padding: 8px 10px; border-bottom: 1px solid #cbd5f5; background: #f8fafc; font-size: 7.5pt; text-transform: uppercase; color: #64748b; }
  .small-table td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; }

  .badge-success { background: #ecfdf5; color: #047857; padding: 3px 8px; border-radius: 4px; font-size: 8pt; font-weight: 700; display: inline-block; }
  .badge-blue { background: #eff6fc; color: #1e3a8a; padding: 3px 8px; border-radius: 4px; font-size: 8pt; font-weight: 700; display: inline-block; }

  .section-title { font-size: 12pt; font-weight: 700; color: var(--navy); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; font-family: 'Cinzel', serif; }

  .timeline-container { margin-top: 24px; position: relative; padding: 20px 0; }
  .timeline-line { position: absolute; top: 32px; left: 60px; right: 60px; height: 3px; background: #e2e8f0; z-index: 0; }
  .timeline-steps { display: flex; justify-content: space-between; position: relative; z-index: 1; }
  .tl-step { text-align: center; flex: 1; padding: 0 10px; }
  .tl-dot-wrapper { display: flex; justify-content: center; margin-bottom: 14px; }
  .tl-dot { width: 16px; height: 16px; background: var(--navy); border: 4px solid white; border-radius: 50%; box-shadow: 0 0 0 3px var(--navy); }
  .tl-week { font-size: 8pt; color: var(--gold); font-weight: 700; text-transform: uppercase; margin-bottom: 6px; display: block; letter-spacing: 1px; }
  .tl-title { font-size: 10pt; font-weight: 700; color: var(--navy); margin-bottom: 6px; font-family: 'Cinzel', serif; }
  .tl-desc { font-size: 8pt; color: #64748b; line-height: 1.4; max-width: 200px; margin: 0 auto; }

  .footer { position: absolute; bottom: 12px; left: 40px; right: 40px; border-top: 1px solid #e2e8f0; padding-top: 4px; display: flex; gap: 18px; font-size: 7pt; color: #94a3b8; text-transform: uppercase; }
  .footer-left { display: flex; align-items: center; }
  .footer-left img { max-height: 60px; object-fit: contain; }
  .footer-middle { display: flex; flex-direction: column; gap: 1px; text-transform: none; flex: 1; }
  .footer-right { display: flex; flex-direction: column; gap: 1px; text-transform: none; text-align: right; font-family: monospace; }
  
  /* Minimal Footer Styles */
  .footer-minimal { 
    justify-content: space-between !important; 
    align-items: flex-end; /* Align to bottom line text */
    padding-top: 8px; /* Slightly more space */
  }
  .footer-minimal .footer-left {
    gap: 12px;
  }
  .footer-minimal .footer-logo-small {
    max-height: 24px;
    opacity: 0.8;
  }
  .footer-minimal .footer-secret {
    font-size: 7pt;
    color: #64748b;
    text-transform: none;
    letter-spacing: 0px;
    white-space: nowrap;
    display: flex;
    align-items: center;
  }

  .footer-line { font-size: 7pt; color: #64748b; line-height: 1.35; }
  .footer-line-1 { font-weight: 700; color: #334155; text-transform: none; font-size: 7.5pt; }
  .footer-line-2 { color: #64748b; }
  .separator { color: #cbd5e1; margin: 0 4px; font-weight: 300; }
  
  .footer-meta { font-size: 7pt; color: #64748b; }
  .footer-vertical { flex-direction: row; align-items: center; }
  .toc-list { display: grid; grid-template-columns: 1fr; gap: 10px; margin-top: 20px; }
  .toc-item { display: flex; justify-content: space-between; border-bottom: 1px dashed #e2e8f0; padding-bottom: 6px; font-size: 10pt; }
  .cover {
    background: radial-gradient(circle at 0% 0%, #1e293b 0%, var(--navy) 60%);
    color: white;
    padding: 60px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
    height: 33%
  }
  .cover > * { position: relative; z-index: 2; }
  .cover-watermark {
    position: absolute;
    right: -15%;
    top: 5%;
    width: 60%;
    opacity: 0.3;
    z-index: 1;
  }
  .cover-title { font-size: 34px; font-weight: 700; color: var(--gold); letter-spacing: 2px; }
  .cover-sub { font-size: 18px; opacity: 0.8; }

  .compare-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
  .compare-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; background: #f8fafc; }
  .compare-card.highlight { background: #fff7ed; border-color: #fdba74; }
  .compare-title { font-size: 10pt; font-weight: 700; color: var(--navy); margin-bottom: 10px; }
  .compare-row { display: flex; justify-content: space-between; gap: 8px; font-size: 9pt; color: var(--text-main); margin-bottom: 6px; }
  .compare-row span { color: var(--text-muted); }
  .compare-row strong { color: var(--navy); font-weight: 600; text-align: right; }
  .compare-row.compare-total { margin-top: 6px; padding-top: 6px; border-top: 1px dashed #e2e8f0; }
  .compare-table { width: 100%; border-collapse: collapse; font-size: 8pt; }
  .compare-table th, .compare-table td { border: 1px solid #e2e8f0; padding: 6px; vertical-align: top; }
  .compare-table thead th { background: #f8fafc; color: var(--navy); font-weight: 700; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.04em; }
  .compare-table tbody td:first-child { width: 34%; color: var(--text-muted); font-weight: 600; }
  .compare-table tbody td:not(:first-child) { text-align: right; color: var(--navy); font-weight: 600; }
  .compare-table tbody tr.row-total td { background: #fff7ed; }
  .compare-table th:nth-child(2), .compare-table td:nth-child(2) { background: #fee2e2; color: #991b1b; }
  .compare-table th:nth-child(4), .compare-table td:nth-child(4) { background: #dcfce7; color: #166534; }
  .legal-box { border: 1px solid #fee2e2; background: #fff5f5; padding: 12px; border-radius: 8px; font-size: 9pt; color: #7f1d1d; }
  .chart-wrap { width: 100%; height: 220px; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; background: #ffffff; }
  .chart-wrap canvas { width: 100%; height: 100%; }
`;
