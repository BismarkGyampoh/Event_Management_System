/* EventMS progressive enhancements. Every page remains usable without JavaScript. */
document.addEventListener('DOMContentLoaded', function () {
  replaceLegacyIcons();
  document.querySelectorAll('.btn-close:not([aria-label])').forEach(function (button) { button.setAttribute('aria-label', 'Close'); });
  document.querySelectorAll('.modal').forEach(function (modal) { modal.setAttribute('aria-hidden', 'true'); });
  var menuButton = document.querySelector('.menu-toggle');
  var navigation = document.querySelector('.primary-nav');
  if (menuButton && navigation) {
    menuButton.addEventListener('click', function () {
      var open = menuButton.getAttribute('aria-expanded') !== 'true';
      menuButton.setAttribute('aria-expanded', String(open));
      menuButton.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
      navigation.classList.toggle('is-open', open);
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && navigation.classList.contains('is-open')) {
        navigation.classList.remove('is-open');
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.focus();
      }
    });
  }

  document.querySelectorAll('.nav-user').forEach(function (button) {
    var menu = document.getElementById(button.getAttribute('aria-controls'));
    if (!menu) return;
    button.addEventListener('click', function () {
      var open = button.getAttribute('aria-expanded') !== 'true';
      button.setAttribute('aria-expanded', String(open));
      menu.hidden = !open;
    });
    document.addEventListener('click', function (event) {
      if (!button.parentElement.contains(event.target)) {
        button.setAttribute('aria-expanded', 'false');
        menu.hidden = true;
      }
    });
  });

  document.addEventListener('click', function (event) {
    var dismiss = event.target.closest('[data-bs-dismiss="alert"], [data-bs-dismiss="modal"]');
    if (dismiss) {
      var panel = dismiss.closest('.alert, .modal');
      if (panel) panel.classList.remove('show');
      if (panel && panel.classList.contains('modal')) {
        panel.hidden = true; panel.setAttribute('aria-hidden', 'true'); panel.removeAttribute('aria-modal');
        if (panel._trigger) panel._trigger.focus();
      }
      else if (panel) panel.remove();
      return;
    }
    var opener = event.target.closest('[data-bs-toggle="modal"]');
    if (opener) {
      var modal = document.querySelector(opener.getAttribute('data-bs-target'));
      if (modal) {
        modal._trigger = opener; modal.hidden = false; modal.classList.add('show');
        modal.setAttribute('aria-modal', 'true'); modal.setAttribute('aria-hidden', 'false');
        var firstControl = modal.querySelector('button, a[href], input, select, textarea');
        if (firstControl) firstControl.focus();
      }
    }
    if (event.target.classList.contains('modal')) {
      event.target.hidden = true;
      event.target.classList.remove('show');
      event.target.setAttribute('aria-hidden', 'true'); event.target.removeAttribute('aria-modal');
      if (event.target._trigger) event.target._trigger.focus();
    }
  });

  document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
    window.setTimeout(function () { if (alert.isConnected) alert.remove(); }, 8000);
  });

  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
      if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; }
      var submit = event.submitter || form.querySelector('[type="submit"]');
      if (submit && !submit.disabled) {
        form.dataset.submitting = 'true';
        submit.classList.add('is-loading');
        submit.setAttribute('aria-busy', 'true');
      }
    });
  });

  document.querySelectorAll('[data-password-target]').forEach(function (button) {
    button.addEventListener('click', function () {
      var input = document.getElementById(button.dataset.passwordTarget);
      if (!input) return;
      var reveal = input.type === 'password';
      input.type = reveal ? 'text' : 'password';
      button.textContent = reveal ? 'Hide' : 'Show';
      button.setAttribute('aria-label', (reveal ? 'Hide' : 'Show') + ' ' + (input.id === 'confirm_password' ? 'confirmation password' : 'password'));
    });
  });

  document.querySelectorAll('[data-confirm-password]').forEach(function (input) {
    input.addEventListener('input', function () {
      var password = document.querySelector('[data-password="' + input.dataset.confirmPassword + '"]');
      input.setCustomValidity(password && input.value !== password.value ? 'Passwords do not match' : '');
    });
  });
  document.querySelectorAll('[data-countdown]').forEach(function (element) {
    var until = new Date(element.dataset.countdown).getTime();
    function update() {
      var remaining = until - Date.now();
      if (remaining <= 0) { element.textContent = 'Event has started'; return; }
      var days = Math.floor(remaining / 86400000);
      var hours = Math.floor((remaining % 86400000) / 3600000);
      var minutes = Math.floor((remaining % 3600000) / 60000);
      element.textContent = days + 'd ' + hours + 'h ' + minutes + 'm';
    }
    update(); window.setInterval(update, 60000);
  });

  var main = document.querySelector('main');
  if (main && !main.id) main.id = 'main-content';

  document.querySelectorAll('.table-responsive table').forEach(function (table) {
    var labels = Array.prototype.map.call(table.querySelectorAll('thead th'), function (heading) { return heading.textContent.trim(); });
    table.querySelectorAll('tbody tr').forEach(function (row) {
      Array.prototype.forEach.call(row.children, function (cell, index) {
        if (cell.tagName === 'TD' && !cell.hasAttribute('colspan') && labels[index]) cell.dataset.label = labels[index];
      });
    });
  });
  var revealItems = document.querySelectorAll('main section, main > section');
  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); }
      });
    }, { threshold: 0.08 });
    revealItems.forEach(function (item) { item.classList.add('reveal'); observer.observe(item); });
  }
});

