/**
 * Halı Yıkama Pazaryeri - Ana JavaScript
 * WebView ve mobil uyumlu
 */

(function () {
  'use strict';

  /* ============================================================
   * WebView / App Mode Tespiti
   * ============================================================ */
  const isWebView = /wv|WebView/i.test(navigator.userAgent) ||
    (window.Android !== undefined);

  if (isWebView) {
    document.body.classList.add('app-mode');
  }

  /* ============================================================
   * Bottom Nav Aktif Sayfa Belirleme
   * ============================================================ */
  function setActiveNavItem() {
    const path = window.location.pathname.split('/').pop() || 'index.php';
    document.querySelectorAll('.bottom-nav-item').forEach(function (item) {
      const href = item.getAttribute('href') || '';
      if (href && path === href.split('/').pop()) {
        item.classList.add('active');
      }
    });
  }

  /* ============================================================
   * Hamburger Menü
   * ============================================================ */
  const hamburger   = document.getElementById('hamburger');
  const navMobile   = document.getElementById('navMobile');

  if (hamburger && navMobile) {
    hamburger.addEventListener('click', function () {
      navMobile.classList.toggle('open');
      hamburger.setAttribute('aria-expanded',
        navMobile.classList.contains('open') ? 'true' : 'false');
    });

    // Dışarı tıklandığında kapat
    document.addEventListener('click', function (e) {
      if (!hamburger.contains(e.target) && !navMobile.contains(e.target)) {
        navMobile.classList.remove('open');
      }
    });
  }

  /* ============================================================
   * Flash Mesajları Otomatik Kapat
   * ============================================================ */
  document.querySelectorAll('.toast').forEach(function (toast) {
    setTimeout(function () {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(100%)';
      toast.style.transition = 'all .4s ease';
      setTimeout(function () { toast.remove(); }, 400);
    }, 4000);
  });

  /* ============================================================
   * İl/İlçe/Mahalle Kaskad Select
   * ============================================================ */
  const citySelect   = document.getElementById('city_id');
  const districtSel  = document.getElementById('district_id');
  const neighborhoodSel = document.getElementById('neighborhood_id');

  if (citySelect && districtSel) {
    citySelect.addEventListener('change', function () {
      const cityId = this.value;
      districtSel.innerHTML = '<option value="">İlçe Seçin</option>';
      if (neighborhoodSel) {
        neighborhoodSel.innerHTML = '<option value="">Mahalle Seçin</option>';
      }
      if (!cityId) return;

      fetchOptions('/api/districts.php?city_id=' + cityId, districtSel,
        'İlçe Seçin');
    });
  }

  if (districtSel && neighborhoodSel) {
    districtSel.addEventListener('change', function () {
      const distId = this.value;
      neighborhoodSel.innerHTML = '<option value="">Mahalle Seçin</option>';
      if (!distId) return;

      fetchOptions('/api/neighborhoods.php?district_id=' + distId, neighborhoodSel,
        'Mahalle Seçin');
    });
  }

  function fetchOptions(url, selectEl, placeholder) {
    selectEl.disabled = true;
    fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        selectEl.innerHTML = '<option value="">' + placeholder + '</option>';
        data.forEach(function (item) {
          const opt = document.createElement('option');
          opt.value       = item.id;
          opt.textContent = item.name;
          selectEl.appendChild(opt);
        });
      })
      .catch(function () { /* sessizce hata yut */ })
      .finally(function () { selectEl.disabled = false; });
  }

  /* ============================================================
   * Form Submit Koruma (çift gönderim engeli)
   * ============================================================ */
  document.querySelectorAll('form[data-once]').forEach(function (form) {
    form.addEventListener('submit', function () {
      const btn = form.querySelector('[type="submit"]');
      if (btn) {
        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<span class="spinner"></span> İşleniyor...';
        // 10 saniye sonra tekrar etkinleştir (timeout fallback)
        setTimeout(function () {
          btn.disabled = false;
          btn.innerHTML = original;
        }, 10000);
      }
    });
  });

  /* ============================================================
   * Onay Diyaloğu
   * ============================================================ */
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(el.dataset.confirm)) {
        e.preventDefault();
      }
    });
  });

  /* ============================================================
   * Karakter Sayacı (textarea)
   * ============================================================ */
  document.querySelectorAll('textarea[maxlength]').forEach(function (ta) {
    const max     = parseInt(ta.getAttribute('maxlength'));
    const counter = document.createElement('div');
    counter.style.cssText = 'font-size:11px;color:#9ca3af;text-align:right;margin-top:4px;';
    ta.parentNode.insertBefore(counter, ta.nextSibling);

    function update() {
      counter.textContent = ta.value.length + ' / ' + max;
    }
    ta.addEventListener('input', update);
    update();
  });

  /* ============================================================
   * Resim Ön İzleme (logo/fotoğraf yükleme)
   * ============================================================ */
  document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
      const previewId = input.dataset.preview;
      const preview   = document.getElementById(previewId);
      if (!preview || !input.files[0]) return;

      const reader = new FileReader();
      reader.onload = function (e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
      };
      reader.readAsDataURL(input.files[0]);
    });
  });

  /* ============================================================
   * Android WebView: Device Token Kayıt
   * Android tarafından window.AndroidInterface.getToken() çağrılabilir
   * ============================================================ */
  if (window.AndroidInterface && typeof window.AndroidInterface.getDeviceToken === 'function') {
    try {
      const token = window.AndroidInterface.getDeviceToken();
      if (token) {
        saveDeviceToken(token, 'android_webview');
      }
    } catch (e) { /* pasif */ }
  }

  function saveDeviceToken(token, platform) {
    fetch('/save-device-token.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'device_token=' + encodeURIComponent(token) +
            '&platform=' + encodeURIComponent(platform)
    }).catch(function () { /* sessiz hata */ });
  }

  /* ============================================================
   * Android Geri Tuşu (WebView içinde popup/modal kapatma)
   * ============================================================ */
  if (window.history && window.history.pushState) {
    window.history.pushState(null, '', window.location.href);
    window.addEventListener('popstate', function () {
      const openModal = document.querySelector('.modal.open');
      if (openModal) {
        openModal.classList.remove('open');
        window.history.pushState(null, '', window.location.href);
      }
      // else: tarayıcı geri tuşu normal çalışır
    });
  }

  /* ============================================================
   * Sayfa Yüklendiğinde
   * ============================================================ */
  document.addEventListener('DOMContentLoaded', function () {
    setActiveNavItem();

    // Bottom nav varsa body class ekle
    if (document.querySelector('.bottom-nav')) {
      document.body.classList.add('has-bottom-nav');
    }
  });

})();
