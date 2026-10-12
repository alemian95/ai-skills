import type { ModelUsage } from 'claude-code'

// USD per million tokens, Anthropic first-party list prices as of 2026-10. Update here when rates change.
// ponytail: cache writes priced at the 5-minute rate (1.25x input); 1-hour writes, fast mode and Haiku 5.5's
// >100K-token tier fall into the "unattributed" row of the breakdown.
type Rate = { input: number; output: number; cacheRead: number }

const RATES: Record<string, Rate> = {
  'claude-fable-5-1': { input: 10, output: 50, cacheRead: 0.25 },
  'claude-mythos-5-1': { input: 10, output: 50, cacheRead: 0.25 },
  'claude-fable-5': { input: 10, output: 50, cacheRead: 1 },
  'claude-mythos-5': { input: 10, output: 50, cacheRead: 1 },
  'claude-opus-5-5': { input: 4, output: 20, cacheRead: 0.2 },
  'claude-opus-5': { input: 5, output: 25, cacheRead: 0.5 },
  'claude-opus-4-8': { input: 5, output: 25, cacheRead: 0.5 },
  'claude-opus-4-7': { input: 5, output: 25, cacheRead: 0.5 },
  'claude-opus-4-6': { input: 5, output: 25, cacheRead: 0.5 },
  'claude-sonnet-5-5': { input: 2, output: 10, cacheRead: 0.2 },
  'claude-sonnet-5': { input: 2, output: 10, cacheRead: 0.2 },
  'claude-sonnet-4-6': { input: 3, output: 15, cacheRead: 0.3 },
  'claude-haiku-5-5': { input: 0.1, output: 0.5, cacheRead: 0.01 },
  'claude-haiku-4-5': { input: 1, output: 5, cacheRead: 0.1 },
}

// Longest matching prefix, so "claude-opus-5-5[1m]" or a dated id finds its rate and "claude-opus-5" never shadows "claude-opus-5-5".
const rateOf = (model: string): Rate | undefined =>
  Object.keys(RATES)
    .filter(id => model.startsWith(id))
    .sort((a, b) => b.length - a.length)
    .map(id => RATES[id])[0]

/** Estimated USD for these tokens on this model; undefined for a model the table does not know. */
export const priceOf = (model: string, u: ModelUsage): number | undefined => {
  const rate = rateOf(model)
  if (rate === undefined) return undefined
  return (
    (u.input_tokens * rate.input +
      u.output_tokens * rate.output +
      u.cache_read_input_tokens * rate.cacheRead +
      u.cache_creation_input_tokens * rate.input * 1.25) /
    1e6
  )
}
