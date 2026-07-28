(function () {
  'use strict';

  document.addEventListener('click', function (event) {
    var button = event.target.closest('.rcl-copy-button');
    if (!button) {
      return;
    }

    var targetId = button.getAttribute('data-copy-target');
    var code = targetId ? document.getElementById(targetId) : null;
    if (!code) {
      return;
    }

    var text = code.textContent || '';
    var library = button.closest('[data-rcl-library]');
    var live = library ? library.querySelector('.rcl-library__live') : null;

    function success() {
      var original = button.textContent;
      button.textContent = 'Copied';
      if (live) {
        live.textContent = 'Code copied to the clipboard.';
      }
      window.setTimeout(function () {
        button.textContent = original;
      }, 1800);
    }

    function failure() {
      if (live) {
        live.textContent = 'Unable to copy the code automatically. Select the code and copy it manually.';
      }
    }

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(success).catch(failure);
      return;
    }

    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
      document.execCommand('copy') ? success() : failure();
    } catch (error) {
      failure();
    }
    document.body.removeChild(textarea);
  });
}());
