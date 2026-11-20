(function () {
    'use strict';
    flatpickr('#from', { dateFormat: 'Y-m-d', maxDate: 'today', disableMobile: true }); flatpickr('#to', { dateFormat: 'Y-m-d', maxDate: 'today', disableMobile: true });
    if (!window.Chart || !window.REPORT) return;
    var R = window.REPORT, P = SMS.palette, grid = { color: 'rgba(100,116,139,.12)' };
    new Chart(document.getElementById('chartDaily'), { type: 'bar', data: { labels: R.daily.map(function (d) { return SMS.fdate(d.label).slice(0, 6); }), datasets: [{ data: R.daily.map(function (d) { return +d.value; }), backgroundColor: P[0], borderRadius: 8, maxBarThickness: 30 }] },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return SMS.money(c.parsed.y); } } } }, scales: { y: { beginAtZero: true, grid: grid }, x: { grid: { display: false } } } } });
    function donut(id, rows) { new Chart(document.getElementById(id), { type: 'doughnut', data: { labels: rows.map(function (m) { return m.label; }), datasets: [{ data: rows.map(function (m) { return +m.value; }), backgroundColor: P, borderWidth: 3, borderColor: '#fff', hoverOffset: 8 }] },
        options: { maintainAspectRatio: false, cutout: '64%', plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: function (c) { return c.label + ': ' + SMS.money(c.parsed); } } } } } }); }
    donut('chartMethods', R.methods); donut('chartTypes', R.types); donut('chartCats', R.cats);
    new Chart(document.getElementById('chartHours'), { type: 'bar', data: { labels: R.hours.map(function (h) { return h.h + ':00'; }), datasets: [{ data: R.hours.map(function (h) { return +h.orders; }), backgroundColor: P[1], borderRadius: 6 }] },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: grid }, x: { grid: { display: false } } } } });
})();
