import assert from 'node:assert/strict'
import test from 'node:test'
import {
  ciOkConclusion,
  confirmStable,
  evaluatePromotion,
  promotionPushGitArgs,
  redact,
} from './release-fast-forward.mjs'

const SHA_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
const SHA_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'

function okInput(overrides = {}) {
  return {
    commentBody: '/fast-forward',
    isPullRequest: true,
    state: 'open',
    draft: false,
    permission: 'maintain',
    headRepo: 'eMatChef/eMatChef1',
    baseRepo: 'eMatChef/eMatChef1',
    expectedRepo: 'eMatChef/eMatChef1',
    headRef: 'develop',
    baseRef: 'staging',
    headSha: SHA_B,
    baseSha: SHA_A,
    ciOk: 'success',
    baseIsAncestor: true,
    ...overrides,
  }
}

test('develop → staging wird zugelassen', () => {
  const result = evaluatePromotion(okInput())
  assert.equal(result.ok, true)
  assert.equal(result.refspec, `${SHA_B}:refs/heads/staging`)
})

test('staging → prod wird zugelassen', () => {
  const result = evaluatePromotion(okInput({
    headRef: 'staging',
    baseRef: 'prod',
    permission: 'admin',
  }))
  assert.equal(result.ok, true)
  assert.equal(result.refspec, `${SHA_B}:refs/heads/prod`)
})

test('feature → staging wird abgelehnt', () => {
  const result = evaluatePromotion(okInput({ headRef: 'feature/login' }))
  assert.equal(result.ok, false)
  assert.equal(result.code, 'pair')
})

test('develop → prod wird abgelehnt', () => {
  const result = evaluatePromotion(okInput({ baseRef: 'prod' }))
  assert.equal(result.ok, false)
  assert.equal(result.code, 'pair')
})

test('staging → develop wird abgelehnt', () => {
  const result = evaluatePromotion(okInput({ headRef: 'staging', baseRef: 'develop' }))
  assert.equal(result.ok, false)
  assert.equal(result.code, 'pair')
})

test('rotes CI ok wird abgelehnt', () => {
  const result = evaluatePromotion(okInput({ ciOk: 'failure' }))
  assert.equal(result.ok, false)
  assert.equal(result.code, 'ci')
})

test('fehlendes CI ok wird abgelehnt', () => {
  assert.equal(ciOkConclusion([]), 'missing')
  assert.equal(evaluatePromotion(okInput({ ciOk: 'missing' })).code, 'ci')
})

test('nicht-ancestor wird abgelehnt', () => {
  const result = evaluatePromotion(okInput({ baseIsAncestor: false }))
  assert.equal(result.ok, false)
  assert.equal(result.code, 'ancestor')
})

test('Draft wird abgelehnt', () => {
  const result = evaluatePromotion(okInput({ draft: true }))
  assert.equal(result.ok, false)
  assert.equal(result.code, 'draft')
})

test('unberechtigter Benutzer wird abgelehnt', () => {
  for (const permission of ['read', 'triage', 'write', '']) {
    const result = evaluatePromotion(okInput({ permission }))
    assert.equal(result.ok, false)
    assert.equal(result.code, 'permission')
  }
})

test('geschlossener PR wird abgelehnt', () => {
  const result = evaluatePromotion(okInput({ state: 'closed' }))
  assert.equal(result.ok, false)
  assert.equal(result.code, 'closed')
})

test('fremdes Repository wird abgelehnt', () => {
  const result = evaluatePromotion(okInput({ headRepo: 'other/eMatChef1' }))
  assert.equal(result.ok, false)
  assert.equal(result.code, 'repository')
})

test('Push-Argumente enthalten kein force und kein Token', () => {
  const secret = 'ghs_example_token_value'
  const args = promotionPushGitArgs('eMatChef/eMatChef1', `${SHA_B}:refs/heads/staging`)
  assert.equal(args.includes('--force'), false)
  assert.equal(args.includes('--force-with-lease'), false)
  assert.equal(args.includes('origin'), false)
  assert.equal(args.at(-1), `${SHA_B}:refs/heads/staging`)
  assert.equal(args.some((arg) => arg.includes(secret)), false)
  assert.equal(redact(`denied ${secret}`, secret), 'denied [redacted]')
})

test('Head-Wechsel zwischen Prüfung und Push bricht ab', () => {
  const result = confirmStable({
    headSha: SHA_B,
    baseSha: SHA_A,
    headShaAgain: 'cccccccccccccccccccccccccccccccccccccccc',
    baseShaAgain: SHA_A,
    remoteHead: SHA_B,
    remoteBase: SHA_A,
  })
  assert.equal(result.ok, false)
  assert.equal(result.code, 'moved')
})

test('unveränderte SHAs bleiben freigegeben', () => {
  const result = confirmStable({
    headSha: SHA_B,
    baseSha: SHA_A,
    headShaAgain: SHA_B,
    baseShaAgain: SHA_A,
    remoteHead: SHA_B,
    remoteBase: SHA_A,
  })
  assert.equal(result.ok, true)
})

test('neuester CI-ok-Lauf zählt', () => {
  const conclusion = ciOkConclusion([
    { name: 'CI ok', status: 'completed', conclusion: 'failure', started_at: '2026-10-01T10:00:00Z' },
    { name: 'CI ok', status: 'completed', conclusion: 'success', started_at: '2026-10-01T11:00:00Z' },
    { name: 'Frontend', status: 'completed', conclusion: 'failure', started_at: '2026-10-01T12:00:00Z' },
  ])
  assert.equal(conclusion, 'success')
})
