/* ============================================================================
   Lead document uploader.

   Used on both the documentation portal and the CRM lead page. Reads the lead
   id from #docUploader's data attribute rather than a PHP-inlined variable, so
   one file serves both.
   ========================================================================== */
(function () {
    'use strict';

    var rootEl = document.getElementById('docUploader');
    if (!rootEl) return;
    var leadId = parseInt(rootEl.dataset.leadId, 10);

    function init() {
    function refreshCount() {
        var n = document.querySelectorAll('.doc-item').length;
        var c = document.getElementById('docCount');
        var e = document.getElementById('docEmpty');
        if (c) c.textContent = n;
        if (e) e.hidden = n > 0;
    }

    document.querySelectorAll('.doc-del').forEach(function (b) {
        b.addEventListener('click', function () {
            if (!confirm('Delete this file?')) return;
            b.disabled = true;
            apiFetch('/doc-portal/delete-file', { body: JSON.stringify({ id: b.dataset.id }) })
                .then(function (res) {
                    if (res && res.ok) { b.closest('.doc-item').remove(); refreshCount(); }
                    else { b.disabled = false; showToast('danger', (res && res.error) || 'Could not delete.'); }
                });
        });
    });

    // ── Document manager ──────────────────────────────────────────────────
    var dz        = document.getElementById('dropZone');
    var fileInput = document.getElementById('fileInput');
    var camInput  = document.getElementById('cameraInput');
    var queue     = document.getElementById('queue');
    var queueBar  = document.getElementById('queueBar');
    var uploadBtn = document.getElementById('uploadBtn');
    var errBox    = document.getElementById('uploadError');
    var pending   = [];

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }
    function prettySize(b) {
        if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB';
        if (b >= 1024)    return Math.round(b / 1024) + ' KB';
        return b + ' B';
    }
    function showErr(msg) { errBox.innerHTML = msg; errBox.style.display = ''; }
    function clearErr()   { errBox.style.display = 'none'; }

    // Pre-fill each label from the filename so a hurried user can just hit
    // Upload, while still being able to correct anything.
    function suggestLabel(name) {
        return name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ').slice(0, 120);
    }

    function renderQueue() {
        queue.innerHTML = '';
        pending.forEach(function (item, i) {
            var row = document.createElement('div');
            row.className = 'q-row';

            var thumb = document.createElement('div');
            thumb.className = 'q-thumb';
            if (item.file.type.indexOf('image/') === 0) {
                var img = document.createElement('img');
                img.src = URL.createObjectURL(item.file);
                img.onload = function () { URL.revokeObjectURL(img.src); };
                thumb.appendChild(img);
            } else {
                thumb.innerHTML = '<i class="bi bi-file-earmark"></i>';
            }

            var mid = document.createElement('div');
            mid.className = 'q-mid';
            var inp = document.createElement('input');
            inp.type = 'text';
            inp.className = 'v2-input q-label';
            inp.value = item.label;
            inp.maxLength = 120;
            inp.placeholder = 'Name this document';
            inp.addEventListener('input', function () { pending[i].label = inp.value; });
            var sub = document.createElement('div');
            sub.className = 'q-sub';
            sub.textContent = item.file.name + ' · ' + prettySize(item.file.size);
            mid.appendChild(inp);
            mid.appendChild(sub);

            var bar = document.createElement('div');
            bar.className = 'q-bar';
            bar.innerHTML = '<span></span>';

            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'doc-act';
            rm.innerHTML = '<i class="bi bi-x-lg"></i>';
            rm.addEventListener('click', function () {
                pending.splice(i, 1);
                renderQueue();
            });

            row.appendChild(thumb);
            row.appendChild(mid);
            row.appendChild(rm);
            mid.appendChild(bar);
            queue.appendChild(row);
        });

        var any = pending.length > 0;
        queue.hidden = !any;
        queueBar.hidden = !any;
        document.getElementById('queueCount').textContent = any ? '(' + pending.length + ')' : '';
    }

    function addFiles(list) {
        clearErr();
        var over = [];
        Array.prototype.forEach.call(list, function (f) {
            if (pending.length >= 20) return;
            // Checked here too so the user hears about it before a long upload,
            // not after. The server still enforces the real limit.
            if (f.size > 25 * 1024 * 1024) { over.push(f.name); return; }
            pending.push({ file: f, label: suggestLabel(f.name) });
        });
        if (over.length) showErr('Too large (25 MB max): ' + over.join(', '));
        renderQueue();
    }

    if (dz) {
        dz.addEventListener('click', function () { fileInput.click(); });
        dz.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
        });
        ['dragenter', 'dragover'].forEach(function (ev) {
            dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('is-over'); });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove('is-over'); });
        });
        dz.addEventListener('drop', function (e) {
            if (e.dataTransfer && e.dataTransfer.files) addFiles(e.dataTransfer.files);
        });
        document.getElementById('pickBtn').addEventListener('click', function () { fileInput.click(); });
        document.getElementById('cameraBtn').addEventListener('click', function () { camInput.click(); });
        fileInput.addEventListener('change', function () { addFiles(fileInput.files); fileInput.value = ''; });
        camInput.addEventListener('change', function () { addFiles(camInput.files); camInput.value = ''; });
        document.getElementById('clearBtn').addEventListener('click', function () {
            pending = []; renderQueue(); clearErr();
        });
    }

    if (uploadBtn) uploadBtn.addEventListener('click', function () {
        if (!pending.length) return;
        clearErr();
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = 'Uploading…';

        var fd = new FormData();
        fd.append('csrf_token', csrf());
        fd.append('lead_id', leadId);
        pending.forEach(function (p) {
            fd.append('files[]', p.file);
            fd.append('labels[]', p.label);
        });

        // XHR rather than fetch: it reports upload progress, which matters when
        // someone is sending phone photos over mobile data.
        var xhr = new XMLHttpRequest();
        xhr.open('POST', BASE_URL + '/doc-portal/upload');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.addEventListener('progress', function (e) {
            if (!e.lengthComputable) return;
            var pct = Math.round((e.loaded / e.total) * 100);
            queue.querySelectorAll('.q-bar span').forEach(function (b) { b.style.width = pct + '%'; });
        });

        xhr.onload = function () {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="bi bi-upload"></i>Upload <span id="queueCount"></span>';
            var res = {};
            try { res = JSON.parse(xhr.responseText); } catch (e) {}

            if (res.errors && res.errors.length) {
                showErr(res.errors.map(function (x) {
                    return String(x).replace(/[<>]/g, '');
                }).join('<br>'));
            }
            if (res.uploaded > 0) {
                // Reload so the new rows arrive rendered by the server, with
                // their real ids and compressed sizes.
                window.location.reload();
            }
        };
        xhr.onerror = function () {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="bi bi-upload"></i>Upload';
            showErr('Upload failed. Check your connection and try again.');
        };
        xhr.send(fd);
    });

    // Rename in place — click the name, edit, blur or Enter to save.
    document.querySelectorAll('.doc-label').forEach(function (el) {
        el.addEventListener('click', function () {
            if (el.querySelector('input')) return;
            var old = el.textContent.trim();
            var inp = document.createElement('input');
            inp.type = 'text';
            inp.className = 'v2-input';
            inp.value = old;
            inp.maxLength = 120;
            inp.style.cssText = 'padding:6px 9px;min-height:34px;font-size:14px';
            el.textContent = '';
            el.appendChild(inp);
            inp.focus();
            inp.select();

            function save() {
                var val = inp.value.trim();
                if (!val || val === old) { el.textContent = old; return; }
                apiFetch('/doc-portal/rename', { body: JSON.stringify({ id: el.dataset.id, label: val }) })
                    .then(function (res) {
                        el.textContent = (res && res.ok) ? res.label : old;
                        if (!res || !res.ok) showToast('danger', (res && res.error) || 'Could not rename.');
                    });
            }
            inp.addEventListener('blur', save);
            inp.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); inp.blur(); }
                if (e.key === 'Escape') { el.textContent = old; }
            });
        });
    });

    // Lightbox preview
    var lb = document.getElementById('lightbox');
    function openLb(src, title) {
        document.getElementById('lbImg').src = src;
        document.getElementById('lbTitle').textContent = title || '';
        lb.hidden = false;
        document.body.style.overflow = 'hidden';
    }
    function closeLb() {
        lb.hidden = true;
        document.getElementById('lbImg').src = '';
        document.body.style.overflow = '';
    }
    document.querySelectorAll('.doc-preview').forEach(function (b) {
        b.addEventListener('click', function () { openLb(b.dataset.src, b.dataset.title); });
    });
    document.querySelectorAll('.doc-thumb[data-full]').forEach(function (t) {
        t.addEventListener('click', function () { openLb(t.dataset.full, t.alt); });
    });
    document.getElementById('lbClose').addEventListener('click', closeLb);
    lb.addEventListener('click', function (e) { if (e.target === lb) closeLb(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !lb.hidden) closeLb(); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
