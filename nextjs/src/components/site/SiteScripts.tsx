'use client';
/**
 * Loads the site's own scripts (js/main.js etc., the same files the PHP pages
 * used) AFTER React has hydrated the page.
 *
 * Why not plain <script defer>: those scripts change the page (count-up
 * numbers, the before/after slider, carousels). If they run before React
 * hydrates, React sees different HTML from what the server sent and re-renders
 * the page, wiping those changes. Loading them after hydration avoids that.
 *
 * The scripts start with document.addEventListener('DOMContentLoaded', ...)
 * (and a few use window 'load'). Those events have already fired by now, so a
 * small shim runs such listeners straight away instead, which is exactly what
 * would have happened on the PHP page.
 */
import { useEffect } from 'react';

declare global {
  interface Window {
    __wefScriptsStarted?: boolean;
  }
}

function installReadyShim() {
  const docAdd = document.addEventListener.bind(document);
  document.addEventListener = function (type: string, listener: any, options?: any) {
    if (type === 'DOMContentLoaded' && document.readyState !== 'loading') {
      queueMicrotask(() => (typeof listener === 'function' ? listener.call(document, new Event(type)) : listener.handleEvent(new Event(type))));
      return;
    }
    return docAdd(type, listener, options);
  } as typeof document.addEventListener;

  const winAdd = window.addEventListener.bind(window);
  window.addEventListener = function (type: string, listener: any, options?: any) {
    if (type === 'load' && document.readyState === 'complete') {
      queueMicrotask(() => (typeof listener === 'function' ? listener.call(window, new Event(type)) : listener.handleEvent(new Event(type))));
      return;
    }
    return winAdd(type, listener, options);
  } as typeof window.addEventListener;
}

function loadInOrder(srcs: string[], nonce?: string) {
  return srcs.reduce<Promise<void>>(
    (chain, src) =>
      chain.then(
        () =>
          new Promise<void>((resolve) => {
            const s = document.createElement('script');
            s.src = src;
            s.async = false;
            if (nonce) s.nonce = nonce;
            s.onload = () => resolve();
            s.onerror = () => resolve();
            document.body.appendChild(s);
          }),
      ),
    Promise.resolve(),
  );
}

export default function SiteScripts({ srcs, nonce }: { srcs: string[]; nonce?: string }) {
  useEffect(() => {
    if (window.__wefScriptsStarted) return;
    window.__wefScriptsStarted = true;
    installReadyShim();
    void loadInOrder(srcs, nonce);
  }, [srcs, nonce]);
  return null;
}
