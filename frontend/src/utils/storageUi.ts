export function getSlotPrefix(rackName: string): string {
  const trimmed = rackName.trim()
  const lastWord = trimmed.split(/\s+/).pop() || ''
  if (/^[A-Za-z]$/.test(lastWord)) return lastWord.toUpperCase()
  if (/^[A-Za-z]\d*$/.test(lastWord)) return lastWord.replace(/\d+$/, '').toUpperCase() || lastWord[0].toUpperCase()
  return (trimmed[0] || 'F').toUpperCase()
}

export function getRackSuggestions(existingNames: string[]): string[] {
  const regalLetter = existingNames.find((n) => /^Regal\s+[A-Z]$/i.test(n))
  const singleLetter = existingNames.find((n) => /^[A-Z]$/i.test(n))
  const regalNum = existingNames.find((n) => /^Regal\s+\d+$/i.test(n))
  const plainNum = existingNames.find((n) => /^\d+$/.test(n))

  if (regalLetter || singleLetter) {
    const letters = existingNames.map((n) => {
      const m = n.match(/^Regal\s+([A-Z])$/i) || n.match(/^([A-Z])$/i)
      return m ? m[1].toUpperCase() : null
    }).filter(Boolean) as string[]
    const max = letters.reduce((a, l) => Math.max(a, l.charCodeAt(0)), 64)
    const prefix = regalLetter ? 'Regal ' : ''
    return [1, 2, 3]
      .map((i) => String.fromCharCode(max + i))
      .filter((c) => c <= 'Z')
      .map((c) => prefix + c)
  }

  if (regalNum || plainNum) {
    const nums = existingNames.map((n) => {
      const m = n.match(/(\d+)$/)
      return m ? parseInt(m[1], 10) : 0
    })
    const max = Math.max(0, ...nums)
    const pad = existingNames.some((n) => /0\d$/.test(n) || /^\d{2,}$/.test(n))
    const fmt = (x: number) => (pad ? String(x).padStart(2, '0') : String(x))
    const prefix = regalNum ? 'Regal ' : ''
    return [max + 1, max + 2, max + 3].map((n) => prefix + fmt(n))
  }

  return ['Regal A', 'Regal B', 'Regal C']
}

export function getSlotSuggestions(rackName: string, existingSlotNames: string[]): string[] {
  const prefix = getSlotPrefix(rackName)
  const maxNum = existingSlotNames
    .map((name) => {
      const m = name.match(new RegExp(`^${escapeRegex(prefix)}(\\d+)$`, 'i'))
      return m ? parseInt(m[1], 10) : 0
    })
    .reduce((a, b) => Math.max(a, b), 0)
  const next = maxNum + 1
  return [next, next + 1, next + 2].map((n) => `${prefix}${n}`)
}

export function generateRackNames(baseInput: string, count: number, existingNames: string[]): string[] {
  const baseTrimmed = baseInput.trim()
  if (!baseTrimmed) return []

  const letterBaseMatch = baseTrimmed.match(/^(.*?)([A-Za-z])$/)
  if (letterBaseMatch) {
    const letterPrefix = letterBaseMatch[1]
    const baseLetterCode = letterBaseMatch[2].toUpperCase().charCodeAt(0)
    const maxExistingLetterCode = existingNames
      .map((n) => {
        const m = n.match(new RegExp(`^${escapeRegex(letterPrefix)}([A-Za-z])$`, 'i'))
        return m ? m[1].toUpperCase().charCodeAt(0) : 0
      })
      .reduce((a, b) => Math.max(a, b), 0)

    const startLetterCode = Math.max(baseLetterCode, maxExistingLetterCode + 1)
    if (startLetterCode <= 90) {
      const result: string[] = []
      for (let i = 0; i < count; i++) {
        const code = startLetterCode + i
        if (code > 90) break
        result.push(`${letterPrefix}${String.fromCharCode(code)}`)
      }
      if (result.length > 0) return result
    }
  }

  return generateSequentialNames(baseInput, count, existingNames)
}

export function generateSequentialNames(baseInput: string, count: number, existingNames: string[]): string[] {
  const prefix = normalizeGeneratedPrefix(baseInput)
  if (!prefix) return []

  const maxNum = existingNames
    .map((name) => {
      const m = name.match(new RegExp(`^${escapeRegex(prefix)}(\\d+)$`, 'i'))
      return m ? parseInt(m[1], 10) : 0
    })
    .reduce((a, b) => Math.max(a, b), 0)

  const result: string[] = []
  for (let i = 1; i <= count; i++) {
    result.push(`${prefix}${maxNum + i}`)
  }
  return result
}

function normalizeGeneratedPrefix(baseInput: string): string {
  const baseTrimmed = baseInput.trim()
  if (!baseTrimmed) return ''
  if (/\s$/.test(baseInput)) return baseInput
  if (/^[A-Za-z]$/.test(baseTrimmed)) return baseTrimmed
  return `${baseTrimmed} `
}

function escapeRegex(s: string): string {
  return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

