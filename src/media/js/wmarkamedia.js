/*! Wmarka Media 1.1.3 — плеер по нажатию и окно просмотра фото. Без библиотек, без обращений к чужим сайтам до нажатия. */
(function () {
  'use strict';
  var d = document;

  /* ---------- постер, который не загрузился (блокировщик, сеть): убираем, чтобы не торчал значок битой картинки ---------- */
  [].forEach.call(d.querySelectorAll('.wm-video__poster'), function (i) { if (i.complete && i.naturalWidth === 0) { i.remove(); } });
  d.addEventListener('error', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('wm-video__poster')) { e.target.remove(); }
  }, true);

  /* ---------- видео: фасад заменяется плеером ---------- */
  d.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-wm-video] .wm-video__btn') : null;
    if (!btn) { return; }
    var box = btn.closest('[data-wm-video]');
    var src = box.getAttribute('data-embed');
    if (!src) { return; }
    var f = d.createElement('iframe');
    f.src = src;
    f.title = box.getAttribute('data-title') || '';
    f.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture; fullscreen; clipboard-write');
    f.setAttribute('allowfullscreen', '');
    f.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    box.classList.add('is-playing');
    btn.replaceWith(f);
    f.focus();
  });

  /* ---------- галерея: окно просмотра на <dialog> ---------- */
  var dlg, img, cap, cnt, items = [], idx = 0, lab = {}, opener = null;

  function build() {
    dlg = d.createElement('dialog');
    dlg.className = 'wm-lightbox';
    dlg.innerHTML = '<div class="wm-lightbox__bar"><span class="wm-lightbox__count" aria-live="polite"></span><button type="button" data-a="close">&times;</button></div>' +
      '<div class="wm-lightbox__stage"><button type="button" class="wm-lightbox__nav wm-lightbox__nav--prev" data-a="prev">&lsaquo;</button>' +
      '<figure class="wm-lightbox__fig"><img alt=""><figcaption></figcaption></figure>' +
      '<button type="button" class="wm-lightbox__nav wm-lightbox__nav--next" data-a="next">&rsaquo;</button></div>';
    d.body.appendChild(dlg);
    img = dlg.querySelector('img'); cap = dlg.querySelector('figcaption'); cnt = dlg.querySelector('.wm-lightbox__count');
    dlg.addEventListener('click', function (e) {
      var a = e.target.getAttribute && e.target.getAttribute('data-a');
      if (a === 'close' || e.target === dlg) { dlg.close ? dlg.close() : dlg.removeAttribute('open'); }
      else if (a === 'prev') { show(idx - 1); }
      else if (a === 'next') { show(idx + 1); }
    });
    dlg.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') { show(idx - 1); } else if (e.key === 'ArrowRight') { show(idx + 1); }
    });
    dlg.addEventListener('close', function () { if (opener && opener.focus) { opener.focus(); } });
  }

  function labels(root) {
    lab = { close: root.getAttribute('data-l-close') || '', prev: root.getAttribute('data-l-prev') || '', next: root.getAttribute('data-l-next') || '', of: root.getAttribute('data-l-of') || '%1$d / %2$d' };
    dlg.querySelector('[data-a="close"]').setAttribute('aria-label', lab.close);
    dlg.querySelector('[data-a="prev"]').setAttribute('aria-label', lab.prev);
    dlg.querySelector('[data-a="next"]').setAttribute('aria-label', lab.next);
  }

  function show(i) {
    idx = (i + items.length) % items.length;
    var b = items[idx], t = b.querySelector('img');
    img.src = b.getAttribute('data-full');
    img.alt = t ? t.alt : (b.getAttribute('data-alt') || '');
    cap.textContent = b.getAttribute('data-caption') || '';
    cnt.textContent = lab.of.replace('%1$d', idx + 1).replace('%2$d', items.length);
  }

  d.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-wm-gallery] .wm-gallery__btn') : null;
    if (!b) { return; }
    var root = b.closest('[data-wm-gallery]');
    if (!dlg) { build(); }
    labels(root);
    items = [].slice.call(root.querySelectorAll('.wm-gallery__btn'));
    opener = b;
    show(items.indexOf(b));
    if (dlg.showModal) { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
  });
})();
