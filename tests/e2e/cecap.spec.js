import { test, expect } from '@playwright/test';

async function loginAsSuperAdmin(page) {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'superadmin@cecap.or.cr');
    await page.fill('input[name="password"]', 'Y4y4&K1k3');
    // Verify CSP-safe eye toggle shows and hides password
    await expect(page.locator('#password')).toHaveAttribute('type', 'password');
    await page.click('[data-password-toggle="password"]');
    await expect(page.locator('#password')).toHaveAttribute('type', 'text');
    await page.click('[data-password-toggle="password"]');
    await expect(page.locator('#password')).toHaveAttribute('type', 'password');
    await Promise.all([
        page.waitForURL('**/panel'),
        page.click('button[type="submit"]'),
    ]);
}

test.describe('Plataforma PWA CeCap SURÍ v3.0 E2E', () => {
    test('PWA manifest, service worker, /inscripcion gender gate & mobile ergonomics', async ({ page, request }, testInfo) => {
        const errors = [];
        page.on('pageerror', (e) => errors.push(e.message));

        // 1. Manifest & Service Worker
        const manifestResp = await request.get('/manifest.webmanifest');
        expect(manifestResp.status()).toBe(200);
        const manifest = await manifestResp.json();
        expect(manifest.display).toBe('standalone');
        expect(manifest.lang).toBe('es-CR');

        const swResp = await request.get('/sw.js');
        expect(swResp.status()).toBe(200);
        const swBody = await swResp.text();
        expect(swBody).toContain('application/pdf');
        expect(swBody).toContain('/livewire');

        // 2. Public enrollment /inscripcion
        await page.goto('/inscripcion');
        await expect(page.getByText('Inscripción en Línea Provisional')).toBeVisible();

        // Check >= 16px input & >= 44px touch target on mobile
        if (testInfo.project.name === 'mobile') {
            const emailInput = page.locator('#pub_email');
            const metrics = await emailInput.evaluate((el) => {
                const cs = window.getComputedStyle(el);
                const rect = el.getBoundingClientRect();
                return { fontSize: parseFloat(cs.fontSize), height: rect.height };
            });
            expect(metrics.fontSize).toBeGreaterThanOrEqual(16);
            expect(metrics.height).toBeGreaterThanOrEqual(44);
        }

        // Gender policy gate for non-female
        await page.selectOption('#pub_gender', 'masculino');
        await expect(page.getByRole('alert')).toBeVisible();
        await expect(page.getByRole('alert')).toContainText('parámetros');

        // Dual recovery & QR verification public pages
        await page.goto('/recuperar-contrasena');
        await expect(page.getByText('Recuperación Dual de Contraseña')).toBeVisible();
        await expect(page.getByText('Validación de Datos Personales')).toBeVisible();

        await page.goto('/verificar/00000000-0000-0000-0000-000000000000');
        await expect(page.getByText('CERTIFICADO NO ENCONTRADO')).toBeVisible();

        expect(errors).toEqual([]);
    });

    test('Authenticated navigation across all 11 modules, collapsible sidebar & mobile bottom bar', async ({ page }, testInfo) => {
        const errors = [];
        page.on('pageerror', (e) => errors.push(e.message));

        await loginAsSuperAdmin(page);
        await expect(page.getByRole('heading', { name: /Bienvenida, Super Administrador CeCap/ })).toBeVisible();

        if (testInfo.project.name === 'desktop') {
            const sidebar = page.locator('#cecap-sidebar');
            await expect(sidebar).toBeVisible();
            const widthExpanded = await sidebar.evaluate((el) => el.getBoundingClientRect().width);
            expect(widthExpanded).toBeGreaterThanOrEqual(240);

            // Collapse sidebar cleanly (w-20, hidden text labels without 2-letter clipping)
            await page.click('[data-sidebar-toggle]');
            await expect(sidebar).toHaveAttribute('data-sidebar-collapsed', 'true');
            await expect.poll(async () => sidebar.evaluate((el) => el.getBoundingClientRect().width)).toBeLessThanOrEqual(84);

            // Compact PWA button still visible when collapsed
            await expect(sidebar.locator('[data-pwa-install-btn]')).toBeVisible();

            // Expand back
            await page.click('[data-sidebar-toggle]');
            await expect(sidebar).toHaveAttribute('data-sidebar-collapsed', 'false');
        }

        if (testInfo.project.name === 'mobile') {
            const bottomNav = page.locator('nav[aria-label="Accesos rápidos móvil"]');
            await expect(bottomNav).toBeVisible();
        }

        const routes = [
            '/academico',
            '/admisiones',
            '/seguimiento',
            '/finanzas',
            '/graduacion',
            '/personal',
            '/actividades',
            '/documentos',
            '/consultas',
            '/direccion',
        ];

        for (const r of routes) {
            const resp = await page.goto(r);
            expect(resp?.status(), `Route ${r} status`).toBe(200);
        }

        // Verify Tab 5 institutional settings in /direccion
        await page.getByRole('button', { name: '5. Configuración e Inscripción' }).click();
        await expect(page.getByText('Tab 5 — Configuración Institucional')).toBeVisible();
        await expect(page.getByText('system.code.manage')).toBeVisible();

        // Verify Tab 2 8-role catalog in /direccion
        await page.getByRole('button', { name: /2\. Usuarios y 8 Roles/ }).click();
        await expect(page.getByText('Catálogo Oficial de los 8 Roles de Gobernanza')).toBeVisible();

        expect(errors).toEqual([]);
    });
});
