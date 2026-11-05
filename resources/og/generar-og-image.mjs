/**
 * Genera la imagen Open Graph general de la tienda: public/icons/og-image.jpg (1200x630).
 *
 *   node resources/og/generar-og-image.mjs
 *
 * Renderiza resources/og/og-image.html con Puppeteer (ya es dependencia del proyecto por
 * Browsershot). Todo lo importante (logo, mascota, textos) vive en la zona segura central
 * (~660x560) para que el recorte cuadrado de WhatsApp/Telegram la muestre completa.
 */
import { readFileSync, statSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import puppeteer from 'puppeteer';

const aqui = dirname(fileURLToPath(import.meta.url));
const raiz = resolve(aqui, '../..');
const salida = resolve(raiz, 'public/icons/og-image.jpg');

const dataUri = (ruta, tipo) =>
    `data:${tipo};base64,${readFileSync(resolve(raiz, ruta)).toString('base64')}`;

const html = readFileSync(resolve(aqui, 'og-image.html'), 'utf8')
    .replaceAll('{{LOGO}}', dataUri('resources/js/img/logo.png', 'image/png'))
    .replaceAll(
        '{{MASCOTA}}',
        dataUri('resources/js/img/mascota-mr-lana.webp', 'image/webp'),
    )
    .replace(/\{\{FUENTE:([\w-]+\.ttf)\}\}/g, (_, archivo) =>
        dataUri(`resources/fonts/${archivo}`, 'font/ttf'),
    );

const navegador = await puppeteer.launch({
    headless: 'shell',
    executablePath:
        process.env.CHROME_PATH ||
        process.env.BROWSERSHOT_CHROME_PATH ||
        undefined,
    args: ['--no-sandbox'],
});
const pagina = await navegador.newPage();
await pagina.setViewport({ width: 1200, height: 630, deviceScaleFactor: 1 });
await pagina.setContent(html, { waitUntil: 'load' });
await pagina.evaluate(() => document.fonts.ready);
await pagina.screenshot({
    path: salida,
    type: 'jpeg',
    quality: 86,
    clip: { x: 0, y: 0, width: 1200, height: 630 },
});
await navegador.close();

console.log(`OK ${salida} (${Math.round(statSync(salida).size / 1024)} KB)`);
