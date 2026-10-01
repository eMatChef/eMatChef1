/**
 * Entscheidung und Fast-Forward-Push für Release-PRs.
 * Abschluss nur develop → staging und staging → prod.
 */
import { execFileSync } from 'node:child_process'
import { readFileSync } from 'node:fs'
import { pathToFileURL } from 'node:url'

const MESSAGES = {
  comment: 'Kommentar muss exakt /fast-forward sein.',
  'not-pr': 'Der Kommentar gehört zu keinem Pull Request.',
  closed: 'Der Pull Request ist nicht offen.',
  draft: 'Draft-Pull-Requests werden nicht promoted.',
  permission: 'Nur Maintain oder Admin dürfen /fast-forward auslösen.',
  repository: 'Head und Base müssen in diesem Repository liegen.',
  pair: 'Erlaubt sind nur develop → staging und staging → prod.',
  ci: 'Der Check CI ok am Head-SHA ist nicht erfolgreich.',
  'not-forward': 'Head und Base sind identisch. Es gibt nichts vorwärts zu bewegen.',
  ancestor: 'Der Zielbranch ist kein Vorfahre des Head-Commits oder die History ist divergent.',
  moved: 'Head oder Base haben sich zwischen Prüfung und Push geändert. Abbruch.',
}

export function ciOkConclusion(runs) {
  const relevant = (runs || []).filter((run) => run.name === 'CI ok')
  if (relevant.length === 0) return 'missing'
  relevant.sort((a, b) => String(b.started_at || '').localeCompare(String(a.started_at || '')))
  const latest = relevant[0]
  if (latest.status !== 'completed') return latest.status || 'incomplete'
  return latest.conclusion || 'missing'
}

export function evaluatePromotion(input) {
  if (input.commentBody !== '/fast-forward') return deny('comment')
  if (!input.isPullRequest) return deny('not-pr')
  if (input.state !== 'open') return deny('closed')
  if (input.draft) return deny('draft')
  if (input.permission !== 'admin' && input.permission !== 'maintain') return deny('permission')
  if (!input.headRepo || !input.baseRepo || input.headRepo !== input.baseRepo || input.headRepo !== input.expectedRepo) {
    return deny('repository')
  }
  const allowed = (input.baseRef === 'staging' && input.headRef === 'develop')
    || (input.baseRef === 'prod' && input.headRef === 'staging')
  if (!allowed) return deny('pair')
  if (input.ciOk !== 'success') return deny('ci')
  if (!input.baseSha || !input.headSha || input.baseSha === input.headSha) return deny('not-forward')
  if (!input.baseIsAncestor) return deny('ancestor')
  return {
    ok: true,
    code: 'promote',
    refspec: `${input.headSha}:refs/heads/${input.baseRef}`,
  }
}

export function confirmStable(snapshot) {
  if (snapshot.headSha !== snapshot.headShaAgain) return { ok: false, code: 'moved' }
  if (snapshot.baseSha !== snapshot.baseShaAgain) return { ok: false, code: 'moved' }
  if (snapshot.remoteHead !== snapshot.headSha) return { ok: false, code: 'moved' }
  if (snapshot.remoteBase !== snapshot.baseSha) return { ok: false, code: 'moved' }
  return { ok: true, code: 'stable' }
}

export function pushArguments(refspec) {
  if (!/^[0-9a-f]{40}:refs\/heads\/(staging|prod)$/.test(refspec)) {
    throw new Error('Ungültige Fast-Forward-Refspec.')
  }
  return ['push', 'origin', refspec]
}

function deny(code) {
  return { ok: false, code, message: MESSAGES[code] }
}

function gh(args) {
  return execFileSync('gh', args, { encoding: 'utf8' })
}

function git(args) {
  return execFileSync('git', args, { encoding: 'utf8' })
}

function isAncestor(baseSha, headSha) {
  try {
    execFileSync('git', ['merge-base', '--is-ancestor', baseSha, headSha], { stdio: 'ignore' })
    return true
  } catch {
    return false
  }
}

