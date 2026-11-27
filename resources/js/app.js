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

        const rawParsed = new URL(url, window.location.origin);
        const parsed = new URL(rawParsed.pathname + rawParsed.search + rawParsed.hash, window.location.origin);

        const htmlUrl = new URL(parsed.toString());
        htmlUrl.searchParams.set('vista', 'html');

        const dlUrl = new URL(parsed.toString());
        dlUrl.searchParams.delete('vista');
        dlUrl.searchParams.set('descargar', '1');

        const pdfUrl = new URL(parsed.toString());
        pdfUrl.searchParams.delete('vista');

        if (titleEl) titleEl.textContent = title || 'Documento Oficial CeCap';
        if (downloadBtn) {
            downloadBtn.setAttribute('href', dlUrl.toString());
            downloadBtn.dataset.pdfFilename = ((title || 'Documento-CeCap').replace(/[^\w\-]+/g, '-')) + '.pdf';
        }
        if (openTabBtn) openTabBtn.setAttribute('href', htmlUrl.toString());
        modal.dataset.currentUrl = pdfUrl.toString();
        modal.dataset.currentHtmlUrl = htmlUrl.toString();

        // Por defecto carga ?vista=html (siempre compatible en iOS, Android, iframes y navegadores estrictos)
        frame.src = htmlUrl.toString();
        if (modeHtmlBtn) modeHtmlBtn.setAttribute('aria-pressed', 'true');
        if (modeBlobBtn) modeBlobBtn.setAttribute('aria-pressed', 'false');

        modal.classList.remove('hidden');
        modal.classList.add('flex');
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
        const rawParsed = new URL(url, window.location.origin);
        const normalized = new URL(rawParsed.pathname + rawParsed.search + rawParsed.hash, window.location.origin);

        const prevText = triggerEl ? triggerEl.textContent : null;
        if (triggerEl) {
            triggerEl.setAttribute('aria-busy', 'true');
            triggerEl.textContent = 'Descargando...';
        }

        try {
            const resp = await fetch(normalized.toString(), {
                credentials: 'same-origin',
            });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);

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
            // Fallback seguro: abrir vista HTML imprimible si la descarga directa falla
            const fallbackUrl = new URL(normalized.toString());
            fallbackUrl.searchParams.delete('descargar');
            fallbackUrl.searchParams.set('vista', 'html');
            window.open(fallbackUrl.toString(), '_blank', 'noopener');
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
            const resp = await fetch(modal.dataset.currentUrl, {
                headers: { Accept: 'application/pdf' },
                credentials: 'same-origin',
            });
            if (!resp.ok) throw new Error('PDF fetch failed');
            const buffer = await resp.arrayBuffer();
            const blob = new Blob([buffer], { type: 'application/pdf' });
            if (activeBlobUrl) URL.revokeObjectURL(activeBlobUrl);
            activeBlobUrl = URL.createObjectURL(blob);
            frame.src = activeBlobUrl;
            if (modeHtmlBtn) modeHtmlBtn.setAttribute('aria-pressed', 'false');
            if (modeBlobBtn) modeBlobBtn.setAttribute('aria-pressed', 'true');
        } catch {
            frame.src = modal.dataset.currentHtmlUrl;
            if (modeHtmlBtn) modeHtmlBtn.setAttribute('aria-pressed', 'true');
            if (modeBlobBtn) modeBlobBtn.setAttribute('aria-pressed', 'false');
        }
    }

    function closePdfModal() {
        const modal = document.getElementById('cecap-pdf-modal');
        const frame = document.getElementById('cecap-pdf-frame');
        if (frame) frame.src = 'about:blank';
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

        const binaryLink = e.target.closest('a[href*="/adjuntos/"], a[href*="/direccion/exportar/"]');
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
            const frame = document.getElementById('cecap-pdf-frame');
            const modeHtmlBtn = document.getElementById('cecap-pdf-mode-html');
            const modeBlobBtn = document.getElementById('cecap-pdf-mode-blob');
            if (modal && frame && modal.dataset.currentHtmlUrl) {
                frame.src = modal.dataset.currentHtmlUrl;
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
