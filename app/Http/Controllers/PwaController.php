<?php

namespace App\Http\Controllers;

use App\Models\InstitutionSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $settings = InstitutionSetting::current();

        return response()->json([
            'name' => $settings->institution_name,
            'short_name' => $settings->short_name,
            'description' => 'Plataforma PWA CeCap — SURÍ Proyecto Educativo, Costa Rica',
            'start_url' => '/panel',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#F8FAFC',
            'theme_color' => $settings->theme_color ?: '#0F4C4C',
            'lang' => 'es-CR',
            'categories' => ['education', 'productivity'],
            'icons' => [
                [
                    'src' => '/icons/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src' => '/icons/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ])->header('Content-Type', 'application/manifest+json; charset=utf-8');
    }

    public function serviceWorker(): Response
    {
        $script = <<<'JS'
const CACHE_NAME = 'cecap-suri-pwa-v3-1';
const STATIC_ASSETS = [
  '/manifest.webmanifest',
  '/images/logo-suri.png',
  '/icons/icon-192.png',
  '/icons/icon-512.png'
];

const TRANSACTIONAL_PREFIXES = [
  '/livewire',
  '/login',
  '/logout',
  '/registro',
  '/cambiar-contrasena',
  '/recuperar-contrasena',
  '/restablecer-contrasena',
  '/admisiones',
  '/finanzas',
  '/personal',
  '/direccion',
  '/seguimiento',
  '/graduacion',
  '/adjuntos',
  '/pdf',
  '/inscripcion/planilla'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS)).catch(() => {})
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') {
    return;
  }

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) {
    return;
  }

  // Bypass nativo para PDFs y descargas documentales
  const accept = req.headers.get('accept') || '';
  if (
    accept.includes('application/pdf') ||
    url.pathname.endsWith('.pdf') ||
    url.pathname.startsWith('/pdf/') ||
    url.pathname.includes('/planilla/') ||
    url.searchParams.get('vista') === 'pdf'
  ) {
    return;
  }

  // Exclusión estricta de rutas transaccionales y Livewire
  if (TRANSACTIONAL_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))) {
    return;
  }

  // Estrategia Network-First segura solo para recursos estáticos (/build, /icons, manifest)
  if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname === '/manifest.webmanifest') {
    event.respondWith(
      fetch(req)
        .then((res) => {
          if (res && res.status === 200) {
            const copy = res.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
          }
          return res;
        })
        .catch(() => caches.match(req))
    );
  }
});
JS;

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
