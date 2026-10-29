(function () {
  var toggle = document.querySelector('.wa-toggle');
  if (toggle) {
    toggle.addEventListener('click', function () {
      document.body.classList.toggle('wa-nav-open');
    });
  }
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
