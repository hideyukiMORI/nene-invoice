// Brand icon generator (Issue #216 / logo spec §6).
// Renders the "monogram NN" mark (採用ロゴ 03) to favicon / app-icon assets with
// headless Chromium (the only rasteriser available). Regenerate with:
//   npm run gen:icons
// Outputs to public/. Source geometry follows the spec: viewBox 42×42, front N
// at x=11, back N at x=-2 (opacity 0.32 light / 0.40 on Pine), Noto Sans JP 800.
import { mkdir, writeFile } from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { chromium } from '@playwright/test'

const dirname = path.dirname(fileURLToPath(import.meta.url))
const PUBLIC = path.resolve(dirname, '../public')

/** The monogram mark as inline SVG markup. */
function mark({ fill, backOpacity }) {
  return (
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 42 42">` +
    `<text x="-2" y="31" font-family="'Noto Sans JP',sans-serif" font-weight="800" font-size="32" fill="${fill}" opacity="${backOpacity}">N</text>` +
    `<text x="11" y="31" font-family="'Noto Sans JP',sans-serif" font-weight="800" font-size="32" fill="${fill}">N</text>` +
    `</svg>`
  )
}

const browser = await chromium.launch()
const page = await browser.newPage({ deviceScaleFactor: 1 })

// Resolve the Pine brand colour (oklch) to sRGB hex via a canvas (reliable;
// getComputedStyle may keep oklch un-converted in modern Chromium).
const pine = await page.evaluate(() => {
  const c = document.createElement('canvas')
  c.width = c.height = 1
  const ctx = c.getContext('2d')
  ctx.fillStyle = 'oklch(41% 0.046 167)'
  ctx.fillRect(0, 0, 1, 1)
  const [r, g, b] = ctx.getImageData(0, 0, 1, 1).data
  return '#' + [r, g, b].map((n) => n.toString(16).padStart(2, '0')).join('')
})
console.log('Pine =', pine)

await mkdir(PUBLIC, { recursive: true })

/** Screenshot a square canvas with the mark centred. bg=null → transparent. */
async function render({ size, markPx, fill, backOpacity, bg, file }) {
  const svg = mark({ fill, backOpacity })
  await page.setViewportSize({ width: size, height: size })
  await page.setContent(
    `<!doctype html><html><body style="margin:0">` +
      `<div style="width:${size}px;height:${size}px;display:grid;place-items:center;${bg ? `background:${bg}` : ''}">` +
      `<div style="width:${markPx}px;height:${markPx}px">${svg}</div>` +
      `</div></body></html>`,
  )
  const buf = await page.screenshot({ omitBackground: !bg })
  await writeFile(path.join(PUBLIC, file), buf)
  return buf
}

// Transparent Pine marks (browser tab / Windows). Glyph fills most of the box.
const png16 = await render({
  size: 16,
  markPx: 16,
  fill: pine,
  backOpacity: 0.32,
  bg: null,
  file: 'favicon-16.png',
})
const png32 = await render({
  size: 32,
  markPx: 32,
  fill: pine,
  backOpacity: 0.32,
  bg: null,
  file: 'favicon-32.png',
})
const png48 = await render({
  size: 48,
  markPx: 48,
  fill: pine,
  backOpacity: 0.32,
  bg: null,
  file: 'favicon-48.png',
})

// Apple touch icon: Pine field + white mark, comfortable padding (iOS rounds it).
await render({
  size: 180,
  markPx: 116,
  fill: '#ffffff',
  backOpacity: 0.42,
  bg: pine,
  file: 'apple-touch-icon.png',
})

// Maskable PWA icons: white mark on full-bleed Pine, mark within the safe zone.
await render({
  size: 192,
  markPx: 108,
  fill: '#ffffff',
  backOpacity: 0.42,
  bg: pine,
  file: 'icon-192.png',
})
await render({
  size: 512,
  markPx: 288,
  fill: '#ffffff',
  backOpacity: 0.42,
  bg: pine,
  file: 'icon-512.png',
})

// favicon.svg (modern browsers) + mark.svg (source, currentColor).
await writeFile(path.join(PUBLIC, 'favicon.svg'), mark({ fill: pine, backOpacity: 0.32 }) + '\n')
await writeFile(
  path.join(PUBLIC, 'mark.svg'),
  mark({ fill: 'currentColor', backOpacity: 0.32 }) + '\n',
)

// favicon.ico = a container of the 16/32/48 PNGs (PNG-in-ICO, supported broadly).
function buildIco(images) {
  const header = Buffer.alloc(6)
  header.writeUInt16LE(0, 0) // reserved
  header.writeUInt16LE(1, 2) // type: icon
  header.writeUInt16LE(images.length, 4)
  const dir = Buffer.alloc(16 * images.length)
  let offset = 6 + dir.length
  images.forEach((img, i) => {
    const e = i * 16
    dir.writeUInt8(img.size >= 256 ? 0 : img.size, e + 0)
    dir.writeUInt8(img.size >= 256 ? 0 : img.size, e + 1)
    dir.writeUInt8(0, e + 2) // palette
    dir.writeUInt8(0, e + 3) // reserved
    dir.writeUInt16LE(1, e + 4) // planes
    dir.writeUInt16LE(32, e + 6) // bpp
    dir.writeUInt32LE(img.buf.length, e + 8)
    dir.writeUInt32LE(offset, e + 12)
    offset += img.buf.length
  })
  return Buffer.concat([header, dir, ...images.map((i) => i.buf)])
}
await writeFile(
  path.join(PUBLIC, 'favicon.ico'),
  buildIco([
    { size: 16, buf: png16 },
    { size: 32, buf: png32 },
    { size: 48, buf: png48 },
  ]),
)

await browser.close()
console.log('Generated icons in', PUBLIC)
