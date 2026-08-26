import type { PrintLayout } from '@/api/printLayouts'
import { faceInk, faceRadius, type PrintFace } from '@/print/printFace'
import type { LayoutSample } from '@/print/renderPrintLayout'

function roundRectPath(
  ctx: CanvasRenderingContext2D,
  x: number,
  y: number,
  w: number,
  h: number,
  r: number,
) {
  const radius = Math.max(0, Math.min(r, w / 2, h / 2))
  ctx.beginPath()
  if (radius <= 0) {
    ctx.rect(x, y, w, h)
    return
  }
  ctx.moveTo(x + radius, y)
  ctx.arcTo(x + w, y, x + w, y + h, radius)
  ctx.arcTo(x + w, y + h, x, y + h, radius)
  ctx.arcTo(x, y + h, x, y, radius)
  ctx.arcTo(x, y, x + w, y, radius)
  ctx.closePath()
}

function wrapLines(ctx: CanvasRenderingContext2D, text: string, maxWidth: number, maxLines: number): string[] {
  const words = text.split(/\s+/).filter(Boolean)
  const lines: string[] = []
  let current = ''
  for (const word of words) {
    const next = current ? `${current} ${word}` : word
    if (ctx.measureText(next).width <= maxWidth || !current) {
      current = next
      continue
    }
    lines.push(current)
    current = word
    if (lines.length === maxLines - 1) break
  }
  if (current && lines.length < maxLines) lines.push(current)
  return lines.length ? lines : ['']
}

async function loadQr(url: string): Promise<HTMLImageElement | null> {
  if (!url) return null
  const QRCode = (await import('qrcode')).default
  const dataUrl = await QRCode.toDataURL(url, { margin: 0, width: 512 })
  return new Promise((resolve) => {
    const img = new Image()
    img.onload = () => resolve(img)
    img.onerror = () => resolve(null)
    img.src = dataUrl
  })
}

function drawTextBlock(
  ctx: CanvasRenderingContext2D,
  text: string,
  x: number,
  y: number,
  maxWidth: number,
  fontSize: number,
  color: string,
  align: CanvasTextAlign,
  maxLines = 2,
) {
  ctx.fillStyle = color
  ctx.font = `${fontSize}px sans-serif`
  ctx.textAlign = align
  ctx.textBaseline = 'top'
  const lines = wrapLines(ctx, text, maxWidth, maxLines)
  lines.forEach((line, index) => {
    ctx.fillText(line, x, y + index * fontSize * 1.15, maxWidth)
  })
  return lines.length * fontSize * 1.15
}

export async function drawPrintFace(
  ctx: CanvasRenderingContext2D,
  width: number,
  height: number,
  options: {
    layout: PrintLayout
    item: LayoutSample
    face: PrintFace
  },
): Promise<void> {
  const { layout, item, face } = options
  const ink = faceInk(face)
  const radius = faceRadius(face, width, height)
  const pad = Math.max(4, Math.min(width, height) * 0.045)

  ctx.fillStyle = '#ffffff'
  ctx.fillRect(0, 0, width, height)
  roundRectPath(ctx, 1, 1, width - 2, height - 2, radius)
  ctx.fillStyle = face.design === 'badge' ? ink.fill : '#ffffff'
  ctx.fill()
  ctx.strokeStyle = ink.accent
  ctx.lineWidth = face.color ? Math.max(2, Math.min(width, height) * 0.012) : Math.max(1, Math.min(width, height) * 0.008)
  ctx.stroke()

  const qr = await loadQr(item.public_url)

  if (face.design === 'badge') {
    let y = pad * 1.2
    const cx = width / 2
    const inner = width - pad * 2
    if (item.event) {
      ctx.font = `700 ${Math.max(9, height * 0.045)}px sans-serif`
      ctx.fillStyle = ink.accent
      ctx.textAlign = 'center'
      ctx.textBaseline = 'top'
      ctx.fillText(`${item.event} · eMatChef`.toUpperCase(), cx, y, inner)
      y += height * 0.07
    }
    if (item.name) {
      y += drawTextBlock(ctx, item.name, cx, y, inner, Math.max(14, height * 0.09), ink.ink, 'center', 2)
    }
    if (item.place) {
      y += 2
      y += drawTextBlock(ctx, item.place, cx, y, inner, Math.max(10, height * 0.05), ink.muted, 'center', 2)
    }
    const bottomReserve = (item.public_code ? height * 0.07 : 0) + (item.drive ? height * 0.07 : 0) + pad
    const qrMax = Math.min(inner * 0.72, height - y - bottomReserve - pad)
    if (qr && qrMax > 12) {
      const size = qrMax
      ctx.drawImage(qr, (width - size) / 2, y, size, size)
      y += size + pad * 0.6
    } else {
      y += pad
    }
    if (item.public_code) {
      ctx.font = `${Math.max(9, height * 0.042)}px ui-monospace, monospace`
      ctx.fillStyle = ink.muted
      ctx.textAlign = 'center'
      ctx.textBaseline = 'top'
      ctx.fillText(item.public_code, cx, y, inner)
      y += height * 0.06
    }
    if (item.drive) {
      drawTextBlock(ctx, item.drive, cx, y, inner, Math.max(10, height * 0.048), ink.accent, 'center', 2)
    }
    return
  }

  for (const field of layout.fields) {
    const x = (field.x / 100) * width
    const y = (field.y / 100) * height
    const w = (field.w / 100) * width
    const h = (field.h / 100) * height
    if (field.type === 'qr') {
      if (qr) {
        const size = Math.min(w, h)
        ctx.drawImage(qr, x, y + (h - size) / 2, size, size)
      }
      continue
    }
    const text = field.key === 'public_code' ? item.public_code : item.label
    const lines = text.split(/\n/).map((line) => line.trim()).filter(Boolean)
    const fontSize = Math.max(10, Math.min(32, Math.round(h / Math.max(1, lines.length * 1.25)), Math.round(h * 0.32)))
    ctx.fillStyle = ink.ink
    ctx.font = `${fontSize}px sans-serif`
    ctx.textAlign = 'left'
    ctx.textBaseline = 'top'
    const use = lines.length ? lines : [' ']
    use.forEach((line, index) => {
      ctx.fillText(line, x + 2, y + 2 + index * fontSize * 1.15, Math.max(8, w - 4))
    })
  }
}
