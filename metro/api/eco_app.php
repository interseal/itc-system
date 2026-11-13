<?php
// ==== ИТК · ЭКОСИСТЕМА · eco_app.php · 4.10.9 (30.10.2026) ====
// Само приложение Экосистемы отдаётся ТОЛЬКО сотруднику с действующим пропуском (authUser('itc')).
// Раньше /eco/index.html открывался кому угодно: расчёты, ставки и наценки были видны без входа.
// Теперь /eco/index.html — только проверка входа, а программа приходит отсюда после проверки пропуска.
require_once __DIR__ . '/config.php';
authUser('itc');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Экосистема ИТК</title>
  <!-- манифест не подключаем: Экосистема живёт внутри приложения ИТК -->
  <meta name="theme-color" content="#112C93">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="ИТК">
  <link rel="apple-touch-icon" href="/eco/icon-192.png">
  <style>
:root {
    --navy: #112C93;
    --navy-light: #1F3FB0;
    --navy-dark: #0B1F6B;
    --green: #128A04;
    --green-dark: #0B6603;
    --green-light: #4DD637;
    --white: #FFFFFF;
    --blue: #1565C0;
    --ink: #1A1A1A;
    --paper: #FAFCFE;
    --line: #B8C4CC;
    --amber: #B8860B;
    --amber-bg: #FFF8E7;
    --red: #A11A1A;
    --red-bg: #FDECEC;
  }

  * { box-sizing: border-box; }

  body {
    margin: 0;
    font-family: 'Times New Roman', Times, serif;
    background: #E8ECF2;
    color: var(--ink);
    -webkit-font-smoothing: antialiased;
  }

  .sans {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  }

  header {
    background: var(--navy);
    color: #fff;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    border-bottom: 4px solid var(--green);
    position: sticky;
    top: 0;
    z-index: 50;
  }

  header img {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    background: #fff;
    padding: 4px;
    flex-shrink: 0;
  }

  header .title-block { flex: 1; min-width: 0; }

  header h1 {
    margin: 0;
    font-size: 19px;
    font-weight: 700;
    letter-spacing: 0.3px;
  }

  header .sub {
    margin: 2px 0 0;
    font-size: 12px;
    color: #C9D4F2;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  }

  .container {
    max-width: 920px;
    margin: 0 auto;
    padding: 18px 14px 60px;
  }

  .search-panel {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 18px;
    box-shadow: 0 2px 10px rgba(17,44,147,0.08);
    margin-bottom: 18px;
  }

  .selected-panel {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    border-radius: 10px;
    padding: 18px 20px;
    margin-bottom: 18px;
    box-shadow: 0 3px 14px rgba(17,44,147,0.25);
    display: none;
  }

  .selected-panel.visible {
    display: block;
    animation: selectedPop 0.2s ease-out;
  }

  @keyframes selectedPop {
    from { transform: scale(0.97); opacity: 0.5; }
    to { transform: scale(1); opacity: 1; }
  }

  .selected-label {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 11.5px;
    font-weight: 700;
    color: #C9D4F2;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 10px;
  }

  .selected-main {
    display: flex;
    align-items: stretch;
    gap: 16px;
    flex-wrap: wrap;
  }

  .selected-code-block {
    flex: 1 1 auto;
    min-width: 160px;
  }

  .selected-code-block .code-num {
    font-family: 'Courier New', Courier, monospace;
    font-size: 28px;
    font-weight: 700;
    color: #fff;
    letter-spacing: 1px;
    line-height: 1.1;
    display: block;
  }

  .selected-duty-block {
    flex: 0 0 auto;
    background: rgba(255,255,255,0.14);
    border: 2px solid rgba(255,255,255,0.35);
    border-radius: 10px;
    padding: 8px 18px;
    text-align: center;
    min-width: 110px;
  }

  .selected-duty-block .duty-label {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 10px;
    font-weight: 700;
    color: #C9D4F2;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .selected-duty-block .duty-value {
    font-size: 26px;
    font-weight: 700;
    color: #fff;
    line-height: 1.2;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  }

  .selected-name {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 12.5px;
    color: #D7DFF4;
    margin-top: 10px;
    line-height: 1.5;
  }

  .selected-actions {
    margin-top: 12px;
    display: flex;
    gap: 8px;
  }

  .selected-copy-btn {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 12px;
    font-weight: 600;
    background: #fff;
    border: none;
    border-radius: 6px;
    padding: 7px 14px;
    color: var(--navy);
    cursor: pointer;
  }

  .selected-copy-btn.copied { background: var(--green); color: #fff; }

  .selected-close-btn {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 12px;
    font-weight: 600;
    background: transparent;
    border: 1px solid rgba(255,255,255,0.4);
    border-radius: 6px;
    padding: 7px 14px;
    color: #fff;
    cursor: pointer;
  }

  .search-label {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 13px;
    font-weight: 600;
    color: var(--navy);
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-bottom: 8px;
    display: block;
  }

  #searchInput {
    width: 100%;
    font-family: 'Times New Roman', Times, serif;
    font-size: 19px;
    padding: 13px 14px;
    border: 2px solid var(--line);
    border-radius: 8px;
    background: var(--paper);
    color: var(--ink);
    outline: none;
    transition: border-color 0.15s;
  }

  #searchInput:focus {
    border-color: var(--navy);
  }

  .hint {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 12.5px;
    color: #5A6B7A;
    margin-top: 8px;
    line-height: 1.5;
  }

  .status-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 13px;
    color: #4A5A68;
    padding: 0 2px;
  }

  .badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
  }

  .badge-ok { background: #E4F4E2; color: var(--green-dark); }
  .badge-many { background: var(--amber-bg); color: var(--amber); }
  .badge-none { background: var(--red-bg); color: var(--red); }

  .result-card {
    background: #fff;
    border: 1px solid var(--line);
    border-left: 5px solid var(--navy);
    border-radius: 8px;
    padding: 14px 16px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: box-shadow 0.15s, transform 0.1s;
  }

  .result-card:hover {
    box-shadow: 0 3px 14px rgba(17,44,147,0.14);
  }

  .result-card:active {
    transform: scale(0.997);
  }

  .result-card.expanded {
    border-left-color: var(--green);
  }

  .code-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 6px;
  }

  .code-num {
    font-family: 'Courier New', Courier, monospace;
    font-size: 18px;
    font-weight: 700;
    color: var(--navy);
    letter-spacing: 0.5px;
  }

  .copy-btn {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 11px;
    font-weight: 600;
    background: var(--paper);
    border: 1px solid var(--line);
    border-radius: 6px;
    padding: 4px 10px;
    color: var(--navy);
    cursor: pointer;
    flex-shrink: 0;
  }

  .copy-btn:hover { background: #EEF2FA; }
  .copy-btn.copied { background: var(--green); color: #fff; border-color: var(--green); }

  .tariff-pill {
    display: inline-block;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 11.5px;
    font-weight: 700;
    background: #EAF6E8;
    color: var(--green-dark);
    padding: 2px 9px;
    border-radius: 12px;
    margin-left: 8px;
  }

  .name-text {
    font-size: 16px;
    line-height: 1.5;
    color: var(--ink);
  }

  .name-text .hl {
    background: #FFF0A8;
    font-weight: 700;
    border-radius: 2px;
  }

  .breadcrumb {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 12px;
    color: #8492A0;
    margin-top: 4px;
    line-height: 1.4;
  }

  .empty-state, .start-state {
    text-align: center;
    padding: 50px 20px;
    color: #6A7A88;
  }

  .empty-state .icon, .start-state .icon {
    font-size: 44px;
    margin-bottom: 14px;
    opacity: 0.6;
  }

  .empty-state h3, .start-state h3 {
    margin: 0 0 8px;
    color: var(--navy);
    font-size: 19px;
  }

  .empty-state p, .start-state p {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 13.5px;
    line-height: 1.6;
    max-width: 420px;
    margin: 0 auto;
  }

  .suggestions {
    margin-top: 18px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: center;
  }

  .suggestion-chip {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 12.5px;
    background: #fff;
    border: 1px solid var(--line);
    color: var(--navy);
    padding: 6px 14px;
    border-radius: 20px;
    cursor: pointer;
  }

  .suggestion-chip:hover {
    background: var(--navy);
    color: #fff;
  }

  footer {
    text-align: center;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 11.5px;
    color: #8492A0;
    padding: 20px 14px 30px;
  }

  .loading-bar {
    text-align: center;
    padding: 60px 20px;
    color: var(--navy);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  }

  @media (max-width: 480px) {
    header h1 { font-size: 16px; }
    .code-num { font-size: 16px; }
    .name-text { font-size: 14.5px; }
  }

/* ===== Доп. стили Честного знака ===== */
  header .logo-badge {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    background: #fff;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }
  header .logo-badge img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
  }
  .disclaimer {
    background: #fff;
    border: 1px solid var(--line);
    border-left: 4px solid var(--amber);
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 16px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 12.5px;
    line-height: 1.55;
    color: #4A3B0E;
  }
  .disclaimer a { color: var(--navy); font-weight: 600; }
  .selected-panel.s-active   { background: linear-gradient(135deg, var(--green-dark) 0%, var(--green) 100%); }
  .selected-panel.s-partial  { background: linear-gradient(135deg, #8a5e02 0%, #b8860b 100%); }
  .selected-panel.s-upcoming { background: linear-gradient(135deg, #144a8a 0%, var(--blue) 100%); }
  .selected-panel.s-not      { background: linear-gradient(135deg, #4A5A68 0%, #6A7A88 100%); }
  .selected-verdict-block { flex: 1 1 auto; min-width: 200px; }
  .selected-verdict-block .verdict-text {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 24px;
    font-weight: 800;
    color: #fff;
    line-height: 1.15;
    display: block;
  }
  .selected-cat-name {
    font-size: 20px;
    font-weight: 700;
    color: #fff;
    margin-top: 4px;
    line-height: 1.35;
  }
  .selected-meta {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 12.5px;
    color: rgba(255,255,255,0.92);
    margin-top: 12px;
    line-height: 1.6;
  }
  .selected-meta b { color: #fff; }
  .selected-note {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 12.5px;
    color: rgba(255,255,255,0.92);
    margin-top: 8px;
    line-height: 1.6;
    background: rgba(255,255,255,0.13);
    border-radius: 7px;
    padding: 9px 11px;
  }
  .selected-link-btn {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 12px;
    font-weight: 600;
    background: #fff;
    border: none;
    border-radius: 6px;
    padding: 7px 14px;
    color: var(--navy);
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
  }
  .pill-active   { background: #E4F4E2; color: var(--green-dark); }
  .pill-partial  { background: var(--amber-bg); color: var(--amber); }
  .pill-upcoming { background: var(--blue-bg); color: var(--blue); }
  .pill-not      { background: var(--gray-bg); color: var(--gray); }
  .result-card.st-active   { border-left-color: var(--green-dark); }
  .result-card.st-partial  { border-left-color: var(--amber); }
  .result-card.st-upcoming { border-left-color: var(--blue); }
  .result-card.st-not      { border-left-color: var(--gray); }
  .card-top-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 6px;
    flex-wrap: wrap;
  }
  .card-note {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 12.5px;
    color: #5A6B7A;
    margin-top: 5px;
    line-height: 1.5;
  }
  .card-since {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 11.5px;
    color: #8492A0;
    margin-top: 4px;
  }
  .empty-actions {
    margin-top: 16px;
    display: flex;
    justify-content: center;
  }
  .empty-link-btn {
    font-size: 12.5px;
    font-weight: 600;
    background: var(--navy);
    color: #fff;
    border-radius: 7px;
    padding: 9px 16px;
    text-decoration: none;
  }
  footer.app-footer {
    text-align: center;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 11.5px;
    color: #8492A0;
    padding: 10px 20px 30px;
    line-height: 1.6;
  }
  footer.app-footer a { color: #5A6B7A; }

/* ===== Доп. стили Калькулятора ===== */
  .header-inner {
    max-width: 1180px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
  }
  .brand {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .brand-logo {
    height: 38px;
    width: auto;
    background: var(--white);
    padding: 3px 6px;
    border-radius: 3px;
  }
  .brand-mark {
    font-family: 'Georgia', serif;
    font-weight: 700;
    font-size: 22px;
    letter-spacing: 0.04em;
  }
  .brand-mark .accent { color: var(--green-light); }
  .brand-sub {
    font-family: -apple-system, sans-serif;
    font-size: 11px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #9FB0C0;
  }
  .confidential {
    font-family: -apple-system, sans-serif;
    font-size: 11px;
    letter-spacing: 0.06em;
    color: var(--green-light);
    border: 1px solid rgba(47,174,28,0.5);
    padding: 4px 10px;
    border-radius: 2px;
  }
  .wrap {
    max-width: 1180px;
    margin: 0 auto;
    padding: 28px 24px 60px;
  }
  .grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    align-items: start;
  }
  @media (max-width: 900px) {
    .grid { grid-template-columns: 1fr; }
  }
  .panel {
    background: var(--white);
    border: 1px solid var(--line);
    border-radius: 4px;
  }
  .panel-head {
    font-family: -apple-system, sans-serif;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--white);
    background: var(--navy);
    padding: 10px 16px;
    border-radius: 4px 4px 0 0;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .panel-head.gold { background: var(--green); color: var(--white); }
  .panel-head.green { background: var(--green); }
  .panel-head.amber { background: var(--amber); }
  .panel-head.red { background: var(--red); }
  .panel-head.navy2 { background: var(--navy-light); }
  .panel-body { padding: 16px; }
  .cat-select-wrap {
    padding: 18px 16px;
    background: linear-gradient(135deg, #FFFCF4, #FBF1DD);
  }
  .cat-label {
    font-family: -apple-system, sans-serif;
    font-size: 11px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--ink-soft);
    margin-bottom: 8px;
  }
  select#category {
    width: 100%;
    font-family: -apple-system, sans-serif;
    font-size: 20px;
    font-weight: 700;
    padding: 12px 14px;
    border: 2px solid var(--green);
    border-radius: 4px;
    background: var(--white) url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="14" height="9"><path d="M1 1l6 6 6-6" stroke="%23946200" stroke-width="2" fill="none"/></svg>') no-repeat right 16px center;
    appearance: none;
    color: var(--navy);
    cursor: pointer;
  }
  select#category:focus { outline: 3px solid rgba(183,134,11,0.3); }
  .cat-desc {
    margin-top: 10px;
    font-family: -apple-system, sans-serif;
    font-size: 13px;
    color: var(--ink-soft);
    font-style: italic;
  }
  .cat-formula {
    margin-top: 8px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    color: var(--navy);
    background: rgba(255,255,255,0.6);
    padding: 6px 10px;
    border-radius: 3px;
    border: 1px dashed var(--green);
    display: inline-block;
  }
  .field-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 9px 0;
    border-bottom: 1px solid #EFEAE0;
    gap: 12px;
  }
  .field-row:last-child { border-bottom: none; }
  .field-label {
    font-family: -apple-system, sans-serif;
    font-size: 13px;
    color: var(--ink-soft);
    flex: 1;
  }
  .field-input-group {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
  }
  input[type=number] {
    width: 110px;
    font-family: 'Courier New', monospace;
    font-size: 15px;
    font-weight: 700;
    text-align: right;
    padding: 6px 8px;
    border: 1px solid #B8C4CC;
    border-radius: 3px;
    background: #FAFCFE;
    color: var(--navy);
  }
  input[type=number]:focus {
    outline: none;
    border-color: var(--navy);
    background: var(--white);
  }
  .unit {
    font-family: -apple-system, sans-serif;
    font-size: 11px;
    color: var(--ink-soft);
    width: 34px;
  }
  .res-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #EFEAE0;
    gap: 12px;
  }
  .res-row:last-child { border-bottom: none; }
  .res-label {
    font-family: -apple-system, sans-serif;
    font-size: 12.5px;
    color: var(--ink-soft);
    flex: 1;
  }
  .res-val {
    font-family: 'Courier New', monospace;
    font-size: 14px;
    font-weight: 700;
    color: var(--navy);
    white-space: nowrap;
  }
  .res-row.total {
    margin-top: 6px;
    padding: 12px 10px;
    background: var(--navy);
    border-radius: 4px;
    border-bottom: none;
  }
  .res-row.total .res-label { color: #C8D4DE; font-size: 13px; }
  .res-row.total .res-val { color: var(--green-light); font-size: 18px; }
  .res-row.sub-total {
    padding: 9px 10px;
    background: var(--green-bg);
    border-radius: 3px;
    border-bottom: none;
    margin-top: 4px;
  }
  .res-row.sub-total .res-val { color: var(--green); font-size: 15px; }
  .final-card {
    background: var(--navy);
    border-radius: 6px;
    padding: 22px 24px;
    color: var(--white);
    box-shadow: 0 8px 24px rgba(15,42,71,0.25);
  }
  .final-card .eyebrow {
    font-family: -apple-system, sans-serif;
    font-size: 11px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--green-light);
    margin-bottom: 10px;
  }
  .final-numbers {
    display: flex;
    gap: 28px;
    flex-wrap: wrap;
    align-items: baseline;
  }
  .final-num-block .num {
    font-family: 'Courier New', monospace;
    font-size: 32px;
    font-weight: 700;
    color: var(--white);
  }
  .final-num-block .lbl {
    font-family: -apple-system, sans-serif;
    font-size: 11px;
    color: #9FB0C0;
    margin-top: 2px;
  }
  .final-num-block .num.gold { color: var(--green-light); }
  .special-note {
    background: var(--amber-bg);
    border: 1px solid var(--green);
    border-radius: 4px;
    padding: 16px;
    font-family: -apple-system, sans-serif;
    font-size: 13px;
    color: var(--amber);
    line-height: 1.6;
  }
  .special-note b { color: var(--navy); }
  .ref-table {
    width: 100%;
    border-collapse: collapse;
    font-family: -apple-system, sans-serif;
    font-size: 12.5px;
  }
  .ref-table th {
    background: var(--navy-light);
    color: var(--white);
    padding: 8px 10px;
    text-align: left;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }
  .ref-table td {
    padding: 8px 10px;
    border-bottom: 1px solid #EFEAE0;
    color: var(--ink);
  }
  .ref-table tr:hover td { background: #FAF8F2; }
  .ref-table tr.active-row td { background: var(--amber-bg); }
  .cat-pill {
    display: inline-block;
    font-family: 'Courier New', monospace;
    font-weight: 700;
    font-size: 11px;
    padding: 2px 7px;
    border-radius: 3px;
    background: var(--navy);
    color: var(--white);
  }
  .footnote {
    margin-top: 28px;
    padding-top: 16px;
    border-top: 1px solid var(--line);
    font-family: -apple-system, sans-serif;
    font-size: 11px;
    color: var(--ink-soft);
    text-align: center;
    line-height: 1.7;
  }
  .toggle-tabs {
    display: flex;
    gap: 2px;
    margin-bottom: 16px;
  }
  .toggle-tab {
    flex: 1;
    text-align: center;
    padding: 9px;
    font-family: -apple-system, sans-serif;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.04em;
    cursor: pointer;
    background: #EAE5D8;
    color: var(--ink-soft);
    border-radius: 3px;
    transition: all 0.15s;
    user-select: none;
  }
  .toggle-tab.active {
    background: var(--navy);
    color: var(--white);
  }
  .hidden { display: none !important; }

/* ===== ХАБ ===== */
#hub {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #f4f6fb;
  padding: 2rem 1rem;
}
.hub-logo img {
    background: transparent;
    height: 120px;
    display: block;
    margin: 0 auto 2rem;
  }


/* ===== ХАБ — белый чистый ===== */
  #hub {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    background: #f4f6fb;
    padding: 0 0 24px;
  }
  .hub-logo-wrap {
    margin: 36px auto 12px;
    width: 110px;
    height: 110px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .hub-logo-wrap img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    filter: drop-shadow(0 4px 12px rgba(17,44,147,0.18));
  }
  .hub-title-new {
    font-family: 'Times New Roman', Times, serif;
    font-size: 1.9rem;
    font-weight: 700;
    color: var(--navy);
    text-align: center;
    margin-bottom: 4px;
  }
  .hub-title-new span {
    color: var(--green);
  }
  .hub-sub-new {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.78rem;
    color: #8a9bbf;
    text-align: center;
    margin-bottom: 4px;
    letter-spacing: 0.04em;
  }

  /* Секции кнопок */
  .hub-section-label {
    width: 100%;
    max-width: 420px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #8a9bbf;
    padding: 20px 20px 8px;
  }
  .hub-cards {
    width: 100%;
    max-width: 420px;
    padding: 0 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  /* Крупные карточки — расчёты */
  .hub-card-big {
    border-radius: 18px;
    padding: 20px 20px;
    cursor: pointer;
    border: none;
    text-align: left;
    width: 100%;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 6px 24px rgba(17,44,147,0.18);
    transition: transform 0.15s, box-shadow 0.15s;
    position: relative;
    overflow: hidden;
  }
  .hub-card-big:active { transform: scale(0.97); }
  .hub-card-big::after {
    content: '›';
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1.6rem;
    opacity: 0.4;
    color: #fff;
  }
  .hub-card-navy {
    background: linear-gradient(135deg, #112C93 0%, #1a3fbf 100%);
  }
  .hub-card-green {
    background: linear-gradient(135deg, #0B6603 0%, #128A04 100%);
  }
  .hub-card-big-icon {
    width: 54px;
    height: 54px;
    border-radius: 14px;
    background: rgba(255,255,255,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.7rem;
    flex-shrink: 0;
  }
  .hub-card-big-label {
    font-family: 'Times New Roman', Times, serif;
    font-size: 1.15rem;
    font-weight: 700;
    color: #fff;
    display: block;
    line-height: 1.2;
  }
  .hub-card-big-desc {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.75rem;
    color: rgba(255,255,255,0.65);
    display: block;
    margin-top: 4px;
    line-height: 1.35;
    padding-right: 20px;
  }


  /* 4.9.3: плитки главного экрана — компактные, по две в ряд, разного цвета */
  .hub-cards { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
  .hub-cards .hub-card-big { flex-direction:column; align-items:flex-start; gap:8px; padding:14px 13px 13px; border-radius:14px; box-shadow:0 3px 12px rgba(17,44,147,.14); min-height:0; }
  .hub-cards .hub-card-big::after { display:none; }
  .hub-cards .hub-card-big-icon { width:38px; height:38px; border-radius:10px; }
  .hub-cards .hub-card-big-icon svg { width:22px !important; height:22px !important; }
  .hub-cards .hub-card-big-label { font-size:.95rem; }
  .hub-cards .hub-card-big-desc { font-size:.68rem; padding-right:0; margin-top:3px; color:rgba(255,255,255,.75); }
  .t-navy   { background:linear-gradient(135deg,#112C93,#1a3fbf); }
  .t-blue   { background:linear-gradient(135deg,#1565c0,#1e88e5); }
  .t-teal   { background:linear-gradient(135deg,#00695c,#00897b); }
  .t-green  { background:linear-gradient(135deg,#0B6603,#128A04); }
  .t-violet { background:linear-gradient(135deg,#4527a0,#5e35b1); }
  .t-amber  { background:linear-gradient(135deg,#a86a00,#c98a0b); }
  .t-slate  { background:linear-gradient(135deg,#37474f,#546e7a); }
  /* Компактная сетка — классификаторы */
  .hub-grid {
    width: 100%;
    max-width: 420px;
    padding: 0 16px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }
  .hub-grid-last {
    grid-column: 1 / -1; /* последняя кнопка — на всю ширину */
  }
  .hub-card-sm {
    background: #fff;
    border-radius: 14px;
    padding: 16px 14px;
    cursor: pointer;
    border: none;
    text-align: left;
    display: flex;
    flex-direction: column;
    gap: 8px;
    box-shadow: 0 2px 10px rgba(17,44,147,0.08);
    transition: transform 0.15s, box-shadow 0.15s;
    border-bottom: 3px solid transparent;
  }
  .hub-card-sm:active { transform: scale(0.96); }
  .hub-card-sm-navy  { border-bottom-color: var(--navy); }
  .hub-card-sm-green { border-bottom-color: var(--green); }
  .hub-card-sm-amber { border-bottom-color: #B8860B; }
  .hub-card-sm-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
  }
  .hub-card-sm-navy  .hub-card-sm-icon { background: #eef1fb; }
  .hub-card-sm-green .hub-card-sm-icon { background: #e4f4e2; }
  .hub-card-sm-amber .hub-card-sm-icon { background: #fff8e7; }
  .hub-card-sm-label {
    font-family: 'Times New Roman', Times, serif;
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--navy);
    line-height: 1.2;
    display: block;
  }
  .hub-card-sm-desc {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.68rem;
    color: #8a9bbf;
    display: block;
    line-height: 1.3;
  }
.hub-btn-icon {
  font-size: 1.9rem;
  line-height: 1;
  color: var(--navy);
  flex-shrink: 0;
  transition: color 0.18s;
}
.hub-btn-label {
  display: block;
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--navy);
  font-family: "Times New Roman", Times, serif;
  transition: color 0.18s;
}
.hub-btn-desc {
  display: block;
  font-size: 0.76rem;
  color: #7a8aaa;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  margin-top: 0.2rem;
  transition: color 0.18s;
}
.section-topbar {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  padding: 0.55rem 1rem;
  background: var(--navy);
  position: sticky;
  top: 0;
  z-index: 300;
}
.back-btn {
  background: rgba(255,255,255,0.13);
  border: 1px solid rgba(255,255,255,0.4);
  color: #fff;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  font-size: 0.82rem;
  font-weight: 600;
  padding: 0.3rem 0.85rem;
  border-radius: 7px;
  cursor: pointer;
  transition: background 0.15s;
  white-space: nowrap;
}
.back-btn:hover { background: rgba(255,255,255,0.25); }
.section-topbar-title {
  color: #fff;
  font-family: "Times New Roman", Times, serif;
  font-size: 1rem;
  font-weight: 700;
}
.app-section { display: none; }
.hidden { display: none !important; }

  /* ===== Единый брендовый блок секций ===== */
  .brand-bar {
    display: flex;
    align-items: center;
    gap: 14px;
    background: #fff;
    padding: 14px 16px;
    border-bottom: 3px solid var(--green);
    margin-bottom: 0;
  }
  .brand-bar-logo {
    width: 52px;
    height: 52px;
    border-radius: 10px;
    overflow: hidden;
    flex-shrink: 0;
    border: 1px solid #e0e6f0;
    background: #fff;
  }
  .brand-bar-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
  }
  .brand-bar-title {
    font-family: 'Times New Roman', Times, serif;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--navy);
    line-height: 1.2;
  }
  .brand-bar-sub {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.75rem;
    color: #6b7a99;
    margin-top: 3px;
  }

  /* ===== Параллельный импорт ===== */
  .pi-disclaimer {
    background: #FFF8E7;
    border: 1px solid #D4A82A;
    border-left: 4px solid #D4A82A;
    border-radius: 8px;
    padding: 12px 14px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 12.5px;
    color: #5A4000;
    margin-bottom: 16px;
    line-height: 1.5;
  }
  .pi-start {
    text-align: center;
    padding: 32px 16px;
  }
  .pi-start-icon { font-size: 3rem; margin-bottom: 12px; }
  .pi-start-title {
    font-family: 'Times New Roman', Times, serif;
    font-size: 1.4rem;
    color: var(--navy);
    margin: 0 0 8px;
  }
  .pi-start-sub {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.85rem;
    color: #6b7a99;
    margin: 0 0 24px;
    line-height: 1.5;
  }
  .pi-stats-row {
    display: flex;
    justify-content: center;
    gap: 24px;
    flex-wrap: wrap;
  }
  .pi-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
  }
  .pi-stat-num {
    font-family: 'Courier New', monospace;
    font-size: 1.6rem;
    font-weight: 700;
  }
  .pi-stat-lbl {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.72rem;
    color: #6b7a99;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }
  .pi-yes-color { color: var(--green); }
  .pi-no-color  { color: #A11A1A; }
  .pi-partial-color { color: #B8860B; }
  .pi-result-count {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.8rem;
    color: #6b7a99;
    margin-bottom: 10px;
  }
  .pi-card {
    background: #fff;
    border-radius: 10px;
    border: 1px solid #dde3ee;
    border-left: 5px solid #dde3ee;
    padding: 14px 14px;
    margin-bottom: 10px;
  }
  .pi-card-yes     { border-left-color: var(--green); }
  .pi-card-no      { border-left-color: #A11A1A; }
  .pi-card-partial { border-left-color: #B8860B; }
  .pi-card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
  }
  .pi-card-icon { font-size: 1.2rem; flex-shrink: 0; }
  .pi-card-brand {
    font-family: 'Times New Roman', Times, serif;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--navy);
  }
  .pi-card-brand mark {
    background: #FFE066;
    border-radius: 2px;
    padding: 0 1px;
    color: inherit;
  }
  .pi-card-status {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 4px;
  }
  .pi-card-yes .pi-card-status     { color: var(--green); }
  .pi-card-no .pi-card-status      { color: #A11A1A; }
  .pi-card-partial .pi-card-status { color: #B8860B; }
  .pi-card-cat {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.75rem;
    color: #6b7a99;
    margin-bottom: 3px;
  }
  .pi-card-note {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.8rem;
    color: #444;
    line-height: 1.4;
  }
  .pi-empty-msg {
    text-align: center;
    padding: 32px 16px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.88rem;
    color: #6b7a99;
    line-height: 1.6;
  }

  /* ===== Проверка в ТРОИС ===== */
  .troix-block {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(17,44,147,0.08);
    padding: 18px;
    margin-top: 22px;
  }
  .troix-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 4px;
  }
  .troix-head-icon {
    font-size: 1.3rem;
    flex-shrink: 0;
  }
  .troix-title {
    font-family: 'Times New Roman', Times, serif;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--navy);
  }
  .troix-sub {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.8rem;
    color: #6b7a99;
    margin: 0 0 14px;
    line-height: 1.5;
  }
  .troix-brand-row {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--paper);
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 9px 12px;
    margin-bottom: 14px;
    flex-wrap: wrap;
  }
  .troix-brand-label {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6b7a99;
  }
  .troix-brand-val {
    font-family: 'Times New Roman', Times, serif;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--navy);
    flex: 1;
    min-width: 60px;
  }
  .troix-brand-val.empty { color: #9aa6bf; font-weight: 400; font-style: italic; font-size: 0.9rem; }
  .troix-copy {
    border: 1px solid var(--line);
    background: #fff;
    color: var(--navy);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.78rem;
    font-weight: 600;
    padding: 7px 12px;
    border-radius: 7px;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.15s, color 0.15s;
  }
  .troix-copy:active { background: #eef1fb; }
  .troix-copy.copied { background: var(--green); color: #fff; border-color: var(--green); }
  .troix-regs {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
  }
  .troix-reg {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: inherit;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 12px 14px;
    transition: border-color 0.15s, transform 0.05s;
  }
  .troix-reg:hover { border-color: var(--navy); }
  .troix-reg:active { transform: scale(0.99); }
  .troix-dot {
    width: 9px; height: 9px;
    border-radius: 50%;
    flex-shrink: 0;
  }
  .troix-reg-text { flex: 1; min-width: 0; }
  .troix-reg-name {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--ink);
    display: block;
  }
  .troix-reg-desc {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.75rem;
    color: #6b7a99;
    display: block;
    margin-top: 1px;
  }
  .troix-reg-arr { color: #9aa6bf; font-size: 1.1rem; }
  .troix-note {
    margin-top: 14px;
    border-left: 3px solid var(--green);
    background: #F3FAF2;
    border-radius: 0 8px 8px 0;
    padding: 10px 12px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.78rem;
    color: #34503a;
    line-height: 1.55;
  }
  .troix-note b { color: var(--navy); }

  /* ===== Кнопка обновления ===== */
  .hub-footer {
    margin-top: 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    width: 100%;
    max-width: 420px;
  }
  .update-btn {
    background: transparent;
    border: 1.5px solid #c0c8e0;
    border-radius: 10px;
    padding: 0.6rem 1.4rem;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.82rem;
    color: #6b7a99;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s;
    width: 100%;
    justify-content: center;
  }
  .update-btn:hover { background: #eef1fb; border-color: var(--navy); color: var(--navy); }
  .update-btn:disabled { opacity: 0.5; cursor: not-allowed; }
  .hub-version {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.72rem;
    color: #a0aabb;
  }
  .update-toast {
    display: none;
    position: fixed;
    top: 18px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--navy);
    color: #fff;
    padding: 10px 20px;
    border-radius: 24px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.85rem;
    z-index: 9999;
    box-shadow: 0 4px 20px rgba(0,0,0,0.25);
    white-space: nowrap;
    max-width: 90vw;
    text-align: center;
  }
  .update-toast.show { display: block; }
  .update-toast.success { background: var(--green); }
  .update-toast.error { background: #A11A1A; }

  /* ===== Полный расчёт ===== */
  .fc-tab {
    flex: 1;
    padding: 10px 8px;
    text-align: center;
    font-size: 13px;
    font-weight: 600;
    color: #6b7a99;
    background: #f0f2f8;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.15s;
  }
  .fc-tab.active {
    background: var(--navy);
    color: #fff;
    border-bottom-color: var(--green);
  }
  .fc-fill-wrap {
    margin-bottom: 16px;
  }
  .fc-fill-header {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #6b7a99;
    margin-bottom: 6px;
  }
  .fc-fill-bar-bg {
    height: 10px;
    background: #e0e6f0;
    border-radius: 5px;
    overflow: hidden;
  }
  .fc-fill-bar {
    height: 100%;
    background: var(--navy);
    border-radius: 5px;
    transition: width 0.3s, background 0.3s;
  }
  .fc-fill-warning {
    margin-top: 6px;
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 6px;
  }
  .fc-warn-over { background: #FDECEC; color: #A11A1A; }
  .fc-warn-ok { background: #E4F4E2; color: #0B6603; }
  .fc-warn-info { background: #EEF1FB; color: #112C93; }
  .fc-item {
    border: 1px solid #dde3ee;
    border-left: 4px solid var(--navy);
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 10px;
    background: #fff;
  }
  .fc-item-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
  }
  .fc-item-name {
    flex: 1;
    border: none;
    border-bottom: 1px solid #dde3ee;
    padding: 4px 0;
    font-size: 14px;
    font-weight: 700;
    color: var(--navy);
    background: transparent;
    outline: none;
  }
  .fc-item-del {
    background: none;
    border: 1px solid #dde3ee;
    border-radius: 6px;
    color: #A11A1A;
    cursor: pointer;
    width: 28px;
    height: 28px;
    font-size: 13px;
    flex-shrink: 0;
  }
  /* ПОЛЯ ПОЗИЦИИ — КОМПАКТНО, В ОДИН РЯД.
     Раньше они растягивались на всю ширину монитора: слева одна колонка,
     справа другая, между ними пустое поле в пол-экрана. Теперь у каждого поля
     своя небольшая ширина, и они просто идут подряд, переносясь на вторую
     строку на узком экране. Порядок задан так, что пара «за штуку / за всё»
     всегда стоит рядом. */
  /* ПОЛЯ ПОЗИЦИИ — ЦВЕТНЫМИ ПОЛОСАМИ.
     Каждая величина живёт в своей горизонтальной полосе со своим цветом:
     количество серое, цена янтарная, объём голубой, вес сиреневый. Так видно
     с одного взгляда, куда какую цифру вбивать, и не надо вчитываться в
     подписи. Полоса — всегда во всю ширину, и на телефоне, и на компьютере,
     поэтому пара «за штуку / за всё» никогда не разрывается. */
  /* КАРТОЧКА В ИСТОРИИ.
     На телефоне кнопки во всю ширину — так удобнее пальцу. На компьютере они
     раньше растягивались через весь экран огромными полосами; теперь жмутся
     влево и имеют разумный предел ширины. */
  .hist-acts { padding: 0 14px 12px; display: flex; gap: 8px; flex-wrap: wrap; }
  .hist-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 7px;
    border-radius: 8px; padding: 10px 14px; cursor: pointer;
    font-family: sans-serif; font-size: 13px; font-weight: 700; border: 1px solid transparent;
    flex: 1 1 auto; min-width: 0;
  }
  .hist-btn svg { width: 16px; height: 16px; flex: 0 0 auto; }
  .hist-btn-edit  { background: #fff; color: #112C93; border-color: #c0c8e0; flex: 0 0 auto; }
  .hist-btn-open  { background: #112C93; color: #fff; }
  .hist-btn-share { background: #128A04; color: #fff; }
  .hist-btn-edit:active  { background: #f3f5f9; }
  @media (min-width: 700px) {
    /* На широком экране кнопки не растягиваем: ставим рядом слева. */
    .hist-acts { justify-content: flex-start; }
    .hist-btn { flex: 0 0 auto; min-width: 132px; }
  }
  .fc-item-fields { display: flex; flex-direction: column; gap: 6px; }
  .fc-grp {
    display: flex; gap: 8px; align-items: flex-end;
    padding: 8px 10px; border-radius: 8px;
    border-left: 4px solid #c3ccdd; background: #f2f4f9;
  }
  .fc-grp-qty   { border-left-color: #6b7a99; background: #f1f3f8; }
  .fc-grp-price { border-left-color: #C98A16; background: #FFF6E4; }
  .fc-grp-vol   { border-left-color: #1F6FB2; background: #E9F3FB; }
  .fc-grp-wt    { border-left-color: #6E4BA8; background: #F2ECFA; }
  .fc-item-field { flex: 1 1 152px; max-width: 152px; min-width: 0; }
  .fc-item-field input { background: #fff; }
  /* «За всё» — та же полоса, но подпись жирнее и рамка в цвет полосы,
     чтобы не путать с графой за штуку. */
  .fc-item-field.tot label { font-weight: 800; }
  .fc-grp-price .tot label { color: #9A6A10; }
  .fc-grp-vol   .tot label { color: #17547F; }
  .fc-grp-wt    .tot label { color: #533A80; }
  .fc-grp-price .tot input { border-color: #E4C384; }
  .fc-grp-vol   .tot input { border-color: #A9CDE8; }
  .fc-grp-wt    .tot input { border-color: #C4B0E4; }
  @media (max-width: 430px) {
    .fc-item-field { max-width: none; }
  }
  .fc-item-field label {
    display: block;
    font-size: 11px;
    color: #6b7a99;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }
  /* Кнопка КП рядом с сохранением расчёта */
  .btn-kp { width:100%; margin-top:10px; padding:14px; border:0; border-radius:0;
    background:#128A04; color:#fff; font-family:inherit; font-size:15px; font-weight:700; cursor:pointer; }
  .btn-kp:active { background:#0e6b03; }
  /* Окно коммерческого предложения */
  .kp-ov { position:fixed; inset:0; background:rgba(15,20,35,.5); z-index:9600;
    display:flex; align-items:flex-start; justify-content:center; overflow:auto; padding:0; }
  .kp-box { width:100%; max-width:820px; background:#fff; min-height:100%; }
  .kp-bar { position:sticky; top:0; display:flex; gap:8px; padding:10px 12px; background:#f7f8fb;
    border-bottom:1px solid var(--line); z-index:2; flex-wrap:wrap; }
  .kp-bar button { flex:1; min-width:120px; padding:11px 8px; border:1px solid var(--line);
    border-radius:0; background:#fff; font-family:inherit; font-size:13px; font-weight:700;
    color:#112C93; cursor:pointer; }
  .kp-bar button.main { background:#112C93; color:#fff; border-color:#112C93; }
  .kp-bar button.x { flex:0 0 auto; min-width:44px; color:#8a92a3; }
  .kp-doc { padding:22px 20px 40px; font-family:-apple-system, sans-serif; color:#1A1A1A; }
  .kp-head { display:flex; gap:14px; align-items:flex-start; border-bottom:3px solid #112C93; padding-bottom:12px; }
  /* Логотип ИТК в коммерческом предложении. Знак широкий (шар плюс надпись),
     поэтому задаём ширину, а высоту считает браузер сам — иначе картинка
     сплющивается в квадрат. */
  .kp-logo { width:130px; flex:0 0 auto; }
  .kp-logo img { width:100%; height:auto; display:block; }
  .kp-co { flex:1; min-width:0; }
  .kp-co b { font-family:'Times New Roman',serif; font-size:15px; line-height:1.25;
    color:#112C93; display:block; margin-bottom:3px; }
  .kp-co span { font-size:11px; color:#5b6472; line-height:1.45; display:block; }
  .kp-title { font-family:'Times New Roman',serif; font-size:22px; color:#112C93; margin:18px 0 2px; }
  .kp-meta { font-size:12px; color:#5b6472; margin-bottom:16px; }
  .kp-tbl { width:100%; border-collapse:collapse; font-size:12.5px; }
  .kp-tbl th { background:#112C93; color:#fff; font-size:11px; text-transform:uppercase;
    letter-spacing:.03em; padding:8px 7px; text-align:left; font-weight:700; }
  .kp-tbl td { padding:8px 7px; border-bottom:1px solid #e0e6f0; vertical-align:top; }
  .kp-tbl td.n { text-align:right; white-space:nowrap; }
  .kp-tbl tr:nth-child(even) td { background:#fafbfe; }
  .kp-total { display:flex; justify-content:space-between; align-items:baseline;
    margin-top:14px; padding:12px 10px; background:#eef1fb; border-left:4px solid #112C93; }
  .kp-total b { font-size:21px; color:#112C93; }
  .kp-note { margin-top:16px; font-size:11.5px; color:#5b6472; line-height:1.6; }
  .kp-req { margin-top:18px; padding:12px; background:#fafbfe; border:1px solid var(--line); font-size:11.5px; line-height:1.6; }
  .kp-req b { color:#112C93; }
  .kp-warn { margin-top:12px; padding:10px 12px; background:#FFF6E5; border-left:4px solid #C2610A;
    font-size:12px; color:#7a4a06; line-height:1.5; }
  @media print {
    .kp-ov { position:static; background:#fff; }
    .kp-bar { display:none; }
    .kp-box { max-width:none; }
  }
  .fld-legend { display:flex; flex-wrap:wrap; gap:4px 14px; padding:9px 12px;
    background:#fafbfe; border-bottom:1px solid var(--line); font-size:11px; color:#6b7a99; }
  .fld-legend span { display:flex; align-items:center; gap:5px; }
  .fld-legend i { width:10px; height:10px; border-radius:0; display:block; }
  /* ===== ЦВЕТОВЫЕ МЕТКИ ПОЛЕЙ =====
     Зрение распознаёт цвет за 200–250 мс, ещё до того как глаз переместится на
     объект — то есть нужное поле находится «одним взглядом». Но надёжно
     различается лишь небольшое число категорий, поэтому их РОВНО ПЯТЬ:
       деньги (синий) · объём (бирюзовый) · вес (фиолетовый)
       логистика (оранжевый) · налоги и проценты (серый)
     Итог отмечен зелёным — это шестая, отдельная роль.
     Цвет НИКОГДА не единственный признак: подпись поля называет его словами.
     Это нужно и для тех, кто плохо различает цвета (около 8% мужчин), и просто
     чтобы не гадать. Красный и зелёный не противопоставлены — самая частая
     форма нарушения цветовосприятия путает именно эту пару.
     Метка — полоска слева и цвет подписи, само поле остаётся белым: заливать
     поля ввода нельзя, читаемость цифр важнее. */
  /* У каждого вида — свой цвет метки и своя бледная заливка строки.
     Заливка добавлена по просьбе: одной полоски слева мало, глаз всё равно
     приходилось наводить. Поля ввода при этом остаются белыми — читаемость
     цифр важнее любой раскраски. */
  .fld-money  { --fld: #112C93; --fldbg: #EEF1FB; }   /* деньги: товар, инвойс, оплата   */
  .fld-vol    { --fld: #0A7A8C; --fldbg: #E6F4F6; }   /* объём                            */
  .fld-wt     { --fld: #6A3FA0; --fldbg: #F1EBF9; }   /* вес                              */
  .fld-log    { --fld: #C2610A; --fldbg: #FDF0E4; }   /* логистика и доставка             */
  .fld-tax    { --fld: #6b7a99; --fldbg: #F2F4F8; }   /* налоги, проценты, комиссии       */
  .fld-total  { --fld: #128A04; --fldbg: #EAF7E8; }   /* итог                             */
  #section-fullcalc .input-row[class*="fld-"],
  #ed-body .input-row[class*="fld-"],
  #tab-calc .field-row[class*="fld-"] { background: var(--fldbg); }
  #section-fullcalc .input-row[class*="fld-"] input,
  #section-fullcalc .input-row[class*="fld-"] select,
  #ed-body .input-row[class*="fld-"] input,
  #tab-calc .field-row[class*="fld-"] input { background: #fff; }
  #section-fullcalc .input-row[class*="fld-"],
  #tab-calc .field-row[class*="fld-"] {
    border-left: 4px solid var(--fld);
    padding-left: 9px;
  }
  #section-fullcalc .input-row[class*="fld-"] .input-label,
  #tab-calc .field-row[class*="fld-"] .field-label { color: var(--fld); }
  #section-fullcalc .input-row[class*="fld-"] .num-input:focus,
  #tab-calc .field-row[class*="fld-"] .field-input-group input:focus { border-color: var(--fld); }
  /* БЫСТРЫЙ РАСЧЁТ — тот же вид, что и полный: плитки по две в ряд, прямые
     углы. Зелёные заголовки оставляем: это наш цвет. */
  #tab-calc .panel { border-radius: 0; }
  #tab-calc .panel-body.grid2 { padding: 0; display: grid; grid-template-columns: 1fr 1fr; overflow: hidden; }
  #tab-calc .field-row {
    display: flex; flex-direction: column; gap: 5px; align-items: stretch;
    padding: 10px 12px; border-bottom: 1px solid var(--line);
    border-right: 1px solid var(--line); margin-right: -1px; min-width: 0;
  }
  #tab-calc .field-label {
    font-family: -apple-system, sans-serif; font-size: 11px; font-weight: 700;
    letter-spacing: .03em; color: #6b7a99; text-transform: uppercase; line-height: 1.25;
  }
  #tab-calc .field-input-group { display: flex; align-items: center; gap: 6px; min-width: 0; }
  #tab-calc .field-input-group input {
    flex: 1; min-width: 0; width: 100%; box-sizing: border-box;
    padding: 8px 10px; border: 1px solid #c0c8e0; border-radius: 0;
    font-size: 15px; text-align: right; color: #112C93; font-weight: 700; background: #fff;
  }
  #tab-calc .field-input-group input:focus { outline: none; border-color: #128A04; }
  #tab-calc .field-input-group .unit { flex: 0 0 auto; font-size: 11.5px; color: #8a92a3; }
  #tab-calc .cat-select-wrap { border-radius: 0; }
  #tab-calc #category { border-radius: 0; }
  /* ПОЛЯ РАСЧЁТА ПЛИТКАМИ. Раньше каждое поле занимало свою строку во всю
     ширину — получалась длинная портянка, по которой приходилось листать.
     Теперь короткие поля идут по два в ряд, прямыми прямоугольниками, как
     остальные наши списки: глазами пробежался и нашёл нужное. */
  #section-fullcalc .panel-body, #ed-body .panel-body { padding: 0; }
  #section-fullcalc .panel-body.grid2, #ed-body .panel-body.grid2 { display: grid; grid-template-columns: 1fr 1fr; }
  #section-fullcalc .input-row, #ed-body .input-row {
    display: flex; flex-direction: column; gap: 5px;
    padding: 10px 12px; border-bottom: 1px solid var(--line); min-width: 0;
  }
  /* Правую рамку даём всем ячейкам и прячем лишнюю за краем панели: считать
     чётность нельзя — часть строк скрывается при выборе автодоставки, и
     разделители поехали бы. */
  #section-fullcalc .panel-body.grid2, #ed-body .panel-body.grid2 { overflow: hidden; }
  #section-fullcalc .panel-body.grid2 .input-row, #ed-body .panel-body.grid2 .input-row { border-right: 1px solid var(--line); margin-right: -1px; }
  #section-fullcalc .input-row.wide, #ed-body .input-row.wide { grid-column: 1 / -1; }
  #section-fullcalc .input-label, #ed-body .input-label {
    font-family: -apple-system, sans-serif; font-size: 11px; font-weight: 700;
    letter-spacing: .03em; color: #6b7a99; text-transform: uppercase; line-height: 1.25;
  }
  #section-fullcalc .input-cell, #ed-body .input-cell { display: flex; align-items: center; gap: 6px; min-width: 0; }
  #section-fullcalc .num-input, #ed-body .num-input {
    flex: 1; min-width: 0; width: 100%; box-sizing: border-box;
    padding: 8px 10px; border: 1px solid #c0c8e0; border-radius: 0;
    font-size: 15px; text-align: right; color: #112C93; font-weight: 700; background: #fff;
  }
  #section-fullcalc .num-input:focus, #ed-body .num-input:focus { outline: none; border-color: #112C93; }
  #section-fullcalc .input-unit, #ed-body .input-unit { flex: 0 0 auto; font-size: 11.5px; color: #8a92a3; }
  #section-fullcalc .cur-switch, #ed-body .cur-switch { width: 100%; border-radius: 0; }
  #section-fullcalc .cur-btn, #ed-body .cur-btn { border-radius: 0; padding: 8px 4px; font-size: 12.5px; }
  /* Кнопка выбора категории — тоже прямыми углами, в общем стиле */
  #section-fullcalc .catpick { border-radius: 0; }
  /* Выбор категории: своё окно плитками, как выбор пункта выдачи в магазине. */
  .catpick { width:100%; display:flex; align-items:center; gap:10px; background:#fff;
             border:1px solid #c0c8e0; border-radius:8px; padding:12px 14px; text-align:left;
             cursor:pointer; font-size:14px; font-weight:700; color:#112C93; font-family:inherit; }
  .catpick:active { background:#f3f5f9; }
  .catpick-t { flex:1; min-width:0; }
  .catpick-ar { flex:0 0 auto; color:#9aabcc; font-size:20px; line-height:1; }
  /* Затемнение поверх страницы. touch-action:none — палец по затемнению
     страницу под ним не двигает вовсе; списку прокрутку возвращаем отдельно,
     иначе он тоже перестал бы листаться. */
  .catp-ov { position:fixed; inset:0; background:rgba(15,20,35,.45); z-index:9000;
             display:flex; align-items:flex-end; justify-content:center;
             touch-action: none; overscroll-behavior: contain; }
  .catp-ov .catp-list { touch-action: pan-y; }
  .catp-box2 { width:100%; max-width:560px; background:#fff; border-radius:0; max-height:88vh;
               display:flex; flex-direction:column; }
  .catp-head { display:flex; align-items:center; justify-content:space-between; padding:14px 16px;
               border-bottom:1px solid #e0e6f0; font-weight:700; color:#112C93; font-size:15px;
               font-family:sans-serif; }
  .catp-x { border:0; background:none; font-size:26px; line-height:1; color:#9aabcc; cursor:pointer; padding:0 4px; }
  .catp-list { overflow:auto; -webkit-overflow-scrolling:touch; overflow-anchor:none;
               /* Докрутив список до края, палец продолжал листать страницу
                  ПОД окном — фон уезжал вместе со списком. Эта строка
                  останавливает прокрутку на границе списка. */
               overscroll-behavior: contain; }
  /* Пока окно открыто, страница под ним не двигается вовсе. */
  /* Пока окно открыто, страница под ним не двигается вовсе. Замок вешаем и на
     html, и на body: на телефоне прокруткой заведует то один, то другой. */
  html.eco-locked { overflow: hidden; height: 100%; }
  body.eco-locked { position: fixed; left: 0; right: 0; width: 100%; overflow: hidden; }
  .catp-grp { padding:10px 16px 6px; font-size:11.5px; font-weight:700; color:#8a92a3;
              text-transform:uppercase; letter-spacing:.4px; background:#f7f8fb;
              border-bottom:1px solid #e0e6f0; font-family:sans-serif; }
  .catp-row { width:100%; display:flex; align-items:flex-start; gap:12px; padding:13px 16px;
              background:#fff; border:0; border-bottom:1px solid #e0e6f0; text-align:left; cursor:pointer; }
  .catp-row:active { background:#f3f5f9; }
  .catp-row.on { background:#f2f6ff; }
  /* Отметка — квадрат, как в магазине */
  .catp-box { flex:0 0 auto; width:22px; height:22px; border:2px solid #c3cadb; border-radius:0;
              display:flex; align-items:center; justify-content:center; color:#fff; background:#fff; margin-top:2px; }
  .catp-row.on .catp-box { background:#112C93; border-color:#112C93; }
  .catp-box svg { width:14px; height:14px; display:block; }
  .catp-tx { flex:1; min-width:0; display:flex; flex-direction:column; gap:3px; font-family:sans-serif; }
  .catp-n { font-size:14px; font-weight:700; color:#1A1A1A; line-height:1.3; }
  .catp-d { font-size:12px; color:#5b6472; line-height:1.35; }
  /* Расшифровка формулы — то, чего менеджерам не хватало */
  .catp-f { font-size:11.5px; color:#112C93; background:#eef1fb; border-radius:4px;
            padding:3px 7px; align-self:flex-start; margin-top:1px; }
  /* Цвет группы = вид доставки. Тот же приём, что и у полей: полоска слева,
     цвет заголовка. Название группы написано словами — цвет только помогает. */
  .catp-grp.grp-direct  { border-left: 4px solid #112C93; color: #112C93; }
  .catp-grp.grp-rail    { border-left: 4px solid #6A3FA0; color: #6A3FA0; }
  .catp-grp.grp-auto    { border-left: 4px solid #C2610A; color: #C2610A; }
  .catp-grp.grp-special { border-left: 4px solid #6b7a99; }
  .catp-row.grp-direct  { border-left: 4px solid rgba(17,44,147,.35); }
  .catp-row.grp-rail    { border-left: 4px solid rgba(106,63,160,.35); }
  .catp-row.grp-auto    { border-left: 4px solid rgba(194,97,10,.35); }
  .catp-row.grp-special { border-left: 4px solid rgba(107,122,153,.35); }
  .catp-legend { padding:11px 16px; border-top:1px solid #e0e6f0; font-size:11.5px;
                 color:#6b7a99; line-height:1.5; font-family:sans-serif; background:#fafbfe; }
  /* Переключатель валюты инвойса */
  .cur-switch { display:flex; gap:0; border:1px solid #c0c8e0; border-radius:8px; overflow:hidden; }
  .cur-btn { flex:1; padding:9px 6px; border:0; background:#fff; color:#5b6472;
             font-size:13px; font-weight:700; cursor:pointer; font-family:inherit; }
  /* ВЫБРАННАЯ ВАЛЮТА ДОЛЖНА БРОСАТЬСЯ В ГЛАЗА.
     Раньше выбранная кнопка была тёмно-синей, как половина остальных
     элементов формы, и на бегу их путали: вбить юани, забыв переключить на
     доллары, — это ошибка в разы, а не в проценты. Теперь выбранная кнопка
     ярко-зелёная, с галочкой и в рамке; невыбранная — бледная и приглушённая.
     Зелёный тут не «хорошо/плохо», а просто самый заметный цвет на этой
     форме: он больше нигде во вводе не встречается. */
  .cur-btn.active { background:#128A04; color:#fff; box-shadow: inset 0 0 0 2px #0B5F02; }
  .cur-btn.active::before { content:'✓ '; font-weight:900; }
  .cur-btn:not(.active) { background:#f4f6fa; color:#8a93a6; }
  /* Вся строка выбора валюты подсвечена, чтобы её было видно при прокрутке */
  .cur-row-hl { background:#F0FAEF !important; }
  .cur-row-hl .input-label { color:#0B5F02 !important; font-weight:800; }
  .cur-btn:not(.active):active { background:#eef1f8; }
  /* Иконки в едином стиле — вместо разнобоя эмодзи. Рисуем контуром,
     подхватывают цвет текста, поэтому одинаково смотрятся на любом фоне. */
  .ic { width:18px; height:18px; display:inline-block; vertical-align:-3px; margin-right:7px; }
  .ic svg { width:100%; height:100%; display:block; fill:currentColor; }
  .fc-item-input {
    width: 100%;
    border: 1px solid #dde3ee;
    border-radius: 6px;
    padding: 7px 8px;
    font-size: 14px;
    font-weight: 700;
    color: var(--navy);
    text-align: right;
    box-sizing: border-box;
    outline: none;
  }
  .fc-item-input:focus { border-color: var(--navy); }
  .fc-add-btn {
    width: 100%;
    border: 2px dashed #c0c8e0;
    background: transparent;
    border-radius: 8px;
    padding: 12px;
    color: var(--navy);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    margin: 10px 0 16px;
    transition: all 0.15s;
  }
  .fc-add-btn:hover { border-color: var(--navy); background: #eef1fb; }
  .fc-empty-items {
    text-align: center;
    color: #9aabcc;
    font-size: 13px;
    padding: 20px 0;
  }
  /* Результаты */
  .fc-res-item {
    background: #fff;
    border-radius: 10px;
    border: 1px solid #dde3ee;
    border-left: 4px solid var(--green);
    margin: 0 -16px 10px;
    padding: 14px 16px;
  }
  .fc-res-item-head {
    font-family: 'Times New Roman', Times, serif;
    font-size: 1rem;
    font-weight: 700;
    color: var(--navy);
    margin-bottom: 10px;
  }
  .fc-res-vol { font-size: 0.8rem; color: #6b7a99; font-weight: 400; }
  .fc-res-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 3px 0;
    font-size: 13px;
  }
  .fc-res-label { color: #6b7a99; }
  .fc-res-val { font-weight: 600; color: var(--navy); }
  .fc-val-green { color: var(--green); }
  .fc-res-bold .fc-res-label,
  .fc-res-bold .fc-res-val { font-weight: 700; font-size: 14px; }
  .fc-res-total {
    margin-top: 4px;
    padding-top: 6px;
    border-top: 1px solid #e0e6f0;
  }
  .fc-val-total { color: var(--navy); font-size: 15px; font-weight: 800; }
  .fc-res-divider { height: 1px; background: #e0e6f0; margin: 6px 0; }
  .fc-res-summary {
    background: var(--navy);
    border-radius: 10px;
    padding: 20px;
    margin: 0 -16px;
    color: #fff;
  }
  .fc-res-summary-title {
    font-size: 11px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--green);
    margin-bottom: 14px;
  }
  .fc-res-summary-row {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    margin-bottom: 10px;
    color: rgba(255,255,255,0.9);
  }
  .fc-val-green { color: #4DD637; }
  .fc-res-summary-total {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    padding-top: 12px;
    border-top: 1px solid rgba(255,255,255,0.2);
    margin-top: 4px;
    font-size: 13px;
    color: rgba(255,255,255,0.7);
  }
  .fc-val-total-big {
    font-family: 'Courier New', monospace;
    font-size: 24px;
    font-weight: 800;
    color: #4DD637;
  }
  .fc-res-summary-note {
    margin-top: 10px;
    font-size: 11px;
    color: rgba(255,255,255,0.5);
  }

  /* ===== Бейджи автопроверки ===== */
  .fc-check-badges {
    margin-top: 8px;
    display: flex;
    flex-direction: column;
    gap: 5px;
  }
  .fc-badge {
    display: flex;
    align-items: flex-start;
    gap: 7px;
    padding: 7px 10px;
    border-radius: 7px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 11.5px;
    line-height: 1.4;
    border-left: 3px solid transparent;
  }
  .fc-badge-icon { flex-shrink: 0; font-size: 13px; line-height: 1.3; }
  .fc-badge-text { color: inherit; }
  .fc-badge-cz {
    background: #E4F4E2;
    border-left-color: #128A04;
    color: #0B5A03;
  }
  .fc-badge-cert {
    background: #FFF3E0;
    border-left-color: #E65100;
    color: #7A3100;
  }
  .fc-badge-decl {
    background: #FFF8E7;
    border-left-color: #B8860B;
    color: #5A4000;
  }
  .fc-badge-no {
    background: #f0f2f8;
    border-left-color: #9aabcc;
    color: #4a5a7a;
  }

  /* ===== Сохранение расчётов ===== */
  .save-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.55);
    z-index: 9000;
    align-items: flex-end;
    justify-content: center;
  }
  .save-modal-overlay.open { display: flex; }
  .save-modal {
    background: #fff;
    border-radius: 20px 20px 0 0;
    padding: 24px 20px 36px;
    width: 100%;
    max-width: 520px;
    animation: slideUp 0.25s ease;
  }
  @keyframes slideUp {
    from { transform: translateY(100%); opacity: 0; }
    to   { transform: translateY(0);    opacity: 1; }
  }
  .save-modal-title {
    font-family: 'Times New Roman', Times, serif;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--navy);
    margin-bottom: 16px;
  }
  .save-modal-label {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 12px;
    color: #6b7a99;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 6px;
  }
  .save-modal-input {
    width: 100%;
    border: 2px solid var(--navy);
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 16px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: var(--navy);
    font-weight: 600;
    box-sizing: border-box;
    outline: none;
    margin-bottom: 12px;
  }
  .save-modal-date {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 12px;
    color: #9aabcc;
    margin-bottom: 20px;
  }
  .save-modal-btns {
    display: flex;
    gap: 10px;
  }
  .save-modal-btn-ok {
    flex: 1;
    background: var(--navy);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 13px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
  }
  .save-modal-btn-cancel {
    background: #f0f2f8;
    color: #6b7a99;
    border: none;
    border-radius: 10px;
    padding: 13px 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 15px;
    cursor: pointer;
  }
  /* Кнопка сохранить */
  .btn-save-calc {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    background: transparent;
    border: 2px solid var(--navy);
    border-radius: 10px;
    padding: 12px;
    color: var(--navy);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    margin: 14px 0 0;
    transition: all 0.15s;
  }
  .btn-save-calc:hover { background: var(--navy); color: #fff; }
  /* История */
  .btn-history {
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.13);
    border: 1px solid rgba(255,255,255,0.4);
    color: #fff;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0.28rem 0.7rem;
    border-radius: 7px;
    cursor: pointer;
    margin-left: auto;
  }
  .history-panel {
    display: none;
    position: fixed;
    inset: 0;
    background: #f4f6fb;
    z-index: 8000;
    overflow-y: auto;
  }
  .history-panel.open { display: block; }
  /* Карточки на широком экране не растягиваем на всю ширину монитора —
     колонкой по центру, как список, а не как обои. */
  @media (min-width: 900px) {
    #hs-list { max-width: 760px; margin: 0 auto; }
  }
  .history-topbar {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    padding: 0.55rem 1rem;
    background: var(--navy);
    position: sticky;
    top: 0;
    z-index: 300;
  }
  .history-topbar-title {
    color: #fff;
    font-family: 'Times New Roman', Times, serif;
    font-size: 1rem;
    font-weight: 700;
    flex: 1;
  }
  .history-empty {
    text-align: center;
    padding: 48px 20px;
    color: #9aabcc;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 14px;
  }
  .history-empty-icon { font-size: 3rem; margin-bottom: 12px; }
  .history-card {
    background: #fff;
    border-radius: 12px;
    margin: 12px 16px;
    border-left: 4px solid var(--navy);
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(17,44,147,0.06);
  }
  .history-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 14px 14px 10px;
    cursor: pointer;
  }
  .history-card-name {
    font-family: 'Times New Roman', Times, serif;
    font-size: 1rem;
    font-weight: 700;
    color: var(--navy);
  }
  .history-card-meta {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 11px;
    color: #9aabcc;
    margin-top: 3px;
  }
  .history-card-del {
    background: none;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    color: #A11A1A;
    cursor: pointer;
    padding: 4px 8px;
    font-size: 12px;
    flex-shrink: 0;
  }
  .history-card-body {
    border-top: 1px solid #f0f2f8;
    padding: 12px 14px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 13px;
  }
  .history-row {
    display: flex;
    justify-content: space-between;
    padding: 3px 0;
    color: #6b7a99;
  }
  .history-row-val { font-weight: 600; color: var(--navy); }
  .history-total-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0 3px;
    border-top: 1px solid #e0e6f0;
    margin-top: 6px;
    font-weight: 700;
  }
  .history-total-val { color: var(--green); font-size: 15px; }

  /* ===== Кнопка Поделиться ===== */
  .btn-share {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    background: var(--green);
    border: none;
    border-radius: 8px;
    padding: 8px 14px;
    color: #fff;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.15s;
    white-space: nowrap;
  }
  .btn-share:hover { opacity: 0.85; }
  .history-card-actions {
    display: flex;
    gap: 8px;
    padding: 0 14px 12px;
    align-items: center;
  }
  .btn-share-full {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    background: var(--green);
    border: none;
    border-radius: 8px;
    padding: 10px;
    color: #fff;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
  }
  /* Кнопка поделиться в save modal */
  .save-modal-btn-share {
    flex: 1;
    background: var(--green);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 13px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
  }

  /* ===== Splash Screen ===== */
  #splash {
    position: fixed;
    inset: 0;
    background: #fff;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0;
  }
  #splash.hide {
    animation: splashFadeOut 0.5s ease forwards;
  }
  @keyframes splashFadeOut {
    to { opacity: 0; pointer-events: none; }
  }

  /* Обёртка логотипа */
  .splash-logo-wrap {
    position: relative;
    width: 160px;
    height: 160px;
    animation: splashLogoIn 0.7s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    opacity: 0;
  }
  @keyframes splashLogoIn {
    0%   { opacity: 0; transform: scale(0.5); }
    100% { opacity: 1; transform: scale(1);   }
  }

  /* Слой с глобусом (статичный) */
  .splash-globe {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    overflow: hidden;
  }
  .splash-globe img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    /* Маскируем стрелку через clip — но проще сделать через анимацию всего логотипа */
  }

  /* Стрелка — отдельный слой вращается */
  .splash-arrow-ring {
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    border: 4px solid transparent;
    border-top-color: #112C93;
    border-right-color: #112C93;
    animation: spinArrow 1.2s linear infinite;
  }
  @keyframes spinArrow {
    to { transform: rotate(360deg); }
  }

  /* Пульсирующее кольцо */
  .splash-ring {
    position: absolute;
    inset: -12px;
    border-radius: 50%;
    border: 3px solid rgba(17, 44, 147, 0.15);
    animation: pulseRing 1.4s ease-out infinite;
  }
  @keyframes pulseRing {
    0%   { transform: scale(0.9); opacity: 0.7; }
    100% { transform: scale(1.15); opacity: 0; }
  }

  /* Заголовок */
  .splash-title {
    font-family: 'Times New Roman', Times, serif;
    font-size: 2rem;
    font-weight: 700;
    color: #112C93;
    letter-spacing: 0.03em;
    margin-top: 28px;
    opacity: 0;
    animation: splashTitleIn 0.6s ease 0.6s forwards;
  }
  @keyframes splashTitleIn {
    0%   { opacity: 0; transform: translateY(16px); }
    100% { opacity: 1; transform: translateY(0); }
  }

  /* Подзаголовок */
  .splash-sub {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.78rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #128A04;
    margin-top: 6px;
    opacity: 0;
    animation: splashTitleIn 0.6s ease 0.9s forwards;
  }

  /* Точки загрузки */
  .splash-dots {
    display: flex;
    gap: 7px;
    margin-top: 40px;
    opacity: 0;
    animation: splashTitleIn 0.4s ease 1.1s forwards;
  }
  .splash-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #112C93;
    animation: dotBounce 0.9s ease-in-out infinite;
  }
  .splash-dot:nth-child(2) { animation-delay: 0.15s; background: #128A04; }
  .splash-dot:nth-child(3) { animation-delay: 0.3s; }
  @keyframes dotBounce {
    0%, 80%, 100% { transform: scale(0.7); opacity: 0.4; }
    40%            { transform: scale(1.2); opacity: 1; }
  }
  
  /* Иконки в стиле Google: тонкая линия, единый размер, цвет наследуется */
  .hub-card-big-icon svg { width: 28px; height: 28px; color: #fff; }
  .hub-card-sm-icon svg  { width: 22px; height: 22px; }
  .hub-card-sm-navy  .hub-card-sm-icon svg { color: #112C93; }
  .hub-card-sm-green .hub-card-sm-icon svg { color: #0B6603; }
  .hub-card-sm-amber .hub-card-sm-icon svg { color: #B8860B; }
  .update-btn svg, .btn-history svg { width: 17px; height: 17px; vertical-align: -3px; margin-right: 6px; }
  .eco-cfg-h svg { width: 18px; height: 18px; vertical-align: -3px; margin-right: 6px; }
</style>
<!-- Создание PDF прямо в приложении: библиотека и урезанный шрифт с
     кириллицей. Грузятся с нашего сервера, интернет для этого не нужен. -->
<script src="/eco/itc-pdf.js" defer></script>
<script src="/eco/itc-pdf-font.js" defer></script>
</head>
<body>

<!-- ===== SPLASH SCREEN ===== -->
<div id="splash">
  <div class="splash-logo-wrap">
    <div class="splash-ring"></div>
    <div class="splash-globe">
      <img src="/eco/logo1.png" alt="ИТК">
    </div>
    <div class="splash-arrow-ring"></div>
  </div>
  <div class="splash-title">Экосистема ИТК</div>
  <div class="splash-sub">International Trading Company</div>
  <div class="splash-dots">
    <div class="splash-dot"></div>
    <div class="splash-dot"></div>
    <div class="splash-dot"></div>
  </div>
</div>


<!-- ===== ХАБ ===== -->
<div id="update-toast" class="update-toast"></div>

<div id="hub">

  <div class="hub-logo-wrap">
    <img src="/eco/logo2.png" alt="ИТК">
  </div>
  <div class="hub-title-new">Экосистема <span>ИТК</span></div>
  <div class="hub-sub-new">Инструменты международной торговли</div>

  <!-- Расчёты -->
  <div class="hub-section-label">Расчёты</div>
  <button id="ecoToApp" onclick="ecoBackToApp()" style="display:none;align-self:flex-start;margin:10px 0 0 14px;border:1px solid #d5dae3;background:#fff;color:#112C93;border-radius:9px;padding:7px 13px;font:600 .85rem/1 sans-serif;cursor:pointer">&#8249; В приложение</button>
  <div class="hub-cards">

    <div id="ecoWho" style="display:none;grid-column:1/-1;margin:0 0 10px;padding:8px 12px;background:#eef2fb;border-radius:9px;font:600 .82rem/1.35 sans-serif;color:#3d4a63"></div>

    <button class="hub-card-big t-navy" data-eco-need="summary" style="display:none" onclick="showSection('summary');smLoad()">
      <div class="hub-card-big-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3.4 20.6V3.4"/><path d="M3.4 20.6h17.2"/><path d="M7.4 16.4V11"/><path d="M12 16.4V7.2"/><path d="M16.6 16.4v-3.6"/></svg></div>
      <div>
        <span class="hub-card-big-label">Сводка</span>
        <span class="hub-card-big-desc">Прибыль по сделкам минус расходы компании</span>
      </div>
    </button>

    <button class="hub-card-big t-teal" data-eco-need="expenses" style="display:none" onclick="showSection('expenses');exLoad()">
      <div class="hub-card-big-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.8" y="5.6" width="18.4" height="12.8" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6.4 9.4v5.2M17.6 9.4v5.2"/></svg></div>
      <div>
        <span class="hub-card-big-label">Расходы компании</span>
        <span class="hub-card-big-desc">Аренда, зарплаты, налоги и прочее — по месяцам</span>
      </div>
    </button>

    <button class="hub-card-big t-green" data-eco-need="deals" style="display:none" onclick="showSection('deals');dlLoad()">
      <div class="hub-card-big-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3.4 7.6h17.2v11.6a1.6 1.6 0 0 1-1.6 1.6H5a1.6 1.6 0 0 1-1.6-1.6z"/><path d="M8.6 7.6V5.4a2 2 0 0 1 2-2h2.8a2 2 0 0 1 2 2v2.2"/><path d="M3.4 12.4h17.2"/></svg></div>
      <div>
        <span class="hub-card-big-label">Сделки</span>
        <span class="hub-card-big-desc">Поставки, расходы по расчёту и по факту</span>
      </div>
    </button>

    <button class="hub-card-big t-violet" data-eco-need="clients" style="display:none" onclick="showSection('clients');crmLoadClients()">
      <div class="hub-card-big-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 20v-1.8a3.4 3.4 0 0 0-3.4-3.4H6.4A3.4 3.4 0 0 0 3 18.2V20"/><circle cx="9.5" cy="7.4" r="3.4"/><path d="M21 20v-1.8a3.4 3.4 0 0 0-2.6-3.3"/><path d="M15.6 4.2a3.4 3.4 0 0 1 0 6.4"/></svg></div>
      <div>
        <span class="hub-card-big-label">Клиенты</span>
        <span class="hub-card-big-desc">База компаний, реквизиты и контактные лица</span>
      </div>
    </button>

    <button class="hub-card-big t-amber" data-eco-need="suppliers" style="display:none" onclick="showSection('suppliers');spLoad()">
      <div class="hub-card-big-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 20V10l5 3V10l5 3V5.5h3V20z"/><path d="M3 20h18"/></svg></div>
      <div>
        <span class="hub-card-big-label">Поставщики</span>
        <span class="hub-card-big-desc">Фабрики с выставок: визитки, контакты, каталоги</span>
      </div>
    </button>

    <button class="hub-card-big t-blue" onclick="showSection('calc')">
      <div class="hub-card-big-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="2.5" width="16" height="19" rx="2.6"/><rect x="7.5" y="5.8" width="9" height="3.4" rx="1"/><circle cx="9" cy="13.2" r=".9" fill="currentColor" stroke="none"/><circle cx="12" cy="13.2" r=".9" fill="currentColor" stroke="none"/><circle cx="15" cy="13.2" r=".9" fill="currentColor" stroke="none"/><circle cx="9" cy="17.4" r=".9" fill="currentColor" stroke="none"/><circle cx="12" cy="17.4" r=".9" fill="currentColor" stroke="none"/><circle cx="15" cy="17.4" r=".9" fill="currentColor" stroke="none"/></svg></div>
      <div>
        <span class="hub-card-big-label">Быстрый расчёт</span>
        <span class="hub-card-big-desc">Калькулятор стоимости поставки — категории A, B, C, VIP</span>
      </div>
    </button>

    <button class="hub-card-big t-slate" onclick="showSection('fullcalc')">
      <div class="hub-card-big-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 2.8H6.8a2 2 0 0 0-2 2v14.4a2 2 0 0 0 2 2h10.4a2 2 0 0 0 2-2V8.3z"/><path d="M13.5 2.8v5.5h5.7"/><path d="M8.4 13h7.2M8.4 16.6h5"/></svg></div>
      <div>
        <span class="hub-card-big-label">Полный расчёт</span>
        <span class="hub-card-big-desc">Расчёт по позициям · распределение по объёму контейнера</span>
      </div>
    </button>

  </div>

  <!-- Классификаторы -->
  <div class="hub-section-label">Классификаторы</div>
  <div class="hub-grid">

    <button class="hub-card-sm hub-card-sm-green" onclick="showSection('hz')">
      <div class="hub-card-sm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="8.6"/><path d="M8.4 12.3l2.6 2.6 4.8-5"/></svg></div>
      <span class="hub-card-sm-label">Честный знак</span>
      <span class="hub-card-sm-desc">Обязательная маркировка товаров</span>
    </button>

    <button class="hub-card-sm hub-card-sm-navy" onclick="showSection('tnved')">
      <div class="hub-card-sm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2.9l8.1 4.2v9.8L12 21.1 3.9 16.9V7.1z"/><path d="M3.9 7.1L12 11.3l8.1-4.2"/><path d="M12 11.3V21.1"/></svg></div>
      <span class="hub-card-sm-label">Код ТН ВЭД</span>
      <span class="hub-card-sm-desc">Таможенный код и ставка пошлины</span>
    </button>

    <button class="hub-card-sm hub-card-sm-amber hub-grid-last" onclick="showSection('pi')">
      <div class="hub-card-sm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="8.6"/><path d="M3.4 12h17.2"/><path d="M12 3.4c2.2 2.4 3.4 5.4 3.4 8.6s-1.2 6.2-3.4 8.6c-2.2-2.4-3.4-5.4-3.4-8.6S9.8 5.8 12 3.4z"/></svg></div>
      <span class="hub-card-sm-label">Параллельный импорт</span>
      <span class="hub-card-sm-desc">Перечень №2701, ТРОИС и реестр ТЗ РФ</span>
    </button>
  </div>

  <div class="hub-footer">
    <button class="update-btn" id="update-check-btn" onclick="checkUpdate()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.2 12a8.2 8.2 0 1 1-2.4-5.8"/><path d="M20.4 3.6v4.9h-4.9"/></svg>Проверить обновления
    </button>
    <div class="hub-version">Версия 4.10.9 · Экосистема ИТК · itcpartner.ru</div>
  </div>
</div>

<!-- ===== БЫСТРЫЙ РАСЧЁТ ===== -->
<div id="section-calc" class="app-section">
  <div class="section-topbar">
    <button class="back-btn" onclick="showHub()">&#8592; Назад</button>
    <span class="section-topbar-title">Быстрый расчёт</span>
    <button class="btn-history" data-action="open-history"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="8.6"/><path d="M12 7.2V12l3.2 1.9"/></svg>История</button>
  </div>
<div class="brand-bar">
    <div class="brand-bar-logo">
      <img src="/eco/logo1.png" alt="ИТК">
    </div>
    <div class="brand-bar-text">
      <div class="brand-bar-title">ИТК — Быстрый расчёт</div>
      <div class="brand-bar-sub">Калькулятор стоимости поставки</div>
    </div>
  </div>

  <div class="wrap">

  <div class="toggle-tabs sans">
    <div class="toggle-tab active" data-tab="calc">Калькулятор</div>
    <div class="toggle-tab" data-tab="ref">Классификация клиентов</div>
  </div>

  <!-- ============ CALCULATOR TAB ============ -->
  <div id="tab-calc">

    <div class="panel" style="margin-bottom:20px;">
      <div class="panel-head gold sans"><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17.3l-6.2 3.7 1.6-7-5.4-4.7 7.1-.6L12 2l2.9 6.7 7.1.6-5.4 4.7 1.6 7z"/></svg></span>Категория клиента</div>
      <div class="cat-select-wrap">
        <div class="cat-label sans">Выберите категорию сделки</div>
        <input type="hidden" id="category" value="A1"><button type="button" class="catpick sans" id="q-cat-btn"><span class="catpick-t" id="q-cat-label">A1 — Новый клиент (15%)</span><span class="catpick-ar">›</span></button>
        <div class="cat-desc sans" id="catDesc">1 контейнер, ИТК самостоятельно ищет фабрику</div>
        <div class="cat-formula" id="catFormula">(База × 1,15) × 1,03</div>
      </div>
    </div>

    <div class="grid">
      <!-- LEFT COLUMN: INPUTS -->
      <div>
        <div class="panel" style="margin-bottom:20px;">
          <div class="panel-head sans"><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l9 4.2v11.6L12 22l-9-4.2V6.2zm0 2.3L5.6 7 12 9.7 18.4 7zM5 8.8v8l6 2.8v-8.2zm14 0l-6 2.6v8.2l6-2.8z"/></svg></span>Товар и фрахт</div>
          <div class="panel-body grid2">
            <div class="field-row">
              <span class="field-label">Стоимость товара (Т)</span>
              <div class="field-input-group">
                <input type="number" id="goods" value="350000" step="1000">
                <span class="unit">¥</span>
              </div>
            </div>
            <div class="field-row">
              <span class="field-label">Фрахт Китай → Москва (Ф)</span>
              <div class="field-input-group">
                <input type="number" id="freight" value="10000" step="100">
                <span class="unit">USD</span>
              </div>
            </div>
            <div class="field-row">
              <span class="field-label">Курс CNY/RUB (юань → рубль)</span>
              <div class="field-input-group">
                <input type="number" id="kursCny" value="13.2" step="0.05">
                <span class="unit">₽/¥</span>
              </div>
            </div>
            <div class="field-row">
              <span class="field-label">Курс USD/RUB (доллар → рубль)</span>
              <div class="field-input-group">
                <input type="number" id="kurs" value="95" step="0.1">
                <span class="unit">₽/$</span>
              </div>
            </div>
          </div>
        </div>

        <div class="panel" style="margin-bottom:20px;" id="volumePanel">
          <div class="panel-head sans" style="background:var(--navy-light);"><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h2v16h16v2H3zm4 12V8l9 9z"/></svg></span>Объём и вес (для категории C)</div>
          <div class="panel-body grid2">
            <div class="field-row">
              <span class="field-label">Объём вашего груза</span>
              <div class="field-input-group">
                <input type="number" id="myVolume" value="15" step="0.5">
                <span class="unit">м³</span>
              </div>
            </div>
            <div class="field-row">
              <span class="field-label">Объём полного контейнера</span>
              <div class="field-input-group">
                <input type="number" id="containerVolume" value="68" step="1">
                <span class="unit">м³</span>
              </div>
            </div>
            <div class="field-row">
              <span class="field-label">Вес вашего груза</span>
              <div class="field-input-group">
                <input type="number" id="myWeight" value="0" step="10" placeholder="0 = не учитывать">
                <span class="unit">кг</span>
              </div>
            </div>
            <div class="field-row">
              <span class="field-label">Грузоподъёмность контейнера</span>
              <div class="field-input-group">
                <input type="number" id="containerWeight" value="26800" step="100">
                <span class="unit">кг</span>
              </div>
            </div>
            <div style="font-size:12px;color:var(--muted);padding:4px 0 0">
              Доля = max(объём / объём конт., вес / грузоподъёмность) — как в расчёте маркетплейса
            </div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head sans" style="background:var(--green-dark);"><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l9 4.5V9H3V7.5zM5 11h2v6H5zm4 0h2v6H9zm4 0h2v6h-2zm4 0h2v6h-2zM3 19h18v2H3z"/></svg></span>Фиксированные ставки</div>
          <div class="panel-body grid2">
            <div class="field-row">
              <span class="field-label">Пошлина</span>
              <div class="field-input-group">
                <input type="number" id="dutyRate" value="10" step="0.5">
                <span class="unit">%</span>
              </div>
            </div>
            <div class="field-row">
              <span class="field-label">НДС</span>
              <div class="field-input-group">
                <input type="number" id="vatRate" value="22" step="0.5">
                <span class="unit">%</span>
              </div>
            </div>
            <div class="field-row">
              <span class="field-label">Услуги станции</span>
              <div class="field-input-group">
                <input type="number" id="station" value="150000" step="1000">
                <span class="unit">₽</span>
              </div>
            </div>
            <div class="field-row">
              <span class="field-label">Услуги брокера</span>
              <div class="field-input-group">
                <input type="number" id="broker" value="35000" step="1000">
                <span class="unit">₽</span>
              </div>
            </div>
            <div class="field-row">
              <span class="field-label">Доставка до клиента</span>
              <div class="field-input-group">
                <input type="number" id="delivery" value="250000" step="1000">
                <span class="unit">₽</span>
              </div>
            </div>
            <div class="field-row">
              <span class="field-label">Перевод денег в Китай</span>
              <div class="field-input-group">
                <input type="number" id="transferRate" value="3" step="0.5">
                <span class="unit">%</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT COLUMN: RESULTS -->
      <div>
        <div id="resultsABC">
          <div class="panel" style="margin-bottom:20px;">
            <div class="panel-head navy2 sans"><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zm2 4h4v2H7zm6 0h4v2h-4zM7 11h4v2H7zm6 0h4v2h-4zm-6 4h4v2H7zm6 0h4v2h-4z"/></svg></span>Базовые элементы</div>
            <div class="panel-body" id="baseElements"></div>
          </div>

          <div class="panel" style="margin-bottom:20px;" id="blockBreakdown">
            <div class="panel-head sans" id="blockBreakdownHead" style="background:var(--navy-light);"><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4a2 2 0 0 1 2-2h12v20H6a2 2 0 0 1-2-2zm4 2v2h8V6z"/></svg></span>Расчёт по формуле категории</div>
            <div class="panel-body" id="blockBreakdownBody"></div>
          </div>

          <div class="final-card">
            <div class="eyebrow sans">Итоговая стоимость поставки</div>
            <div class="final-numbers">
              <div class="final-num-block">
                <div class="num gold" id="finalUsd">$0</div>
                <div class="lbl sans">долларовая часть</div>
              </div>
              <div class="final-num-block">
                <div class="num" id="finalRubPart">0 ₽</div>
                <div class="lbl sans">рублёвая часть</div>
              </div>
              <div class="final-num-block">
                <div class="num gold" id="finalTotalRub">0 ₽</div>
                <div class="lbl sans">всего в рублях по курсу</div>
              </div>
            </div>
          </div>
        </div>

        <div id="resultsSpecial" class="hidden">
          <div class="special-note sans">
            <p id="specialText"></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ REFERENCE TAB ============ -->
  <div id="tab-ref" class="hidden">
    <div class="panel">
      <div class="panel-head sans">Классификация клиентов — справочник</div>
      <div class="panel-body" style="padding:0;">
        <table class="ref-table">
          <thead>
            <tr>
              <th>Кат.</th>
              <th>Тип клиента</th>
              <th>Наценка / Коэф.</th>
              <th>Признак</th>
              <th>Формула</th>
            </tr>
          </thead>
          <tbody id="refTableBody"></tbody>
        </table>
      </div>
    </div>

    <div class="footnote sans">
      📌 3% на перевод денег в Китай — всегда последний шаг, начисляется на итоговую сумму после всех наценок и коэффициентов<br>
      📌 Все новые B-клиенты стартуют с B1 (10%). Переход на B2 (5%) — при объёме от 1 контейнера в месяц<br>
      📌 НДС на доставку облагается только китайское плечо (50% фрахта). Российское плечо НДС не облагается
    </div>
  </div>

  <div class="footnote sans">
    ООО «Интернациональная Торговая Компания» · itcpartner.ru · +7 (978) 500-44-02 · Конфиденциально, только для внутреннего использования
  </div>

</div>

<script>
(function(){

const CATEGORIES = {
  A1: { type: "Новый клиент", markup: 0.15, sign: "1 контейнер, ИТК самостоятельно ищет фабрику", formula: "(База × 1,15) × 1,03", group: "AB" },
  A2: { type: "Активный клиент", markup: 0.10, sign: "~1 контейнер/мес., ИТК ищет фабрику", formula: "(База × 1,10) × 1,03", group: "AB" },
  A3: { type: "Постоянный клиент", markup: 0.05, sign: "Регулярные заказы, ИТК ищет фабрику", formula: "(База × 1,05) × 1,03", group: "AB" },
  B1: { type: "Своя фабрика (старт)", markup: 0.10, sign: "До 1 контейнера в 3 мес. (по умолч. для новых)", formula: "(База × 1,10) × 1,03", group: "AB" },
  B2: { type: "Своя фабрика (активный)", markup: 0.05, sign: "1+ контейнеров/мес.", formula: "(База × 1,05) × 1,03", group: "AB" },
  C0: { type: "Сборный груз — особые условия", coef: 2.0, goodsMarkup: 0.25, sign: "Лог. ×2,0 + товар 25% + 3%", formula: "(Б1 + Б2×2,0) × 1,03", group: "C" },
  C1: { type: "Сборный груз — малый объём", coef: 2.0, goodsMarkup: 0.15, sign: "Лог. ×2,0 + товар 15% + 3%", formula: "(Б1 + Б2×2,0) × 1,03", group: "C" },
  C2: { type: "Сборный груз — средний объём", coef: 1.8, goodsMarkup: 0.15, sign: "Лог. ×1,8 + товар 15% + 3%", formula: "(Б1 + Б2×1,8) × 1,03", group: "C" },
  C3: { type: "Сборный груз — полконтейнера+", coef: 1.5, goodsMarkup: 0.15, sign: "Лог. ×1,5 + товар 15% + 3%", formula: "(Б1 + Б2×1,5) × 1,03", group: "C" },
  VIP: { type: "Крупные сети", sign: "Ручной расчёт каждого заказа, решение руководства", formula: "Индивидуально + 3%", group: "SPECIAL" },
  K: { type: "Карго (серый импорт)", sign: "Вес × тариф по плотности груза", formula: "Вес × тариф $/кг", group: "SPECIAL" },
};

const fmt = (n, decimals=0) => {
  if (isNaN(n)) return "0";
  return n.toLocaleString('ru-RU', {minimumFractionDigits: decimals, maximumFractionDigits: decimals});
};

function getVal(id) {
  const v = parseFloat(document.getElementById(id).value);
  return isNaN(v) ? 0 : v;
}

function calculate() {
  const cat = document.getElementById('category').value;
  const info = CATEGORIES[cat];

  document.getElementById('catDesc').textContent = info.sign;
  var qlb = document.getElementById('q-cat-label');    // подпись на кнопке выбора
  if (qlb && typeof qCatTitle === 'function') qlb.textContent = qCatTitle(cat);
  document.getElementById('catFormula').textContent = info.formula;

  const T_cny = getVal('goods');           // товар в юанях
  const F_usd = getVal('freight');         // фрахт в долларах
  const kursCny = getVal('kursCny');       // ¥ → ₽
  const kursUsd = getVal('kurs');          // $ → ₽
  const dutyRate = getVal('dutyRate') / 100;
  const vatRate = getVal('vatRate') / 100;
  const station = getVal('station');
  const broker = getVal('broker');
  const delivery = getVal('delivery');
  const transferRate = getVal('transferRate') / 100;
  const myVol = getVal('myVolume');
  const contVol = getVal('containerVolume');
  const myWeight = getVal('myWeight');
  const contWeight = getVal('containerWeight') || 26800;

  // Доля = max(доля объёма, доля веса) — как в calcLandedRub маркетплейса
  const shareVol = (contVol > 0) ? myVol / contVol : 0;
  const shareWt  = (contWeight > 0 && myWeight > 0) ? myWeight / contWeight : 0;
  const shareMax = Math.max(shareVol, shareWt);

  // Товар переводим в рубли — от него считаются пошлина и НДС (в рублях!)
  const T_rub = T_cny * kursCny;
  const duty = T_rub * dutyRate;          // пошлина, ₽
  const vatGoods = T_rub * vatRate;       // НДС на товар, ₽
  // НДС на доставку считается с половины фрахта, переведённой в рубли
  const F_rub = F_usd * kursUsd;
  // Для сборного груза (категория C) используем max(доля объёма, доля веса).
  // Для A/B (полный контейнер) — со всего фрахта.
  const freightShare = (info.group === 'C') ? shareMax : 1;
  const vatFreight = (F_rub * freightShare / 2) * vatRate; // НДС на доставку, ₽

  document.getElementById('volumePanel').classList.toggle('hidden', info.group !== 'C');

  // Base elements display — всё в рублях, кроме фрахта (он $, показываем и ₽-эквивалент)
  document.getElementById('baseElements').innerHTML = `
    <div class="res-row"><span class="res-label">Товар Т = ¥${fmt(T_cny)} × курс ${fmt(kursCny,2)}</span><span class="res-val">${fmt(T_rub)} ₽</span></div>
    <div class="res-row"><span class="res-label">Фрахт Ф = $${fmt(F_usd)} × курс ${fmt(kursUsd,2)}</span><span class="res-val">${fmt(F_rub)} ₽</span></div>
    <div class="res-row"><span class="res-label">Пошлина = Т(₽) × ${(dutyRate*100).toFixed(0)}%</span><span class="res-val">${fmt(duty)} ₽</span></div>
    <div class="res-row"><span class="res-label">НДС на товар = Т(₽) × ${(vatRate*100).toFixed(0)}%</span><span class="res-val">${fmt(vatGoods)} ₽</span></div>
    <div class="res-row"><span class="res-label">НДС на доставку = (Ф(₽)${info.group === 'C' ? '×доля' : ''}/2) × ${(vatRate*100).toFixed(0)}%</span><span class="res-val">${fmt(vatFreight)} ₽</span></div>
  `;

  let finalUsd = 0, finalRub = 0;

  if (info.group === 'AB') {
    document.getElementById('resultsABC').classList.remove('hidden');
    document.getElementById('resultsSpecial').classList.add('hidden');
    document.getElementById('blockBreakdownHead').style.background = '#128A04';
    document.getElementById('blockBreakdownHead').textContent = '📗 Расчёт A/B';

    // Фрахт остаётся отдельной $-составляющей (стоимость контейнера),
    // всё остальное — рубли
    const baseUsd = F_usd;
    const baseRub = T_rub + duty + vatGoods + vatFreight + station + broker + delivery;
    const afterUsd = baseUsd * (1 + info.markup);
    const afterRub = baseRub * (1 + info.markup);
    finalUsd = afterUsd * (1 + transferRate);
    finalRub = afterRub * (1 + transferRate);

    document.getElementById('blockBreakdownBody').innerHTML = `
      <div class="res-row"><span class="res-label">База $ = Фрахт (контейнер)</span><span class="res-val">$${fmt(baseUsd)}</span></div>
      <div class="res-row"><span class="res-label">База ₽ = Товар+Пошлина+НДС тов.+НДС дост.+Станция+Брокер+Доставка</span><span class="res-val">${fmt(baseRub)} ₽</span></div>
      <div class="res-row sub-total"><span class="res-label">После наценки ${(info.markup*100).toFixed(0)}% ($)</span><span class="res-val">$${fmt(afterUsd)}</span></div>
      <div class="res-row sub-total"><span class="res-label">После наценки ${(info.markup*100).toFixed(0)}% (₽)</span><span class="res-val">${fmt(afterRub)} ₽</span></div>
    `;

  } else if (info.group === 'C') {
    document.getElementById('resultsABC').classList.remove('hidden');
    document.getElementById('resultsSpecial').classList.add('hidden');
    document.getElementById('blockBreakdownHead').style.background = '#1F3FB0';
    document.getElementById('blockBreakdownHead').textContent = '📙 Расчёт C (сборный груз)';

    const share = shareMax;
    // Блок1: товар + налоги (всё в рублях) × 1.15
    const block1Rub = (T_rub + duty + vatGoods + vatFreight) * (1 + info.goodsMarkup);
    // Блок2: фрахт ($, доля от контейнера × коэф.) — остаётся в долларах
    const block2Usd = F_usd * share * info.coef;
    // Блок2 рублёвая часть: станция+брокер+доставка × доля × коэф.
    const block2Rub = (station + broker + delivery) * share * info.coef;

    finalUsd = block2Usd * (1 + transferRate);
    finalRub = (block1Rub + block2Rub) * (1 + transferRate);

    const shareVolPct = (shareVol * 100).toFixed(1);
    const shareWtPct  = (shareWt  * 100).toFixed(1);
    const shareNote = myWeight > 0
      ? `max(объём ${shareVolPct}%, вес ${shareWtPct}%) = <b>${(share*100).toFixed(1)}%</b>`
      : `по объёму: <b>${(share*100).toFixed(1)}%</b>`;

    document.getElementById('blockBreakdownBody').innerHTML = `
      <div class="res-row"><span class="res-label">Доля (${fmt(myVol,1)} м³ / ${fmt(contVol,1)} м³${myWeight > 0 ? `, ${fmt(myWeight,0)} кг / ${fmt(contWeight,0)} кг` : ''})</span><span class="res-val">${shareNote}</span></div>
      <div class="res-row"><span class="res-label">Блок1 ₽ (товар+налоги) × ${(1+info.goodsMarkup).toFixed(2).replace('.', ',')}</span><span class="res-val">${fmt(block1Rub)} ₽</span></div>
      <div class="res-row"><span class="res-label">Блок2 $ фрахт (доля × ${info.coef})</span><span class="res-val">$${fmt(block2Usd)}</span></div>
      <div class="res-row sub-total"><span class="res-label">Блок2 ₽ (станция+брокер+дост.) × ${info.coef}</span><span class="res-val">${fmt(block2Rub)} ₽</span></div>
    `;

  } else {
    // VIP or K
    document.getElementById('resultsABC').classList.add('hidden');
    document.getElementById('resultsSpecial').classList.remove('hidden');
    const text = cat === 'VIP'
      ? '<b>Категория VIP</b> — крупные сетевые структуры. Каждый заказ рассчитывается индивидуально вручную. 3% на перевод денег в Китай включаются в итоговый расчёт. Отнесение к категории — только по решению руководства.<br><br>Обратитесь к руководителю отдела продаж для расчёта индивидуальных условий.'
      : '<b>Категория К (Карго)</b> — серый импорт. Стоимость рассчитывается по формуле: (вес товара + вес обрешётки) × тариф $/кг. Тариф определяется по таблице плотности груза.<br><br>Используйте отдельный калькулятор карго-тарифов.';
    document.getElementById('specialText').innerHTML = text;
    return;
  }

  document.getElementById('finalUsd').textContent = `$${fmt(finalUsd)}`;
  document.getElementById('finalRubPart').textContent = `${fmt(finalRub)} ₽`;
  document.getElementById('finalTotalRub').textContent = `${fmt(finalUsd * kursUsd + finalRub)} ₽`;
}

function renderRefTable() {
  const tbody = document.getElementById('refTableBody');
  tbody.innerHTML = Object.entries(CATEGORIES).map(([code, info]) => {
    const markupDisplay = info.markup !== undefined
      ? `${(info.markup*100).toFixed(0)}%`
      : info.coef !== undefined
        ? `×${info.coef}`
        : (code === 'VIP' ? 'Индивид.' : '$/кг');
    return `
      <tr>
        <td><span class="cat-pill">${code}</span></td>
        <td>${info.type}</td>
        <td><b>${markupDisplay}</b></td>
        <td>${info.sign}</td>
        <td style="font-family:'Courier New',monospace; color:#1565C0;">${info.formula}</td>
      </tr>
    `;
  }).join('');
}

// Tabs
document.querySelectorAll('.toggle-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.toggle-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    const target = tab.dataset.tab;
    document.getElementById('tab-calc').classList.toggle('hidden', target !== 'calc');
    document.getElementById('tab-ref').classList.toggle('hidden', target !== 'ref');
  });
});

/* Выбор категории в быстром расчёте — то же окно плитками, что и в полном.
   Значение по-прежнему лежит в поле с id category, поэтому расчёт, сохранение
   и история читают его как раньше. */
var Q_CAT_LIST = [
    {code:'A1', title:'A1 — Новый клиент (15%)'},
    {code:'A2', title:'A2 — Активный клиент (10%)'},
    {code:'A3', title:'A3 — Постоянный клиент (5%)'},
    {code:'B1', title:'B1 — Своя фабрика, старт (10%)'},
    {code:'B2', title:'B2 — Своя фабрика, активный (5%)'},
    {code:'C0', title:'C0 — Сборный груз, особые условия (лог. ×2,0, товар 25%)'},
    {code:'C1', title:'C1 — Сборный груз, малый объём (лог. ×2,0)'},
    {code:'C2', title:'C2 — Сборный груз, средний объём (лог. ×1,8)'},
    {code:'C3', title:'C3 — Сборный груз, полконтейнера+ (лог. ×1,5)'},
    {code:'VIP', title:'VIP — Крупные сети'},
    {code:'K', title:'К — Карго (серый импорт)'}
];
var Q_CAT_GROUPS = [
  {name:'Прямые поставки',              kind:'direct', codes:['A1','A2','A3','B1','B2']},
  {name:'Сборный груз (железная дорога)', kind:'rail',   codes:['C0','C1','C2','C3']},
  {name:'Особые условия',                kind:'special',codes:['VIP','K']}
];
function qCatTitle(code){
  for (var i=0;i<Q_CAT_LIST.length;i++) if (Q_CAT_LIST[i].code===code) return Q_CAT_LIST[i].title;
  return code;
}
function qCatPickOpen(){
  var cur = document.getElementById('category').value;
  var rows = '';
  Q_CAT_GROUPS.forEach(function(gr){
    rows += '<div class="catp-grp grp-'+gr.kind+'">'+gr.name+'</div>';
    gr.codes.forEach(function(code){
      var c = CATEGORIES[code]; if (!c) return;
      var on = (code === cur);
      rows += '<button type="button" class="catp-row grp-'+gr.kind+(on?' on':'')+'" data-qcat="'+code+'">'
           +    '<span class="catp-box">'+(on?'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>':'')+'</span>'
           +    '<span class="catp-tx"><span class="catp-n">'+qCatTitle(code)+'</span>'
           +      (c.sign ? '<span class="catp-d">'+c.sign+'</span>' : '')
           +      (c.formula ? '<span class="catp-f">'+c.formula+'</span>' : '')
           +    '</span></button>';
    });
  });
  /* ЗАМОК ПРОКРУТКИ.
     Пока открыто окно поверх страницы, сама страница стоять должна. Просто
     спрятать прокрутку мало: на телефоне страница отматывается в начало.
     Поэтому запоминаем, где человек стоял, придерживаем страницу на месте и
     возвращаем на то же место при закрытии. */
  /* Страховка: если окно почему-то убрали не через close (например, кнопкой
     «Назад», которая разбирает окна сама), замок всё равно снимется — следим
     за тем, осталось ли на странице хоть одно окно, требующее замка. */
  if (!window._ecoLockWatch) {
    window._ecoLockWatch = true;
    try {
      new MutationObserver(function() {
        if (!document.querySelector('[data-eco-lock]') && document.body.classList.contains('eco-locked')) {
          window.ecoLockScroll(false);
        }
      }).observe(document.body, { childList: true });
    } catch (e) {}
  }

  window.ecoLockScroll = window.ecoLockScroll || function(on) {
    try {
      var b = document.body;
      if (on) {
        if (b.classList.contains('eco-locked')) return;
        var y = window.pageYOffset || document.documentElement.scrollTop || 0;
        b.setAttribute('data-eco-y', String(y));
        b.style.top = (-y) + 'px';
        b.classList.add('eco-locked');
        document.documentElement.classList.add('eco-locked');
      } else {
        if (!b.classList.contains('eco-locked')) return;
        var y2 = parseInt(b.getAttribute('data-eco-y') || '0', 10) || 0;
        b.classList.remove('eco-locked');
        document.documentElement.classList.remove('eco-locked');
        b.style.top = '';
        b.removeAttribute('data-eco-y');
        window.scrollTo(0, y2);
      }
    } catch (e) {}
  };

  var ov = document.createElement('div');
  ov.className = 'catp-ov';
  ov.setAttribute('data-eco-lock','1');
  ov.innerHTML = '<div class="catp-box2"><div class="catp-head"><span>Категория сделки</span>'
               + '<button type="button" class="catp-x">\u00d7</button></div>'
               + '<div class="catp-list">'+rows+'</div>'
               + '<div class="catp-legend">Цвет слева — вид доставки: синий прямые поставки · фиолетовый железная дорога · серый особые условия</div></div>';
  document.body.appendChild(ov);
  if (window.ecoLockScroll) window.ecoLockScroll(true);
  /* Окно — отдельный шаг для кнопки «Назад»: первое нажатие закрывает его,
     а не уводит с экрана. */
  /* шаг истории не добавляем: «Назад» разбирает окна сама */
  var close = function(){
    if (ov.parentNode) ov.parentNode.removeChild(ov);
    if (window.ecoLockScroll) window.ecoLockScroll(false);
  };
  ov.addEventListener('click', function(e){ if (e.target === ov) close(); });
  ov.querySelector('.catp-x').addEventListener('click', close);
  var rws = ov.querySelectorAll('.catp-row');
  for (var k=0;k<rws.length;k++) (function(b){
    b.addEventListener('click', function(){
      var code = b.getAttribute('data-qcat');
      var f = document.getElementById('category');
      f.value = code;
      var lb = document.getElementById('q-cat-label'); if (lb) lb.textContent = qCatTitle(code);
      close();
      try { f.dispatchEvent(new Event('change')); } catch(e) { calculate(); }
    });
  })(rws[k]);
}
document.getElementById('q-cat-btn').addEventListener('click', qCatPickOpen);

// Bind inputs
['category','goods','freight','kursCny','kurs','dutyRate','vatRate','station','broker','delivery','transferRate','myVolume','containerVolume','myWeight','containerWeight']
  .forEach(id => {
    document.getElementById(id).addEventListener('input', calculate);
    document.getElementById(id).addEventListener('change', calculate);
  });

window.ITC_CATS_QUICK = CATEGORIES;
window.ITC_REFRESH_QUICK = function(){ try{ renderRefTable(); calculate(); }catch(e){} };
renderRefTable();
calculate();

// Кнопка сохранения для быстрого расчёта
window.saveQuickCalc = function() {
  openSaveModal(function() {
    var gv2 = function(id, def) {
      var el = document.getElementById(id);
      return el ? (parseFloat(el.value) || def || 0) : (def || 0);
    };
    var gs = function(id) {
      var el = document.getElementById(id);
      return el ? el.value : '';
    };
    // Итоги из DOM
    var finalUsd = 0, finalRub = 0, totalRub = 0;
    try {
      var nums = document.querySelectorAll('#section-calc .final-num-block .num');
      if (nums[0]) finalUsd = parseFloat(nums[0].textContent.replace(/[^\d.]/g,'')) || 0;
      if (nums[1]) finalRub = parseFloat(nums[1].textContent.replace(/[^\d.]/g,'')) || 0;
      if (nums[2]) totalRub = parseFloat(nums[2].textContent.replace(/[^\d.]/g,'')) || 0;
    } catch(e) {}
    return {
      type: 'quick',
      category: gs('category'),
      goods: gv2('goods'),
      freight: gv2('freight'),
      cnyRate: gv2('kursCny', 13.2),
      usdRate: gv2('kurs', 95),
      finalUsd: Math.round(finalUsd),
      finalRub: Math.round(finalRub),
      totalRub: Math.round(totalRub),
    };
  });
};

})();
</script>

<div style="padding:0 16px 20px">
  <button class="btn-save-calc sans" data-action="save-quick">💾 Сохранить расчёт</button>
</div>

</div>

<!-- ===== ЧЕСТНЫЙ ЗНАК ===== -->
<div id="section-hz" class="app-section">
  <div class="section-topbar">
    <button class="back-btn" onclick="showHub()">&#8592; Назад</button>
    <span class="section-topbar-title">Честный знак</span>
  </div>
<div class="brand-bar">
    <div class="brand-bar-logo">
      <img src="/eco/logo1.png" alt="ИТК">
    </div>
    <div class="brand-bar-text">
      <div class="brand-bar-title">ИТК — Честный знак</div>
      <div class="brand-bar-sub">Классификатор обязательной маркировки</div>
    </div>
  </div>

  <div class="container">
  <p id="h-dbDate" class="sub" style="text-align:center;font-size:0.78rem;color:#6b7a99;margin:0 0 6px;"></p>

  <div class="disclaimer">
    Инструмент определяет обязательность маркировки по укрупнённым товарным группам и носит справочный характер. Перечень кодов ТН ВЭД/ОКПД2 постоянно расширяется отдельными постановлениями Правительства РФ. Для точной проверки конкретного товара по коду ТН ВЭД используйте официальный сервис на <a href="https://xn--80ajghhoc2aj1c8b.xn--p1ai/" target="_blank" rel="noopener">честныйзнак.рф</a>.
  </div>

  <div class="search-panel">
    <label class="search-label" for="searchInput">Поиск по названию товара</label>
    <input type="text" id="h-searchInput" placeholder="например: кроссовки, молоко, шампунь, шоколад…" autocomplete="off">
    <p class="hint">Введите название товара — система покажет, входит ли он в товарную группу, подлежащую обязательной маркировке «Честный знак».</p>
  </div>

  <div class="selected-panel" id="h-selectedPanel">
    <div class="selected-label">Результат проверки</div>
    <div class="selected-main">
      <div class="selected-verdict-block">
        <span class="verdict-text" id="h-selectedVerdict">—</span>
        <div class="selected-cat-name" id="h-selectedName">—</div>
      </div>
    </div>
    <div class="selected-meta" id="h-selectedMeta"></div>
    <div class="selected-note" id="h-selectedNote" style="display:none;"></div>
    <div class="selected-actions">
      <a class="selected-link-btn" id="h-selectedLinkBtn" href="https://xn--80ajghhoc2aj1c8b.xn--p1ai/" target="_blank" rel="noopener">Проверить на честныйзнак.рф</a>
      <button class="selected-close-btn" id="h-selectedCloseBtn">Закрыть</button>
    </div>
  </div>

  <div class="status-row" id="h-statusRow" style="display:none;"></div>

  <div id="h-results"></div>

</div>

<footer class="app-footer">
  Данные основаны на постановлениях Правительства РФ и публичных календарях маркировки «Честный знак».<br>
  Сервис не является официальным источником — финальную проверку всегда делайте по коду ТН ВЭД/ОКПД2 на честныйзнак.рф.
</footer>

<script id="cz-data" type="application/json">
[
{"name":"Табак и табачная продукция","status":"active","since":"с 2019–2021 года, поэтапно","decree":"Постановление Правительства РФ № 224 от 28.02.2019","note":"Маркировка сигарет и прочей табачной продукции обязательна для всех участников оборота.","keywords":["сигареты","табак","сигары","сигариллы","папиросы","табак трубочный","табак для кальяна","нюхательный табак","насвай"]},
{"name":"Электронные сигареты и жидкости для вейпов","status":"upcoming","since":"проект постановления, точная дата пока не установлена","decree":"—","note":"Уже подготовлен проект постановления о маркировке этой группы, но обязательная маркировка ещё не введена.","keywords":["электронная сигарета","вейп","жидкость для вейпа","под-система","одноразовая электронная сигарета","картридж для вейпа"]},
{"name":"Лекарственные средства","status":"active","since":"с 2019–2020 года","decree":"Постановление Правительства РФ № 1556 от 14.12.2018","note":"Маркировка лекарств ведётся в отдельной системе МДЛП в составе ГИС МТ, действует поэкземплярный учёт.","keywords":["лекарство","таблетки","медикамент","препарат лекарственный","лекарственное средство","сироп лекарственный","капсулы лекарственные","мазь лекарственная"]},
{"name":"Обувь","status":"active","since":"с 2020 года","decree":"Постановление Правительства РФ № 860 от 05.07.2019","note":"Маркировке подлежит любая обувь независимо от материала и назначения.","keywords":["обувь","туфли","ботинки","кроссовки","кеды","сапоги","сандалии","тапочки","шлёпанцы","ботильоны","валенки","мокасины"]},
{"name":"Духи и туалетная вода","status":"active","since":"с 1 октября 2020 года","decree":"Постановление Правительства РФ № 1957 от 31.12.2019","note":"С 1 апреля 2026 года перечень парфюмерных позиций дополнительно расширен.","keywords":["духи","туалетная вода","парфюм","одеколон","парфюмерная вода"]},
{"name":"Фотоаппараты и фотовспышки","status":"active","since":"с 1 октября 2020 года","decree":"Постановление Правительства РФ № 1953 от 31.12.2019","note":"","keywords":["фотоаппарат","фотокамера","цифровой фотоаппарат","фотовспышка","объектив фотоаппарата"]},
{"name":"Шины и покрышки","status":"active","since":"с 1 ноября 2020 года","decree":"Постановление Правительства РФ № 1958 от 31.12.2019","note":"","keywords":["шины","покрышки","резина автомобильная","шины летние","шины зимние","шины грузовые","автошины"]},
{"name":"Одежда, бельё и текстиль лёгкой промышленности","status":"active","since":"с 2021 года; перечень расширяется поэтапно, 4-я волна — с 1 марта 2026","decree":"Постановление Правительства РФ № 1956 от 31.12.2019","note":"С 1 марта 2026 года в перечень добавлены новые позиции лёгкой промышленности (согласно ПП РФ № 2129 от 25.12.2025).","keywords":["одежда","куртка","футболка","платье","брюки","юбка","бельё нижнее","постельное бельё","носки","перчатки","свитер","толстовка","пальто","костюм","рубашка","спецодежда","рабочая одежда","трикотаж","халат","штаны","джинсы"]},
{"name":"Натуральный мех и меховые изделия","status":"active","since":"с 2016 года (метки КиЗ); переход на коды Data Matrix — с 1 марта 2026","decree":"Постановление № 787 от 11.08.2016; № 75 от 31.01.2026","note":"Старые RFID-метки КиЗ заменены кодами Data Matrix, введена обязательная регистрация в новой группе «Натуральный мех».","keywords":["шуба","дублёнка","мех норковый","мех песцовый","меховое изделие","меховой жилет","меховая шапка"]},
{"name":"Молочная продукция","status":"active","since":"с 2021 года, поэтапно","decree":"Постановление Правительства РФ № 2099 от 15.12.2020","note":"Крупнейшая по объёму выпускаемых кодов товарная группа в системе.","keywords":["молоко","кефир","йогурт","творог","сметана","сыр","масло сливочное","ряженка","простокваша","мороженое","сгущённое молоко"]},
{"name":"Упакованная вода","status":"active","since":"с 2021 года","decree":"Постановление Правительства РФ № 841 от 31.05.2021","note":"","keywords":["вода питьевая","вода минеральная","вода газированная","вода негазированная","бутилированная вода"]},
{"name":"Пиво и слабоалкогольные напитки","status":"active","since":"с 2023–2024 года, поэтапно","decree":"Постановление Правительства РФ № 2173 от 30.11.2022","note":"Маркировка интегрирована с системой ЕГАИС. С 1 марта 2026 года для безалкогольного пива введён поэкземплярный учёт.","keywords":["пиво","сидр","пуаре","медовуха","слабоалкогольный напиток","безалкогольное пиво"]},
{"name":"Антисептики и дезинфицирующие средства","status":"active","since":"с 2023 года, перечень расширен с 1 марта 2025","decree":"Постановление Правительства РФ № 894 от 31.05.2023","note":"С 1 сентября 2026 года вводится обязательная регистрация участников оборота.","keywords":["антисептик","дезинфицирующее средство","антисептический спрей","санитайзер","антисептик для рук"]},
{"name":"Биологически активные добавки (БАД)","status":"active","since":"с 2023 года, перечень расширен с 1 марта 2026 (ферменты, рыбий жир, дрожжи, активированный уголь, экстракты)","decree":"Постановление Правительства РФ № 894 от 31.05.2023","note":"31 августа 2026 года — последний срок маркировки товарных остатков по расширенному перечню.","keywords":["бад","биологически активная добавка","пищевая добавка биологическая","рыбий жир","активированный уголь"]},
{"name":"Кресла-коляски","status":"active","since":"с 2023 года","decree":"Постановление Правительства РФ № 894 от 31.05.2023","note":"С 1 сентября 2026 года вводится обязательная регистрация участников оборота.","keywords":["кресло-коляска","инвалидная коляска"]},
{"name":"Технические средства реабилитации","status":"active","since":"с 2024 года; новые позиции — с 1 февраля 2026","decree":"Постановление Правительства РФ № 744 от 31.05.2024","note":"С 1 февраля 2026 года в перечень добавлены моче- и калоприёмники.","keywords":["технические средства реабилитации","костыли","трость опорная","ходунки","ортопедическое изделие","мочеприёмник","калоприёмник","протез"]},
{"name":"Отдельные виды медицинских изделий","status":"active","since":"с 2023–2025 года, поэтапно","decree":"Постановление Правительства РФ № 894 от 31.05.2023","note":"С 1 сентября 2026 года вводится обязательная регистрация участников оборота.","keywords":["медицинское изделие","медицинский прибор","изделие медицинского назначения","тонометр","глюкометр"]},
{"name":"Велосипеды","status":"active","since":"с 2024 года","decree":"Постановление Правительства РФ № 645 от 23.05.2024","note":"","keywords":["велосипед","велосипедная рама","велосипед детский","велосипед горный","велосипед городской"]},
{"name":"Икра осетровых и лососёвых рыб","status":"active","since":"с 2024 года","decree":"Постановление Правительства РФ № 2028 от 29.11.2023","note":"","keywords":["икра красная","икра чёрная","икра лососёвая","икра осетровая"]},
{"name":"Соки, нектары и безалкогольные напитки","status":"active","since":"с 2023–2024 года, поэтапно","decree":"Постановление Правительства РФ № 887 от 31.05.2023","note":"С 1 марта 2026 года введён поэкземплярный учёт.","keywords":["сок","нектар","морс","лимонад","газировка","безалкогольный напиток","смузи"]},
{"name":"Растительные масла","status":"active","since":"с 2024–2025 года, поэтапно","decree":"Постановление Правительства РФ № 676 от 27.05.2024","note":"","keywords":["масло подсолнечное","масло оливковое","масло растительное","масло рапсовое","масло кукурузное","масло льняное"]},
{"name":"Корм для животных","status":"active","since":"с 2024–2025 года, поэтапно","decree":"Постановление Правительства РФ № 674 от 27.05.2024","note":"","keywords":["корм для собак","корм для кошек","корм для животных","сухой корм","влажный корм"]},
{"name":"Парфюмерно-косметическая продукция и бытовая химия","status":"active","since":"с 2025 года, поэтапно (1 мая — 1 декабря)","decree":"Постановление Правительства РФ № 1681 от 30.11.2024","note":"С 1 июля 2026 года требуется сканирование кодов на кассе и передача сведений в ОСУ через ЭДО.","keywords":["шампунь","крем для лица","гель для душа","стиральный порошок","моющее средство","чистящее средство","зубная паста","дезодорант","косметика","мыло","кондиционер для волос"]},
{"name":"Спортивное питание","status":"active","since":"с 1 октября 2025 года","decree":"Постановление Правительства РФ № 811 от 31.05.2025","note":"","keywords":["протеин","спортивное питание","гейнер","аминокислоты спортивные","креатин"]},
{"name":"Отдельные виды стройматериалов","status":"active","since":"с 1 октября 2025 года; обязательный ЭДО — с декабря 2026","decree":"Постановление Правительства РФ № 820 от 31.05.2025","note":"Маркируются не все стройматериалы, а отдельные позиции из перечня — точный список лучше уточнять по коду ТН ВЭД.","keywords":["стройматериалы","лакокрасочные материалы","сухие строительные смеси","краска строительная"]},
{"name":"Автомобильные жидкости","status":"active","since":"с 1 сентября 2025 года; поэкземплярный учёт — с 1 апреля 2026","decree":"Постановление Правительства РФ № 1683 от 30.11.2024","note":"","keywords":["антифриз","тормозная жидкость","незамерзайка","омыватель стекла","моторное масло","трансмиссионное масло"]},
{"name":"Смазочные материалы (ГСМ)","status":"active","since":"с 1 апреля 2026 года","decree":"Постановление Правительства РФ № 1683 от 30.11.2024 (расширение перечня)","note":"","keywords":["смазочные материалы","машинное масло","техническая смазка","гсм","индустриальное масло"]},
{"name":"Бритвы и лезвия","status":"active","since":"с 1 декабря 2025 года","decree":"Постановление Правительства РФ № 808 от 31.05.2025","note":"","keywords":["бритва","бритвенный станок","лезвие для бритья","кассета для бритвы","сменное лезвие"]},
{"name":"Радиоэлектроника (отдельные группы)","status":"partial","since":"регистрация — с 2025–2026 года; нанесение кодов производителями и импортёрами — с 1 мая 2026","decree":"Постановление Правительства РФ № 1954 от 28.11.2025","note":"Переходный период ещё не завершён: маркировка остатков разрешена до 30 ноября 2026 года.","keywords":["смартфон","телефон мобильный","ноутбук","планшет","телевизор","наушники беспроводные","колонка портативная"]},
{"name":"Детские товары (игрушки и др.)","status":"partial","since":"2025–2026 год, поэтапно; розничный учёт — с 1 сентября 2026","decree":"Постановление Правительства РФ № 819 от 31.05.2025","note":"Период маркировки товарных остатков продолжается до 31 августа 2026 года.","keywords":["игрушка","кукла","конструктор детский","машинка игрушечная","мягкая игрушка","настольная игра детская"]},
{"name":"Бакалея — снеки и соусы","status":"active","since":"с 2025 года","decree":"Постановление Правительства РФ № 857 от 26.06.2024","note":"","keywords":["снеки","чипсы","сухарики","соус","кетчуп","майонез"]},
{"name":"Бакалея — макароны, мёд, мюсли","status":"upcoming","since":"с 1 сентября 2026 года","decree":"Постановление Правительства РФ № 186 от 25.02.2026","note":"Маркировка станет обязательной с 1 сентября 2026 года — на дату проверки ещё не введена.","keywords":["макароны","мёд","мюсли","картофельные хлопья"]},
{"name":"Бакалея — мука и крупы","status":"upcoming","since":"с 1 декабря 2026 года","decree":"Постановление Правительства РФ № 186 от 25.02.2026","note":"Маркировка станет обязательной с 1 декабря 2026 года — на дату проверки ещё не введена.","keywords":["мука","крупа","крупяная смесь","смесь для теста"]},
{"name":"Какао","status":"active","since":"с декабря 2025 года","decree":"Постановление Правительства РФ № 809 от 31.05.2025","note":"","keywords":["какао","какао-порошок","напиток какао"]},
{"name":"Чай","status":"active","since":"с 1 апреля 2026 года","decree":"Постановление Правительства РФ № 809 от 31.05.2025","note":"","keywords":["чай","чайные пакетики","чай листовой","мате","чай травяной"]},
{"name":"Кофе и цикорий","status":"active","since":"с 1 июня 2026 года","decree":"Постановление Правительства РФ № 809 от 31.05.2025","note":"","keywords":["кофе","растворимый кофе","кофе в зёрнах","молотый кофе","цикорий"]},
{"name":"Сахаристые кондитерские изделия (печенье, зефир, мармелад, вафли)","status":"active","since":"с 1 марта 2026 года","decree":"Постановление Правительства РФ № 818 от 31.05.2025","note":"","keywords":["печенье","зефир","мармелад","пастила","вафли"]},
{"name":"Торты, пирожные и выпечка длительного хранения","status":"active","since":"с 1 мая 2026 года","decree":"Постановление Правительства РФ № 818 от 31.05.2025","note":"","keywords":["торт","пирожное","рулет кондитерский","кекс длительного хранения"]},
{"name":"Шоколад, карамель, жевательная резинка","status":"upcoming","since":"с 1 июля 2026 года","decree":"Постановление Правительства РФ № 818 от 31.05.2025","note":"Маркировка станет обязательной с 1 июля 2026 года — на дату проверки ещё не введена.","keywords":["шоколад","карамель","жевательная резинка","драже","конфеты"]},
{"name":"Мясная продукция и колбасные изделия","status":"partial","since":"регистрация — с 1 марта 2026; нанесение кодов на готовую продукцию — с 1 августа 2026, на колбасы — с 1 октября 2026","decree":"Постановление Правительства РФ № 1682 от 30.11.2024","note":"Сейчас обязательна только регистрация участников оборота, нанесение кодов на товар ещё не началось.","keywords":["мясо охлаждённое","колбаса","сосиски","сардельки","ветчина","деликатесы мясные","паштет мясной"]},
{"name":"Ювелирные изделия и бижутерия","status":"not_applicable","since":"—","decree":"—","note":"Не входит в систему «Честный знак». Изделия из драгоценных металлов и камней контролируются отдельной системой ГИИС ДМДК (пробирная палата), не «Честным знаком».","keywords":["бижутерия","ювелирные изделия","украшения","серьги","кольцо","кольца","подвеска","цепочка золотая","браслет ювелирный"]},
{"name":"Канцелярские товары","status":"not_applicable","since":"—","decree":"—","note":"Канцелярские товары не входят в перечень обязательной маркировки «Честный знак» по состоянию на дату проверки.","keywords":["блокнот","тетрадь","ручка шариковая","карандаш","скрепка","папка для документов","открытка"]},
{"name":"Мебель","status":"not_applicable","since":"—","decree":"—","note":"Мебель пока не подлежит обязательной маркировке «Честный знак».","keywords":["мебель","диван","шкаф","стол кухонный","стул","кровать мебельная"]},
{"name":"Посуда и кухонная утварь","status":"not_applicable","since":"—","decree":"—","note":"Посуда и кухонная утварь не входят в перечень обязательной маркировки «Честный знак».","keywords":["посуда","тарелка","кастрюля","сковорода","кружка"]},
{"name":"Книги и печатная продукция","status":"not_applicable","since":"—","decree":"—","note":"Книги не входят в перечень обязательной маркировки «Честный знак». Для учебников уже подготовлен проект постановления.","keywords":["книга","печатная продукция","журнал бумажный","блокнот печатный"]},
{"name":"Учебники","status":"upcoming","since":"проект постановления, точная дата пока не установлена","decree":"—","note":"По учебникам уже подготовлен проект постановления о маркировке.","keywords":["учебник","учебное пособие школьное"]},
{"name":"Удобрения","status":"upcoming","since":"проект постановления, точная дата пока не установлена","decree":"—","note":"Подготовлен проект постановления о маркировке этой группы.","keywords":["удобрение","минеральное удобрение","удобрение для растений"]},
{"name":"Пиротехника","status":"upcoming","since":"проект постановления, точная дата пока не установлена","decree":"—","note":"Подготовлен проект постановления о маркировке этой группы.","keywords":["пиротехника","фейерверк","петарда","салют"]},
{"name":"Автозапчасти","status":"upcoming","since":"эксперимент продлён до 28 февраля 2026 года; проект постановления готовится","decree":"—","note":"Идёт эксперимент по маркировке автозапчастей, обязательная маркировка ещё не введена.","keywords":["автозапчасти","запчасти автомобильные","деталь автомобиля","автозапчасть"]},
{"name":"Средства гигиены","status":"upcoming","since":"проект постановления, точная дата пока не установлена","decree":"—","note":"Подготовлен проект постановления о маркировке этой группы.","keywords":["средства гигиены","гигиенические прокладки","влажные салфетки","подгузники"]}
]
</script>

<script>
(function(){

const RAW_DATA = JSON.parse(document.getElementById('cz-data').textContent);
const DB_DATE = '21.06.2026';

document.getElementById('h-dbDate').textContent = 'база актуальна на ' + DB_DATE + ' — ' + RAW_DATA.length + ' товарных групп';

const STATUS_INFO = {
  active:        { verdict: 'ТРЕБУЕТСЯ',         pill: 'pill-active',   panel: 's-active',   cardClass: 'st-active' },
  partial:       { verdict: 'ЧАСТИЧНО / ВВОДИТСЯ', pill: 'pill-partial',  panel: 's-partial',  cardClass: 'st-partial' },
  upcoming:      { verdict: 'СКОРО (пока нет)',   pill: 'pill-upcoming', panel: 's-upcoming', cardClass: 'st-upcoming' },
  not_applicable:{ verdict: 'НЕ ТРЕБУЕТСЯ',       pill: 'pill-not',      panel: 's-not',       cardClass: 'st-not' }
};

const DATA = RAW_DATA.map(r => ({
  ...r,
  nameLower: r.name.toLowerCase(),
  kwLower: r.keywords.map(k => k.toLowerCase()),
  words: ((r.name + ' ' + r.keywords.join(' ')).toLowerCase().match(/[а-яё]+/g) || [])
}));

function stem(word, len = 5) {
  return word.length <= len ? word : word.slice(0, len);
}

function normalizeQuery(q) {
  return q.toLowerCase().trim().match(/[а-яё]+/g) || [];
}

function escapeHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function highlightTerms(name, queryWords) {
  let html = escapeHtml(name);
  const stems = queryWords.map(w => stem(w)).filter(s => s.length >= 2);
  if (stems.length === 0) return html;
  const pattern = new RegExp('(' + stems.map(s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|') + ')([а-яёА-ЯЁ]*)', 'gi');
  html = html.replace(pattern, '<span class="hl">$1$2</span>');
  return html;
}

function search(query) {
  const queryWords = normalizeQuery(query);
  if (queryWords.length === 0) return [];

  const stems = queryWords.map(w => stem(w));

  const scored = [];
  for (const rec of DATA) {
    // Prefer exact / startswith matches against the explicit keyword list first
    let kwMatch = 0;
    for (const kw of rec.kwLower) {
      const kwWords = kw.match(/[а-яё]+/g) || [];
      for (let i = 0; i < stems.length; i++) {
        const st = stems[i];
        for (const kww of kwWords) {
          if (kww.startsWith(st)) { kwMatch++; break; }
        }
      }
    }

    let matchedStems = 0;
    let exactBonus = 0;
    for (let i = 0; i < stems.length; i++) {
      const st = stems[i];
      const fullWord = queryWords[i];
      let found = false;
      for (const w of rec.words) {
        if (w.startsWith(st)) {
          found = true;
          if (w === fullWord) exactBonus += 2;
          break;
        }
      }
      if (found) matchedStems++;
    }

    if (matchedStems > 0) {
      const score = (matchedStems / queryWords.length) * 100
                  + exactBonus
                  + kwMatch * 15
                  - (rec.name.length / 300);
      scored.push({ rec, score });
    }
  }

  scored.sort((a, b) => b.score - a.score);
  return scored;
}

const SUGGESTIONS = ['кроссовки', 'молоко', 'шампунь', 'шоколад', 'духи', 'смартфон', 'игрушка', 'кофе'];

function renderStart() {
  document.getElementById('h-statusRow').style.display = 'none';
  hideSelected();
  document.getElementById('h-results').innerHTML = `
    <div class="start-state">
      <div class="icon">✅</div>
      <h3>Нужна ли маркировка «Честный знак»?</h3>
      <p>Введите название товара выше — система покажет, входит ли он в товарную группу, подлежащую обязательной маркировке, и с какой даты.</p>
      <div class="suggestions">
        ${SUGGESTIONS.map(s => `<span class="suggestion-chip" data-q="${s}">${s}</span>`).join('')}
      </div>
    </div>
  `;
  document.querySelectorAll('.suggestion-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      document.getElementById('h-searchInput').value = chip.dataset.q;
      runSearch(chip.dataset.q);
    });
  });
}

function renderEmpty(query) {
  hideSelected();
  document.getElementById('h-results').innerHTML = `
    <div class="empty-state">
      <div class="icon">🔍</div>
      <h3>Товарная группа не найдена</h3>
      <p>По запросу «${escapeHtml(query)}» совпадений в базе нет. Это не означает, что маркировка точно не требуется — база охватывает основные товарные группы, но не все коды ТН ВЭД целиком.</p>
      <p style="margin-top:10px;">Попробуйте более общее название товара (например, не «зимние беговые кроссовки», а «кроссовки»), либо проверьте точно по коду ТН ВЭД.</p>
      <div class="empty-actions">
        <a class="empty-link-btn" href="https://xn--80ajghhoc2aj1c8b.xn--p1ai/" target="_blank" rel="noopener">Проверить на честныйзнак.рф →</a>
      </div>
    </div>
  `;
}

function renderResults(results, query, queryWords) {
  const total = results.length;
  const statusRow = document.getElementById('h-statusRow');
  statusRow.style.display = 'flex';

  let badgeClass = 'badge-ok', badgeText = `найдено: ${total}`;
  if (total === 0) {
    badgeClass = 'badge-none'; badgeText = 'не найдено';
  } else if (total > 10) {
    badgeClass = 'badge-many'; badgeText = `найдено: ${total} — уточните запрос`;
  }
  statusRow.innerHTML = `
    <span>Результаты по запросу «${escapeHtml(query)}»</span>
    <span class="badge ${badgeClass}">${badgeText}</span>
  `;

  if (total === 0) {
    renderEmpty(query);
    return;
  }

  const shown = results.slice(0, 10);
  const html = shown.map(({rec}, idx) => {
    const info = STATUS_INFO[rec.status];
    return `
      <div class="result-card ${info.cardClass}" data-idx="${DATA.indexOf(rec)}">
        <div class="card-top-row">
          <span class="name-text">${highlightTerms(rec.name, queryWords)}</span>
          <span class="badge ${info.pill}">${info.verdict}</span>
        </div>
        ${rec.note ? `<div class="card-note">${escapeHtml(rec.note)}</div>` : ''}
        <div class="card-since">${rec.since !== '—' ? 'Действует ' + escapeHtml(rec.since) : ''}</div>
      </div>
    `;
  }).join('');

  document.getElementById('h-results').innerHTML = html
    + (total > 10 ? `<p class="hint" style="text-align:center; margin-top:14px;">Показаны первые 10 из ${total} результатов. Уточните запрос для более точного списка.</p>` : '');

  document.querySelectorAll('.result-card').forEach(card => {
    card.addEventListener('click', () => {
      document.querySelectorAll('.result-card.expanded').forEach(c => c.classList.remove('expanded'));
      card.classList.add('expanded');
      showSelected(DATA[parseInt(card.dataset.idx, 10)]);
    });
  });
}

function showSelected(rec) {
  const info = STATUS_INFO[rec.status];
  const panel = document.getElementById('h-selectedPanel');
  panel.className = 'selected-panel visible ' + info.panel;

  document.getElementById('h-selectedVerdict').textContent = info.verdict;
  document.getElementById('h-selectedName').textContent = rec.name;

  let metaHtml = '';
  if (rec.since && rec.since !== '—') {
    metaHtml += `<div><b>Срок:</b> ${escapeHtml(rec.since)}</div>`;
  }
  if (rec.decree && rec.decree !== '—') {
    metaHtml += `<div style="margin-top:4px;"><b>Документ:</b> ${escapeHtml(rec.decree)}</div>`;
  }
  document.getElementById('h-selectedMeta').innerHTML = metaHtml;

  const noteEl = document.getElementById('h-selectedNote');
  if (rec.note) {
    noteEl.style.display = 'block';
    noteEl.textContent = rec.note;
  } else {
    noteEl.style.display = 'none';
  }

  panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function hideSelected() {
  document.getElementById('h-selectedPanel').classList.remove('visible');
  document.querySelectorAll('.result-card.expanded').forEach(c => c.classList.remove('expanded'));
}

document.getElementById('h-selectedCloseBtn').addEventListener('click', hideSelected);

let debounceTimer = null;
function runSearch(query) {
  query = query.trim();
  if (query.length === 0) {
    renderStart();
    return;
  }
  if (query.length < 2) {
    document.getElementById('h-statusRow').style.display = 'none';
    document.getElementById('h-results').innerHTML = `<p class="hint" style="text-align:center; padding:30px;">Введите минимум 2 символа</p>`;
    return;
  }
  const queryWords = normalizeQuery(query);
  const results = search(query);
  renderResults(results, query, queryWords);
}

document.getElementById('h-searchInput').addEventListener('input', (e) => {
  clearTimeout(debounceTimer);
  const val = e.target.value;
  debounceTimer = setTimeout(() => runSearch(val), 120);
});

renderStart();

})();
</script>
</div>

<!-- ===== КОД ТН ВЭД ===== -->
<div id="section-tnved" class="app-section">
  <div class="section-topbar">
    <button class="back-btn" onclick="showHub()">&#8592; Назад</button>
    <span class="section-topbar-title">Код ТН ВЭД</span>
  </div>
  

<div class="brand-bar">
    <div class="brand-bar-logo">
      <img src="/eco/logo1.png" alt="ИТК">
    </div>
    <div class="brand-bar-text">
      <div class="brand-bar-title">ИТК — Классификатор ТН ВЭД</div>
      <div class="brand-bar-sub">ООО «Интернациональная Торговая Компания»</div>
    </div>
  </div>


<div class="container">

  <div class="search-panel">
    <span class="search-label">Название товара</span>
    <input type="text" id="t-searchInput" placeholder="например: наушники беспроводные" autocomplete="off">
    <p class="hint">Введите название товара на русском языке. Чем точнее формулировка — тем точнее подбор кода. Если ничего не найдено — попробуйте другое слово (например, не «сумка», а «портфель» или «рюкзак»).</p>
  </div>

  <div id="t-selectedPanel" class="selected-panel">
    <div class="selected-label">✓ Выбранный код</div>
    <div class="selected-main">
      <div class="selected-code-block">
        <span class="code-num" id="t-selectedCode">—</span>
      </div>
      <div class="selected-duty-block">
        <div class="duty-label">Пошлина</div>
        <div class="duty-value" id="t-selectedDuty">—</div>
      </div>
    </div>
    <div class="selected-name" id="t-selectedName"></div>
    <div class="selected-actions">
      <button class="selected-copy-btn" id="t-selectedCopyBtn">Копировать код</button>
      <button class="selected-close-btn" id="t-selectedCloseBtn">Закрыть</button>
    </div>
  </div>

  <div id="t-statusRow" class="status-row" style="display:none;"></div>
  <div id="t-results"></div>

</div>

<footer>
  База ТН ВЭД ЕАЭС · <span id="t-dbDate"></span> · <span id="t-dbCount"></span> кодов · только для внутреннего использования
</footer>

<script>
/* СПРАВОЧНИК ТН ВЭД вынесен в отдельный файл: он весит около пяти мегабайт и
   раньше сидел прямо в этой странице, из-за чего она грузилась мучительно
   долго у всех, даже у тех, кто справочник не открывает. Теперь файл
   подтягивается только при первом заходе в раздел «Код ТН ВЭД». */
(function(){
  var loaded = false, loading = false;
  window.tnvedEnsure = function(){
    if (loaded || loading) return;
    loading = true;
    var note = document.getElementById('t-dbCount');
    if (note) note.textContent = 'загружаю…';
    var sc = document.createElement('script');
    sc.src = '/eco/tnved.js';
    sc.onload = function(){ loaded = true; loading = false; };
    sc.onerror = function(){
      loading = false;
      if (note) note.textContent = 'не загрузился';
      var box = document.getElementById('t-results');
      if (box) box.innerHTML = '<div style="padding:40px 20px;text-align:center;color:#A11A1A;font-family:sans-serif">Не удалось загрузить справочник. Проверьте связь и откройте раздел заново.</div>';
    };
    document.head.appendChild(sc);
  };
})();
</script>

</div>

<script>
/* ВЫХОД В ПРИЛОЖЕНИЕ.
   Экосистема открывается внутри приложения ИТК. Своей кнопки «выйти» у неё
   быть не должно — вместо этого она просит приложение закрыть её. Когда
   страницу открывают напрямую по адресу /eco/, ничего этого не происходит. */
var ITC_EMBED = (function(){ try{ return window.parent && window.parent !== window; }catch(e){ return false; } })();
function ecoBackToApp(){
  if (ITC_EMBED) { try{ window.parent.postMessage({itcEco:"close"}, "*"); }catch(e){} return; }
  /* Открыты целой страницей — возвращаемся туда, откуда пришли. */
  try{ if (document.referrer && document.referrer.indexOf(location.host) >= 0) { history.back(); return; } }catch(e){}
  try{ location.href = "/"; }catch(e){}
}
function onHubNow(){
  var h=document.getElementById('hub');
  return !!(h && h.style.display !== 'none');
}
function showSection(name) {
  if (name === 'tnved' && window.tnvedEnsure) window.tnvedEnsure();
  document.getElementById('hub').style.display = 'none';
  ['tnved','hz','calc','pi','fullcalc','clients','deals','expenses','summary','suppliers'].forEach(function(s) {
    var _s = document.getElementById('section-' + s); if (_s) _s.style.display = 'none';
  });
  document.getElementById('section-' + name).style.display = 'block';
  window.scrollTo(0, 0);
  // Добавляем запись в историю — Android-кнопка "Назад" вернёт на хаб
  history.pushState({ section: name }, '', '#' + name);
}
function showHub() {
  ['tnved','hz','calc','pi','fullcalc','clients','deals','expenses','summary','suppliers'].forEach(function(s) {
    var _s = document.getElementById('section-' + s); if (_s) _s.style.display = 'none';
  });
  document.getElementById('hub').style.display = 'flex';
  window.scrollTo(0, 0);
}

/* ===== ЭКРАНЫ ПОВЕРХ РАЗДЕЛА И КНОПКА «НАЗАД» =====
   История расчётов, просмотр записи и правка — это отдельные экраны во весь
   размер, лежащие поверх раздела. Раньше они жили сами по себе: системная
   кнопка «Назад» их не видела и уводила прямо в приложение, минуя всё
   открытое. Теперь при открытии каждого добавляется шаг в историю, а «Назад»
   закрывает верхний экран. Закрытие своей кнопкой тоже отматывает шаг —
   иначе шаги копились бы и «Назад» пришлось бы жать лишние разы. */
var ECO_POP_SKIP = 0;
window.ecoPanelOpen = function(id) {
  var p = document.getElementById(id);
  if (!p) return;
  p.style.display = 'block';
  try { history.pushState({ ecoPanel: id }, '', location.hash || ''); } catch (e) {}
};
window.ecoPanelClose = function(id) {
  var p = document.getElementById(id);
  if (!p || p.style.display === 'none') return;
  p.style.display = 'none';
  ecoHistBack();
};

// Обработчик кнопки "Назад" Android / браузерной стрелки
/* ============================================================
   РАЗБОР ОДНОГО ШАГА «НАЗАД» (переписано 22.08.2026).
   Раньше уровни разбирались только внутри popstate. Но в приложении
   системная кнопка до popstate НЕ доходит: оболочка спрашивает
   приложение, приложение — Экосистему, вызывая ecoNativeBack. Такой
   функции здесь не было, поэтому приложение считало, что разбирать
   нечего, и захлопывало окно целиком с любого экрана.
   ТЕПЕРЬ разбор живёт в одной функции, и ею пользуются оба пути.
   Возвращает true, если шаг разобран, и false — если возвращаться
   больше некуда (тогда окно Экосистемы закрывает приложение).
   ============================================================ */
function ecoBackStep(){
  /* Слои перечислены СВЕРХУ ВНИЗ, ровно в порядке наложения на экране
     (z-index): что лежит выше — закрывается первым.
       9600  окно КП и выбор клиента (.kp-ov)
       9001  выбор категории при сохранении (cp-overlay)
       9000  окно сохранения (sv-overlay), выбор категории (.catp-ov)
       8600  правка записи (ed-overlay)
       8500  просмотр записи (vw-overlay)
       8000  история расчётов (hs-panel)
     Ниже — формы внутри разделов, карточки CRM, затем сам раздел.

     ВОЗВРАЩАЕТ:
       false  — разбирать нечего, окно Экосистемы пора закрывать;
       'hist' — уровень закрыт, и при открытии он КЛАЛ шаг в историю;
       'ui'   — уровень закрыт, но шага в истории у него НЕ было.
     Это различие важно: иначе счётчик шагов разъезжается и «Назад»
     через раз перескакивает лишний уровень. */

  /* 1. Окна, которые создаются на лету, без шага истории. Их может быть
        несколько друг на друге — закрываем ВЕРХНЕЕ. */
  var _float = document.querySelectorAll('.kp-ov, .catp-ov');
  if (_float.length) {
    var _top = _float[_float.length - 1];
    if (_top.parentNode) _top.parentNode.removeChild(_top);
    return 'ui';
  }

  /* 2. Окна сохранения и выбора категории. Показываются как flex, шага
        истории не кладут. Раньше проверка была только на 'block', поэтому
        разбор их вообще не замечал. */
  var _plain = ['cp-overlay', 'sv-overlay'];
  for (var _j = 0; _j < _plain.length; _j++) {
    var _q = document.getElementById(_plain[_j]);
    if (_q && _q.style.display && _q.style.display !== 'none') { _q.style.display = 'none'; return 'ui'; }
  }

  /* 3. Панели, открытые через ecoPanelOpen — они шаг истории кладут. */
  var _stack = ['ed-overlay', 'vw-overlay', 'hs-panel'];
  for (var _i = 0; _i < _stack.length; _i++) {
    var _p = document.getElementById(_stack[_i]);
    if (_p && _p.style.display && _p.style.display !== 'none') { _p.style.display = 'none'; return 'hist'; }
  }

  /* 4. Открытые формы внутри разделов: расход и контактное лицо. */
  var _forms = ['exForm', 'crmContactForm'];
  for (var _f = 0; _f < _forms.length; _f++) {
    var _el = document.getElementById(_forms[_f]);
    if (_el && _el.innerHTML.trim() !== '') { _el.innerHTML = ''; return 'ui'; }
  }

  /* 5. Карточки CRM: клиент и сделка — шаг истории кладут. */
  var _cards = [['crmCard','crmList'], ['dlCard','dlList']];
  for (var _c = 0; _c < _cards.length; _c++) {
    var _card = document.getElementById(_cards[_c][0]);
    if (_card && _card.style.display === 'block') {
      _card.style.display = 'none';
      var _lst = document.getElementById(_cards[_c][1]);
      if (_lst) _lst.style.display = 'block';
      return 'hist';
    }
  }

  /* 5а. Раздел «Поставщики»: просмотр фото, форма, карточка. */
  if (window.spBackStep) { var _spr = window.spBackStep(); if (_spr) return _spr; }

  /* 6. Мы внутри раздела — возвращаемся на главный экран Экосистемы.
        Раздел открывается через showSection, шаг истории есть. */
  if (!onHubNow()) {
    ['tnved','hz','calc','pi','fullcalc','clients','deals','expenses','summary','suppliers'].forEach(function(x) {
      var _s = document.getElementById('section-' + x); if (_s) _s.style.display = 'none';
    });
    document.getElementById('hub').style.display = 'flex';
    window.scrollTo(0, 0);
    return 'hist';
  }

  /* 7. Мы на главной Экосистемы — разбирать больше нечего. */
  return false;
}

/* ВНУТРИ ПРИЛОЖЕНИЯ ИСТОРИЮ НЕ ТРОГАЕМ (важно, обжёгся 22.08.2026).
   Экосистема открыта окном внутри приложения, и история у них ОБЩАЯ.
   Стоило отмотать её на шаг — этот шаг забирался у приложения, оно
   считало, что «Назад» нажали второй раз, снова спрашивало Экосистему,
   та была уже на главной и отвечала «некуда» — окно закрывалось.
   Одно нажатие срабатывало дважды.
   Поэтому: внутри приложения только меняем экран, историю оставляем
   приложению. В обычном браузере всё как было. */
function ecoHistBack(){
  if (ITC_EMBED) return;
  ECO_POP_SKIP++;
  try{ history.back(); }catch(e){}
}

/* Наружу: этой же кнопкой пользуются разделы CRM, а они живут в другой
   части страницы и обычных функций отсюда не видят. */
window.ecoHistBack = ecoHistBack;

/* Приложение зовёт это перед тем, как закрыть окно Экосистемы. */
window.ecoNativeBack = function(){
  try { return !!ecoBackStep(); } catch(e) { return false; }
};

/* Кнопка «Назад» браузера. Здесь история УЖЕ отмотана на шаг назад. */
window.addEventListener('popstate', function(e) {
  /* Шаг отмотан нашей же кнопкой — экран уже закрыт, делать нечего. */
  if (ECO_POP_SKIP > 0) { ECO_POP_SKIP--; return; }
  var r = ecoBackStep();
  if (!r) { if (ITC_EMBED) ecoBackToApp(); return; }
  /* Закрыли уровень без своего шага — значит история ушла назад лишний
     раз. Возвращаем баланс, иначе следующее нажатие съест два уровня. */
  if (r === 'ui') { try{ history.pushState({ecoBal:1}, '', location.hash || ''); }catch(e){} }
});

// При старте: добавляем базовую запись истории (хаб)
// Это нужно чтобы первая кнопка Назад не закрывала приложение
window.addEventListener('DOMContentLoaded', function() {
  history.replaceState({ section: 'hub' }, '', '#hub');
});
</script>

<!-- ===== СВОДКА БОССА (CRM, 4.7.0) ===== -->
<div id="section-summary" class="app-section">
  <button onclick="showHub()" style="border:1px solid #d5dae3;background:#fff;color:#112C93;border-radius:9px;padding:7px 13px;font:600 .85rem/1 sans-serif;cursor:pointer;margin-bottom:12px">&#8249; Меню</button>
  <div id="smBody">Загружаю…</div>
</div>

<!-- ===== РАСХОДЫ КОМПАНИИ (CRM, 4.5.0) ===== -->
<div id="section-expenses" class="app-section">
  <button onclick="showHub()" style="border:1px solid #d5dae3;background:#fff;color:#112C93;border-radius:9px;padding:7px 13px;font:600 .85rem/1 sans-serif;cursor:pointer;margin-bottom:12px">&#8249; Меню</button>
  <div id="exBody">Загружаю…</div>
</div>

<!-- ===== СДЕЛКИ (CRM, 4.4.0) ===== -->
<div id="section-deals" class="app-section">
  <button onclick="showHub()" style="border:1px solid #d5dae3;background:#fff;color:#112C93;border-radius:9px;padding:7px 13px;font:600 .85rem/1 sans-serif;cursor:pointer;margin-bottom:12px">&#8249; Меню</button>

  <div id="dlList">
    <div style="display:flex;gap:8px;margin-bottom:10px">
      <button id="dlTabOpen"   onclick="dlSetTab('open')"   style="flex:1;border:0;border-radius:9px;padding:9px;font:600 .85rem/1 sans-serif;cursor:pointer">В работе</button>
      <button id="dlTabClosed" onclick="dlSetTab('closed')" style="flex:1;border:0;border-radius:9px;padding:9px;font:600 .85rem/1 sans-serif;cursor:pointer">Закрытые</button>
    </div>
    <button onclick="dlNew()" style="width:100%;margin-bottom:12px;background:#112C93;color:#fff;border:0;border-radius:9px;padding:11px;font:700 .92rem/1 sans-serif;cursor:pointer">+ Новая сделка</button>
    <div id="dlRows" style="font:400 .9rem/1.4 sans-serif;color:#3d4a63">Загружаю…</div>
  </div>

  <div id="dlCard" style="display:none"></div>
</div>

<!-- ===== КЛИЕНТЫ (CRM, 4.1.0) ===== -->
<div id="section-clients" class="app-section">
  <button onclick="showHub()" style="border:1px solid #d5dae3;background:#fff;color:#112C93;border-radius:9px;padding:7px 13px;font:600 .85rem/1 sans-serif;cursor:pointer;margin-bottom:12px">&#8249; Меню</button>

  <div id="crmList">
    <div style="display:flex;gap:8px;margin-bottom:12px">
      <input id="crmQ" placeholder="Поиск: компания или ИНН" oninput="crmSearchLater()"
             style="flex:1;font-size:16px;padding:10px;border:1px solid #dde;border-radius:9px">
      <button onclick="crmNewClient()" style="background:#112C93;color:#fff;border:0;border-radius:9px;padding:10px 16px;font:600 .9rem/1 sans-serif;cursor:pointer">+ Новый</button>
    </div>
    <div id="crmRows" style="font:400 .9rem/1.4 sans-serif;color:#3d4a63">Загружаю…</div>
  </div>

  <div id="crmCard" style="display:none"></div>
</div>


<!-- ===== ПОСТАВЩИКИ (база с выставок, 4.8.0) ===== -->
<style>
  /* В стиле остальных разделов Экосистемы: синяя полоса сверху, белая
     шапка с логотипом и зелёной чертой, панели с цветными заголовками,
     названия — Times New Roman. */
  #section-suppliers{color:var(--ink)}
  #section-suppliers .section-topbar{justify-content:flex-start}
  .sp-topadd{margin-left:auto;background:#fff;color:var(--navy);border:0;border-radius:7px;padding:.36rem .85rem;font:700 .82rem/1 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;cursor:pointer;display:inline-flex;align-items:center;gap:5px;white-space:nowrap}
  .sp-topadd svg{width:15px;height:15px}
  #spTopTitle{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0}
  .sp-wrap{max-width:920px;margin:0 auto;padding:14px 12px 40px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
  .sp-serif{font-family:"Times New Roman",Times,serif}
  .sp-btn{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--navy);background:#fff;color:var(--navy);border-radius:5px;padding:8px 12px;font:600 .84rem/1.1 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;cursor:pointer;text-decoration:none}
  .sp-btn svg{width:16px;height:16px;flex:none}
  .sp-btn.pri{background:var(--navy);color:#fff}
  .sp-btn.grn{background:var(--green);border-color:var(--green);color:#fff}
  .sp-btn.red{color:var(--red);border-color:#d9a3a3}
  .sp-btn.sm{padding:5px 9px;font-size:.79rem}
  .sp-btn:disabled{opacity:.5;cursor:default}

  /* поиск — как панель поиска в «Честном знаке» */
  .sp-search{background:#fff;border:1px solid var(--line);border-radius:4px;padding:12px 14px;margin-bottom:12px}
  .sp-search .lbl{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--navy);margin-bottom:7px}
  .sp-search .inp{display:flex;align-items:center;gap:7px;border:1px solid var(--line);border-radius:4px;padding:0 9px;background:var(--paper)}
  .sp-search .inp svg{width:17px;height:17px;color:#6b7a99;flex:none}
  .sp-search input{border:0;outline:0;font-size:16px;padding:9px 0;width:100%;background:transparent;font-family:inherit;color:var(--ink)}
  .sp-chips{display:flex;gap:6px;overflow-x:auto;padding:0 0 8px;margin-bottom:6px;-webkit-overflow-scrolling:touch}
  .sp-chip{flex:none;border:1px solid var(--line);background:#fff;color:#3d4a63;border-radius:4px;padding:7px 11px;font:600 .78rem/1 inherit;font-family:inherit;cursor:pointer;white-space:nowrap}
  .sp-chip.on{background:var(--navy);color:#fff;border-color:var(--navy)}
  .sp-chip span{opacity:.6;margin-left:4px}

  /* строка списка */
  .sp-row{display:flex;gap:11px;align-items:center;background:#fff;border:1px solid var(--line);border-left:3px solid var(--navy);border-radius:4px;padding:9px;margin-bottom:8px;cursor:pointer}
  .sp-row.live{border-left-color:var(--green)}
  .sp-row:active{background:#f4f7fc}
  .sp-thumb{width:84px;height:54px;border-radius:3px;object-fit:cover;flex:none;background:#eef2f8;display:flex;align-items:center;justify-content:center;color:#9aa6bd;overflow:hidden;border:1px solid #e3e8ef}
  .sp-thumb svg{width:24px;height:24px}
  .sp-row-main{min-width:0;flex:1}
  .sp-row-name{font-family:"Times New Roman",Times,serif;font-weight:700;color:var(--navy);font-size:1.08rem;line-height:1.15;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .sp-row-sub{font-size:.76rem;color:#6b7a99;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:2px}
  .sp-row-tags{display:flex;gap:5px;flex-wrap:wrap;margin-top:5px}
  .sp-pill{display:inline-flex;align-items:center;gap:4px;font-size:.7rem;font-weight:700;border-radius:3px;padding:2px 7px;background:#eef2fb;color:#3d4a63;white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis}
  .sp-pill svg{width:12px;height:12px;flex:none}
  .sp-pill.ok{background:#e4f3e1;color:var(--green-dark)}
  .sp-pill.warn{background:var(--amber-bg);color:#8a6200}
  .sp-pill.none{background:var(--red-bg);color:var(--red)}
  .sp-pill.seg{background:#e6ebf8;color:var(--navy)}
  .sp-empty{color:#6b7a99;text-align:center;padding:30px 8px;font-size:.92rem}
  .sp-seed{background:var(--amber-bg);border:1px solid #e9d39b;border-left:4px solid var(--amber);border-radius:4px;padding:11px 12px;margin-bottom:12px;font-size:.86rem;color:#5c4500}
  .sp-seed b{display:block;margin-bottom:4px}
  .sp-bar{height:6px;background:#efe3c2;border-radius:3px;overflow:hidden;margin-top:8px}
  .sp-bar i{display:block;height:100%;background:var(--green);width:0}

  /* карточка */
  /* карточка-«сайт» (4.9.2) */
  .sp-hero{position:relative;border-radius:4px;overflow:hidden;background:var(--navy);margin-bottom:0;height:230px;border:1px solid var(--line);border-bottom:0}
  .sp-hero img{width:100%;height:100%;object-fit:cover;display:block;cursor:zoom-in}
  .sp-hero.nophoto{height:150px}
  .sp-hero-ph{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.25)}
  .sp-hero-ph svg{width:64px;height:64px}
  .sp-hero-txt{position:absolute;left:0;right:0;bottom:0;padding:40px 14px 12px;background:linear-gradient(to top,rgba(10,22,70,.92),rgba(10,22,70,.55) 60%,rgba(10,22,70,0));pointer-events:none}
  .sp-hero-txt h2{margin:0;font-family:"Times New Roman",Times,serif;font-size:1.6rem;color:#fff;line-height:1.15}
  .sp-hero-txt .full{color:rgba(255,255,255,.85);font-size:.8rem;margin-top:4px}
  .sp-head2{border-top:0;border-radius:0 0 4px 4px}
  .sp-facts{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:6px;margin-top:10px}
  .sp-fact{display:flex;gap:7px;align-items:flex-start;background:#f3f6fb;border-radius:3px;padding:7px 9px;font-size:.84rem;line-height:1.35;color:#2d3648}
  .sp-fact svg{width:15px;height:15px;flex:none;color:var(--green);margin-top:1px}
  .sp-gal{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px}
  .sp-gal.sm{grid-template-columns:repeat(auto-fill,minmax(110px,1fr))}
  .sp-gal figure{margin:0;cursor:zoom-in;background:#eef2f8;border-radius:3px;overflow:hidden;border:1px solid var(--line)}
  .sp-gal img{width:100%;height:110px;object-fit:cover;display:block}
  .sp-gal.sm img{height:90px}
  .sp-gal figcaption{font-size:.72rem;color:#3d4a63;padding:4px 6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  /* 4.10.0: товары со страницы сайта прямо в карточке поставщика */
  .sp-goods{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px}
  .sp-good{background:#fff;border:1px solid var(--line);border-radius:3px;overflow:hidden;display:flex;flex-direction:column}
  .sp-good img{width:100%;height:120px;object-fit:contain;background:#fff;display:block;cursor:zoom-in}
  .sp-good b{display:block;font-size:.8rem;color:var(--navy);padding:6px 8px 2px;line-height:1.25}
  .sp-good small{display:block;font-size:.72rem;color:#55617a;padding:0 8px 8px;line-height:1.3}
  .sp-good i{font-style:normal;font-size:.66rem;color:#6b7a99;padding:0 8px 6px}
  .sp-gname{font-size:.78rem;font-weight:700;color:#3d4a63;margin:12px 0 6px;text-transform:uppercase;letter-spacing:.04em}
  .sp-gname:first-child{margin-top:0}
  .sp-sub{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#6b7a99;margin:12px 0 6px}
  @media (min-width:700px){ .sp-hero{height:320px} }
  .sp-head{background:#fff;border:1px solid var(--line);border-top:4px solid var(--navy);border-radius:4px;padding:14px;margin-bottom:12px}
  .sp-head h2{margin:0;font-family:"Times New Roman",Times,serif;font-size:1.45rem;color:var(--navy);line-height:1.2}
  .sp-head .full{color:#6b7a99;font-size:.84rem;margin-top:4px}
  .sp-head .tags{display:flex;gap:5px;flex-wrap:wrap;margin-top:9px}
  .sp-head .info{margin-top:10px;padding-top:9px;border-top:1px dashed #d5dce6;font-size:.85rem;line-height:1.55}
  .sp-head .info span{color:#6b7a99}
  .sp-panel{background:#fff;border:1px solid var(--line);border-radius:4px;margin-bottom:12px;overflow:hidden}
  .sp-ph{font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#fff;background:var(--navy);padding:9px 14px;display:flex;align-items:center;gap:8px}
  .sp-ph svg{width:16px;height:16px}
  .sp-ph.green{background:var(--green)}
  .sp-ph.amber{background:var(--amber)}
  .sp-pb{padding:12px 14px}
  .sp-what{font-size:.93rem;line-height:1.55;margin:0}
  .sp-list{margin:6px 0 0;padding-left:18px;font-size:.87rem;line-height:1.55;color:#2d3648}
  .sp-cards{display:flex;gap:8px;overflow-x:auto;-webkit-overflow-scrolling:touch}
  .sp-cards img{height:150px;max-width:260px;border-radius:3px;box-shadow:0 1px 4px rgba(0,0,0,.22);cursor:zoom-in;flex:none;object-fit:cover;background:#eef2f8}
  .sp-docs{display:flex;gap:8px;flex-wrap:wrap}
  .sp-docs img{width:86px;height:116px;object-fit:cover;border-radius:3px;border:1px solid var(--line);cursor:zoom-in}
  .sp-person{padding:9px 0;border-top:1px solid #edf0f5}
  .sp-person:first-child{border-top:0;padding-top:0}
  .sp-pname{font-family:"Times New Roman",Times,serif;font-weight:700;font-size:1.02rem;color:var(--navy)}
  .sp-prole{color:#6b7a99;font-weight:400;font-size:.8rem;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
  .sp-cl{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
  .sp-copy{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--line);background:var(--paper);border-radius:4px;padding:6px 9px;font:500 .82rem/1.25 inherit;font-family:inherit;color:var(--ink);cursor:pointer;text-align:left;max-width:100%}
  .sp-copy svg{width:15px;height:15px;color:var(--navy);flex:none}
  .sp-copy span{word-break:break-word}
  .sp-copy.done{border-color:var(--green);color:var(--green-dark)}
  .sp-file{display:flex;align-items:center;gap:10px;padding:8px 0;border-top:1px solid #edf0f5}
  .sp-file:first-child{border-top:0;padding-top:0}
  .sp-file .ic{width:36px;height:36px;border-radius:4px;background:#e6ebf8;color:var(--navy);display:flex;align-items:center;justify-content:center;flex:none}
  .sp-file .ic svg{width:19px;height:19px}
  .sp-file .t{flex:1;min-width:0;font-size:.87rem}
  .sp-file .t b{display:block;font-weight:600;word-break:break-word}
  .sp-file .t small{color:#6b7a99}
  .sp-note{background:var(--amber-bg);border-left:4px solid var(--amber);border-radius:3px;padding:9px 11px;font-size:.85rem;color:#5c4500;margin-bottom:12px}
  .sp-foot{font-size:.74rem;color:#8a93a6;text-align:center;margin:4px 0 12px}
  .sp-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}

  /* форма */
  .sp-form label{display:block;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#4a5878;margin:12px 0 5px}
  .sp-form label:first-child{margin-top:0}
  .sp-form label.sp-btn{display:inline-flex;text-transform:none;letter-spacing:0;font-size:.79rem;color:var(--navy);margin:0}
  .sp-form input,.sp-form select,.sp-form textarea{width:100%;font-size:16px;padding:9px 10px;border:1px solid var(--line);border-radius:4px;background:#fff;font-family:inherit;color:var(--navy)}
  .sp-form input:focus,.sp-form select:focus,.sp-form textarea:focus{outline:2px solid #c9d4f2;border-color:var(--navy)}
  .sp-form textarea{min-height:72px;resize:vertical;color:var(--ink)}
  .sp-form .hint{font-size:.74rem;color:#8a93a6;margin-top:3px}
  .sp-grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 10px}
  @media (max-width:560px){.sp-grid2{grid-template-columns:1fr}}
  .sp-box{border:1px solid #dfe5ee;border-left:3px solid var(--navy);border-radius:4px;padding:10px;margin-top:8px;background:var(--paper)}
  .sp-crow{display:flex;gap:6px;margin-top:6px}
  .sp-crow select{flex:0 0 108px;font-size:14px;padding:9px 4px}
  .sp-crow input{flex:1;min-width:0}
  .sp-x{border:0;background:none;color:var(--red);cursor:pointer;padding:4px;flex:none}
  .sp-x svg{width:18px;height:18px}
  .sp-up{margin-top:8px;font-size:.82rem;color:#3d4a63}
  .sp-up .sp-bar{background:#e3e8ef}
  .sp-msg{position:fixed;left:50%;bottom:22px;transform:translateX(-50%);background:var(--navy-dark);color:#fff;padding:10px 16px;border-radius:6px;font:600 .88rem/1.3 -apple-system,sans-serif;z-index:9900;max-width:90vw;text-align:center}
  .sp-lb{position:fixed;inset:0;background:rgba(8,14,40,.92);z-index:9700;display:flex;align-items:center;justify-content:center;padding:14px;cursor:zoom-out}
  .sp-lb img,.sp-lb video{max-width:100%;max-height:100%;border-radius:4px}
  .sp-lb .cls{position:absolute;top:10px;right:12px;color:#fff;font:600 .85rem/1 sans-serif;opacity:.8}
  /* окно «вырезать из каталога» */
  .sp-crop{position:fixed;inset:0;z-index:9650;background:#1b2233;display:flex;flex-direction:column;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
  .sp-crop-bar{display:flex;gap:8px;align-items:center;padding:8px 10px;background:var(--navy);color:#fff;flex-wrap:wrap}
  .sp-crop-bar select{flex:1;min-width:0;max-width:420px;font-size:14px;padding:6px;border-radius:5px;border:0}
  .sp-crop-bar .pg{display:inline-flex;align-items:center;gap:6px}
  .sp-crop-bar .pg button,.sp-crop-bar .x{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.45);color:#fff;border-radius:6px;padding:5px 11px;font:700 .9rem/1 sans-serif;cursor:pointer}
  .sp-crop-bar .x{margin-left:auto;font-weight:600}
  .sp-crop-hint{background:#2a3247;color:#d6dcea;font-size:.8rem;padding:6px 10px}
  .sp-crop-stage{flex:1;overflow:auto;display:flex;justify-content:center;align-items:flex-start;padding:10px}
  .sp-crop-wrap{position:relative;touch-action:none;user-select:none;-webkit-user-select:none;max-width:100%}
  .sp-crop-cv{display:block;max-width:100%;height:auto;background:#fff;max-height:calc(100vh - 170px);width:auto}
  .sp-crop-sel{position:absolute;border:2px solid #4DD637;background:rgba(77,214,55,.15);box-shadow:0 0 0 9999px rgba(0,0,0,.35);pointer-events:none}
  .sp-crop-load{color:#cfd6e6;padding:40px 10px;text-align:center}
  .sp-crop-foot{display:flex;gap:10px;align-items:center;justify-content:flex-end;padding:10px;background:#fff;flex-wrap:wrap}
  .sp-crop-foot span{font-size:.82rem;color:#3d4a63;margin-right:auto}
</style>

<div id="section-suppliers" class="app-section">
  <div class="section-topbar">
    <button class="back-btn" onclick="spTopBack()">&#8592; Назад</button>
    <span class="section-topbar-title" id="spTopTitle">Поставщики</span>
    <button class="sp-topadd" id="spAddBtn" style="display:none" onclick="spNew()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>Добавить</button>
  </div>
  <div class="brand-bar">
    <div class="brand-bar-logo"><img src="/eco/logo1.png" alt="ИТК"></div>
    <div class="brand-bar-text">
      <div class="brand-bar-title">ИТК — Поставщики</div>
      <div class="brand-bar-sub" id="spBrandSub">Фабрики с выставок: визитки, контакты, каталоги</div>
    </div>
  </div>
  <div class="sp-wrap">
    <div id="spList">
      <div id="spSeed"></div>
      <div class="sp-search"><div class="lbl">Поиск по базе</div><div class="inp"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/></svg>
        <input id="spQ" type="search" placeholder="Компания, человек, товар, город" autocomplete="off" oninput="spRender()"></div></div>
      <div class="sp-chips" id="spChips"></div>
      <div id="spRows"><div class="sp-empty">Загружаю…</div></div>
    </div>
    <div id="spCard" style="display:none"></div>
    <div id="spForm" style="display:none"></div>
    <div id="spWeb" style="display:none"></div>
  </div>
</div>

<script>
(function(){
  /* ============================================================
     БАЗА ПОСТАВЩИКОВ (4.8.0, 10.10.2026)
     Данные — в базе сайта, файлы (визитки, каталоги, видео) — в том же
     хранилище, где фото магазина. Сервер: /api/suppliers.php.
     Смотреть может любой вошедший сотрудник, изменять — менеджер,
     супервайзер, модератор и босс, удалять — только босс.
     ============================================================ */
  var SP = { rows: [], segs: [], me: {}, seed: false, seg: 'all', cur: null, edit: null, loaded: false };
  var API = '/api/suppliers.php';
  var CHUNK = 2 * 1024 * 1024;

  function tok(){
    try { if (window.ECO_TOKEN_GET) { var t = window.ECO_TOKEN_GET(); if (t) return t; } } catch(e){}
    try { return sessionStorage.getItem('eco_t') || ''; } catch(e){ return ''; }
  }
  function esc(x){ return String(x==null?'':x).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
  function get(action){
    return fetch(API+'?action='+action+'&t='+encodeURIComponent(tok())).then(function(r){ return r.json(); });
  }
  function post(action, data){
    data = data || {}; data.t = tok();
    return fetch(API+'?action='+action, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data)})
      .then(function(r){ return r.json(); });
  }
  function msg(t, ms){
    var m = document.createElement('div'); m.className = 'sp-msg'; m.textContent = t;
    document.body.appendChild(m); setTimeout(function(){ if (m.parentNode) m.parentNode.removeChild(m); }, ms || 2200);
  }

  /* --- значки в стиле Material (контур) --- */
  var IC = {
    phone:'<path d="M5 4h3.5l1.7 4.3-2.2 1.4a11 11 0 0 0 5.3 5.3l1.4-2.2L19 14.5V18a2 2 0 0 1-2 2A15 15 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
    call:'<rect x="3.5" y="5" width="17" height="14" rx="2"/><path d="M7.5 9h9M7.5 13h5"/>',
    chat:'<path d="M20 12a7.5 7.5 0 0 1-11 6.6L4 20l1.4-4.6A7.5 7.5 0 1 1 20 12z"/>',
    mail:'<rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="M3.5 7l8.5 6 8.5-6"/>',
    tag:'<path d="M3.5 12.5V4.5h8l9 9-8 8z"/><circle cx="8" cy="9" r="1.3"/>',
    place:'<path d="M12 21s-6.5-6-6.5-11a6.5 6.5 0 0 1 13 0c0 5-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.4"/>',
    web:'<circle cx="12" cy="12" r="8.6"/><path d="M3.4 12h17.2"/><path d="M12 3.4c2.2 2.4 3.4 5.4 3.4 8.6s-1.2 6.2-3.4 8.6c-2.2-2.4-3.4-5.4-3.4-8.6S9.8 5.8 12 3.4z"/>',
    pdf:'<path d="M13.5 2.8H6.8a2 2 0 0 0-2 2v14.4a2 2 0 0 0 2 2h10.4a2 2 0 0 0 2-2V8.3z"/><path d="M13.5 2.8v5.5h5.7"/><path d="M8.4 13h7.2M8.4 16.6h5"/>',
    video:'<rect x="3" y="5.5" width="13" height="13" rx="2"/><path d="M16 10.5l5-3v9l-5-3z"/>',
    image:'<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="9" cy="9.5" r="1.6"/><path d="M20.5 16l-5-5-8 8.5"/>',
    store:'<path d="M4 9.5L5.5 4h13L20 9.5"/><path d="M4 9.5a2.7 2.7 0 0 0 5.3 0 2.7 2.7 0 0 0 5.4 0 2.7 2.7 0 0 0 5.3 0"/><path d="M5.5 11v8.5h13V11"/>',
    folder:'<path d="M3.5 6.5a2 2 0 0 1 2-2h4l2 2.2h7a2 2 0 0 1 2 2V17.5a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2z"/>',
    copy:'<rect x="8.5" y="8.5" width="11" height="11" rx="2"/><path d="M15.5 8.5V6.5a2 2 0 0 0-2-2h-7a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h2"/>',
    factory:'<path d="M3.5 20V10l5 3V10l5 3V5.5h3V20z"/><path d="M3 20h18"/>',
    ok:'<circle cx="12" cy="12" r="8.6"/><path d="M8.4 12.3l2.6 2.6 4.8-5"/>',
    help:'<circle cx="12" cy="12" r="8.6"/><path d="M9.8 9.5a2.3 2.3 0 1 1 3.4 2c-.8.4-1.2 1-1.2 1.8v.4"/><circle cx="12" cy="16.6" r=".6" fill="currentColor"/>',
    off:'<path d="M4 4l16 16"/><path d="M7.5 7.6A5.5 5.5 0 0 0 7 18h10"/><path d="M11 6.2A5.5 5.5 0 0 1 17.4 10 3.8 3.8 0 0 1 20 16"/>',
    link:'<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
    edit:'<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13.5 6.5l4 4"/>',
    del:'<path d="M4.5 7h15M9.5 7V4.5h5V7M6.5 7l1 13h9l1-13"/>',
    up:'<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4.5 15v4.5h15V15"/>',
    x:'<path d="M6 6l12 12M18 6L6 18"/>',
    person:'<circle cx="12" cy="8" r="3.6"/><path d="M5 20a7 7 0 0 1 14 0"/>'
  };
  function ic(k, sw){ return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'+(sw||1.8)+'" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+(IC[k]||IC.tag)+'</svg>'; }
  var CICON = { phone:'phone', call:'call', chat:'chat', mail:'mail', tag:'tag' };
  var CTYPE = [['phone','Мобильный'],['call','Городской'],['chat','WeChat / WhatsApp'],['mail','Почта'],['tag','Другое']];
  var LTYPE = [['language','Сайт'],['picture_as_pdf','Каталог / PDF'],['folder','Файлы'],['movie','Видео'],['storefront','Магазин']];
  var LICON = { language:'web', picture_as_pdf:'pdf', folder:'folder', movie:'video', storefront:'store' };
  var WEB = { ok:['ok','ok'], warn:['warn','help'], none:['none','off'] };
  var CONTACT_STATES = ['Не написали','Написали, ждём ответа','На связи','Не отвечает','Работаем'];

  function segName(c){ for (var i=0;i<SP.segs.length;i++) if (SP.segs[i].code===c) return SP.segs[i].name; return c ? c : ''; }
  function firstCard(s){
    /* в списке — фото фабрики с сайта; если его нет — визитка, потом наше фото */
    var m=(s.media||[]).filter(function(x){return x.kind==='site' && /^image\//.test(x.mime||'');});
    if (!m.length) m=(s.media||[]).filter(function(x){return x.kind==='card';});
    if (!m.length) m=(s.media||[]).filter(function(x){return x.kind==='doc' && /^image\//.test(x.mime||'');});
    return m[0] ? m[0].url : '';
  }
  function fsize(b){ if (!b) return ''; if (b < 1024*1024) return Math.round(b/1024)+' КБ'; return (b/1024/1024).toFixed(1).replace('.',',')+' МБ'; }
  function isImg(m){ return /^image\//.test(m.mime||''); }
  function isVid(m){ return /^video\//.test(m.mime||''); }

  /* ---------- список ---------- */
  function load(keepView){
    return get('sup_list').then(function(d){
      if (!d || !d.ok) { document.getElementById('spRows').innerHTML = '<div class="sp-empty">'+esc((d&&d.error)||'Не удалось загрузить')+'</div>'; return; }
      SP.rows = d.rows || []; SP.segs = d.segs || []; SP.me = d.me || {}; SP.seed = !!d.seed; SP.loaded = true;
      if (!keepView) showList();
      spRender(); seedBox();
    }).catch(function(){ document.getElementById('spRows').innerHTML = '<div class="sp-empty">Нет связи с сервером.</div>'; });
  }

  function hay(s){
    var p = (s.people||[]).map(function(x){ return [x.n,x.r].concat((x.c||[]).map(function(c){return c[1];})).join(' '); }).join(' ');
    return [s.name,s.full,s.city,s.what,s.kind,s.event,s.contact_state,s.addr,s.note,segName(s.seg),segName(s.seg2),p]
      .concat(s.products||[]).concat(s.facts||[]).join(' ').toLowerCase();
  }

  function spRender(){
    var q = (document.getElementById('spQ').value||'').trim().toLowerCase();
    var cnt = {};
    SP.rows.forEach(function(s){ cnt[s.seg]=(cnt[s.seg]||0)+1; if (s.seg2 && s.seg2!==s.seg) cnt[s.seg2]=(cnt[s.seg2]||0)+1; });
    var chips = '<button class="sp-chip'+(SP.seg==='all'?' on':'')+'" onclick="spSeg(\'all\')">Все<span>'+SP.rows.length+'</span></button>';
    SP.segs.forEach(function(g){ if (cnt[g.code]) chips += '<button class="sp-chip'+(SP.seg===g.code?' on':'')+'" onclick="spSeg(\''+esc(g.code)+'\')">'+esc(g.name)+'<span>'+cnt[g.code]+'</span></button>'; });
    document.getElementById('spChips').innerHTML = chips;
    var list = SP.rows.filter(function(s){
      if (SP.seg!=='all' && s.seg!==SP.seg && s.seg2!==SP.seg) return false;
      return !q || hay(s).indexOf(q) >= 0;
    });
    setTop('Поставщики · ' + SP.rows.length);
    var box = document.getElementById('spRows');
    if (!SP.rows.length) { box.innerHTML = '<div class="sp-empty">Пока ни одного поставщика.'+(SP.me.edit?' Нажмите «Добавить».':'')+'</div>'; return; }
    if (!list.length) { box.innerHTML = '<div class="sp-empty">Ничего не найдено. Сбросьте раздел или измените запрос.</div>'; return; }
    box.innerHTML = list.map(function(s){
      var th = firstCard(s);
      var files = (s.media||[]).filter(function(m){ return m.kind==='file'; }).length;
      var tags = '<span class="sp-pill seg">'+esc(segName(s.seg))+'</span>';
      if (s.contact_state) tags += '<span class="sp-pill'+(/На связи|Работаем/.test(s.contact_state)?' ok':'')+'">'+ic('chat')+esc(s.contact_state.length>28?s.contact_state.slice(0,28)+'…':s.contact_state)+'</span>';
      if (files) tags += '<span class="sp-pill">'+ic('folder')+files+'</span>';
      var live = /На связи|Работаем/.test(s.contact_state||'');
      return '<div class="sp-row'+(live?' live':'')+'" onclick="spOpen(\''+esc(s.id)+'\')">'
        + (th ? '<img class="sp-thumb" src="'+esc(th)+'" alt="" loading="lazy">' : '<div class="sp-thumb">'+ic('factory')+'</div>')
        + '<div class="sp-row-main"><div class="sp-row-name">'+esc(s.name)+'</div>'
        + '<div class="sp-row-sub">'+esc([s.city, s.full].filter(Boolean).join(' · '))+'</div>'
        + '<div class="sp-row-tags">'+tags+'</div></div></div>';
    }).join('');
  }
  function spSeg(c){ SP.seg = c; spRender(); }

  function setTop(t){ var e = document.getElementById('spTopTitle'); if (e) e.textContent = t; }
  function showList(){
    setTop('Поставщики · ' + SP.rows.length);
    document.getElementById('spAddBtn').style.display = SP.me.edit ? '' : 'none';
    document.getElementById('spList').style.display = 'block';
    document.getElementById('spCard').style.display = 'none';
    document.getElementById('spForm').style.display = 'none';
    document.getElementById('spWeb').style.display = 'none';
  }
  function pushStep(){ try{ history.pushState({ spStep:1 }, '', location.hash || ''); }catch(e){} }

  /* ---------- карточка ---------- */
  function byId(id){ for (var i=0;i<SP.rows.length;i++) if (SP.rows[i].id===id) return SP.rows[i]; return null; }

  function spOpen(id){
    var s = byId(id); if (!s) return;
    SP.cur = s;
    var wasOpen = document.getElementById('spCard').style.display === 'block';
    renderCard(s);
    document.getElementById('spList').style.display = 'none';
    document.getElementById('spForm').style.display = 'none';
    document.getElementById('spCard').style.display = 'block';
    window.scrollTo(0,0);
    if (!wasOpen) pushStep();
  }

  function copyBtn(icon, val){
    return '<button type="button" class="sp-copy" data-copy="'+esc(val)+'" onclick="spCopy(this)">'+ic(icon)+'<span>'+esc(val)+'</span></button>';
  }

  function panel(title, icon, body, color){
    return '<div class="sp-panel"><div class="sp-ph'+(color?' '+color:'')+'">'+ic(icon)+esc(title)+'</div><div class="sp-pb">'+body+'</div></div>';
  }

  /* Карточка поставщика — как маленький одностраничный сайт:
     сверху фото фабрики с названием, ниже о фабрике, фото с сайта, товары,
     контакты с визиткой, наши фото, видео, каталоги, ссылки. (4.9.2) */
  function heroPhoto(s){
    var m = s.media || [];
    var pick = m.filter(function(x){ return x.kind==='site' && isImg(x); })[0]
            || m.filter(function(x){ return x.kind==='doc' && isImg(x) && !/wechat|профил/i.test(x.title||''); })[0]
            || m.filter(function(x){ return x.kind==='card' && isImg(x); })[0];
    return pick || null;
  }
  function renderCard(s){
    var media = s.media || [];
    var hero = heroPhoto(s);
    // если у поставщика есть страница на сайте — сверху её главное фото
    if (s.web && s.web.hero) hero = { id: 0, url: s.web.hero, kind: 'web' };
    var cards = media.filter(function(m){ return m.kind==='card'; });
    var site = media.filter(function(m){ return m.kind==='site' && isImg(m); });
    var ours = media.filter(function(m){ return (m.kind==='doc' || m.kind==='file') && isImg(m); });
    var vids = media.filter(function(m){ return (m.kind==='doc' || m.kind==='file' || m.kind==='site') && isVid(m); });
    var files = media.filter(function(m){ return (m.kind==='file' || m.kind==='doc') && !isImg(m) && !isVid(m); });
    setTop(s.name);
    document.getElementById('spAddBtn').style.display = 'none';

    var tags = '<span class="sp-pill seg">'+esc(segName(s.seg))+'</span>';
    if (s.seg2 && s.seg2!==s.seg) tags += '<span class="sp-pill seg">'+esc(segName(s.seg2))+'</span>';
    if (s.city) tags += '<span class="sp-pill">'+ic('place')+esc(s.city)+'</span>';
    if (s.kind) tags += '<span class="sp-pill">'+ic('factory')+esc(s.kind)+'</span>';
    if (s.web && s.web.status === 'live') tags += '<span class="sp-pill ok">'+ic('web')+'На сайте</span>';

    var h = '<div class="sp-hero'+(hero?'':' nophoto')+'">'
      + (hero ? '<img src="'+esc(hero.url)+'" alt="'+esc(s.name)+'" onclick="'+(hero.id ? 'spView('+hero.id+')' : 'spViewUrl(\''+esc(hero.url).replace(/'/g,'%27')+'\')')+'">' : '<div class="sp-hero-ph">'+ic('factory')+'</div>')
      + '<div class="sp-hero-txt"><h2>'+esc(s.name)+'</h2>'+(s.full?'<div class="full">'+esc(s.full)+'</div>':'')+'</div></div>';
    h += '<div class="sp-head sp-head2"><div class="tags" style="margin-top:0">'+tags+'</div>';
    var info = [];
    if (s.event) info.push(['Где познакомились', s.event]);
    if (s.contact_state) info.push(['Связь', s.contact_state]);
    if (s.web_state && WEB[s.web_state]) info.push(['Проверка', s.web_note || '']);
    if (info.length) h += '<div class="info">' + info.map(function(x){ return '<div><span>'+esc(x[0])+':</span> '+esc(x[1])+'</div>'; }).join('') + '</div>';
    if (SP.me.edit) h += '<div class="sp-cl" style="margin-top:10px"><button class="sp-btn sm" onclick="spEdit(\''+esc(s.id)+'\')">'+ic('edit')+'Изменить</button>'
      + (SP.me.boss ? '<button class="sp-btn sm grn" onclick="spWebOpen(\''+esc(s.id)+'\')">'+ic('web')+'Страница на сайте</button>' : '') + '</div>';
    h += '</div>';

    if (s.note) h += '<div class="sp-note">'+esc(s.note)+'</div>';

    if (s.what || (s.facts||[]).length) {
      var w = '';
      if (s.what) w += '<p class="sp-what">'+esc(s.what)+'</p>';
      if ((s.facts||[]).length) w += '<div class="sp-facts">'+s.facts.map(function(f){ return '<div class="sp-fact">'+ic('ok')+'<span>'+esc(f)+'</span></div>'; }).join('')+'</div>';
      h += panel('О фабрике', 'factory', w, 'green');
    }
    if (site.length > (hero && hero.kind==='site' ? 1 : 0) || (site.length && !(hero && hero.kind==='site'))) {
      h += panel('Фото фабрики и продукции', 'image', '<div class="sp-gal">'
        + site.map(function(m){ return '<figure onclick="spView('+m.id+')"><img src="'+esc(m.url)+'" alt="'+esc(m.title)+'" loading="lazy">'+(m.title&&m.title!=='site.jpg'?'<figcaption>'+esc(m.title)+'</figcaption>':'')+'</figure>'; }).join('') + '</div>');
    }
    var wg = (s.web && s.web.groups) || [], nGoods = 0;
    wg.forEach(function(g){ nGoods += (g.items||[]).length; });
    if (nGoods) {
      var gh = '';
      if (s.web.status === 'live') gh += '<div class="sp-cl" style="margin:0 0 10px"><span class="sp-pill ok">'+ic('ok')+'На сайте</span><button type="button" class="sp-btn sm" onclick="spOpenUrl(\'https://itcpartner.ru/'+esc(s.web.slug)+'\')">'+ic('web')+'Открыть страницу</button></div>';
      wg.forEach(function(g){
        if (!(g.items||[]).length) return;
        gh += (wg.length > 1 && g.name ? '<div class="sp-gname">'+esc(g.name)+'</div>' : '') + '<div class="sp-goods">'
          + g.items.map(function(it){
              var ph = (it.photos||[])[0];
              return '<div class="sp-good">'+(ph ? '<img src="'+esc(ph)+'" alt="'+esc(it.name)+'" loading="lazy" onclick="spViewUrl(\''+esc(ph).replace(/'/g,'%27')+'\')">' : '')
                + '<b>'+esc(it.name)+'</b>'+(it.desc?'<small>'+esc(it.desc)+'</small>':'')
                + ((it.photos||[]).length > 1 ? '<i>фото: '+it.photos.length+'</i>' : '') + '</div>';
            }).join('') + '</div>';
      });
      h += panel('Товары · ' + nGoods, 'store', gh, 'green');
    } else if ((s.products||[]).length) h += panel('Товары', 'store', '<ul class="sp-list" style="margin-top:0">'+s.products.map(function(f){ return '<li>'+esc(f)+'</li>'; }).join('')+'</ul>');

    if ((s.people||[]).length || s.addr || cards.length) {
      var c = '';
      (s.people||[]).forEach(function(p){
        c += '<div class="sp-person"><div class="sp-pname">'+esc(p.n||'')+(p.r?' <span class="sp-prole">· '+esc(p.r)+'</span>':'')+'</div>';
        if ((p.c||[]).length) c += '<div class="sp-cl">'+p.c.map(function(x){ return copyBtn(CICON[x[0]]||'tag', x[1]); }).join('')+'</div>';
        c += '</div>';
      });
      if (s.addr) c += '<div class="sp-cl" style="margin-top:9px">'+copyBtn('place', s.addr)+'</div>';
      if (cards.length) c += '<div class="sp-sub">Визитка</div><div class="sp-cards">'
        + cards.map(function(m){ return '<img src="'+esc(m.url)+'" alt="'+esc(m.title)+'" loading="lazy" onclick="spView('+m.id+')">'; }).join('') + '</div>';
      h += panel('Контакты', 'person', c);
    }

    if (ours.length) h += panel('Наши фото', 'image', '<div class="sp-gal sm">'
        + ours.map(function(m){ return '<figure onclick="spView('+m.id+')"><img src="'+esc(m.url)+'" alt="'+esc(m.title)+'" loading="lazy"><figcaption>'+esc(m.title)+'</figcaption></figure>'; }).join('') + '</div>');

    if (vids.length) h += panel('Видео', 'video', vids.map(function(m){
        return '<div class="sp-file"><div class="ic">'+ic('video')+'</div><div class="t"><b>'+esc(m.title)+'</b><small>'+fsize(m.bytes)+'</small></div>'
          + '<button class="sp-btn sm pri" onclick="spView('+m.id+')">Смотреть</button></div>';
      }).join(''));

    if (files.length) h += panel('Каталоги и файлы', 'folder', files.map(function(m){
        return '<div class="sp-file"><div class="ic">'+ic('pdf')+'</div><div class="t"><b>'+esc(m.title)+'</b><small>'+fsize(m.bytes)+'</small></div>'
          + '<button class="sp-btn sm pri" onclick="spView('+m.id+')">Открыть</button></div>';
      }).join(''), 'green');

    if ((s.links||[]).length) h += panel('Ссылки', 'link', '<div class="sp-cl" style="margin-top:0">' + s.links.map(function(l){
        return '<button type="button" class="sp-btn sm" onclick="spOpenUrl(\''+esc(l[2]).replace(/'/g,'%27')+'\')">'+ic(LICON[l[0]]||'link')+esc(l[1])+'</button>';
      }).join('') + '</div>');

    h += '<div class="sp-foot">Добавил: '+esc(s.created_by||'—')+(s.updated_by && s.updated_at ? ' · изменил: '+esc(s.updated_by)+', '+esc(String(s.updated_at).slice(0,10).split('-').reverse().join('.')) : '')+'</div>';
    document.getElementById('spCard').innerHTML = h;
  }

  function spBack(){
    var c = document.getElementById('spCard');
    if (c.style.display !== 'block') return;
    showList(); spRender();
    if (window.ecoHistBack) window.ecoHistBack();
  }

  function spCopy(b){
    var v = b.getAttribute('data-copy') || '';
    function ok(){ b.classList.add('done'); setTimeout(function(){ b.classList.remove('done'); }, 1300); msg('Скопировано'); }
    function fallback(){
      var ta = document.createElement('textarea'); ta.value = v; ta.style.position='fixed'; ta.style.opacity='0';
      document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); ok(); } catch(e){ msg(v, 4000); }
      document.body.removeChild(ta);
    }
    try { if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(v).then(ok, fallback); return; } } catch(e){}
    fallback();
  }

  /* Открыть ссылку или файл. Внутри приложения окно Экосистемы не умеет
     открывать файлы само — отдаём ссылку в панель приложения (как с КП). */
  function spOpenUrl(url){
    var inApp = /ITCApp/i.test(navigator.userAgent || '');
    var AS = null;
    try { AS = window.AndroidShare || (window.parent && window.parent.AndroidShare) || null; } catch(e){}
    if (inApp && AS && AS.share) { try { AS.share('Файл поставщика', 'Открыть в браузере', url); return; } catch(e){} }
    var w = null; try { w = window.open(url, '_blank'); } catch(e){}
    if (!w) location.href = url;
  }

  function findMedia(id){
    var s = SP.cur || SP.edit; if (!s) return null;
    var m = (s.media||[]).filter(function(x){ return x.id===id; });
    return m[0] || null;
  }
  /* Просмотр: фото и видео — поверх экрана, остальное — открыть ссылкой. */
  function spView(id){
    var m = findMedia(id); if (!m) return;
    if (!isImg(m) && !isVid(m)) { spOpenUrl(m.url); return; }
    var lb = document.createElement('div'); lb.className = 'sp-lb';
    lb.innerHTML = (isVid(m) ? '<video src="'+esc(m.url)+'" controls autoplay playsinline></video>' : '<img src="'+esc(m.url)+'" alt="">')
      + '<div class="cls">'+(isVid(m)?'нажмите вне видео, чтобы закрыть':'нажмите, чтобы закрыть')+'</div>';
    lb.addEventListener('click', function(e){ if (e.target.tagName === 'VIDEO') return; if (lb.parentNode) lb.parentNode.removeChild(lb); });
    document.body.appendChild(lb);
  }

  function spViewUrl(u){
    var lb = document.createElement('div'); lb.className = 'sp-lb';
    lb.innerHTML = '<img src="'+esc(u)+'" alt=""><div class="cls">нажмите, чтобы закрыть</div>';
    lb.addEventListener('click', function(){ if (lb.parentNode) lb.parentNode.removeChild(lb); });
    document.body.appendChild(lb);
  }
  window.spViewUrl = spViewUrl;

  /* ---------- форма ---------- */
  function blank(){ return { id:'', name:'', full:'', seg:(SP.seg!=='all'?SP.seg:'unk'), seg2:'', city:'', kind:'', event:'', web_state:'', web_note:'', contact_state:'Не написали', what:'', facts:[], products:[], people:[{n:'',r:'',c:[['phone','']]}], addr:'', note:'', links:[], media:[] }; }
  function spNew(){ openForm(blank()); }
  function spEdit(id){ var s = byId(id); if (s) openForm(JSON.parse(JSON.stringify(s))); }

  function openForm(s){
    SP.edit = s;
    renderForm();
    var wasList = document.getElementById('spList').style.display !== 'none';
    document.getElementById('spList').style.display = 'none';
    document.getElementById('spCard').style.display = 'none';
    document.getElementById('spForm').style.display = 'block';
    window.scrollTo(0,0);
    pushStep();
    SP.formFromList = wasList;
  }

  function opts(list, val){ return list.map(function(o){ return '<option value="'+esc(o[0])+'"'+(o[0]===val?' selected':'')+'>'+esc(o[1])+'</option>'; }).join(''); }
  function segOpts(val, empty){ return (empty?'<option value="">— нет —</option>':'') + SP.segs.map(function(g){ return '<option value="'+esc(g.code)+'"'+(g.code===val?' selected':'')+'>'+esc(g.name)+'</option>'; }).join('') + '<option value="__new">+ Новый раздел…</option>'; }
  function uniq(field){ var o={}; SP.rows.forEach(function(r){ if (r[field]) o[r[field]]=1; }); return Object.keys(o); }

  function renderForm(){
    var s = SP.edit;
    setTop(s.id ? 'Изменить: ' + s.name : 'Новый поставщик');
    document.getElementById('spAddBtn').style.display = 'none';
    var g = '';
    g += '<label>Название (коротко, как будем искать) *</label><input id="spf_name" value="'+esc(s.name)+'">';
    g += '<label>Полное название (китайское и английское)</label><input id="spf_full" value="'+esc(s.full)+'">';
    g += '<div class="sp-grid2"><div><label>Раздел</label><select id="spf_seg" onchange="spSegPick(this)">'+segOpts(s.seg)+'</select></div>'
       + '<div><label>Второй раздел</label><select id="spf_seg2" onchange="spSegPick(this)">'+segOpts(s.seg2, true)+'</select></div></div>';
    g += '<div class="sp-grid2"><div><label>Город, провинция</label><input id="spf_city" value="'+esc(s.city)+'"></div>'
       + '<div><label>Кто это</label><input id="spf_kind" list="spdl_kind" value="'+esc(s.kind)+'" placeholder="Фабрика / торговая компания"></div></div>';
    g += '<datalist id="spdl_kind"><option value="Производитель"><option value="Торговая компания (не фабрика)"><option value="Экспедитор"></datalist>';
    g += '<div class="sp-grid2"><div><label>Где познакомились</label><input id="spf_event" list="spdl_event" value="'+esc(s.event)+'"></div>'
       + '<div><label>Связь</label><input id="spf_contact" list="spdl_contact" value="'+esc(s.contact_state)+'"></div></div>';
    g += '<datalist id="spdl_event">'+uniq('event').map(function(v){return '<option value="'+esc(v)+'">';}).join('')+'</datalist>';
    g += '<datalist id="spdl_contact">'+CONTACT_STATES.map(function(v){return '<option value="'+esc(v)+'">';}).join('')+'</datalist>';
    g += '<div class="sp-grid2"><div><label>Сайт компании</label><select id="spf_web">'+opts([['','не указано'],['ok','проверен'],['warn','не открылся / есть вопросы'],['none','сайта нет']], s.web_state)+'</select></div>'
       + '<div><label>Пометка к сайту</label><input id="spf_webnote" value="'+esc(s.web_note)+'" placeholder="Сайт проверен"></div></div>';
    g += '<label>Адрес</label><input id="spf_addr" value="'+esc(s.addr)+'">';

    var w = '<label>Чем занимаются</label><textarea id="spf_what">'+esc(s.what)+'</textarea>';
    w += '<label>Факты о компании</label><textarea id="spf_facts" placeholder="по одному на строку">'+esc((s.facts||[]).join('\n'))+'</textarea><div class="hint">Год основания, площадь завода, сертификаты — по одному на строку</div>';
    w += '<label>Товары</label><textarea id="spf_products" placeholder="по одному на строку">'+esc((s.products||[]).join('\n'))+'</textarea>';
    w += '<label>Заметка</label><textarea id="spf_note" style="min-height:50px">'+esc(s.note)+'</textarea>';

    var pp = '<div id="spf_people">';
    (s.people||[]).forEach(function(p, i){ pp += personHtml(p, i); });
    pp += '</div><button type="button" class="sp-btn sm" style="margin-top:10px" onclick="spAddPerson()">'+ic('person')+'Добавить человека</button>';

    var ll = '<div id="spf_links">';
    (s.links||[]).forEach(function(l, i){ ll += linkHtml(l, i); });
    ll += '</div><button type="button" class="sp-btn sm" style="margin-top:10px" onclick="spAddLink()">'+ic('link')+'Добавить ссылку</button>';

    var f = '';
    if (!s.id) f += '<div class="sp-note" style="margin:0">Сначала нажмите «Сохранить» — после этого можно прикреплять визитки и каталоги.</div>';
    else f += '<div class="sp-cl" style="margin-top:0">' + upBtn('card', 'Визитка', 'image/*') + upBtn('site', 'Фото фабрики', 'image/*') + upBtn('doc', 'Наше фото, листовка', 'image/*') + upBtn('file', 'Каталог, видео, файл', '')
        + '</div><div id="spUps"></div><div id="spMedia" style="margin-top:10px">' + mediaEditHtml(s) + '</div>';

    var h = '<div class="sp-form">'
      + panel('Компания', 'factory', g)
      + panel('Описание', 'pdf', w, 'green')
      + panel('Контактные лица', 'person', pp)
      + panel('Ссылки', 'link', ll)
      + '<div class="sp-actions" style="margin:0 0 12px"><button class="sp-btn grn" id="spSaveBtn" onclick="spSave()">Сохранить</button>'
      + (s.id && SP.me.boss ? '<button class="sp-btn red" style="margin-left:auto" onclick="spDelete()">'+ic('del')+'Удалить поставщика</button>' : '')
      + '</div>'
      + panel('Визитки, фото и файлы', 'up', f, 'green')
      + '</div>';
    document.getElementById('spForm').innerHTML = h;
  }

  function upBtn(kind, label, accept){
    return '<label class="sp-btn sm" style="margin:0">'+ic('up')+label
      + '<input type="file" multiple '+(accept?'accept="'+accept+'"':'')+' style="display:none" onchange="spUpload(this,\''+kind+'\')"></label>';
  }
  var KIND_NAME = { card:'Визитка', doc:'Наше фото', file:'Файл', site:'Фото фабрики' };
  function mediaEditHtml(s){
    /* фото для сайта живут в редакторе страницы, здесь их не показываем */
    var m = (s.media || []).filter(function(x){ return x.kind !== 'web'; });
    if (!m.length) return '<div class="hint" style="font-size:.8rem;color:#8a93a6">Пока ничего не прикреплено.</div>';
    return m.map(function(x){
      return '<div class="sp-file"><div class="ic">'+(isImg(x)?'<img src="'+esc(x.url)+'" style="width:36px;height:36px;object-fit:cover;border-radius:8px">':ic(isVid(x)?'video':'pdf'))+'</div>'
        + '<div class="t sp-form"><input value="'+esc(x.title)+'" onchange="spMediaTitle('+x.id+',this.value)" style="padding:6px 8px;font-size:14px"><small>'+KIND_NAME[x.kind]+' · '+fsize(x.bytes)+'</small></div>'
        + '<button class="sp-x" title="Открепить" onclick="spMediaDel('+x.id+')">'+ic('del')+'</button></div>';
    }).join('');
  }

  function personHtml(p, i){
    var h = '<div class="sp-box" data-person="'+i+'"><div class="sp-grid2"><div><label style="margin-top:0">Имя</label><input class="pp_n" value="'+esc(p.n||'')+'"></div>'
      + '<div><label style="margin-top:0">Должность</label><input class="pp_r" value="'+esc(p.r||'')+'"></div></div><div class="pp_c">';
    (p.c||[]).forEach(function(c){ h += contactRow(c); });
    h += '</div><div style="display:flex;gap:6px;margin-top:8px"><button type="button" class="sp-btn sm" onclick="spAddContact(this)">+ контакт</button>'
      + '<button type="button" class="sp-btn sm red" style="margin-left:auto" onclick="spDelBox(this)">Убрать человека</button></div></div>';
    return h;
  }
  function contactRow(c){
    return '<div class="sp-crow"><select class="pc_t">'+opts(CTYPE, c[0]||'phone')+'</select><input class="pc_v" value="'+esc(c[1]||'')+'"><button type="button" class="sp-x" onclick="spDelRow(this)">'+ic('x')+'</button></div>';
  }
  function linkHtml(l){
    return '<div class="sp-crow" data-link="1"><select class="pl_t">'+opts(LTYPE, l[0]||'language')+'</select><input class="pl_n" value="'+esc(l[1]||'')+'" placeholder="Подпись" style="flex:0 0 32%"><input class="pl_u" value="'+esc(l[2]||'')+'" placeholder="https://"><button type="button" class="sp-x" onclick="spDelRow(this)">'+ic('x')+'</button></div>';
  }
  function spAddPerson(){ var w = document.getElementById('spf_people'); w.insertAdjacentHTML('beforeend', personHtml({n:'',r:'',c:[['phone','']]}, w.children.length)); }
  function spAddContact(b){ b.closest('.sp-box').querySelector('.pp_c').insertAdjacentHTML('beforeend', contactRow(['chat',''])); }
  function spAddLink(){ document.getElementById('spf_links').insertAdjacentHTML('beforeend', linkHtml(['language','Сайт',''])); }
  function spDelRow(b){ var r = b.closest('.sp-crow'); if (r) r.parentNode.removeChild(r); }
  function spDelBox(b){ var r = b.closest('.sp-box'); if (r) r.parentNode.removeChild(r); }

  function spSegPick(sel){
    if (sel.value !== '__new') return;
    var name = prompt('Название нового раздела');
    if (!name || !name.trim()) { sel.value = ''; return; }
    post('sup_seg_add', {name:name.trim()}).then(function(d){
      if (!d || !d.ok) { msg((d&&d.error)||'Не удалось'); sel.value=''; return; }
      if (!SP.segs.some(function(g){ return g.code===d.seg.code; })) SP.segs.push(d.seg);
      var a = document.getElementById('spf_seg').value, b = document.getElementById('spf_seg2').value;
      if (sel.id==='spf_seg') a = d.seg.code; else b = d.seg.code;
      document.getElementById('spf_seg').innerHTML = segOpts(a==='__new'?'':a);
      document.getElementById('spf_seg2').innerHTML = segOpts(b==='__new'?'':b, true);
    });
  }

  function lines(id){ return (document.getElementById(id).value||'').split('\n').map(function(x){return x.trim();}).filter(Boolean); }
  function collect(){
    var s = SP.edit;
    function v(id){ return (document.getElementById(id).value||'').trim(); }
    var people = [];
    document.querySelectorAll('#spf_people .sp-box').forEach(function(b){
      var c = [];
      b.querySelectorAll('.sp-crow').forEach(function(r){ var val = r.querySelector('.pc_v').value.trim(); if (val) c.push([r.querySelector('.pc_t').value, val]); });
      var n = b.querySelector('.pp_n').value.trim(), ro = b.querySelector('.pp_r').value.trim();
      if (n || ro || c.length) people.push({n:n, r:ro, c:c});
    });
    var links = [];
    document.querySelectorAll('#spf_links .sp-crow').forEach(function(r){
      var u = r.querySelector('.pl_u').value.trim(); if (!u) return;
      if (!/^https?:\/\//i.test(u)) u = 'https://' + u;
      links.push([r.querySelector('.pl_t').value, r.querySelector('.pl_n').value.trim() || 'Ссылка', u]);
    });
    var seg = v('spf_seg'), seg2 = v('spf_seg2');
    return { id:s.id, name:v('spf_name'), full:v('spf_full'), seg:(seg==='__new'?'unk':seg)||'unk', seg2:(seg2==='__new'?'':seg2),
      city:v('spf_city'), kind:v('spf_kind'), event:v('spf_event'), contact_state:v('spf_contact'),
      web_state:v('spf_web'), web_note:v('spf_webnote'), what:v('spf_what'), facts:lines('spf_facts'), products:lines('spf_products'),
      addr:v('spf_addr'), note:v('spf_note'), people:people, links:links };
  }

  function spSave(){
    var d = collect();
    if (!d.name) { msg('Впишите название'); document.getElementById('spf_name').focus(); return; }
    var b = document.getElementById('spSaveBtn'); b.disabled = true; b.textContent = 'Сохраняю…';
    post('sup_save', {sup:d}).then(function(r){
      b.disabled = false; b.textContent = 'Сохранить';
      if (!r || !r.ok) { msg((r&&r.error)||'Не удалось сохранить', 3500); return; }
      var wasNew = !SP.edit.id;
      upsert(r.sup); SP.edit = JSON.parse(JSON.stringify(r.sup));
      if (wasNew) { renderForm(); msg('Сохранено. Теперь можно прикрепить визитку и файлы', 3200); }
      else { SP.cur = r.sup; msg('Сохранено'); spFormClose(true); }
    }).catch(function(){ b.disabled = false; b.textContent = 'Сохранить'; msg('Нет связи с сервером', 3000); });
  }
  function upsert(s){
    var i; for (i=0;i<SP.rows.length;i++) if (SP.rows[i].id===s.id) { SP.rows[i] = s; break; }
    if (i===SP.rows.length) SP.rows.push(s);
    SP.rows.sort(function(a,b){ return a.name.localeCompare(b.name, 'ru'); });
  }

  /* Закрыть форму: если открывали из карточки — вернуться в карточку. */
  function spFormClose(fromSave){
    var f = document.getElementById('spForm');
    if (f.style.display !== 'block') return;
    f.style.display = 'none';
    var s = SP.edit && SP.edit.id ? byId(SP.edit.id) : null;
    SP.edit = null;
    if (s && !SP.formFromList) { SP.cur = s; renderCard(s); document.getElementById('spCard').style.display = 'block'; }
    else if (s && SP.formFromList) { SP.cur = s; renderCard(s); document.getElementById('spCard').style.display = 'block'; document.getElementById('spList').style.display='none'; SP.formFromList = false; window.scrollTo(0,0); return; }
    else { showList(); spRender(); }
    window.scrollTo(0,0);
    if (window.ecoHistBack) window.ecoHistBack();
  }

  function spDelete(){
    var s = SP.edit; if (!s || !s.id) return;
    if (!confirm('Удалить поставщика «'+s.name+'» из базы?')) return;
    post('sup_delete', {id:s.id}).then(function(r){
      if (!r || !r.ok) { msg((r&&r.error)||'Не удалось', 3000); return; }
      SP.rows = SP.rows.filter(function(x){ return x.id!==s.id; });
      SP.edit = null; SP.cur = null;
      document.getElementById('spForm').style.display = 'none';
      document.getElementById('spCard').style.display = 'none';
      showList(); spRender(); msg('Поставщик удалён');
      if (window.ecoHistBack) window.ecoHistBack();
    });
  }

  function spMediaTitle(id, t){ post('sup_media_save', {id:id, title:t}).then(function(r){ if (r&&r.ok){ var m=findMedia(id); if(m) m.title=t; refreshRow(); msg('Название сохранено'); } }); }
  function spMediaDel(id){
    if (!confirm('Открепить файл от поставщика?')) return;
    post('sup_media_del', {id:id}).then(function(r){
      if (!r || !r.ok) { msg((r&&r.error)||'Не удалось'); return; }
      SP.edit.media = (SP.edit.media||[]).filter(function(m){ return m.id!==id; });
      refreshRow(); document.getElementById('spMedia').innerHTML = mediaEditHtml(SP.edit);
    });
  }
  function refreshRow(){ var r = byId(SP.edit.id); if (r) r.media = JSON.parse(JSON.stringify(SP.edit.media||[])); }

  /* ---------- загрузка файлов ----------
     Фото визиток и листовок уменьшаем на телефоне до 1800 px — так они
     весят 300–600 КБ и грузятся быстро. Каталоги и видео идут как есть,
     кусками по 2 МБ, с полоской хода. */
  function shrink(file){
    return new Promise(function(res){
      if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size < 700*1024) { res(file); return; }
      var url = URL.createObjectURL(file), img = new Image();
      img.onload = function(){
        var max = 1800, w = img.naturalWidth, h = img.naturalHeight, k = Math.min(1, max/Math.max(w,h));
        var c = document.createElement('canvas'); c.width = Math.round(w*k); c.height = Math.round(h*k);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        c.toBlob(function(b){
          if (!b || b.size >= file.size) { res(file); return; }
          var name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
          try { res(new File([b], name, {type:'image/jpeg'})); } catch(e){ b.name = name; res(b); }
        }, 'image/jpeg', 0.85);
      };
      img.onerror = function(){ URL.revokeObjectURL(url); res(file); };
      img.src = url;
    });
  }
  function b64(blob){
    return new Promise(function(res, rej){
      var r = new FileReader();
      r.onload = function(){ var s = String(r.result); res(s.slice(s.indexOf(',')+1)); };
      r.onerror = rej; r.readAsDataURL(blob);
    });
  }
  function rid(){ var a = new Uint8Array(12); (window.crypto||window.msCrypto).getRandomValues(a); return Array.prototype.map.call(a, function(x){ return ('0'+x.toString(16)).slice(-2); }).join(''); }

  function spUpload(input, kind){
    var files = Array.prototype.slice.call(input.files || []);
    input.value = '';
    if (!files.length || !SP.edit || !SP.edit.id) return;
    var supId = SP.edit.id;
    var chain = Promise.resolve();
    files.forEach(function(f){ chain = chain.then(function(){ return uploadOne(f, kind, supId); }); });
  }

  function uploadOne(orig, kind, supId){
    var box = document.createElement('div'); box.className = 'sp-up';
    box.innerHTML = '<div>'+esc(orig.name)+' · <span class="st">готовлю…</span></div><div class="sp-bar"><i></i></div>';
    var ups = document.getElementById('spUps'); if (ups) ups.appendChild(box);
    var st = box.querySelector('.st'), bar = box.querySelector('i');
    var prep = (kind==='file') ? Promise.resolve(orig) : shrink(orig);
    return prep.then(function(file){
      var size = file.size, up = rid(), n = Math.max(1, Math.ceil(size / CHUNK)), i = 0;
      var name = file.name || orig.name || 'file';
      function next(){
        if (i >= n) {
          st.textContent = 'сохраняю в хранилище…';
          return post('sup_up_done', {up:up, sup_id:supId, kind:kind, name:name, title:name.replace(/\.[^.]+$/,''), size:size});
        }
        var part = file.slice(i*CHUNK, Math.min(size, (i+1)*CHUNK));
        return b64(part).then(function(data){ return post('sup_up_chunk', {up:up, idx:i, data:data}); })
          .then(function(r){
            if (!r || !r.ok) throw new Error((r&&r.error)||'обрыв связи');
            i++; bar.style.width = Math.round(i/n*100) + '%';
            st.textContent = fsize(Math.min(size, i*CHUNK)) + ' из ' + fsize(size);
            return next();
          });
      }
      return next();
    }).then(function(r){
      if (!r || !r.ok) throw new Error((r&&r.error)||'не сохранилось');
      st.textContent = 'готово'; bar.style.width = '100%';
      setTimeout(function(){ if (box.parentNode) box.parentNode.removeChild(box); }, 1500);
      if (SP.edit && SP.edit.id === supId) {
        SP.edit.media = (SP.edit.media||[]).concat([r.media]);
        refreshRow();
        var mb = document.getElementById('spMedia'); if (mb) mb.innerHTML = mediaEditHtml(SP.edit);
      }
    }).catch(function(e){
      st.textContent = 'ошибка: ' + (e && e.message ? e.message : 'не загрузилось');
      st.style.color = '#A11A1A';
    });
  }

  /* ---------- разовый перенос начальной базы (только босс) ---------- */
  function seedBox(){
    var b = document.getElementById('spSeed');
    if (!SP.seed) { b.innerHTML = ''; return; }
    b.innerHTML = '<div class="sp-seed"><b>На сервере лежит начальная база поставщиков</b>'
      + 'Нажмите «Перенести» — поставщики появятся в списке, а визитки, каталоги и видео уйдут в хранилище. Окно не закрывайте, пока идёт перенос.'
      + '<div class="sp-actions"><button class="sp-btn grn" id="spSeedBtn" onclick="spSeedRun()">Перенести</button></div>'
      + '<div class="sp-bar" id="spSeedBar" style="display:none"><i></i></div><div id="spSeedSt" style="margin-top:6px;font-size:.82rem"></div></div>';
  }
  function spSeedRun(){
    var btn = document.getElementById('spSeedBtn'), st = document.getElementById('spSeedSt'), bar = document.getElementById('spSeedBar');
    btn.disabled = true; bar.style.display = 'block';
    var moved = 0, total = null, stuck = 0;
    function step(){
      st.textContent = 'Переношу… перенесено файлов: ' + moved;
      post('sup_seed_run', {}).then(function(r){
        if (!r || !r.ok) { st.textContent = 'Ошибка: ' + ((r&&r.error)||'нет ответа'); btn.disabled = false; return; }
        moved += (r.done||[]).length;
        if (total === null) total = moved + (r.left||0) + (r.missing||0);
        bar.querySelector('i').style.width = (total ? Math.round(moved/Math.max(total,1)*100) : 100) + '%';
        if (r.finished) { st.textContent = 'Готово: перенесено файлов ' + moved + '.'; SP.seed = false; load(true).then(function(){ msg('Начальная база перенесена', 3000); }); return; }
        if (!(r.done||[]).length && !(r.left||0) && (r.missing||0)) {
          st.textContent = 'Перенесено файлов: ' + moved + '. На сервере не хватает ещё ' + r.missing + ' — распакуйте в папку eco все архивы начальной базы и нажмите «Перенести» снова.';
          btn.disabled = false; return;
        }
        if ((r.errors||[]).length && !(r.done||[]).length) {
          if (++stuck >= 2) { st.innerHTML = 'Не удалось перенести:<br>' + r.errors.map(esc).join('<br>'); btn.disabled = false; return; }
        } else stuck = 0;
        step();
      }).catch(function(){ st.textContent = 'Связь прервалась — нажмите «Перенести» ещё раз, перенос продолжится с того же места.'; btn.disabled = false; });
    }
    step();
  }

  /* ---------- «Назад»: что закрывать в этом разделе ----------
     Возвращает 'ui' (без шага истории), 'hist' (со своим шагом) или false. */

  /* ============================================================
     СТРАНИЦА ПОСТАВЩИКА НА САЙТЕ itcpartner.ru (4.9.0, 10.10.2026)
     По образцу /pecron: главное фото, о заводе, товары с фото,
     кому подойдёт, преимущества, вопросы. Контакты фабрики, их сайты
     и каталоги на сайт НЕ идут — таких полей здесь нет.
     Фото товаров вырезаются прямо из каталогов (PDF) и фото визиток.
     ============================================================ */
  var WEB_IC = 'web';
  function webEmpty(s){
    return { slug:'', brand:s.name, crumb:'', menu_sub:'', title:'', descr:'', h1:'', lead:'', bullets:[], cta:'',
      hero:'', hero_alt:'', about_title:'', about_lead:'', about_bullets:[], factory:'', factory_alt:'',
      models_title:'МОДЕЛИ, ДОСТУПНЫЕ К ЗАКАЗУ', models_lead:'', groups:[], uses_title:'КОМУ ПОДОЙДЁТ', uses_lead:'', uses:[],
      adv:[], adv_bottom:'', coop_lead:'', coop:[], faq:[], section:'', city:s.city||'', projects:[], awards:[] };
  }

  function spWebOpen(id){
    var s = byId(id); if (!s || !SP.me || !SP.me.boss) return;   // только босс
    SP.cur = s; SP.web = null; SP.webStatus = '';
    document.getElementById('spCard').style.display = 'none';
    document.getElementById('spList').style.display = 'none';
    document.getElementById('spForm').style.display = 'none';
    var w = document.getElementById('spWeb'); w.style.display = 'block';
    w.innerHTML = '<div class="sp-empty">Загружаю…</div>';
    setTop('Сайт: ' + s.name); document.getElementById('spAddBtn').style.display = 'none';
    window.scrollTo(0,0); pushStep();
    get('sup_web_get&id=' + encodeURIComponent(id)).then(function(d){
      if (!d || !d.ok) { w.innerHTML = '<div class="sp-empty">'+esc((d&&d.error)||'Не удалось открыть')+'</div>'; return; }
      SP.web = d.web || webEmpty(s); SP.webStatus = d.status || ''; SP.webSite = d.site || 'https://itcpartner.ru';
      renderWeb();
    }).catch(function(){ w.innerHTML = '<div class="sp-empty">Нет связи с сервером.</div>'; });
  }
  function spWebClose(){
    var w = document.getElementById('spWeb');
    if (w.style.display !== 'block') return;
    w.style.display = 'none';
    if (SP.cur) { renderCard(SP.cur); document.getElementById('spCard').style.display = 'block'; } else { showList(); spRender(); }
    window.scrollTo(0,0);
    if (window.ecoHistBack) window.ecoHistBack();
  }

  /* --- поля --- */
  function wIn(k, label, ph){ return '<label>'+label+'</label><input data-wk="'+k+'" value="'+esc(SP.web[k]||'')+'"'+(ph?' placeholder="'+esc(ph)+'"':'')+' oninput="spWebSet(this)">'; }
  function wTa(k, label, hint){ return '<label>'+label+'</label><textarea data-wk="'+k+'" oninput="spWebSet(this)">'+esc(SP.web[k]||'')+'</textarea>'+(hint?'<div class="hint">'+hint+'</div>':''); }
  function wLines(k, label, hint){ return '<label>'+label+'</label><textarea data-wl="'+k+'" oninput="spWebSet(this)">'+esc((SP.web[k]||[]).join('\n'))+'</textarea><div class="hint">'+(hint||'По одному на строку')+'</div>'; }
  function wPairs(k, label, hint){ return '<label>'+label+'</label><textarea data-wp="'+k+'" style="min-height:120px" oninput="spWebSet(this)">'+esc((SP.web[k]||[]).map(function(x){ return x.h + ' — ' + x.p; }).join('\n'))+'</textarea><div class="hint">'+hint+'</div>'; }
  function spWebSet(el){
    var w = SP.web; if (!w) return;
    var k;
    if ((k = el.getAttribute('data-wk'))) w[k] = el.value;
    else if ((k = el.getAttribute('data-wl'))) w[k] = el.value.split('\n').map(function(x){return x.trim();}).filter(Boolean);
    else if ((k = el.getAttribute('data-wp'))) w[k] = el.value.split('\n').map(function(x){ var i = x.indexOf(' — '); if (i<0) i = x.indexOf(' - '); return i<0 ? {h:x.trim(), p:''} : {h:x.slice(0,i).trim(), p:x.slice(i+3).trim()}; }).filter(function(x){ return x.h || x.p; });
    else if (el.hasAttribute('data-gi')) {
      var g = w.groups[+el.getAttribute('data-gi')], ii = el.getAttribute('data-ii'), f = el.getAttribute('data-f');
      if (ii === null || ii === '') g[f] = el.value; else g.items[+ii][f] = el.value;
    }
    else if (el.hasAttribute('data-fi')) { var fq = w.faq[+el.getAttribute('data-fi')]; fq[el.getAttribute('data-f')] = el.value; }
    SP.webDirty = true;
  }

  function photoPick(key, label){
    var u = SP.web[key];
    return '<label>'+label+'</label><div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">'
      + (u ? '<img src="'+esc(u)+'" style="width:120px;height:80px;object-fit:cover;border:1px solid var(--line);border-radius:3px">' : '<div class="sp-thumb" style="width:120px;height:80px">'+ic('image')+'</div>')
      + '<div class="sp-cl" style="margin:0"><button type="button" class="sp-btn sm" onclick="spCropOpen(\'set:'+key+'\')">'+ic('image')+'Вырезать из каталога</button>'
      + '<label class="sp-btn sm" style="margin:0">'+ic('up')+'Загрузить<input type="file" accept="image/*" style="display:none" onchange="spWebFile(this,\'set:'+key+'\')"></label>'
      + (u ? '<button type="button" class="sp-btn sm red" onclick="spWebPhotoDel(\'set:'+key+'\')">Убрать</button>' : '') + '</div></div>';
  }

  // варианты заказа и упаковка товара (4.10.5) — только показываем, приходят из каталога
  var ORD_U = {pcs:'шт.', set:'компл.', pair:'пар', m:'м'};
  function ordInfo(it){
    var o = it.opt || [], k = it.pack || [], t = [];
    if (o.length) t.push('Варианты: ' + o.map(function(x){ return x.n + (x.v ? ' — ' + x.v.join(', ') : ' (вписывает покупатель' + (x.f ? ': ' + x.f : '') + ')'); }).join('; '));
    if (k.length) t.push('Упаковка: ' + k.map(function(r){
      var w = (r.w||[]).map(function(x, j){ return x === null || x === undefined || !o[j] || !o[j].v ? '' : o[j].v[x]; }).filter(Boolean).join(', ');
      return r.q + ' ' + (ORD_U[r.u] || 'шт.') + (w ? ' (' + w + ')' : '');
    }).join('; '));
    else if (o.length || (it.u && it.u !== 'pcs')) t.push('Заказ в: ' + (ORD_U[it.u] || 'шт.') + ', упаковка не указана');
    return t.length ? '<div style="font-size:12px;line-height:1.45;color:#5b6b7f;margin-top:6px">' + esc(t.join(' · ')) + '</div>' : '';
  }
  function groupsHtml(){
    var w = SP.web, h = '';
    (w.groups||[]).forEach(function(g, gi){
      h += '<div class="sp-box"><div class="sp-grid2"><div><label style="margin-top:0">Группа товаров</label><input data-gi="'+gi+'" data-f="name" value="'+esc(g.name)+'" oninput="spWebSet(this)"></div>'
        + '<div><label style="margin-top:0">Пояснение к группе</label><input data-gi="'+gi+'" data-f="sub" value="'+esc(g.sub)+'" oninput="spWebSet(this)"></div></div>';
      (g.items||[]).forEach(function(it, ii){
        h += '<div style="border-top:1px dashed #d5dce6;margin-top:10px;padding-top:8px"><div class="sp-grid2"><div><label style="margin-top:0">Товар</label><input data-gi="'+gi+'" data-ii="'+ii+'" data-f="name" value="'+esc(it.name)+'" oninput="spWebSet(this)"></div>'
          + '<div><label style="margin-top:0">Коротко о товаре</label><input data-gi="'+gi+'" data-ii="'+ii+'" data-f="desc" value="'+esc(it.desc)+'" oninput="spWebSet(this)"></div></div>'
          + ordInfo(it)
          + '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">'
          + (it.photos||[]).map(function(p, pi){
              return '<div style="position:relative"><img src="'+esc(p)+'" style="width:76px;height:76px;object-fit:cover;border:1px solid var(--line);border-radius:3px;display:block">'
                + '<button type="button" title="Убрать фото" onclick="spWebPhotoDel(\'it:'+gi+':'+ii+':'+pi+'\')" style="position:absolute;top:2px;right:2px;width:22px;height:22px;border:0;border-radius:3px;background:rgba(161,26,26,.9);color:#fff;font:700 13px/22px sans-serif;cursor:pointer;padding:0">×</button>'
                + (pi ? '<button type="button" title="Сделать первым" onclick="spWebPhotoFirst('+gi+','+ii+','+pi+')" style="position:absolute;bottom:2px;left:2px;height:20px;border:0;border-radius:3px;background:rgba(17,44,147,.85);color:#fff;font:600 10px/20px sans-serif;cursor:pointer;padding:0 5px">первое</button>' : '')
                + '</div>';
            }).join('')
          + '</div><div class="sp-cl"><button type="button" class="sp-btn sm" onclick="spCropOpen(\'it:'+gi+':'+ii+'\')">'+ic('image')+'Вырезать из каталога</button>'
          + '<label class="sp-btn sm" style="margin:0">'+ic('up')+'Загрузить фото<input type="file" accept="image/*" multiple style="display:none" onchange="spWebFile(this,\'it:'+gi+':'+ii+'\')"></label>'
          + '<button type="button" class="sp-btn sm red" style="margin-left:auto" onclick="spWebItemDel('+gi+','+ii+')">Убрать товар</button></div></div>';
      });
      h += '<div class="sp-cl" style="margin-top:10px"><button type="button" class="sp-btn sm" onclick="spWebItemAdd('+gi+')">+ товар</button>'
        + '<button type="button" class="sp-btn sm red" style="margin-left:auto" onclick="spWebGroupDel('+gi+')">Убрать группу</button></div></div>';
    });
    return h + '<button type="button" class="sp-btn sm" style="margin-top:10px" onclick="spWebGroupAdd()">+ группа товаров</button>';
  }
  function faqHtml(){
    return (SP.web.faq||[]).map(function(f, i){
      return '<div class="sp-box"><label style="margin-top:0">Вопрос</label><input data-fi="'+i+'" data-f="q" value="'+esc(f.q)+'" oninput="spWebSet(this)">'
        + '<label>Ответ</label><textarea data-fi="'+i+'" data-f="a" oninput="spWebSet(this)">'+esc(f.a)+'</textarea>'
        + '<div class="sp-cl"><button type="button" class="sp-btn sm red" style="margin-left:auto" onclick="spWebFaqDel('+i+')">Убрать вопрос</button></div></div>';
    }).join('') + '<button type="button" class="sp-btn sm" style="margin-top:10px" onclick="spWebFaqAdd()">+ вопрос</button>';
  }

  function renderWeb(){
    var w = SP.web, live = SP.webStatus === 'live';
    var url = SP.webSite + '/' + (w.slug || '');
    var st = '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">'
      + (live ? '<span class="sp-pill ok">'+ic('ok')+'На сайте</span><button type="button" class="sp-btn sm" onclick="spOpenUrl(\''+esc(url)+'\')">'+ic('web')+'Открыть страницу</button>'
              : '<span class="sp-pill warn">'+ic('help')+(SP.webStatus ? 'Черновик, на сайте не видна' : 'Страницы ещё нет')+'</span>')
      + '</div><div class="hint" style="font-size:.78rem;color:#6b7a99;margin-top:8px">На сайт идут только описание, главное фото, фото завода и товары. Контакты фабрики, их сайты и каталоги на сайт не попадают.</div>'
      + '<div class="sp-actions"><label class="sp-btn grn" style="margin:0;color:#fff">'+ic('up')+'Загрузить готовую страницу<input type="file" accept=".zip,application/zip" style="display:none" onchange="spWebPack(this)"></label>'
      + '<button type="button" class="sp-btn" id="spWebDraftBtn" onclick="spWebDraft()">'+ic('edit')+(w.h1?'Составить текст заново':'Составить текст из карточки')+'</button>'
      + '<button type="button" class="sp-btn" onclick="spWebSave()">Сохранить черновик</button>'
      + '<button type="button" class="sp-btn grn" id="spWebPubBtn" onclick="spWebPublish()">'+ic('up')+(live?'Обновить на сайте':'Опубликовать на сайте')+'</button>'
      + (live ? '<button type="button" class="sp-btn red" onclick="spWebUnpublish()">Снять с сайта</button>' : '') + '</div>'
      + '<div id="spWebMsg" style="font-size:.84rem;margin-top:8px;color:#3d4a63"></div>';
    var h = '<div class="sp-form">'
      + panel('Страница на сайте itcpartner.ru', 'web', st)
      + panel('Адрес и меню', 'link',
          '<label>Адрес страницы</label><div style="display:flex;align-items:center;gap:4px"><span style="color:#6b7a99;white-space:nowrap">itcpartner.ru/</span><input data-wk="slug" value="'+esc(w.slug)+'" placeholder="ronma" oninput="spWebSet(this)"></div><div class="hint">Латиницей, маленькими буквами, через дефис</div>'
          + '<label>Раздел сайта</label><select data-wk="section" onchange="spWebSet(this)">' + [['','Отдельной строкой в меню «Направления»'],['solnechnaya-energetika','Солнечная энергетика'],['selhoztehnika','Сельхозтехника'],['oborudovanie-dlya-kafe','Оборудование для кафе и торговли'],['tehnika-dlya-volos','Техника для ухода за волосами'],['mebel','Мебель']].map(function(o){ return '<option value="'+o[0]+'"'+((w.section||'')===o[0]?' selected':'')+'>'+o[1]+'</option>'; }).join('') + '</select><div class="hint">В разделе страница встанет плиткой рядом с другими заводами</div>'
          + wIn('crumb', 'Название в меню сайта', 'Солнечные панели Ronma') + wIn('menu_sub', 'Подпись в меню', 'Панели и накопители с завода') + wIn('city', 'Город завода (на плитке в разделе)', 'Сямынь'))
      + panel('Первый экран', 'image', wIn('h1', 'Заголовок') + wTa('lead', 'Подзаголовок') + wLines('bullets', 'Пункты под заголовком', 'По одному на строку, обычно 4') + wIn('cta', 'Надпись на кнопке', 'Запросить цены') + photoPick('hero', 'Главное фото') + wIn('hero_alt', 'Подпись к главному фото (для поиска)'), 'green')
      + panel('О заводе', 'factory', wIn('about_title', 'Заголовок', 'О ЗАВОДЕ …') + wTa('about_lead', 'О заводе в двух предложениях') + wLines('about_bullets', 'Факты о заводе') + photoPick('factory', 'Фото завода или продукции') + wIn('factory_alt', 'Подпись к фото'))
      + panel('Товары', 'store', wIn('models_title', 'Заголовок блока') + wIn('models_lead', 'Пояснение') + '<div id="spWebGroups">' + groupsHtml() + '</div>', 'green')
      + panel('Кому подойдёт', 'person', wIn('uses_title', 'Заголовок') + wIn('uses_lead', 'Пояснение') + wPairs('uses', 'Карточки', 'По одной на строку: «Заголовок — текст», обычно 6'))
      + panel('Преимущества', 'ok', wPairs('adv', 'Преимущества', 'По одному на строку: «ЗАГОЛОВОК — текст», ровно 6') + wIn('adv_bottom', 'Строка под преимуществами'))
      + panel('Сотрудничество', 'person', wIn('coop_lead', 'Подзаголовок') + wLines('coop', 'Кому предлагаем'))
      + panel('Вопросы и ответы', 'help', '<div id="spWebFaq">' + faqHtml() + '</div>', 'green')
      + panel('Для поиска Яндекса', 'web', wIn('title', 'Заголовок вкладки') + wTa('descr', 'Описание в поиске'))
      + '</div>';
    document.getElementById('spWeb').innerHTML = h;
  }
  function webMsg(t, bad){ var m = document.getElementById('spWebMsg'); if (m) { m.textContent = t; m.style.color = bad ? '#A11A1A' : '#3d4a63'; } }
  function rerenderKeepScroll(){ var y = window.scrollY; renderWeb(); window.scrollTo(0, y); }

  function spWebGroupAdd(){ SP.web.groups.push({name:'', sub:'', items:[{name:'', desc:'', photos:[]}]}); rerenderKeepScroll(); }
  function spWebGroupDel(gi){ if (!confirm('Убрать группу целиком?')) return; SP.web.groups.splice(gi,1); rerenderKeepScroll(); }
  function spWebItemAdd(gi){ SP.web.groups[gi].items.push({name:'', desc:'', photos:[]}); rerenderKeepScroll(); }
  function spWebItemDel(gi, ii){ SP.web.groups[gi].items.splice(ii,1); rerenderKeepScroll(); }
  function spWebFaqAdd(){ SP.web.faq.push({q:'', a:''}); rerenderKeepScroll(); }
  function spWebFaqDel(i){ SP.web.faq.splice(i,1); rerenderKeepScroll(); }
  function spWebPhotoFirst(gi, ii, pi){ var p = SP.web.groups[gi].items[ii].photos; p.unshift(p.splice(pi,1)[0]); rerenderKeepScroll(); }
  function spWebPhotoDel(target){
    var t = target.split(':');
    if (t[0] === 'set') SP.web[t[1]] = '';
    else SP.web.groups[+t[1]].items[+t[2]].photos.splice(+t[3], 1);
    rerenderKeepScroll();
  }
  function webPut(target, url){
    var t = target.split(':');
    if (t[0] === 'set') SP.web[t[1]] = url;
    else SP.web.groups[+t[1]].items[+t[2]].photos.push(url);
    SP.webDirty = true;
  }

  /* Загрузить готовый кусок (фото) в хранилище как «фото для сайта». */
  function webUpload(blob, name){
    var supId = SP.cur.id, up = rid(), size = blob.size, n = Math.max(1, Math.ceil(size / CHUNK)), i = 0;
    function next(){
      if (i >= n) return post('sup_up_done', {up:up, sup_id:supId, kind:'web', name:name, title:name.replace(/\.[^.]+$/,''), size:size});
      return b64(blob.slice(i*CHUNK, Math.min(size, (i+1)*CHUNK))).then(function(data){ return post('sup_up_chunk', {up:up, idx:i, data:data}); })
        .then(function(r){ if (!r || !r.ok) throw new Error((r&&r.error)||'обрыв связи'); i++; return next(); });
    }
    return next().then(function(r){ if (!r || !r.ok) throw new Error((r&&r.error)||'не сохранилось'); return r.media; });
  }
  function spWebFile(input, target){
    var files = Array.prototype.slice.call(input.files||[]); input.value = '';
    var chain = Promise.resolve();
    files.forEach(function(f){
      chain = chain.then(function(){ webMsg('Загружаю фото…'); return shrink(f); })
        .then(function(b){ return webUpload(b, b.name || f.name || 'photo.jpg'); })
        .then(function(m){ webPut(target, m.url); });
    });
    chain.then(function(){ webMsg('Фото добавлено. Не забудьте сохранить.'); rerenderKeepScroll(); })
      .catch(function(e){ webMsg('Ошибка: ' + (e && e.message || 'не загрузилось'), true); });
  }

  /* ---------- готовая страница от Claude ----------
     Архив stranica_<бренд>.zip: page.json (тексты, у фото — имена файлов)
     и папка photos/. Фото уходят в хранилище, страница встаёт черновиком. */
  function zipRead(buf){
    var v = new DataView(buf), u8 = new Uint8Array(buf), e = -1;
    for (var i = buf.byteLength - 22; i >= Math.max(0, buf.byteLength - 70000); i--) if (v.getUint32(i, true) === 0x06054b50) { e = i; break; }
    if (e < 0) return Promise.reject(new Error('это не архив zip'));
    var n = v.getUint16(e + 10, true), p = v.getUint32(e + 16, true), list = [];
    for (var k = 0; k < n; k++) {
      if (v.getUint32(p, true) !== 0x02014b50) break;
      var meth = v.getUint16(p + 10, true), csz = v.getUint32(p + 20, true), nl = v.getUint16(p + 28, true),
          xl = v.getUint16(p + 30, true), cl = v.getUint16(p + 32, true), off = v.getUint32(p + 42, true);
      var name = new TextDecoder().decode(u8.subarray(p + 46, p + 46 + nl));
      var ds = off + 30 + v.getUint16(off + 26, true) + v.getUint16(off + 28, true);
      list.push({name:name, meth:meth, data:u8.subarray(ds, ds + csz)});
      p += 46 + nl + xl + cl;
    }
    var out = {};
    return Promise.all(list.map(function(f){
      if (/\/$/.test(f.name)) return null;
      var key = f.name.replace(/^.*?(page\.json|photos\/)/, '$1');
      if (f.meth === 0) { out[key] = new Blob([f.data]); return null; }
      if (f.meth === 8 && window.DecompressionStream)
        return new Response(new Blob([f.data]).stream().pipeThrough(new DecompressionStream('deflate-raw'))).blob().then(function(b){ out[key] = b; });
      throw new Error('архив сжат неизвестным способом');
    })).then(function(){ return out; });
  }
  function spWebPack(input){
    var f = input.files && input.files[0]; input.value = ''; if (!f) return;
    if (SP.web && SP.web.h1 && !confirm('Страница будет заменена готовой из архива. Продолжить?')) return;
    webMsg('Открываю архив…');
    var files, page, names = [], urls = {};
    f.arrayBuffer().then(zipRead).then(function(z){
      files = z;
      if (!files['page.json']) throw new Error('в архиве нет page.json');
      return files['page.json'].text();
    }).then(function(t){
      page = JSON.parse(t);
      var want = function(x){ if (x && !/^https?:/.test(x) && names.indexOf(x) < 0) names.push(x); };
      want(page.hero); want(page.factory);
      (page.groups||[]).forEach(function(g){ (g.items||[]).forEach(function(it){ (it.photos||[]).forEach(want); }); });
      var miss = names.filter(function(x){ return !files[x]; });
      if (miss.length) throw new Error('в архиве нет фото: ' + miss.slice(0,3).join(', '));
      var i = 0;
      function next(){
        if (i >= names.length) return;
        var nm = names[i];
        webMsg('Загружаю фото ' + (i+1) + ' из ' + names.length + '…');
        return webUpload(new Blob([files[nm]], {type:'image/jpeg'}), nm.replace(/^photos\//, ''))
          .then(function(m){ urls[nm] = m.url; i++; return next(); });
      }
      return next();
    }).then(function(){
      var u = function(x){ return urls[x] || x || ''; };
      page.hero = u(page.hero); page.factory = u(page.factory);
      (page.groups||[]).forEach(function(g){ (g.items||[]).forEach(function(it){ it.photos = (it.photos||[]).map(u); }); });
      var keep = SP.web || {};
      SP.web = Object.assign(webEmpty(SP.cur), page);
      if (keep.slug && SP.webStatus === 'live') SP.web.slug = keep.slug;   // адрес живой страницы не меняем
      return spWebSave(true);
    }).then(function(ok){
      if (!ok) return;
      renderWeb();
      webMsg(SP.webStatus === 'live'
        ? 'Готовая страница загружена и уже на сайте. Нажмите «Обновить на сайте», чтобы обновился английский и китайский.'
        : 'Готовая страница загружена черновиком. Проверьте и нажмите «Опубликовать на сайте».');
    }).catch(function(e){ webMsg('Ошибка: ' + (e && e.message || 'не получилось'), true); });
  }
  window.spWebPack = spWebPack;

  function webPayload(){ return {id:SP.cur.id, web:SP.web}; }
  function spWebSave(silent){
    return post('sup_web_save', webPayload()).then(function(r){
      if (!r || !r.ok) { webMsg((r&&r.error)||'Не удалось сохранить', true); return false; }
      SP.web = r.web; SP.webStatus = r.status; SP.webDirty = false;
      if (!silent) { rerenderKeepScroll(); webMsg(SP.webStatus === 'live' ? 'Сохранено и обновлено на сайте' : 'Черновик сохранён'); }
      return true;
    });
  }
  function spWebDraft(){
    if (SP.web.h1 && !confirm('Текст страницы будет составлен заново из карточки поставщика. Подобранные фото останутся. Продолжить?')) return;
    var b = document.getElementById('spWebDraftBtn'); b.disabled = true;
    webMsg('Составляю текст страницы — это займёт до минуты…');
    post('sup_web_draft', {id:SP.cur.id}).then(function(r){
      b.disabled = false;
      if (!r || !r.ok) { webMsg((r&&r.error)||'Не получилось', true); return; }
      SP.web = r.web; SP.webStatus = r.status; renderWeb();
      webMsg('Текст готов. Проверьте его, подберите главное фото и фото товаров, затем «Опубликовать на сайте».');
    }).catch(function(){ b.disabled = false; webMsg('Связь прервалась, попробуйте ещё раз', true); });
  }
  function spWebPublish(){
    var b = document.getElementById('spWebPubBtn'); b.disabled = true;
    webMsg('Перевожу страницу на английский и китайский и выкладываю на сайт — до двух минут…');
    post('sup_web_publish', webPayload()).then(function(r){
      b.disabled = false;
      if (!r || !r.ok) { webMsg((r&&r.error)||'Не получилось', true); return; }
      SP.web = r.web; SP.webStatus = 'live'; SP.webDirty = false; rerenderKeepScroll();
      webMsg('Страница на сайте: ' + r.url);
    }).catch(function(){ b.disabled = false; webMsg('Связь прервалась. Проверьте страницу через минуту или нажмите ещё раз', true); });
  }
  function spWebUnpublish(){
    if (!confirm('Снять страницу с сайта? Черновик останется.')) return;
    post('sup_web_unpublish', {id:SP.cur.id}).then(function(r){
      if (!r || !r.ok) { webMsg((r&&r.error)||'Не получилось', true); return; }
      SP.webStatus = 'draft'; rerenderKeepScroll(); webMsg('Страница снята с сайта');
    });
  }

  /* ---------- вырезать фото из каталога ----------
     Каталог (PDF) или фото открывается во весь экран, пальцем выделяется
     товар — кнопкой «Сохранить» вырезанный кусок уходит в хранилище и
     встаёт к товару. Можно вырезать несколько ракурсов подряд. */
  var CROP = null;
  function pdfLib(){
    return new Promise(function(res, rej){
      if (window.pdfjsLib) { res(window.pdfjsLib); return; }
      var s = document.createElement('script'); s.src = '/eco/pdfjs/pdf.min.js';
      s.onload = function(){ try { pdfjsLib.GlobalWorkerOptions.workerSrc = '/eco/pdfjs/pdf.worker.min.js'; res(pdfjsLib); } catch(e){ rej(e); } };
      s.onerror = function(){ rej(new Error('не загрузился просмотрщик PDF')); };
      document.head.appendChild(s);
    });
  }
  function rawUrl(id){ return API + '?action=sup_media_raw&id=' + id + '&t=' + encodeURIComponent(tok()); }

  function spCropOpen(target){
    var src = (SP.cur.media||[]).filter(function(m){ return /pdf/.test(m.mime||'') || isImg(m); });
    if (!src.length) { webMsg('У поставщика нет каталогов и фото — сначала прикрепите их в карточке', true); return; }
    var single = target.indexOf('set:') === 0;
    var ov = document.createElement('div'); ov.className = 'sp-crop';
    ov.innerHTML = '<div class="sp-crop-bar"><select id="spCropSrc">' + src.map(function(m, i){
        return '<option value="'+i+'">'+esc((/pdf/.test(m.mime)?'Каталог: ':(m.kind==='card'?'Визитка: ':(m.kind==='web'?'Для сайта: ':'Фото: ')))+m.title)+'</option>'; }).join('') + '</select>'
      + '<span class="pg"><button type="button" data-c="prev">‹</button><b id="spCropPg">—</b><button type="button" data-c="next">›</button></span>'
      + '<button type="button" class="x" data-c="close">Закрыть</button></div>'
      + '<div class="sp-crop-hint">Выделите товар пальцем или мышью, затем «Сохранить». '+(single?'':'Можно вырезать несколько ракурсов подряд.')+'</div>'
      + '<div class="sp-crop-stage" id="spCropStage"><div class="sp-crop-load">Открываю…</div></div>'
      + '<div class="sp-crop-foot"><span id="spCropInfo"></span><button type="button" class="sp-btn grn" data-c="save">Сохранить выделенное</button></div>';
    document.body.appendChild(ov);
    CROP = { ov:ov, target:target, single:single, src:src, idx:0, pdf:null, page:1, pages:1, canvas:null, sel:null, saved:0 };
    ov.addEventListener('click', function(e){
      var b = e.target.closest ? e.target.closest('[data-c]') : null; if (!b) return;
      var c = b.getAttribute('data-c');
      if (c === 'close') spCropClose();
      else if (c === 'prev' && CROP.pdf && CROP.page > 1) { CROP.page--; cropRender(); }
      else if (c === 'next' && CROP.pdf && CROP.page < CROP.pages) { CROP.page++; cropRender(); }
      else if (c === 'save') cropSave();
    });
    ov.querySelector('#spCropSrc').addEventListener('change', function(){ CROP.idx = +this.value; cropLoad(); });
    cropLoad();
  }
  function spCropClose(){
    if (!CROP) return false;
    var saved = CROP.saved;
    if (CROP.ov.parentNode) CROP.ov.parentNode.removeChild(CROP.ov);
    CROP = null;
    if (saved) rerenderKeepScroll();
    return true;
  }
  function cropStage(html){ var s = document.getElementById('spCropStage'); if (s) s.innerHTML = html; return s; }
  function cropLoad(){
    var m = CROP.src[CROP.idx]; CROP.pdf = null; CROP.page = 1; CROP.sel = null;
    cropStage('<div class="sp-crop-load">Открываю…</div>');
    document.getElementById('spCropPg').textContent = '—';
    if (/pdf/.test(m.mime||'')) {
      pdfLib().then(function(lib){ return lib.getDocument({url: rawUrl(m.id)}).promise; })
        .then(function(pdf){ if (!CROP) return; CROP.pdf = pdf; CROP.pages = pdf.numPages; cropRender(); })
        .catch(function(e){ cropStage('<div class="sp-crop-load">Не удалось открыть каталог: '+esc(e && e.message || '')+'</div>'); });
    } else {
      var img = new Image();
      img.onload = function(){
        if (!CROP) return;
        var k = Math.min(1, 2400 / Math.max(img.naturalWidth, img.naturalHeight));
        var c = document.createElement('canvas'); c.width = Math.round(img.naturalWidth*k); c.height = Math.round(img.naturalHeight*k);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        cropShow(c);
      };
      img.onerror = function(){ cropStage('<div class="sp-crop-load">Не удалось открыть фото</div>'); };
      img.src = rawUrl(m.id);
    }
  }
  function cropRender(){
    var pdf = CROP.pdf, n = CROP.page;
    document.getElementById('spCropPg').textContent = n + ' из ' + CROP.pages;
    cropStage('<div class="sp-crop-load">Страница ' + n + '…</div>');
    pdf.getPage(n).then(function(p){
      var v1 = p.getViewport({scale:1});
      var scale = Math.min(3, 2200 / Math.max(v1.width, v1.height));
      var vp = p.getViewport({scale:scale});
      var c = document.createElement('canvas'); c.width = Math.floor(vp.width); c.height = Math.floor(vp.height);
      return p.render({canvasContext:c.getContext('2d'), viewport:vp}).promise.then(function(){ if (CROP && CROP.page === n) cropShow(c); });
    });
  }
  function cropShow(c){
    CROP.canvas = c; CROP.sel = null;
    var st = cropStage('');
    var wrap = document.createElement('div'); wrap.className = 'sp-crop-wrap';
    c.className = 'sp-crop-cv'; wrap.appendChild(c);
    var box = document.createElement('div'); box.className = 'sp-crop-sel'; box.style.display = 'none'; wrap.appendChild(box);
    st.appendChild(wrap);
    document.getElementById('spCropInfo').textContent = 'Ничего не выделено — сохранится весь лист';
    var start = null;
    function pt(e){ var r = c.getBoundingClientRect(); return { x: Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)), y: Math.max(0, Math.min(1, (e.clientY - r.top) / r.height)) }; }
    function draw(a, b){
      var x = Math.min(a.x,b.x), y = Math.min(a.y,b.y), w = Math.abs(a.x-b.x), h = Math.abs(a.y-b.y);
      CROP.sel = {x:x, y:y, w:w, h:h};
      box.style.display = 'block'; box.style.left = (x*100)+'%'; box.style.top = (y*100)+'%'; box.style.width = (w*100)+'%'; box.style.height = (h*100)+'%';
      document.getElementById('spCropInfo').textContent = 'Выделено ' + Math.round(w*c.width) + ' × ' + Math.round(h*c.height);
    }
    wrap.addEventListener('pointerdown', function(e){ start = pt(e); try { wrap.setPointerCapture(e.pointerId); } catch(x){} e.preventDefault(); });
    wrap.addEventListener('pointermove', function(e){ if (start) { draw(start, pt(e)); e.preventDefault(); } });
    wrap.addEventListener('pointerup', function(e){
      if (start) { var p = pt(e); if (Math.abs(p.x-start.x) < 0.02 || Math.abs(p.y-start.y) < 0.02) { CROP.sel = null; box.style.display = 'none'; document.getElementById('spCropInfo').textContent = 'Ничего не выделено — сохранится весь лист'; } }
      start = null;
    });
  }
  function cropSave(){
    if (!CROP || !CROP.canvas) return;
    var c = CROP.canvas, s = CROP.sel || {x:0, y:0, w:1, h:1};
    var sx = Math.round(s.x*c.width), sy = Math.round(s.y*c.height), sw = Math.max(1, Math.round(s.w*c.width)), sh = Math.max(1, Math.round(s.h*c.height));
    var k = Math.min(1, 1600 / Math.max(sw, sh));
    var out = document.createElement('canvas'); out.width = Math.round(sw*k); out.height = Math.round(sh*k);
    var g = out.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, out.width, out.height);
    g.drawImage(c, sx, sy, sw, sh, 0, 0, out.width, out.height);
    var info = document.getElementById('spCropInfo'); info.textContent = 'Сохраняю…';
    var cr = CROP;
    out.toBlob(function(b){
      webUpload(b, 'web_' + Date.now() + '.jpg').then(function(m){
        webPut(cr.target, m.url); cr.saved++;
        (SP.cur.media = SP.cur.media || []).push(m);
        if (cr.single) { spCropClose(); msg('Фото поставлено'); return; }
        if (CROP === cr) info.textContent = 'Сохранено фото № ' + cr.saved + '. Можно выделить следующий ракурс.';
      }).catch(function(e){ if (CROP === cr) info.textContent = 'Ошибка: ' + (e && e.message || 'не сохранилось'); });
    }, 'image/jpeg', 0.9);
  }

  window.spWebOpen = spWebOpen; window.spWebClose = spWebClose; window.spWebSet = spWebSet;
  window.spWebGroupAdd = spWebGroupAdd; window.spWebGroupDel = spWebGroupDel; window.spWebItemAdd = spWebItemAdd; window.spWebItemDel = spWebItemDel;
  window.spWebFaqAdd = spWebFaqAdd; window.spWebFaqDel = spWebFaqDel; window.spWebPhotoFirst = spWebPhotoFirst; window.spWebPhotoDel = spWebPhotoDel;
  window.spWebFile = spWebFile; window.spWebSave = spWebSave; window.spWebDraft = spWebDraft; window.spWebPublish = spWebPublish; window.spWebUnpublish = spWebUnpublish;
  window.spCropOpen = spCropOpen;

  window.spBackStep = function(){
    if (CROP) { spCropClose(); return 'ui'; }
    var lb = document.querySelectorAll('.sp-lb');
    if (lb.length) { var t = lb[lb.length-1]; t.parentNode.removeChild(t); return 'ui'; }
    var sec = document.getElementById('section-suppliers');
    if (!sec || sec.style.display !== 'block') return false;
    var wb = document.getElementById('spWeb');
    if (wb.style.display === 'block') {
      wb.style.display = 'none';
      if (SP.cur) { renderCard(SP.cur); document.getElementById('spCard').style.display = 'block'; } else { showList(); spRender(); }
      return 'hist';
    }
    var f = document.getElementById('spForm');
    if (f.style.display === 'block') {
      f.style.display = 'none';
      var s = SP.edit && SP.edit.id ? byId(SP.edit.id) : null; SP.edit = null;
      if (s && !SP.formFromList) { SP.cur = s; renderCard(s); document.getElementById('spCard').style.display = 'block'; }
      else { SP.formFromList = false; showList(); spRender(); }
      return 'hist';
    }
    var c = document.getElementById('spCard');
    if (c.style.display === 'block') { showList(); spRender(); return 'hist'; }
    return false;
  };

  /* Синяя кнопка «Назад» в полосе сверху: форма → карточка → список → меню. */
  function spTopBack(){
    if (document.getElementById('spWeb').style.display === 'block') { spWebClose(); return; }
    if (document.getElementById('spForm').style.display === 'block') { spFormClose(); return; }
    if (document.getElementById('spCard').style.display === 'block') { spBack(); return; }
    showHub();
  }
  window.spTopBack = spTopBack;

  window.spLoad = function(){ if (!SP.loaded) load(); else { showList(); spRender(); load(true); } };
  window.spRender = spRender; window.spSeg = spSeg; window.spOpen = spOpen; window.spBack = spBack;
  window.spCopy = spCopy; window.spOpenUrl = spOpenUrl; window.spView = spView;
  window.spNew = spNew; window.spEdit = spEdit; window.spSave = spSave; window.spFormClose = spFormClose; window.spDelete = spDelete;
  window.spAddPerson = spAddPerson; window.spAddContact = spAddContact; window.spAddLink = spAddLink; window.spDelRow = spDelRow; window.spDelBox = spDelBox;
  window.spSegPick = spSegPick; window.spUpload = spUpload; window.spMediaTitle = spMediaTitle; window.spMediaDel = spMediaDel;
  window.spSeedRun = spSeedRun;

  /* Прямая ссылка из заявки с сайта (4.10.8): …/eco/?sup=ИД — сразу раздел «Поставщики» и карточка этого поставщика.
     Без входа список не отдаётся — тогда просто раздел с сообщением сервера. */
  (function(){
    var m = /[?&]sup=([A-Za-z0-9_-]{1,64})/.exec(location.search || ''); if (!m) return;
    var id = m[1];
    function go(){
      showSection('suppliers');
      try{ history.replaceState({ section:'suppliers' }, '', location.pathname + '#suppliers'); }catch(e){}
      (SP.loaded ? Promise.resolve() : load()).then(function(){
        if (byId(id)) spOpen(id);
        else { var q = document.getElementById('spQ'); if (q && SP.loaded) { q.value = id; spRender(); } }
      });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function(){ setTimeout(go, 400); });
    else setTimeout(go, 400);
  })();
})();
</script>

<!-- ===== ПАРАЛЛЕЛЬНЫЙ ИМПОРТ ===== -->
<div id="section-pi" class="app-section">
  <div class="section-topbar">
    <button class="back-btn" onclick="showHub()">&#8592; Назад</button>
    <span class="section-topbar-title">Параллельный импорт</span>
  </div>
  <div class="brand-bar">
    <div class="brand-bar-logo">
      <img src="/eco/logo1.png" alt="ИТК">
    </div>
    <div class="brand-bar-text">
      <div class="brand-bar-title">ИТК — Параллельный импорт</div>
      <div class="brand-bar-sub">Приказ Минпромторга №2701 от 21.07.2023 (ред. 2025–2026)</div>
    </div>
  </div>

  <script type="application/json" id="pi-data">[{"brand": "ACURA", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили"}, {"brand": "AUDI", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили, запчасти"}, {"brand": "BMW", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили, запчасти, мотоциклы"}, {"brand": "CADILLAC", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили"}, {"brand": "CHEVROLET", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили"}, {"brand": "CITROEN", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили"}, {"brand": "DACIA", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили"}, {"brand": "FERRARI", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили"}, {"brand": "FORD", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые и грузовые автомобили"}, {"brand": "GENESIS", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили"}, {"brand": "HONDA", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили, мотоциклы"}, {"brand": "HYUNDAI", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили, ряд позиций исключён с 2025"}, {"brand": "INFINITI", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили"}, {"brand": "ISUZU", "cat": "Автомобили и запчасти", "status": "yes", "note": "Грузовые автомобили"}, {"brand": "JAGUAR", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили"}, {"brand": "KIA", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили, добавлен 11.09.2024"}, {"brand": "LAND ROVER", "cat": "Автомобили и запчасти", "status": "yes", "note": "Внедорожники"}, {"brand": "LEXUS", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили"}, {"brand": "MAZDA", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили"}, {"brand": "MERCEDES-BENZ", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые и грузовые автомобили"}, {"brand": "NISSAN", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили"}, {"brand": "OPEL", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили"}, {"brand": "PEUGEOT", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили"}, {"brand": "PORSCHE", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили"}, {"brand": "RENAULT", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили"}, {"brand": "SUBARU", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили"}, {"brand": "TESLA", "cat": "Автомобили и запчасти", "status": "yes", "note": "Электромобили"}, {"brand": "TOYOTA", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые автомобили"}, {"brand": "VOLKSWAGEN", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые и грузовые автомобили"}, {"brand": "VOLVO", "cat": "Автомобили и запчасти", "status": "yes", "note": "Легковые и грузовые автомобили"}, {"brand": "BRIDGESTONE", "cat": "Шины и колёса", "status": "yes", "note": "Автомобильные шины"}, {"brand": "GOODYEAR", "cat": "Шины и колёса", "status": "yes", "note": "Автомобильные шины"}, {"brand": "MICHELIN", "cat": "Шины и колёса", "status": "yes", "note": "Автомобильные шины"}, {"brand": "MITES", "cat": "Шины и колёса", "status": "yes", "note": "Велосипедные шины"}, {"brand": "CONTINENTAL", "cat": "Шины и колёса", "status": "yes", "note": "Автомобильные шины"}, {"brand": "PIRELLI", "cat": "Шины и колёса", "status": "yes", "note": "Автомобильные шины"}, {"brand": "APPLE", "cat": "Электроника", "status": "yes", "note": "Смартфоны, планшеты, компьютеры Mac"}, {"brand": "LG", "cat": "Электроника", "status": "yes", "note": "Телевизоры, бытовая техника"}, {"brand": "MSI", "cat": "Электроника", "status": "yes", "note": "Компьютеры, видеокарты"}, {"brand": "DELL", "cat": "Электроника", "status": "yes", "note": "Компьютеры и ноутбуки"}, {"brand": "GOPRO", "cat": "Электроника", "status": "yes", "note": "Экшн-камеры"}, {"brand": "DYSON", "cat": "Электроника", "status": "yes", "note": "Пылесосы, фены, вентиляторы"}, {"brand": "PHILIPS", "cat": "Электроника", "status": "partial", "note": "Часть позиций исключена (электробритвы, машинки для стрижки)"}, {"brand": "PANASONIC", "cat": "Электроника", "status": "partial", "note": "Часть позиций исключена (миксеры, измельчители, соковыжималки)"}, {"brand": "BOSCH", "cat": "Электроника", "status": "partial", "note": "Ряд позиций исключён с 2025 г."}, {"brand": "SIEMENS", "cat": "Электроника", "status": "yes", "note": "Промышленное и бытовое оборудование"}, {"brand": "ELECTROLUX", "cat": "Электроника", "status": "yes", "note": "Бытовая техника"}, {"brand": "HP", "cat": "Электроника", "status": "no", "note": "Исключён с 2025 г. Требуется разрешение правообладателя"}, {"brand": "HEWLETT PACKARD", "cat": "Электроника", "status": "no", "note": "Исключён с 2025 г. Требуется разрешение правообладателя"}, {"brand": "FUJITSU", "cat": "Электроника", "status": "no", "note": "Исключён с 2025 г. Требуется разрешение правообладателя"}, {"brand": "RICOH", "cat": "Электроника", "status": "no", "note": "Исключён. Требуется разрешение правообладателя"}, {"brand": "ACER", "cat": "Электроника", "status": "no", "note": "Исключён с мая 2026 г. (Приказ №4769)"}, {"brand": "ASUS", "cat": "Электроника", "status": "no", "note": "Исключён с мая 2026 г. (Приказ №4769)"}, {"brand": "CISCO", "cat": "Электроника", "status": "no", "note": "Исключён с мая 2026 г. (Приказ №4769)"}, {"brand": "INTEL", "cat": "Электроника", "status": "no", "note": "Исключён с мая 2026 г. (Приказ №4769)"}, {"brand": "SAMSUNG", "cat": "Электроника", "status": "no", "note": "Исключён с мая 2026 г. (Приказ №4769)"}, {"brand": "TOSHIBA", "cat": "Электроника", "status": "no", "note": "Исключён с мая 2026 г. (Приказ №4769)"}, {"brand": "IBM", "cat": "Электроника", "status": "no", "note": "Исключён с мая 2026 г. (Приказ №4769)"}, {"brand": "KINGSTON", "cat": "Электроника", "status": "no", "note": "Исключён с мая 2026 г. (Приказ №4769)"}, {"brand": "HITACHI", "cat": "Электроника", "status": "no", "note": "Исключён с мая 2026 г. (Приказ №4769)"}, {"brand": "BRAUN", "cat": "Электроника", "status": "no", "note": "Исключён. Требуется разрешение правообладателя"}, {"brand": "CALVIN KLEIN", "cat": "Парфюмерия и косметика", "status": "yes", "note": "Добавлен в 2025 г."}, {"brand": "CHANEL", "cat": "Парфюмерия и косметика", "status": "yes", "note": "Парфюмерия, косметика"}, {"brand": "ESTEE LAUDER", "cat": "Парфюмерия и косметика", "status": "yes", "note": "Парфюмерия, косметика"}, {"brand": "GUCCI", "cat": "Парфюмерия и косметика", "status": "yes", "note": "Парфюмерия"}, {"brand": "HUGO BOSS", "cat": "Парфюмерия и косметика", "status": "yes", "note": "Парфюмерия"}, {"brand": "TOM FORD", "cat": "Парфюмерия и косметика", "status": "yes", "note": "Парфюмерия, косметика"}, {"brand": "YVES SAINT LAURENT", "cat": "Парфюмерия и косметика", "status": "yes", "note": "Парфюмерия, косметика"}, {"brand": "YSL", "cat": "Парфюмерия и косметика", "status": "yes", "note": "Парфюмерия, косметика (Yves Saint Laurent)"}, {"brand": "DIOR", "cat": "Парфюмерия и косметика", "status": "yes", "note": "Парфюмерия, косметика"}, {"brand": "GIVENCHY", "cat": "Парфюмерия и косметика", "status": "yes", "note": "Парфюмерия"}, {"brand": "LANCOME", "cat": "Парфюмерия и косметика", "status": "yes", "note": "Косметика"}, {"brand": "KENZO", "cat": "Парфюмерия и косметика", "status": "yes", "note": "Парфюмерия"}, {"brand": "LA ROCHE-POSAY", "cat": "Парфюмерия и косметика", "status": "yes", "note": "Дерматологическая косметика"}, {"brand": "VERSACE", "cat": "Парфюмерия и косметика", "status": "yes", "note": "Парфюмерия"}, {"brand": "BURBERRY", "cat": "Парфюмерия и косметика", "status": "yes", "note": "Парфюмерия"}, {"brand": "PACO RABANNE", "cat": "Парфюмерия и косметика", "status": "yes", "note": "Парфюмерия"}, {"brand": "ARMANI", "cat": "Парфюмерия и косметика", "status": "yes", "note": "Парфюмерия"}, {"brand": "ADIDAS", "cat": "Одежда и обувь", "status": "yes", "note": "Добавлен в 2025 г."}, {"brand": "PRADA", "cat": "Одежда и обувь", "status": "yes", "note": "Добавлен в 2025 г."}, {"brand": "ZARA", "cat": "Одежда и обувь", "status": "yes", "note": "Добавлен в 2025 г."}, {"brand": "NIKE", "cat": "Одежда и обувь", "status": "yes", "note": "Одежда, обувь, аксессуары"}, {"brand": "NEW BALANCE", "cat": "Одежда и обувь", "status": "yes", "note": "Обувь, одежда"}, {"brand": "LACOSTE", "cat": "Одежда и обувь", "status": "no", "note": "Исключён. Требуется разрешение правообладателя"}, {"brand": "HUGO", "cat": "Одежда и обувь", "status": "no", "note": "Исключён. Требуется разрешение правообладателя"}, {"brand": "LEE COOPER", "cat": "Одежда и обувь", "status": "no", "note": "Исключён. Требуется разрешение правообладателя"}, {"brand": "MANGO", "cat": "Одежда и обувь", "status": "no", "note": "Исключён. Требуется разрешение правообладателя"}, {"brand": "PIERRE CARDIN", "cat": "Одежда и обувь", "status": "no", "note": "Исключён. Требуется разрешение правообладателя"}, {"brand": "CASTROL", "cat": "Масла и смазки", "status": "yes", "note": "Моторные масла, смазочные материалы"}, {"brand": "MOBIL", "cat": "Масла и смазки", "status": "yes", "note": "Моторные масла"}, {"brand": "SHELL", "cat": "Масла и смазки", "status": "yes", "note": "Моторные масла, смазки"}, {"brand": "TOTAL ENERGIES", "cat": "Масла и смазки", "status": "yes", "note": "Моторные масла, смазки"}, {"brand": "TOTAL", "cat": "Масла и смазки", "status": "yes", "note": "Моторные масла, смазки"}, {"brand": "LIQUI MOLY", "cat": "Масла и смазки", "status": "yes", "note": "Моторные масла, присадки"}, {"brand": "MOTUL", "cat": "Масла и смазки", "status": "no", "note": "Исключён с 2025 г. Требуется разрешение правообладателя"}, {"brand": "XBOX", "cat": "Игровые приставки", "status": "yes", "note": "Игровые консоли Microsoft"}, {"brand": "MICROSOFT", "cat": "Игровые приставки", "status": "yes", "note": "Xbox и аксессуары"}, {"brand": "PLAYSTATION", "cat": "Игровые приставки", "status": "yes", "note": "Игровые консоли Sony"}, {"brand": "SONY", "cat": "Игровые приставки", "status": "yes", "note": "PlayStation и аксессуары"}, {"brand": "NINTENDO", "cat": "Игровые приставки", "status": "yes", "note": "Игровые консоли"}, {"brand": "DEWALT", "cat": "Инструменты и оборудование", "status": "yes", "note": "Электроинструменты, горнодобывающее оборудование"}, {"brand": "HUSQVARNA", "cat": "Инструменты и оборудование", "status": "yes", "note": "Бензоинструменты, садовая техника"}, {"brand": "EPIROC", "cat": "Инструменты и оборудование", "status": "yes", "note": "Горнодобывающее оборудование"}, {"brand": "SCHNEIDER ELECTRIC", "cat": "Инструменты и оборудование", "status": "no", "note": "Исключён с 2025 г. Требуется разрешение"}, {"brand": "PARKER", "cat": "Канцелярия", "status": "yes", "note": "Ручки, чернила"}, {"brand": "WATERMAN", "cat": "Канцелярия", "status": "yes", "note": "Ручки, чернила"}, {"brand": "O.B.", "cat": "Фармацевтика и гигиена", "status": "yes", "note": "Средства гигиены"}, {"brand": "CAREFREE", "cat": "Фармацевтика и гигиена", "status": "yes", "note": "Средства гигиены"}, {"brand": "ORAL-B", "cat": "Фармацевтика и гигиена", "status": "no", "note": "Исключён. Требуется разрешение правообладателя"}, {"brand": "JACK DANIEL'S", "cat": "Алкогольные напитки", "status": "yes", "note": "Виски"}, {"brand": "JOHNNIE WALKER", "cat": "Алкогольные напитки", "status": "yes", "note": "Виски"}, {"brand": "HENNESSY", "cat": "Алкогольные напитки", "status": "yes", "note": "Коньяк"}, {"brand": "BACARDI", "cat": "Алкогольные напитки", "status": "yes", "note": "Ром"}, {"brand": "ABSOLUT", "cat": "Алкогольные напитки", "status": "yes", "note": "Водка"}, {"brand": "BAILEYS", "cat": "Алкогольные напитки", "status": "yes", "note": "Ликёр"}]</script>

  <div class="container">
    <div class="pi-disclaimer">
      Перечень товаров для параллельного импорта утверждён <b>Приказом Минпромторга РФ №2701 от 21.07.2023</b>
      и регулярно обновляется. База актуальна на <b>июнь 2026 г.</b>
      Перед ввозом партии рекомендуется проверять действующую редакцию приказа.
    </div>

    <div class="search-panel">
      <span class="search-label">Название бренда</span>
      <input type="text" id="pi-search" class="search-input" placeholder="Например: Apple, BMW, Chanel…" autocomplete="off" autocorrect="off" spellcheck="false">
      <p class="search-hint">Введите название бренда — система проверит, разрешён ли параллельный импорт в Россию.</p>
    </div>

    <div id="pi-results-area">
      <div id="pi-start-screen" class="pi-start">
        <div class="pi-start-icon">🌐</div>
        <h2 class="pi-start-title">Параллельный импорт</h2>
        <p class="pi-start-sub">Введите название бренда выше — система покажет, можно ли ввозить товары этой марки в Россию без разрешения правообладателя.</p>
        <div class="pi-stats-row">
          <div class="pi-stat"><span class="pi-stat-num pi-yes-color" id="pi-count-yes">—</span><span class="pi-stat-lbl">разрешено</span></div>
          <div class="pi-stat"><span class="pi-stat-num pi-no-color" id="pi-count-no">—</span><span class="pi-stat-lbl">исключено</span></div>
          <div class="pi-stat"><span class="pi-stat-num pi-partial-color" id="pi-count-partial">—</span><span class="pi-stat-lbl">частично</span></div>
        </div>
      </div>
      <div id="pi-results" class="hidden"></div>
      <div id="pi-empty" class="hidden pi-empty-msg">
        <div style="font-size:2rem;margin-bottom:10px">🔍</div>
        <b>Бренд не найден в базе</b><br>
        <span>Возможно, для этого товарного знака параллельный импорт не предусмотрен перечнем, или бренд указан иначе. Проверьте актуальную редакцию Приказа №2701.</span>
      </div>
    </div>

    <!-- Проверка в ТРОИС -->
    <div class="troix-block">
      <div class="troix-head">
        <span class="troix-head-icon">🛃</span>
        <span class="troix-title">Проверка бренда в ТРОИС</span>
      </div>
      <p class="troix-sub">Если бренд не разрешён к параллельному импорту, проверьте в Таможенном реестре объектов интеллектуальной собственности, нужно ли согласие правообладателя на ввоз.</p>

      <div class="troix-brand-row">
        <span class="troix-brand-label">Бренд:</span>
        <span class="troix-brand-val empty" id="troix-brand">введите бренд в поиске выше</span>
        <button class="troix-copy" id="troix-copy" type="button">Копировать</button>
      </div>

      <div class="troix-regs">
        <a class="troix-reg" target="_blank" rel="noopener" href="https://www.alta.ru/rois/">
          <span class="troix-dot" style="background:var(--green)"></span>
          <span class="troix-reg-text">
            <span class="troix-reg-name">Альта-Софт · ТРОИС</span>
            <span class="troix-reg-desc">Удобный поиск, контакты правообладателя</span>
          </span>
          <span class="troix-reg-arr">↗</span>
        </a>
        <a class="troix-reg" target="_blank" rel="noopener" href="https://customs.gov.ru/registers/objects-of-intellectual-property">
          <span class="troix-dot" style="background:var(--navy)"></span>
          <span class="troix-reg-text">
            <span class="troix-reg-name">ФТС · реестр ОИС (ТРОИС)</span>
            <span class="troix-reg-desc">Официальный источник, самые актуальные данные</span>
          </span>
          <span class="troix-reg-arr">↗</span>
        </a>
        <a class="troix-reg" target="_blank" rel="noopener" href="https://searchplatform.rospatent.gov.ru/trademarks">
          <span class="troix-dot" style="background:#9aa6bf"></span>
          <span class="troix-reg-text">
            <span class="troix-reg-name">Роспатент · поиск товарных знаков</span>
            <span class="troix-reg-desc">Кто правообладатель (отдельная база от ТРОИС)</span>
          </span>
          <span class="troix-reg-arr">↗</span>
        </a>
      </div>

      <div class="troix-note">
        <b>Есть в ТРОИС</b> — ввоз только с разрешения правообладателя (контакты в карточке знака).<br>
        <b>Нет в ТРОИС</b> — таможня автоматически не блокирует, но риск остаётся: приостановка выпуска до 10 дней, претензии по ст. 14.10 КоАП / 1515 ГК.<br>
        Прямой ввоз от своего китайского поставщика согласия не требует.
      </div>
    </div>

    <!-- Товарный знак в РФ -->
    <div class="troix-block">
      <div class="troix-head">
        <span class="troix-head-icon">®️</span>
        <span class="troix-title">Товарный знак в РФ</span>
      </div>
      <p class="troix-sub">Проверьте, зарегистрирован ли знак в России: правообладатель, № свидетельства, статус (действует / прекращён) и классы МКТУ. Полезно, даже если бренда нет в ТРОИС.</p>

      <div class="troix-brand-row">
        <span class="troix-brand-label">Бренд:</span>
        <span class="troix-brand-val empty" id="tzrf-brand">введите бренд в поиске выше</span>
        <button class="troix-copy" id="tzrf-copy" type="button">Копировать</button>
      </div>

      <div class="troix-regs">
        <a class="troix-reg" target="_blank" rel="noopener" href="https://searchplatform.rospatent.gov.ru/trademarks">
          <span class="troix-dot" style="background:var(--navy)"></span>
          <span class="troix-reg-text">
            <span class="troix-reg-name">Роспатент · Поисковая платформа</span>
            <span class="troix-reg-desc">Поиск по названию знака — официальный сервис</span>
          </span>
          <span class="troix-reg-arr">↗</span>
        </a>
        <a class="troix-reg" target="_blank" rel="noopener" href="https://fips.ru/registers-web/action?acName=clickRegister&regName=RUTM">
          <span class="troix-dot" style="background:var(--green)"></span>
          <span class="troix-reg-text">
            <span class="troix-reg-name">ФИПС · Открытые реестры (ТЗ)</span>
            <span class="troix-reg-desc">Поиск по № свидетельства → статус и правообладатель</span>
          </span>
          <span class="troix-reg-arr">↗</span>
        </a>
        <a class="troix-reg" target="_blank" rel="noopener" href="https://onlinepatent.ru/trademarks/">
          <span class="troix-dot" style="background:#9aa6bf"></span>
          <span class="troix-reg-text">
            <span class="troix-reg-name">Онлайн Патент · бесплатный поиск</span>
            <span class="troix-reg-desc">Удобный поиск по названию (словесные знаки)</span>
          </span>
          <span class="troix-reg-arr">↗</span>
        </a>
      </div>

      <div class="troix-note">
        <b>Действует</b> + правообладатель в РФ → знак под охраной, при импорте возможны требования согласия или претензии.<br>
        <b>Международная регистрация с указанием РФ</b> охраняется наравне с национальной.<br>
        ФИПС ищет по <b>номеру</b> свидетельства; по названию удобнее Роспатент или Онлайн Патент. Бесплатный поиск — только по словесным знакам.
      </div>
    </div>
  </div>

  <script>
(function(){
  const RAW = JSON.parse(document.getElementById('pi-data').textContent);

  // Считаю статистику
  var countYes = RAW.filter(x=>x.status==='yes').length;
  var countNo  = RAW.filter(x=>x.status==='no').length;
  var countPart= RAW.filter(x=>x.status==='partial').length;
  document.getElementById('pi-count-yes').textContent = countYes;
  document.getElementById('pi-count-no').textContent  = countNo;
  document.getElementById('pi-count-partial').textContent = countPart;

  const CATS = {
    'yes':     {icon:'✅', label:'Параллельный импорт разрешён', cls:'pi-card-yes'},
    'no':      {icon:'🚫', label:'Параллельный импорт НЕ разрешён', cls:'pi-card-no'},
    'partial': {icon:'⚠️', label:'Разрешён частично', cls:'pi-card-partial'},
  };

  function normalize(s){ return s.toLowerCase().replace(/[-–—]/g,' ').trim(); }

  function escHtml(s){
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function highlight(text, query){
    if(!query) return escHtml(text);
    var re = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + ')', 'gi');
    return escHtml(text).replace(re,'<mark>$1</mark>');
  }

  function search(q){
    if(!q || q.length < 1) return null;
    var nq = normalize(q);
    return RAW.filter(function(item){
      return normalize(item.brand).includes(nq) || normalize(item.cat).includes(nq);
    });
  }

  var debounce;
  document.getElementById('pi-search').addEventListener('input', function(e){
    clearTimeout(debounce);
    debounce = setTimeout(function(){ render(e.target.value.trim()); }, 150);
  });

  function render(q){
    var start   = document.getElementById('pi-start-screen');
    var results = document.getElementById('pi-results');
    var empty   = document.getElementById('pi-empty');

    if(!q){
      start.classList.remove('hidden');
      results.classList.add('hidden');
      empty.classList.add('hidden');
      return;
    }

    var found = search(q);
    start.classList.add('hidden');

    if(!found || found.length === 0){
      results.classList.add('hidden');
      empty.classList.remove('hidden');
      return;
    }

    empty.classList.add('hidden');
    results.classList.remove('hidden');

    var html = '<div class="pi-result-count">Найдено: <b>' + found.length + '</b> результат(а/ов)</div>';
    found.forEach(function(item){
      var c = CATS[item.status];
      html += '<div class="pi-card ' + c.cls + '">';
      html += '<div class="pi-card-header">';
      html += '<span class="pi-card-icon">' + c.icon + '</span>';
      html += '<span class="pi-card-brand">' + highlight(item.brand, q) + '</span>';
      html += '</div>';
      html += '<div class="pi-card-status">' + c.label + '</div>';
      html += '<div class="pi-card-cat">' + escHtml(item.cat) + '</div>';
      html += '<div class="pi-card-note">' + escHtml(item.note) + '</div>';
      html += '</div>';
    });

    results.innerHTML = html;
  }

  // ===== Синхронизация бренда с блоками ТРОИС и ТЗ РФ =====
  var brandEls = [document.getElementById('troix-brand'), document.getElementById('tzrf-brand')];
  var currentBrand = '';

  function syncTroix(q){
    currentBrand = (q || '').trim();
    brandEls.forEach(function(el){
      if(!el) return;
      if(currentBrand){
        el.textContent = currentBrand;
        el.classList.remove('empty');
      } else {
        el.textContent = 'введите бренд в поиске выше';
        el.classList.add('empty');
      }
    });
    [document.getElementById('troix-copy'), document.getElementById('tzrf-copy')].forEach(function(b){
      if(b){ b.classList.remove('copied'); b.textContent = 'Копировать'; }
    });
  }

  document.getElementById('pi-search').addEventListener('input', function(e){
    syncTroix(e.target.value);
  });

  function wireCopy(btn){
    if(!btn) return;
    btn.addEventListener('click', function(){
      if(!currentBrand){
        document.getElementById('pi-search').focus();
        return;
      }
      var done = function(){
        btn.classList.add('copied');
        btn.textContent = 'Скопировано';
        setTimeout(function(){
          btn.classList.remove('copied');
          btn.textContent = 'Копировать';
        }, 1500);
      };
      if(navigator.clipboard && navigator.clipboard.writeText){
        navigator.clipboard.writeText(currentBrand).then(done, function(){ fallbackCopy(currentBrand, done); });
      } else {
        fallbackCopy(currentBrand, done);
      }
    });
  }
  wireCopy(document.getElementById('troix-copy'));
  wireCopy(document.getElementById('tzrf-copy'));

  function fallbackCopy(text, done){
    try {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      done();
    } catch(e){ /* тихо */ }
  }
})();
  </script>
</div>

<script>
(function() {
  /* ============================================================
     КТО ПРИШЁЛ (4.0.0, 22.08.2026).
     Приложение передаёт пропуск сотрудника в адресе: /eco/?t=...
     Спрашиваем сервер, кто это и что ему можно, и запоминаем ответ.
     Пропуск сразу убираем из адресной строки, чтобы он не остался
     в истории браузера и не попал в чужие руки.
     ЭТО ТОЛЬКО ДЛЯ ПОКАЗА МЕНЮ. Настоящая проверка прав — на сервере,
     в каждой команде CRM.
     ============================================================ */
  var ECO_ME = null;                  // {name, role, can:{...}}
  var ECO_TOKEN = "";
  try{
    var _m = /[?&]t=([^&]+)/.exec(location.search||"");
    if (_m) {
      ECO_TOKEN = decodeURIComponent(_m[1]);
      try{ sessionStorage.setItem("eco_t", ECO_TOKEN); }catch(e){}
      try{ history.replaceState(null, "", location.pathname); }catch(e){}
    } else {
      try{ ECO_TOKEN = sessionStorage.getItem("eco_t") || ""; }catch(e){}
    }
  }catch(e){}

  function ecoCan(what){
    /* базу поставщиков видит любой вошедший сотрудник (4.8.0) */
    if (what === "suppliers") return !!ECO_ME;
    return !!(ECO_ME && ECO_ME.can && ECO_ME.can[what]);
  }
  /* пропуск нужен разделу «Поставщики», он живёт в другом блоке скрипта */
  window.ECO_TOKEN_GET = function(){ return ECO_TOKEN; };

  function ecoWhoAmI(){
    if (!ECO_TOKEN) { ecoApplyRole(); return; }
    try{
      fetch("/api/api.php?action=eco_who&t=" + encodeURIComponent(ECO_TOKEN))
        .then(function(r){ return r.json(); })
        .then(function(d){ if (d && d.ok) ECO_ME = d; ecoApplyRole(); })
        .catch(function(){ ecoApplyRole(); });
    }catch(e){ ecoApplyRole(); }
  }

  /* Показываем имя вошедшего и прячем разделы, которые ему не положены.
     Разделы CRM появятся позже — пока функция готовит для них место. */
  function ecoApplyRole(){
    try{
      var box = document.getElementById("ecoWho");
      if (box) {
        box.textContent = ECO_ME
          ? (ECO_ME.name || "Сотрудник") + " · " + ecoRoleLabel(ECO_ME.role)
          : "Вход не выполнен · доступны только расчёты";
        box.style.display = "block";
      }
      var els = document.querySelectorAll("[data-eco-need]");
      for (var i=0;i<els.length;i++){
        els[i].style.display = ecoCan(els[i].getAttribute("data-eco-need")) ? "" : "none";
      }
    }catch(e){}
  }

  function ecoRoleLabel(r){
    return {boss:"Босс",manager:"Менеджер",moderator:"Модератор",accountant:"Бухгалтерия",
            chief_accountant:"Гл. бухгалтер",supervisor:"Супервайзер",wh_manager:"Управляющий складом",
            wh_worker:"Сотрудник склада",wh_keeper:"Кладовщик"}[r] || "Сотрудник";
  }

  try{ ecoWhoAmI(); }catch(e){}

  /* ============================================================
     КЛИЕНТСКАЯ БАЗА (4.1.0, 22.08.2026)
     Данные лежат на сервере, а не в памяти браузера: бухгалтер вбивает
     у себя, босс видит то же самое. Каждый запрос идёт с пропуском,
     сервер сам решает, пускать или нет.
     ============================================================ */
  var CRM = { rows: [], client: null, contacts: [], kp: [] };

  function crmEsc(x){
    return String(x==null?"":x).replace(/[&<>"]/g, function(c){
      return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];
    });
  }

  /* Подсветка найденного куска. Экранируем ДО подсветки, иначе разметка
     сломается на первом же угловом скобке в названии. */
  function crmMark(text, q){
    var t = crmEsc(text || "");
    q = (q||"").trim();
    if (!q) return t;
    var qq = crmEsc(q).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    try { return t.replace(new RegExp(qq, "ig"), function(m){
      return '<span style="background:#fff3bf;border-radius:3px">' + m + '</span>';
    }); } catch(e){ return t; }
  }

  function crmGet(action, params){
    var u = "/api/api.php?action=" + action + "&t=" + encodeURIComponent(ECO_TOKEN);
    if (params) for (var k in params) u += "&" + k + "=" + encodeURIComponent(params[k]);
    return fetch(u).then(function(r){ return r.json(); });
  }
  function crmPost(action, data){
    data = data || {}; data.t = ECO_TOKEN;
    return fetch("/api/api.php?action=" + action, {
      method: "POST",
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify(data)
    }).then(function(r){ return r.json(); });
  }

  var _crmT = null;
  function crmSearchLater(){
    clearTimeout(_crmT);
    _crmT = setTimeout(crmLoadClients, 300);
  }

  function crmLoadClients(){
    var box = document.getElementById("crmRows");
    var q = (document.getElementById("crmQ")||{}).value || "";
    document.getElementById("crmList").style.display = "block";
    document.getElementById("crmCard").style.display = "none";
    if (box) box.innerHTML = "Загружаю…";
    crmGet("crm_clients", {q:q}).then(function(d){
      if (!d || !d.ok) { box.innerHTML = "Ошибка: " + crmEsc((d&&d.error)||"не удалось загрузить"); return; }
      CRM.rows = d.rows || [];
      if (!CRM.rows.length) { box.innerHTML = q ? "Ничего не найдено." : "Пока ни одного клиента. Нажмите «+ Новый»."; return; }
      box.innerHTML = CRM.rows.map(function(r){
        return '<div onclick="crmOpen(\'' + r.id + '\')" style="padding:11px 12px;margin-bottom:7px;background:#fff;border:1px solid #e6e9f0;border-radius:9px;cursor:pointer">'
          + '<div style="font-weight:700;color:#112C93">' + crmMark(r.alias || r.company, q) + '</div>'
          + ((r.alias && r.company) ? '<div style="font-size:.78rem;color:#3d4a63">' + crmMark(r.company, q) + '</div>' : '')
          + '<div style="font-size:.8rem;color:#7b8496;margin-top:2px">'
          + (r.inn ? "ИНН " + crmMark(r.inn, q) : "без ИНН")
          + " · контактных лиц: " + (r.contacts||0) + '</div></div>';
      }).join("");
    }).catch(function(){ box.innerHTML = "Нет связи с сервером."; });
  }

  function crmNewClient(){ crmShowCard(null, []); }

  /* Возврат к списку своей кнопкой. Шаг истории, положенный при открытии
     карточки, снимаем сами — иначе он останется висеть и следующее нажатие
     «Назад» не уйдёт выше. */
  function crmBackToList(){
    var c = document.getElementById("crmCard");
    if (!c || c.style.display !== "block") return;
    c.style.display = "none";
    if (window.ecoHistBack) window.ecoHistBack();
    crmLoadClients();
  }

  function crmOpen(id){
    crmGet("crm_client", {id:id}).then(function(d){
      if (!d || !d.ok) { alert((d&&d.error)||"Не удалось открыть"); return; }
      CRM.kp = d.kp || [];
      crmShowCard(d.client, d.contacts||[]);
    });
  }

  function crmField(label, id, val, ph){
    return '<label style="display:block;font-size:.75rem;color:#7b8496;margin:8px 0 3px">' + label + '</label>'
      + '<input id="' + id + '" value="' + crmEsc(val||"") + '" placeholder="' + crmEsc(ph||"") + '"'
      + ' style="width:100%;font-size:16px;padding:9px;border:1px solid #dde;border-radius:8px;box-sizing:border-box">';
  }

  function crmShowCard(c, contacts){
    CRM.client = c; CRM.contacts = contacts || [];
    c = c || {};
    document.getElementById("crmList").style.display = "none";
    var box = document.getElementById("crmCard");
    /* Кладём шаг истории ТОЛЬКО при первом открытии карточки. Сохранение
       перерисовывает её же — второй шаг сделал бы два нажатия «Назад». */
    if (box.style.display !== "block") {
      try{ history.pushState({crmCard:1}, "", location.hash || ""); }catch(e){}
    }
    box.style.display = "block";
    box.innerHTML =
      '<button onclick="crmBackToList()" style="border:1px solid #d5dae3;background:#fff;color:#112C93;border-radius:9px;padding:7px 13px;font:600 .85rem/1 sans-serif;cursor:pointer;margin-bottom:12px">&#8249; К списку</button>'
      + '<div style="background:#fff;border:1px solid #e6e9f0;border-radius:11px;padding:14px">'
      + '<div style="font:700 1rem/1.3 sans-serif;color:#112C93;margin-bottom:4px">' + (c.id ? "Карточка клиента" : "Новый клиент") + '</div>'
      + crmField("Псевдоним", "cl_alias", c.alias, "магазин Яблоко")
      + '<div style="font-size:.73rem;color:#7b8496;margin-top:2px">Как вы называете клиента между собой. Хватает одного псевдонима — реквизиты можно добить позже.</div>'
      + '<label style="display:block;font-size:.75rem;color:#7b8496;margin:10px 0 3px">ИНН</label>'
      + '<div style="display:flex;gap:7px">'
        + '<input id="cl_inn" value="' + crmEsc(c.inn||"") + '" placeholder="10 или 12 цифр" inputmode="numeric"'
        + ' style="flex:1;font-size:16px;padding:9px;border:1px solid #dde;border-radius:8px;box-sizing:border-box">'
        + '<button onclick="crmPullInn()" style="background:#0B6603;color:#fff;border:0;border-radius:8px;padding:0 14px;font:600 .85rem/1 sans-serif;cursor:pointer;white-space:nowrap">Найти</button>'
      + '</div>'
      + '<div id="cl_innInfo" style="font-size:.78rem;color:#7b8496;margin-top:4px"></div>'
      + crmField("Компания", "cl_company", c.company, "ООО «Ромашка»")
      + crmField("КПП", "cl_kpp", c.kpp)
      + crmField("ОГРН", "cl_ogrn", c.ogrn)
      + crmField("Адрес", "cl_addr", c.addr)
      + crmField("Банк", "cl_bank", c.bank)
      + crmField("БИК", "cl_bik", c.bik)
      + crmField("Расчётный счёт", "cl_acct", c.acct)
      + crmField("Корр. счёт", "cl_corr", c.corr)
      + crmField("Сайт", "cl_site", c.site)
      + crmField("Заметка", "cl_note", c.note)
      + '<button onclick="crmSaveClient()" style="width:100%;margin-top:12px;background:#112C93;color:#fff;border:0;border-radius:9px;padding:12px;font:700 .95rem/1 sans-serif;cursor:pointer">Сохранить</button>'
      + '</div>'
      + (c.id ? crmContactsHtml() + crmKpHtml() : '<div style="margin-top:12px;font-size:.83rem;color:#7b8496">Контактных лиц можно будет добавить после сохранения.</div>');
  }

  /* Коммерческие предложения, выставленные этому клиенту. */
  function crmKpHtml(){
    var h = '<div style="margin-top:14px;background:#fff;border:1px solid #e6e9f0;border-radius:11px;padding:14px">'
      + '<div style="font:700 .95rem/1.3 sans-serif;color:#112C93;margin-bottom:8px">Коммерческие предложения</div>';
    if (!CRM.kp || !CRM.kp.length) {
      h += '<div style="font-size:.83rem;color:#7b8496">Пока ни одного. Сделайте полный расчёт, откройте КП и нажмите «Выбрать клиента».</div>';
    } else {
      h += CRM.kp.map(function(k){
        return '<div style="padding:9px 0;border-top:1px solid #f0f2f7;display:flex;justify-content:space-between;gap:10px">'
          + '<div><div style="font-weight:600">КП № ' + crmEsc(k.no) + '</div>'
          + '<div style="font-size:.79rem;color:#7b8496">от ' + crmEsc(k.dt) + '</div></div>'
          + '<div style="font-weight:700;color:#112C93;white-space:nowrap">' + Math.round(k.total).toLocaleString("ru-RU") + ' ₽</div>'
          + '</div>';
      }).join("");
    }
    return h + '</div>';
  }

  function crmContactsHtml(){
    var h = '<div style="margin-top:14px;background:#fff;border:1px solid #e6e9f0;border-radius:11px;padding:14px">'
      + '<div style="font:700 .95rem/1.3 sans-serif;color:#112C93;margin-bottom:8px">Контактные лица</div>';
    if (!CRM.contacts.length) h += '<div style="font-size:.83rem;color:#7b8496">Пока никого.</div>';
    else h += CRM.contacts.map(function(k){
      return '<div style="padding:9px 0;border-top:1px solid #f0f2f7">'
        + '<div style="font-weight:600">' + crmEsc(k.name) + (k.post ? ' <span style="font-weight:400;color:#7b8496">· ' + crmEsc(k.post) + '</span>' : '') + '</div>'
        + (k.area ? '<div style="font-size:.8rem;color:#0B6603">Направление: ' + crmEsc(k.area) + '</div>' : '')
        + '<div style="font-size:.8rem;color:#7b8496">' + crmEsc(k.phone||"") + (k.email ? " · " + crmEsc(k.email) : "") + '</div>'
        + '<div style="margin-top:5px"><button onclick="crmEditContact(\'' + k.id + '\')" style="border:1px solid #d5dae3;background:#fff;border-radius:7px;padding:4px 10px;font-size:.78rem;cursor:pointer">Изменить</button> '
        + '<button onclick="crmDelContact(\'' + k.id + '\')" style="border:1px solid #f0d0d0;background:#fff;color:#c0392b;border-radius:7px;padding:4px 10px;font-size:.78rem;cursor:pointer">Удалить</button></div>'
        + '</div>';
    }).join("");
    h += '<button onclick="crmEditContact(\'\')" style="width:100%;margin-top:10px;border:1px solid #112C93;background:#fff;color:#112C93;border-radius:9px;padding:10px;font:600 .9rem/1 sans-serif;cursor:pointer">+ Добавить контактное лицо</button>';
    h += '<div id="crmContactForm"></div></div>';
    return h;
  }

  function crmEditContact(id){
    var k = {};
    for (var i=0;i<CRM.contacts.length;i++) if (CRM.contacts[i].id===id) k = CRM.contacts[i];
    var f = document.getElementById("crmContactForm");
    f.innerHTML = '<div style="margin-top:12px;padding:12px;background:#f6f8ff;border-radius:9px">'
      + '<input type="hidden" id="ct_id" value="' + crmEsc(k.id||"") + '">'
      + crmField("Имя", "ct_name", k.name, "Иван Петров")
      + crmField("Должность", "ct_post", k.post, "менеджер по закупкам")
      + crmField("Направление", "ct_area", k.area, "посуда / мебель")
      + crmField("Телефон", "ct_phone", k.phone)
      + crmField("Почта", "ct_email", k.email)
      + crmField("Комментарий", "ct_note", k.note)
      + '<button onclick="crmSaveContact()" style="width:100%;margin-top:10px;background:#0B6603;color:#fff;border:0;border-radius:9px;padding:10px;font:700 .9rem/1 sans-serif;cursor:pointer">Сохранить лицо</button>'
      + '</div>';
  }

  function crmV(id){ var e=document.getElementById(id); return e ? e.value.trim() : ""; }

  function crmSaveClient(){
    var d = {
      id: (CRM.client && CRM.client.id) || "",
      alias: crmV("cl_alias"),
      company: crmV("cl_company"), inn: crmV("cl_inn"), kpp: crmV("cl_kpp"),
      ogrn: crmV("cl_ogrn"), addr: crmV("cl_addr"), bank: crmV("cl_bank"),
      bik: crmV("cl_bik"), acct: crmV("cl_acct"), corr: crmV("cl_corr"),
      site: crmV("cl_site"), note: crmV("cl_note")
    };
    if (!d.alias && !d.company) { alert("Впишите хотя бы псевдоним"); return; }
    crmPost("crm_client_save", d).then(function(r){
      if (!r || !r.ok) { alert((r&&r.error)||"Не сохранилось"); return; }
      crmOpen(r.id);
    });
  }

  /* Подстановка по ИНН из открытых реестров. Ничего не сохраняем —
     человек смотрит, что подтянулось, и жмёт «Сохранить» сам. */
  function crmPullInn(){
    var inn = crmV("cl_inn").replace(/\D+/g,"");
    var info = document.getElementById("cl_innInfo");
    if (inn.length < 10) { info.textContent = "ИНН должен быть 10 или 12 цифр."; return; }
    info.textContent = "Ищу…";
    crmGet("crm_inn", {inn:inn}).then(function(d){
      if (!d || !d.ok) { info.textContent = (d&&d.error) || "Не найдено."; return; }
      var set = function(id,v){ var e=document.getElementById(id); if(e && v) e.value = v; };
      set("cl_company", d.full || d.company);
      set("cl_kpp",  d.kpp);
      set("cl_ogrn", d.ogrn);
      set("cl_addr", d.addr);
      var t = "Найдено: " + (d.company||"");
      if (d.status) t += " · " + d.status;
      if (d.head)   t += " · руководитель " + d.head;
      info.textContent = t;
      /* Псевдоним не трогаем: он ваш рабочий, а не из реестра. Если пустой —
         подставим короткое название, чтобы в списке было что показать. */
      var a = document.getElementById("cl_alias");
      if (a && !a.value.trim()) a.value = d.company || "";
    }).catch(function(){ info.textContent = "Нет связи с реестром."; });
  }

  function crmSaveContact(){
    var d = {
      id: crmV("ct_id"),
      client_id: (CRM.client && CRM.client.id) || "",
      name: crmV("ct_name"), post: crmV("ct_post"), area: crmV("ct_area"),
      phone: crmV("ct_phone"), email: crmV("ct_email"), note: crmV("ct_note")
    };
    if (!d.name) { alert("Впишите имя"); return; }
    crmPost("crm_contact_save", d).then(function(r){
      if (!r || !r.ok) { alert((r&&r.error)||"Не сохранилось"); return; }
      crmOpen(d.client_id);
    });
  }

  function crmDelContact(id){
    if (!confirm("Удалить контактное лицо?")) return;
    crmPost("crm_contact_del", {id:id}).then(function(){
      crmOpen((CRM.client && CRM.client.id) || "");
    });
  }

  /* ВЫБОР КЛИЕНТА ДЛЯ КП (4.3.0).
     Живёт здесь, а не в окне КП: клиенты приходят с сервера, а окно КП
     о сервере ничего не знает. Выбранный клиент лежит в KP_CLIENT и
     подставляется в шапку предложения. */
  /* ВНИМАНИЕ: этот блок кода и окно КП живут в РАЗНЫХ функциях и обычных
     переменных друг у друга не видят. Поэтому выбранный клиент лежит
     в общей памяти страницы — window.KP_CLIENT. */
  window.KP_CLIENT = window.KP_CLIENT || null;

  function kpPickClient(done){
    var ov = document.createElement("div");
    ov.className = "kp-ov";
    ov.innerHTML = '<div class="kp-box" style="padding:14px;display:flex;flex-direction:column;max-height:82vh">'
      + '<div style="font:700 1rem/1.3 sans-serif;color:#112C93;margin-bottom:10px">Выбрать клиента</div>'
      + '<input id="kpClQ" placeholder="Название или ИНН" autocomplete="off" style="width:100%;font-size:16px;padding:10px;border:1px solid #dde;border-radius:9px;box-sizing:border-box;flex:none">'
      /* Список прокручивается внутри окна: клиентов может быть много,
         а окно должно оставаться в границах экрана. */
      + '<div id="kpClRows" style="margin-top:10px;overflow-y:auto;flex:1;min-height:120px;-webkit-overflow-scrolling:touch;font:400 .9rem/1.4 sans-serif;color:#3d4a63">Загружаю…</div>'
      + '<button id="kpClNone" style="width:100%;margin-top:10px;flex:none;border:1px solid #d5dae3;background:#fff;border-radius:9px;padding:10px;font:600 .88rem/1 sans-serif;cursor:pointer">Без клиента</button>'
      + '</div>';
    document.body.appendChild(ov);
    var close = function(){ if (ov.parentNode) ov.parentNode.removeChild(ov); };
    ov.addEventListener("click", function(e){ if (e.target === ov) close(); });
    document.getElementById("kpClNone").onclick = function(){ window.KP_CLIENT = null; close(); if (done) done(); };

    /* Подсветка найденного куска — чтобы сразу видеть, чем строка совпала:
       названием или ИНН. Экранируем ДО подсветки, иначе разметка сломается. */
    var mark = crmMark;

    var draw = function(){
      var box = document.getElementById("kpClRows");
      var q = ((document.getElementById("kpClQ")||{}).value || "").trim();
      box.innerHTML = "Загружаю…";
      crmGet("crm_clients", {q:q}).then(function(d){
        if (!d || !d.ok) { box.innerHTML = "Ошибка: " + crmEsc((d&&d.error)||""); return; }
        if (!(d.rows||[]).length) { box.innerHTML = q ? "Ничего не найдено." : "В базе пока пусто."; return; }
        box.innerHTML = "";
        d.rows.forEach(function(r){
          var b = document.createElement("div");
          b.style.cssText = "padding:10px 11px;margin-bottom:6px;background:#fff;border:1px solid #e6e9f0;border-radius:9px;cursor:pointer";
          var line2 = [];
          if (r.company && r.company !== r.alias) line2.push(mark(r.company, q));
          if (r.inn) line2.push("ИНН " + mark(r.inn, q));
          b.innerHTML = '<div style="font-weight:700;color:#112C93">' + mark(r.alias || r.company, q) + '</div>'
            + (line2.length ? '<div style="font-size:.78rem;color:#7b8496;margin-top:1px">' + line2.join(" · ") + '</div>' : '');
          b.onclick = function(){
            window.KP_CLIENT = { id:r.id, alias:r.alias, company:r.company, inn:r.inn,
                                 title:(r.alias || r.company || "клиент") };
            close(); if (done) done();
          };
          box.appendChild(b);
        });
      }).catch(function(){ box.innerHTML = "Нет связи с сервером."; });
    };
    var t = null;
    document.getElementById("kpClQ").oninput = function(){ clearTimeout(t); t = setTimeout(draw, 250); };
    draw();
  }

  window.kpPickClient = kpPickClient;
  /* ============================================================
     СДЕЛКИ (4.4.0, 22.08.2026)
     Одна сделка — одна поставка. У каждой статьи расхода две суммы:
     по расчёту и фактическая. Маржу присылает сервер и только боссу —
     у остальных этих полей в ответе просто нет.
     ============================================================ */
  var DL = { tab:"open", rows:[], deal:null, costs:[], margin:false, client:null };

  function dlMoney(v){ return Math.round(+v||0).toLocaleString("ru-RU") + " \u20BD"; }

  function dlSetTab(t){ DL.tab = t; dlLoad(); }

  function dlPaintTabs(){
    var a = document.getElementById("dlTabOpen"), b = document.getElementById("dlTabClosed");
    if (!a || !b) return;
    var on = "background:#112C93;color:#fff", off = "background:#e8ecf5;color:#3d4a63";
    a.style.cssText = "flex:1;border:0;border-radius:9px;padding:9px;font:600 .85rem/1 sans-serif;cursor:pointer;" + (DL.tab==="open"?on:off);
    b.style.cssText = "flex:1;border:0;border-radius:9px;padding:9px;font:600 .85rem/1 sans-serif;cursor:pointer;" + (DL.tab==="closed"?on:off);
  }

  function dlLoad(){
    document.getElementById("dlList").style.display = "block";
    document.getElementById("dlCard").style.display = "none";
    dlPaintTabs();
    var box = document.getElementById("dlRows");
    box.innerHTML = "Загружаю…";
    crmGet("crm_deals", {status:DL.tab}).then(function(d){
      if (!d || !d.ok) { box.innerHTML = "Ошибка: " + crmEsc((d&&d.error)||""); return; }
      DL.rows = d.rows || []; DL.margin = !!d.margin;
      if (!DL.rows.length) { box.innerHTML = DL.tab==="open" ? "Сделок в работе нет." : "Закрытых сделок нет."; return; }
      box.innerHTML = DL.rows.map(function(r){
        var who = r.alias || r.company || "без клиента";
        var mark = (r.kind === "cargo")
          ? '<span style="background:#fdeaea;color:#c0392b;border-radius:5px;padding:1px 6px;font-size:.72rem">КАРГО</span>'
          : '<span style="background:#e8f3e8;color:#0B6603;border-radius:5px;padding:1px 6px;font-size:.72rem">БЕЛАЯ</span>';
        var money = "";
        if (DL.margin) {
          var mf = (+r.margin_fact||0), mp = (+r.margin_plan||0);
          var use = (+r.sold_fact ? mf : mp);
          var lbl = (+r.sold_fact ? "факт" : "расчёт");
          money = '<div style="margin-top:4px;font-weight:700;color:' + (use>=0 ? "#0B6603" : "#c0392b") + '">'
                + (use>=0 ? "прибыль " : "убыток ") + dlMoney(Math.abs(use))
                + ' <span style="font-weight:400;color:#7b8496;font-size:.78rem">(' + lbl + ')</span></div>';
        }
        return '<div onclick="dlOpen(\'' + r.id + '\')" style="padding:11px 12px;margin-bottom:7px;background:#fff;border:1px solid #e6e9f0;border-radius:9px;cursor:pointer">'
          + '<div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">'
          + '<div style="font-weight:700;color:#112C93">' + crmEsc(r.title) + '</div>' + mark + '</div>'
          + '<div style="font-size:.8rem;color:#7b8496;margin-top:2px">' + crmEsc(who) + '</div>'
          + money + '</div>';
      }).join("");
    }).catch(function(){ box.innerHTML = "Нет связи с сервером."; });
  }

  function dlNew(){
    DL.deal = null; DL.costs = []; DL.client = null;
    dlShowCard();
  }

  function dlOpen(id){
    crmGet("crm_deal", {id:id}).then(function(d){
      if (!d || !d.ok) { alert((d&&d.error)||"Не удалось открыть"); return; }
      DL.deal = d.deal; DL.costs = d.costs || []; DL.margin = !!d.margin;
      DL.sums = { plan:d.cost_plan||0, fact:d.cost_fact||0,
                  mplan:d.margin_plan, mfact:d.margin_fact };
      DL.client = d.deal.client_id ? { id:d.deal.client_id, alias:d.deal.alias, company:d.deal.company,
                                       title:(d.deal.alias || d.deal.company || "клиент") } : null;
      dlShowCard();
    });
  }

  function dlBackToList(){
    var c = document.getElementById("dlCard");
    if (!c || c.style.display !== "block") return;
    c.style.display = "none";
    if (window.ecoHistBack) window.ecoHistBack();
    dlLoad();
  }

  function dlNum(label, id, val){
    return '<label style="display:block;font-size:.75rem;color:#7b8496;margin:8px 0 3px">' + label + '</label>'
      + '<input id="' + id + '" value="' + (val ? Math.round(val) : "") + '" inputmode="numeric" placeholder="0"'
      + ' style="width:100%;font-size:16px;padding:9px;border:1px solid #dde;border-radius:8px;box-sizing:border-box">';
  }

  function dlShowCard(){
    var d = DL.deal || {};
    document.getElementById("dlList").style.display = "none";
    var box = document.getElementById("dlCard");
    if (box.style.display !== "block") {
      try{ history.pushState({dlCard:1}, "", location.hash || ""); }catch(e){}
    }
    box.style.display = "block";

    var kind = d.kind || "white";
    var closed = (d.status === "closed");

    var h = '<button onclick="dlBackToList()" style="border:1px solid #d5dae3;background:#fff;color:#112C93;border-radius:9px;padding:7px 13px;font:600 .85rem/1 sans-serif;cursor:pointer;margin-bottom:12px">&#8249; К списку</button>'
      + '<div style="background:#fff;border:1px solid #e6e9f0;border-radius:11px;padding:14px">'
      + '<div style="font:700 1rem/1.3 sans-serif;color:#112C93;margin-bottom:4px">' + (d.id ? "Сделка" : "Новая сделка") + '</div>'
      + crmField("Название", "dl_title", d.title, "Посуда для сети Вкусно")
      + '<label style="display:block;font-size:.75rem;color:#7b8496;margin:10px 0 3px">Клиент</label>'
      + '<button onclick="dlPickClient()" style="width:100%;text-align:left;border:1px solid #dde;background:#fff;border-radius:8px;padding:10px;font:400 .92rem/1.2 sans-serif;color:#3d4a63;cursor:pointer">'
        + (DL.client ? crmEsc(DL.client.title) : "нажмите, чтобы выбрать") + '</button>'
      + '<label style="display:block;font-size:.75rem;color:#7b8496;margin:10px 0 3px">Тип поставки</label>'
      + '<div style="display:flex;gap:7px">'
        + '<button onclick="dlSetKind(\'white\')" id="dlKW" style="flex:1;border:0;border-radius:8px;padding:9px;font:600 .85rem/1 sans-serif;cursor:pointer">Белая</button>'
        + '<button onclick="dlSetKind(\'cargo\')" id="dlKC" style="flex:1;border:0;border-radius:8px;padding:9px;font:600 .85rem/1 sans-serif;cursor:pointer">Карго</button>'
      + '</div>'
      + '<input type="hidden" id="dl_kind" value="' + kind + '">';

    if (DL.margin) {
      h += '<div style="margin-top:12px;padding-top:10px;border-top:1px solid #f0f2f7">'
        + '<div style="font:700 .85rem/1.3 sans-serif;color:#112C93">За сколько продана</div>'
        + dlNum("По расчёту", "dl_soldp", d.sold_plan)
        + dlNum("Фактически", "dl_soldf", d.sold_fact)
        + '</div>';
    }

    h += crmField("Заметка", "dl_note", d.note)
      + '<label style="display:flex;align-items:center;gap:8px;margin-top:12px;font-size:.88rem;color:#3d4a63">'
        + '<input type="checkbox" id="dl_closed"' + (closed ? " checked" : "") + ' style="width:18px;height:18px"> Сделка закрыта</label>'
      + '<button onclick="dlSave()" style="width:100%;margin-top:12px;background:#112C93;color:#fff;border:0;border-radius:9px;padding:12px;font:700 .95rem/1 sans-serif;cursor:pointer">Сохранить сделку</button>'
      + '</div>';

    if (d.id) h += dlCostsHtml();
    else h += '<div style="margin-top:12px;font-size:.83rem;color:#7b8496">Расходы появятся после сохранения — статьи подставятся сами.</div>';

    box.innerHTML = h;
    dlPaintKind();
  }

  function dlSetKind(k){
    document.getElementById("dl_kind").value = k;
    dlPaintKind();
  }
  function dlPaintKind(){
    var k = (document.getElementById("dl_kind")||{}).value || "white";
    var w = document.getElementById("dlKW"), c = document.getElementById("dlKC");
    if (!w || !c) return;
    var base = "flex:1;border:0;border-radius:8px;padding:9px;font:600 .85rem/1 sans-serif;cursor:pointer;";
    w.style.cssText = base + (k==="white" ? "background:#0B6603;color:#fff" : "background:#e8ecf5;color:#3d4a63");
    c.style.cssText = base + (k==="cargo" ? "background:#c0392b;color:#fff" : "background:#e8ecf5;color:#3d4a63");
  }

  /* Таблица расходов: слева статья, справа две суммы. */
  function dlCostsHtml(){
    var s = DL.sums || {plan:0,fact:0};
    var h = '<div style="margin-top:14px;background:#fff;border:1px solid #e6e9f0;border-radius:11px;padding:14px">'
      + '<div style="font:700 .95rem/1.3 sans-serif;color:#112C93;margin-bottom:3px">Расходы по сделке</div>'
      + '<div style="display:flex;gap:7px;font-size:.72rem;color:#7b8496;margin-bottom:6px">'
        + '<div style="flex:1"></div><div style="width:88px;text-align:center">по расчёту</div><div style="width:88px;text-align:center">фактически</div></div>';

    h += DL.costs.map(function(c){
      return '<div style="display:flex;gap:7px;align-items:center;padding:4px 0">'
        + '<div style="flex:1;font-size:.84rem;color:#3d4a63">' + crmEsc(c.label)
          + (c.kind === "own" ? ' <span onclick="dlCostDel(\'' + c.id + '\')" style="color:#c0392b;cursor:pointer">&times;</span>' : '')
        + '</div>'
        + '<input id="cp_' + c.id + '" value="' + (+c.plan ? Math.round(c.plan) : "") + '" inputmode="numeric" placeholder="0" style="width:88px;font-size:15px;padding:7px;border:1px solid #dde;border-radius:7px;text-align:right;box-sizing:border-box">'
        + '<input id="cf_' + c.id + '" value="' + (+c.fact ? Math.round(c.fact) : "") + '" inputmode="numeric" placeholder="0" style="width:88px;font-size:15px;padding:7px;border:1px solid #e0d0b0;border-radius:7px;text-align:right;box-sizing:border-box">'
        + '</div>';
    }).join("");

    h += '<div style="display:flex;gap:7px;padding:9px 0 0;margin-top:6px;border-top:1px solid #f0f2f7;font-weight:700;font-size:.85rem">'
      + '<div style="flex:1">Всего расходов</div>'
      + '<div style="width:88px;text-align:right">' + Math.round(s.plan).toLocaleString("ru-RU") + '</div>'
      + '<div style="width:88px;text-align:right">' + Math.round(s.fact).toLocaleString("ru-RU") + '</div></div>';

    h += '<button onclick="dlCostsSave()" style="width:100%;margin-top:11px;background:#0B6603;color:#fff;border:0;border-radius:9px;padding:11px;font:700 .9rem/1 sans-serif;cursor:pointer">Сохранить расходы</button>'
      + '<button onclick="dlCostAdd()" style="width:100%;margin-top:8px;border:1px solid #112C93;background:#fff;color:#112C93;border-radius:9px;padding:10px;font:600 .88rem/1 sans-serif;cursor:pointer">+ Своя статья расхода</button>'
      + '</div>';

    /* Итог по сделке — только боссу. */
    if (DL.margin) {
      var mk = function(lbl, v){
        var neg = (v < 0);
        return '<div style="display:flex;justify-content:space-between;padding:5px 0">'
          + '<span style="color:#7b8496;font-size:.85rem">' + lbl + '</span>'
          + '<b style="color:' + (neg ? "#c0392b" : "#0B6603") + '">' + (neg ? "\u2212" : "") + dlMoney(Math.abs(v)) + '</b></div>';
      };
      h += '<div style="margin-top:14px;background:#f6f8ff;border:1px solid #dde3f2;border-radius:11px;padding:14px">'
        + '<div style="font:700 .95rem/1.3 sans-serif;color:#112C93;margin-bottom:6px">Итог по сделке</div>'
        + mk("По расчёту", (DL.sums.mplan||0))
        + mk("Фактически", (DL.sums.mfact||0))
        + '<div style="font-size:.75rem;color:#7b8496;margin-top:6px">Видно только вам как боссу.</div></div>';
    }
    return h;
  }

  function dlPickClient(){
    kpPickClient(function(){
      DL.client = window.KP_CLIENT ? { id:window.KP_CLIENT.id, title:window.KP_CLIENT.title } : null;
      dlShowCard();
    });
  }

  function dlSave(){
    var d = {
      id: (DL.deal && DL.deal.id) || "",
      client_id: (DL.client && DL.client.id) || "",
      title: crmV("dl_title"),
      kind: (document.getElementById("dl_kind")||{}).value || "white",
      status: (document.getElementById("dl_closed")||{}).checked ? "closed" : "open",
      note: crmV("dl_note")
    };
    if (DL.margin) {
      d.sold_plan = (crmV("dl_soldp")||"0").replace(/\s/g,"");
      d.sold_fact = (crmV("dl_soldf")||"0").replace(/\s/g,"");
    }
    if (!d.title) { alert("Впишите название сделки"); return; }
    crmPost("crm_deal_save", d).then(function(r){
      if (!r || !r.ok) { alert((r&&r.error)||"Не сохранилось"); return; }
      dlOpen(r.id);
    });
  }

  function dlCostsSave(){
    if (!DL.deal || !DL.deal.id) return;
    var rows = DL.costs.map(function(c){
      var g = function(id){ var e=document.getElementById(id); return e ? (e.value||"").replace(/\s/g,"") : "0"; };
      return { id:c.id, label:c.label, plan:g("cp_"+c.id), fact:g("cf_"+c.id) };
    });
    crmPost("crm_deal_costs_save", {deal_id:DL.deal.id, rows:rows}).then(function(r){
      if (!r || !r.ok) { alert((r&&r.error)||"Не сохранилось"); return; }
      dlOpen(DL.deal.id);
    });
  }

  function dlCostAdd(){
    var name = prompt("Название статьи расхода");
    if (!name) return;
    crmPost("crm_deal_cost_add", {deal_id:DL.deal.id, label:name}).then(function(){ dlOpen(DL.deal.id); });
  }

  function dlCostDel(id){
    if (!confirm("Убрать статью?")) return;
    crmPost("crm_deal_cost_del", {id:id}).then(function(){ dlOpen(DL.deal.id); });
  }

  /* ============================================================
     РАСХОДЫ КОМПАНИИ (4.5.0, 22.08.2026)
     Раздел бухгалтера: что потратили, когда и чем заплатили.
     Общую картину «в плюсе или в убытке» здесь НЕ показываем — она
     собирается в сводке босса.
     ============================================================ */
  var EX = { rows:[], from:"", to:"", kinds:[], edit:null, fixed:[] };

  function exMonthStart(){ var d=new Date(); return d.getFullYear()+"-"+("0"+(d.getMonth()+1)).slice(-2)+"-01"; }
  function exToday(){ var d=new Date(); return d.getFullYear()+"-"+("0"+(d.getMonth()+1)).slice(-2)+"-"+("0"+d.getDate()).slice(-2); }

  function exLoad(){
    if (!EX.from) { EX.from = exMonthStart(); EX.to = exToday(); }
    var box = document.getElementById("exBody");
    box.innerHTML = "Загружаю…";
    if (!EX.kinds.length) {
      crmGet("crm_exp_kinds").then(function(d){ if (d&&d.ok) EX.kinds = d.rows||[]; });
    }
    crmGet("crm_expenses", {from:EX.from, to:EX.to}).then(function(d){
      if (!d || !d.ok) { box.innerHTML = "Ошибка: " + crmEsc((d&&d.error)||""); return; }
      EX.rows = d.rows || [];
      box.innerHTML = exHtml(d);
      exLoadFixed();
    }).catch(function(){ box.innerHTML = "Нет связи с сервером."; });
  }

  function exHtml(d){
    var h = '<div style="background:#fff;border:1px solid #e6e9f0;border-radius:11px;padding:14px;margin-bottom:12px">'
      + '<div style="font:700 .95rem/1.3 sans-serif;color:#112C93;margin-bottom:8px">Период</div>'
      + '<div style="display:flex;gap:7px;align-items:center">'
        + '<input type="date" id="exFrom" value="' + crmEsc(d.from) + '" style="flex:1;font-size:15px;padding:8px;border:1px solid #dde;border-radius:8px;box-sizing:border-box">'
        + '<span style="color:#7b8496">—</span>'
        + '<input type="date" id="exTo" value="' + crmEsc(d.to) + '" style="flex:1;font-size:15px;padding:8px;border:1px solid #dde;border-radius:8px;box-sizing:border-box">'
      + '</div>'
      + '<button onclick="exApplyPeriod()" style="width:100%;margin-top:9px;border:1px solid #112C93;background:#fff;color:#112C93;border-radius:8px;padding:9px;font:600 .86rem/1 sans-serif;cursor:pointer">Показать</button>'
      + '</div>';

    h += '<div style="background:#f6f8ff;border:1px solid #dde3f2;border-radius:11px;padding:14px;margin-bottom:12px">'
      + '<div style="display:flex;justify-content:space-between;font-weight:700;color:#112C93"><span>Всего за период</span><span>' + dlMoney(d.sum) + '</span></div>'
      + '<div style="display:flex;justify-content:space-between;font-size:.83rem;color:#7b8496;margin-top:4px"><span>Со счёта</span><span>' + dlMoney(d.bank) + '</span></div>'
      + '<div style="display:flex;justify-content:space-between;font-size:.83rem;color:#7b8496"><span>Наличными</span><span>' + dlMoney(d.cash) + '</span></div>'
      + '</div>';

    h += '<div id="exFixed"></div>'
      + '<button onclick="exEdit(\'\')" style="width:100%;margin-bottom:12px;background:#112C93;color:#fff;border:0;border-radius:9px;padding:11px;font:700 .92rem/1 sans-serif;cursor:pointer">+ Записать расход</button>'
      + '<div id="exForm"></div>';

    if (!EX.rows.length) {
      h += '<div style="font-size:.86rem;color:#7b8496">За этот период записей нет.</div>';
    } else {
      h += EX.rows.map(function(r){
        var way = (r.pay_way === "cash") ? "наличными" : "со счёта";
        return '<div style="padding:11px 12px;margin-bottom:7px;background:#fff;border:1px solid #e6e9f0;border-radius:9px">'
          + '<div style="display:flex;justify-content:space-between;gap:8px">'
            + '<div style="font-weight:700;color:#112C93">' + crmEsc(r.label) + '</div>'
            + '<div style="font-weight:700;white-space:nowrap">' + dlMoney(r.amount) + '</div></div>'
          + '<div style="font-size:.79rem;color:#7b8496;margin-top:2px">' + crmEsc(r.paid_on || "") + ' · ' + way
            + (r.note ? ' · ' + crmEsc(r.note) : '') + '</div>'
          + '<div style="margin-top:6px">'
            + '<button onclick="exEdit(\'' + r.id + '\')" style="border:1px solid #d5dae3;background:#fff;border-radius:7px;padding:4px 10px;font-size:.78rem;cursor:pointer">Изменить</button> '
            + '<button onclick="exDel(\'' + r.id + '\')" style="border:1px solid #f0d0d0;background:#fff;color:#c0392b;border-radius:7px;padding:4px 10px;font-size:.78rem;cursor:pointer">Удалить</button></div>'
          + '</div>';
      }).join("");
    }
    return h;
  }

  function exApplyPeriod(){
    EX.from = (document.getElementById("exFrom")||{}).value || EX.from;
    EX.to   = (document.getElementById("exTo")||{}).value   || EX.to;
    exLoad();
  }

  function exEdit(id){
    var r = {};
    for (var i=0;i<EX.rows.length;i++) if (EX.rows[i].id === id) r = EX.rows[i];
    var opts = EX.kinds.map(function(k){ return '<option value="' + crmEsc(k) + '">'; }).join("");
    var f = document.getElementById("exForm");
    f.innerHTML = '<div style="background:#fff;border:1px solid #e6e9f0;border-radius:11px;padding:14px;margin-bottom:12px">'
      + '<div style="font:700 .95rem/1.3 sans-serif;color:#112C93;margin-bottom:4px">' + (id ? "Изменить расход" : "Новый расход") + '</div>'
      + '<input type="hidden" id="ex_id" value="' + crmEsc(r.id||"") + '">'
      + '<label style="display:block;font-size:.75rem;color:#7b8496;margin:8px 0 3px">Статья расхода</label>'
      + '<input id="ex_label" list="exKinds" value="' + crmEsc(r.label||"") + '" placeholder="выберите или впишите свою"'
        + ' style="width:100%;font-size:16px;padding:9px;border:1px solid #dde;border-radius:8px;box-sizing:border-box">'
      + '<datalist id="exKinds">' + opts + '</datalist>'
      + '<label style="display:block;font-size:.75rem;color:#7b8496;margin:8px 0 3px">Сумма, ₽</label>'
      + '<input id="ex_amount" inputmode="numeric" value="' + (r.amount ? Math.round(r.amount) : "") + '" placeholder="0"'
        + ' style="width:100%;font-size:16px;padding:9px;border:1px solid #dde;border-radius:8px;box-sizing:border-box">'
      + '<label style="display:block;font-size:.75rem;color:#7b8496;margin:8px 0 3px">Дата оплаты</label>'
      + '<input type="date" id="ex_date" value="' + crmEsc(r.paid_on || exToday()) + '"'
        + ' style="width:100%;font-size:16px;padding:9px;border:1px solid #dde;border-radius:8px;box-sizing:border-box">'
      + '<label style="display:block;font-size:.75rem;color:#7b8496;margin:10px 0 3px">Чем оплачено</label>'
      + '<div style="display:flex;gap:7px">'
        + '<button onclick="exWay(\'bank\')" id="exWB" style="flex:1;border:0;border-radius:8px;padding:9px;font:600 .85rem/1 sans-serif;cursor:pointer">Со счёта</button>'
        + '<button onclick="exWay(\'cash\')" id="exWC" style="flex:1;border:0;border-radius:8px;padding:9px;font:600 .85rem/1 sans-serif;cursor:pointer">Наличными</button>'
      + '</div>'
      + '<input type="hidden" id="ex_way" value="' + crmEsc(r.pay_way || "bank") + '">'
      + '<label style="display:block;font-size:.75rem;color:#7b8496;margin:8px 0 3px">Комментарий</label>'
      + '<input id="ex_note" value="' + crmEsc(r.note||"") + '" style="width:100%;font-size:16px;padding:9px;border:1px solid #dde;border-radius:8px;box-sizing:border-box">'
      + '<button onclick="exSave()" style="width:100%;margin-top:12px;background:#0B6603;color:#fff;border:0;border-radius:9px;padding:11px;font:700 .92rem/1 sans-serif;cursor:pointer">Сохранить</button>'
      + '<button onclick="exSaveFixed()" style="width:100%;margin-top:8px;border:1px solid #112C93;background:#fff;color:#112C93;border-radius:9px;padding:10px;font:600 .88rem/1 sans-serif;cursor:pointer">Сохранить и сделать постоянным</button>'
      + '<div style="font-size:.73rem;color:#7b8496;margin-top:5px">Постоянный расход повторяется каждый месяц — аренда, зарплата, интернет. Вносить его будете одной кнопкой, а не заново руками.</div>'
      + '</div>';
    exPaintWay();
    try{ f.scrollIntoView({behavior:"smooth", block:"start"}); }catch(e){}
  }

  function exWay(w){ document.getElementById("ex_way").value = w; exPaintWay(); }
  function exPaintWay(){
    var w = (document.getElementById("ex_way")||{}).value || "bank";
    var b = document.getElementById("exWB"), c = document.getElementById("exWC");
    if (!b || !c) return;
    var base = "flex:1;border:0;border-radius:8px;padding:9px;font:600 .85rem/1 sans-serif;cursor:pointer;";
    b.style.cssText = base + (w==="bank" ? "background:#112C93;color:#fff" : "background:#e8ecf5;color:#3d4a63");
    c.style.cssText = base + (w==="cash" ? "background:#0B6603;color:#fff" : "background:#e8ecf5;color:#3d4a63");
  }

  function exSave(){
    var d = {
      id: crmV("ex_id"),
      label: crmV("ex_label"),
      amount: (crmV("ex_amount")||"0").replace(/\s/g,""),
      paid_on: crmV("ex_date"),
      pay_way: (document.getElementById("ex_way")||{}).value || "bank",
      note: crmV("ex_note")
    };
    if (!d.label) { alert("Впишите статью расхода"); return; }
    if (!(+d.amount > 0)) { alert("Впишите сумму"); return; }
    crmPost("crm_exp_save", d).then(function(r){
      if (!r || !r.ok) { alert((r&&r.error)||"Не сохранилось"); return; }
      exLoad();
    });
  }

  function exDel(id){
    if (!confirm("Удалить запись?")) return;
    crmPost("crm_exp_del", {id:id}).then(function(){ exLoad(); });
  }

  /* ПОСТОЯННЫЕ РАСХОДЫ. Здесь лежит образец: что и сколько платим каждый
     месяц. Кнопка «Внести за месяц» кладёт выбранное в обычные расходы —
     повторно одно и то же не внесётся, сервер это проверяет. */
  function exLoadFixed(){
    var box = document.getElementById("exFixed");
    if (!box) return;
    crmGet("crm_fixed").then(function(d){
      if (!d || !d.ok) { box.innerHTML = ""; return; }
      EX.fixed = d.rows || [];
      if (!EX.fixed.length) { box.innerHTML = ""; return; }
      var left = EX.fixed.filter(function(r){ return !r.done; });
      var h = '<div style="background:#fff;border:1px solid #e6e9f0;border-radius:11px;padding:14px;margin-bottom:12px">'
        + '<div style="font:700 .95rem/1.3 sans-serif;color:#112C93;margin-bottom:3px">Постоянные расходы</div>'
        + '<div style="font-size:.76rem;color:#7b8496;margin-bottom:8px">За ' + crmEsc(d.month) + ' не внесено: ' + left.length + ' из ' + EX.fixed.length + '</div>';
      h += EX.fixed.map(function(r){
        return '<div style="display:flex;align-items:center;gap:9px;padding:7px 0;border-top:1px solid #f0f2f7">'
          + (r.done
              ? '<span style="width:18px;text-align:center;color:#0B6603">\u2713</span>'
              : '<input type="checkbox" id="fx_' + r.id + '" checked style="width:18px;height:18px">')
          + '<div style="flex:1">'
            + '<div style="font-size:.86rem;' + (r.done ? "color:#9aa3b2" : "font-weight:600") + '">' + crmEsc(r.label) + '</div>'
            + '<div style="font-size:.75rem;color:#7b8496">' + dlMoney(r.amount) + ' · ' + (r.pay_way==="cash"?"наличными":"со счёта") + ' · ' + r.day + '-го</div>'
          + '</div>'
          + '<span onclick="exFixedDel(\'' + r.id + '\')" style="color:#c0392b;cursor:pointer;padding:0 4px">&times;</span>'
          + '</div>';
      }).join("");
      if (left.length) {
        h += '<button onclick="exFixedApply()" style="width:100%;margin-top:10px;background:#0B6603;color:#fff;border:0;border-radius:9px;padding:11px;font:700 .9rem/1 sans-serif;cursor:pointer">Внести отмеченные за этот месяц</button>';
      } else {
        h += '<div style="margin-top:9px;font-size:.8rem;color:#0B6603">Всё за этот месяц уже внесено.</div>';
      }
      box.innerHTML = h + '</div>';
    }).catch(function(){ box.innerHTML = ""; });
  }

  function exSaveFixed(){
    var d = {
      label: crmV("ex_label"),
      amount: (crmV("ex_amount")||"0").replace(/\s/g,""),
      pay_way: (document.getElementById("ex_way")||{}).value || "bank",
      day: (crmV("ex_date")||"").slice(8,10) || "1",
      note: crmV("ex_note")
    };
    if (!d.label) { alert("Впишите статью расхода"); return; }
    if (!(+d.amount > 0)) { alert("Впишите сумму"); return; }
    /* Сначала записываем сам расход, потом заводим образец: если образец
       не завёлся (например, такой уже есть), расход всё равно сохранён. */
    exSave();
    crmPost("crm_fixed_save", d).then(function(r){
      if (r && r.ok) alert("Расход записан и добавлен в постоянные");
      else alert("Расход записан. В постоянные не добавлен: " + ((r&&r.error)||"ошибка"));
      exLoad();
    });
  }

  function exFixedApply(){
    var ids = (EX.fixed||[]).filter(function(r){
      if (r.done) return false;
      var c = document.getElementById("fx_" + r.id);
      return c && c.checked;
    }).map(function(r){ return r.id; });
    if (!ids.length) { alert("Ничего не отмечено"); return; }
    crmPost("crm_fixed_apply", {ids:ids}).then(function(r){
      if (!r || !r.ok) { alert((r&&r.error)||"Не получилось"); return; }
      alert("Внесено: " + r.added + (r.skipped ? ", пропущено уже внесённых: " + r.skipped : ""));
      exLoad();
    });
  }

  function exFixedDel(id){
    if (!confirm("Убрать из постоянных? Уже записанные расходы останутся.")) return;
    crmPost("crm_fixed_del", {id:id}).then(function(){ exLoadFixed(); });
  }

  /* ============================================================
     СВОДКА БОССА (4.7.0, 22.08.2026)
     Заработали на сделках минус расходы компании. В прибыль идут только
     ЗАКРЫТЫЕ сделки — по ним поставка завершена и цифры окончательные.
     Открытые показываем отдельно, чтобы было видно, что ещё в работе.
     ============================================================ */
  var SM = { period:"month" };

  function smSetPeriod(p){ SM.period = p; smLoad(); }

  function smLoad(){
    var box = document.getElementById("smBody");
    box.innerHTML = "Считаю…";
    crmGet("crm_summary", {period:SM.period}).then(function(d){
      if (!d || !d.ok) { box.innerHTML = "Ошибка: " + crmEsc((d&&d.error)||""); return; }
      box.innerHTML = smHtml(d);
    }).catch(function(){ box.innerHTML = "Нет связи с сервером."; });
  }

  function smBtn(p, label){
    var on = (SM.period === p);
    return '<button onclick="smSetPeriod(\'' + p + '\')" style="flex:1;border:0;border-radius:8px;padding:9px 4px;font:600 .8rem/1 sans-serif;cursor:pointer;'
      + (on ? "background:#112C93;color:#fff" : "background:#e8ecf5;color:#3d4a63") + '">' + label + '</button>';
  }

  function smRow(label, v, big){
    var neg = (v < 0);
    return '<div style="display:flex;justify-content:space-between;align-items:baseline;padding:' + (big ? "8px 0" : "4px 0") + '">'
      + '<span style="color:' + (big ? "#112C93" : "#7b8496") + ';font-size:' + (big ? ".95rem;font-weight:700" : ".85rem") + '">' + label + '</span>'
      + '<b style="font-size:' + (big ? "1.05rem" : ".92rem") + ';color:' + (neg ? "#c0392b" : (big ? "#0B6603" : "#3d4a63")) + '">'
      + (neg ? "\u2212" : "") + dlMoney(Math.abs(v)) + '</b></div>';
  }

  function smHtml(d){
    var h = '<div style="display:flex;gap:6px;margin-bottom:12px">'
      + smBtn("week","Неделя") + smBtn("month","Месяц") + smBtn("quarter","Квартал") + smBtn("year","Год")
      + '</div>'
      + '<div style="font-size:.76rem;color:#7b8496;margin-bottom:10px">с ' + crmEsc(d.from) + ' по ' + crmEsc(d.to) + '</div>';

    /* ГЛАВНАЯ ЦИФРА — в плюсе компания или в убытке. */
    var plus = (d.bottom >= 0);
    h += '<div style="background:' + (plus ? "#eef7ee" : "#fdeeee") + ';border:1px solid ' + (plus ? "#cfe6cf" : "#f2d5d5") + ';border-radius:12px;padding:16px;margin-bottom:12px;text-align:center">'
      + '<div style="font-size:.83rem;color:#7b8496">' + (plus ? "Компания в плюсе" : "Компания в убытке") + '</div>'
      + '<div style="font:800 1.7rem/1.2 sans-serif;color:' + (plus ? "#0B6603" : "#c0392b") + ';margin-top:4px">'
        + (plus ? "" : "\u2212") + dlMoney(Math.abs(d.bottom)) + '</div>'
      + '<div style="font-size:.75rem;color:#7b8496;margin-top:5px">заработок на сделках минус расходы компании</div>'
      + '</div>';

    h += '<div style="background:#fff;border:1px solid #e6e9f0;border-radius:11px;padding:14px;margin-bottom:12px">'
      + '<div style="font:700 .95rem/1.3 sans-serif;color:#112C93;margin-bottom:4px">Сделки</div>'
      + smRow("Продано на", d.revenue)
      + smRow("Расходы по сделкам", -d.deal_cost)
      + '<div style="border-top:1px solid #f0f2f7;margin-top:6px">' + smRow("Заработано на сделках", d.deal_profit, true) + '</div>'
      + '<div style="border-top:1px solid #f0f2f7;padding-top:6px;margin-top:4px">'
        + smRow("из них белые", d.white) + smRow("из них карго", d.cargo) + '</div>'
      + '<div style="font-size:.75rem;color:#7b8496;margin-top:7px">Считаются только закрытые сделки. Где есть фактические суммы — берём их, иначе расчётные.</div>'
      + '</div>';

    h += '<div style="background:#fff;border:1px solid #e6e9f0;border-radius:11px;padding:14px;margin-bottom:12px">'
      + '<div style="font:700 .95rem/1.3 sans-serif;color:#112C93;margin-bottom:4px">Расходы компании</div>'
      + smRow("Всего за период", -d.exp_total, true);
    if ((d.expenses||[]).length) {
      h += '<div style="border-top:1px solid #f0f2f7;margin-top:6px;padding-top:4px">'
        + d.expenses.map(function(r){ return smRow(crmEsc(r.label), -r.s); }).join("") + '</div>';
    } else {
      h += '<div style="font-size:.83rem;color:#7b8496;margin-top:4px">За период ничего не записано.</div>';
    }
    h += '</div>';

    if ((d.closed||[]).length) {
      h += '<div style="background:#fff;border:1px solid #e6e9f0;border-radius:11px;padding:14px;margin-bottom:12px">'
        + '<div style="font:700 .95rem/1.3 sans-serif;color:#112C93;margin-bottom:6px">Закрытые сделки за период</div>'
        + d.closed.map(function(r){
            var neg = (r.margin < 0);
            return '<div onclick="smGoDeal(\'' + r.id + '\')" style="display:flex;justify-content:space-between;gap:9px;padding:8px 0;border-top:1px solid #f0f2f7;cursor:pointer">'
              + '<div><div style="font-size:.87rem;font-weight:600">' + crmEsc(r.title) + '</div>'
              + '<div style="font-size:.75rem;color:#7b8496">' + crmEsc(r.who || "без клиента")
                + ' · ' + (r.kind === "cargo" ? "карго" : "белая") + '</div></div>'
              + '<b style="white-space:nowrap;color:' + (neg ? "#c0392b" : "#0B6603") + '">'
                + (neg ? "\u2212" : "") + dlMoney(Math.abs(r.margin)) + '</b></div>';
          }).join("") + '</div>';
    }

    if ((d.open||[]).length) {
      h += '<div style="background:#fff;border:1px solid #e6e9f0;border-radius:11px;padding:14px">'
        + '<div style="font:700 .95rem/1.3 sans-serif;color:#112C93;margin-bottom:3px">Ещё в работе</div>'
        + '<div style="font-size:.75rem;color:#7b8496;margin-bottom:5px">В прибыль не считаются — поставка не завершена.</div>'
        + d.open.map(function(r){
            return '<div onclick="smGoDeal(\'' + r.id + '\')" style="display:flex;justify-content:space-between;gap:9px;padding:7px 0;border-top:1px solid #f0f2f7;cursor:pointer">'
              + '<div style="font-size:.86rem">' + crmEsc(r.title) + '</div>'
              + '<span style="font-size:.8rem;color:#7b8496;white-space:nowrap">' + dlMoney(r.cost) + ' вложено</span></div>';
          }).join("") + '</div>';
    }
    return h;
  }

  /* Из сводки можно провалиться прямо в сделку. */
  function smGoDeal(id){
    showSection('deals');
    dlOpen(id);
  }

  window.smLoad = smLoad; window.smSetPeriod = smSetPeriod; window.smGoDeal = smGoDeal;

  window.exLoadFixed = exLoadFixed; window.exSaveFixed = exSaveFixed;
  window.exFixedApply = exFixedApply; window.exFixedDel = exFixedDel;
  window.exLoad = exLoad; window.exApplyPeriod = exApplyPeriod; window.exEdit = exEdit;
  window.exWay = exWay; window.exSave = exSave; window.exDel = exDel;

  window.dlLoad = dlLoad; window.dlSetTab = dlSetTab; window.dlNew = dlNew;
  window.dlOpen = dlOpen; window.dlSave = dlSave; window.dlSetKind = dlSetKind;
  window.dlBackToList = dlBackToList; window.dlPickClient = dlPickClient;
  window.dlCostsSave = dlCostsSave; window.dlCostAdd = dlCostAdd; window.dlCostDel = dlCostDel;

  window.crmPost        = crmPost;
  window.crmGet         = crmGet;
  window.crmLoadClients = crmLoadClients;
  window.crmSearchLater = crmSearchLater;
  window.crmNewClient   = crmNewClient;
  window.crmBackToList  = crmBackToList;
  window.crmOpen        = crmOpen;
  window.crmSaveClient  = crmSaveClient;
  window.crmPullInn     = crmPullInn;
  window.crmEditContact = crmEditContact;
  window.crmSaveContact = crmSaveContact;
  window.crmDelContact  = crmDelContact;


  var APP_VERSION = '4.10.9';
  window.APP_VERSION = APP_VERSION;   // нужно окну правки для подписи в шапке
  // Относительные пути — приложение обновляется со своего домена (Netlify, субдомен Reg.ru — любой)
  var UPDATE_URL = './';
  var VERSION_CHECK_URL = '/eco/version.json';

  function showToast(msg, type, duration) {
    var t = document.getElementById('update-toast');
    t.textContent = msg;
    t.className = 'update-toast show' + (type ? ' ' + type : '');
    setTimeout(function() { t.className = 'update-toast'; }, duration || 3000);
  }

  window.checkUpdate = function() {
    var btn = document.getElementById('update-check-btn');
    btn.disabled = true;
    btn.innerHTML = '⏳ Проверяем...';

    // Если файл открыт локально — предлагаем открыть с хостинга
    if (window.location.protocol === 'file:') {
      btn.disabled = false;
      btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.2 12a8.2 8.2 0 1 1-2.4-5.8"/><path d="M20.4 3.6v4.9h-4.9"/></svg>Проверить обновления';
      var go = confirm('Приложение открыто локально.\nОткрыть актуальную версию с сервера?');
      if (go) window.open(UPDATE_URL, '_blank');
      return;
    }

    // Если открыт с веб-сервера — проверяем version.json
    fetch(VERSION_CHECK_URL + '?t=' + Date.now())
      .then(function(r) { return r.json(); })
      .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.2 12a8.2 8.2 0 1 1-2.4-5.8"/><path d="M20.4 3.6v4.9h-4.9"/></svg>Проверить обновления';
        if (data.version && data.version !== APP_VERSION) {
          var go = confirm('Доступна новая версия: ' + data.version + '\nТекущая: ' + APP_VERSION + '\n\nОбновить сейчас?');
          if (go) window.location.reload(true);
        } else {
          showToast('✅ У вас актуальная версия ' + APP_VERSION, 'success', 2500);
        }
      })
      .catch(function() {
        btn.disabled = false;
        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.2 12a8.2 8.2 0 1 1-2.4-5.8"/><path d="M20.4 3.6v4.9h-4.9"/></svg>Проверить обновления';
        // Если нет version.json — просто делаем hard reload
        var go = confirm('Не удалось проверить версию.\nПерезагрузить приложение?');
        if (go) window.location.reload(true);
      });
  };
})();
</script>
<script>
// ===== PWA: Service Worker + Автообновление =====
if ('serviceWorker' in navigator) {

  navigator.serviceWorker.register('/eco/sw.js', { scope: '/eco/' }).then(function(reg) {

    // 1. Проверяем обновление сразу при открытии приложения
    reg.update();

    // 2. Проверяем каждые 30 минут пока приложение открыто
    setInterval(function() { reg.update(); }, 30 * 60 * 1000);

    /* 3. НОВАЯ ВЕРСИЯ НЕ ПРИМЕНЯЕТСЯ САМА.
       Раньше обновление ставилось немедленно, страница тут же перезагружалась,
       и человек видел, как загрузка идёт дважды подряд: кружок, пропал, снова
       кружок. Теперь просто показываем полоску «Доступна новая версия» —
       обновление применяется по нажатию, в удобный момент. Если её не нажать,
       новая версия встанет при следующем запуске сама собой. */
    var showUpdateBar = function(sw) {
      var t = document.getElementById('update-toast');
      if (!t) return;
      t.textContent = 'Доступна новая версия — нажмите, чтобы обновить';
      t.className = 'update-toast show';
      t.style.cursor = 'pointer';
      t.onclick = function() {
        t.textContent = 'Обновляем…';
        try { sessionStorage.setItem('itc_eco_upd', '1'); } catch(e) {}
        if (sw) sw.postMessage({ type: 'SKIP_WAITING' });
      };
    };
    reg.addEventListener('updatefound', function() {
      var newSW = reg.installing;
      newSW.addEventListener('statechange', function() {
        if (newSW.state === 'installed' && navigator.serviceWorker.controller) showUpdateBar(newSW);
      });
    });
    if (reg.waiting) showUpdateBar(reg.waiting);
  });

  // 4. Когда SW взял контроль — перезагружаем страницу (применяем обновление).
  //    ВАЖНО: при ПЕРВОМ открытии обработчик берёт контроль впервые, и раньше
  //    это тоже считалось обновлением — страница перезагружалась сама, из-за
  //    чего шестимегабайтный файл грузился дважды. Теперь перезагружаем только
  //    если контроль уже был, то есть пришла действительно новая версия.
  var hadController = !!navigator.serviceWorker.controller;
  var refreshing = false;
  navigator.serviceWorker.addEventListener('controllerchange', function() {
    if (!refreshing && hadController) {
      refreshing = true;
      /* Помечаем, что перезагрузка идёт ради обновления: при следующем запуске
         заставку показываем мгновенно, чтобы не было ощущения двойной
         загрузки — она и правда проигрывалась дважды подряд. */
      try { sessionStorage.setItem('itc_eco_upd', '1'); } catch(e) {}
      window.location.reload();
    }
  });

  // 5. Сообщение от SW об обновлении
  navigator.serviceWorker.addEventListener('message', function(e) {
    if (e.data && e.data.type === 'SW_UPDATED') {
      var t = document.getElementById('update-toast');
      if (t) {
        t.textContent = '✅ Приложение обновлено до ' + e.data.version;
        t.className = 'update-toast show success';
        setTimeout(function() { t.className = 'update-toast'; }, 3000);
      }
    }
  });
}

// Кнопка ручной проверки (резерв)
window.checkUpdate = function() {
  var btn = document.getElementById('update-check-btn');
  btn.disabled = true;
  btn.innerHTML = '⏳ Проверяем...';

  if (!('serviceWorker' in navigator)) {
    window.location.reload(true);
    return;
  }

  navigator.serviceWorker.getRegistration().then(function(reg) {
    if (!reg) { btn.disabled = false; btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.2 12a8.2 8.2 0 1 1-2.4-5.8"/><path d="M20.4 3.6v4.9h-4.9"/></svg>Проверить обновления'; return; }
    reg.update().then(function() {
      setTimeout(function() {
        if (!reg.installing && !reg.waiting) {
          btn.disabled = false;
          btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.2 12a8.2 8.2 0 1 1-2.4-5.8"/><path d="M20.4 3.6v4.9h-4.9"/></svg>Проверить обновления';
          var t = document.getElementById('update-toast');
          t.textContent = '✅ Установлена актуальная версия';
          t.className = 'update-toast show success';
          setTimeout(function() { t.className = 'update-toast'; }, 2500);
        }
      }, 2000);
    });
  });
};
</script>
<!-- ===== ПОЛНЫЙ РАСЧЁТ ===== -->
<div id="section-fullcalc" class="app-section">
  <div class="section-topbar">
    <button class="back-btn" onclick="showHub()">&#8592; Назад</button>
    <span class="section-topbar-title">Полный расчёт</span>
    <button class="btn-history" data-action="open-history"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="8.6"/><path d="M12 7.2V12l3.2 1.9"/></svg>История</button>
  </div>
  <div class="brand-bar">
    <div class="brand-bar-logo"><img src="/eco/logo1.png" alt="ИТК"></div>
    <div class="brand-bar-text">
      <div class="brand-bar-title">ИТК — Полный расчёт</div>
      <div class="brand-bar-sub">Расчёт по позициям с распределением затрат по объёму</div>
    </div>
  </div>

  <div class="wrap">
    <!-- Вкладки -->
    <div class="toggle-tabs sans">
      <div class="fc-tab active" data-tab="fc-calc">Калькулятор</div>
      <div class="fc-tab" data-tab="fc-ref">Классификация клиентов</div>
    </div>

    <!-- Вкладка: Калькулятор -->
    <div id="fc-calc">

      <!-- Категория -->
      <div class="panel">
        <div class="panel-head sans"><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17.3l-6.2 3.7 1.6-7-5.4-4.7 7.1-.6L12 2l2.9 6.7 7.1.6-5.4 4.7 1.6 7z"/></svg></span>КАТЕГОРИЯ КЛИЕНТА</div>
        <div class="panel-body">
          <div class="field-label sans">ВЫБЕРИТЕ КАТЕГОРИЮ СДЕЛКИ</div>
          <input type="hidden" id="fc-cat" value="A1"><button type="button" class="catpick sans" id="fc-cat-btn"><span class="catpick-t" id="fc-cat-label">A1 — Новый клиент (15%)</span><span class="catpick-ar">›</span></button>
          <div id="fc-cat-desc" class="cat-desc sans"></div>
          <div id="fc-cat-formula" class="formula-tag sans"></div>
        </div>
      </div>

      <!-- Курсы и фрахт -->
      <div class="panel">
        <div class="panel-head sans"><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h11v9H3zM14 9h3.5l2.5 3v3h-6zM7 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/></svg></span>КОНТЕЙНЕР И КУРСЫ ВАЛЮТ</div>
        <div class="panel-body grid2">
          <div class="input-row">
            <label class="input-label sans" id="fc-lb-container">Ёмкость контейнера (м³)</label>
            <div class="input-cell">
              <input type="number" id="fc-container" value="68" step="1" min="1" class="num-input sans">
              <span class="input-unit sans">м³</span>
            </div>
          </div>
          <div class="input-row">
            <label class="input-label sans" id="fc-lb-containerWt">Грузоподъёмность контейнера</label>
            <div class="input-cell">
              <input type="number" id="fc-containerWt" value="26800" step="100" min="1" class="num-input sans">
              <span class="input-unit sans">кг</span>
            </div>
          </div>
          <div class="input-row">
            <label class="input-label sans">Фрахт Китай → Москва (Ф)</label>
            <div class="input-cell">
              <input type="number" id="fc-freight" value="10000" step="100" class="num-input sans">
              <span class="input-unit sans">USD</span>
            </div>
          </div>
          <!-- ВАЛЮТА ИНВОЙСА. Меняет ТОЛЬКО то, в чём вбивается цена товара.
               Фрахт остаётся в долларах, станция/брокер/доставка — в рублях,
               оба курса стоят здесь же и никуда не деваются. -->
          <!-- Автоперевозка: вместо фрахта за контейнер — ставка за кубометр.
               Показывается только для категорий «Автосбор». -->
          <div class="input-row" id="fc-row-cbmrate" style="display:none">
            <label class="input-label sans">Ставка за 1 м³ (авто)</label>
            <div class="input-cell">
              <input type="number" id="fc-cbmrate" value="150" step="5" min="0" class="num-input sans">
              <span class="input-unit sans">USD/м³</span>
            </div>
          </div>
          <div class="input-row" id="fc-row-payfee" style="display:none">
            <label class="input-label sans">Комиссия за оплату логистики (%)</label>
            <div class="input-cell">
              <input type="number" id="fc-payfee" value="6" step="0.5" min="0" class="num-input sans">
              <span class="input-unit sans">%</span>
            </div>
          </div>
          <div class="input-row" id="fc-row-payrate" style="display:none">
            <label class="input-label sans">Курс оплаты логистики (₽/$)</label>
            <div class="input-cell">
              <input type="number" id="fc-payrate" value="95" step="0.5" min="0" class="num-input sans">
              <span class="input-unit sans">₽/$</span>
            </div>
          </div>
          <div class="input-row" id="fc-row-export" style="display:none">
            <label class="input-label sans">Экспортное оформление в Китае</label>
            <div class="input-cell">
              <input type="number" id="fc-export" value="500" step="100" min="0" class="num-input sans">
              <span class="input-unit sans" id="fc-export-unit">¥</span>
            </div>
          </div>
          <div class="input-row wide cur-row-hl">
            <label class="input-label sans">Валюта инвойса — в чём указана цена товара</label>
            <div class="input-cell">
              <div class="cur-switch sans">
                <button type="button" class="cur-btn active" data-cur="CNY">¥ юани</button>
                <button type="button" class="cur-btn" data-cur="USD">$ доллары</button>
              </div>
            </div>
          </div>
          <div class="input-row">
            <label class="input-label sans">Курс CNY/RUB (юань → рубль)</label>
            <div class="input-cell">
              <input type="number" id="fc-cny" value="13.2" step="0.1" class="num-input sans">
              <span class="input-unit sans">₽/¥</span>
            </div>
          </div>
          <div class="input-row">
            <label class="input-label sans">Курс USD/RUB (доллар → рубль)</label>
            <div class="input-cell">
              <input type="number" id="fc-usd" value="95" step="0.5" class="num-input sans">
              <span class="input-unit sans">₽/$</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Фиксированные ставки -->
      <div class="panel">
        <div class="panel-head sans"><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l9 4.5V9H3V7.5zM5 11h2v6H5zm4 0h2v6H9zm4 0h2v6h-2zm4 0h2v6h-2zM3 19h18v2H3z"/></svg></span>ФИКСИРОВАННЫЕ СТАВКИ</div>
        <div class="fld-legend sans">
          <span><i style="background:#112C93"></i>деньги</span>
          <span><i style="background:#0A7A8C"></i>объём</span>
          <span><i style="background:#6A3FA0"></i>вес</span>
          <span><i style="background:#C2610A"></i>логистика</span>
          <span><i style="background:#6b7a99"></i>налоги и проценты</span>
        </div>
        <div class="panel-body grid2">
          <div class="input-row">
            <label class="input-label sans">Пошлина (%)</label>
            <div class="input-cell">
              <input type="number" id="fc-duty" value="10" step="0.5" class="num-input sans">
              <span class="input-unit sans">%</span>
            </div>
          </div>
          <div class="input-row wide">
            <label class="input-label sans">НДС (%)</label>
            <div class="input-cell">
              <input type="number" id="fc-vat" value="22" step="0.5" class="num-input sans">
              <span class="input-unit sans">%</span>
            </div>
          </div>
          <!-- Страховка груза. Берётся процентом от стоимости товара; в переписке
               с экспедитором это 0,5%. Можно не страховать вовсе. -->
          <div class="input-row">
            <label class="input-label sans">Страховка груза</label>
            <div class="input-cell">
              <div class="cur-switch sans" id="fc-ins-switch">
                <button type="button" class="cur-btn active" data-ins="0">без</button>
                <button type="button" class="cur-btn" data-ins="0.5">0,5%</button>
                <button type="button" class="cur-btn" data-ins="1">1%</button>
                <button type="button" class="cur-btn" data-ins="1.5">1,5%</button>
                <button type="button" class="cur-btn" data-ins="2">2%</button>
              </div>
              <input type="hidden" id="fc-ins" value="0">
            </div>
          </div>
          <div class="input-row" id="fc-row-station">
            <label class="input-label sans">Станция (₽ на контейнер)</label>
            <div class="input-cell">
              <input type="number" id="fc-station" value="150000" step="1000" class="num-input sans">
              <span class="input-unit sans">₽</span>
            </div>
          </div>
          <!-- Автодоставка: вместо услуг станции — плечо внутри Китая, от фабрики
               до нашего склада. Вводится в валюте инвойса. -->
          <!-- Логистику оплачиваем экспедитору по ЕГО внутреннему курсу, не
               банковскому, и сверху он берёт процент за оплату. В переписке это
               выглядит как «150 $ / 0,94» — то есть те же 6%. -->
          
          
          
          <div class="input-row" id="fc-row-china" style="display:none">
            <label class="input-label sans">Доставка по Китаю (фабрика → склад)</label>
            <div class="input-cell">
              <input type="number" id="fc-china" value="0" step="100" min="0" class="num-input sans">
              <span class="input-unit sans" id="fc-china-unit">¥</span>
            </div>
          </div>
          <div class="input-row">
            <label class="input-label sans">Брокер (₽ на поставку)</label>
            <div class="input-cell">
              <input type="number" id="fc-broker" value="35000" step="1000" class="num-input sans">
              <span class="input-unit sans">₽</span>
            </div>
          </div>
          <div class="input-row">
            <label class="input-label sans" id="fc-lb-delivery">Доставка до склада (₽)</label>
            <div class="input-cell">
              <input type="number" id="fc-delivery" value="250000" step="1000" class="num-input sans">
              <span class="input-unit sans">₽</span>
            </div>
          </div>
          <div class="input-row" id="fc-row-citynm" style="display:none">
            <label class="input-label sans">Доставка до города</label>
            <div class="input-cell">
              <input type="text" id="fc-cityname" value="Симферополь" class="num-input sans" style="text-align:left">
            </div>
          </div>
          <div class="input-row" id="fc-row-cityrate" style="display:none">
            <label class="input-label sans">Ставка до города (₽ за м³)</label>
            <div class="input-cell">
              <input type="number" id="fc-cityrate" value="6200" step="100" min="0" class="num-input sans">
              <span class="input-unit sans">₽</span>
            </div>
          </div>
          <div class="input-row">
            <label class="input-label sans">Конверсия/перевод (%)</label>
            <div class="input-cell">
              <input type="number" id="fc-transfer" value="3" step="0.5" class="num-input sans">
              <span class="input-unit sans">%</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Позиции товаров -->
      <div class="panel">
        <div class="panel-head sans"><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l9 4.2v11.6L12 22l-9-4.2V6.2zm0 2.3L5.6 7 12 9.7 18.4 7zM5 8.8v8l6 2.8v-8.2zm14 0l-6 2.6v8.2l6-2.8z"/></svg></span>ТОВАРНЫЕ ПОЗИЦИИ</div>
        <div class="panel-body" style="padding-bottom:0">

          <!-- Индикатор заполнения контейнера -->
          <div class="fc-fill-wrap">
            <div class="fc-fill-header sans">
              <span>Заполнение контейнера</span>
              <span id="fc-fill-text">0 / 68 м³</span>
            </div>
            <div class="fc-fill-bar-bg">
              <div id="fc-fill-bar" class="fc-fill-bar" style="width:0%"></div>
            </div>
            <div id="fc-fill-warning" class="fc-fill-warning hidden"></div>
          </div>

          <!-- Список позиций -->
          <div id="fc-items-list"></div>

          <!-- Кнопка добавить -->
          <button class="fc-add-btn sans" onclick="fcAddItem()">&#43; Добавить позицию</button>
        </div>
      </div>

      <!-- Результаты -->
      <div id="fc-results-wrap" class="hidden">
        <div id="fc-results-content"></div>
      </div>

    </div><!-- /fc-calc -->

    <!-- Вкладка: Классификация -->
    <div id="fc-ref" class="hidden">
      <div class="panel-head sans" style="margin:0 -16px;padding:14px 16px"><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v2H4zm0 6h16v2H4zm0 6h16v2H4z"/></svg></span>Классификация клиентов — справочник</div>
      <div style="overflow-x:auto">
        <table class="ref-table">
          <thead><tr>
            <th>Код</th><th>Тип</th><th>Наценка</th><th>Формула</th>
          </tr></thead>
          <tbody>
            <tr><td>A1</td><td>Новый клиент</td><td>15%</td><td>(База×1,15)×1,03</td></tr>
            <tr><td>A2</td><td>Активный клиент</td><td>10%</td><td>(База×1,10)×1,03</td></tr>
            <tr><td>A3</td><td>Постоянный клиент</td><td>5%</td><td>(База×1,05)×1,03</td></tr>
            <tr><td>B1</td><td>Своя фабрика (старт)</td><td>10%</td><td>(База×1,10)×1,03</td></tr>
            <tr><td>B2</td><td>Своя фабрика (актив)</td><td>5%</td><td>(База×1,05)×1,03</td></tr>
            <tr><td>C0</td><td>Сборный — особые</td><td>Лог.×2,0 + тов.25%</td><td>(Б1+Б2×2,0)×1,03</td></tr>
            <tr><td>C1</td><td>Сборный — малый</td><td>Лог.×2,0 + тов.15%</td><td>(Б1+Б2×2,0)×1,03</td></tr>
            <tr><td>C2</td><td>Сборный — средний</td><td>Лог.×1,8 + тов.15%</td><td>(Б1+Б2×1,8)×1,03</td></tr>
            <tr><td>C3</td><td>Сборный — полный</td><td>Лог.×1,5 + тов.15%</td><td>(Б1+Б2×1,5)×1,03</td></tr>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /wrap -->

  <script>
(function() {

  // ===== Базы данных для автопроверки =====

  // Честный знак: [keywords, status, note]
  // status: 'yes'=нужен, 'no'=не нужен (для этой категории)
  var CZ_DB = [
    {kw:['обувь','кроссовки','ботинки','сапоги','туфли','сандали','кеды','мокасины','тапки','шлёпки','угги','валенки'],
     status:'yes', label:'Честный знак', note:'Маркировка обязательна (обувь)'},
    {kw:['шина','покрышка','колесо резиновое','автошина'],
     status:'yes', label:'Честный знак', note:'Маркировка обязательна (шины)'},
    {kw:['духи','парфюм','туалетная вода','парфюмерия','одеколон','ароматическая вода'],
     status:'yes', label:'Честный знак', note:'Маркировка обязательна (парфюмерия)'},
    {kw:['постельное бельё','постельное белье','пододеяльник','простынь','простыня','наволочка','комплект белья'],
     status:'yes', label:'Честный знак', note:'Маркировка обязательна (постельное бельё)'},
    {kw:['полотенце','кухонное полотенце','банное полотенце'],
     status:'yes', label:'Честный знак', note:'Маркировка обязательна (текстиль для кухни/ванной)'},
    {kw:['пальто','куртка','плащ','анорак','ветровка','пуховик','жакет','пиджак','пальтецо','шуба','дублёнка','бомбер'],
     status:'yes', label:'Честный знак', note:'Маркировка обязательна (верхняя одежда)'},
    {kw:['блузка','блуза','блузон'],
     status:'yes', label:'Честный знак', note:'Маркировка обязательна (блузки)'},
    {kw:['молоко','кефир','творог','сыр','масло сливочное','йогурт','сметана','ряженка','молочная продукция','сливки','мороженое'],
     status:'yes', label:'Честный знак', note:'Маркировка обязательна (молочная продукция)'},
    {kw:['велосипед','электровелосипед','самокат','электросамокат'],
     status:'yes', label:'Честный знак', note:'Маркировка обязательна (велосипеды и самокаты)'},
    {kw:['пиво','пивной напиток','слабоалкогольный','сидр','медовуха'],
     status:'yes', label:'Честный знак', note:'Маркировка обязательна (пиво и слабоалкогольные)'},
    {kw:['вода питьевая','минеральная вода','газированная вода','питьевая вода'],
     status:'yes', label:'Честный знак', note:'Маркировка обязательна (вода)'},
    {kw:['бад','биологически активная добавка','витамины','dietary supplement'],
     status:'yes', label:'Честный знак', note:'Маркировка обязательна (БАД)'},
    {kw:['табак','сигареты','папиросы','табачные изделия','сигара','никотин'],
     status:'yes', label:'Честный знак', note:'Маркировка обязательна (табачные изделия)'},
    {kw:['инвалидная коляска','кресло-коляска','кресло коляска'],
     status:'yes', label:'Честный знак', note:'Маркировка обязательна (кресла-коляски)'},
    {kw:['фотоаппарат','цифровой фотоаппарат','мыльница'],
     status:'yes', label:'Честный знак', note:'Маркировка обязательна (фотокамеры)'},
    {kw:['антисептик','дезинфектор','санитайзер'],
     status:'yes', label:'Честный знак', note:'Маркировка обязательна (антисептики)'},
  ];

  // Сертификация: [keywords, type, note]
  // type: 'cert'=сертификат, 'decl'=декларация, 'no'=не требуется обязательная
  var CERT_DB = [
    // Требуется СЕРТИФИКАТ (3я сторона)
    {kw:['игрушка','детская игрушка','конструктор','кукла','мягкая игрушка','плюшевый','погремушка'],
     type:'cert', note:'Сертификат соответствия обязателен (детские товары, ТР ТС 008/2011)'},
    {kw:['детская одежда','одежда для детей','детский костюм','школьная форма','ползунки','боди детский','распашонка'],
     type:'cert', note:'Сертификат соответствия обязателен (детская одежда, ТР ТС 007/2011)'},
    {kw:['детская обувь','обувь для детей','пинетки','ботинки детские'],
     type:'cert', note:'Сертификат соответствия обязателен (детская обувь, ТР ТС 007/2011)'},
    {kw:['автокресло','детское автокресло','бустер'],
     type:'cert', note:'Сертификат соответствия обязателен (автокресла, ТР ТС 018/2011)'},
    {kw:['каска','каска строительная','защитный шлем','шлем рабочий'],
     type:'cert', note:'Сертификат соответствия обязателен (СИЗ головы, ТР ТС 019/2011)'},
    {kw:['респиратор','маска защитная','защитная маска','маска от пыли'],
     type:'cert', note:'Сертификат соответствия обязателен (СИЗ органов дыхания, ТР ТС 019/2011)'},
    {kw:['детская коляска','коляска для новорождённых','коляска прогулочная','люлька коляска'],
     type:'cert', note:'Сертификат соответствия обязателен (товары для детей, ТР ТС 008/2011)'},
    {kw:['пиротехника','фейерверк','петарды','бенгальские огни'],
     type:'cert', note:'Сертификат соответствия обязателен (пиротехника, ТР ТС 006/2011)'},
    {kw:['удлинитель','сетевой фильтр','удлинитель с заземлением','разветвитель розеток'],
     type:'cert', note:'Сертификат соответствия обязателен (низковольтное оборудование, ТР ТС 004/2011)'},
    {kw:['детская мебель','кроватка детская','манеж детский','стульчик для кормления'],
     type:'cert', note:'Сертификат соответствия обязателен (детская мебель, ТР ТС 008/2011)'},
    // Требуется ДЕКЛАРАЦИЯ
    {kw:['диван','кресло','кровать','шкаф','стеллаж','тумба','трюмо','комод','буфет','гарнитур мебельный'],
     type:'decl', note:'Декларация соответствия обязательна (мебель, ТР ЕАЭС 044/2017)'},
    {kw:['стол','стул','табурет','пуфик','оттоманка','столик'],
     type:'decl', note:'Декларация соответствия обязательна (мебель, ТР ЕАЭС 044/2017)'},
    {kw:['холодильник','морозильник','морозильная камера'],
     type:'decl', note:'Декларация соответствия обязательна (бытовая техника, ТР ТС 004/2011)'},
    {kw:['стиральная машина','машинка стиральная','сушильная машина'],
     type:'decl', note:'Декларация соответствия обязательна (бытовая техника, ТР ТС 004/2011)'},
    {kw:['телевизор','телевизионный приёмник','тв приставка','смарт тв'],
     type:'decl', note:'Декларация соответствия обязательна (электронное оборудование, ТР ТС 020/2011)'},
    {kw:['ноутбук','планшет','компьютер','моноблок','нетбук'],
     type:'decl', note:'Декларация соответствия обязательна (ЭМС и Low Voltage, ТР ТС 004/2011)'},
    {kw:['смартфон','телефон мобильный','мобильный телефон','мобильник'],
     type:'decl', note:'Декларация соответствия обязательна (радиооборудование, ТР ТС 020/2011)'},
    {kw:['пылесос','робот-пылесос','паровой пылесос'],
     type:'decl', note:'Декларация соответствия обязательна (бытовая техника, ТР ТС 004/2011)'},
    {kw:['микроволновка','микроволновая печь','свч печь'],
     type:'decl', note:'Декларация соответствия обязательна (бытовая техника, ТР ТС 004/2011)'},
    {kw:['утюг','парогенератор','отпариватель'],
     type:'decl', note:'Декларация соответствия обязательна (бытовая техника, ТР ТС 004/2011)'},
    {kw:['кондиционер','сплит-система','сплит система'],
     type:'decl', note:'Декларация соответствия обязательна (бытовая техника, ТР ТС 004/2011)'},
    {kw:['крем','шампунь','гель для душа','лосьон','маска косметическая','тоник','пенка','скраб','бальзам','кондиционер для волос','туш','помада','тени','пудра косметическая'],
     type:'decl', note:'Декларация соответствия обязательна (косметика, ТР ТС 009/2011)'},
    {kw:['одежда','футболка','брюки','джинсы','юбка','платье','рубашка','толстовка','свитер','кофта','носки','нижнее бельё','трусы','бюстгальтер','майка','лосины','леггинсы'],
     type:'decl', note:'Декларация соответствия обязательна (лёгкая промышленность, ТР ТС 017/2011)'},
    {kw:['обувь','кроссовки','ботинки','сапоги','туфли','сандали','кеды'],
     type:'decl', note:'Декларация соответствия обязательна (обувь, ТР ТС 017/2011)'},
    {kw:['посуда','тарелка','кастрюля','сковорода','кружка','стакан','миска','кружка','ложка','вилка','нож столовый'],
     type:'decl', note:'Декларация соответствия обязательна (посуда, ТР ТС 005/2011)'},
    {kw:['светильник','лампа','люстра','бра','торшер','прожектор','фонарь'],
     type:'decl', note:'Декларация соответствия обязательна (световые приборы, ТР ТС 004/2011)'},
    {kw:['зарядное устройство','зарядка','блок питания','адаптер питания','павербанк','power bank'],
     type:'decl', note:'Декларация соответствия обязательна (Low Voltage, ТР ТС 004/2011)'},
    // Не требуется обязательная (примеры)
    {kw:['сувенир','статуэтка','декор','вазочка','рамка для фото','картина'],
     type:'no', note:'Обязательная сертификация, как правило, не требуется (декор/сувениры)'},
    {kw:['канцелярия','ручка','карандаш','блокнот','тетрадь','папка','скрепки','степлер'],
     type:'no', note:'Обязательная сертификация, как правило, не требуется (канцелярия)'},
  ];

  // Функция поиска
  function fcCheckProduct(name) {
    var n = name.toLowerCase().trim();
    if (!n || n.length < 3) return null;
    var result = { cz: null, cert: null };

    // Проверяем Честный знак
    for (var i = 0; i < CZ_DB.length; i++) {
      var entry = CZ_DB[i];
      for (var j = 0; j < entry.kw.length; j++) {
        if (n.includes(entry.kw[j])) {
          result.cz = entry;
          break;
        }
      }
      if (result.cz) break;
    }

    // Проверяем сертификацию
    for (var i = 0; i < CERT_DB.length; i++) {
      var entry = CERT_DB[i];
      for (var j = 0; j < entry.kw.length; j++) {
        if (n.includes(entry.kw[j])) {
          result.cert = entry;
          break;
        }
      }
      if (result.cert) break;
    }

    return result;
  }

  var CATS = {
    A1: {markup:0.15, sign:'1 контейнер, ИТК ищет фабрику', formula:'(База×1,15)×1,03', group:'AB'},
    A2: {markup:0.10, sign:'~1 контейнер/мес., ИТК ищет фабрику', formula:'(База×1,10)×1,03', group:'AB'},
    A3: {markup:0.05, sign:'Регулярные заказы, ИТК ищет фабрику', formula:'(База×1,05)×1,03', group:'AB'},
    B1: {markup:0.10, sign:'До 1 контейнера в 3 мес.', formula:'(База×1,10)×1,03', group:'AB'},
    B2: {markup:0.05, sign:'1+ контейнеров/мес.', formula:'(База×1,05)×1,03', group:'AB'},
    C0: {coef:2.0, goodsMarkup:0.25, sign:'Лог.×2,0 + товар 25% + 3%', formula:'(Б1+Б2×2,0)×1,03', group:'C'},
    C1: {coef:2.0, goodsMarkup:0.15, sign:'Лог.×2,0 + товар 15% + 3%', formula:'(Б1+Б2×2,0)×1,03', group:'C'},
    C2: {coef:1.8, goodsMarkup:0.15, sign:'Лог.×1,8 + товар 15% + 3%', formula:'(Б1+Б2×1,8)×1,03', group:'C'},
    C3: {coef:1.5, goodsMarkup:0.15, sign:'Лог.×1,5 + товар 15% + 3%', formula:'(Б1+Б2×1,5)×1,03', group:'C'},
    /* АВТОПЕРЕВОЗКА (фура вместо железной дороги). Доставка считается не долей
       от контейнера, а по объёму: ставка за 1 м³. Процент — наценка на доставку;
       товар идёт без наценки. Остальное как у сборного груза. */
    D5:  {autoMarkup:0.05, sign:'FOB · фура · наценка 5% на товар и на доставку + 3% перевод',  formula:'(Б1×1,05 + м³×ставка×1,05) × 1,03', group:'AUTO'},
    D10: {autoMarkup:0.10, sign:'FOB · фура · наценка 10% на товар и на доставку + 3% перевод', formula:'(Б1×1,10 + м³×ставка×1,10) × 1,03', group:'AUTO'},
    D15: {autoMarkup:0.15, sign:'FOB · фура · наценка 15% на товар и на доставку + 3% перевод', formula:'(Б1×1,15 + м³×ставка×1,15) × 1,03', group:'AUTO'},
    D25: {autoMarkup:0.25, sign:'FOB · фура · наценка 25% на товар и на доставку + 3% перевод', formula:'(Б1×1,25 + м³×ставка×1,25) × 1,03', group:'AUTO'},
  };

  var items = [];
  var nextId = 1;

  function fmt(n, d) {
    d = d === undefined ? 0 : d;
    return n.toLocaleString('ru-RU', {minimumFractionDigits:d, maximumFractionDigits:d});
  }

  function g(id) { return document.getElementById(id); }
  function gv(id) { return parseFloat(g(id).value) || 0; }

  /* ВЫБОР КАТЕГОРИИ — своё окно вместо системного списка.
     Системный список рисует телефон: там нельзя показать расшифровку формулы
     под названием, а менеджеры как раз не понимали, из чего складывается цена.
     Здесь скрытое поле с тем же id (всё, что читало значение, читает его
     по-прежнему) плюс кнопка, открывающая окно с плитками. */
  var FC_CAT_LIST = [
    {code:'A1', title:'A1 — Новый клиент (15%)'},
    {code:'A2', title:'A2 — Активный клиент (10%)'},
    {code:'A3', title:'A3 — Постоянный клиент (5%)'},
    {code:'B1', title:'B1 — Своя фабрика (старт) (10%)'},
    {code:'B2', title:'B2 — Своя фабрика (активный) (5%)'},
    {code:'C0', title:'C0 — Сборный, особые условия (лог.×2,0 + тов.25%)'},
    {code:'C1', title:'C1 — Сборный, малый объём (лог.×2,0 + тов.15%)'},
    {code:'C2', title:'C2 — Сборный, средний объём (лог.×1,8 + тов.15%)'},
    {code:'C3', title:'C3 — Сборный, полконтейнера+ (лог.×1,5 + тов.15%)'},
    {code:'D5', title:'Автодоставка FOB 5% (фура, ставка за м³)'},
    {code:'D10', title:'Автодоставка FOB 10% (фура, ставка за м³)'},
    {code:'D15', title:'Автодоставка FOB 15% (фура, ставка за м³)'},
    {code:'D25', title:'Автодоставка FOB 25% (фура, ставка за м³)'}
  ];
  var FC_CAT_GROUPS = [
    {name:'Прямые поставки', kind:'direct', codes:['A1','A2','A3','B1','B2']},
    {name:'Сборный груз (железная дорога)', kind:'rail', codes:['C0','C1','C2','C3']},
    {name:'Автодоставка FOB (фура)', kind:'auto', codes:['D5','D10','D15','D25']}
  ];
  function fcCatTitle(code){
    for (var i=0;i<FC_CAT_LIST.length;i++) if (FC_CAT_LIST[i].code===code) return FC_CAT_LIST[i].title;
    return code;
  }
  function fcCatPickOpen(){
    var cur = g('fc-cat').value;
    var rows = '';
    FC_CAT_GROUPS.forEach(function(gr){
      rows += '<div class="catp-grp grp-'+gr.kind+'">'+gr.name+'</div>';
      gr.codes.forEach(function(code){
        var cat = CATS[code]; if (!cat) return;
        var on = (code === cur);
        rows += '<button type="button" class="catp-row grp-'+gr.kind+(on?' on':'')+'" data-cat="'+code+'">'
             +    '<span class="catp-box">'+(on?'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>':'')+'</span>'
             +    '<span class="catp-tx">'
             +      '<span class="catp-n">'+fcCatTitle(code)+'</span>'
             +      '<span class="catp-d">'+cat.sign+'</span>'
             +      '<span class="catp-f">'+cat.formula+'</span>'
             +    '</span></button>';
      });
    });
    var ov = document.createElement('div');
    ov.className = 'catp-ov';
  ov.setAttribute('data-eco-lock','1');
    ov.innerHTML = '<div class="catp-box2"><div class="catp-head"><span>Категория сделки</span>'
                 + '<button type="button" class="catp-x">×</button></div>'
                 + '<div class="catp-list">'+rows+'</div>'
                 + '<div class="catp-legend">Цвет слева — вид доставки: <b style="color:#112C93">синий</b> прямые · <b style="color:#6A3FA0">фиолетовый</b> железная дорога · <b style="color:#C2610A">оранжевый</b> фура<br>Б1 — товар с пошлиной и НДС · Б2 — логистика · 1,03 — 3% за перевод денег в Китай</div></div>';
    document.body.appendChild(ov);
  if (window.ecoLockScroll) window.ecoLockScroll(true);
  /* Окно — отдельный шаг для кнопки «Назад»: первое нажатие закрывает его,
     а не уводит с экрана. */
  /* шаг истории не добавляем: «Назад» разбирает окна сама */
  var close = function(){
    if (ov.parentNode) ov.parentNode.removeChild(ov);
    if (window.ecoLockScroll) window.ecoLockScroll(false);
  };
    ov.addEventListener('click', function(e){ if (e.target === ov) close(); });
    ov.querySelector('.catp-x').addEventListener('click', close);
    var rws = ov.querySelectorAll('.catp-row');
    for (var k=0;k<rws.length;k++) (function(b){
      b.addEventListener('click', function(){
        var code = b.getAttribute('data-cat');
        g('fc-cat').value = code;
        var lb = g('fc-cat-label'); if (lb) lb.textContent = fcCatTitle(code);
        close(); updateCatDesc(); fcCalc();
      });
    })(rws[k]);
  }

  /* Раскраска полей по смыслу. Список ролей задан явно, чтобы новое поле не
     осталось без метки незаметно — если id нет в списке, поле просто будет
     без полоски. */
  var FLD_ROLE = {
    money: ['goods','fc-china','fc-export','ed-china','ed-export'],
    vol:   ['fc-container','volume','cbm','ed-container'],
    wt:    ['fc-containerWt','weight','kg','ed-containerWt'],
    log:   ['freight','fc-freight','fc-cbmrate','fc-payrate','fc-payfee',
            'fc-station','station','fc-broker','broker','fc-delivery','delivery',
            'fc-cityname','fc-cityrate',                       // доставка до города
            'ed-freight','ed-cbmrate','ed-payrate','ed-payfee','ed-station',
            'ed-broker','ed-delivery','ed-cityname','ed-cityrate'],
    tax:   ['duty','fc-duty','vat','fc-vat','fc-transfer','transfer','fc-ins','kursCny','kursUsd','fc-cny','fc-usd',
            'ed-duty','ed-vat','ed-transfer','ed-ins','ed-cny','ed-usd']
  };
  function fcPaintFields(){
    try{
      Object.keys(FLD_ROLE).forEach(function(role){
        FLD_ROLE[role].forEach(function(id){
          var e = document.getElementById(id);
          if (!e) return;
          var row = e.closest('.input-row') || e.closest('.field-row');
          if (!row) return;
          row.className = row.className.replace(/\s*fld-\w+/g,'') + ' fld-' + role;
        });
      });
    }catch(e){}
  }
  setTimeout(fcPaintFields, 0);
  window.fcPaintFields = fcPaintFields;

  /* ===== КОММЕРЧЕСКОЕ ПРЕДЛОЖЕНИЕ ДЛЯ КЛИЕНТА =====
     В КП уходит только то, что клиенту положено видеть: наименование,
     количество, цена за штуку и сумма. Внутренняя кухня — курсы, фрахт,
     наценки, категория — не попадает сюда никогда. */
  /* НАБОРОВ РЕКВИЗИТОВ МОЖЕТ БЫТЬ НЕСКОЛЬКО: у компании не одно юрлицо и не
     один банк. Храним список, менеджер выбирает нужный перед созданием КП.
     В списке короткая строка «ООО ИТК · Уралсиб», внутри — всё целиком. */
  var KP_REQ_KEY = 'itc_kp_reqs', KP_REQ_CUR = 'itc_kp_req_cur', KP_NO_KEY = 'itc_kp_no';
  function kpNextNo(){
    var n = parseInt(localStorage.getItem(KP_NO_KEY) || '100000', 10);
    n = (isNaN(n) ? 100000 : n) + 1;
    if (n > 999999) n = 100001;          // номер всегда шестизначный
    localStorage.setItem(KP_NO_KEY, String(n));
    return n;
  }
  function kpDefaultReqs(){
    return [{
      short:'ООО ИТК · Уралсиб',
      name:'ООО «Интернациональная Торговая Компания»', inn:'9701313336', kpp:'', ogrn:'',
      addr:'109380, г. Москва, ул. Чагинская, д. 4, стр. 13, оф. 14/4',
      phone:'+7 (978) 500-44-02', mail:'info@itcpartner.ru', site:'itcpartner.ru',
      bank:'Филиал «Южный» ПАО «Банк Уралсиб», г. Краснодар',
      bik:'040349700', acc:'40702810347890000147', corr:'30101810400000000700',
      boss:'Генеральный директор'   // без фамилии — по указанию Алексея 13.08.2026
    }];
  }
  function kpReqs(){
    try {
      var a = JSON.parse(localStorage.getItem(KP_REQ_KEY) || 'null');
      if (a && a.length) return a;
      /* Перенос со старой версии, где набор был один. */
      var one = JSON.parse(localStorage.getItem('itc_kp_req') || 'null');
      if (one) { one.short = one.short || 'Мои реквизиты'; return [one]; }
    } catch(e){}
    return kpDefaultReqs();
  }
  function kpReqsSave(a){ try{ localStorage.setItem(KP_REQ_KEY, JSON.stringify(a)); }catch(e){} }
  function kpCurIdx(){
    var i = parseInt(localStorage.getItem(KP_REQ_CUR) || '0', 10);
    var a = kpReqs();
    return (isNaN(i) || i < 0 || i >= a.length) ? 0 : i;
  }
  function kpSetCur(i){ try{ localStorage.setItem(KP_REQ_CUR, String(i)); }catch(e){} }
  function kpReq(){ return kpReqs()[kpCurIdx()] || kpDefaultReqs()[0]; }
  function kpShortOf(r){
    if (r.short) return r.short;
    var b = (r.bank||'').replace(/[«»"]/g,'');
    var m = b.match(/Уралсиб|Точка|Сбер\w*|ВТБ|Альфа\w*|Тинькофф|Райффайзен|Открытие|ПСБ|Совкомбанк/i);
    return (r.name||'Реквизиты').replace(/ООО\s*«?([^»]{0,24}).*/,'ООО $1') + (m ? ' · '+m[0] : '');
  }
  function kpEsc(v){ return String(v==null?'':v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  /* Собираем строки КП из позиций расчёта: цена за штуку — это итог позиции,
     делённый на количество, то есть уже со всеми расходами и наценкой. */
  function kpRows(){
    var rows = [], total = 0;
    document.querySelectorAll('#fc-results .fc-res-item').forEach(function(){});
    (window.FC_LAST_ROWS || []).forEach(function(r){
      var qty = r.qty > 0 ? r.qty : 1;
      rows.push({ name: r.name || 'Позиция', qty: qty,
                  per: r.totalRub / qty, sum: r.totalRub });
      total += r.totalRub;
    });
    return { rows: rows, total: total };
  }

  window.kpOpen = function(){
    var data = kpRows();
    if (!data.rows.length) { alert('Сначала внесите позиции и получите расчёт'); return; }
    var req = kpReq();
    var no  = kpNextNo();
    var d   = new Date();
    var dateStr = ('0'+d.getDate()).slice(-2)+'.'+('0'+(d.getMonth()+1)).slice(-2)+'.'+d.getFullYear();
    var noBank = !req.bank || !req.acc || !req.bik;

    var tr = data.rows.map(function(r,i){
      return '<tr><td class="n">'+(i+1)+'</td><td>'+kpEsc(r.name)+'</td>'
           + '<td class="n">'+fmt(r.qty,0)+'</td>'
           + '<td class="n">'+fmt(r.per,0)+' ₽</td>'
           + '<td class="n"><b>'+fmt(r.sum,0)+' ₽</b></td></tr>';
    }).join('');

    var doc = '<div class="kp-doc" id="kp-doc">'
      + '<div class="kp-head"><div class="kp-logo"><img src="/eco/itc-logo.png" alt="ITC International Trading Company"></div>'
      +   '<div class="kp-co"><b>'+kpEsc(req.name)+'</b>'
      +     '<span>' + (req.inn ? 'ИНН '+kpEsc(req.inn) : '') + (req.kpp ? ' · КПП '+kpEsc(req.kpp) : '')
      +       (req.ogrn ? ' · ОГРН '+kpEsc(req.ogrn) : '') + '</span>'
      +     (req.addr ? '<span>'+kpEsc(req.addr)+'</span>' : '')
      +     '<span>'+kpEsc(req.phone)+' · '+kpEsc(req.mail)+' · '+kpEsc(req.site)+'</span>'
      +   '</div></div>'
      + '<div class="kp-title">Коммерческое предложение № '+no+'</div>'
      + '<div class="kp-meta">от '+dateStr+'</div>'
      + (window.KP_CLIENT ? '<div class="kp-meta">Кому: <b>'+kpEsc(window.KP_CLIENT.company || window.KP_CLIENT.alias)+'</b>'
          + (window.KP_CLIENT.inn ? ' · ИНН '+kpEsc(window.KP_CLIENT.inn) : '') + '</div>' : '')
      + '<table class="kp-tbl"><thead><tr><th>№</th><th>Наименование</th><th>Кол-во, шт</th>'
      +   '<th>Цена за шт</th><th>Сумма</th></tr></thead><tbody>'+tr+'</tbody></table>'
      + '<div class="kp-total"><span>Итого к оплате</span><b>'+fmt(data.total,0)+' ₽</b></div>'
      + '<div class="kp-note">Цена указана в рублях и включает стоимость товара, доставку до Москвы, '
      +   'таможенное оформление, пошлину и НДС. Срок поставки и условия отгрузки согласовываются отдельно. '
      +   'Предложение действительно 7 календарных дней.</div>'
      + '<div class="kp-req"><b>Реквизиты для оплаты</b><br>'
      +   kpEsc(req.name) + (req.inn ? ', ИНН '+kpEsc(req.inn) : '') + (req.kpp ? ', КПП '+kpEsc(req.kpp) : '') + '<br>'
      +   (req.bank ? 'Банк: '+kpEsc(req.bank)+'<br>' : '')
      +   (req.acc  ? 'Расчётный счёт: '+kpEsc(req.acc)+'<br>' : '')
      +   (req.corr ? 'Корр. счёт: '+kpEsc(req.corr)+'<br>' : '')
      +   (req.bik  ? 'БИК: '+kpEsc(req.bik) : '')
      + '</div>'
      + (req.boss ? '<div style="margin-top:22px;display:flex;justify-content:space-between;align-items:flex-end;font-size:12.5px">'
          + '<span>'+kpEsc(req.boss)+'</span>'
          + '<span style="border-bottom:1px solid #9aabcc;min-width:150px;text-align:center;color:#9aabcc;font-size:11px">подпись, печать</span></div>' : '')
      + (noBank ? '<div class="kp-warn">Банковские реквизиты не заполнены — нажмите «Реквизиты» и внесите их один раз. Пока они пустые, отправлять КП клиенту нельзя.</div>' : '')
      + '</div>';

    /* Запоминаем всё, что нужно для PDF: номер, дату, позиции и реквизиты. */
    _kpLast = { no: no, dateStr: dateStr, data: data, req: req };
    var ov = document.createElement('div');
    ov.className = 'kp-ov';
    ov.innerHTML = '<div class="kp-box">'
      + '<div class="kp-bar">'
      +   '<button type="button" class="x" data-kp="close">✕</button>'
      +   '<button type="button" data-kp="req">Реквизиты: '+kpEsc(kpShortOf(req))+'</button>'
      +   '<button type="button" data-kp="client">'+(window.KP_CLIENT ? 'Клиент: '+kpEsc(window.KP_CLIENT.title) : 'Выбрать клиента')+'</button>'
      +   '<button type="button" data-kp="save">Сохранить КП</button>'
      +   '<button type="button" data-kp="print">Сохранить PDF</button>'
      +   '<button type="button" data-kp="check" title="Проверка">?</button>'
      +   '<button type="button" class="main" data-kp="share">Отправить</button>'
      + '</div>' + doc + '</div>';
    document.body.appendChild(ov);
    /* шаг истории не добавляем: «Назад» разбирает окна сама */
    var close = function(){ if (ov.parentNode) ov.parentNode.removeChild(ov);
       };

    ov.addEventListener('click', function(e){
      if (e.target === ov) { close(); return; }
      var b = e.target.closest ? e.target.closest('[data-kp]') : null;
      if (!b) return;
      var act = b.getAttribute('data-kp');
      if (act === 'close') { close(); return; }
      if (act === 'print') { kpPrint(no, ov); return; }
      if (act === 'check') { kpSelfTest(); return; }
      if (act === 'req')   { close(); kpEditReq(); return; }
      if (act === 'client'){ close(); window.kpPickClient(function(){ window.kpOpen(); }); return; }
      if (act === 'save')  { kpSaveToHistory(no, dateStr, data, req); return; }
      if (act === 'share') { kpShare(no, dateStr, data); return; }
    });
  };

  /* ПЕЧАТЬ И PDF. Внутри приложения печать средствами страницы часто просто
     не срабатывает — окна печати там нет. Поэтому собираем готовый файл со
     всем оформлением и отдаём его: он открывается в браузере, а оттуда уже
     печатается или сохраняется в PDF штатными средствами телефона. */
  /* СОЗДАНИЕ PDF. Печать средствами страницы внутри приложения не работает —
     системного окна печати там нет. Поэтому собираем PDF сами и сохраняем
     файлом: его можно открыть, отправить или распечатать откуда угодно.
     Шрифт с кириллицей встроен, иначе в PDF вместо русских букв квадраты. */
  var _kpLast = null;
  function kpPrintLast(){ if (_kpLast) kpMakePdf(_kpLast); }
  function kpPrint(no, ov){ kpMakePdf(_kpLast); }

  /* Разряды в русском формате разделяются НЕРАЗРЫВНЫМ пробелом, а его нет в
     урезанном шрифте — из-за этого «44 521 ₽» обрывалось до «44». Меняем такие
     пробелы на обычные перед выводом в PDF. */
  function pdfT(v){
    return String(v == null ? '' : v)
      .replace(/[\u00A0\u2007\u202F\u2009]/g, ' ')
      .replace(/\u2011/g, '-');
  }
  function pdfMoney(n, cur){ return pdfT(fmt(n, 0)) + ' ' + (cur || '₽'); }

  /* Логотип для PDF: подгружаем один раз и держим в памяти. */
  var _kpLogo = null;
  function kpLogoLoad(){
    if (_kpLogo !== null) return Promise.resolve(_kpLogo);
    return fetch('/eco/itc-logo.png').then(function(r){ return r.blob(); })
      .then(function(b){
        return new Promise(function(res){
          var fr = new FileReader();
          fr.onload = function(){ _kpLogo = fr.result; res(_kpLogo); };
          fr.onerror = function(){ _kpLogo = ''; res(''); };
          fr.readAsDataURL(b);
        });
      }).catch(function(){ _kpLogo = ''; return ''; });
  }

  function kpMakePdf(ctx){
    kpLogoLoad().then(function(){ kpMakePdfNow(ctx); });
  }
  function kpMakePdfNow(ctx){
    if (!ctx) { alert('Сначала откройте КП'); return; }
    /* Говорим прямо, чего не хватает: иначе кнопка молчит и непонятно, то ли
       файлы не залиты, то ли ошибка в коде. */
    var J = window.jspdf && window.jspdf.jsPDF;
    if (!J) { alert('Не найден файл itc-pdf.js в папке eco на сайте. Залейте его и перезапустите приложение.'); return; }
    if (!window.ITC_FONTS) { alert('Не найден файл itc-pdf-font.js в папке eco на сайте. Залейте его и перезапустите приложение.'); return; }
    try {
    var d = new J({ unit:'mm', format:'a4' });
    d.addFileToVFS('itc.ttf', window.ITC_FONTS.normal);
    d.addFont('itc.ttf', 'itc', 'normal');
    d.addFileToVFS('itc-b.ttf', window.ITC_FONTS.bold);
    d.addFont('itc-b.ttf', 'itc', 'bold');
    d.setFont('itc', 'normal');

    var L = 15, R = 195, y = 16;
    var req = ctx.req, rows = ctx.data.rows, no = ctx.no;

    /* Шапка: слева логотип, справа реквизиты — тот же вид, что на экране. */
    var tx = L, wide = R - L;
    if (_kpLogo) {
      try {
        var lw = 42, lh = lw / 2.57;      // пропорции знака 2,57:1
        d.addImage(_kpLogo, 'PNG', L, y - 4, lw, lh);
        tx = L + lw + 6; wide = R - tx;
      } catch(e) {}
    }
    d.setFont('itc','bold'); d.setFontSize(11); d.setTextColor(17,44,147);
    var nm = d.splitTextToSize(pdfT(req.name || ''), wide);
    nm.forEach(function(l){ d.text(l, tx, y); y += 5; });
    y += 1;
    d.setFont('itc','normal'); d.setFontSize(8); d.setTextColor(91,100,114);
    var head = [];
    if (req.inn) head.push('ИНН ' + req.inn + (req.kpp ? ' · КПП ' + req.kpp : ''));
    if (req.addr) head.push(req.addr);
    head.push([req.phone, req.mail, req.site].filter(Boolean).join(' · '));
    head.forEach(function(t){ d.splitTextToSize(pdfT(t), wide).forEach(function(l){ d.text(l, tx, y); y += 4; }); });
    if (_kpLogo && y < 32) y = 32;        // не наезжаем на знак

    y += 2; d.setDrawColor(17,44,147); d.setLineWidth(0.8); d.line(L, y, R, y); y += 9;

    d.setFont('itc','bold'); d.setFontSize(16); d.setTextColor(17,44,147);
    d.text(pdfT('Коммерческое предложение № ' + no), L, y); y += 6;
    d.setFont('itc','normal'); d.setFontSize(9); d.setTextColor(91,100,114);
    d.text('от ' + ctx.dateStr, L, y); y += 8;

    // таблица позиций
    var cx = [L, L+10, L+95, L+130, R];      // границы колонок
    d.setFillColor(17,44,147); d.rect(L, y-4.5, R-L, 7, 'F');
    d.setFont('itc','bold'); d.setFontSize(8); d.setTextColor(255,255,255);
    d.text('№', cx[0]+2, y); d.text('Наименование', cx[1]+2, y);
    d.text('Кол-во', cx[2]+2, y); d.text('Цена за шт', cx[3]+2, y);
    d.text('Сумма', R-2, y, { align:'right' });
    y += 6.5;

    d.setFont('itc','normal'); d.setFontSize(9); d.setTextColor(26,26,26);
    rows.forEach(function(r, i){
      var nmL = d.splitTextToSize(pdfT(r.name || ('Позиция ' + (i+1))), 78);
      var h = Math.max(6, nmL.length * 4.4 + 1.6);
      if (y + h > 275) { d.addPage(); y = 20; }
      if (i % 2) { d.setFillColor(250,251,254); d.rect(L, y-4.2, R-L, h, 'F'); }
      d.text(String(i+1), cx[0]+2, y);
      nmL.forEach(function(l, k){ d.text(l, cx[1]+2, y + k*4.4); });
      d.text(pdfT(fmt(r.qty,0)), cx[2]+2, y);
      d.text(pdfMoney(r.per), cx[3]+2, y);
      d.setFont('itc','bold'); d.text(pdfMoney(r.sum), R-2, y, { align:'right' }); d.setFont('itc','normal');
      y += h;
      d.setDrawColor(224,230,240); d.setLineWidth(0.2); d.line(L, y-4.2, R, y-4.2);
    });

    // итог
    y += 4;
    d.setFillColor(238,241,251); d.rect(L, y-5, R-L, 11, 'F');
    d.setFillColor(17,44,147); d.rect(L, y-5, 1.4, 11, 'F');
    d.setFont('itc','normal'); d.setFontSize(10); d.setTextColor(26,26,26);
    d.text('Итого к оплате', L+5, y+1.5);
    d.setFont('itc','bold'); d.setFontSize(14); d.setTextColor(17,44,147);
    d.text(pdfMoney(ctx.data.total), R-2, y+2, { align:'right' });
    y += 14;

    d.setFont('itc','normal'); d.setFontSize(8); d.setTextColor(91,100,114);
    d.splitTextToSize('Цена указана в рублях и включает стоимость товара, доставку до Москвы, '
      + 'таможенное оформление, пошлину и налог на добавленную стоимость. Срок поставки и условия '
      + 'отгрузки согласовываются отдельно. Предложение действительно 7 календарных дней.', R-L)
      .forEach(function(l){ d.text(l, L, y); y += 4; });

    // реквизиты
    y += 4;
    if (y > 235) { d.addPage(); y = 20; }
    var rq = [];
    rq.push(req.name + (req.inn ? ', ИНН ' + req.inn : '') + (req.kpp ? ', КПП ' + req.kpp : ''));
    if (req.bank) rq.push('Банк: ' + req.bank);
    if (req.acc)  rq.push('Расчётный счёт: ' + req.acc);
    if (req.corr) rq.push('Корреспондентский счёт: ' + req.corr);
    if (req.bik)  rq.push('БИК: ' + req.bik);
    var bh = rq.length * 4.4 + 9;
    d.setFillColor(250,251,254); d.setDrawColor(224,230,240); d.setLineWidth(0.3);
    d.rect(L, y-4, R-L, bh, 'FD');
    d.setFont('itc','bold'); d.setFontSize(8.5); d.setTextColor(17,44,147);
    d.text('Реквизиты для оплаты', L+3, y); y += 5;
    d.setFont('itc','normal'); d.setTextColor(26,26,26);
    rq.forEach(function(t){ d.text(pdfT(t), L+3, y); y += 4.4; });
    y += 8;

    if (req.boss) {
      d.setFontSize(9); d.setTextColor(26,26,26);
      d.text(pdfT(req.boss), L, y);
      d.setDrawColor(154,171,204); d.setLineWidth(0.3); d.line(R-55, y+0.5, R, y+0.5);
      d.setFontSize(7.5); d.setTextColor(154,171,204);
      d.text('подпись, печать', R-27, y+4, { align:'center' });
    }

    var name = 'KP-' + no + '.pdf';
    var blob = d.output('blob');
    kpDeliverPdf(blob, name, d);
    } catch (err) {
      alert('Не удалось собрать PDF: ' + (err && err.message ? err.message : err));
    }
  }

  /* САМОПРОВЕРКА. Показывает по пунктам, что доступно, — чтобы не гадать,
     почему не сохраняется файл, а видеть причину. */
  window.kpSelfTest = function(){
    var L = [];
    var J = window.jspdf && window.jspdf.jsPDF;
    L.push((J ? '✓' : '✗') + ' файл itc-pdf.js ' + (J ? 'загружен' : 'НЕ НАЙДЕН в папке eco'));
    L.push((window.ITC_FONTS ? '✓' : '✗') + ' файл itc-pdf-font.js ' + (window.ITC_FONTS ? 'загружен' : 'НЕ НАЙДЕН в папке eco'));
    var okBlob = false; try { okBlob = !!new Blob(['x']); } catch(e) {}
    L.push((okBlob ? '✓' : '✗') + ' сборка файла в памяти');
    var okUrl = false; try { var u = URL.createObjectURL(new Blob(['x'])); URL.revokeObjectURL(u); okUrl = true; } catch(e) {}
    L.push((okUrl ? '✓' : '✗') + ' ссылка на файл');
    var a = document.createElement('a');
    L.push(('download' in a ? '✓' : '✗') + ' скачивание файлов');
    L.push((navigator.share ? '✓' : '✗') + ' системная панель «Поделиться»');
    L.push((window.AndroidShare ? '✓' : '✗') + ' мост приложения AndroidShare');
    if (J && window.ITC_FONTS) {
      try {
        var d = new J({ unit:'mm', format:'a4' });
        d.addFileToVFS('t.ttf', window.ITC_FONTS.normal);
        d.addFont('t.ttf','t','normal'); d.setFont('t','normal');
        d.text('проверка', 10, 10);
        var b = d.output('blob');
        L.push('✓ пробный PDF собран, ' + Math.round(b.size/1024) + ' КБ');
      } catch(e) {
        L.push('✗ сборка PDF: ' + (e && e.message ? e.message : e));
      }
    }
    alert('Проверка PDF\n\n' + L.join('\n'));
  };

  /* ВЫДАЧА ГОТОВОГО PDF. Внутри приложения обычное скачивание файла часто
     запрещено, поэтому пробуем по очереди: отдать файл в системную панель
     «Поделиться» (оттуда его можно сохранить или отправить), затем обычное
     скачивание, и в любом случае показываем документ прямо в окне со ссылкой —
     чтобы человек не остался ни с чем. */
  /* Сначала пробуем через сервер: кладём PDF на сайт и открываем обычную
     ссылку — такие приложение скачивает штатно. Если сервер недоступен,
     остаются прежние пути. */
  function kpDeliverPdf(blob, name, d){
    var dataStr = null;
    try { dataStr = d ? d.output('datauristring') : null; } catch(e) {}
    if (dataStr) {
      var no = (name.match(/\d+/) || ['kp'])[0];
      fetch('/api/api.php?action=kp_pdf_put', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ no: no, pdf: dataStr })
      }).then(function(r){ return r.json(); }).then(function(j){
        if (j && j.ok && j.url) { kpServedPdf(j.url, name, blob); return; }
        kpDeliverLocal(blob, name, d);
      }).catch(function(){ kpDeliverLocal(blob, name, d); });
      return;
    }
    kpDeliverLocal(blob, name, d);
  }

  /* Файл лежит на сайте — открываем ссылку, приложение скачает его само. */
  function kpServedPdf(url, name, blob){
    var ov = document.createElement('div');
    ov.className = 'kp-ov';
    ov.innerHTML = '<div class="kp-box"><div class="kp-bar">'
      + '<button type="button" class="x" data-pdf="close">✕</button></div>'
      + '<div class="kp-doc">'
      + '<div class="kp-title" style="margin-top:0">Файл ' + name + ' готов</div>'
      + '<div class="kp-meta">Размер ' + Math.round(blob.size/1024) + ' КБ. Файл лежит на сайте — ссылка работает неделю.</div>'
      + '<button type="button" data-pdf="open" '
      +   'style="width:100%;padding:14px;margin-bottom:9px;background:#112C93;color:#fff;border:0;'
      +   'font-size:15px;font-weight:700;text-align:center;cursor:pointer;font-family:inherit">'
      +   'Открыть и сохранить PDF</button>'
      + '<button type="button" data-pdf="link" '
      +   'style="width:100%;padding:14px;margin-bottom:9px;border:1px solid var(--line);background:#fff;'
      +   'color:#112C93;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit">Отправить ссылку на файл</button>'
      + '<button type="button" data-pdf="copy" '
      +   'style="width:100%;padding:14px;border:1px solid var(--line);background:#fff;'
      +   'color:#112C93;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit">Скопировать ссылку</button>'
      + '<div class="kp-note">Ссылку можно переслать клиенту напрямую — он откроет её в любом браузере.</div>'
      + '</div></div>';
    document.body.appendChild(ov);
    /* шаг истории не добавляем: «Назад» разбирает окна сама */
    ov.addEventListener('click', function(e){
      var b = e.target.closest ? e.target.closest('[data-pdf]') : null;
      var act = b ? b.getAttribute('data-pdf') : null;
      if (act === 'open') {
        /* Внутри приложения переход на файл ни к чему не приводит: своего
           просмотрщика нет, загрузчик молчит, новые вкладки не открываются.
           Единственный рабочий путь — отдать ссылку в панель приложения:
           оттуда её открывают браузером, который и скачает файл.
           В обычном браузере просто переходим по ссылке. */
        var inApp = /ITCApp/i.test(navigator.userAgent || '');
        try {
          if (inApp && window.AndroidShare && AndroidShare.share) {
            AndroidShare.share('КП ' + name, 'Открыть в браузере, чтобы сохранить PDF', url);
            return;
          }
        } catch(err) {}
        location.href = url;
        return;
      }
      if (act === 'link') {
        try { if (window.AndroidShare && AndroidShare.share) { AndroidShare.share(name, 'Коммерческое предложение', url); return; } } catch(err) {}
        if (navigator.share) { navigator.share({ title:name, url:url }).catch(function(){}); return; }
        alert(url);
        return;
      }
      if (act === 'copy') {
        if (navigator.clipboard) navigator.clipboard.writeText(url).then(function(){ alert('Ссылка скопирована'); });
        else alert(url);
        return;
      }
      if (e.target === ov || b) {
        if (ov.parentNode) ov.parentNode.removeChild(ov);
        
      }
    });
  }

  function kpDeliverLocal(blob, name, d){
    /* ВАЖНОЕ ОГРАНИЧЕНИЕ. Внутри приложения нет встроенного просмотрщика PDF —
       показать документ прямо в окне нельзя, будет белый лист. И ссылки вида
       blob: приложение не подхватывает на скачивание. Зато оно понимает адреса
       вида data: — их ловит обработчик загрузок. Поэтому отдаём файл так, а
       blob оставляем как запасной для компьютера. */
    var dataUrl = null;
    try { dataUrl = d ? d.output('datauristring') : null; } catch(e) {}
    var blobUrl = null;
    try { blobUrl = URL.createObjectURL(blob); } catch(e) {}
    var href = dataUrl || blobUrl;

    var shared = false;
    try {
      var f = new File([blob], name, { type:'application/pdf' });
      if (navigator.canShare && navigator.canShare({ files:[f] }) && navigator.share) {
        navigator.share({ files:[f], title:name }).catch(function(){});
        shared = true;
      }
    } catch(e) {}
    if (!shared) {
      try {
        var a0 = document.createElement('a');
        a0.href = href; a0.download = name; a0.rel = 'noopener';
        document.body.appendChild(a0); a0.click(); a0.remove();
      } catch(e) {}
    }

    var ov = document.createElement('div');
    ov.className = 'kp-ov';
    ov.innerHTML = '<div class="kp-box"><div class="kp-bar">'
      + '<button type="button" class="x" data-pdf="close">✕</button></div>'
      + '<div class="kp-doc">'
      + '<div class="kp-title" style="margin-top:0">Файл ' + name + ' готов</div>'
      + '<div class="kp-meta">Размер ' + Math.round(blob.size/1024) + ' КБ. '
      +   'Если он не сохранился сам — воспользуйтесь кнопками ниже.</div>'
      + '<a href="' + href + '" download="' + name + '" '
      +   'style="display:block;padding:14px;margin-bottom:9px;background:#112C93;color:#fff;'
      +   'font-size:15px;font-weight:700;text-align:center;text-decoration:none">Сохранить файл</a>'
      + '<a href="' + href + '" target="_blank" rel="noopener" '
      +   'style="display:block;padding:14px;margin-bottom:9px;border:1px solid var(--line);'
      +   'background:#fff;color:#112C93;font-size:15px;font-weight:700;text-align:center;'
      +   'text-decoration:none">Открыть в браузере</a>'
      + '<button type="button" data-pdf="share" '
      +   'style="width:100%;padding:14px;border:1px solid var(--line);background:#fff;'
      +   'color:#112C93;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit">'
      +   'Отправить файл</button>'
      + '<div class="kp-note">Внутри приложения PDF не показывается — у него нет своего '
      +   'просмотрщика. Сохраните файл и откройте любым просмотрщиком: оттуда же печать '
      +   'и отправка.</div>'
      + '</div></div>';
    document.body.appendChild(ov);
    /* шаг истории не добавляем: «Назад» разбирает окна сама */
    ov.addEventListener('click', function(e){
      var b = e.target.closest ? e.target.closest('[data-pdf]') : null;
      if (b && b.getAttribute('data-pdf') === 'share') {
        try {
          var f2 = new File([blob], name, { type:'application/pdf' });
          if (navigator.canShare && navigator.canShare({ files:[f2] }) && navigator.share) {
            navigator.share({ files:[f2], title:name }).catch(function(){});
            return;
          }
        } catch(err) {}
        alert('Отправка файла недоступна. Нажмите «Сохранить файл», затем отправьте его из проводника.');
        return;
      }
      if (e.target === ov || b) {
        if (ov.parentNode) ov.parentNode.removeChild(ov);
        
        if (blobUrl) setTimeout(function(){ URL.revokeObjectURL(blobUrl); }, 120000);
      }
    });
  }


  /* ОТПРАВКА КП. Системное меню «Поделиться» внутри приложения часто
     недоступно — тогда раньше оставалось только копирование в буфер. Теперь
     даём прямые кнопки мессенджеров: они открывают приложение напрямую. */
  function kpText(no, dateStr, data){
    var lines = ['Коммерческое предложение № ' + no + ' от ' + dateStr, ''];
    data.rows.forEach(function(r,i){
      lines.push((i+1)+'. '+r.name+' — '+fmt(r.qty,0)+' шт × '+fmt(r.per,0)+' ₽ = '+fmt(r.sum,0)+' ₽');
    });
    lines.push('', 'Итого: '+fmt(data.total,0)+' ₽');
    lines.push('Цена включает доставку до Москвы, таможенное оформление, пошлину и НДС.');
    return lines.join('\n');
  }
  function kpShare(no, dateStr, data){
    var txt = kpText(no, dateStr, data);
    var title = 'Коммерческое предложение № ' + no;
    /* СНАЧАЛА ПРОБУЕМ СИСТЕМНУЮ ПАНЕЛЬ — ту самую, что выезжает снизу с
       иконками приложений. В нашем Android-приложении она доступна через мост
       AndroidShare (в магазине «Поделиться» работает именно так), в браузере —
       через navigator.share. Свой список показываем только если ни того, ни
       другого нет. */
    try {
      if (window.AndroidShare && AndroidShare.share) {
        AndroidShare.share(title, txt, '');
        return;
      }
    } catch(e) {}
    if (navigator.share) {
      navigator.share({ title: title, text: txt }).catch(function(err){
        if (err && (err.name === 'AbortError' || err.name === 'NotAllowedError')) return;
        kpShareList(no, txt);
      });
      return;
    }
    kpShareList(no, txt);
  }

  /* Запасной список — когда системной панели нет вовсе (обычно на компьютере). */
  function kpShareList(no, txt){
    var enc = encodeURIComponent(txt);
    var btn = function(id, label, note){
      return '<button type="button" data-send="'+id+'" style="width:100%;display:flex;align-items:center;'
        + 'justify-content:space-between;gap:10px;padding:14px 16px;background:#fff;border:0;'
        + 'border-bottom:1px solid #e0e6f0;text-align:left;cursor:pointer;font-family:inherit">'
        + '<span style="font-size:15px;font-weight:700;color:#1A1A1A">'+label+'</span>'
        + '<span style="font-size:11.5px;color:#8a92a3">'+(note||'')+'</span></button>';
    };
    var ov = document.createElement('div');
    ov.className = 'kp-ov';
    ov.innerHTML = '<div class="kp-box"><div class="kp-bar">'
      + '<button type="button" class="x" data-send="close">✕</button>'
      + '<button type="button" data-send="print">Сохранить PDF</button></div>'
      + '<div class="kp-doc"><div class="kp-title" style="margin-top:0">Куда отправить КП № '+no+'</div>'
      + '<div class="kp-meta">Текст предложения уйдёт в выбранное приложение.</div>'
      + '<div style="border:1px solid #e0e6f0">'
      +   btn('whatsapp','WhatsApp','откроется выбор чата')
      +   btn('telegram','Telegram','откроется выбор чата')
      +   btn('mail','Электронная почта','письмо с текстом КП')
      +   btn('copy','Скопировать текст','вставить куда угодно')
      + '</div></div></div>';
    document.body.appendChild(ov);
    /* шаг истории не добавляем: «Назад» разбирает окна сама */
    var close = function(){ if (ov.parentNode) ov.parentNode.removeChild(ov);
       };
    var go = function(url){ try { window.open(url, '_blank'); } catch(e) { location.href = url; } };
    ov.addEventListener('click', function(e){
      if (e.target === ov) { close(); return; }
      var b = e.target.closest ? e.target.closest('[data-send]') : null;
      if (!b) return;
      var act = b.getAttribute('data-send');
      if (act === 'close') { close(); return; }
      if (act === 'print') { close(); kpPrintLast(); return; }
      if (act === 'whatsapp') { go('https://wa.me/?text=' + enc); return; }
      if (act === 'telegram') { go('https://t.me/share/url?url=' + encodeURIComponent(location.origin) + '&text=' + enc); return; }
      if (act === 'mail') { location.href = 'mailto:?subject=' + encodeURIComponent('Коммерческое предложение № '+no) + '&body=' + enc; return; }
      if (act === 'copy') {
        if (navigator.clipboard) navigator.clipboard.writeText(txt).then(function(){ alert('Текст КП скопирован'); });
        else alert('Скопируйте текст вручную');
      }
    });
  }

  /* КП сохраняется в ту же историю, но ОТДЕЛЬНОЙ записью, чтобы расчёт и
     предложение клиенту не смешивались. */
  function kpSaveToHistory(no, dateStr, data, req){
    try {
      /* Ключ тот же, что у истории расчётов (itc_v2) — иначе КП сохранялось бы
         мимо и в списке не появлялось. */
      var arr = JSON.parse(localStorage.getItem('itc_v2') || '[]');
      arr.unshift({ id: 'kp'+Date.now(), name: 'КП № '+no, dateStr: dateStr, ts: Date.now(),
        data: { type:'kp', no:no, total:Math.round(data.total),
                items: data.rows.map(function(r){ return {name:r.name, qty:r.qty, per:Math.round(r.per), sum:Math.round(r.sum)}; }) } });
      localStorage.setItem('itc_v2', JSON.stringify(arr.slice(0,200)));
      try { if (window.ITC_REFRESH_HISTORY) window.ITC_REFRESH_HISTORY(); } catch(e){}
      /* Если клиент выбран — КП уходит ещё и в его карточку на сервер, чтобы
         предложение не пропало вместе с памятью браузера. */
      if (window.KP_CLIENT && window.KP_CLIENT.id && window.crmPost) {
        window.crmPost('crm_kp_save', {
          client_id: window.KP_CLIENT.id, no: no, dt: dateStr,
          total: Math.round(data.total),
          items: data.rows.map(function(r){ return {name:r.name, qty:r.qty, per:Math.round(r.per), sum:Math.round(r.sum)}; })
        }).then(function(r){
          alert(r && r.ok
            ? 'КП № '+no+' сохранено в историю и в карточку клиента'
            : 'КП сохранено в историю, но в карточку клиента не попало: '+((r&&r.error)||'ошибка'));
        }).catch(function(){ alert('КП сохранено в историю. Карточка клиента недоступна — нет связи.'); });
      } else {
        alert('КП № '+no+' сохранено в историю');
      }
    } catch(e){ alert('Не удалось сохранить КП'); }
  }

  /* Список наборов: коротко «ООО ИТК · Уралсиб», отметка у выбранного.
     Нажатие на строку выбирает набор, карандаш открывает правку. */
  function kpEditReq(){
    var arr = kpReqs(), cur = kpCurIdx();
    var rows = arr.map(function(r,i){
      var on = (i === cur);
      return '<button type="button" class="catp-row'+(on?' on':'')+'" data-pick="'+i+'">'
        +  '<span class="catp-box">'+(on?'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>':'')+'</span>'
        +  '<span class="catp-tx"><span class="catp-n">'+kpEsc(kpShortOf(r))+'</span>'
        +    '<span class="catp-d">'+kpEsc(r.name||'')+(r.inn?' · ИНН '+kpEsc(r.inn):'')+'</span></span>'
        +  '<span data-edit="'+i+'" style="flex:0 0 auto;padding:4px 8px;color:#112C93;font-size:12px;font-weight:700">Изменить</span>'
        +  (arr.length>1?'<span data-del="'+i+'" style="flex:0 0 auto;padding:4px 8px;color:#A11A1A;font-size:12px">Удалить</span>':'')
        + '</button>';
    }).join('');
    var ov = document.createElement('div');
    ov.className = 'kp-ov';
    ov.innerHTML = '<div class="kp-box"><div class="kp-bar">'
      + '<button type="button" class="x" data-list="close">✕</button>'
      + '<button type="button" class="main" data-list="add">Добавить реквизиты</button></div>'
      + '<div class="kp-doc"><div class="kp-title" style="margin-top:0">Реквизиты для КП</div>'
      + '<div class="kp-meta">Выбранный набор подставляется в коммерческое предложение.</div>'
      + '<div style="border:1px solid var(--line)">'+rows+'</div></div></div>';
    document.body.appendChild(ov);
    /* шаг истории не добавляем: «Назад» разбирает окна сама */
    var close = function(){ if (ov.parentNode) ov.parentNode.removeChild(ov);
       };
    ov.addEventListener('click', function(e){
      if (e.target === ov) { close(); return; }
      var t = e.target;
      var ed = t.closest ? t.closest('[data-edit]') : null;
      if (ed) { close(); kpEditOne(parseInt(ed.getAttribute('data-edit'),10)); return; }
      var dl = t.closest ? t.closest('[data-del]') : null;
      if (dl) {
        var di = parseInt(dl.getAttribute('data-del'),10);
        var a2 = kpReqs();
        if (!confirm('Удалить набор «'+kpShortOf(a2[di])+'»?')) return;
        a2.splice(di,1); kpReqsSave(a2);
        if (kpCurIdx() >= a2.length) kpSetCur(0);
        close(); kpEditReq(); return;
      }
      var b = t.closest ? t.closest('[data-list]') : null;
      if (b) {
        if (b.getAttribute('data-list') === 'close') close();
        else { close(); kpEditOne(-1); }        // -1 = новый набор
        return;
      }
      var pk = t.closest ? t.closest('[data-pick]') : null;
      if (pk) { kpSetCur(parseInt(pk.getAttribute('data-pick'),10)); close(); alert('Реквизиты выбраны'); }
    });
  }

  /* Правка одного набора. idx = -1 — создаём новый. */
  function kpEditOne(idx){
    var arr = kpReqs();
    var isNew = (idx < 0);
    var r = isNew ? { short:'', name:'', inn:'', kpp:'', ogrn:'', addr:'', phone:'', mail:'',
                      site:'', bank:'', bik:'', acc:'', corr:'', boss:'' } : arr[idx];
    var f = function(lb, key, ph){
      return '<label style="display:block;margin-bottom:9px"><span style="display:block;font-size:11px;font-weight:700;color:#6b7a99;text-transform:uppercase;margin-bottom:4px">'+lb+'</span>'
        + '<input data-req="'+key+'" value="'+kpEsc(r[key]||'')+'" placeholder="'+(ph||'')+'" style="width:100%;box-sizing:border-box;padding:9px 10px;border:1px solid #c0c8e0;border-radius:0;font-size:14px"></label>';
    };
    var ov = document.createElement('div');
    ov.className = 'kp-ov';
    ov.innerHTML = '<div class="kp-box"><div class="kp-bar">'
      + '<button type="button" class="x" data-one="close">✕</button>'
      + '<button type="button" class="main" data-one="save">Сохранить</button></div>'
      + '<div class="kp-doc"><div class="kp-title" style="margin-top:0">'+(isNew?'Новые реквизиты':'Изменить реквизиты')+'</div>'
      + '<div class="kp-meta">Короткое название видно в списке — по нему менеджер выбирает набор.</div>'
      + f('Короткое название','short','ООО ИТК · Уралсиб')
      + f('Полное наименование','name') + f('ИНН','inn') + f('КПП','kpp') + f('ОГРН','ogrn')
      + f('Юридический адрес','addr') + f('Телефон','phone') + f('E-mail','mail') + f('Сайт','site')
      + f('Банк','bank','наименование банка') + f('БИК','bik') + f('Расчётный счёт','acc') + f('Корр. счёт','corr')
      + f('Подпись под КП','boss','должность и ФИО')
      + '</div></div>';
    document.body.appendChild(ov);
    /* шаг истории не добавляем: «Назад» разбирает окна сама */
    var close = function(){ if (ov.parentNode) ov.parentNode.removeChild(ov);
       };
    ov.addEventListener('click', function(e){
      if (e.target === ov) { close(); return; }
      var b = e.target.closest ? e.target.closest('[data-one]') : null;
      if (!b) return;
      if (b.getAttribute('data-one') === 'close') { close(); kpEditReq(); return; }
      var out = {};
      ov.querySelectorAll('[data-req]').forEach(function(i2){ out[i2.getAttribute('data-req')] = i2.value.trim(); });
      if (!out.name) { alert('Укажите наименование организации'); return; }
      var a3 = kpReqs();
      if (isNew) { a3.push(out); kpReqsSave(a3); kpSetCur(a3.length-1); }
      else { a3[idx] = out; kpReqsSave(a3); }
      close(); kpEditReq();
    });
  }

  /* В числовых полях уже стоит значение, и раньше его приходилось стирать
     перед вводом — а если не стереть, новая цифра приписывалась к старой.
     Теперь при попадании в поле всё выделяется: набрал своё — прежнее ушло. */
  document.addEventListener('focusin', function(e){
    var t = e.target;
    if (!t || t.tagName !== 'INPUT') return;
    if (t.type !== 'number' && t.inputMode !== 'decimal') return;
    setTimeout(function(){ try{ t.select(); }catch(err){} }, 0);
  });

  // Обновление описания категории
  function updateCatDesc() {
    var code = g('fc-cat').value;
    var cat = CATS[code];
    if (!cat) return;
    g('fc-cat-desc').textContent = cat.sign;
    g('fc-cat-formula').textContent = cat.formula;
    var lbl = g('fc-cat-label');            // подпись на кнопке выбора
    if (lbl) lbl.textContent = fcCatTitle(code);
    /* Для автоперевозки фрахт за контейнер не нужен — вместо него ставка за
       кубометр. Станция (железная дорога) тоже не нужна: её ставим в ноль,
       но поле оставляем — вдруг понадобится мелкая сумма за перевалку. */
    /* Автодоставка везёт фура, а не контейнер по железной дороге. Меняем
       подписи, значения по умолчанию и состав полей: станции нет вовсе, вместо
       неё плечо внутри Китая. */
    var isAuto = (cat.group === 'AUTO');
    var show = function(id, on){ var e = g(id); if (e) e.style.display = on ? '' : 'none'; };
    var setLb = function(id, txt){ var e = g(id); if (e) e.textContent = txt; };
    show('fc-row-cbmrate', isAuto);
    show('fc-row-station', !isAuto);
    show('fc-row-china', isAuto);
    show('fc-row-payrate', isAuto);
    show('fc-row-payfee', isAuto);
    show('fc-row-export', isAuto);
    /* Доставка от Москвы до города назначения. Нужна только при автоперевозке:
       фура приходит в Москву, дальше товар едет партнёрской машиной. */
    show('fc-row-citynm', isAuto);
    show('fc-row-cityrate', isAuto);
    var frEl = g('fc-freight');
    var frRow = frEl ? frEl.closest('.input-row') : null;
    if (frRow) frRow.style.display = isAuto ? 'none' : '';
    setLb('fc-lb-container',   isAuto ? 'Ёмкость еврофуры (м³)' : 'Ёмкость контейнера (м³)');
    setLb('fc-lb-containerWt', isAuto ? 'Грузоподъёмность еврофуры' : 'Грузоподъёмность контейнера');
    /* У фуры это не доставка до склада, а перегрузка на терминале в Москве и
       перевозка до транспортной компании клиента. Сумма известна не всегда,
       поэтому по умолчанию пусто — вбивают, когда её объявят. */
    setLb('fc-lb-delivery', isAuto ? 'Перегрузка и перевозка по Москве (₽)' : 'Доставка до склада (₽)');
    if (isAuto !== _autoWasOn) {
      /* Значения по умолчанию своё для каждого вида перевозки. Запоминаем
         прежние, чтобы вернуть при обратном переключении. */
      var swap = function(id, val, store){
        var e = g(id); if (!e) return;
        if (isAuto) { _saved[store] = e.value; e.value = val; }
        else if (_saved[store] != null) { e.value = _saved[store]; _saved[store] = null; }
      };
      swap('fc-container',   80,    'cbm');    // еврофура
      swap('fc-containerWt', 20000, 'wt');
      swap('fc-broker',      25000, 'broker');
      swap('fc-station',     0,     'station');
      swap('fc-delivery',    0,     'delivery');
    }
    _autoWasOn = isAuto;
    var cu = g('fc-china-unit'); if (cu) cu.textContent = fcCurSign();
    try{ fcPaintFields(); }catch(e){}
    var eu = g('fc-export-unit'); if (eu) eu.textContent = fcCurSign();
  }
  var _autoWasOn = false, _saved = {};
  g('fc-cat-btn').addEventListener('click', fcCatPickOpen);
  updateCatDesc();

  // Добавить позицию
  /* ВАЛЮТА ИНВОЙСА — одна на весь расчёт. Инвойс приходит целиком либо в юанях,
     либо в долларах, поэтому переключатель общий. Числа при переключении не
     трогаем: менеджер сам знает, что вбивает. */
  var FC_CUR = 'CNY';
  function fcCurSign(){ return FC_CUR === 'USD' ? '$' : '¥'; }
  function fcCurRate(){
    var e = document.getElementById(FC_CUR === 'USD' ? 'fc-usd' : 'fc-cny');
    return e ? (parseFloat(e.value)||0) : 0;
  }
  window.fcSetCur = function(cur){
    FC_CUR = (cur === 'USD') ? 'USD' : 'CNY';
    var bs = document.querySelectorAll('.cur-btn');
    for (var i=0;i<bs.length;i++) bs[i].className = 'cur-btn' + (bs[i].getAttribute('data-cur')===FC_CUR ? ' active' : '');
    var cu = document.getElementById('fc-china-unit'); if (cu) cu.textContent = fcCurSign();
    renderItems();     // подписи полей и итоги по позициям меняются
  };

  window.fcAddItem = function() {
    var id = nextId++;
    /* Имя оставляем пустым: раньше сюда писалось «Позиция N», и менеджеру
       приходилось это стирать перед вводом. Теперь номер виден серой подсказкой. */
    items.push({id: id, name: '', qty: 1, vol: 0, inv: 0, wt: 0});
    renderItems();
  };

  // Удалить позицию
  window.fcRemoveItem = function(id) {
    items = items.filter(function(it) { return it.id !== id; });
    renderItems();
  };

  // Рендер списка позиций
  function renderItems() {
    var list = g('fc-items-list');
    if (items.length === 0) {
      list.innerHTML = '<div class="fc-empty-items sans">Нажмите «+ Добавить позицию» чтобы внести товары в контейнер</div>';
      fcCalc();
      return;
    }
    var html = '';
    items.forEach(function(it, idx) {
      html += '<div class="fc-item" id="fc-item-' + it.id + '">';
      html += '<div class="fc-item-header">';
      html += '<input type="text" class="fc-item-name sans" value="' + escH(it.name) + '" placeholder="Наименование товара ' + (idx+1) + '" oninput="fcUpdateItem(' + it.id + ',\'name\',this.value)">';
      html += '<button class="fc-item-del" onclick="fcRemoveItem(' + it.id + ')">✕</button>';
      html += '</div>';
      /* ЗА ШТУКУ И ЗА ВСЮ ПАРТИЮ — две графы на каждую величину, рядом.
         Бывает известен объём одной штуки, а бывает только общий на всю партию;
         то же с ценой и весом. Обе графы заполняются в любую сторону и
         пересчитывают друг друга через количество. */
      var q0 = (parseFloat(it.qty) > 0 ? parseFloat(it.qty) : 1);
            html += '<div class="fc-item-fields">';

      // Количество — первым, от него считаются все суммы
      html += '<div class="fc-grp fc-grp-qty">';
      html += '<div class="fc-item-field">';
      html += '<label class="sans">Кол-во (шт)</label>';
      html += '<input type="number" id="fi-qty-' + it.id + '" class="fc-item-input sans" value="' + (it.qty || 1) + '" step="1" min="1" placeholder="1" oninput="fcUpdateItem(' + it.id + ',\'qty\',this.value)">';
      html += '</div></div>';

      // Цена
      html += '<div class="fc-grp fc-grp-price">';
      html += '<div class="fc-item-field">';
      html += '<label class="sans">Цена 1 шт (' + fcCurSign() + ')</label>';
      html += '<input type="number" id="fi-inv-' + it.id + '" class="fc-item-input sans" value="' + fcNum(it.inv) + '" step="1000" min="0" placeholder="0" oninput="fcUpdateItem(' + it.id + ',\'inv\',this.value)">';
      html += '</div>';
      html += '<div class="fc-item-field tot">';
      html += '<label class="sans">Цена за всё (' + fcCurSign() + ')</label>';
      html += '<input type="number" id="fi-invt-' + it.id + '" class="fc-item-input sans" value="' + fcNum((parseFloat(it.inv)||0)*q0) + '" step="1000" min="0" placeholder="0" oninput="fcUpdateItem(' + it.id + ',\'invTot\',this.value)">';
      html += '</div></div>';

      // Объём
      html += '<div class="fc-grp fc-grp-vol">';
      html += '<div class="fc-item-field">';
      html += '<label class="sans">Объём 1 шт (м³)</label>';
      html += '<input type="number" id="fi-vol-' + it.id + '" class="fc-item-input sans" value="' + fcNum(it.vol) + '" step="0.1" min="0" placeholder="0.0" oninput="fcUpdateItem(' + it.id + ',\'vol\',this.value)">';
      html += '</div>';
      html += '<div class="fc-item-field tot">';
      html += '<label class="sans">Объём за всё (м³)</label>';
      html += '<input type="number" id="fi-volt-' + it.id + '" class="fc-item-input sans" value="' + fcNum((parseFloat(it.vol)||0)*q0) + '" step="0.1" min="0" placeholder="0.0" oninput="fcUpdateItem(' + it.id + ',\'volTot\',this.value)">';
      html += '</div></div>';

      // Вес
      html += '<div class="fc-grp fc-grp-wt">';
      html += '<div class="fc-item-field">';
      html += '<label class="sans">Вес 1 шт (кг)</label>';
      html += '<input type="number" id="fi-wt-' + it.id + '" class="fc-item-input sans" value="' + fcNum(it.wt || 0) + '" step="10" min="0" placeholder="0" oninput="fcUpdateItem(' + it.id + ',\'wt\',this.value)">';
      html += '</div>';
      html += '<div class="fc-item-field tot">';
      html += '<label class="sans">Вес за всё (кг)</label>';
      html += '<input type="number" id="fi-wtt-' + it.id + '" class="fc-item-input sans" value="' + fcNum((parseFloat(it.wt)||0)*q0) + '" step="10" min="0" placeholder="0" oninput="fcUpdateItem(' + it.id + ',\'wtTot\',this.value)">';
      html += '</div></div>';
      html += '</div>';
      html += '<div id="fc-tot-' + it.id + '" class="sans" style="font-size:12px;color:#5b6472;margin-top:6px">' + fcTotalsText(it) + '</div>';
      // Блок автопроверки
      html += '<div id="fc-check-' + it.id + '" class="fc-check-badges"></div>';
      html += '</div>';
    });
    list.innerHTML = html;
    // После рендера — обновляем бейджи
    items.forEach(function(it) { fcUpdateBadges(it.id, it.name); });
    fcCalc();
  }

  /* Текст итога по позиции. Отдельно, чтобы обновлять его на месте, не
     перерисовывая всю позицию: иначе поле ввода пересоздаётся и курсор из него
     вылетает после первой же цифры. */
  function fcTotalsText(it) {
    var q = (parseFloat(it.qty) > 0 ? parseFloat(it.qty) : 1);
    return 'На ' + fmt(q,0) + ' шт: ' + fmt((parseFloat(it.vol)||0)*q, 2) + ' м³ · ' +
           fmt((parseFloat(it.inv)||0)*q, 0) + ' ' + fcCurSign() + ' · ' + fmt((parseFloat(it.wt)||0)*q, 0) + ' кг';
  }

  function escH(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  /* Число для поля ввода: без хвоста нулей и без разрядных пробелов, иначе
     браузер посчитает значение недопустимым и очистит поле. */
  function fcNum(v) {
    var n = parseFloat(v);
    if (!isFinite(n) || n === 0) return 0;
    return parseFloat(n.toFixed(6));
  }

  /* ПАРНЫЕ ГРАФЫ. Храним по-прежнему значение НА ОДНУ ШТУКУ — на нём держится
     весь расчёт. Графа «за всю партию» вычисляется из него, а если менеджер
     вбивает её сам, обратно получаем значение на штуку делением на количество.
     Переписываем ТОЛЬКО соседнее поле, а не всю позицию: при перерисовке поле
     ввода пересоздаётся и курсор вылетает после первой же цифры. */
  var FC_PAIRS = { vol: 'fi-volt-', inv: 'fi-invt-', wt: 'fi-wtt-' };
  var FC_BACK  = { volTot: 'vol', invTot: 'inv', wtTot: 'wt' };
  var FC_UNIT  = { vol: 'fi-vol-', inv: 'fi-inv-', wt: 'fi-wt-' };

  function fcSyncItemFields(it, skip) {
    var q = (parseFloat(it.qty) > 0 ? parseFloat(it.qty) : 1);
    ['vol','inv','wt'].forEach(function(f) {
      if (skip !== f) {
        var u = document.getElementById(FC_UNIT[f] + it.id);
        if (u && document.activeElement !== u) u.value = fcNum(it[f]);
      }
      if (skip !== f + 'Tot') {
        var t = document.getElementById(FC_PAIRS[f] + it.id);
        if (t && document.activeElement !== t) t.value = fcNum((parseFloat(it[f]) || 0) * q);
      }
    });
  }

  window.fcUpdateItem = function(id, field, val) {
    var it = items.find(function(x) { return x.id === id; });
    if (!it) return;
    if (FC_BACK[field]) {
      // Вбили значение на всю партию — получаем из него значение на штуку
      var q = (parseFloat(it.qty) > 0 ? parseFloat(it.qty) : 1);
      it[FC_BACK[field]] = (parseFloat(val) || 0) / q;
      fcSyncItemFields(it, field);
      var tot0 = document.getElementById('fc-tot-' + id);
      if (tot0) tot0.textContent = fcTotalsText(it);
      fcCalc();
      return;
    }
    if (field === 'vol' || field === 'inv' || field === 'wt' || field === 'qty') {
      it[field] = parseFloat(val) || 0;
      /* При смене количества значения на штуку остаются прежними, а суммы за
         партию пересчитываются — это и есть привычный порядок: сперва сколько
         штук, потом сколько стоит одна. */
      fcSyncItemFields(it, field);
      var tot = document.getElementById('fc-tot-' + id);
      if (tot) tot.textContent = fcTotalsText(it);
      fcCalc();
      return;
    } else {
      it[field] = val;
      fcUpdateBadges(id, val);
    }
    fcCalc();
  };

  // Обновление бейджей Честный знак и Сертификат
  function fcUpdateBadges(id, name) {
    var el = document.getElementById('fc-check-' + id);
    if (!el) return;
    var res = fcCheckProduct(name || '');
    if (!res || (!res.cz && !res.cert)) {
      el.innerHTML = '';
      return;
    }
    var html = '';
    if (res.cz) {
      html += '<div class="fc-badge fc-badge-cz">';
      html += '<span class="fc-badge-icon">✅</span>';
      html += '<span class="fc-badge-text">' + escH(res.cz.note) + '</span>';
      html += '</div>';
    }
    if (res.cert) {
      var icon = res.cert.type === 'cert' ? '📋' : res.cert.type === 'decl' ? '📄' : 'ℹ️';
      var cls = res.cert.type === 'cert' ? 'fc-badge-cert' : res.cert.type === 'decl' ? 'fc-badge-decl' : 'fc-badge-no';
      html += '<div class="fc-badge ' + cls + '">';
      html += '<span class="fc-badge-icon">' + icon + '</span>';
      html += '<span class="fc-badge-text">' + escH(res.cert.note) + '</span>';
      html += '</div>';
    }
    el.innerHTML = html;
  }

  // Пересчёт
  function fcCalc() {
    /* КОЛИЧЕСТВО. В позиции хранятся данные ЗА ШТУКУ, а считать надо по всей
       партии. Поэтому здесь один раз собираем рабочий список, где объём, цена
       и вес уже умножены на количество. Дальше весь расчёт идёт по нему. */
    var _q = function(v){ var n = parseFloat(v); return (isFinite(n) && n > 0) ? n : 1; };
    var work = items.map(function(it){
      var q = _q(it.qty);
      return { id: it.id, name: it.name, qty: q,
               vol: (parseFloat(it.vol) || 0) * q,
               inv: (parseFloat(it.inv) || 0) * q,
               wt:  (parseFloat(it.wt)  || 0) * q };
    });
    var containerCbm = gv('fc-container') || 68;
    var containerWt = gv('fc-containerWt') || 26800;
    var F_usd = gv('fc-freight');
    var cnyRate = gv('fc-cny');
    var usdRate = gv('fc-usd');
    var invRate = (FC_CUR === 'USD') ? usdRate : cnyRate;   // курс валюты инвойса
    var cbmRate = gv('fc-cbmrate') || 150;                  // ставка авто за 1 м³
    var chinaLeg = gv('fc-china');                          // доставка по Китаю, в валюте инвойса
    var payRate  = gv('fc-payrate') || usdRate;             // курс, по которому платим экспедитору
    var payFee   = gv('fc-payfee') / 100;                   // его комиссия за оплату
    var expFee   = gv('fc-export');                         // экспортное оформление в Китае
    var insRate  = gv('fc-ins') / 100;                      // страховка от стоимости товара
    var dutyRate = gv('fc-duty') / 100;
    var vatRate = gv('fc-vat') / 100;
    var station = gv('fc-station');
    var broker = gv('fc-broker');
    var delivery = gv('fc-delivery');
    var transferRate = gv('fc-transfer') / 100;
    /* Доставка от Москвы до города назначения. Ставка за кубометр, платим
       партнёрской транспортной компании уже в России — поэтому ни наценки, ни
       3% за перевод денег в Китай на неё не идёт: это закуп, показываем его
       отдельной строкой. */
    var cityRate = gv('fc-cityrate');
    var cityName = (function(){ var e = g('fc-cityname'); return (e && e.value.trim()) || 'города назначения'; })();
    var catCode = g('fc-cat').value;
    var cat = CATS[catCode];

    // Заполнение контейнера
    var totalVol = work.reduce(function(s, it) { return s + it.vol; }, 0);
    var fillPct = Math.min(100, (totalVol / containerCbm) * 100);
    g('fc-fill-bar').style.width = fillPct + '%';
    g('fc-fill-bar').style.background = fillPct > 100 ? '#A11A1A' : fillPct >= 95 ? '#128A04' : '#112C93';
    g('fc-fill-text').textContent = fmt(totalVol,2) + ' / ' + fmt(containerCbm,0) + ' м³';

    var warn = g('fc-fill-warning');
    if (totalVol > containerCbm) {
      warn.textContent = '⚠️ Объём превышает ёмкость контейнера на ' + fmt(totalVol - containerCbm, 2) + ' м³!';
      warn.className = 'fc-fill-warning fc-warn-over';
    } else if (fillPct >= 95) {
      warn.textContent = '✅ Контейнер заполнен на ' + fmt(fillPct, 1) + '%';
      warn.className = 'fc-fill-warning fc-warn-ok';
    } else if (items.length > 0 && totalVol > 0) {
      warn.textContent = 'ℹ️ Свободно ' + fmt(containerCbm - totalVol, 2) + ' м³ (' + fmt(100 - fillPct, 1) + '%)';
      warn.className = 'fc-fill-warning fc-warn-info';
    } else {
      warn.className = 'fc-fill-warning hidden';
    }

    // Если нет позиций — скрываем результаты
    if (items.length === 0 || totalVol === 0) {
      g('fc-results-wrap').className = 'hidden';
      return;
    }

    // Считаем каждую позицию
    var rows = work.map(function(it) {
      var share = totalVol > 0 ? it.vol / totalVol : 0; // доля от суммарного объёма (для отображения)
      var shareVol = containerCbm > 0 ? it.vol / containerCbm : 0;
      var shareWt  = (containerWt > 0 && it.wt > 0) ? it.wt / containerWt : 0;
      var shareC = Math.max(shareVol, shareWt); // доля = max(объём, вес) — как в calcLandedRub

      /* Стоимость товара переводим в рубли по курсу ВЫБРАННОЙ валюты инвойса —
         это единственное место, где переключатель что-то меняет. */
      var T_rub = it.inv * invRate;
      var insur = T_rub * insRate;                 // страховка — процент от стоимости товара
      var duty = T_rub * dutyRate;
      var vatGoods = T_rub * vatRate;                  // НДС на товар — от стоимости товара (как в быстром расчёте)
      var freight_i_usd = F_usd * shareC;
      var freight_i_rub = freight_i_usd * usdRate;
      var vatFreight = (freight_i_rub / 2) * vatRate;  // НДС на доставку — только китайское плечо (50% фрахта)
      var station_i = station * shareC;
      var broker_i = broker * shareC;
      var delivery_i = delivery * shareC;

      var finalUsd, finalRub;
      var rowUsdRate = usdRate;      // курс для перевода долларовой части в рубли

      if (cat.group === 'AB') {
        var baseRub = T_rub + duty + vatGoods + insur + vatFreight + station_i + broker_i + delivery_i;
        finalUsd = freight_i_usd * (1 + cat.markup) * (1 + transferRate);
        finalRub = baseRub * (1 + cat.markup) * (1 + transferRate);
      } else if (cat.group === 'AUTO') {
        /* АВТОДОСТАВКА FOB. Вместо железной дороги — фура, поэтому доставка
           считается не долей от контейнера, а по объёму: ставка за кубометр.
           Наценка ОДНА И ТА ЖЕ на товар и на доставку, сверху 3% за перевод
           денег в Китай — как у сборного груза. */
        /* Экспедитору платим по ЕГО курсу и с комиссией за оплату: в переписке
           это «150 $ / 0,94», то есть те же 6% сверху. Логистика переводится в
           рубли по своему курсу, а не по банковскому. */
        var autoCost = it.vol * cbmRate * (1 + payFee);
        var autoUsd  = autoCost * (1 + cat.autoMarkup);
        var autoRub  = autoUsd * payRate;
        var vatAuto  = (autoRub / 2) * vatRate;
        var china_i = chinaLeg * invRate * shareC;
        var exp_i   = expFee * invRate * shareC;
        var goodsBlock = (T_rub + duty + vatGoods + insur + vatAuto + china_i + exp_i) * (1 + cat.autoMarkup);
        var costBlock  = (station_i + broker_i + delivery_i) * (1 + cat.autoMarkup);
        finalUsd = autoUsd * (1 + transferRate);
        finalRub = (goodsBlock + costBlock) * (1 + transferRate);
        rowUsdRate = payRate;
      } else {
        // C группа
        var block1Rub = (T_rub + duty + vatGoods + insur + vatFreight) * (1 + cat.goodsMarkup);
        var block2Usd = freight_i_usd * cat.coef;
        var block2Rub = (station_i + broker_i + delivery_i) * cat.coef;
        finalUsd = block2Usd * (1 + transferRate);
        finalRub = (block1Rub + block2Rub) * (1 + transferRate);
      }

      var mskRub = finalUsd * rowUsdRate + finalRub;   // цена с доставкой до Москвы
      var cityRub = (cat.group === 'AUTO') ? it.vol * cityRate : 0;
      var totalRub = mskRub + cityRub;                 // финальная, с доставкой до города

      return {
        name: it.name,
        vol: it.vol,
        volPct: share * 100,
        invCny: it.inv,
        invRub: T_rub,
        freight_usd: freight_i_usd,
        finalUsd: finalUsd,
        finalRub: finalRub,
        mskRub: mskRub,
        cityRub: cityRub,
        totalRub: totalRub,
      };
    });

    /* Складываем строки для КП: там нужны наименование, количество и итог. */
    try {
      window.FC_LAST_ROWS = rows.map(function(r, i){
        var it = work[i] || {};
        return { name: (it.name || '').trim() || ('Позиция ' + (i+1)),
                 qty: (parseFloat(it.qty) > 0 ? parseFloat(it.qty) : 1),
                 totalRub: r.totalRub };
      });
    } catch(e) { window.FC_LAST_ROWS = []; }

    // Итоги
    var sumUsd = rows.reduce(function(s,r){return s+r.finalUsd;},0);
    var sumRub = rows.reduce(function(s,r){return s+r.finalRub;},0);
    var sumTotal = rows.reduce(function(s,r){return s+r.totalRub;},0);
    var sumMsk   = rows.reduce(function(s,r){return s+r.mskRub;},0);
    var sumCity  = rows.reduce(function(s,r){return s+r.cityRub;},0);

    // Строим HTML результатов
    var html = '';

    // По каждой позиции
    rows.forEach(function(r) {
      html += '<div class="fc-res-item">';
      html += '<div class="fc-res-item-head">' + escH(r.name) + ' <span class="fc-res-vol sans">(' + fmt(r.vol,2) + ' м³ · ' + fmt(r.volPct,1) + '%)</span></div>';
      html += '<div class="fc-res-row"><span class="fc-res-label sans">Инвойс (' + fcCurSign() + ')</span><span class="fc-res-val sans">' + fmt(r.invCny) + ' ' + fcCurSign() + '</span></div>';
      html += '<div class="fc-res-row"><span class="fc-res-label sans">Инвойс (₽)</span><span class="fc-res-val sans">' + fmt(r.invRub) + ' ₽</span></div>';
      html += '<div class="fc-res-row"><span class="fc-res-label sans">Фрахт (доля)</span><span class="fc-res-val sans">$' + fmt(r.freight_usd) + '</span></div>';
      html += '<div class="fc-res-divider"></div>';
      html += '<div class="fc-res-row fc-res-bold"><span class="fc-res-label sans">Итого $</span><span class="fc-res-val fc-val-green sans">$' + fmt(r.finalUsd) + '</span></div>';
      html += '<div class="fc-res-row fc-res-bold"><span class="fc-res-label sans">Итого ₽</span><span class="fc-res-val sans">' + fmt(r.finalRub) + ' ₽</span></div>';
      /* Финальная цена двумя частями: сперва до Москвы, потом плечо до города.
         Пока доставка до города не задана, лишних строк не показываем. */
      if (r.cityRub > 0) {
        html += '<div class="fc-res-row fc-res-bold"><span class="fc-res-label sans">С доставкой до Москвы</span><span class="fc-res-val sans">' + fmt(r.mskRub) + ' ₽</span></div>';
        html += '<div class="fc-res-row fc-res-bold"><span class="fc-res-label sans">Доставка до ' + escH(cityName) + '</span><span class="fc-res-val sans">' + fmt(r.cityRub) + ' ₽</span></div>';
      }
      html += '<div class="fc-res-row fc-res-total"><span class="fc-res-label sans">Всего в ₽ по курсу</span><span class="fc-res-val fc-val-total sans">' + fmt(r.totalRub) + ' ₽</span></div>';
      html += '</div>';
    });

    // Сводный итог
    html += '<div class="fc-res-summary">';
    html += '<div class="fc-res-summary-title sans">ИТОГО ПО КОНТЕЙНЕРУ</div>';
    html += '<div class="fc-res-summary-row"><span class="sans">Долларовая часть:</span><span class="fc-val-green sans">$' + fmt(sumUsd) + '</span></div>';
    html += '<div class="fc-res-summary-row"><span class="sans">Рублёвая часть:</span><span class="sans">' + fmt(sumRub) + ' ₽</span></div>';
    if (sumCity > 0) {
      html += '<div class="fc-res-summary-row"><span class="sans">Цена с доставкой до Москвы:</span><span class="sans">' + fmt(sumMsk) + ' ₽</span></div>';
      html += '<div class="fc-res-summary-row"><span class="sans">Доставка до ' + escH(cityName) + ' (' + fmt(cityRate,0) + ' ₽/м³ × ' + fmt(totalVol,2) + ' м³):</span><span class="sans">' + fmt(sumCity) + ' ₽</span></div>';
    }
    html += '<div class="fc-res-summary-total"><span class="sans">' + (sumCity > 0 ? ('Всего с доставкой до ' + escH(cityName)) : 'Всего в рублях по курсу') + '</span><span class="fc-val-total-big sans">' + fmt(sumTotal) + ' ₽</span></div>';
    html += '<div class="fc-res-summary-note sans">Категория: ' + g('fc-cat').value + ' · ' + cat.sign + '</div>';
    html += '</div>';

    g('fc-results-content').innerHTML = html;
    g('fc-results-wrap').className = '';
  }

  // Навешиваю пересчёт на все инпуты
  ['fc-container','fc-containerWt','fc-freight','fc-cny','fc-usd','fc-duty','fc-vat',
   'fc-station','fc-broker','fc-delivery','fc-transfer','fc-cityrate','fc-cityname'].forEach(function(id) {
    var _e = g(id); if (_e) _e.addEventListener('input', fcCalc);
  });
  window.ITC_CATS_FULL = CATS;
  window.ITC_REFRESH_FULL = function(){ try{ fcCalc(); }catch(e){} };

  // Переключение вкладок
  document.querySelectorAll('#section-fullcalc .fc-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
      document.querySelectorAll('#section-fullcalc .fc-tab').forEach(function(t) { t.classList.remove('active'); });
      tab.classList.add('active');
      var target = tab.dataset.tab;
      g('fc-calc').classList.toggle('hidden', target !== 'fc-calc');
      g('fc-ref').classList.toggle('hidden', target !== 'fc-ref');
    });
  });

  // Переключатель валюты инвойса
  document.querySelectorAll('#section-fullcalc [data-cur]').forEach(function(b){
    b.addEventListener('click', function(){ fcSetCur(b.getAttribute('data-cur')); fcCalc(); });
  });
  // Переключатель страховки
  document.querySelectorAll('#section-fullcalc [data-ins]').forEach(function(b){
    b.addEventListener('click', function(){
      var v = b.getAttribute('data-ins');
      g('fc-ins').value = v;
      var all = document.querySelectorAll('#fc-ins-switch .cur-btn');
      for (var i=0;i<all.length;i++) all[i].className = 'cur-btn' + (all[i].getAttribute('data-ins')===v ? ' active' : '');
      fcCalc();
    });
  });

  // Добавить первую позицию при открытии
  if (items.length === 0) {
    fcAddItem();
  }

  renderItems();

  // Кнопка сохранения полного расчёта
  /* ДАННЫЕ ПОЛНОГО РАСЧЁТА ДЛЯ СОХРАНЕНИЯ.
     Раньше это была функция saveFullCalc, которая звала openSaveModal —
     функцию, которой в программе НЕТ НИ ОДНОЙ. То есть код был мёртвый: его
     никто не вызывал, а если бы вызвал — он бы упал. Сохранением всё это время
     занимался запасной кусок в другом месте, который читал поля позиции ПО
     ПОРЯДКУ и знал только название, объём и цену. После того как порядок полей
     поменялся, он стал писать количество в графу объёма, а цену терять вовсе.
     Теперь это просто сборщик данных, и его вызывает настоящее сохранение. */
  window.ITC_FULLCALC_DATA = function() {
    return (function() {
      var F_usd = gv('fc-freight');
      var cnyRate = gv('fc-cny');
      var usdRate = gv('fc-usd');
      var invRate = (FC_CUR === 'USD') ? usdRate : cnyRate;
      var cbmRate = gv('fc-cbmrate') || 150;
      var chinaLeg = gv('fc-china');
      var payRate  = gv('fc-payrate') || usdRate;
      var payFee   = gv('fc-payfee') / 100;
      var expFee   = gv('fc-export');
      var insRate  = gv('fc-ins') / 100;
      var catCode = g('fc-cat').value;
      var containerCbm = gv('fc-container') || 68;
      /* КОЛИЧЕСТВО УЧИТЫВАЕТСЯ И ЗДЕСЬ.
         Сохранение считало итоги по значениям ЗА ОДНУ ШТУКУ, не умножая их на
         количество: на экране была одна сумма, а в историю уходила другая —
         при партии в 700 штук расхождение семисоткратное. Собираем такой же
         рабочий список, как в fcCalc. */
      var _wk = items.map(function(it){
        var q = (parseFloat(it.qty) > 0 ? parseFloat(it.qty) : 1);
        return { name: it.name, qty: q,
                 vol: (parseFloat(it.vol)||0)*q,
                 inv: (parseFloat(it.inv)||0)*q,
                 wt:  (parseFloat(it.wt) ||0)*q };
      });
      var totalVol = _wk.reduce(function(s,it){return s+it.vol;},0);

      // Пересчёт итогов
      var sumUsd = 0, sumRub = 0, sumTotal = 0;
      var duty = gv('fc-duty')/100, vat = gv('fc-vat')/100;
      var station = gv('fc-station'), broker = gv('fc-broker'), delivery = gv('fc-delivery');
      var transferRate = gv('fc-transfer')/100;
      var cat = CATS[catCode];

      _wk.forEach(function(it) {
        /* Доля — как в калькуляторе: что больше, объём или вес.
           Грузоподъёмность читаем с экрана: в этой области видимости
           переменной containerWt нет, и обращение к ней роняло СОХРАНЕНИЕ
           целиком — запись уходила в историю обрезанной, без ставок и без
           чисел позиций. Именно поэтому «Изменить» открывало пустые поля. */
        var _cwt = gv('fc-containerWt') || 0;
        var shareC = Math.max(containerCbm > 0 ? it.vol / containerCbm : 0,
                              (_cwt > 0 && it.wt > 0) ? it.wt / _cwt : 0);
        var T_rub = it.inv * invRate;
        var d = T_rub * duty;
        /* НДС берём ОТ СТОИМОСТИ ТОВАРА — так же, как на экране расчёта.
           Здесь стояло (товар + пошлина), из-за чего сохранённый итог не
           совпадал с показанным. */
        var vg = T_rub * vat;
        var ins = T_rub * insRate;
        var rowRate = usdRate;
        var fi_usd = F_usd * shareC;
        var fi_rub = fi_usd * usdRate;
        var vf = (fi_rub / 2) * vat;   // НДС только на китайское плечо — как на экране
        var st = station * shareC, br = broker * shareC, dv = delivery * shareC;
        var fUsd, fRub;
        if (cat.group === 'AB') {
          fUsd = fi_usd * (1 + cat.markup) * (1 + transferRate);
          fRub = (T_rub + d + vg + ins + vf + st + br + dv) * (1 + cat.markup) * (1 + transferRate);
        } else if (cat.group === 'AUTO') {
          var aUsd = (it.vol||0) * cbmRate * (1 + payFee) * (1 + cat.autoMarkup);
          var vAuto = (aUsd * payRate / 2) * vat;
          fUsd = aUsd * (1 + transferRate);
          var chi = chinaLeg * invRate * shareC;
          var exi = expFee * invRate * shareC;
          fRub = ((T_rub + d + vg + ins + vAuto + chi + exi) * (1 + cat.autoMarkup) + (st + br + dv) * (1 + cat.autoMarkup)) * (1 + transferRate);
          rowRate = payRate;
        } else {
          fUsd = fi_usd * cat.coef * (1 + transferRate);
          fRub = ((T_rub + d + vg + ins + vf) * (1 + cat.goodsMarkup) + (st + br + dv) * cat.coef) * (1 + transferRate);
        }
        sumUsd += fUsd;
        sumRub += fRub;
        sumTotal += fUsd * rowRate + fRub;
      });

      /* СОХРАНЯЕМ ВЕСЬ РАСЧЁТ, а не огрызок. Раньше в историю уходили только
         категория, курсы, фрахт и три поля позиции — открыть запись и получить
         тот же результат было невозможно. Теперь пишем всё, включая валюту
         инвойса: без неё завтра не понять, в чём считали. */
      /* Доставка до города назначения — тоже часть расчёта, иначе открытая
         запись дала бы другую сумму. */
      var _cityRate = gv('fc-cityrate');
      var _cityName = (function(){ var e = g('fc-cityname'); return (e && e.value.trim()) || 'Симферополь'; })();
      var _cityRub = (cat && cat.group === 'AUTO') ? totalVol * _cityRate : 0;
      return {
        type: 'full',
        cur: FC_CUR,
        category: catCode,
        cityRate: _cityRate,
        cityName: _cityName,
        cbmRate: cbmRate,
        chinaLeg: chinaLeg,
        payRate: payRate,
        payFee: payFee * 100,
        expFee: expFee,
        insRate: insRate * 100,
        freight: F_usd,
        cnyRate: cnyRate,
        usdRate: usdRate,
        containerCbm: containerCbm,
        containerWt: gv('fc-containerWt') || 0,
        /* Здесь стояло dutyRate и vatRate — таких переменных в этой функции
           НЕТ, они живут внутри fcCalc и сюда не видны. Сохранение падало на
           этой строке ЦЕЛИКОМ, и запись уходила в историю обрезанной: без
           ставок, без грузоподъёмности и без чисел в позициях. Открытая на
           правку, она и показывала пустые поля. */
        duty: duty * 100,
        vat: vat * 100,
        station: station,
        broker: broker,
        delivery: delivery,
        transfer: transferRate * 100,
        items: items.map(function(it){ return {name:it.name, qty:(it.qty||1), vol:it.vol, invCny:it.inv, wt:(it.wt||0)}; }),
        finalUsd: Math.round(sumUsd),
        finalRub: Math.round(sumRub),
        totalRub: Math.round(sumTotal + _cityRub),
      };
    })();
  };

})();
  </script>

  <div style="padding:0 16px 20px">
    <button class="btn-save-calc sans" data-action="save-full">Сохранить расчёт</button>
    <button class="btn-kp sans" data-action="make-kp">Создать КП для клиента</button>
  </div>

</div>


<!-- СОХРАНЕНИЕ / ИСТОРИЯ / ПОДЕЛИТЬСЯ -->

<!-- Оверлей: ввод имени расчёта -->
<div id="sv-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9000;align-items:flex-end;justify-content:center;padding:0">
  <div style="background:#fff;border-radius:20px 20px 0 0;padding:24px 20px 40px;width:100%;max-width:520px;box-sizing:border-box">
    <p style="font-family:'Times New Roman',serif;font-size:1.2rem;font-weight:700;color:#112C93;margin:0 0 16px">💾 Сохранить расчёт</p>
    <p style="font-size:11px;color:#6b7a99;text-transform:uppercase;letter-spacing:.05em;margin:0 0 6px;font-family:sans-serif">Название / клиент</p>
    <input id="sv-name" type="text" maxlength="80" placeholder="Например: ООО Ромашка, партия 3"
      style="width:100%;border:2px solid #112C93;border-radius:10px;padding:12px;font-size:16px;font-family:sans-serif;color:#112C93;font-weight:600;box-sizing:border-box;outline:none;display:block;margin-bottom:8px">
    <p id="sv-date" style="font-size:12px;color:#9aabcc;font-family:sans-serif;margin:0 0 20px"></p>
    <div style="display:flex;gap:8px">
      <button id="sv-cancel" style="background:#f0f2f8;color:#6b7a99;border:none;border-radius:10px;padding:13px 16px;font-size:15px;font-family:sans-serif;cursor:pointer;white-space:nowrap">Отмена</button>
      <button id="sv-ok" style="flex:1;background:#112C93;color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-family:sans-serif;font-weight:700;cursor:pointer">Сохранить</button>
      <button id="sv-share" style="flex:1;background:#128A04;color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-family:sans-serif;font-weight:700;cursor:pointer">Поделиться</button>
    </div>
  </div>
</div>

<!-- Оверлей: текст для копирования (десктоп share) -->
<div id="cp-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9001;align-items:center;justify-content:center;padding:16px;box-sizing:border-box">
  <div style="background:#fff;border-radius:16px;padding:20px;width:100%;max-width:480px;max-height:85vh;display:flex;flex-direction:column;box-sizing:border-box">
    <p style="font-family:'Times New Roman',serif;font-size:1.1rem;font-weight:700;color:#112C93;margin:0 0 12px">Поделиться расчётом</p>
    <p style="font-family:sans-serif;font-size:12px;color:#6b7a99;margin:0 0 8px">Скопируйте текст и вставьте в WhatsApp, Telegram, почту:</p>
    <textarea id="cp-text" readonly style="flex:1;min-height:200px;border:1px solid #dde3ee;border-radius:8px;padding:10px;font-size:12px;font-family:monospace;resize:none;outline:none;color:#333;box-sizing:border-box"></textarea>
    <div style="display:flex;gap:10px;margin-top:12px">
      <button id="cp-copy" style="flex:1;background:#112C93;color:#fff;border:none;border-radius:10px;padding:12px;font-size:14px;font-family:sans-serif;font-weight:700;cursor:pointer">📋 Скопировать</button>
      <button id="cp-close" style="background:#f0f2f8;color:#6b7a99;border:none;border-radius:10px;padding:12px 16px;font-size:14px;font-family:sans-serif;cursor:pointer">Закрыть</button>
    </div>
  </div>
</div>

<!-- Панель истории -->
<div id="hs-panel" style="display:none;position:fixed;inset:0;background:#f4f6fb;z-index:8000;overflow-y:auto">
  <div style="display:flex;align-items:center;gap:.8rem;padding:.6rem 1rem;background:#112C93;position:sticky;top:0;z-index:1">
    <button id="hs-back" style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.4);color:#fff;font-family:sans-serif;font-size:.82rem;font-weight:600;padding:.3rem .9rem;border-radius:7px;cursor:pointer">← Назад</button>
    <span style="color:#fff;font-family:'Times New Roman',serif;font-size:1rem;font-weight:700;flex:1">История расчётов</span>
    <button id="hs-all" style="display:none;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.4);color:#fff;border-radius:7px;padding:4px 10px;font-size:12px;font-family:sans-serif;cursor:pointer;margin-right:6px">Все менеджеры</button><button id="hs-clear" style="background:rgba(255,80,80,.2);border:1px solid rgba(255,120,120,.35);color:#fff;border-radius:7px;padding:4px 10px;font-size:12px;font-family:sans-serif;cursor:pointer">Очистить</button>
  </div>
  <div id="hs-list"></div>
</div>

<!-- Просмотр сохранённого расчёта -->
<div id="vw-overlay" style="display:none;position:fixed;inset:0;background:#f4f6fb;z-index:8500;overflow-y:auto">
  <div style="display:flex;align-items:center;gap:.8rem;padding:.6rem 1rem;background:#112C93;position:sticky;top:0;z-index:1">
    <button id="vw-back" style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.4);color:#fff;font-family:sans-serif;font-size:.82rem;font-weight:600;padding:.3rem .9rem;border-radius:7px;cursor:pointer">← Назад</button>
    <span id="vw-title" style="color:#fff;font-family:'Times New Roman',serif;font-size:1rem;font-weight:700;flex:1"></span>
    <button id="vw-edit-btn" style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.4);color:#fff;border-radius:7px;padding:4px 10px;font-size:12px;font-family:sans-serif;cursor:pointer">Изменить</button>
    <button id="vw-share-btn" style="background:rgba(18,138,4,.3);border:1px solid rgba(18,138,4,.5);color:#fff;border-radius:7px;padding:4px 10px;font-size:12px;font-family:sans-serif;cursor:pointer">Поделиться</button>
  </div>
  <div id="vw-body" style="padding:16px"></div>
</div>

<!-- Редактирование сохранённого расчёта -->
<div id="ed-overlay" style="display:none;position:fixed;inset:0;background:#f4f6fb;z-index:8600;overflow-y:auto">
  <div style="display:flex;align-items:center;gap:.8rem;padding:.6rem 1rem;background:#112C93;position:sticky;top:0;z-index:1">
    <button id="ed-back" style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.4);color:#fff;font-family:sans-serif;font-size:.82rem;font-weight:600;padding:.3rem .9rem;border-radius:7px;cursor:pointer">← Отмена</button>
    <span style="color:#fff;font-family:'Times New Roman',serif;font-size:1rem;font-weight:700;flex:1">Редактирование <span id="ed-ver" style="font-family:sans-serif;font-size:11px;font-weight:400;opacity:.75"></span></span>
    <button id="ed-save" style="background:rgba(18,138,4,.35);border:1px solid rgba(18,138,4,.6);color:#fff;border-radius:7px;padding:4px 12px;font-size:12px;font-weight:700;font-family:sans-serif;cursor:pointer">💾 Сохранить</button>
  </div>
  <div id="ed-body" style="padding:16px"></div>
</div>

<!-- Тост -->
<div id="itc-toast" style="display:none;position:fixed;top:20px;left:50%;transform:translateX(-50%);padding:10px 22px;border-radius:24px;font-family:sans-serif;font-size:.85rem;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.25);white-space:nowrap;max-width:90vw;text-align:center;color:#fff;background:#112C93"></div>

<script>
(function() {

  var STORE_KEY = 'itc_v2';
  var _cb = null;

  function $(id) { return document.getElementById(id); }

  function toast(msg, color, ms) {
    var t = $('itc-toast');
    if (!t) return;
    t.textContent = msg;
    t.style.background = color || '#112C93';
    t.style.display = 'block';
    clearTimeout(t._t);
    t._t = setTimeout(function() { t.style.display = 'none'; }, ms || 3000);
  }

  function fmt(n) {
    try { return Number(n||0).toLocaleString('ru-RU', {maximumFractionDigits:0}); }
    catch(e) { return String(n||0); }
  }

  function escH(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function load() {
    try { return JSON.parse(localStorage.getItem(STORE_KEY) || '[]'); } catch(e) { return []; }
  }

  function save(arr) {
    try { localStorage.setItem(STORE_KEY, JSON.stringify(arr)); } catch(e) {}
  }

  // ---- Открыть модалку сохранения ----
  function openSave(cb) {
    _cb = cb;
    var now = new Date();
    $('sv-date').textContent = now.toLocaleString('ru-RU',
      {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
    $('sv-name').value = '';
    $('sv-name').style.borderColor = '#112C93';
    $('sv-overlay').style.display = 'flex';
    setTimeout(function() { $('sv-name').focus(); }, 150);
  }

  function closeSave() { $('sv-overlay').style.display = 'none'; _cb = null; }

  function doSave(andShare) {
    var name = $('sv-name').value.trim();
    if (!name) { $('sv-name').style.borderColor = '#A11A1A'; $('sv-name').focus(); return; }
    var data = {};
    try { data = _cb ? _cb() : {}; } catch(e) {}
    var now = new Date();
    var rec = {
      id: Date.now().toString(),
      name: name,
      dateStr: now.toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}),
      data: data
    };
    var arr = load();
    arr.unshift(rec);
    if (arr.length > 100) arr = arr.slice(0,100);
    save(arr);
    try { if (window.ecoLog) window.ecoLog('создал', rec); } catch(e) {}
    closeSave();
    if (andShare) { doShare(rec); }
    else { toast('✅ Расчёт "' + name + '" сохранён', '#128A04', 3000); }
  }

  // ---- Поделиться ----
  function makeText(rec) {
    var d = rec.data || {};
    var lines = [
      (d.type==='full' ? 'Полный расчёт' : 'Быстрый расчёт') + ' — ИТК',
      '👤 ' + rec.name,
      '📅 ' + rec.dateStr, '',
    ];
    if (d.category) lines.push('🏷  Категория: ' + d.category);
    if (d.cnyRate)  lines.push('💱  1 ¥ = ' + d.cnyRate + ' ₽  |  1 $ = ' + (d.usdRate||'') + ' ₽');
    if (d.freight)  lines.push('🚢  Фрахт: $' + fmt(d.freight));
    if (d.type==='full' && d.items && d.items.length) {
      lines.push(''); lines.push('ПОЗИЦИИ:');
      d.items.forEach(function(it,i) {
        /* В выгрузке подписываем ту валюту, в которой считали, иначе через
           неделю не понять, что за числа. */
        var _sg = (d.cur === 'USD') ? '$' : '¥';
        lines.push('  '+(i+1)+'. '+(it.name||('Позиция '+(i+1)))+' — '+fmt(it.invCny)+' '+_sg+
                   (it.qty>1?(' × '+fmt(it.qty,0)+' шт'):'')+'  /  '+it.vol+' м³');
      });
    }
    if (d.type==='quick' && d.goods) lines.push('🏭  Товар: '+fmt(d.goods)+' ¥');
    lines.push(''); lines.push('💰 ИТОГ:');
    if (d.finalUsd) lines.push('   💵 Доллары:   $' + fmt(d.finalUsd));
    if (d.finalRub) lines.push('   💴 Рубли:     ' + fmt(d.finalRub) + ' ₽');
    if (d.totalRub) lines.push('   🔷 Всего в ₽: ' + fmt(d.totalRub) + ' ₽');
    lines.push(''); lines.push('──────────────────────');
    lines.push('ООО «Интернациональная Торговая Компания»');
    lines.push('itcpartner.ru  |  +7 (978) 500-44-02');
    return lines.join('\n');
  }

  function doShare(rec) {
    var text = makeText(rec);
    if (navigator.share) {
      navigator.share({ title: 'Расчёт ИТК — ' + rec.name, text: text })
        .then(function() { toast('✅ Отправлено', '#128A04'); })
        .catch(function(e) { if (e.name !== 'AbortError') showCopy(text); });
    } else {
      showCopy(text);
    }
  }

  function showCopy(text) {
    $('cp-text').value = text;
    $('cp-overlay').style.display = 'flex';
  }

  // ---- История ----
  function openHistory() {
    renderHistory();
    if (window.ecoPanelOpen) window.ecoPanelOpen('hs-panel'); else $('hs-panel').style.display = 'block';
    window.scrollTo(0,0);
  }

  window.ITC_REFRESH_HISTORY = function(){ try{ renderHistory(); }catch(e){} };
  /* ЗНАЧКИ ВМЕСТО ЭМОДЗИ.
     Эмодзи рисует система, и у каждой они свои: на телефоне выходили пёстрые
     картинки из чужого набора — карандаш, глазик, календарик. В фирменном
     стиле должны быть тонкие однотонные значки, как в остальной программе. */
  var ICO = {
    pencil: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>',
    eye:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1.5 12S5 5.5 12 5.5 22.5 12 22.5 12 19 18.5 12 18.5 1.5 12 1.5 12z"/><circle cx="12" cy="12" r="3"/></svg>',
    share:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4"/><path d="M8 8l4-4 4 4"/><path d="M4 14v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/></svg>',
    folder: '<svg viewBox="0 0 24 24" width="46" height="46" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="opacity:.5"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>'
  };

  window.ecoRenderHistory = function(){ try{ renderHistory(); }catch(e){} };
  function renderHistory() {
    var el = $('hs-list');
    if (!el) return;
    var arr = load();
    if (!arr.length) {
      el.innerHTML = '<div style="text-align:center;padding:48px 20px;color:#9aabcc;font-family:sans-serif">' + ICO.folder + '<div style="margin-top:10px">Сохранённых расчётов пока нет</div></div>';
      return;
    }
    var h = '';
    arr.forEach(function(r) {
      var d = r.data || {};
      h += '<div style="background:#fff;border-radius:12px;margin:12px 16px;border-left:4px solid #112C93;box-shadow:0 2px 8px rgba(17,44,147,.07)">';
      h += '<div style="display:flex;align-items:flex-start;justify-content:space-between;padding:14px 14px 8px">';
      h += '<div><div style="font-family:Times New Roman,serif;font-size:1rem;font-weight:700;color:#112C93">'+escH(r.name)+'</div>';
      h += '<div style="font-size:11px;color:#9aabcc;font-family:sans-serif;margin-top:3px">'+(d.type==='full'?'Полный расчёт':'Быстрый расчёт')+' · '+r.dateStr+'</div></div>';
      h += '<button data-del="'+r.id+'" style="background:none;border:1px solid #e0e0e0;border-radius:6px;color:#A11A1A;cursor:pointer;padding:4px 8px;font-size:12px">✕</button>';
      h += '</div>';
      h += '<div style="border-top:1px solid #f0f2f8;padding:10px 14px;font-family:sans-serif;font-size:13px">';
      if (d.category) h += '<div style="display:flex;justify-content:space-between;padding:3px 0;color:#6b7a99"><span>Категория</span><span style="font-weight:600;color:#112C93">'+escH(d.category)+'</span></div>';
      if (d.freight)  h += '<div style="display:flex;justify-content:space-between;padding:3px 0;color:#6b7a99"><span>Фрахт</span><span style="font-weight:600;color:#112C93">$'+fmt(d.freight)+'</span></div>';
      if (d.type==='full'&&d.items) h += '<div style="display:flex;justify-content:space-between;padding:3px 0;color:#6b7a99"><span>Позиций</span><span style="font-weight:600;color:#112C93">'+d.items.length+' шт.</span></div>';
      if (d.totalRub) h += '<div style="display:flex;justify-content:space-between;padding:8px 0 3px;border-top:1px solid #e0e6f0;margin-top:4px;font-weight:700"><span>Всего в ₽</span><span style="color:#128A04;font-size:15px">'+fmt(d.totalRub)+' ₽</span></div>';
      h += '</div>';
      h += '<div class="hist-acts">';
      h += '<button data-edit="'+r.id+'" class="hist-btn hist-btn-edit" title="Изменить">' + ICO.pencil + '<span>Изменить</span></button>';
      h += '<button data-view="'+r.id+'" class="hist-btn hist-btn-open">' + ICO.eye + '<span>Открыть</span></button>';
      h += '<button data-share="'+r.id+'" class="hist-btn hist-btn-share">' + ICO.share + '<span>Поделиться</span></button>';
      h += '</div>';
      h += '</div>';
    });
    el.innerHTML = h;
  }

  // ---- Глобальный обработчик кликов ----
  document.addEventListener('click', function(e) {
    var el = e.target;

    // data-action кнопки
    var act = el.closest ? el.closest('[data-action]') : null;
    if (!act) act = el.getAttribute && el.getAttribute('data-action') ? el : null;
    var action = act ? act.getAttribute('data-action') : null;

    if (action === 'save-quick') {
      openSave(function() {
        var gv = function(id,def) { var e=$('section-calc').querySelector('#'+id); return e?(parseFloat(e.value)||def||0):(def||0); };
        var gs = function(id) { var e=$('section-calc').querySelector('#'+id); return e?e.value:''; };
        var nums = document.querySelectorAll('#section-calc .final-num-block .num');
        var finalUsd=0, finalRub=0, totalRub=0;
        if (nums[0]) finalUsd = parseFloat(nums[0].textContent.replace(/[^\d.]/g,''))||0;
        if (nums[1]) finalRub = parseFloat(nums[1].textContent.replace(/[^\d.]/g,''))||0;
        if (nums[2]) totalRub = parseFloat(nums[2].textContent.replace(/[^\d.]/g,''))||0;
        return { type:'quick', category:gs('category'), goods:gv('goods'), freight:gv('freight'),
          cnyRate:gv('kursCny',13.2), usdRate:gv('kurs',95),
          finalUsd:Math.round(finalUsd), finalRub:Math.round(finalRub), totalRub:Math.round(totalRub) };
      });
      return;
    }

    if (action === 'make-kp') { try { kpOpen(); } catch(e) { alert('Не удалось открыть КП'); } return; }
    if (action === 'save-full') {
      openSave(function() {
        /* СНАЧАЛА СПРАШИВАЕМ САМ КАЛЬКУЛЯТОР. Он знает про позицию всё:
           количество, цену, объём, вес — и все ставки расчёта. Читать поля
           по порядку, как делалось ниже, нельзя: порядок меняется, и тогда
           количество уезжает в объём, а цена теряется. */
        try {
          if (window.ITC_FULLCALC_DATA) {
            var _d = window.ITC_FULLCALC_DATA();
            if (_d && _d.items) return _d;
          }
        } catch (e) {}
        /* Запасной путь — только если сборщик почему-то недоступен. Здесь
           поля ищутся по своим классам, а не по номеру в ряду. */
        var gv = function(id,def) { var e=$(id); return e?(parseFloat(e.value)||def||0):(def||0); };
        var items = [];
        document.querySelectorAll('#fc-items-list .fc-item').forEach(function(item) {
          var nameEl = item.querySelector('.fc-item-name');
          var num = function(sel) { var e = item.querySelector(sel); return e ? (parseFloat(e.value) || 0) : 0; };
          items.push({ name: nameEl ? nameEl.value : '',
                       qty:    num('[id^="fi-qty-"]')  || 1,
                       vol:    num('[id^="fi-vol-"]'),
                       invCny: num('[id^="fi-inv-"]'),
                       wt:     num('[id^="fi-wt-"]') });
        });
        var nums = document.querySelectorAll('#section-fullcalc .fc-res-summary-row span:last-child, #section-fullcalc .fc-val-total-big');
        var totalRub = 0;
        var bigEl = document.querySelector('#section-fullcalc .fc-val-total-big');
        if (bigEl) totalRub = parseFloat(bigEl.textContent.replace(/[^\d.]/g,''))||0;
        var usdEl = document.querySelector('#section-fullcalc .fc-val-green.sans');
        var finalUsd = usdEl ? parseFloat(usdEl.textContent.replace(/[^\d.]/g,''))||0 : 0;
        return { type:'full',
          category: $('fc-cat') ? $('fc-cat').value : '',
          freight: gv('fc-freight'), cnyRate: gv('fc-cny',13.2), usdRate: gv('fc-usd',95),
          containerCbm: gv('fc-container',68), items: items,
          finalUsd: Math.round(finalUsd), finalRub: 0, totalRub: Math.round(totalRub) };
      });
      return;
    }

    if (action === 'open-history') { openHistory(); return; }

    // Кнопки внутри модалок
    if (el.id === 'sv-cancel' || (el.closest && el.closest('#sv-overlay') === $('sv-overlay') && el === $('sv-overlay'))) {
      closeSave(); return;
    }
    if (el.id === 'sv-cancel') { closeSave(); return; }
    if (el.id === 'sv-ok') { doSave(false); return; }
    if (el.id === 'sv-share') { doSave(true); return; }
    if (el.id === 'cp-copy') {
      var ta = $('cp-text'); ta.select();
      try { document.execCommand('copy'); }
      catch(ex) { if(navigator.clipboard) navigator.clipboard.writeText(ta.value); }
      toast('Скопировано! Вставьте в мессенджер или письмо', '#112C93', 4000);
      setTimeout(function() { $('cp-overlay').style.display='none'; }, 1800);
      return;
    }
    if (el.id === 'cp-close') { $('cp-overlay').style.display='none'; return; }
    if (el.id === 'hs-back') { window.ecoPanelClose('hs-panel'); return; }
    if (el.id === 'hs-clear') {
      if (confirm('Удалить все расчёты?')) { localStorage.removeItem(STORE_KEY); renderHistory(); }
      return;
    }

    // ---- Редактирование записи ----
    if (el.id === 'ed-back' || (el.closest && el.closest('#ed-back'))) { window.ecoPanelClose('ed-overlay'); return; }
    if (el.id === 'ed-save' || (el.closest && el.closest('#ed-save'))) { saveEdit(); return; }
    if (el.closest && el.closest('#ed-add-item')) {
      var edCont = $('ed-items');
      if (edCont) { var ei = edCont.querySelectorAll('.ed-item').length; edCont.insertAdjacentHTML('beforeend', edItemRow({}, ei, _edCur)); }
      return;
    }
    /* Переключение валюты внутри сохранённой записи: меняем подпись поля цены
       у всех позиций, сами числа не трогаем — как и в калькуляторе. */
    var edCurBtn = el.closest ? el.closest('[data-edcur]') : null;
    if (edCurBtn) {
      _edCur = edCurBtn.getAttribute('data-edcur') === 'USD' ? 'USD' : 'CNY';
      var cb = document.querySelectorAll('#ed-cur-switch .cur-btn');
      for (var ci=0; ci<cb.length; ci++) cb[ci].className = 'cur-btn' + (cb[ci].getAttribute('data-edcur')===_edCur ? ' active' : '');
      var sign = (_edCur === 'USD') ? '$' : '¥';
      var invs = document.querySelectorAll('#ed-items .ed-item-inv');
      for (var ii=0; ii<invs.length; ii++) invs[ii].placeholder = 'Цена 1 шт, ' + sign;
      return;
    }
    /* Пересчёт: собираем то, что сейчас в полях, и показываем новые итоги. */
    if (el.id === 'ed-recalc' || (el.closest && el.closest('#ed-recalc'))) {
      var recR = load().find(function(r){ return r.id === _editId; });
      if (!recR) return;
      var dR = JSON.parse(JSON.stringify(recR.data || {}));
      dR.cur = _edCur;
      var gnum = function(id, def){ var e=$(id); if(!e) return def; var v=parseFloat((e.value||'').replace(',','.')); return isNaN(v)?def:v; };
      if ($('ed-category')) dR.category = $('ed-category').value.trim();
      dR.cnyRate = gnum('ed-cny', dR.cnyRate); dR.usdRate = gnum('ed-usd', dR.usdRate);
      dR.freight = gnum('ed-freight', dR.freight); dR.containerCbm = gnum('ed-container', dR.containerCbm);
      dR.containerWt = gnum('ed-containerWt', dR.containerWt);
      dR.cbmRate = gnum('ed-cbmrate', dR.cbmRate);
      dR.chinaLeg = gnum('ed-china', dR.chinaLeg);
      dR.payRate = gnum('ed-payrate', dR.payRate); dR.payFee = gnum('ed-payfee', dR.payFee);
      dR.expFee = gnum('ed-export', dR.expFee); dR.insRate = gnum('ed-ins', dR.insRate);
      dR.duty = gnum('ed-duty', dR.duty); dR.vat = gnum('ed-vat', dR.vat);
      dR.station = gnum('ed-station', dR.station); dR.broker = gnum('ed-broker', dR.broker);
      dR.delivery = gnum('ed-delivery', dR.delivery); dR.transfer = gnum('ed-transfer', dR.transfer);
      var itR = [];
      document.querySelectorAll('#ed-items .ed-item').forEach(function(row){
        var num = function(sel, def){ var e=row.querySelector(sel); if(!e) return def; var v=parseFloat((e.value||'').replace(',','.')); return isNaN(v)?def:v; };
        var nmE = row.querySelector('.ed-item-name');
        itR.push({ name: nmE?nmE.value.trim():'', qty: num('.ed-item-qty',1), vol: num('.ed-item-vol',0),
                   invCny: num('.ed-item-inv',0), wt: num('.ed-item-wt',0) });
      });
      dR.items = itR;
      var rcR = edRecalc(dR);
      if (!rcR) { toast('Не удалось пересчитать: не выбрана категория', '#A11A1A', 3000); return; }
      if ($('ed-finalUsd')) $('ed-finalUsd').value = rcR.finalUsd;
      if ($('ed-finalRub')) $('ed-finalRub').value = rcR.finalRub;
      if ($('ed-totalRub')) $('ed-totalRub').value = rcR.totalRub;
      toast('Пересчитано · итого ' + rcR.totalRub.toLocaleString('ru-RU') + ' ₽', '#128A04', 3000);
      return;
    }
    var edDelItem = el.closest ? el.closest('[data-ed-del-item]') : null;
    if (edDelItem) { var edRow = edDelItem.closest('.ed-item'); if (edRow) edRow.remove(); return; }
    if (el.id === 'vw-edit-btn' || (el.closest && el.closest('#vw-edit-btn'))) { if (_viewRec) openEdit(_viewRec); return; }
    var editBtn = el.closest ? el.closest('[data-edit]') : null;
    if (editBtn) {
      var eid = editBtn.getAttribute('data-edit');
      var recE = load().find(function(r) { return r.id === eid; });
      if (recE) openEdit(recE);
      return;
    }

    // Кнопки внутри истории (удалить / открыть / поделиться).
    // Используем closest, чтобы тап по иконке или тексту внутри кнопки
    // тоже срабатывал на любых устройствах.
    var delBtn = el.closest ? el.closest('[data-del]') : null;
    if (delBtn) {
      var did = delBtn.getAttribute('data-del');
      var _delRec = load().filter(function(r) { return r.id === did; })[0];
      var arr = load().filter(function(r) { return r.id !== did; });
      save(arr);
      try { if (window.ecoLog) window.ecoLog('удалил', _delRec || { id: did }); } catch(e) {}
      renderHistory(); return;
    }
    var viewBtn = el.closest ? el.closest('[data-view]') : null;
    if (viewBtn) {
      var vid = viewBtn.getAttribute('data-view');
      var recV = load().find(function(r) { return r.id === vid; });
      if (recV) openView(recV);
      return;
    }
    var shrBtn = el.closest ? el.closest('[data-share]') : null;
    if (shrBtn) {
      var sid = shrBtn.getAttribute('data-share');
      var recS = load().find(function(r) { return r.id === sid; });
      if (recS) doShare(recS);
      return;
    }
  });

  // ---- Просмотр расчёта ----
  var _viewRec = null;

  /* Отдаём просмотр наружу: общий журнал менеджеров лежит в другом блоке
     скрипта и своей отрисовки записи не имеет. */
  window.ecoOpenView = function(rec) { try { openView(rec); } catch (e) {} };
  function openView(rec) {
    _viewRec = rec;
    var d = rec.data || {};
    $('vw-title').textContent = rec.name;

    var typeLabel = d.type === 'full' ? 'Полный расчёт' : 'Быстрый расчёт';
    var h = '';

    /* СТАРАЯ ЗАПИСЬ ИЛИ НОВАЯ.
       До 13.08.2026 сохранение писало только название, объём и цену — без
       количества, веса и ставок. Такие записи узнаём по тому, что в них нет
       ни валюты, ни пошлины, ни НДС: раньше их просто не записывали. Честно
       говорим об этом, иначе нули в позициях выглядят поломкой программы. */
    var _oldRec = (d.type === 'full') && !d.cur && !d.duty && !d.vat && !d.containerWt;
    var _oldNote = '<div style="background:#FFF6E4;border-left:4px solid #C98A16;border-radius:10px;padding:12px 14px;margin-bottom:12px;font-family:sans-serif;font-size:13px;color:#7A5205;line-height:1.45">'
      +  '<b>Запись сохранена старой версией.</b><br>'
      +  'Количество, вес и ставки в ней не сохранялись — поэтому здесь нули, '
      +  'а в объёме может стоять количество. Итоговая сумма верна: она записана '
      +  'отдельным числом. Чтобы данные появились, расчёт нужно собрать и сохранить заново.'
      +  '</div>';
    /* Плашку ставим и наверху, и ПРЯМО НАД ПОЗИЦИЯМИ. Наверху её легко
       прокрутить мимо, а вопросы вызывают именно нули в позициях. */
    if (_oldRec) h += _oldNote;

    // Шапка: дата и тип
    h += '<div style="background:#fff;border-radius:12px;padding:14px 16px;margin-bottom:12px;border-left:4px solid #112C93">';
    h += '<div style="font-family:sans-serif;font-size:11px;color:#9aabcc;margin-bottom:4px">'+typeLabel+'</div>';
    h += '<div style="font-family:Times New Roman,serif;font-size:1.15rem;font-weight:700;color:#112C93">'+escH(rec.name)+'</div>';
    h += '<div style="font-family:sans-serif;font-size:12px;color:#9aabcc;margin-top:4px">'+rec.dateStr+'</div>';
    h += '</div>';

    // Параметры
    h += '<div style="background:#fff;border-radius:12px;padding:14px 16px;margin-bottom:12px">';
    h += '<div style="font-family:sans-serif;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7a99;margin-bottom:10px">Параметры</div>';
    function row(label, val) {
      return '<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f4f6fb;font-family:sans-serif;font-size:13px"><span style="color:#6b7a99">'+label+'</span><span style="font-weight:600;color:#112C93">'+val+'</span></div>';
    }
    /* Показываем ВСЕ ставки расчёта, а не пять из двадцати: раньше просмотр
       умалчивал про пошлину, НДС, брокера, комиссию и доставку до города —
       по нему нельзя было проверить, из чего сложилась цена. */
    var _isAuto = /^D\d/.test(String(d.category || ''));
    var _cs = (d.cur === 'USD') ? '$' : '¥';
    if (d.category)    h += row('Категория', escH(d.category));
    if (d.cur)         h += row('Валюта инвойса', d.cur === 'USD' ? '$ доллары' : '¥ юани');
    if (d.cnyRate)     h += row('Курс юань → рубль', d.cnyRate + ' ₽/¥');
    if (d.usdRate)     h += row('Курс доллар → рубль', d.usdRate + ' ₽/$');
    if (!_isAuto && d.freight) h += row('Фрахт', '$' + fmt(d.freight));
    if (d.containerCbm) h += row(_isAuto ? 'Ёмкость еврофуры' : 'Контейнер', d.containerCbm + ' м³');
    if (d.containerWt)  h += row('Грузоподъёмность', fmt(d.containerWt) + ' кг');
    if (_isAuto && d.cbmRate) h += row('Ставка авто за 1 м³', '$' + fmt(d.cbmRate));
    if (_isAuto && d.payRate) h += row('Курс оплаты логистики', d.payRate + ' ₽/$');
    if (_isAuto && d.payFee)  h += row('Комиссия за оплату логистики', d.payFee + ' %');
    if (_isAuto && d.chinaLeg) h += row('Доставка по Китаю', fmt(d.chinaLeg) + ' ' + _cs);
    if (_isAuto && d.expFee)   h += row('Экспортное оформление', fmt(d.expFee) + ' ' + _cs);
    if (d.duty)        h += row('Пошлина', d.duty + ' %');
    if (d.vat)         h += row('НДС', d.vat + ' %');
    if (d.insRate)     h += row('Страховка от товара', d.insRate + ' %');
    if (!_isAuto && d.station) h += row('Станция', fmt(d.station) + ' ₽');
    if (d.broker)      h += row('Брокер на поставку', fmt(d.broker) + ' ₽');
    if (d.delivery)    h += row(_isAuto ? 'Перегрузка и перевозка по Москве' : 'Доставка до склада', fmt(d.delivery) + ' ₽');
    if (_isAuto && d.cityRate) h += row('Доставка до ' + escH(d.cityName || 'города'), fmt(d.cityRate) + ' ₽/м³');
    if (d.transfer)    h += row('Конверсия/перевод', d.transfer + ' %');
    if (d.type==='quick' && d.goods) h += row('Стоимость товара', fmt(d.goods) + ' ' + _cs);
    h += '</div>';

    // Позиции (полный расчёт)
    if (d.type === 'full' && d.items && d.items.length) {
      h += '<div style="background:#fff;border-radius:12px;padding:14px 16px;margin-bottom:12px">';
      h += '<div style="font-family:sans-serif;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7a99;margin-bottom:10px">Товарные позиции</div>';
      if (_oldRec) h += _oldNote;
      /* ПО КАЖДОЙ ПОЗИЦИИ — ВСЁ, ЧТО ВВОДИЛИ.
         Раньше здесь показывались только объём и цена ОДНОЙ штуки, без
         количества и веса — по такому просмотру нельзя было понять, что за
         поставка. Теперь видно и за штуку, и за всю партию. */
      var _sign = (d.cur === 'USD') ? '$' : '¥';
      d.items.forEach(function(it, i) {
        var q  = (parseFloat(it.qty) > 0 ? parseFloat(it.qty) : 1);
        var v  = parseFloat(it.vol) || 0;
        var pr = parseFloat(it.invCny) || 0;
        var w  = parseFloat(it.wt) || 0;
        function cell(lab, one, all, unit) {
          return '<div style="flex:1 1 150px;min-width:0">'
               +   '<div style="font-size:10.5px;color:#9aabcc;text-transform:uppercase;letter-spacing:.04em">'+lab+'</div>'
               +   '<div style="font-size:13px;color:#112C93;font-weight:700">'+one+' '+unit+'</div>'
               +   '<div style="font-size:12px;color:#6b7a99">за всё: '+all+' '+unit+'</div>'
               + '</div>';
        }
        h += '<div style="padding:10px 0;border-bottom:1px solid #f4f6fb">';
        h += '<div style="font-family:Times New Roman,serif;font-size:.95rem;font-weight:700;color:#112C93;margin-bottom:6px">'+(i+1)+'. '+escH(it.name)+'</div>';
        h += '<div style="display:flex;flex-wrap:wrap;gap:10px 14px;font-family:sans-serif">';
        h += '<div style="flex:1 1 150px;min-width:0"><div style="font-size:10.5px;color:#9aabcc;text-transform:uppercase;letter-spacing:.04em">Количество</div>'
           +   '<div style="font-size:13px;color:#112C93;font-weight:700">'+fmt(q)+' шт</div></div>';
        h += cell('Цена',  fmt(pr),                          fmt(pr * q),                        _sign);
        h += cell('Объём', (Math.round(v * 1000) / 1000),    (Math.round(v * q * 1000) / 1000),  'м³');
        h += cell('Вес',   (Math.round(w * 1000) / 1000),    (Math.round(w * q * 1000) / 1000),  'кг');
        h += '</div>';
        h += '</div>';
      });
      h += '</div>';
    }

    // Итоги
    h += '<div style="background:#112C93;border-radius:12px;padding:20px;margin-bottom:12px">';
    h += '<div style="font-family:sans-serif;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#4DD637;margin-bottom:14px">Итоговая стоимость</div>';
    function resRow(label, val, big) {
      var valStyle = big
        ? 'font-family:Courier New,monospace;font-size:1.5rem;font-weight:800;color:#4DD637'
        : 'font-weight:700;color:#fff;font-family:sans-serif;font-size:14px';
      return '<div style="display:flex;justify-content:space-between;align-items:baseline;padding:6px 0;border-bottom:1px solid rgba(255,255,255,.1);font-family:sans-serif;font-size:13px">'
        + '<span style="color:rgba(255,255,255,.7)">'+label+'</span>'
        + '<span style="'+valStyle+'">'+val+'</span></div>';
    }
    if (d.finalUsd) h += resRow('Долларовая часть', '$' + fmt(d.finalUsd), false);
    if (d.finalRub) h += resRow('Рублёвая часть', fmt(d.finalRub) + ' ₽', false);
    if (d.totalRub) h += resRow('Всего в рублях', fmt(d.totalRub) + ' ₽', true);
    h += '</div>';

    $('vw-body').innerHTML = h;
    if (window.ecoPanelOpen) window.ecoPanelOpen('vw-overlay'); else $('vw-overlay').style.display = 'block';
    window.scrollTo(0,0);
  }

  // ---- Редактирование записи истории ----
  var _editId = null;

  /* ПОЛЕ ПРАВКИ — ТЕМИ ЖЕ ПЛИТКАМИ, ЧТО И В КАЛЬКУЛЯТОРЕ.
     Раньше окно правки выглядело совсем иначе: широкие поля в столбик со
     скруглениями. Открывая сохранённый расчёт, человек попадал будто в другую
     программу и искал знакомые поля заново. Теперь разметка ровно та же —
     input-row с подписью сверху, — и оформление подхватывается общими
     правилами, которым добавлен #ed-body. */
  function edField(label, id, value, type, unit, wide) {
    var v = (value == null ? '' : String(value)).replace(/"/g,'&quot;');
    var extra = (type === 'number') ? 'inputmode="decimal" step="any"' : '';
    var align = (type === 'number') ? '' : ' style="text-align:left"';
    return '<div class="input-row' + (wide ? ' wide' : '') + '">'
      + '<label class="input-label sans">'+label+'</label>'
      + '<div class="input-cell">'
      + '<input id="'+id+'" class="num-input sans" type="'+(type||'text')+'" '+extra+' value="'+v+'"'+align+'>'
      + (unit ? '<span class="input-unit sans">'+unit+'</span>' : '')
      + '</div></div>';
  }

  /* ПОЗИЦИЯ В ПРАВКЕ — ТАКАЯ ЖЕ, КАК В КАЛЬКУЛЯТОРЕ.
     Та же рамка fc-item, тот же компактный ряд полей и те же пары
     «за штуку / за всё». Поля с классами ed-item-* читает сохранение —
     их имена не трогаем, парные добавлены рядом. */
  /* Своя копия округления. fcNum объявлена в другом блоке скрипта, и окно
     правки её НЕ ВИДИТ: обращение к ней роняло openEdit, и кнопка «Изменить»
     просто ничего не делала. Функции из калькулятора здесь недоступны — всё,
     что нужно, объявляем рядом. */
  /* ВНИМАНИЕ: имя edNum в этом блоке УЖЕ ЗАНЯТО — ниже есть своя edNum(id),
     которая читает поле по его номеру и возвращает undefined, если поле
     пустое. Объявления функций в JavaScript поднимаются наверх, и побеждает
     ПОСЛЕДНЕЕ: моя функция молча подменялась чужой. Из-за этого в поля
     подставлялось undefined, а числовое поле такое значение отбрасывает —
     графы правки оставались пустыми при целых данных в записи.
     Поэтому имя здесь своё, ни с чем не пересекающееся. */
  function edFmtNum(v) {
    var n = parseFloat(v);
    if (!isFinite(n) || n === 0) return 0;
    return parseFloat(n.toFixed(6));
  }

  function edItemRow(it, i, cur) {
    it = it || {};
    var sign = (cur === 'USD') ? '$' : '¥';
    var nm = String(it.name||'').replace(/"/g,'&quot;');
    var q = (parseFloat(it.qty) > 0 ? parseFloat(it.qty) : 1);
    var vol = parseFloat(it.vol) || 0, inv = parseFloat(it.invCny) || 0, wt = parseFloat(it.wt) || 0;
    function f(cls, lab, val, tot) {
      return '<div class="fc-item-field' + (tot ? ' tot' : '') + '">'
        + '<label class="sans">' + lab + '</label>'
        + '<input class="fc-item-input sans ' + cls + '" type="number" inputmode="decimal" step="any" value="' + edFmtNum(val) + '">'
        + '</div>';
    }
    return '<div class="fc-item ed-item">'
      + '<div class="fc-item-header">'
      +   '<input class="fc-item-name sans ed-item-name" placeholder="Наименование товара ' + (i+1) + '" value="'+nm+'">'
      +   '<button type="button" class="fc-item-del" data-ed-del-item>✕</button>'
      + '</div>'
      /* Порядок и группировка — те же, что в калькуляторе: количество, цена,
         объём, вес; пара «за штуку / за всё» не разрывается при переносе. */
      + '<div class="fc-item-fields">'
      +   '<div class="fc-grp fc-grp-qty">' + f('ed-item-qty', 'Кол-во (шт)', q, false) + '</div>'
      +   '<div class="fc-grp fc-grp-price">'
      +     f('ed-item-inv',  'Цена 1 шт (' + sign + ')',   inv,     false)
      +     f('ed-item-invt', 'Цена за всё (' + sign + ')', inv * q, true)
      +   '</div>'
      +   '<div class="fc-grp fc-grp-vol">'
      +     f('ed-item-vol',  'Объём 1 шт (м³)',    vol,     false)
      +     f('ed-item-volt', 'Объём за всё (м³)',  vol * q, true)
      +   '</div>'
      +   '<div class="fc-grp fc-grp-wt">'
      +     f('ed-item-wt',   'Вес 1 шт (кг)',      wt,      false)
      +     f('ed-item-wtt',  'Вес за всё (кг)',    wt * q,  true)
      +   '</div>'
      + '</div></div>';
  }

  /* Пары «за штуку / за всё» в окне правки пересчитывают друг друга — так же,
     как в калькуляторе. Слушаем всё окно разом: позиции добавляются на ходу. */
  document.addEventListener('input', function(e) {
    var el = e.target;
    if (!el || !el.className || typeof el.className !== 'string') return;
    var box = el.closest ? el.closest('.ed-item') : null;
    if (!box) return;
    var gq = function(c){ return box.querySelector('.' + c); };
    var qEl = gq('ed-item-qty');
    var q = (qEl && parseFloat(qEl.value) > 0) ? parseFloat(qEl.value) : 1;
    var trio = [['ed-item-vol','ed-item-volt'], ['ed-item-inv','ed-item-invt'], ['ed-item-wt','ed-item-wtt']];
    trio.forEach(function(p) {
      var u = gq(p[0]), t = gq(p[1]);
      if (!u || !t) return;
      if (el === t) { u.value = edFmtNum((parseFloat(t.value) || 0) / q); }
      else if (el === u || el === qEl) { if (document.activeElement !== t) t.value = edFmtNum((parseFloat(u.value) || 0) * q); }
    });
  });

  var _edCur = 'CNY';     // валюта открытой в правке записи
  function openEdit(rec) {
    if (!rec) return;
    _editId = rec.id;
    var d = rec.data || {};
    var isFull = d.type === 'full';
    var edCur = (d.cur === 'USD') ? 'USD' : 'CNY';   // валюта записи
    var edCat = (window.ITC_CATS_FULL || {})[d.category];   // чтобы подписать поля по виду перевозки
    _edCur = edCur;
    var h = '';

    h += '<div class="panel"><div class="panel-head sans">'+(isFull?'ПОЛНЫЙ РАСЧЁТ':'БЫСТРЫЙ РАСЧЁТ')+'</div><div class="panel-body grid2">';
    h += edField('Название', 'ed-name', rec.name, 'text', '', true);
    h += edField('Дата / время', 'ed-date', rec.dateStr, 'text', '', true);
    h += '</div></div>';

    h += '<div class="panel"><div class="panel-head sans">ПАРАМЕТРЫ</div><div class="panel-body grid2">';
    /* ПОЛЯ ЗАВИСЯТ ОТ ВИДА ПЕРЕВОЗКИ — как в калькуляторе.
       Раньше окно показывало всё подряд: у расчёта по фуре торчали «Фрахт, $»
       и «Станция» от железной дороги, и было непонятно, тот ли это расчёт.
       Теперь лишнее не рисуется, а подписи меняются на фуру. */
    var edAuto = !!(edCat && edCat.group === 'AUTO');
    h += edField('Категория' + (edCat ? ' · ' + edCat.sign : ''), 'ed-category', d.category, 'text', '', true);
    h += edField('Курс юань → рубль', 'ed-cny', d.cnyRate, 'number', '₽/¥');
    h += edField('Курс доллар → рубль', 'ed-usd', d.usdRate, 'number', '₽/$');
    if (!edAuto) h += edField('Фрахт', 'ed-freight', d.freight, 'number', '$');
    if (isFull || d.containerCbm != null) h += edField(edAuto ? 'Ёмкость еврофуры' : 'Контейнер', 'ed-container', d.containerCbm, 'number', 'м³');
    if (!isFull) h += edField('Стоимость товара, ¥', 'ed-goods', d.goods, 'number');
    if (isFull) {
      /* Валюта инвойса и остальные ставки. Раньше их тут не было вовсе, и
         открытая запись не давала того же расчёта. */
      h += '<div class="input-row wide cur-row-hl"><label class="input-label sans">Валюта инвойса — в чём указана цена товара</label>'
        +  '<div class="cur-switch sans" id="ed-cur-switch">'
        +    '<button type="button" class="cur-btn'+(edCur!=='USD'?' active':'')+'" data-edcur="CNY">¥ юани</button>'
        +    '<button type="button" class="cur-btn'+(edCur==='USD'?' active':'')+'" data-edcur="USD">$ доллары</button>'
        +  '</div></div>';
      h += edField(edAuto ? 'Грузоподъёмность еврофуры' : 'Грузоподъёмность', 'ed-containerWt', d.containerWt, 'number', 'кг');
      if (edAuto) {
        h += edField('Ставка авто за 1 м³', 'ed-cbmrate', (d.cbmRate != null ? d.cbmRate : 150), 'number', '$');
        h += edField('Доставка по Китаю (фабрика → склад)', 'ed-china', (d.chinaLeg != null ? d.chinaLeg : 0), 'number', (edCur === 'USD' ? '$' : '¥'));
        h += edField('Курс оплаты логистики', 'ed-payrate', (d.payRate != null ? d.payRate : d.usdRate), 'number', '₽/$');
        h += edField('Комиссия за оплату логистики', 'ed-payfee', (d.payFee != null ? d.payFee : 6), 'number', '%');
        h += edField('Экспортное оформление в Китае', 'ed-export', (d.expFee != null ? d.expFee : 0), 'number', (edCur === 'USD' ? '$' : '¥'));
      }
      h += edField('Пошлина', 'ed-duty', d.duty, 'number', '%');
      h += edField('НДС', 'ed-vat', d.vat, 'number', '%');
      if (!edAuto) h += edField('Станция', 'ed-station', d.station, 'number', '₽');
      h += edField('Брокер (₽ на поставку)', 'ed-broker', d.broker, 'number', '₽');
      /* У автодоставки это плечо по Москве: перегрузка на терминале и перевозка
         до транспортной компании клиента. */
      h += edField(edAuto ? 'Перегрузка и перевозка по Москве' : 'Доставка до склада',
                   'ed-delivery', d.delivery, 'number', '₽');
      h += edField('Конверсия/перевод', 'ed-transfer', d.transfer, 'number', '%');
      /* Доставка от Москвы до города назначения — только у автоперевозки */
      if (edAuto) {
        h += edField('Доставка до города', 'ed-cityname', (d.cityName || 'Симферополь'), 'text');
        h += edField('Ставка до города', 'ed-cityrate', (d.cityRate != null ? d.cityRate : 6200), 'number', '₽/м³');
      }
    }
    if (isFull) {
      h += edField('Страховка от товара', 'ed-ins', (d.insRate != null ? d.insRate : 0), 'number', '%');
    }
    h += '</div></div>';

    if (isFull) {
      h += '<div class="panel"><div class="panel-head sans">ТОВАРНЫЕ ПОЗИЦИИ</div><div class="panel-body" style="padding:12px">';
      h += '<div id="ed-items">';
      (d.items||[]).forEach(function(it,i){ h += edItemRow(it,i,edCur); });
      h += '</div>';
      /* Если позиций в записи нет вовсе — скажем об этом прямо, а не покажем
         пустое место, по которому непонятно, потерялись данные или их не было. */
      if (!(d.items && d.items.length)) {
        h += '<div style="padding:10px 2px;font-family:sans-serif;font-size:13px;color:#7A5205;background:#FFF6E4;border-left:4px solid #C98A16;border-radius:8px;padding:12px 14px">'
          +  'В этой записи товарных позиций не сохранено. Добавьте их кнопкой ниже.'
          +  '</div>';
      }
      h += '<button type="button" id="ed-add-item" class="fc-add-btn sans">&#43; Добавить позицию</button>';
      h += '</div></div>';
    }

    h += '<div class="panel"><div class="panel-head sans">ИТОГИ (СНИМОК)</div><div class="panel-body grid2">';
    h += edField('Долларовая часть', 'ed-finalUsd', d.finalUsd, 'number', '$');
    h += edField('Рублёвая часть', 'ed-finalRub', d.finalRub, 'number', '₽');
    h += edField('Всего в рублях', 'ed-totalRub', d.totalRub, 'number', '₽', true);
    h += '</div><div style="padding:12px">';
    if (isFull) {
      h += '<button type="button" id="ed-recalc" style="width:100%;background:#112C93;border:0;border-radius:9px;padding:12px;color:#fff;font-family:sans-serif;font-size:14px;font-weight:700;cursor:pointer;margin-top:6px">Пересчитать по этим данным</button>';
      h += '<div style="font-family:sans-serif;font-size:11px;color:#9aabcc;margin-top:6px;line-height:1.5">Итоги пересчитываются по той же формуле, что и в калькуляторе. Нажмите «Пересчитать», если меняли параметры или позиции.</div>';
    } else {
      h += '<div style="font-family:sans-serif;font-size:11px;color:#9aabcc;margin-top:4px;line-height:1.5">Итоги хранятся как снимок.</div>';
    }
    h += '</div></div>';

    $('ed-body').innerHTML = h;

    /* ЗНАЧЕНИЯ ПОЗИЦИЙ ПРОСТАВЛЯЕМ ЕЩЁ РАЗ, УЖЕ ПО ЖИВЫМ ПОЛЯМ.
       В разметке значения есть — это проверено, — но поле type="number"
       умеет отбросить значение из разметки, если оно ему не нравится
       (запятая, пробел, длинный хвост после точки). Тогда графа выглядит
       пустой, хотя данные в записи целы. Поэтому сразу после отрисовки
       раскладываем числа по полям напрямую. */
    /* Номер версии прямо в шапке правки: по скриншоту сразу видно, какая
       версия его нарисовала, и не приходится гадать, обновилось ли. */
    try {
      var _v = $('ed-ver');
      if (_v) _v.textContent = 'v' + (window.APP_VERSION || '') + ' · позиций: ' + ((d.items || []).length);
    } catch (e) {}

    try {
      var _rows = $('ed-body').querySelectorAll('#ed-items .ed-item');
      (d.items || []).forEach(function(it, i) {
        var row = _rows[i]; if (!row) return;
        var q  = (parseFloat(it.qty) > 0 ? parseFloat(it.qty) : 1);
        var v  = parseFloat(it.vol) || 0;
        var pr = parseFloat(it.invCny) || 0;
        var w  = parseFloat(it.wt) || 0;
        function put(cls, val) {
          var e = row.querySelector('.' + cls);
          if (e) e.value = edFmtNum(val);
        }
        put('ed-item-qty',  q);
        put('ed-item-inv',  pr);  put('ed-item-invt', pr * q);
        put('ed-item-vol',  v);   put('ed-item-volt', v * q);
        put('ed-item-wt',   w);   put('ed-item-wtt',  w * q);

        var nm = row.querySelector('.ed-item-name');
        if (nm) nm.value = String(it.name || '');
      });
    } catch (e) {}

    // Раскрашиваем поля правки теми же метками, что и в калькуляторе
    try { if (window.fcPaintFields) window.fcPaintFields(); } catch(e) {}
    if (window.ecoPanelOpen) window.ecoPanelOpen('ed-overlay'); else $('ed-overlay').style.display = 'block';
    window.scrollTo(0,0);
  }

  /* ПЕРЕСЧЁТ СОХРАНЁННОГО РАСЧЁТА.
     Формула ровно та же, что в калькуляторе: доля по объёму или весу (что
     больше), пошлина и НДС от стоимости товара, НДС на половину фрахта,
     дальше наценка по категории и конверсия. Стоимость товара переводится
     в рубли по курсу той валюты, в которой сохранён расчёт. */
  function edRecalc(d) {
    var CATS = window.ITC_CATS_FULL || {};
    var cat = CATS[d.category];
    if (!cat) return null;
    var cbm = +d.containerCbm || 68, cwt = +d.containerWt || 26800;
    var usd = +d.usdRate || 0, cny = +d.cnyRate || 0;
    var invRate = (d.cur === 'USD') ? usd : cny;
    var duty = (+d.duty || 0) / 100, vat = (+d.vat || 0) / 100, tr = (+d.transfer || 0) / 100;
    var F = +d.freight || 0, st = +d.station || 0, br = +d.broker || 0, dl = +d.delivery || 0;
    var sumUsd = 0, sumRub = 0, sumTotal = 0;
    (d.items || []).forEach(function (it) {
      var q = (+it.qty > 0) ? +it.qty : 1;
      var vol = (+it.vol || 0) * q, wt = (+it.wt || 0) * q, inv = (+it.invCny || 0) * q;
      var shareC = Math.max(cbm > 0 ? vol / cbm : 0, (cwt > 0 && wt > 0) ? wt / cwt : 0);
      var T_rub = inv * invRate;
      var insR = T_rub * ((+d.insRate || 0) / 100);
      var rowRate = usd;
      var fUsd = F * shareC, fRub = fUsd * usd;
      var base = T_rub + T_rub * duty + T_rub * vat + insR + (fRub / 2) * vat + (st + br + dl) * shareC;
      var finalUsd, finalRub;
      if (cat.group === 'AB') {
        finalUsd = fUsd * (1 + cat.markup) * (1 + tr);
        finalRub = base * (1 + cat.markup) * (1 + tr);
      } else if (cat.group === 'AUTO') {
        /* Автодоставка: доставка по кубометрам; одна наценка на товар и на
           доставку, сверху перевод. */
        var aUsd = vol * (+d.cbmRate || 150) * (1 + cat.autoMarkup);
        var pRate = (+d.payRate || usd), pFee = (+d.payFee || 0) / 100;
        aUsd = vol * (+d.cbmRate || 150) * (1 + pFee) * (1 + cat.autoMarkup);
        var vAuto = (aUsd * pRate / 2) * vat;
        var chi = (+d.chinaLeg || 0) * invRate * shareC;
        var exi = (+d.expFee || 0) * invRate * shareC;
        finalUsd = aUsd * (1 + tr);
        finalRub = ((T_rub + T_rub * duty + T_rub * vat + insR + vAuto + chi + exi) * (1 + cat.autoMarkup)
                    + (st + br + dl) * (1 + cat.autoMarkup)) * (1 + tr);
        rowRate = pRate;
      } else {
        var b1 = (T_rub + T_rub * duty + T_rub * vat + insR + (fRub / 2) * vat) * (1 + cat.goodsMarkup);
        finalUsd = (fUsd * cat.coef) * (1 + tr);
        finalRub = (b1 + (st + br + dl) * shareC * cat.coef) * (1 + tr);
      }
      /* Плечо Москва → город назначения: закуп по ставке за кубометр, без
         наценки и без перевода. Считается так же, как в калькуляторе. */
      var cityR = (cat.group === 'AUTO') ? (d.cityRate != null ? +d.cityRate : 6200) : 0;
      var cityRub = vol * cityR;
      sumUsd += finalUsd; sumRub += finalRub; sumTotal += finalUsd * rowRate + finalRub + cityRub;
    });
    return { finalUsd: Math.round(sumUsd), finalRub: Math.round(sumRub), totalRub: Math.round(sumTotal) };
  }

  function edNum(id) {
    var e = $(id); if (!e) return undefined;
    var v = (e.value||'').trim(); if (v === '') return undefined;
    var n = parseFloat(v.replace(',','.'));
    return isNaN(n) ? undefined : n;
  }

  function saveEdit() {
    var arr = load();
    var idx = arr.findIndex(function(r){ return r.id === _editId; });
    if (idx < 0) { window.ecoPanelClose('ed-overlay'); return; }
    var rec = arr[idx];
    var d = rec.data || {};
    var name = ($('ed-name').value||'').trim();
    if (!name) { $('ed-name').style.borderColor='#A11A1A'; $('ed-name').focus(); return; }
    rec.name = name;
    rec.dateStr = ($('ed-date').value||'').trim() || rec.dateStr;
    if ($('ed-category')) d.category = $('ed-category').value.trim();
    var setNum = function(key,id){ var v=edNum(id); if(v!==undefined) d[key]=v; else delete d[key]; };
    setNum('cnyRate','ed-cny');
    setNum('usdRate','ed-usd');
    setNum('freight','ed-freight');
    if ($('ed-container')) setNum('containerCbm','ed-container');
    if ($('ed-goods')) setNum('goods','ed-goods');
    /* Остальные ставки и валюта — без них открытая запись не давала того же
       расчёта. */
    if ($('ed-containerWt')) setNum('containerWt','ed-containerWt');
    if ($('ed-cbmrate')) setNum('cbmRate','ed-cbmrate');
    if ($('ed-china')) setNum('chinaLeg','ed-china');
    if ($('ed-payrate')) setNum('payRate','ed-payrate');
    if ($('ed-payfee')) setNum('payFee','ed-payfee');
    if ($('ed-export')) setNum('expFee','ed-export');
    if ($('ed-ins')) setNum('insRate','ed-ins');
    if ($('ed-duty')) setNum('duty','ed-duty');
    if ($('ed-vat')) setNum('vat','ed-vat');
    if ($('ed-station')) setNum('station','ed-station');
    if ($('ed-broker')) setNum('broker','ed-broker');
    if ($('ed-delivery')) setNum('delivery','ed-delivery');
    if ($('ed-transfer')) setNum('transfer','ed-transfer');
    if (_edCur) d.cur = _edCur;
    setNum('finalUsd','ed-finalUsd');
    setNum('finalRub','ed-finalRub');
    setNum('totalRub','ed-totalRub');
    if (d.type === 'full') {
      var items = [];
      document.querySelectorAll('#ed-items .ed-item').forEach(function(row){
        var num = function(sel, def){ var e=row.querySelector(sel); if(!e) return def; var v=parseFloat((e.value||'').replace(',','.')); return isNaN(v)?def:v; };
        var nm = row.querySelector('.ed-item-name');
        var nmv = nm ? nm.value.trim() : '';
        var qtyv = num('.ed-item-qty', 1), volv = num('.ed-item-vol', 0);
        var invv = num('.ed-item-inv', 0), wtv = num('.ed-item-wt', 0);
        if (nmv || volv || invv) items.push({ name:nmv, qty:qtyv, vol:volv, invCny:invv, wt:wtv });
      });
      d.items = items;
      /* Итоги всегда приводим в соответствие с тем, что сейчас в полях: иначе
         в истории останется старая сумма при новых данных. */
      var rc = edRecalc(d);
      if (rc) { d.finalUsd = rc.finalUsd; d.finalRub = rc.finalRub; d.totalRub = rc.totalRub; }
    }
    rec.data = d;
    arr[idx] = rec;
    save(arr);
    try { if (window.ecoLog) window.ecoLog('изменил', rec); } catch(e) {}
    /* Сохранили правку — экран закрываем через общий путь, чтобы шаг «Назад»
       не остался висеть. */
    window.ecoPanelClose('ed-overlay');
    if ($('vw-overlay').style.display !== 'none') { openView(rec); }
    renderHistory();
    toast('✅ Изменения сохранены', '#128A04', 2500);
  }

  // Обработка кнопки Поделиться в просмотре
  $('vw-share-btn').addEventListener('click', function() {
    if (_viewRec) doShare(_viewRec);
  });

  $('vw-back').addEventListener('click', function() {
    window.ecoPanelClose('vw-overlay');
  });

  // Закрытие оверлеев по клику на фон
  $('sv-overlay').addEventListener('click', function(e) { if (e.target === this) closeSave(); });
  $('cp-overlay').addEventListener('click', function(e) { if (e.target === this) $('cp-overlay').style.display='none'; });

})();
</script>


<script>
// Скрываем splash после анимации
(function() {
  window.addEventListener('DOMContentLoaded', function() {
    var quick = false;
    try { quick = sessionStorage.getItem('itc_eco_upd') === '1'; sessionStorage.removeItem('itc_eco_upd'); } catch(e) {}
    setTimeout(function() {
      var splash = document.getElementById('splash');
      if (splash) {
        splash.classList.add('hide');
        setTimeout(function() {
          splash.style.display = 'none';
        }, 500);
      }
    }, quick ? 150 : 2200); // после обновления — почти сразу, обычно 2,2 сек
  });
})();
</script>

<script>
/* ===== НАСТРОЙКИ РАСЧЁТА · общие для всех сотрудников ИТК =====
   Значения лежат в базе магазина. Любой сотрудник ИТК может их поменять,
   каждая правка ложится в журнал: видно кто, когда и что написал. */
(function(){
  var API = '/api/api.php';

  var FACTORY = {
    cny:13.2, usd:95, duty:10, vat:22,
    station:150000, broker:35000, delivery:250000, transfer:3,
    contVol:68, contWt:26800,
    m:{A1:15,A2:10,A3:5,B1:10,B2:5},
    c:{C0:{k:2.0,g:25},C1:{k:2.0,g:15},C2:{k:1.8,g:15},C3:{k:1.5,g:15}}
  };

  function token(){
    try{ var a=JSON.parse(localStorage.getItem('itc_auth')||'null'); return (a&&a.token)||''; }catch(e){ return ''; }
  }
  function g(id){ return document.getElementById(id); }
  function num(v,d){ v=parseFloat(v); return isFinite(v)?v:d; }
  function ru(n){ return String(n).replace('.',','); }

  function clean(c){
    var o=JSON.parse(JSON.stringify(FACTORY));
    if(!c||typeof c!=='object') return o;
    ['cny','usd','duty','vat','station','broker','delivery','transfer','contVol','contWt'].forEach(function(k){
      if(c[k]!==undefined&&c[k]!==null&&isFinite(parseFloat(c[k]))) o[k]=parseFloat(c[k]);
    });
    if(c.m) Object.keys(o.m).forEach(function(k){ if(isFinite(parseFloat(c.m[k]))) o.m[k]=parseFloat(c.m[k]); });
    if(c.c) Object.keys(o.c).forEach(function(k){
      if(!c.c[k]) return;
      if(isFinite(parseFloat(c.c[k].k))) o.c[k].k=parseFloat(c.c[k].k);
      if(isFinite(parseFloat(c.c[k].g))) o.c[k].g=parseFloat(c.c[k].g);
    });
    return o;
  }

  function setVal(id,v){ var e=g(id); if(e&&v!==undefined&&v!==null){ e.value=v; } }

  /* Разложить настройки по обоим калькуляторам */
  function apply(cfg){
    window.ECO_CFG = cfg;
    var pairs = [
      ['kursCny','fc-cny',cfg.cny],['kurs','fc-usd',cfg.usd],
      ['dutyRate','fc-duty',cfg.duty],['vatRate','fc-vat',cfg.vat],
      ['station','fc-station',cfg.station],['broker','fc-broker',cfg.broker],
      ['delivery','fc-delivery',cfg.delivery],['transferRate','fc-transfer',cfg.transfer],
      ['containerVolume','fc-container',cfg.contVol],['containerWeight','fc-containerWt',cfg.contWt]
    ];
    pairs.forEach(function(p){ setVal(p[0],p[2]); setVal(p[1],p[2]); });

    var t = cfg.transfer/100;
    [window.ITC_CATS_QUICK, window.ITC_CATS_FULL].forEach(function(CAT){
      if(!CAT) return;
      Object.keys(cfg.m).forEach(function(k){
        if(!CAT[k]) return;
        CAT[k].markup = cfg.m[k]/100;
        CAT[k].formula = '(База × '+ru((1+cfg.m[k]/100).toFixed(2))+') × '+ru((1+t).toFixed(2));
      });
      Object.keys(cfg.c).forEach(function(k){
        if(!CAT[k]) return;
        CAT[k].coef = cfg.c[k].k;
        CAT[k].goodsMarkup = cfg.c[k].g/100;
        CAT[k].sign = 'Лог. ×'+ru(cfg.c[k].k)+' + товар '+cfg.c[k].g+'% + '+ru(cfg.transfer)+'%';
        CAT[k].formula = '(Б1 + Б2×'+ru(cfg.c[k].k)+') × '+ru((1+t).toFixed(2));
      });
    });
    if(window.ITC_REFRESH_QUICK) window.ITC_REFRESH_QUICK();
    if(window.ITC_REFRESH_FULL) window.ITC_REFRESH_FULL();
    fillForm(cfg);
  }

  function fillForm(cfg){
    setVal('ec-cny',cfg.cny); setVal('ec-usd',cfg.usd);
    setVal('ec-duty',cfg.duty); setVal('ec-vat',cfg.vat);
    setVal('ec-station',cfg.station); setVal('ec-broker',cfg.broker);
    setVal('ec-delivery',cfg.delivery); setVal('ec-transfer',cfg.transfer);
    setVal('ec-contvol',cfg.contVol); setVal('ec-contwt',cfg.contWt);
    Object.keys(cfg.m).forEach(function(k){ setVal('ec-m-'+k,cfg.m[k]); });
    Object.keys(cfg.c).forEach(function(k){ setVal('ec-c-'+k+'-k',cfg.c[k].k); setVal('ec-c-'+k+'-g',cfg.c[k].g); });
  }

  function readForm(){
    var c={
      cny:num(g('ec-cny').value,FACTORY.cny), usd:num(g('ec-usd').value,FACTORY.usd),
      duty:num(g('ec-duty').value,FACTORY.duty), vat:num(g('ec-vat').value,FACTORY.vat),
      station:num(g('ec-station').value,FACTORY.station), broker:num(g('ec-broker').value,FACTORY.broker),
      delivery:num(g('ec-delivery').value,FACTORY.delivery), transfer:num(g('ec-transfer').value,FACTORY.transfer),
      contVol:num(g('ec-contvol').value,FACTORY.contVol), contWt:num(g('ec-contwt').value,FACTORY.contWt),
      m:{}, c:{}
    };
    Object.keys(FACTORY.m).forEach(function(k){ c.m[k]=num(g('ec-m-'+k).value,FACTORY.m[k]); });
    Object.keys(FACTORY.c).forEach(function(k){
      c.c[k]={ k:num(g('ec-c-'+k+'-k').value,FACTORY.c[k].k), g:num(g('ec-c-'+k+'-g').value,FACTORY.c[k].g) };
    });
    return c;
  }

  function msg(text,ok){
    var e=g('eco-cfg-msg'); if(!e) return;
    e.textContent=text; e.style.color = ok ? '#178a3f' : '#A11A1A';
  }

  function noAccess(text){
    var w=g('eco-cfg-warn'); if(w){ w.style.display='block'; w.textContent=text; }
    var f=g('eco-cfg-form'); if(f) f.style.display='none';
  }

  /* Загрузка при открытии */
  function load(){
    var t=token();
    if(!t){ apply(clean(null)); noAccess('Настройки видны и меняются только после входа в приложении ИТК. Сейчас работают заводские значения.'); return; }
    fetch(API+'?action=eco_cfg&t='+encodeURIComponent(t))
      .then(function(r){ return r.json(); })
      .then(function(d){
        if(!d||!d.ok){ apply(clean(null)); noAccess('Не удалось получить общие настройки — работают заводские значения.'); return; }
        apply(clean(d.cfg));
        var w=g('eco-cfg-who');
        if(w) w.textContent = d.who ? ('Последнее изменение: '+d.who+', '+(d.at||'')) : 'Настройки ещё ни разу не меняли — заводские значения.';
      })
      .catch(function(){ apply(clean(null)); noAccess('Нет связи с сервером — работают заводские значения.'); });
  }

  window.ecoCfgSave = function(){
    var t=token();
    if(!t){ msg('Сначала войдите в приложении ИТК',false); return; }
    var cfg=readForm();
    msg('Сохраняю…',true);
    fetch(API+'?action=eco_cfg_save&t='+encodeURIComponent(t),{
      method:'POST', headers:{'Content-Type':'application/json'},
      body:JSON.stringify({ t:t, cfg:cfg, note:(g('ec-note')?g('ec-note').value:'') })
    }).then(function(r){ return r.json(); })
      .then(function(d){
        if(d&&d.ok){ apply(clean(cfg)); if(g('ec-note')) g('ec-note').value=''; msg('Сохранено. Новые значения увидят все сотрудники.',true); load(); }
        else msg((d&&d.error)||'Не удалось сохранить',false);
      }).catch(function(){ msg('Нет связи с сервером',false); });
  };

  window.ecoCfgReset = function(){
    fillForm(FACTORY);
    msg('Подставлены заводские значения. Чтобы они стали общими — нажмите «Сохранить для всех».',true);
  };

  window.ecoCfgLog = function(){
    var box=g('eco-cfg-loglist'); if(!box) return;
    var t=token();
    if(!t){ box.innerHTML='<div class="eco-log-item">Журнал доступен после входа в приложении ИТК.</div>'; return; }
    box.innerHTML='<div class="eco-log-item">Загружаю журнал…</div>';
    fetch(API+'?action=eco_cfg_log&t='+encodeURIComponent(t))
      .then(function(r){ return r.json(); })
      .then(function(d){
        if(!d||!d.ok||!d.log||!d.log.length){ box.innerHTML='<div class="eco-log-item">Изменений пока не было.</div>'; return; }
        box.innerHTML='<div class="eco-cfg-h">История изменений</div>'+d.log.map(function(r){
          var c=null; try{ c=JSON.parse(r.cfg); }catch(e){}
          var short = c ? ('¥ '+c.cny+' · $ '+c.usd+' · пошлина '+c.duty+'% · НДС '+c.vat+'%') : '';
          return '<div class="eco-log-item"><div class="eco-log-who">'+(r.who||'сотрудник')+'</div>'+
                 '<div class="eco-log-when">'+(r.created_at||'')+'</div>'+
                 (r.note?'<div style="margin-top:4px">'+r.note+'</div>':'')+
                 '<div style="margin-top:4px;color:#5b6472">'+short+'</div></div>';
        }).join('');
      }).catch(function(){ box.innerHTML='<div class="eco-log-item">Не удалось загрузить журнал.</div>'; });
  };

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',load);
  else load();
})();
</script>
<script>
/* ===== ОБЩИЙ ЖУРНАЛ РАСЧЁТОВ =====
   Расчёты каждого менеджера лежат у него в браузере — босс их не видел.
   Теперь каждое сохранение, правка и удаление уходят на сервер и подписываются
   именем сотрудника. Список всех менеджеров открывается кнопкой «Все менеджеры»
   в истории, и эта кнопка появляется только у босса. */
(function(){
  var API='/api/api.php';
  function token(){ try{ var a=JSON.parse(localStorage.getItem('itc_auth')||'null'); return (a&&a.token)||''; }catch(e){ return ''; } }
  function $(id){ return document.getElementById(id); }
  function esc(v){ return String(v==null?'':v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  /* Отправка отметки. Тихая: не получилось — расчёт всё равно сохранён у менеджера. */
  window.ecoLog=function(act, rec){
    var t=token(); if(!t||!rec) return;
    var d=rec.data||{};
    try{
      fetch(API+'?action=eco_hist_push&t='+encodeURIComponent(t),{
        method:'POST', headers:{'Content-Type':'application/json'},
        body:JSON.stringify({ t:t, rid:String(rec.id||''), act:act, name:rec.name||'',
                              kind:(d.type==='full'?'Полный расчёт':'Быстрый расчёт'), data:d })
      }).catch(function(){});
    }catch(e){}
  };

  var _shown=false;

  function render(rows){
    var el=$('hs-list'); if(!el) return;
    if(!rows||!rows.length){ el.innerHTML='<div style="text-align:center;padding:48px 20px;color:#9aabcc;font-family:sans-serif">Записей пока нет</div>'; return; }
    var col={'создал':'#128A04','изменил':'#B8860B','удалил':'#A11A1A'};
    el.innerHTML='<div style="padding:14px 16px 0;font-family:sans-serif;font-size:12px;color:#6b7a99">Расчёты всех менеджеров</div>'+
      rows.map(function(r){
        var c=col[r.act]||'#112C93';
        return '<div style="background:#fff;border-radius:12px;margin:12px 16px;border-left:4px solid '+c+';box-shadow:0 2px 8px rgba(17,44,147,.07);padding:12px 14px;font-family:sans-serif">'+
          '<div style="display:flex;justify-content:space-between;gap:10px">'+
            '<div style="font-family:Times New Roman,serif;font-size:1rem;font-weight:700;color:#112C93">'+esc(r.title||'Без названия')+'</div>'+
            '<div style="font-size:12px;font-weight:600;color:'+c+';white-space:nowrap">'+esc(r.act)+'</div>'+
          '</div>'+
          '<div style="font-size:12px;color:#6b7a99;margin-top:5px">'+esc(r.who||'сотрудник')+'</div>'+
          '<div style="font-size:11px;color:#9aabcc;margin-top:2px">'+esc(r.kind||'')+(r.kind?' · ':'')+esc(r.created_at||'')+'</div>'+
          /* Открыть чужой расчёт. Только посмотреть: правка чужой записи
             ушла бы в ЕГО историю, а не в вашу — путаницы было бы больше,
             чем пользы. Нужен себе такой же — есть «Скопировать себе». */
          (r.act === 'удалил' ? '' :
            '<div style="display:flex;gap:8px;margin-top:10px">'+
              '<button data-hget="'+esc(r.id)+'" class="hist-btn hist-btn-open">Открыть</button>'+
              '<button data-hcopy="'+esc(r.id)+'" class="hist-btn hist-btn-edit">Скопировать себе</button>'+
            '</div>')+
        '</div>';
      }).join('');
  }

  function loadAll(){
    var t=token(); if(!t) return;
    var el=$('hs-list');
    if(el) el.innerHTML='<div style="text-align:center;padding:40px;color:#9aabcc;font-family:sans-serif">Загружаю…</div>';
    fetch(API+'?action=eco_hist_list&t='+encodeURIComponent(t))
      .then(function(r){ return r.json(); })
      .then(function(d){ render(d&&d.log); })
      .catch(function(){ if(el) el.innerHTML='<div style="text-align:center;padding:40px;color:#A11A1A;font-family:sans-serif">Нет связи с сервером</div>'; });
  }

  /* Босс или нет — решает сервер, здесь только показываем кнопку */
  function checkBoss(){
    if(_shown) return; _shown=true;
    var t=token(); if(!t) return;
    fetch(API+'?action=eco_hist_list&t='+encodeURIComponent(t))
      .then(function(r){ return r.json(); })
      .then(function(d){ if(d&&d.boss){ var b=$('hs-all'); if(b) b.style.display='inline-block'; } })
      .catch(function(){});
  }

  /* Забираем запись с сервера и показываем. Своя история при этом не
     трогается — она у каждого своя, в его же браузере. */
  function hGet(id, then){
    var t=token(); if(!t) return;
    fetch(API+'?action=eco_hist_get&t='+encodeURIComponent(t)+'&id='+encodeURIComponent(id))
      .then(function(r){ return r.json(); })
      .then(function(d){
        if(!d||!d.ok||!d.rec){ alert((d&&d.err)||'Не удалось получить расчёт'); return; }
        var r=d.rec;
        then({ id:'srv-'+r.id, name:r.title||'Без названия', dateStr:r.created_at||'', data:r.data||{} });
      })
      .catch(function(){ alert('Нет связи с сервером'); });
  }

  document.addEventListener('click', function(e){
    var el=e.target;
    var all=el.closest?el.closest('#hs-all'):null;
    if(all){ loadAll(); return; }
    var g=el.closest?el.closest('[data-hget]'):null;
    if(g){ hGet(g.getAttribute('data-hget'), function(rec){ if(window.ecoOpenView) window.ecoOpenView(rec); }); return; }
    var c=el.closest?el.closest('[data-hcopy]'):null;
    if(c){
      hGet(c.getAttribute('data-hcopy'), function(rec){
        try{
          var key='itc_v2';   // тот же ящик, где лежит личная история
          var arr=JSON.parse(localStorage.getItem(key)||'[]');
          rec.id=Date.now().toString();   // номера записей у нас строковые
          rec.name=(rec.name||'Расчёт')+' (копия)';
          arr.unshift(rec);
          localStorage.setItem(key, JSON.stringify(arr));
          alert('Расчёт скопирован в вашу историю.');
          try{ if(window.ecoRenderHistory) window.ecoRenderHistory(); }catch(e2){}
        }catch(err){ alert('Не удалось скопировать'); }
      });
      return;
    }
    var hb=el.closest?el.closest('[data-action="open-history"]'):null;
    if(hb){ setTimeout(checkBoss,50); }
  }, true);

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',checkBoss);
  else checkBoss();
  try{
    var _b=document.getElementById('ecoToApp');
    var _fromApp=false;
    try{ _fromApp = !!(document.referrer && document.referrer.indexOf(location.host)>=0); }catch(e){}
    if(_b && (ITC_EMBED || _fromApp)) _b.style.display='inline-block';
  }catch(e){}
})();
</script>
</body>
</html>