import { atom, read, update } from 'claude-code'
import type { ModelUsage, Register, TurnUsage } from 'claude-code'

import type { UsageByModel } from '../types'
import { priceOf } from './prices'

const usd = atom({ plugin: 'cost', key: 'usd' } as const, null)
const byModel = atom({ plugin: 'cost', key: 'byModel' } as const, {})
const isOpen = atom({ plugin: 'cost', key: 'isOpen' } as const, false)

const ZERO: ModelUsage = { input_tokens: 0, output_tokens: 0, cache_read_input_tokens: 0, cache_creation_input_tokens: 0 }
const SUBAGENT = ' (subagent)'

export const formatUsd = (value: number): string => `$${value.toFixed(value < 1 ? 4 : 2)}`

export const formatTokens = (n: number): string =>
  n >= 1e6 ? `${(n / 1e6).toFixed(1)}M` : n >= 1e3 ? `${(n / 1e3).toFixed(1)}k` : `${n}`

export const addUsage = (totals: UsageByModel, { model, ...usage }: TurnUsage, isSubagent: boolean): UsageByModel => {
  const key = isSubagent ? `${model}${SUBAGENT}` : model
  const sum = totals[key] ?? ZERO
  return {
    ...totals,
    [key]: {
      input_tokens: sum.input_tokens + usage.input_tokens,
      output_tokens: sum.output_tokens + usage.output_tokens,
      cache_read_input_tokens: sum.cache_read_input_tokens + usage.cache_read_input_tokens,
      cache_creation_input_tokens: sum.cache_creation_input_tokens + usage.cache_creation_input_tokens,
    },
  }
}

export type BreakdownRow = { label: string; usd: number | undefined; usage: ModelUsage }

/** Rows by cost, most expensive first, then what the engine's exact total holds beyond their estimates. */
export const breakdown = (totals: UsageByModel, total: number): { rows: BreakdownRow[]; unattributed: number } => {
  const rows = Object.entries(totals)
    .map(([label, usage]) => ({ label, usage, usd: priceOf(label.replace(SUBAGENT, ''), usage) }))
    .sort((a, b) => (b.usd ?? -1) - (a.usd ?? -1))
  const priced = rows.reduce((sum, row) => sum + (row.usd ?? 0), 0)
  return { rows, unattributed: total - priced }
}

const tokens = (u: ModelUsage): string =>
  `in ${formatTokens(u.input_tokens)} · out ${formatTokens(u.output_tokens)} · ` +
  `cache read ${formatTokens(u.cache_read_input_tokens)} · write ${formatTokens(u.cache_creation_input_tokens)}`

const USD_COLUMN = 10

export const register: Register = on => {
  // A resumed session already has a total; afterwards every measurement that moved the cost refreshes it.
  on('session.start', async ($, e, next) => {
    const started = await next(e)
    const { cost } = await $.session.usage()
    if (cost !== undefined) await update($, usd, () => cost.usd)
    return started
  })

  on('session.measure', async ($, e, next) => {
    const { cost } = e
    if (e.changed.includes('cost') && cost !== undefined) await update($, usd, () => cost.usd)
    return next(e)
  })

  on('turn.complete', async ($, e, next) => {
    const { usage } = e
    if (usage !== undefined) await update($, byModel, totals => addUsage(totals, usage, e.agentId !== undefined))
    return next(e)
  })

  on('ui.render', { component: 'AbovePrompt' }, async ($, e, next) => {
    const total = await read($, usd)
    if (e.props.hasSurvey || total === null) return next(e)

    const { Box, Button, Text } = $.ui.resolve(e)
    const open = await read($, isOpen)
    const toggle = (
      <Box key="header" flexDirection="row" gap={1}>
        <Text dimColor>Session cost at API prices</Text>
        <Button key="toggle" label={`${formatUsd(total)} ${open ? '▾' : '▸'}`} onPress={() => update($, isOpen, v => !v)} />
        {!open && <Text dimColor>click for the breakdown by model</Text>}
      </Box>
    )
    if (!open) return toggle

    const { rows, unattributed } = breakdown(await read($, byModel), total)

    return (
      <Box flexDirection="column">
        {toggle}
        {rows.map(row => (
          <Box key={row.label} flexDirection="row">
            <Box width={USD_COLUMN}>
              <Text bold>{row.usd === undefined ? '?' : formatUsd(row.usd)}</Text>
            </Box>
            <Box flexDirection="column">
              <Text>{row.label}</Text>
              <Text dimColor>{tokens(row.usage)}</Text>
            </Box>
          </Box>
        ))}
        {Math.abs(unattributed) >= 0.0001 && (
          <Box key="unattributed" flexDirection="row">
            <Box width={USD_COLUMN}>
              <Text dimColor>{formatUsd(unattributed)}</Text>
            </Box>
            <Text dimColor>unattributed: compaction, internal calls, turns before this view, other rates</Text>
          </Box>
        )}
        <Text dimColor italic>
          Per-model $ estimated at list price; the total is Claude Code's own, as /cost.
        </Text>
      </Box>
    )
  })
}