function replaceLegacyIcons() {
  var iconPaths = {
    calendar: 'M8 2v4m8-4v4M4 10h16M5 4h14a1 1 0 0 1 1 1v15H4V5a1 1 0 0 1 1-1z',
    'calendar-alt': 'M8 2v4m8-4v4M4 10h16M5 4h14a1 1 0 0 1 1 1v15H4V5a1 1 0 0 1 1-1z',
    'calendar-days': 'M8 2v4m8-4v4M4 10h16M5 4h14a1 1 0 0 1 1 1v15H4V5a1 1 0 0 1 1-1zM8 14h.01M12 14h.01M16 14h.01M8 17h.01M12 17h.01',
    search: 'm20 20-4.5-4.5M18 10.5a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z',
    user: 'M20 21a8 8 0 0 0-16 0m8-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
    'user-circle': 'M20 21a8 8 0 0 0-16 0m8-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
    'map-marker-alt': 'M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Zm-5 0a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
    'location-dot': 'M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Zm-5 0a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
    'map-pin': 'M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Zm-5 0a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
    ticket: 'M3 7h18v4a2 2 0 0 0 0 4v4H3v-4a2 2 0 0 0 0-4V7Zm12 0v2m0 2v2m0 2v2',
    'ticket-alt': 'M3 7h18v4a2 2 0 0 0 0 4v4H3v-4a2 2 0 0 0 0-4V7Zm12 0v2m0 2v2m0 2v2',
    'arrow-right': 'M4 12h16m-7-7 7 7-7 7',
    'arrow-left': 'M20 12H4m7 7-7-7 7-7',
    edit: 'm14 5 5 5M4 20l4-.8L19 8a2.1 2.1 0 0 0-3-3L5 16l-1 4Z',
    plus: 'M12 5v14m-7-7h14',
    'plus-circle': 'M12 8v8m-4-4h8m6 0a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z',
    trash: 'M4 7h16m-10 4v6m4-6v6M6 7l1 14h10l1-14M9 7V4h6v3',
    check: 'm5 12 4 4L19 6',
    'check-circle': 'm8 12 3 3 5-6m6 3a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z',
    lock: 'M5 10h14v11H5zM8 10V7a4 4 0 0 1 8 0v3',
    shield: 'M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z',
    'shield-halved': 'M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Zm0-18v17',
    clock: 'M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    'sign-out-alt': 'M10 17l5-5-5-5m5 5H3m9-9h8v18h-8',
    'gauge-high': 'M4 19a9 9 0 1 1 16 0M12 13l5-5m-5 5v.01',
    'tachometer-alt': 'M4 19a9 9 0 1 1 16 0M12 13l5-5m-5 5v.01',
    'id-card': 'M3 5h18v14H3zM7 10a2 2 0 1 0 4 0 2 2 0 0 0-4 0Zm-1 6a3 3 0 0 1 6 0m3-5h4m-4 4h4',
    coins: 'M12 8c4 0 7-1.3 7-3s-3-3-7-3-7 1.3-7 3 3 3 7 3Zm-7-3v5c0 1.7 3 3 7 3 1 0 2-.1 3-.3M5 10v5c0 1.7 3 3 7 3m7-4c0-1.7-1.6-3-3.5-3S12 12.3 12 14s1.6 3 3.5 3 3.5 1.3 3.5 3-1.6 3-3.5 3S12 21.7 12 20',
    eye: 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Zm10 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
    'eye-slash': 'm3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10 10 0 0 1 12 5c6.5 0 10 7 10 7a16 16 0 0 1-3 3.8M6.2 6.2C3.4 8 2 12 2 12s3.5 7 10 7c1 0 1.9-.2 2.7-.5',
    info: 'M12 11v6m0-10h.01m9 5a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    'circle-info': 'M12 11v6m0-10h.01m9 5a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    warning: 'M12 3 2 21h20L12 3Zm0 7v4m0 3h.01',
    'triangle-exclamation': 'M12 3 2 21h20L12 3Zm0 7v4m0 3h.01',
    compass: 'm16.2 7.8-2.5 5.9-5.9 2.5 2.5-5.9 5.9-2.5ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    home: 'm3 10 9-7 9 7v10h-6v-6H9v6H3V10Z',
    music: 'M9 18V5l12-2v13M9 18a3 3 0 1 1-3-3 3 3 0 0 1 3 3Zm12-2a3 3 0 1 1-3-3 3 3 0 0 1 3 3Z'
  };
  document.querySelectorAll('i[class*="fa-"]').forEach(function (oldIcon) {
    var iconClass = Array.prototype.find.call(oldIcon.classList, function (name) { return name.indexOf('fa-') === 0; });
    var name = iconClass ? iconClass.slice(3) : 'circle-info';
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    var utilityClasses = Array.prototype.filter.call(oldIcon.classList, function (className) {
      return className !== 'fas' && className !== 'far' && className !== 'fa' && !/^fa-/.test(className);
    });
    if (oldIcon.classList.contains('fa-2x')) utilityClasses.push('fa-2x');
    if (oldIcon.classList.contains('fa-4x')) utilityClasses.push('fa-4x');
    svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('class', 'ui-icon ' + utilityClasses.join(' ')); svg.setAttribute('aria-hidden', 'true');
    var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', iconPaths[name] || iconPaths['circle-info']);
    path.setAttribute('fill', 'none'); path.setAttribute('stroke', 'currentColor'); path.setAttribute('stroke-width', '1.5');
    path.setAttribute('stroke-linecap', 'round'); path.setAttribute('stroke-linejoin', 'round'); svg.appendChild(path);
    var parent = oldIcon.parentElement;
    if (parent && !parent.textContent.trim() && !parent.getAttribute('aria-label') && /^(A|BUTTON)$/.test(parent.tagName)) {
      var labels = {edit:'Edit',trash:'Delete',search:'Search',ticket:'View ticket', 'ticket-alt':'View ticket', 'arrow-right':'Continue', 'arrow-left':'Go back', plus:'Add', 'plus-circle':'Add', check:'Confirm', 'sign-out-alt':'Sign out'};
      parent.setAttribute('aria-label', labels[name] || name.replace(/-/g, ' '));
    }
    oldIcon.replaceWith(svg);
  });
}

