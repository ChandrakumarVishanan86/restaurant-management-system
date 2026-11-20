/* dashboard.js - charts for DineFlow dashboards */
(function () {
    'use strict';
    if (!window.Chart || !window.DASH) return;
    var D = window.DASH, P = SMS.palette, grid = { color: 'rgba(100,116,139,.12)' };
    function el(id) { return document.getElementById(id); }
    function bars(id, rows, color) { if (!el(id) || !rows) return; new Chart(el(id), { type: 'bar', data: { labels: rows.map(function (r) { return SMS.fdate(r.label).slice(0, 6); }), datasets: [{ data: rows.map(function (r) { return +r.value; }), backgroundColor: color, borderRadius: 8, maxBarThickness: 30 }] },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return SMS.money(c.parsed.y); } } } }, scales: { y: { beginAtZero: true, grid: grid }, x: { grid: { display: false } } } } }); }
    bars('chartSales', D.sales, P[0]); bars('chartCollect', D.collect, P[1]);
    if (el('chartType') && D.types) new Chart(el('chartType'), { type: 'doughnut', data: { labels: D.types.map(function (r) { return r.label; }), datasets: [{ data: D.types.map(function (r) { return +r.value; }), backgroundColor: P, borderWidth: 3, borderColor: '#fff', hoverOffset: 8 }] },
        options: { maintainAspectRatio: false, cutout: '64%', plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: function (c) { return c.label + ': ' + SMS.money(c.parsed); } } } } } });
    if (el('chartTop') && D.top) new Chart(el('chartTop'), { type: 'bar', data: { labels: D.top.map(function (r) { return r.label; }), datasets: [{ data: D.top.map(function (r) { return +r.value; }), backgroundColor: P[2] || P[1], borderRadius: 6, maxBarThickness: 20 }] },
        options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 }, grid: grid }, y: { grid: { display: false } } } } });
})();
