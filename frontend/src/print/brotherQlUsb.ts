const BROTHER_VID = 0x04f9

export type BrotherQlStatus = {
  ready: boolean
  mediaWidthMm: number | null
  mediaType: string
  raw: Uint8Array
}

function toHex(n: number, width = 4): string {
  return n.toString(16).padStart(width, '0')
}

export function webUsbSupported(): boolean {
  return typeof navigator !== 'undefined' && !!navigator.usb
}

export async function requestBrotherQlDevice(): Promise<USBDevice> {
  const usb = navigator.usb
  if (!usb) {
    throw new Error('WebUSB wird von diesem Browser nicht unterstützt (Chrome/Edge, HTTPS).')
  }
  return usb.requestDevice({
    filters: [{ vendorId: BROTHER_VID }],
  })
}

async function claim(device: USBDevice): Promise<{ out: USBEndpoint; inn: USBEndpoint }> {
  if (!device.opened) await device.open()
  if (device.configuration == null) await device.selectConfiguration(1)
  const iface = device.configuration?.interfaces.find((item) => item.claimed === false) || device.configuration?.interfaces[0]
  if (!iface) throw new Error('Keine USB-Schnittstelle am QL')
  await device.claimInterface(iface.interfaceNumber)
  const alt = iface.alternate
  const out = alt.endpoints.find((e) => e.direction === 'out')
  const inn = alt.endpoints.find((e) => e.direction === 'in')
  if (!out || !inn) throw new Error('QL USB-Endpunkte fehlen')
  return { out, inn }
}

export async function readBrotherQlStatus(device: USBDevice): Promise<BrotherQlStatus> {
  const { out, inn } = await claim(device)
  await device.transferOut(out.endpointNumber, new Uint8Array([0x1b, 0x69, 0x53]))
  const result = await device.transferIn(inn.endpointNumber, 32)
  const view = result.data
  const raw = new Uint8Array(view?.byteLength || 0)
  if (view) {
    for (let i = 0; i < view.byteLength; i += 1) raw[i] = view.getUint8(i)
  }
  const widthByte = raw.length > 10 ? raw[10] : 0
  const typeByte = raw.length > 11 ? raw[11] : 0
  const mediaType = typeByte === 0x0a ? 'endlos' : typeByte === 0x0b ? 'stanze' : `typ ${toHex(typeByte, 2)}`
  return {
    ready: raw.length >= 20,
    mediaWidthMm: widthByte > 0 ? widthByte : null,
    mediaType,
    raw,
  }
}

function bitsFromCanvas(canvas: HTMLCanvasElement): { widthDots: number; rows: Uint8Array[] } {
  const ctx = canvas.getContext('2d')
  if (!ctx) throw new Error('Canvas ohne Kontext')
  const { width, height } = canvas
  const image = ctx.getImageData(0, 0, width, height)
  const bytesPerRow = Math.ceil(width / 8)
  const rows: Uint8Array[] = []
  for (let y = 0; y < height; y += 1) {
    const row = new Uint8Array(bytesPerRow)
    for (let x = 0; x < width; x += 1) {
      const i = (y * width + x) * 4
      const lum = image.data[i] * 0.3 + image.data[i + 1] * 0.59 + image.data[i + 2] * 0.11
      if (lum < 160) {
        row[Math.floor(x / 8)] |= 0x80 >> (x % 8)
      }
    }
    rows.push(row)
  }
  return { widthDots: width, rows }
}

function le16(n: number): Uint8Array {
  return new Uint8Array([n & 0xff, (n >> 8) & 0xff])
}

export async function printCanvasToBrotherQl(device: USBDevice, canvas: HTMLCanvasElement): Promise<void> {
  const { out } = await claim(device)
  const { rows } = bitsFromCanvas(canvas)
  const chunks: number[] = []
  const push = (...bytes: number[]) => chunks.push(...bytes)
  push(0x1b, 0x40)
  push(0x1b, 0x69, 0x61, 0x01)
  for (const row of rows) {
    push(0x67, ...le16(row.length), ...row)
  }
  push(0x1a)
  await device.transferOut(out.endpointNumber, new Uint8Array(chunks))
}
