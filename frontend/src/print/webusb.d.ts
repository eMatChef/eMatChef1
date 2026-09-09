/** Minimal WebUSB types for Brother QL from Chrome/Edge. */
interface USBEndpoint {
  endpointNumber: number
  direction: 'in' | 'out'
}

interface USBAlternateInterface {
  endpoints: USBEndpoint[]
}

interface USBInterface {
  interfaceNumber: number
  claimed: boolean
  alternate: USBAlternateInterface
}

interface USBConfiguration {
  interfaces: USBInterface[]
}

interface USBOutTransferResult {
  status: string
  bytesWritten?: number
}

interface USBInTransferResult {
  data?: DataView
  status: string
}

interface USBDevice {
  opened: boolean
  configuration: USBConfiguration | null
  open(): Promise<void>
  selectConfiguration(configurationValue: number): Promise<void>
  claimInterface(interfaceNumber: number): Promise<void>
  transferOut(endpointNumber: number, data: BufferSource): Promise<USBOutTransferResult>
  transferIn(endpointNumber: number, length: number): Promise<USBInTransferResult>
}

interface USBDeviceFilter {
  vendorId?: number
  productId?: number
}

interface USB {
  requestDevice(options: { filters: USBDeviceFilter[] }): Promise<USBDevice>
}

interface Navigator {
  usb?: USB
}