function lsRemote(ref) {
  const output = git(['ls-remote', 'origin', ref]).trim()
  if (!output) return ''
  return output.split(/\s+/)[0]
}

function commentOnPullRequest(repo, number, body) {
  gh(['api', `repos/${repo}/issues/${number}/comments`, '-f', `body=${body}`])
}

function fail(repo, number, message) {
  const text = `Fast-Forward abgebrochen. ${message}`
  console.error(text)
  if (repo && number) commentOnPullRequest(repo, number, text)
  process.exit(1)
}

function main() {
  const eventPath = process.env.GITHUB_EVENT_PATH
  const repo = process.env.GITHUB_REPOSITORY
  if (!eventPath || !repo) {
    console.error('GITHUB_EVENT_PATH oder GITHUB_REPOSITORY fehlt.')
    process.exit(1)
  }
  const event = JSON.parse(readFileSync(eventPath, 'utf8'))
  const number = event.issue?.number
  const login = event.comment?.user?.login
  if (!event.issue?.pull_request || !number || !login) {
    console.error(MESSAGES['not-pr'])
    process.exit(1)
  }

  const pr = JSON.parse(gh(['api', `repos/${repo}/pulls/${number}`]))
  const permissionBody = JSON.parse(gh(['api', `repos/${repo}/collaborators/${login}/permission`]))
  const headSha = pr.head?.sha || ''
  const baseSha = pr.base?.sha || ''
  const headRef = pr.head?.ref || ''
  const baseRef = pr.base?.ref || ''

  for (const ref of [headRef, baseRef]) {
    if (!/^[A-Za-z0-9._/-]+$/.test(ref) || ref.includes('..') || ref.startsWith('-') || ref.startsWith('/')) {
      fail(repo, number, MESSAGES.pair)
    }
  }

  git([
    'fetch',
    '--no-tags',
    'origin',
    `refs/heads/${headRef}:refs/remotes/origin/${headRef}`,
    `refs/heads/${baseRef}:refs/remotes/origin/${baseRef}`,
  ])

  const runs = []
  let page = 1
  for (;;) {
    const body = JSON.parse(gh([
      'api',
      `repos/${repo}/commits/${headSha}/check-runs?per_page=100&page=${page}`,
    ]))
    const batch = body.check_runs || []
    runs.push(...batch)
    if (batch.length === 0 || runs.length >= (body.total_count || 0)) break
    page += 1
    if (page > 10) break
  }

  const decision = evaluatePromotion({
    commentBody: event.comment?.body,
    isPullRequest: true,
    state: pr.state,
    draft: Boolean(pr.draft),
    permission: permissionBody.permission,
    headRepo: pr.head?.repo?.full_name || '',
    baseRepo: pr.base?.repo?.full_name || '',
    expectedRepo: repo,
    headRef,
    baseRef,
    headSha,
    baseSha,
    ciOk: ciOkConclusion(runs),
    baseIsAncestor: isAncestor(baseSha, headSha),
  })
  if (!decision.ok) fail(repo, number, decision.message)

  const prAgain = JSON.parse(gh(['api', `repos/${repo}/pulls/${number}`]))
  const stable = confirmStable({
    headSha,
    baseSha,
    headShaAgain: prAgain.head?.sha || '',
    baseShaAgain: prAgain.base?.sha || '',
    remoteHead: lsRemote(`refs/heads/${headRef}`),
    remoteBase: lsRemote(`refs/heads/${baseRef}`),
  })
  if (!stable.ok) fail(repo, number, MESSAGES.moved)

  git(pushArguments(decision.refspec))
  commentOnPullRequest(
    repo,
    number,
    `Fast-Forward: \`${baseRef}\` steht jetzt auf \`${headSha}\`. Deployment startet durch den Push auf \`${baseRef}\`.`,
  )
  console.log(`Fast-Forward ${baseRef} -> ${headSha}`)
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  main()
}