function showLoading(element) {
  element = element || document.body;
  var loader = document.createElement('div');
  loader.className = 'loader'; loader.setAttribute('role', 'status');
  loader.setAttribute('aria-label', 'Loading'); element.appendChild(loader); return loader;
}
function hideLoading(loader) { if (loader && loader.parentNode) loader.remove(); }
function showToast(message, type) {
  var alert = document.createElement('div');
  var allowed = ['success', 'danger', 'warning', 'info'];
  alert.className = 'alert alert-' + (allowed.indexOf(type) >= 0 ? type : 'info');
  alert.setAttribute('role', 'status'); alert.textContent = String(message);
  document.body.appendChild(alert); window.setTimeout(function () { alert.remove(); }, 5000);
}
function confirmAction(message, callback) {
  if (typeof callback === 'function') callback(window.confirm(message || 'Are you sure?'));
}
function ajaxRequest(url, options) {
  options = options || {};
  var xhr = new XMLHttpRequest(); xhr.open(options.method || 'GET', url, true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  Object.keys(options.headers || {}).forEach(function (key) { xhr.setRequestHeader(key, options.headers[key]); });
  xhr.onload = function () {
    if (xhr.status >= 200 && xhr.status < 400) {
      try { if (options.success) options.success(JSON.parse(xhr.responseText)); }
      catch (error) { if (options.error) options.error(xhr); }
    } else if (options.error) options.error(xhr);
  };
  xhr.onerror = function () { if (options.error) options.error(xhr); };
  xhr.send(options.data || null); return xhr;
}
