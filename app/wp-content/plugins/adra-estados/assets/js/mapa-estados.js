(function() {
  const MARKER_W = 12;
  const MARKER_H = 15;
  const MIN_POLY_PX = 80;
  const PIN_URL = 'https://files.adventistas.org/v2.adra.org.br/2025/10/02191816/assistencia.svg';
  const DEBUG = false;

  function slugify(str) {
    return String(str || '')
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/\s+/g, '-')
      .replace(/[^a-z0-9\-]/g, '')
      .replace(/\-+/g, '-')
      .replace(/^\-|\-$/g, '');
  }

  function pickShapes(g) {
    let shapes = g.querySelectorAll('.map__state, path.map__state, polygon.map__state');
    if (shapes && shapes.length) return shapes;
    shapes = g.querySelectorAll('path, polygon');
    return shapes;
  }

  function centroidFromPolygons(polys) {
    let sumArea = 0, sumCx = 0, sumCy = 0;

    polys.forEach(poly => {
      const pts = (poly.getAttribute('points') || '').trim();
      if (!pts) return;
      const nums = pts.split(/[\s,]+/).map(parseFloat).filter(n => !Number.isNaN(n));
      if (nums.length < 6) return;

      let area = 0, Cx = 0, Cy = 0;
      for (let i = 0; i < nums.length; i += 2) {
        const x1 = nums[i], y1 = nums[i + 1];
        const x2 = nums[(i + 2) % nums.length], y2 = nums[(i + 3) % nums.length];
        const cross = x1 * y2 - x2 * y1;
        area += cross;
        Cx += (x1 + x2) * cross;
        Cy += (y1 + y2) * cross;
      }
      area *= 0.5;
      if (area === 0) return;
      const cx = Cx / (6 * area);
      const cy = Cy / (6 * area);
      const w  = Math.abs(area);
      sumArea += w;
      sumCx   += w * cx;
      sumCy   += w * cy;
    });

    if (sumArea === 0) return null;
    return { cx: sumCx / sumArea, cy: sumCy / sumArea };
  }

  function getStateCenter(g) {
    const polys = Array.from(g.querySelectorAll('polygon'));
    const cent  = polys.length ? centroidFromPolygons(polys) : null;
    if (cent && isFinite(cent.cx) && isFinite(cent.cy)) return cent;

    const bb = g.getBBox();
    return { cx: bb.x + bb.width / 2, cy: bb.y + bb.height / 2 };
  }

  // Regra estrita: se não tiver polygon, não mostra pin.
  function maxPolygonRenderedHeightPx(g) {
    const polys = Array.from(g.querySelectorAll('polygon'));
    if (polys.length === 0) return 0;
    let maxH = 0;
    polys.forEach(p => {
      const r = p.getBoundingClientRect();
      if (r && r.height > maxH) maxH = r.height;
    });
    return maxH;
  }

  function stateHasTallEnoughPolygonPx(g) {
    const px = maxPolygonRenderedHeightPx(g);
    if (DEBUG) console.debug('[ADRA MAPA] px-height check', { id: g.id, polygonRenderedHeightPx: px, minPx: MIN_POLY_PX });
    return px >= MIN_POLY_PX;
  }

  function positionMarkerImage(g, width = MARKER_W, height = MARKER_H) {
    const img = g.querySelector('.marker-image');
    if (!img) return;

    const { cx, cy } = getStateCenter(g);
    const x = cx - (width / 2);
    const y = cy - (height / 2);

    img.setAttribute('width', String(width));
    img.setAttribute('height', String(height));
    img.setAttribute('x', String(x));
    img.setAttribute('y', String(y));
  }

  function ensureMarkerImage(g) {
    if (g.querySelector('.marker-image')) return;
    const svgns = 'http://www.w3.org/2000/svg';
    const img = document.createElementNS(svgns, 'image');
    img.setAttribute('class', 'marker-image');
    img.setAttribute('href', PIN_URL);
    img.setAttribute('preserveAspectRatio', 'xMidYMid meet');
    img.setAttribute('pointer-events', 'none');
    g.appendChild(img);
  }

  function removeMarkerImage(g) {
    const m = g.querySelector('.marker-image');
    if (m) m.remove();
  }

  function updateMarkerForGroup(g) {
    if (!stateHasTallEnoughPolygonPx(g)) {
      if (DEBUG) console.debug('[ADRA MAPA] hide pin (polygon too small in px)', g.id);
      removeMarkerImage(g);
      return;
    }
    ensureMarkerImage(g);
    positionMarkerImage(g, MARKER_W, MARKER_H);
    if (DEBUG) console.debug('[ADRA MAPA] show pin', g.id);
  }

  function markStates() {
    if (!window.ADRA_MAPA) {
      if (DEBUG) console.warn('[ADRA MAPA] ADRA_MAPA não encontrado.');
      return;
    }
    const allowed = new Set(Array.isArray(ADRA_MAPA.clickableGroups) ? ADRA_MAPA.clickableGroups : []);

    const groups = document.querySelectorAll('.adra-mapa-wrapper svg g[id], .adra-mapa-container svg g[id]');
    groups.forEach(function(g) {
      const rawId = g.getAttribute('id');
      const group = slugify(rawId);
      const shapes = pickShapes(g);
      if (!shapes.length) return;

      if (allowed.has(group)) {
        shapes.forEach(function(el) {
          el.classList.add('is-clickable');
          el.classList.remove('is-disabled');
          el.style.pointerEvents = 'auto';
        });
        g.classList.add('is-clickable');
        g.style.cursor = 'pointer';

        updateMarkerForGroup(g);

        g.addEventListener('click', function(e) {
          e.preventDefault();
          openEstadoModal(group);
        }, { passive: true });
      } else {
        shapes.forEach(function(el) {
          el.classList.add('is-disabled');
          el.classList.remove('is-clickable');
          el.style.pointerEvents = 'none';
        });
        g.classList.remove('is-clickable');
        g.style.cursor = 'not-allowed';
        removeMarkerImage(g);
      }
    });
  }

  // ===== Modal =====
  async function openEstadoModal(group) {
    try {
      const url = `${ADRA_MAPA.restUrl}?group=${encodeURIComponent(group)}`;
      const res = await fetch(url, { credentials: 'same-origin' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();

      const ok = (data && data.success === true) || (data && !data.code && (data.id || data.title));
      if (!ok) {
        console.warn('Estado sem dados:', group, data);
        return;
      }

      // Se houver link_externo, redireciona ao invés de abrir modal
      if (data.link_externo && data.link_externo.trim() !== '') {
        window.location.href = data.link_externo;
        return;
      }

      renderModal(data);
    } catch (err) {
      console.error('Falha ao carregar estado', group, err);
    }
  }

  function ensureModal() {
    let modal = document.getElementById('adra-estado-modal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'adra-estado-modal';
    modal.className = 'adra-modal-root';
    modal.innerHTML = `
      <div class="adra-modal__backdrop"></div>
      <div class="adra-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="adra-estado-title">
        <button class="adra-modal__close" aria-label="Fechar">&times;</button>
        <h3 class="adra-modal__title" id="adra-estado-title"></h3>
        <div class="adra-modal__body"></div>
      </div>`;
    document.body.appendChild(modal);

    modal.querySelector('.adra-modal__backdrop').addEventListener('click', closeModal);
    modal.querySelector('.adra-modal__close').addEventListener('click', closeModal);
    document.addEventListener('keydown', function(e){
      if(e.key === 'Escape') closeModal();
    });

    return modal;
  }

  function renderModal(payload) {
    const modal = ensureModal();
    modal.querySelector('.adra-modal__title').textContent = payload.title || '';
    modal.querySelector('.adra-modal__body').innerHTML =
      (payload.thumbnail ? `<img class="adra-estado__thumb" src="${payload.thumbnail}" alt="">` : '') +
      (payload.content || '');
    modal.classList.add('is-open');
    document.documentElement.classList.add('adra-modal-open');
  }

  function closeModal() {
    const modal = document.getElementById('adra-estado-modal');
    if (modal) modal.classList.remove('is-open');
    document.documentElement.classList.remove('adra-modal-open');
  }

  document.addEventListener('DOMContentLoaded', markStates);
})();
