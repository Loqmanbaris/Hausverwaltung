/*
 * Beleg-Scanner (Paket 47): Fotos zuschneiden (mit Entzerrung), verbessern, zu PDF zusammenfassen und per Texterkennung auswerten.
 * Alles läuft im Browser. Es werden keine Bilder an Dritte gesendet.
 * Texterkennung: Tesseract.js 5.1.1 und tesseract.js-core 5.1.0 (Apache-2.0), deutsche Sprachdaten tessdata_best_int (Apache-2.0).
 * Die Bibliotheken liegen unverändert im selben Ordner und behalten ihre eigenen Lizenzhinweise.
 */
(function (global) {
  'use strict';

  var scriptSrc = (document.currentScript && document.currentScript.src) || (location.origin + '/scan/ocr/scanner.js');
  var BASE = scriptSrc.replace(/scanner\.js(\?.*)?$/, '');
  var MAXSIDE = 2200;

  /* ------------------------------------------------------------ Hilfen */

  function el(tag, props, children) {
    var e = document.createElement(tag);
    Object.keys(props || {}).forEach(function (k) {
      if (k === 'style') { e.style.cssText = props[k]; }
      else if (k === 'text') { e.textContent = props[k]; }
      else { e.setAttribute(k, props[k]); }
    });
    (children || []).forEach(function (c) { e.appendChild(c); });
    return e;
  }

  function loadImage(file) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () { URL.revokeObjectURL(url); resolve(img); };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('Das Bild konnte nicht gelesen werden.')); };
      img.src = url;
    });
  }

  function toCanvas(source, maxSide) {
    var w = source.naturalWidth || source.width;
    var h = source.naturalHeight || source.height;
    var s = Math.min(1, maxSide / Math.max(w, h));
    var c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(w * s));
    c.height = Math.max(1, Math.round(h * s));
    var ctx = c.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, c.width, c.height);
    ctx.drawImage(source, 0, 0, c.width, c.height);
    return c;
  }

  function rotateCanvas(src) {
    var c = document.createElement('canvas');
    c.width = src.height; c.height = src.width;
    var ctx = c.getContext('2d');
    ctx.translate(c.width, 0);
    ctx.rotate(Math.PI / 2);
    ctx.drawImage(src, 0, 0);
    return c;
  }

  function dist(a, b) { return Math.sqrt((a.x - b.x) * (a.x - b.x) + (a.y - b.y) * (a.y - b.y)); }

  /* ------------------------------------------------------------ Kanten finden */

  function insetCorners(w, h) {
    var mx = w * 0.06, my = h * 0.06;
    return [{ x: mx, y: my }, { x: w - mx, y: my }, { x: w - mx, y: h - my }, { x: mx, y: h - my }];
  }

  /* Heller Beleg auf dunklerem Untergrund: größte helle Fläche suchen, ihre vier äußersten Ecken nehmen. Sonst Rand mit Abstand. */
  function detectCorners(canvas) {
    var W = canvas.width, H = canvas.height;
    var s = Math.min(1, 240 / Math.max(W, H));
    var w = Math.max(8, Math.round(W * s)), h = Math.max(8, Math.round(H * s));
    var t = document.createElement('canvas');
    t.width = w; t.height = h;
    t.getContext('2d').drawImage(canvas, 0, 0, w, h);
    var d = t.getContext('2d').getImageData(0, 0, w, h).data;
    var gray = new Uint8Array(w * h);
    var hist = new Array(256).fill(0);
    for (var i = 0; i < w * h; i++) {
      var g = Math.round(0.299 * d[i * 4] + 0.587 * d[i * 4 + 1] + 0.114 * d[i * 4 + 2]);
      gray[i] = g; hist[g]++;
    }
    var total = w * h, sum = 0;
    for (var k = 0; k < 256; k++) { sum += k * hist[k]; }
    var sumB = 0, wB = 0, best = 0, thr = 128;
    for (var th = 0; th < 256; th++) {
      wB += hist[th];
      if (wB === 0) { continue; }
      var wF = total - wB;
      if (wF === 0) { break; }
      sumB += th * hist[th];
      var mB = sumB / wB, mF = (sum - sumB) / wF;
      var between = wB * wF * (mB - mF) * (mB - mF);
      if (between > best) { best = between; thr = th; }
    }
    var mask = new Uint8Array(w * h);
    var bright = 0;
    for (var j = 0; j < w * h; j++) { if (gray[j] > thr) { mask[j] = 1; bright++; } }
    // größte zusammenhängende helle Fläche
    var seen = new Uint8Array(w * h), bestComp = null, bestSize = 0;
    var stack = new Int32Array(w * h);
    for (var p = 0; p < w * h; p++) {
      if (!mask[p] || seen[p]) { continue; }
      var sp = 0, size = 0, pts = [];
      stack[sp++] = p; seen[p] = 1;
      while (sp > 0) {
        var q = stack[--sp];
        size++; pts.push(q);
        var x = q % w, y = (q - x) / w;
        if (x > 0 && mask[q - 1] && !seen[q - 1]) { seen[q - 1] = 1; stack[sp++] = q - 1; }
        if (x < w - 1 && mask[q + 1] && !seen[q + 1]) { seen[q + 1] = 1; stack[sp++] = q + 1; }
        if (y > 0 && mask[q - w] && !seen[q - w]) { seen[q - w] = 1; stack[sp++] = q - w; }
        if (y < h - 1 && mask[q + w] && !seen[q + w]) { seen[q + w] = 1; stack[sp++] = q + w; }
      }
      if (size > bestSize) { bestSize = size; bestComp = pts; }
    }
    var frac = bestSize / total;
    if (!bestComp || frac < 0.15 || frac > 0.97) { return insetCorners(W, H); }
    var tl = null, tr = null, br = null, bl = null;
    var vTl = Infinity, vTr = -Infinity, vBr = -Infinity, vBl = Infinity;
    for (var n = 0; n < bestComp.length; n++) {
      var pt = bestComp[n], px = pt % w, py = (pt - px) / w;
      if (px + py < vTl) { vTl = px + py; tl = { x: px, y: py }; }
      if (px - py > vTr) { vTr = px - py; tr = { x: px, y: py }; }
      if (px + py > vBr) { vBr = px + py; br = { x: px, y: py }; }
      if (px - py < vBl) { vBl = px - py; bl = { x: px, y: py }; }
    }
    var q4 = [tl, tr, br, bl].map(function (c) { return { x: (c.x + 0.5) / s, y: (c.y + 0.5) / s }; });
    // Plausibilität: Fläche des Vierecks und Reihenfolge
    var area = Math.abs((q4[0].x * q4[1].y - q4[1].x * q4[0].y) + (q4[1].x * q4[2].y - q4[2].x * q4[1].y) + (q4[2].x * q4[3].y - q4[3].x * q4[2].y) + (q4[3].x * q4[0].y - q4[0].x * q4[3].y)) / 2;
    if (area < 0.12 * W * H) { return insetCorners(W, H); }
    return q4;
  }

  /* ------------------------------------------------------------ Entzerren */

  // Abbildung vom Einheitsquadrat auf ein beliebiges Viereck (Heckbert), Ecken: oben links, oben rechts, unten rechts, unten links
  function quadMap(c) {
    var x0 = c[0].x, y0 = c[0].y, x1 = c[1].x, y1 = c[1].y, x2 = c[2].x, y2 = c[2].y, x3 = c[3].x, y3 = c[3].y;
    var dx1 = x1 - x2, dx2 = x3 - x2, dx3 = x0 - x1 + x2 - x3;
    var dy1 = y1 - y2, dy2 = y3 - y2, dy3 = y0 - y1 + y2 - y3;
    var a, b, cc, d, e, f, g, hh;
    if (Math.abs(dx3) < 1e-9 && Math.abs(dy3) < 1e-9) {
      a = x1 - x0; b = x2 - x1; cc = x0; d = y1 - y0; e = y2 - y1; f = y0; g = 0; hh = 0;
      b = x3 - x0; e = y3 - y0; // affin
    } else {
      var den = dx1 * dy2 - dx2 * dy1;
      g = (dx3 * dy2 - dx2 * dy3) / den;
      hh = (dx1 * dy3 - dx3 * dy1) / den;
      a = x1 - x0 + g * x1; b = x3 - x0 + hh * x3; cc = x0;
      d = y1 - y0 + g * y1; e = y3 - y0 + hh * y3; f = y0;
    }
    return { a: a, b: b, c: cc, d: d, e: e, f: f, g: g, h: hh };
  }

  function warp(src, corners, maxSide) {
    var wTop = dist(corners[0], corners[1]), wBot = dist(corners[3], corners[2]);
    var hL = dist(corners[0], corners[3]), hR = dist(corners[1], corners[2]);
    var ow = Math.max(wTop, wBot), oh = Math.max(hL, hR);
    var s = Math.min(1, maxSide / Math.max(ow, oh));
    ow = Math.max(8, Math.round(ow * s)); oh = Math.max(8, Math.round(oh * s));
    var out = document.createElement('canvas');
    out.width = ow; out.height = oh;
    var sctx = src.getContext('2d');
    var sw = src.width, sh = src.height;
    var sd = sctx.getImageData(0, 0, sw, sh).data;
    var octx = out.getContext('2d');
    var od = octx.createImageData(ow, oh);
    var o = od.data;
    var m = quadMap(corners);
    for (var y = 0; y < oh; y++) {
      var v = (y + 0.5) / oh;
      for (var x = 0; x < ow; x++) {
        var u = (x + 0.5) / ow;
        var den = m.g * u + m.h * v + 1;
        var X = (m.a * u + m.b * v + m.c) / den - 0.5;
        var Y = (m.d * u + m.e * v + m.f) / den - 0.5;
        var x0 = Math.floor(X), y0 = Math.floor(Y);
        var fx = X - x0, fy = Y - y0;
        if (x0 < 0) { x0 = 0; fx = 0; } if (y0 < 0) { y0 = 0; fy = 0; }
        if (x0 >= sw - 1) { x0 = sw - 2; fx = 1; } if (y0 >= sh - 1) { y0 = sh - 2; fy = 1; }
        var i00 = (y0 * sw + x0) * 4, i10 = i00 + 4, i01 = i00 + sw * 4, i11 = i01 + 4;
        var w00 = (1 - fx) * (1 - fy), w10 = fx * (1 - fy), w01 = (1 - fx) * fy, w11 = fx * fy;
        var oi = (y * ow + x) * 4;
        o[oi] = sd[i00] * w00 + sd[i10] * w10 + sd[i01] * w01 + sd[i11] * w11;
        o[oi + 1] = sd[i00 + 1] * w00 + sd[i10 + 1] * w10 + sd[i01 + 1] * w01 + sd[i11 + 1] * w11;
        o[oi + 2] = sd[i00 + 2] * w00 + sd[i10 + 2] * w10 + sd[i01 + 2] * w01 + sd[i11 + 2] * w11;
        o[oi + 3] = 255;
      }
    }
    octx.putImageData(od, 0, 0);
    return out;
  }

  /* ------------------------------------------------------------ Bild verbessern */

  function enhance(canvas, mode) {
    if (mode === 'original') { return canvas; }
    var w = canvas.width, h = canvas.height;
    var ctx = canvas.getContext('2d');
    var id = ctx.getImageData(0, 0, w, h);
    var d = id.data;
    var n = w * h;
    if (mode === 'bw') {
      var gray = new Float32Array(n);
      for (var i = 0; i < n; i++) { gray[i] = 0.299 * d[i * 4] + 0.587 * d[i * 4 + 1] + 0.114 * d[i * 4 + 2]; }
      // Mittelwert in einem Fenster über das Integralbild
      var integral = new Float64Array((w + 1) * (h + 1));
      for (var y = 0; y < h; y++) {
        var row = 0;
        for (var x = 0; x < w; x++) {
          row += gray[y * w + x];
          integral[(y + 1) * (w + 1) + x + 1] = integral[y * (w + 1) + x + 1] + row;
        }
      }
      var r = Math.max(8, Math.round(Math.min(w, h) / 24));
      for (var yy = 0; yy < h; yy++) {
        var y0 = Math.max(0, yy - r), y1 = Math.min(h, yy + r + 1);
        for (var xx = 0; xx < w; xx++) {
          var x0 = Math.max(0, xx - r), x1 = Math.min(w, xx + r + 1);
          var area = (x1 - x0) * (y1 - y0);
          var mean = (integral[y1 * (w + 1) + x1] - integral[y0 * (w + 1) + x1] - integral[y1 * (w + 1) + x0] + integral[y0 * (w + 1) + x0]) / area;
          var v = gray[yy * w + xx] < mean - 10 ? 0 : 255;
          var oi = (yy * w + xx) * 4;
          d[oi] = d[oi + 1] = d[oi + 2] = v;
        }
      }
    } else {
      // Kontrast: je Farbkanal die dunkelsten und hellsten 1 % abschneiden und strecken
      for (var c = 0; c < 3; c++) {
        var hist = new Uint32Array(256);
        for (var j = c; j < d.length; j += 4) { hist[d[j]]++; }
        var lo = 0, hi = 255, acc = 0, cut = n * 0.01;
        while (lo < 254 && acc + hist[lo] < cut) { acc += hist[lo]; lo++; }
        acc = 0;
        while (hi > lo + 1 && acc + hist[hi] < cut) { acc += hist[hi]; hi--; }
        var scale = 255 / Math.max(1, hi - lo);
        for (var k = c; k < d.length; k += 4) {
          var nv = (d[k] - lo) * scale;
          d[k] = nv < 0 ? 0 : (nv > 255 ? 255 : nv);
        }
      }
    }
    ctx.putImageData(id, 0, 0);
    return canvas;
  }

  /* ------------------------------------------------------------ Oberfläche zum Zuschneiden */

  function edit(file, opts) {
    opts = opts || {};
    return loadImage(file).then(function (img) {
      return new Promise(function (resolve) {
        var src = toCanvas(img, MAXSIDE);
        var corners = detectCorners(src);
        var mode = opts.filter || 'contrast';
        var drag = -1;

        var overlay = el('div', { style: 'position:fixed;inset:0;z-index:3000;background:#0e1424;color:#fff;display:flex;flex-direction:column;font:16px system-ui,sans-serif;touch-action:none' });
        var title = el('div', { style: 'padding:.7rem 1rem;font-weight:700;display:flex;justify-content:space-between;align-items:center', text: 'Beleg zuschneiden' });
        var hint = el('span', { style: 'font-weight:400;font-size:.85rem;color:#aab3c5', text: 'Ecken mit dem Finger ziehen' });
        title.appendChild(hint);
        var stage = el('div', { style: 'flex:1;min-height:0;display:flex;align-items:center;justify-content:center;padding:.4rem' });
        var view = el('canvas', { style: 'max-width:100%;max-height:100%;touch-action:none;background:#000' });
        stage.appendChild(view);
        var bar = el('div', { style: 'display:flex;flex-wrap:wrap;gap:.5rem;padding:.7rem;background:#161d33' });
        function btn(text, primary) {
          return el('button', { type: 'button', text: text, style: 'flex:1 1 auto;min-width:6.5rem;padding:.85rem .6rem;border-radius:12px;border:1px solid ' + (primary ? '#1d4ed8' : 'rgba(255,255,255,.25)') + ';background:' + (primary ? '#1d4ed8' : 'rgba(255,255,255,.08)') + ';color:#fff;font:inherit;font-weight:700;cursor:pointer' });
        }
        var bCancel = btn('Abbrechen'), bRotate = btn('Drehen ⟳'), bAuto = btn('Kanten suchen'), bFull = btn('Ganzes Bild'), bOk = btn('Übernehmen', true);
        var sel = el('select', { 'aria-label': 'Darstellung', style: 'flex:1 1 auto;min-width:8rem;padding:.85rem .6rem;border-radius:12px;border:1px solid rgba(255,255,255,.25);background:#26345a;color:#fff;font:inherit;font-weight:700' });
        [['contrast', 'Kontrast (empfohlen)'], ['original', 'Original'], ['bw', 'Schwarzweiß']].forEach(function (o) {
          var op = el('option', { value: o[0], text: o[1] });
          if (o[0] === mode) { op.selected = true; }
          sel.appendChild(op);
        });
        [bCancel, bRotate, bAuto, bFull, sel, bOk].forEach(function (b) { bar.appendChild(b); });
        overlay.appendChild(title); overlay.appendChild(stage); overlay.appendChild(bar);
        document.body.appendChild(overlay);
        var prevOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        var scale = 1;
        function layout() {
          var maxW = stage.clientWidth - 8, maxH = stage.clientHeight - 8;
          scale = Math.min(maxW / src.width, maxH / src.height);
          if (!(scale > 0)) { scale = 0.2; }
          view.width = Math.max(1, Math.round(src.width * scale));
          view.height = Math.max(1, Math.round(src.height * scale));
          draw();
        }
        function draw() {
          var ctx = view.getContext('2d');
          ctx.drawImage(src, 0, 0, view.width, view.height);
          ctx.save();
          ctx.fillStyle = 'rgba(14,20,36,.45)';
          ctx.beginPath();
          ctx.rect(0, 0, view.width, view.height);
          ctx.moveTo(corners[0].x * scale, corners[0].y * scale);
          for (var i = 3; i >= 0; i--) { ctx.lineTo(corners[i].x * scale, corners[i].y * scale); }
          ctx.closePath();
          ctx.fill('evenodd');
          ctx.restore();
          ctx.strokeStyle = '#34d399'; ctx.lineWidth = 3;
          ctx.beginPath();
          corners.forEach(function (c, i) { if (i === 0) { ctx.moveTo(c.x * scale, c.y * scale); } else { ctx.lineTo(c.x * scale, c.y * scale); } });
          ctx.closePath(); ctx.stroke();
          corners.forEach(function (c, i) {
            ctx.beginPath();
            ctx.arc(c.x * scale, c.y * scale, drag === i ? 20 : 15, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(52,211,153,.35)'; ctx.fill();
            ctx.lineWidth = 3; ctx.strokeStyle = '#34d399'; ctx.stroke();
          });
        }
        function pos(ev) {
          var r = view.getBoundingClientRect();
          return { x: (ev.clientX - r.left) * (view.width / r.width) / scale, y: (ev.clientY - r.top) * (view.height / r.height) / scale };
        }
        view.addEventListener('pointerdown', function (ev) {
          var p = pos(ev), best = -1, bd = 1e9;
          corners.forEach(function (c, i) { var dd = dist(c, p); if (dd < bd) { bd = dd; best = i; } });
          if (bd * scale < 60) { drag = best; try { view.setPointerCapture(ev.pointerId); } catch (e) { /* ältere Browser */ } ev.preventDefault(); draw(); }
        });
        view.addEventListener('pointermove', function (ev) {
          if (drag < 0) { return; }
          var p = pos(ev);
          corners[drag] = { x: Math.max(0, Math.min(src.width, p.x)), y: Math.max(0, Math.min(src.height, p.y)) };
          ev.preventDefault(); draw();
        });
        function up() { drag = -1; draw(); }
        view.addEventListener('pointerup', up);
        view.addEventListener('pointercancel', up);

        function close(result) {
          window.removeEventListener('resize', layout);
          document.body.style.overflow = prevOverflow;
          overlay.remove();
          resolve(result);
        }
        bCancel.addEventListener('click', function () { close(null); });
        bRotate.addEventListener('click', function () { src = rotateCanvas(src); corners = detectCorners(src); layout(); });
        bAuto.addEventListener('click', function () { corners = detectCorners(src); draw(); });
        bFull.addEventListener('click', function () { corners = [{ x: 0, y: 0 }, { x: src.width, y: 0 }, { x: src.width, y: src.height }, { x: 0, y: src.height }]; draw(); });
        bOk.addEventListener('click', function () {
          bOk.disabled = true; bOk.textContent = 'Wird verarbeitet …';
          window.setTimeout(function () {
            try {
              var out = enhance(warp(src, corners, MAXSIDE), sel.value);
              out.toBlob(function (blob) {
                if (!blob) { close(null); return; }
                var name = (file.name || 'beleg').replace(/\.[^.]+$/, '') + '.jpg';
                close({ file: new File([blob], name, { type: 'image/jpeg' }), canvas: out });
              }, 'image/jpeg', 0.85);
            } catch (e) { close(null); }
          }, 30);
        });
        window.addEventListener('resize', layout);
        layout();
        // Testzugriff
        overlay.__test = { corners: function () { return corners; }, setCorners: function (c) { corners = c; draw(); }, ok: function () { bOk.click(); } };
        global.__scannerOverlay = overlay;
      });
    });
  }

  /* ------------------------------------------------------------ PDF */

  var enc = new TextEncoder();

  function latin(str) {
    // WinAnsi: Zeichen bis 255 unverändert (nahezu Latin-1), Euro-Zeichen 0x80, alles andere Fragezeichen
    var out = [];
    for (var i = 0; i < str.length; i++) {
      var c = str.charCodeAt(i);
      if (c === 0x20AC) { out.push(0x80); }
      else if (c < 32) { out.push(0x20); }
      else if (c < 256) { out.push(c); }
      else { out.push(0x3F); }
    }
    return out;
  }

  function pdfString(bytes) {
    var s = '(';
    for (var i = 0; i < bytes.length; i++) {
      var b = bytes[i];
      if (b === 0x28 || b === 0x29 || b === 0x5C) { s += '\\' + String.fromCharCode(b); }
      else if (b < 32 || b > 126) { s += '\\' + ('00' + b.toString(8)).slice(-3); }
      else { s += String.fromCharCode(b); }
    }
    return s + ')';
  }

  function canvasToJpeg(canvas, quality) {
    return new Promise(function (resolve, reject) {
      canvas.toBlob(function (blob) {
        if (!blob) { reject(new Error('Das Bild konnte nicht umgewandelt werden.')); return; }
        blob.arrayBuffer().then(function (buf) { resolve(new Uint8Array(buf)); }, reject);
      }, 'image/jpeg', quality || 0.82);
    });
  }

  /* pages: [{canvas, words?: [{text, x0, y0, x1, y1}]}] ergibt ein Blob (PDF). Mit Wörtern wird die PDF durchsuchbar (unsichtbarer Text). */
  function buildPdf(pages) {
    return Promise.all(pages.map(function (p) { return canvasToJpeg(p.canvas, 0.82); })).then(function (jpegs) {
      var chunks = [], offsets = [], length = 0;
      function push(x) {
        var b = typeof x === 'string' ? enc.encode(x) : x;
        chunks.push(b); length += b.length;
      }
      function obj(n, body) { offsets[n] = length; push(n + ' 0 obj\n'); push(body); push('\nendobj\n'); }
      var hasText = pages.some(function (p) { return p.words && p.words.length; });
      var count = pages.length;
      var fontId = 3;
      var firstPage = hasText ? 4 : 3;
      push('%PDF-1.4\n%\xE2\xE3\xCF\xD3\n');
      obj(1, '<< /Type /Catalog /Pages 2 0 R >>');
      var kids = [];
      for (var i = 0; i < count; i++) { kids.push((firstPage + i * 3) + ' 0 R'); }
      obj(2, '<< /Type /Pages /Kids [' + kids.join(' ') + '] /Count ' + count + ' >>');
      if (hasText) { obj(fontId, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'); }
      for (var n = 0; n < count; n++) {
        var pg = pages[n], cv = pg.canvas;
        var pw = cv.width * 72 / 150, ph = cv.height * 72 / 150;
        var pageId = firstPage + n * 3, contId = pageId + 1, imgId = pageId + 2;
        var content = 'q ' + pw.toFixed(2) + ' 0 0 ' + ph.toFixed(2) + ' 0 0 cm /Im0 Do Q\n';
        if (pg.words && pg.words.length) {
          content += 'BT\n3 Tr\n';
          var k = 72 / 150;
          pg.words.forEach(function (w) {
            if (!w.text || !/\S/.test(w.text)) { return; }
            var bw = Math.max(1, (w.x1 - w.x0) * k), bh = Math.max(2, (w.y1 - w.y0) * k);
            var size = Math.max(2, Math.min(bh * 0.9, 60));
            var tz = Math.max(10, Math.min(400, 100 * bw / (w.text.length * 0.52 * size)));
            content += '/F1 ' + size.toFixed(1) + ' Tf ' + tz.toFixed(0) + ' Tz 1 0 0 1 ' + (w.x0 * k).toFixed(2) + ' ' + (ph - w.y1 * k + bh * 0.2).toFixed(2) + ' Tm ' + pdfString(latin(w.text)) + ' Tj\n';
          });
          content += 'ET\n';
        }
        obj(pageId, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + pw.toFixed(2) + ' ' + ph.toFixed(2) + '] /Resources << /XObject << /Im0 ' + imgId + ' 0 R >>' + (hasText ? ' /Font << /F1 ' + fontId + ' 0 R >>' : '') + ' >> /Contents ' + contId + ' 0 R >>');
        var cbytes = new Uint8Array(latin(content));
        offsets[contId] = length; push(contId + ' 0 obj\n'); push('<< /Length ' + cbytes.length + ' >>\nstream\n'); push(cbytes); push('\nendstream\nendobj\n');
        offsets[imgId] = length; push(imgId + ' 0 obj\n'); push('<< /Type /XObject /Subtype /Image /Width ' + cv.width + ' /Height ' + cv.height + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + jpegs[n].length + ' >>\nstream\n'); push(jpegs[n]); push('\nendstream\nendobj\n');
      }
      var total = firstPage + count * 3;
      var xref = length;
      push('xref\n0 ' + total + '\n0000000000 65535 f \n');
      for (var o = 1; o < total; o++) { push(('0000000000' + offsets[o]).slice(-10) + ' 00000 n \n'); }
      push('trailer\n<< /Size ' + total + ' /Root 1 0 R >>\nstartxref\n' + xref + '\n%%EOF\n');
      return new Blob(chunks, { type: 'application/pdf' });
    });
  }

  /* ------------------------------------------------------------ Texterkennung */

  var tesseractReady = null;
  function loadTesseract() {
    if (global.Tesseract) { return Promise.resolve(); }
    if (!tesseractReady) {
      tesseractReady = new Promise(function (resolve, reject) {
        var s = document.createElement('script');
        s.src = BASE + 'tesseract.min.js';
        s.onload = function () { resolve(); };
        s.onerror = function () { tesseractReady = null; reject(new Error('Die Texterkennung konnte nicht geladen werden.')); };
        document.head.appendChild(s);
      });
    }
    return tesseractReady;
  }

  /* canvas oder Liste von Canvas ergibt [{text, words, w, h}]. onProgress(Text, Anteil 0 bis 1) */
  function ocr(canvases, onProgress) {
    var list = Array.isArray(canvases) ? canvases : [canvases];
    var abs = new URL(BASE, location.href).href;
    var current = 0;
    return loadTesseract().then(function () {
      return global.Tesseract.createWorker('deu', 1, {
        workerPath: abs + 'worker.min.js', corePath: abs, langPath: abs, workerBlobURL: false,
        logger: function (m) {
          if (!onProgress) { return; }
          var label = m.status === 'recognizing text' ? 'Text wird gelesen' : (m.status === 'loading language traineddata' || m.status === 'loading tesseract core' ? 'Texterkennung wird geladen' : 'Wird vorbereitet');
          var part = m.status === 'recognizing text' ? m.progress : 0;
          onProgress(label + (list.length > 1 ? ' (Seite ' + (current + 1) + ' von ' + list.length + ')' : ''), (current + part) / list.length);
        },
      });
    }).then(function (worker) {
      var results = [];
      var chain = Promise.resolve();
      list.forEach(function (cv, idx) {
        chain = chain.then(function () {
          current = idx;
          // Kleine Bilder vor dem Lesen vergrößern: Die Schrift sollte mindestens etwa 30 Pixel hoch sein
          var f = Math.max(1, Math.min(3, 1800 / Math.max(cv.width, cv.height)));
          var input = cv;
          if (f > 1.15) {
            input = document.createElement('canvas');
            input.width = Math.round(cv.width * f); input.height = Math.round(cv.height * f);
            var ictx = input.getContext('2d');
            ictx.imageSmoothingQuality = 'high';
            ictx.drawImage(cv, 0, 0, input.width, input.height);
          } else { f = 1; }
          return worker.recognize(input).then(function (r) {
            var words = ((r.data && r.data.words) || []).map(function (w) { return { text: w.text, x0: w.bbox.x0 / f, y0: w.bbox.y0 / f, x1: w.bbox.x1 / f, y1: w.bbox.y1 / f }; });
            results.push({ text: (r.data && r.data.text) || '', words: words, w: cv.width, h: cv.height });
          });
        });
      });
      return chain.then(function () { return worker.terminate(); }, function (e) { worker.terminate(); throw e; }).then(function () { return results; });
    });
  }

  /* ------------------------------------------------------------ Auswertung des Textes */

  var MONEY = /(?:^|[^\d.,])(\d{1,3}(?:[. ]\d{3})+,\d{2}|\d+,\d{2})(?!\d)/g;

  function toCents(str) {
    var n = str.replace(/[. ]/g, '').replace(',', '.');
    return Math.round(parseFloat(n) * 100);
  }

  function fmt(cents) {
    var s = String(Math.abs(cents));
    while (s.length < 3) { s = '0' + s; }
    var euro = s.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return euro + ',' + s.slice(-2);
  }

  function validDate(y, m, d, today) {
    var dt = new Date(y, m - 1, d);
    if (dt.getFullYear() !== y || dt.getMonth() !== m - 1 || dt.getDate() !== d) { return false; }
    if (y < 2000) { return false; }
    return dt.getTime() <= today.getTime() + 86400000;
  }

  /* text: erkannter Text. vendors: Namen bekannter Lieferanten. today: Date (zum Testen). Gibt gefundene Werte zurück, Leeres bleibt weg. */
  function parseReceipt(text, vendors, today) {
    today = today || new Date();
    text = String(text || '').replace(/\r/g, '');
    var lines = text.split('\n').map(function (l) { return l.replace(/[ \t]+/g, ' ').trim(); }).filter(function (l) { return l !== ''; });
    var res = {};

    // Betrag: Zeilen mit Schlüsselwort. Starke Wörter zuerst, Nettobeträge und Steuerzeilen auslassen.
    var strong = /(zu zahlen|zahlbetrag|rechnungsbetrag|gesamtbetrag|endbetrag|bruttobetrag|gesamtsumme|summe brutto|brutto|total|gesamt)/i;
    var weak = /(summe|betrag|zahlung)/i;
    var skip = /(netto|mwst|mehrwertsteuer|umsatzsteuer|\bust\b|steuer|zwischensumme|rabatt|skonto)/i;
    var best = null, level = 0;
    lines.forEach(function (l, i) {
      var lv = strong.test(l) ? 2 : (weak.test(l) ? 1 : 0);
      if (!lv) { return; }
      if (skip.test(l) && !/brutto|zu zahlen|gesamtbetrag|rechnungsbetrag/i.test(l)) { return; }
      var found = [], m;
      var cand = l + ' ' + (lines[i + 1] && !/[a-zA-Z]{4,}/.test(lines[i + 1]) ? lines[i + 1] : '');
      MONEY.lastIndex = 0;
      while ((m = MONEY.exec(cand)) !== null) { found.push(toCents(m[1])); }
      if (!found.length) { return; }
      var v = Math.max.apply(null, found);
      if (lv > level || (lv === level && v > best)) { best = v; level = lv; }
    });
    if (best !== null) { res.amount = fmt(best); res.amountSure = level === 2; }
    else {
      var all = [], mm;
      MONEY.lastIndex = 0;
      while ((mm = MONEY.exec(text)) !== null) { all.push(toCents(mm[1])); }
      if (all.length) { res.amount = fmt(Math.max.apply(null, all)); res.amountSure = false; }
    }

    // Datum: Rechnungsdatum bevorzugen, Fälligkeit und Leistungsdatum meiden
    var DATE = /(?:^|[^\d])(0?[1-9]|[12]\d|3[01])\.(0?[1-9]|1[0-2])\.(20\d{2}|\d{2})(?!\d)/g;
    var pref = null, first = null, later = null;
    lines.forEach(function (l) {
      var m2;
      DATE.lastIndex = 0;
      while ((m2 = DATE.exec(l)) !== null) {
        var y = parseInt(m2[3], 10); if (y < 100) { y += 2000; }
        var d = parseInt(m2[1], 10), mo = parseInt(m2[2], 10);
        if (!validDate(y, mo, d, today)) { continue; }
        var iso = y + '-' + ('0' + mo).slice(-2) + '-' + ('0' + d).slice(-2);
        if (/(rechnungsdatum|belegdatum|rechnung vom|datum)/i.test(l) && !/(fällig|zahlbar|liefer|leistung|bis)/i.test(l)) { pref = pref || iso; }
        else if (/(fällig|zahlbar|liefer|leistung|bis)/i.test(l)) { later = later || iso; }
        else { first = first || iso; }
      }
    });
    var dateFound = pref || first || later;
    if (dateFound) { res.date = dateFound; res.dateSure = !!pref; }

    // Rechnungsnummer
    var NR = /(?:rechnungs?[- ]?(?:nr|nummer)|rechnung nr|re[.-]? ?nr|beleg[- ]?(?:nr|nummer)|rg[.-]? ?nr|invoice (?:no|number|nr)|belegnummer)\.?\s*[:#]?\s*([A-Z0-9ÄÖÜ][A-Z0-9ÄÖÜ\-\/.]{2,30})/i;
    for (var i2 = 0; i2 < lines.length; i2++) {
      var mn = NR.exec(lines[i2]);
      if (mn) {
        var cand2 = mn[1].replace(/[.\-\/]+$/, '');
        if (/\d/.test(cand2)) { res.invoice = cand2; break; }
      }
    }

    // Lieferant: bekannte Namen zuerst, sonst eine Zeile mit Rechtsform im Kopf
    var lower = text.toLowerCase();
    var known = (vendors || []).filter(function (v) { return v && v.length >= 3 && lower.indexOf(v.toLowerCase()) !== -1; })
      .sort(function (a, b) { return b.length - a.length; });
    if (known.length) { res.vendor = known[0]; res.vendorKnown = true; }
    else {
      var head = lines.slice(0, 8);
      var legal = /(gmbh|\bag\b|\bkg\b|\bohg\b|\bug\b|e\.k\.|\bse\b|& co|gbr)/i;
      var pick = head.filter(function (l) { return legal.test(l) && l.length <= 80 && !/(rechnung|datum|steuer)/i.test(l); })[0]
        || head.filter(function (l) { return /^[A-Za-zÄÖÜäöüß][A-Za-zÄÖÜäöüß0-9 &.,'-]{3,60}$/.test(l) && !/(rechnung|datum|beleg|kunde|seite|tel|fax|www|iban)/i.test(l); })[0];
      if (pick) { res.vendor = pick.replace(/\s{2,}/g, ' ').slice(0, 80); res.vendorKnown = false; }
    }
    return res;
  }

  global.BelegScanner = {
    edit: edit, buildPdf: buildPdf, ocr: ocr, parseReceipt: parseReceipt,
    _t: { detectCorners: detectCorners, warp: warp, enhance: enhance, toCanvas: toCanvas, loadImage: loadImage, quadMap: quadMap, rotateCanvas: rotateCanvas, latin: latin },
  };
})(window);
