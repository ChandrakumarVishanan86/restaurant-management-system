(function () {
    'use strict';
    var B = window.BILL, API = APP.base + '/bills/api', f = document.getElementById('payForm');
    if (f) f.addEventListener('submit', function (e) { e.preventDefault(); var b = { action: 'pay', id: B.id }; new FormData(f).forEach(function (v, k) { b[k] = v; });
        SMS.api(API, { method: 'POST', body: b }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) setTimeout(function () { location.reload(); }, 600); }); });
    var rb = document.getElementById('btnReopen');
    if (rb) rb.addEventListener('click', function () { SMS.confirmBox('Cancel this bill and reopen the order?').then(function (ok) { if (ok) SMS.api(API, { method: 'POST', body: { action: 'reopen', id: B.id } }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) location.href = APP.base + '/orders/' + B.order; }); }); });
})();
