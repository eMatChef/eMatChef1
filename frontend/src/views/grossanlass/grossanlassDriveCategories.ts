export type GaDriveProofKind = 'none' | 'in_person' | 'document'

export type GaDriveCategoryCode =
  | 'b' | 'be' | 'c' | 'c1' | 'c1e' | 'ce' | 'd' | 'd1' | 'd1e' | 'de' | 'f' | 'g'
  | 'r1' | 'r2' | 'r3' | 'r4'
  | 's1' | 's2' | 's3'
  | 'crane_a' | 'crane_b' | 'crane_c'

export type GaDriveCategoryGroupId = 'license' | 'forklift_r' | 'forklift_s' | 'crane'

export const GA_DRIVE_CATEGORY_GROUPS: Array<{
  id: GaDriveCategoryGroupId
  codes: GaDriveCategoryCode[]
}> = [
  { id: 'license', codes: ['b', 'be', 'c', 'c1', 'c1e', 'ce', 'd', 'd1', 'd1e', 'de', 'f', 'g'] },
  { id: 'forklift_r', codes: ['r1', 'r2', 'r3', 'r4'] },
  { id: 'forklift_s', codes: ['s1', 's2', 's3'] },
  { id: 'crane', codes: ['crane_a', 'crane_b', 'crane_c'] },
]

export const GA_DRIVE_EXTRA_REGULATION: GaDriveCategoryCode[] = ['crane_a', 'crane_b']

export function driveHasExtraRegulation(codes: string[]): boolean {
  return codes.some((code) => GA_DRIVE_EXTRA_REGULATION.includes(code as GaDriveCategoryCode))
}

export function driveClassLabelKey(code: string): string {
  return `grossanlass.chain.drive.classes.${code}`
}
