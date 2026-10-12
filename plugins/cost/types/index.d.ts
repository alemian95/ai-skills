export type Tokens = {
  input_tokens: number
  output_tokens: number
  cache_read_input_tokens: number
  cache_creation_input_tokens: number
}

export type UsageByModel = Record<string, Tokens>

declare module 'claude-code' {
  interface PluginState {
    cost: { usd: number | null; byModel: UsageByModel; isOpen: boolean }
  }
}
