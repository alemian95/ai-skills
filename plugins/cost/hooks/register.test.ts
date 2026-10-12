import { test, expect } from 'claude-code/testing'

test('shows the API cost when the session measures a new total', async ($, on) => {
  const statuses: (string | undefined)[] = []
  on('ui.status', (_$, e) => { statuses.push(e.text); return { value: undefined } })
  on('session.measure', (_$, e) => ({ changed: e.changed }))

  await $.session.measure({ context: { window: 200000 }, rateLimits: [], cost: { usd: 0.01234 }, changed: ['cost'] })
  await $.session.measure({ context: { window: 200000 }, rateLimits: [], cost: { usd: 3.5 }, changed: ['cost'] })
  await $.session.measure({ context: { window: 200000, percent: 10 }, rateLimits: [], cost: { usd: 3.5 }, changed: ['context'] })

  expect(statuses).toEqual(['$0.0123', '$3.50'])
})
