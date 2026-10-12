import { test, expect } from 'claude-code/testing'

import { priceOf } from './prices'
import { addUsage, breakdown, formatTokens, formatUsd } from './register'

const BAND = { hasSurvey: false, isWorking: false, maxRows: 20, bodyColumns: 100, scroll: { offset: 0, bodyRows: 19 }, view: {} }
const MEASURE = { context: { window: 200000 }, rateLimits: [] }
const cents = (n: number | undefined) => (n === undefined ? n : Math.round(n * 100) / 100)
const MILLION = { input_tokens: 1e6, output_tokens: 1e6, cache_read_input_tokens: 1e6, cache_creation_input_tokens: 1e6 }

test('formats dollars and tokens', () => {
  expect([formatUsd(0.01234), formatUsd(3.5)]).toEqual(['$0.0123', '$3.50'])
  expect([formatTokens(950), formatTokens(12_345), formatTokens(2_500_000)]).toEqual(['950', '12.3k', '2.5M'])
})

test('sums usage per model, subagents apart', () => {
  const turn = { model: 'opus', input_tokens: 1, output_tokens: 2, cache_read_input_tokens: 3, cache_creation_input_tokens: 4 }
  const totals = addUsage(addUsage(addUsage({}, turn, false), turn, false), turn, true)

  expect(totals).toEqual({
    opus: { input_tokens: 2, output_tokens: 4, cache_read_input_tokens: 6, cache_creation_input_tokens: 8 },
    'opus (subagent)': { input_tokens: 1, output_tokens: 2, cache_read_input_tokens: 3, cache_creation_input_tokens: 4 },
  })
})

test('prices a million of each token kind at list price, by longest id prefix', () => {
  // Opus 5.5: 4 in + 20 out + 0.20 cache read + 5 cache write (1.25x input)
  expect(cents(priceOf('claude-opus-5-5', MILLION))).toBe(29.2)
  expect(cents(priceOf('claude-opus-5-5[1m]', MILLION))).toBe(29.2)
  // Opus 5 must not borrow Opus 5.5's rate, nor the reverse
  expect(cents(priceOf('claude-opus-5', MILLION))).toBe(36.75)
  expect(priceOf('gpt-unknown', MILLION)).toBeUndefined()
})

test('the breakdown sorts by cost and keeps the rest unattributed', () => {
  const totals = {
    'claude-haiku-5-5 (subagent)': MILLION,
    'claude-opus-5-5': MILLION,
    'mystery-model': MILLION,
  }
  const { rows, unattributed } = breakdown(totals, 40)

  expect(rows.map(r => r.label)).toEqual(['claude-opus-5-5', 'claude-haiku-5-5 (subagent)', 'mystery-model'])
  expect(rows[2].usd).toBeUndefined()
  // haiku 5.5: 0.10 + 0.50 + 0.01 + 0.125
  expect(cents(unattributed)).toBe(10.07)
})

test('the cost button toggles the breakdown', async ($, on) => {
  on('session.measure', (_$, e) => ({ changed: e.changed }))
  await $.session.measure({ ...MEASURE, cost: { usd: 1.26 }, changed: ['cost'] })

  for (const surface of ['terminal', 'desktop'] as const) {
    const ui = await $.ui.mount({ plugin: 'cost', surface, component: 'AbovePrompt', props: BAND })

    expect((await ui.find({ key: 'toggle' }))?.text).toContain('$1.26')
    expect(await ui.find({ text: /Session cost at API prices/ })).toBeDefined()
    expect(await ui.find({ text: /click for the breakdown/ })).toBeDefined()
    expect(await ui.find({ text: /list price/ })).toBeUndefined()

    await ui.press({ key: 'toggle' })
    expect(await ui.find({ text: /list price/ })).toBeDefined()
    expect(await ui.find({ text: /unattributed/ })).toBeDefined()

    await ui.press({ key: 'toggle' })
  }
})
