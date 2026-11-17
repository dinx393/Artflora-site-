(function () {
  'use strict';
  var list = document.querySelector('[data-season-list]');
  if (!list) return;
  var message = document.querySelector('[data-season-message]');
  var csrf = document.querySelector('meta[name="csrf"]').content;
  var dragged = null;
  var saving = false;
  var saveAgain = false;

  function rows() { return Array.prototype.slice.call(list.querySelectorAll('[data-season-id]')); }
  function setMessage(text, error) {
    if (!message) return;
    message.textContent = text;
    message.style.color = error ? '#a3264a' : '';
  }
  function save() {
    if (saving) { saveAgain = true; return; }
    saving = true;
    setMessage('Сохраняю порядок…');
    fetch('/admin/season/order', {
      method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF': csrf},
      body: JSON.stringify({ids: rows().map(function (row) { return Number(row.dataset.seasonId); })})
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (!data.ok) throw new Error(data.error || 'Не удалось сохранить');
      setMessage('Порядок сохранён');
    }).catch(function (e) { setMessage(e.message + '. Обновите страницу.', true); })
      .finally(function () {
        saving = false;
        if (saveAgain) { saveAgain = false; save(); }
      });
  }
  list.addEventListener('click', function (event) {
    var button = event.target.closest('[data-season-step]');
    if (!button) return;
    var row = button.closest('[data-season-id]');
    var step = Number(button.dataset.seasonStep);
    var target = step < 0 ? row.previousElementSibling : row.nextElementSibling;
    if (!target) return;
    if (step < 0) list.insertBefore(row, target); else list.insertBefore(target, row);
    save();
  });
  list.addEventListener('dragstart', function (event) {
    dragged = event.target.closest('[data-season-id]');
    if (!dragged) return;
    dragged.classList.add('dragging');
    event.dataTransfer.effectAllowed = 'move';
  });
  list.addEventListener('dragover', function (event) {
    if (!dragged) return;
    event.preventDefault();
    var target = event.target.closest('[data-season-id]');
    if (!target || target === dragged) return;
    var box = target.getBoundingClientRect();
    list.insertBefore(dragged, event.clientY < box.top + box.height / 2 ? target : target.nextElementSibling);
  });
  list.addEventListener('dragend', function () {
    if (!dragged) return;
    dragged.classList.remove('dragging');
    dragged = null;
    save();
  });
})();
