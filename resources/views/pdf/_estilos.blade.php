<style>
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body {
        font-family: Montserrat, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        color: #0A0A0A;
        font-size: 11.5px;
        line-height: 1.5;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .encabezado {
        background: linear-gradient(135deg, #73C83A 0%, #64CBD4 100%);
        border-radius: 16px;
        padding: 20px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }
    .encabezado img { height: 46px; width: auto; display: block; }
    .encabezado .marca { font-size: 22px; font-weight: 800; }
    .encabezado .doc { text-align: right; }
    .encabezado .doc .tipo { font-size: 11px; text-transform: uppercase; letter-spacing: 2px; font-weight: 700; color: #14332B; }
    .encabezado .doc .folio { font-size: 22px; font-weight: 800; letter-spacing: .5px; }
    .encabezado .doc .fecha { font-size: 11px; color: #14332B; }
    .fila { display: flex; gap: 14px; margin-top: 16px; }
    .fila > .caja { flex: 1; }
    .caja { border: 1px solid #E3E1DE; border-radius: 12px; padding: 12px 14px; page-break-inside: avoid; }
    .caja h3 { margin: 0 0 6px; font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; color: #3F8A1C; }
    .caja p { margin: 0; }
    .muted { color: #6B7280; }
    .chip { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 10px; font-weight: 700; background: #F3F2EF; color: #14332B; }
    .chip.success { background: #EEF8E7; color: #2F6B12; }
    .chip.warning { background: #FFF7E6; color: #7A4A00; }
    .chip.info, .chip.brand { background: #E6F7F9; color: #0E5960; }
    .chip.danger { background: #FDECEC; color: #7F1D1D; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 16px; }
    table.items th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #FFFFFF; background: #14332B; padding: 8px 10px; }
    table.items th:first-child { border-top-left-radius: 8px; }
    table.items th:last-child { border-top-right-radius: 8px; }
    table.items td { padding: 8px 10px; border-bottom: 1px solid #EEECE9; vertical-align: top; }
    table.items tr { page-break-inside: avoid; }
    .num { text-align: right; white-space: nowrap; }
    .totales { margin-left: auto; margin-top: 12px; width: 280px; }
    .totales div { display: flex; justify-content: space-between; padding: 3px 0; }
    .totales .total { border-top: 2px solid #0A0A0A; margin-top: 4px; padding-top: 6px; font-size: 15px; font-weight: 800; }
    .aviso { margin-top: 16px; border-radius: 12px; padding: 12px 14px; border-left: 4px solid #64CBD4; background: #E6F7F9; color: #0E5960; page-break-inside: avoid; }
    .aviso.warning { border-color: #F2B544; background: #FFF7E6; color: #7A4A00; }
    .aviso strong { display: block; margin-bottom: 2px; }
    .banco { display: grid; grid-template-columns: 140px 1fr; gap: 3px 10px; margin-top: 6px; }
    .mono { font-family: 'Courier New', Courier, monospace; letter-spacing: 1px; }
    .pie { margin-top: 22px; padding-top: 10px; border-top: 1px solid #E3E1DE; font-size: 10px; color: #6B7280; display: flex; justify-content: space-between; gap: 12px; }
</style>
