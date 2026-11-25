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

        const parsed = new URL(url, window.location.origin);
        const htmlUrl = new URL(parsed.toString());
        htmlUrl.searchParams.set('vista', 'html');

        const dlUrl = new URL(parsed.toString());
        dlUrl.searchParams.delete('vista');
        dlUrl.searchParams.set('descargar', '1');

        const pdfUrl = new URL(parsed.toString());
        pdfUrl.searchParams.delete('vista');

        if (titleEl) titleEl.textContent = title || 'Documento Oficial CeCap';
        if (downloadBtn) downloadBtn.setAttribute('href', dlUrl.toString());
        if (openTabBtn) openTabBtn.setAttribute('href', pdfUrl.toString());
        modal.dataset.currentUrl = pdfUrl.toString();
        modal.dataset.currentHtmlUrl = htmlUrl.toString();

        // Por defecto carga ?vista=html (siempre compatible en iOS, Android y navegadores estrictos)
        frame.src = htmlUrl.toString();
        if (modeHtmlBtn) modeHtmlBtn.setAttribute('aria-pressed', 'true');
        if (modeBlobBtn) modeBlobBtn.setAttribute('aria-pressed', 'false');

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    async function switchPdfModalToBlob() {
        const modal = document.getElementById('cecap-pdf-modal');
        const frame = document.getElementById('cecap-pdf-frame');
        if (!modal || !frame || !modal.dataset.currentUrl) return;

        try {
            const resp = await fetch(modal.dataset.currentUrl, {
                headers: { Accept: 'application/pdf' },
                credentials: 'same-origin',
            });
            if (!resp.ok) throw new Error('PDF fetch failed');
            const blob = await resp.blob();
            if (activeBlobUrl) URL.revokeObjectURL(activeBlobUrl);
            activeBlobUrl = URL.createObjectURL(blob);
            frame.src = activeBlobUrl;
        } catch {
            frame.src = modal.dataset.currentHtmlUrl;
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

        if (e.target.closest('[data-pdf-modal-close]')) {
            closePdfModal();
            return;
        }

        if (e.target.closest('#cecap-pdf-mode-html')) {
            const modal = document.getElementById('cecap-pdf-modal');
            const frame = document.getElementById('cecap-pdf-frame');
            if (modal && frame && modal.dataset.currentHtmlUrl) {
                frame.src = modal.dataset.currentHtmlUrl;
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
