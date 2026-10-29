(function () {
  var toggle = document.querySelector('.wa-toggle');
  if (toggle) {
    toggle.addEventListener('click', function () {
      document.body.classList.toggle('wa-nav-open');
    });
  }
  var searches = document.querySelectorAll('.wa-search');
  Array.prototype.forEach.call(searches, function (input) {
    input.addEventListener('input', function () {
      var q = input.value.toLowerCase();
      var panel = input.closest('.wa-panel');
      var rows = panel ? panel.querySelectorAll('tbody tr') : [];
      Array.prototype.forEach.call(rows, function (tr) {
        tr.style.display = tr.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
      });
    });
  });

  var forms = document.querySelectorAll('form[data-confirm]');
  Array.prototype.forEach.call(forms, function (form) {
    form.addEventListener('submit', function (event) {
      var msg = form.getAttribute('data-confirm');
      if (msg && !window.confirm(msg)) {
        event.preventDefault();
      }
    });
  });
})();
