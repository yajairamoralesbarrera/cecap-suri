(() => {
    let deferredInstallPrompt = null;

    // 1. Registro Service Worker PWA
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
        });
    }

    // 2. Gestión de instalación PWA Universal (Desktop, Android, guía iOS)
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredInstallPrompt = e;
        document.querySelectorAll('[data-pwa-install-btn]').forEach((btn) => {
            btn.classList.remove('hidden');
            btn.removeAttribute('disabled');
        });
    });

    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        document.querySelectorAll('[data-pwa-status]').forEach((el) => {
            el.textContent = 'App instalada';
        });
    });

    function triggerPwaInstall() {
        if (deferredInstallPrompt) {
            deferredInstallPrompt.prompt();
            deferredInstallPrompt.userChoice.finally(() => {
                deferredInstallPrompt = null;
            });
            return;
        }
        const modal = document.getElementById('cecap-pwa-guide-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    // 3. Sidebar colapsable limpio (persistido en localStorage)
    function applySidebarState(collapsed) {
        const sidebar = document.getElementById('cecap-sidebar');
        const mainWrap = document.getElementById('cecap-main-wrap');
        if (!sidebar) return;

        sidebar.setAttribute('data-sidebar-collapsed', collapsed ? 'true' : 'false');
        document.body.setAttribute('data-sidebar-collapsed', collapsed ? 'true' : 'false');
        if (collapsed) {
            sidebar.classList.remove('md:w-64');
            sidebar.classList.add('md:w-20');
            if (mainWrap) {
                mainWrap.classList.remove('md:pl-64');
                mainWrap.classList.add('md:pl-20');
            }
        } else {
            sidebar.classList.remove('md:w-20');
            sidebar.classList.add('md:w-64');
            if (mainWrap) {
                mainWrap.classList.remove('md:pl-20');
                mainWrap.classList.add('md:pl-64');
            }
        }
    }

    function initSidebar() {
        const saved = localStorage.getItem('cecap_sidebar_collapsed') === '1';
        applySidebarState(saved);
    }

    // 4. Visor PDF Modal seguro (usa ?vista=html para vista previa instantánea CSP-safe + descarga Blob PDF)
    let activeBlobUrl = null;

    function getAppBaseUrl() {
        const lwScript = document.querySelector('script[data-update-uri]');
        const updateUri = lwScript?.getAttribute('data-update-uri');
        if (updateUri) {
            try {
                const u = new URL(updateUri, window.location.href);
                const basePath = u.pathname.replace(/\/livewire[^/]*\/update\/?$/i, '');
                return u.origin + basePath;
            } catch {}
        }
        return window.location.origin;
    }

    function buildRelativePath(rawUrl, setParams = {}, deleteParams = []) {
        const u = new URL(rawUrl, 'http://cecap.local');
        for (const key of deleteParams) {
            u.searchParams.delete(key);
        }
        for (const [k, v] of Object.entries(setParams)) {
            u.searchParams.set(k, v);
        }
        return u.pathname + u.search + u.hash;
    }

    function buildUrlCandidates(rawUrl, setParams = {}, deleteParams = []) {
        const rel = buildRelativePath(rawUrl, setParams, deleteParams);
        const appBase = getAppBaseUrl().replace(/\/+$/, '');
        const list = [appBase + rel, rel];
        if (window.location.origin && window.location.origin !== 'null') {
            list.push(window.location.origin + rel);
        }
        return [...new Set(list)];
    }

    async function fetchFirstOk(candidates) {
        let lastErr = 'Error de red';
        for (const candidate of candidates) {
            try {
                const resp = await fetch(candidate, { credentials: 'same-origin' });
                if (resp.ok) {
                    return resp;
                }
                lastErr = 'HTTP ' + resp.status + ' (' + candidate + ')';
            } catch (err) {
                lastErr = (err?.message || 'Error de red') + ' (' + candidate + ')';
            }
        }
        throw new Error(lastErr);
    }

    function openPdfModal(url, title) {
        const modal = document.getElementById('cecap-pdf-modal');
        const frame = document.getElementById('cecap-pdf-frame');
        const titleEl = document.getElementById('cecap-pdf-title');
        const downloadBtn = document.getElementById('cecap-pdf-download');
        const openTabBtn = document.getElementById('cecap-pdf-open-tab');
        const modeHtmlBtn = document.getElementById('cecap-pdf-mode-html');
        const modeBlobBtn = document.getElementById('cecap-pdf-mode-blob');
        if (!modal || !frame) return;

        if (activeBlobUrl) {
            URL.revokeObjectURL(activeBlobUrl);
            activeBlobUrl = null;
        }

        const htmlRel = buildRelativePath(url, { vista: 'html' });
        const dlRel = buildRelativePath(url, { descargar: '1' }, ['vista']);
        const pdfRel = buildRelativePath(url, {}, ['vista', 'descargar']);
        const appBase = getAppBaseUrl().replace(/\/+$/, '');

        if (titleEl) titleEl.textContent = title || 'Documento Oficial CeCap';
        if (downloadBtn) {
            downloadBtn.textContent = 'Descargar PDF';
            downloadBtn.setAttribute('href', appBase + dlRel);
            downloadBtn.dataset.pdfDownloadUrl = dlRel;
            downloadBtn.dataset.pdfFilename = ((title || 'Documento-CeCap').replace(/[^\w\-]+/g, '-')) + '.pdf';
        }
        if (openTabBtn) openTabBtn.setAttribute('href', appBase + htmlRel);
        modal.dataset.currentUrl = pdfRel;
        modal.dataset.currentHtmlUrl = htmlRel;

        // Por defecto carga ?vista=html vía fetch -> srcdoc (evita bloqueos de iframes anidados y proxies)
        if (modeHtmlBtn) modeHtmlBtn.setAttribute('aria-pressed', 'true');
        if (modeBlobBtn) modeBlobBtn.setAttribute('aria-pressed', 'false');

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        loadHtmlIntoModalFrame(htmlRel);
    }

    async function loadHtmlIntoModalFrame(htmlRel) {
        const frame = document.getElementById('cecap-pdf-frame');
        if (!frame || !htmlRel) return;

        frame.removeAttribute('src');
        frame.srcdoc = '<!DOCTYPE html><html lang="es"><body style="font-family:sans-serif;color:#475569;padding:32px;text-align:center;">Cargando vista previa del documento...</body></html>';

        try {
            const resp = await fetchFirstOk(buildUrlCandidates(htmlRel));
            const html = await resp.text();
            frame.removeAttribute('src');
            frame.srcdoc = html;
        } catch (err) {
            const detail = String(err?.message || 'Error desconocido').replace(/[<>&"]/g, '');
            frame.removeAttribute('src');
            frame.srcdoc = '<!DOCTYPE html><html lang="es"><body style="font-family:sans-serif;color:#9f1239;padding:32px;text-align:center;"><p>No se pudo cargar la vista previa. Utilice el botón <strong>Descargar PDF</strong>.</p><p style="font-size:11px;color:#64748b;margin-top:12px;">Detalle: ' + detail + '</p></body></html>';
        }
    }

    function extractFilename(contentDisposition, fallback) {
        if (contentDisposition) {
            const utf8Match = contentDisposition.match(/filename\*=UTF-8''([^;\n]+)/i);
            if (utf8Match && utf8Match[1]) {
                try {
                    return decodeURIComponent(utf8Match[1].trim());
                } catch {
                    return utf8Match[1].trim();
                }
            }
            const asciiMatch = contentDisposition.match(/filename="?([^";\n]+)"?/i);
            if (asciiMatch && asciiMatch[1]) {
                return asciiMatch[1].trim();
            }
        }
        return fallback || 'documento-cecap.pdf';
    }

    async function downloadViaBlob(url, fallbackFilename, triggerEl) {
        if (!url || url === '#') return;

        const prevText = triggerEl ? triggerEl.textContent : null;
        if (triggerEl) {
            triggerEl.setAttribute('aria-busy', 'true');
            triggerEl.textContent = 'Descargando...';
        }

        try {
            const resp = await fetchFirstOk(buildUrlCandidates(url));
            const contentType = resp.headers.get('Content-Type') || 'application/octet-stream';
            const filename = extractFilename(resp.headers.get('Content-Disposition'), fallbackFilename);
            const buffer = await resp.arrayBuffer();
            const blob = new Blob([buffer], { type: contentType });
            const blobUrl = URL.createObjectURL(blob);

            const tempLink = document.createElement('a');
            tempLink.href = blobUrl;
            tempLink.download = filename;
            tempLink.style.display = 'none';
            document.body.appendChild(tempLink);
            tempLink.click();
            document.body.removeChild(tempLink);

            setTimeout(() => URL.revokeObjectURL(blobUrl), 60000);
        } catch {
            const fallbackUrl = getAppBaseUrl().replace(/\/+$/, '') + buildRelativePath(url, { vista: 'html' }, ['descargar']);
            window.open(fallbackUrl, '_blank', 'noopener');
        } finally {
            if (triggerEl && prevText !== null) {
                triggerEl.removeAttribute('aria-busy');
                triggerEl.textContent = prevText;
            }
        }
    }

    async function switchPdfModalToBlob() {
        const modal = document.getElementById('cecap-pdf-modal');
        const frame = document.getElementById('cecap-pdf-frame');
        const modeHtmlBtn = document.getElementById('cecap-pdf-mode-html');
        const modeBlobBtn = document.getElementById('cecap-pdf-mode-blob');
        if (!modal || !frame || !modal.dataset.currentUrl) return;

        try {
            const resp = await fetchFirstOk(buildUrlCandidates(modal.dataset.currentUrl));
            const buffer = await resp.arrayBuffer();
            const blob = new Blob([buffer], { type: 'application/pdf' });
            if (activeBlobUrl) URL.revokeObjectURL(activeBlobUrl);
            activeBlobUrl = URL.createObjectURL(blob);
            frame.removeAttribute('srcdoc');
            frame.src = activeBlobUrl;
            if (modeHtmlBtn) modeHtmlBtn.setAttribute('aria-pressed', 'false');
            if (modeBlobBtn) modeBlobBtn.setAttribute('aria-pressed', 'true');
        } catch {
            loadHtmlIntoModalFrame(modal.dataset.currentHtmlUrl);
            if (modeHtmlBtn) modeHtmlBtn.setAttribute('aria-pressed', 'true');
            if (modeBlobBtn) modeBlobBtn.setAttribute('aria-pressed', 'false');
        }
    }

    function closePdfModal() {
        const modal = document.getElementById('cecap-pdf-modal');
        const frame = document.getElementById('cecap-pdf-frame');
        if (frame) {
            frame.removeAttribute('srcdoc');
            frame.src = 'about:blank';
        }
        if (activeBlobUrl) {
            URL.revokeObjectURL(activeBlobUrl);
            activeBlobUrl = null;
        }
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    // Delegación global de eventos (100% CSP-safe, compatible con wire:navigate)
    document.addEventListener('click', (e) => {
        const pwToggle = e.target.closest('[data-password-toggle]');
        if (pwToggle) {
            e.preventDefault();
            const targetId = pwToggle.getAttribute('data-password-toggle');
            const input = targetId ? document.getElementById(targetId) : pwToggle.parentElement?.querySelector('input');
            if (input) {
                const showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                pwToggle.setAttribute('aria-pressed', showing ? 'false' : 'true');
                pwToggle.setAttribute('aria-label', showing ? 'Mostrar contraseña' : 'Ocultar contraseña');
                pwToggle.querySelector('[data-eye-open]')?.classList.toggle('hidden', !showing);
                pwToggle.querySelector('[data-eye-closed]')?.classList.toggle('hidden', showing);
            }
            return;
        }

        const toggleBtn = e.target.closest('[data-sidebar-toggle]');
        if (toggleBtn) {
            const sidebar = document.getElementById('cecap-sidebar');
            const next = sidebar?.getAttribute('data-sidebar-collapsed') !== 'true';
            localStorage.setItem('cecap_sidebar_collapsed', next ? '1' : '0');
            applySidebarState(next);
            return;
        }

        const mobileToggle = e.target.closest('[data-mobile-drawer-toggle]');
        if (mobileToggle) {
            const drawer = document.getElementById('cecap-mobile-drawer');
            if (drawer) drawer.classList.toggle('hidden');
            return;
        }

        const pwaBtn = e.target.closest('[data-pwa-install-btn]');
        if (pwaBtn) {
            e.preventDefault();
            triggerPwaInstall();
            return;
        }

        const closePwaGuide = e.target.closest('[data-pwa-guide-close]');
        if (closePwaGuide) {
            const modal = document.getElementById('cecap-pwa-guide-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
            return;
        }

        const pdfTrigger = e.target.closest('[data-pdf-modal-url]');
        if (pdfTrigger) {
            e.preventDefault();
            openPdfModal(pdfTrigger.dataset.pdfModalUrl, pdfTrigger.dataset.pdfModalTitle);
            return;
        }

        const pdfDownloadTrigger = e.target.closest('#cecap-pdf-download, [data-pdf-download-url]');
        if (pdfDownloadTrigger) {
            e.preventDefault();
            const targetUrl = pdfDownloadTrigger.dataset.pdfDownloadUrl || pdfDownloadTrigger.getAttribute('href');
            downloadViaBlob(targetUrl, pdfDownloadTrigger.dataset.pdfFilename || 'documento-cecap.pdf', pdfDownloadTrigger);
            return;
        }

        const attachmentLink = e.target.closest('a[href*="/adjuntos/"]');
        if (attachmentLink) {
            e.preventDefault();
            const label = (attachmentLink.textContent || 'Adjunto Privado').trim();
            openPdfModal(attachmentLink.getAttribute('href'), label);
            const dlBtn = document.getElementById('cecap-pdf-download');
            if (dlBtn) {
                dlBtn.textContent = 'Descargar Archivo';
            }
            return;
        }

        const binaryLink = e.target.closest('a[href*="/direccion/exportar/"]');
        if (binaryLink) {
            e.preventDefault();
            downloadViaBlob(binaryLink.getAttribute('href'), '', binaryLink);
            return;
        }

        if (e.target.closest('[data-pdf-modal-close]')) {
            closePdfModal();
            return;
        }

        if (e.target.closest('#cecap-pdf-mode-html')) {
            const modal = document.getElementById('cecap-pdf-modal');
            const modeHtmlBtn = document.getElementById('cecap-pdf-mode-html');
            const modeBlobBtn = document.getElementById('cecap-pdf-mode-blob');
            if (modal && modal.dataset.currentHtmlUrl) {
                loadHtmlIntoModalFrame(modal.dataset.currentHtmlUrl);
                if (modeHtmlBtn) modeHtmlBtn.setAttribute('aria-pressed', 'true');
                if (modeBlobBtn) modeBlobBtn.setAttribute('aria-pressed', 'false');
            }
            return;
        }

        if (e.target.closest('#cecap-pdf-mode-blob')) {
            switchPdfModalToBlob();
            return;
        }

        if (e.target.closest('#cecap-pdf-print')) {
            const frame = document.getElementById('cecap-pdf-frame');
            try {
                frame?.contentWindow?.print();
            } catch {
                window.open(document.getElementById('cecap-pdf-open-tab')?.href, '_blank');
            }
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closePdfModal();
            const guide = document.getElementById('cecap-pwa-guide-modal');
            if (guide) {
                guide.classList.add('hidden');
                guide.classList.remove('flex');
            }
        }
    });

    document.addEventListener('DOMContentLoaded', initSidebar);
    document.addEventListener('livewire:navigated', initSidebar);
})();
