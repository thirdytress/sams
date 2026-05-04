(function () {
  'use strict';

  var endpoint = '/sams/realtime.php';
  var lastSignature = null;
  var pollIntervalMs = 15000;

  function computeSignature(payload) {
    var counts = payload && payload.counts ? payload.counts : {};
    var parts = [
      payload ? payload.role : '',
      counts.applications || 0,
      counts.pending_applications || 0,
      counts.students || 0,
      counts.attendance_today || 0,
      counts.unread_reports || 0,
      counts.unread_messages || 0,
      payload && payload.timestamp ? payload.timestamp : 0
    ];
    return parts.join('|');
  }

  function poll() {
    fetch(endpoint + '?_=' + Date.now(), {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (res) {
        if (!res.ok) {
          throw new Error('Realtime poll failed');
        }
        return res.json();
      })
      .then(function (payload) {
        if (!payload || payload.ok !== true) {
          return;
        }

        var signature = computeSignature(payload);
        if (lastSignature === null) {
          lastSignature = signature;
          return;
        }

        if (signature !== lastSignature) {
          window.location.reload();
        }
      })
      .catch(function () {
        // Silent fail; the next poll will try again.
      });
  }

  function start() {
    poll();
    window.setInterval(poll, pollIntervalMs);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
