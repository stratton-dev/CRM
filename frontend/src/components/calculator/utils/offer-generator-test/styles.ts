// Common styles for the offer PDF
export const offerStyles = `
  :root {
    --navy: #0B1020;
    --navy-light: #1E293B;
    --navy-accent: #2c3e50;
    --gold: #C6A15B;
    --gold-dim: #b8934a;
    --gold-light: #F3E6C8;
    --white: #FFFFFF;
    --gray-bg: #F8FAFC;
    --gray-light: #F1F5F9;
    --gray-border: #E2E8F0;
    --text-main: #334155;
    --text-dark: #0F172A;
    --text-muted: #64748B;
    --success: #059669;
    --success-bg: #ECFDF5;
    --danger: #DC2626;
  }

  @page { margin: 0; }

  * { box-sizing: border-box; }

  body {
    font-family: 'DM Sans', sans-serif;
    margin: 0;
    padding: 0;
    background: #525659;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    color: var(--text-main);
  }

  .page {
    background: var(--white);
    margin: 40px auto;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    display: flex;
    flex-direction: column;
    width: 210mm;
    height: 297mm;
    page-break-after: always;
  }

  /* Watermark / Texture for Premium Feel */
  .page::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 6px;
    background: linear-gradient(90deg, var(--navy) 0%, var(--navy-light) 50%, var(--gold) 100%);
    z-index: 20;
  }
  
  .page::after {
      content: "";
      position: absolute;
      bottom: 0;
      right: 0;
      width: 300px;
      height: 300px;
      background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%230B1020' fill-opacity='0.03' fill-rule='evenodd'/%3E%3C/svg%3E");
      opacity: 0.6;
      pointer-events: none;
      z-index: 0;
  }

  @media print {
    body { background: white; margin: 0; }
    .page { margin: 0; box-shadow: none; page-break-after: always; width: 210mm; height: 297mm; overflow: visible; }
  }

  h1 { font-family: 'Cinzel', serif; font-weight: 700; color: var(--navy); margin: 0; text-transform: uppercase; letter-spacing: -0.5px; line-height: 1.1; }
  h2 { font-family: 'Cinzel', serif; font-weight: 600; color: var(--navy-light); margin: 0; letter-spacing: 0.5px; }
  h3 { font-family: 'DM Sans', sans-serif; font-weight: 700; color: var(--text-dark); margin: 0; letter-spacing: 0; }
  
  .text-gold { color: var(--gold); }
  .text-navy { color: var(--navy); }
  .text-muted { color: var(--text-muted); }
  .bg-navy { background-color: var(--navy); color: white; }
  
  .muted { color: var(--text-muted); font-size: 9pt; line-height: 1.5; }

  .page-header {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    padding: 24px 40px 0;
    background: white;
    z-index: 10;
  }

  /* Adjusted for new compact header */
  .page-body { 
    margin-top: 80px; 
    height: calc(100% - 80px); 
    display: flex;
    flex-direction: column;
    position: relative;
    z-index: 1;
  }
  
  .page-pad { 
    padding: 0 48px 32px; /* Increased padding for luxury feel */
    flex: 1;
    display: flex;
    flex-direction: column;
  }

  /* Grid Systems */
  .grid-two { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
  .grid-three { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }

  /* Premium Cards */
  .info-card, .kpi-card, .summary-card, .benefit-item, .compare-card {
    background: var(--white);
    border: 1px solid var(--gray-border);
    border-radius: 2px; /* Sharper corners for modern financial look */
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
  }
  
  .info-card { padding: 16px; background: var(--gray-bg); border-left: 3px solid var(--navy); }
  
  /* KPIs */
  .kpi-card { padding: 20px; text-align: left; }
  .kpi-label { font-size: 9px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; letter-spacing: 1px; margin-bottom: 8px; }
  .kpi-val { font-family: 'Inter', monospace; font-size: 24px; font-weight: 700; color: var(--navy); letter-spacing: -0.5px; }
  
  /* Financial Table Styling - The "consulting" look */
  .fin-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 9pt; margin-bottom: 24px; }
  
  .fin-table th { 
    text-align: right; 
    padding: 12px 16px; 
    border-bottom: 2px solid var(--navy); 
    color: var(--navy); 
    font-weight: 700; 
    text-transform: uppercase; 
    font-size: 8pt; 
    letter-spacing: 0.5px;
    background: white;
    font-family: 'Inter', sans-serif;
  }
  
  .fin-table th:first-child { text-align: left; padding-left: 8px; }
  
  .fin-table td { 
    padding: 12px 16px; 
    border-bottom: 1px solid var(--gray-border); 
    color: var(--text-main); 
    text-align: right;
    font-variant-numeric: tabular-nums;
  }
  
  .fin-table td:first-child { text-align: left; font-weight: 500; color: var(--text-dark); padding-left: 8px; }
  
  .fin-table tr:last-child td { border-bottom: none; }
  .fin-table tr:hover td { background-color: var(--gray-light); }

  .col-highlight { background: rgba(198, 161, 91, 0.04); font-weight: 600; color: var(--navy); border-left: 1px solid rgba(198, 161, 91, 0.1); border-right: 1px solid rgba(198, 161, 91, 0.1); }
  
  .row-total td { 
    font-weight: 800; 
    border-top: 2px solid var(--navy); 
    border-bottom: 2px double var(--navy) !important;
    background: var(--gray-bg); 
    font-size: 10pt; 
    color: var(--navy); 
    padding-top: 16px;
    padding-bottom: 16px;
  }
  
  .small-table { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
  .small-table th { text-align: left; padding: 8px 10px; border-bottom: 1px solid #cbd5f5; background: #f8fafc; font-size: 7.5pt; text-transform: uppercase; color: #64748b; }
  .small-table td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; }

  .badge-success { background: #ecfdf5; color: #047857; padding: 3px 8px; border-radius: 4px; font-size: 8pt; font-weight: 700; display: inline-block; }
  .badge-blue { background: #eff6fc; color: #1e3a8a; padding: 3px 8px; border-radius: 4px; font-size: 8pt; font-weight: 700; display: inline-block; }

  .section-title { font-size: 14pt; font-weight: 700; color: var(--navy); margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px; font-family: 'Cinzel', serif; border-left: 4px solid var(--gold); padding-left: 12px; }

  .timeline-container { margin-top: 24px; position: relative; padding: 20px 0; }
  .timeline-line { position: absolute; top: 32px; left: 60px; right: 60px; height: 3px; background: #e2e8f0; z-index: 0; }
  .timeline-steps { display: flex; justify-content: space-between; position: relative; z-index: 1; }
  .tl-step { text-align: center; flex: 1; padding: 0 10px; }
  .tl-dot-wrapper { display: flex; justify-content: center; margin-bottom: 14px; }
  .tl-dot { width: 16px; height: 16px; background: var(--navy); border: 4px solid white; border-radius: 50%; box-shadow: 0 0 0 3px var(--navy); }
  .tl-week { font-size: 8pt; color: var(--gold); font-weight: 700; text-transform: uppercase; margin-bottom: 6px; display: block; letter-spacing: 1px; }
  .tl-title { font-size: 10pt; font-weight: 700; color: var(--navy); margin-bottom: 6px; font-family: 'Cinzel', serif; }
  .tl-desc { font-size: 8pt; color: #64748b; line-height: 1.4; max-width: 200px; margin: 0 auto; }

  .footer { position: absolute; bottom: 16px; left: 48px; right: 48px; border-top: 1px solid var(--gray-border); padding-top: 8px; display: flex; gap: 18px; font-size: 8pt; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; font-family: 'DM Sans', sans-serif; }
  .footer-left { display: flex; align-items: center; }
  .footer-left img { max-height: 20px; object-fit: contain; } /* Smaller, more discreet logo */
  .footer-middle { display: flex; flex-direction: column; gap: 2px; text-transform: none; flex: 1; }
  .footer-right { display: flex; flex-direction: column; gap: 2px; text-transform: none; text-align: right; font-family: 'Inter', monospace; color: var(--navy-light); }
  
  /* Minimal Footer Styles */
  .footer-minimal { 
    justify-content: space-between !important; 
    align-items: flex-end; /* Align to bottom line text */
    padding-top: 12px; /* Slightly more space */
    border-top: 1px solid rgba(0,0,0,0.05); /* Very subtle border */
  }
  .footer-minimal .footer-left {
    gap: 12px;
  }
  .footer-minimal .footer-logo-small {
    max-height: 18px;
    opacity: 0.6;
    filter: grayscale(100%); /* Elegant grayscale logo */
  }
  .footer-minimal .footer-secret {
    font-size: 7pt;
    color: var(--text-muted);
    text-transform: none;
    letter-spacing: 0px;
    white-space: nowrap;
    display: flex;
    align-items: center;
  }

  .footer-line { font-size: 7pt; color: var(--text-muted); line-height: 1.4; }
  .footer-line-1 { font-weight: 600; color: var(--navy); text-transform: none; font-size: 8pt; }
  .footer-line-2 { color: var(--text-muted); font-size: 7.5pt; font-style: italic; }
  .separator { color: var(--gold); margin: 0 6px; font-weight: 300; opacity: 0.5; }
  
  .footer-meta { font-size: 7.5pt; color: var(--text-muted); font-variant-numeric: tabular-nums; }
  .footer-vertical { flex-direction: row; align-items: center; }
  
  .toc-list { display: grid; grid-template-columns: 1fr; gap: 12px; margin-top: 32px; }
  .toc-item { 
      display: flex; 
      justify-content: space-between; 
      border-bottom: 1px dotted var(--gray-border); 
      padding-bottom: 4px; 
      font-size: 11pt; 
      font-family: 'Cinzel', serif;
      color: var(--navy);
  }
  .toc-item span:last-child { font-family: 'Inter', sans-serif; color: var(--gold); font-weight: 600; }

  .cover-page {
    position: relative;
    width: 210mm;
    height: 297mm;
    background: var(--white);
    overflow: hidden;
    font-family: 'DM Sans', sans-serif;
    display: flex;
    flex-direction: column;
  }

  /* Ultra-Premium Cover Background */
  .cover-accent-bg {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 45%;
    background: var(--navy);
    /* Subtle luxury geometric pattern */
    background-image: 
        radial-gradient(circle at 100% 100%, var(--navy-light) 10%, transparent 10%), 
        radial-gradient(circle at 0% 100%, var(--navy-light) 10%, transparent 10%);
    background-size: 40px 40px;
    border-top: 4px solid var(--gold);
    clip-path: polygon(0 15%, 100% 0%, 100% 100%, 0% 100%);
    z-index: 0;
  }
  
  .cover-accent-bg::after {
      content: "";
      position: absolute;
      top: 0; left: 0; right: 0; bottom: 0;
      background: linear-gradient(135deg, rgba(11, 16, 32, 0.9) 0%, rgba(11, 16, 32, 0.7) 100%);
  }

  .cover-content {
    position: relative;
    z-index: 1;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }

  .cover-header {
    padding: 60px 60px 0;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    height: auto;
    margin-bottom: 40px;
  }

  .brand-logo { 
    height: 56px; 
    object-fit: contain;
  }

  .cover-client-logo {
    text-align: right;
  }

  .client-label {
    font-size: 8pt;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 8px;
    font-weight: 700;
  }
  
  .client-name {
    font-size: 16pt;
    font-weight: 700;
    color: var(--navy);
    font-family: 'Cinzel', serif;
    letter-spacing: -0.5px;
  }

  .cover-hero {
    padding: 0 60px;
    margin-top: 80px;
    flex-grow: 1;
  }

  .hero-title {
    font-family: 'Cinzel', serif;
    font-weight: 700;
    font-size: 42pt;
    line-height: 1.05;
    color: var(--navy);
    text-transform: uppercase;
    margin-bottom: 24px;
    letter-spacing: -1px;
  }
  
  .hero-title span { display: block; }
  
  .hero-subtitle {
      font-family: 'DM Sans', sans-serif;
      font-size: 14pt;
      color: var(--text-muted);
      font-weight: 300;
      letter-spacing: 0.5px;
      margin-bottom: 40px;
      border-left: 3px solid var(--gold);
      padding-left: 20px;
  }

  .highlight-number {
    color: var(--navy); 
    font-weight: 700;
    border-bottom: 2px solid var(--gold-light);
  }

  /* Footer Area - Overlapping the navy background */
  .cover-footer-area {
    padding: 0 60px 48px;
    color: white; /* White text on navy bg */
    margin-top: auto; /* Push to bottom */
    position: relative;
    top: 0; 
  }

  .personalization-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 40px;
    margin-bottom: 40px;
    border-top: 1px solid rgba(255,255,255,0.1);
    padding-top: 30px;
  }

  .pers-col {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .pers-label {
    font-size: 8pt;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: var(--gold); 
    font-weight: 600;
  }

  .pers-value {
    font-size: 11pt;
    font-weight: 400;
    color: rgba(255,255,255,0.9);
    font-family: 'DM Sans', sans-serif;
  }

  .cover-bottom-bar {
    border-top: 1px solid rgba(255,255,255,0.1);
    padding-top: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 8pt;
    color: rgba(255,255,255,0.5);
  }

  .cover-ref {
    opacity: 0.8;
    font-family: 'Inter', monospace;
    letter-spacing: 1px;
  }
  
  .cover-date {
      color: var(--gold);
      font-weight: 600;
  }

  .compare-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
  .compare-card { 
      border: 1px solid var(--gray-border); 
      border-radius: 4px; 
      padding: 20px; 
      background: var(--white); 
      box-shadow: 0 2px 10px rgba(0,0,0,0.03); 
  }
  .compare-card.highlight { 
      background: linear-gradient(180deg, var(--white) 0%, var(--gray-bg) 100%); 
      border: 1px solid var(--gold); 
      position: relative;
      top: -10px; /* Pop out */
      box-shadow: 0 10px 20px rgba(0,0,0,0.08);
  }
  .compare-card.highlight::before {
      content: "RECOMMENDED";
      position: absolute;
      top: -12px;
      left: 50%;
      transform: translateX(-50%);
      background: var(--navy);
      color: var(--gold);
      font-size: 7pt;
      padding: 4px 12px;
      border-radius: 12px;
      font-weight: 700;
      letter-spacing: 1px;
  }
  
  .compare-title { font-size: 12pt; font-weight: 700; color: var(--navy); margin-bottom: 16px; text-transform: uppercase; font-family: 'Cinzel', serif; text-align: center; }
  
  .compare-row { display: flex; justify-content: space-between; gap: 8px; font-size: 9pt; color: var(--text-main); margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px dashed var(--gray-border); }
  .compare-row:last-child { border-bottom: none; }
  .compare-row span { color: var(--text-muted); font-size: 8.5pt; }
  .compare-row strong { color: var(--navy); font-weight: 700; text-align: right; }
  
  .compare-row.compare-total { 
      margin-top: 12px; 
      padding-top: 12px; 
      border-top: 2px solid var(--navy); 
      border-bottom: none;
  }
  .compare-row.compare-total strong { font-size: 11pt; color: var(--gold); }
  
  .compare-table { width: 100%; border-collapse: collapse; font-size: 8pt; margin-top: 20px; }
  .compare-table th, .compare-table td { border: 1px solid var(--gray-border); padding: 10px; vertical-align: middle; }
  .compare-table thead th { background: var(--gray-light); color: var(--navy); font-weight: 700; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.5px; }
  .compare-table tbody td:first-child { width: 34%; color: var(--text-dark); font-weight: 600; background: var(--white); }
  .compare-table tbody td:not(:first-child) { text-align: right; color: var(--navy); font-weight: 600; }
  .compare-table tbody tr.row-total td { background: var(--gray-light); font-weight: 800; border-top: 2px solid var(--navy); }
  
  .chart-wrap { width: 100%; height: 260px; border: 1px solid var(--gray-border); border-radius: 8px; padding: 20px; background: var(--white); box-shadow: 0 4px 6px rgba(0,0,0,0.02); margin-top: 20px; }
  .chart-wrap canvas { width: 100%; height: 100%; }

  /* --- Executive Summary Styles --- */

  .executive-summary-page {
    display: flex;
    flex-direction: column;
    font-family: 'DM Sans', sans-serif; 
  }

  /* Mini Header */
  .summary-mini-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 32px 48px;
    border-bottom: 1px solid var(--gray-border);
    margin-bottom: 32px;
    background: var(--white);
  }

  .mini-logo img {
    height: 28px;
    width: auto;
  }

  .mini-title {
    font-family: 'Cinzel', serif;
    font-size: 10pt;
    color: var(--text-muted);
    letter-spacing: 2px;
    text-transform: uppercase;
    font-weight: 600;
  }

  /* Progress Bar - Simplified & Classy */
  .summary-progress-bar {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 24px;
    margin-bottom: 48px;
    font-size: 8pt;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 1px;
    position: relative;
    z-index: 10;
  }

  .progress-step {
    padding: 6px 12px;
    border-radius: 20px;
    background: var(--gray-light);
    font-weight: 600;
  }

  .progress-step.active {
    color: var(--white);
    background: var(--navy);
    box-shadow: 0 4px 10px rgba(11, 16, 32, 0.2);
  }

  .progress-step.completed {
    color: var(--navy);
    background: var(--gold-light);
  }

  .progress-arrow {
    color: var(--gray-border);
    font-size: 10px;
  }

  /* Content Layout */
  .summary-content {
    padding: 0 48px;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
  }

  /* Summary Cards */
  .summary-card {
    background: var(--white);
    border-radius: 4px; 
    padding: 24px;
    border: 1px solid var(--gray-border);
    display: flex;
    flex-direction: column;
    height: 100%;
    position: relative;
    overflow: hidden;
  }
  
  .summary-card::before {
      content: "";
      position: absolute;
      top: 0; left: 0; width: 100%; height: 3px;
      background: var(--gray-border);
  }
  
  .summary-card.pain-card::before { background: var(--danger); }
  .summary-card.sol-card::before { background: var(--navy); }
  .summary-card.imp-card::before { background: var(--gold); }

  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
  }

  .card-label {
    font-family: 'Cinzel', serif;
    font-size: 10pt;
    font-weight: 700;
    color: var(--navy);
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .card-body {
    font-size: 10pt;
    line-height: 1.7;
    color: var(--text-main);
  }

  .card-icon-pain { color: var(--danger); }
  .highlight-pain { font-weight: 700; color: var(--danger); border-bottom: 1px solid rgba(220, 38, 38, 0.2); }

  .card-icon-solution { color: var(--navy); }
  
  .benefits-section {
     grid-column: span 3;
     margin-top: 24px;
     background: transparent;
     border: none;
     padding: 0;
     box-shadow: none;
  }
  .benefits-section::before { display: none; }

  .benefits-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
  }

  .benefit-item {
    background: var(--white);
    border: 1px solid var(--gray-border);
    border-left: 3px solid var(--gold);
    border-radius: 4px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
  }

  .benefit-icon {
    margin-bottom: 8px;
    color: var(--gold);
  }

  .benefit-value {
    font-size: 20pt;
    font-weight: 700;
    line-height: 1;
    color: var(--navy);
    font-family: 'Cinzel', serif;
  }

  .benefit-desc {
    font-size: 9pt;
    color: var(--text-muted);
    line-height: 1.5;
  }

  .text-green { color: var(--success); }
  .text-navy { color: var(--navy); }
  .text-gold { color: var(--gold); }

  /* Safety Row */
  .benefit-safety-row {
    grid-column: span 3;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    margin-top: 32px;
    padding: 16px;
    background: var(--gray-light);
    border-radius: 40px;
    color: var(--text-muted);
    font-size: 9pt;
    width: fit-content;
    margin-left: auto;
    margin-right: auto;
  }
`;
