/**
 * RUPSA PADUKALAYA - Report Export Utility (CSV & A4 Professional PDF)
 */

export function exportToCsv(filename, columns, data) {
  if (!data || !data.length) {
    alert('No data available to export.');
    return;
  }

  const headers = columns.map(c => `"${c.label.replace(/"/g, '""')}"`).join(',');
  const rows = data.map(row => {
    return columns.map(c => {
      let val = c.field ? row[c.field] : (c.value ? c.value(row) : '');
      if (val === null || val === undefined) val = '';
      val = String(val).replace(/"/g, '""');
      return `"${val}"`;
    }).join(',');
  });

  const csvContent = '\uFEFF' + [headers, ...rows].join('\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  link.setAttribute('download', `${filename}_${new Date().toISOString().slice(0, 10)}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

export function exportToPdf(reportTitle, filterSummary, kpiCards = [], columns = [], data = []) {
  if (!data || !data.length) {
    alert('No report data available to export to PDF.');
    return;
  }

  const generatedAt = new Date().toLocaleString('en-IN', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit', hour12: true
  });

  // Generate Summary Cards HTML
  const kpiHtml = kpiCards.length ? `
    <div class="kpi-grid">
      ${kpiCards.map(kpi => `
        <div class="kpi-card">
          <div class="kpi-title">${kpi.title}</div>
          <div class="kpi-value">${kpi.value}</div>
          ${kpi.subtext ? `<div class="kpi-subtext ${kpi.isLoss ? 'loss' : ''}">${kpi.subtext}</div>` : ''}
        </div>
      `).join('')}
    </div>
  ` : '';

  // Generate Table HTML
  const headersHtml = columns.map(c => `<th style="${c.align ? 'text-align:' + c.align : ''}">${c.label}</th>`).join('');
  
  const rowsHtml = data.map(row => {
    const isLoss = row.is_profit === false || (row.gross_profit < 0) || (row.net_profit < 0);
    return `
      <tr class="${isLoss ? 'row-loss' : ''}">
        ${columns.map(c => {
          let val = c.field ? row[c.field] : (c.value ? c.value(row) : '');
          if (val === null || val === undefined) val = '';
          const align = c.align ? `style="text-align:${c.align}"` : '';
          
          if (c.field === 'is_profit' || c.isStatus) {
            const isProfit = row.is_profit !== false;
            return `<td ${align}><span class="badge ${isProfit ? 'badge-profit' : 'badge-loss'}">${isProfit ? 'PROFIT' : 'LOSS'}</span></td>`;
          }
          
          return `<td ${align}>${val}</td>`;
        }).join('')}
      </tr>
    `;
  }).join('');

  const htmlContent = `
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="utf-8">
      <title>${reportTitle} — RUPSA PADUKALAYA</title>
      <style>
        @page {
          size: A4 portrait;
          margin: 12mm 10mm 15mm 10mm;
        }
        body {
          font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
          color: #0f172a;
          margin: 0;
          padding: 0;
          font-size: 10px;
          line-height: 1.3;
          background: #fff;
        }
        .header {
          display: flex;
          align-items: center;
          justify-content: space-between;
          border-bottom: 2px solid #dc2626;
          padding-bottom: 8px;
          margin-bottom: 12px;
        }
        .brand {
          display: flex;
          align-items: center;
          gap: 10px;
        }
        .logo-box {
          background: #dc2626;
          color: #fff;
          font-weight: 900;
          font-size: 16px;
          width: 32px;
          height: 32px;
          border-radius: 8px;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        .brand-title {
          font-size: 14px;
          font-weight: 900;
          letter-spacing: 0.5px;
          color: #0f172a;
          margin: 0;
        }
        .brand-sub {
          font-size: 8px;
          font-weight: 800;
          color: #dc2626;
          letter-spacing: 1px;
          margin-top: 1px;
        }
        .meta-info {
          text-align: right;
          font-size: 9px;
          color: #64748b;
        }
        .report-title-bar {
          background: #0f172a;
          color: #fff;
          padding: 8px 12px;
          border-radius: 8px;
          display: flex;
          align-items: center;
          justify-content: space-between;
          margin-bottom: 12px;
        }
        .report-title {
          font-size: 12px;
          font-weight: 900;
          letter-spacing: 0.5px;
          margin: 0;
          text-transform: uppercase;
        }
        .filter-badge {
          background: rgba(255,255,255,0.15);
          color: #fff;
          padding: 3px 8px;
          border-radius: 4px;
          font-size: 8px;
          font-weight: 800;
        }
        .kpi-grid {
          display: grid;
          grid-template-columns: repeat(4, 1fr);
          gap: 8px;
          margin-bottom: 12px;
        }
        .kpi-card {
          border: 1px solid #e2e8f0;
          border-radius: 8px;
          padding: 8px;
          background: #f8fafc;
        }
        .kpi-title {
          font-size: 8px;
          font-weight: 800;
          color: #64748b;
          text-transform: uppercase;
        }
        .kpi-value {
          font-size: 13px;
          font-weight: 900;
          color: #0f172a;
          margin-top: 2px;
          font-family: monospace;
        }
        .kpi-subtext {
          font-size: 7px;
          color: #059669;
          font-weight: 800;
          margin-top: 2px;
        }
        .kpi-subtext.loss {
          color: #dc2626;
        }
        table {
          width: 100%;
          border-collapse: collapse;
          margin-top: 5px;
          font-size: 9px;
        }
        thead {
          display: table-header-group;
        }
        tr {
          page-break-inside: avoid;
        }
        th {
          background: #1e293b;
          color: #fff;
          font-weight: 800;
          font-size: 8px;
          text-transform: uppercase;
          padding: 6px 8px;
          border: 1px solid #1e293b;
        }
        td {
          padding: 6px 8px;
          border-bottom: 1px solid #e2e8f0;
          color: #334155;
        }
        tr:nth-child(even) td {
          background: #f8fafc;
        }
        tr.row-loss td {
          background: #fef2f2;
        }
        .badge {
          display: inline-block;
          padding: 2px 6px;
          border-radius: 4px;
          font-size: 8px;
          font-weight: 900;
          text-transform: uppercase;
        }
        .badge-profit {
          background: #dcfce7;
          color: #15803d;
        }
        .badge-loss {
          background: #fee2e2;
          color: #b91c1c;
        }
        .footer {
          margin-top: 20px;
          border-top: 1px solid #e2e8f0;
          padding-top: 8px;
          display: flex;
          align-items: center;
          justify-content: space-between;
          font-size: 8px;
          color: #94a3b8;
        }
      </style>
    </head>
    <body>
      <div class="header">
        <div class="brand">
          <div class="logo-box">R</div>
          <div>
            <h1 class="brand-title">RUPSA PADUKALAYA</h1>
            <div class="brand-sub">ERP FINANCIAL REPORTING SYSTEM</div>
          </div>
        </div>
        <div class="meta-info">
          <div><strong>Generated:</strong> ${generatedAt}</div>
          <div><strong>Report System:</strong> RUPSA ERP v1.0</div>
        </div>
      </div>

      <div class="report-title-bar">
        <h2 class="report-title">${reportTitle}</h2>
        <div class="filter-badge">${filterSummary || 'All Time / All Stores'}</div>
      </div>

      ${kpiHtml}

      <table>
        <thead>
          <tr>${headersHtml}</tr>
        </thead>
        <tbody>
          ${rowsHtml}
        </tbody>
      </table>

      <div class="footer">
        <div>RUPSA PADUKALAYA • Official Business Report</div>
        <div>Page 1 of 1</div>
      </div>

      <script>
        window.onload = function() {
          window.print();
        };
      </script>
    </body>
    </html>
  `;

  const printWindow = window.open('', '_blank');
  if (printWindow) {
    printWindow.document.write(htmlContent);
    printWindow.document.close();
  } else {
    alert('Please allow popups to open the PDF report window.');
  }
}
