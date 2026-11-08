// Pengendali Chrome headless minimal lewat Chrome DevTools Protocol (tanpa paket npm).
// Dipakai untuk mengambil screenshot pengujian. Node >= 22 (WebSocket & fetch bawaan).
import { spawn } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

export async function launch({ chrome, port = 9333, profile, width = 1366, height = 900 }) {
  mkdirSync(profile, { recursive: true });
  const proc = spawn(chrome, [
    '--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`,
    '--no-first-run', '--no-default-browser-check', '--disable-gpu', '--hide-scrollbars',
    `--window-size=${width},${height}`, '--lang=id-ID', 'about:blank',
  ], { stdio: 'ignore' });

  let target;
  for (let i = 0; i < 50 && !target; i++) {
    try {
      const list = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
      target = list.find((t) => t.type === 'page');
    } catch { /* chrome belum siap */ }
    if (!target) await sleep(200);
  }
  if (!target) throw new Error('Chrome tidak dapat dihubungi');

  const ws = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((res, rej) => { ws.onopen = res; ws.onerror = rej; });

  let seq = 0;
  const pending = new Map();
  const listeners = [];
  ws.onmessage = (ev) => {
    const msg = JSON.parse(ev.data);
    if (msg.id && pending.has(msg.id)) {
      const { resolve, reject } = pending.get(msg.id);
      pending.delete(msg.id);
      msg.error ? reject(new Error(msg.error.message)) : resolve(msg.result);
    } else if (msg.method) {
      listeners.forEach((l) => l(msg));
    }
  };
  const send = (method, params = {}) => new Promise((resolve, reject) => {
    const id = ++seq;
    pending.set(id, { resolve, reject });
    ws.send(JSON.stringify({ id, method, params }));
  });
  const waitEvent = (name, timeout = 20000) => new Promise((resolve) => {
    const t = setTimeout(resolve, timeout);
    listeners.push((m) => { if (m.method === name) { clearTimeout(t); resolve(m); } });
  });

  await send('Page.enable');
  await send('Runtime.enable');
  await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false });

  const page = {
    send,
    sleep,
    async goto(url, settle = 1500) {
      const loaded = waitEvent('Page.loadEventFired');
      await send('Page.navigate', { url });
      await loaded;
      await sleep(settle);
    },
    async eval(expression) {
      const r = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
      if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text);
      return r.result.value;
    },
    async waitFor(selector, timeout = 15000) {
      const start = Date.now();
      while (Date.now() - start < timeout) {
        if (await page.eval(`!!document.querySelector(${JSON.stringify(selector)})`)) return true;
        await sleep(250);
      }
      throw new Error(`Elemen tidak muncul: ${selector}`);
    },
    // Ketik ke input agar Vue/vee-validate menerima perubahan.
    async type(selector, value) {
      await page.eval(`(() => {
        const el = document.querySelector(${JSON.stringify(selector)});
        el.focus();
        const proto = el.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
        Object.getOwnPropertyDescriptor(proto, 'value').set.call(el, ${JSON.stringify(String(value))});
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        el.blur();
      })()`);
    },
    async click(selector) {
      await page.eval(`document.querySelector(${JSON.stringify(selector)}).click()`);
    },
    async screenshot(file, { full = false } = {}) {
      let clip;
      if (full) {
        const m = await send('Page.getLayoutMetrics');
        clip = { x: 0, y: 0, width, height: Math.min(Math.ceil(m.cssContentSize.height), 4000), scale: 1 };
      }
      const { data } = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: !!full, ...(clip ? { clip } : {}) });
      writeFileSync(file, Buffer.from(data, 'base64'));
    },
    async close() {
      try { ws.close(); } catch {}
      proc.kill();
    },
  };

  return page;
}
