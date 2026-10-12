import type { Register, SessionCost } from 'claude-code'

export const formatCost = (cost: SessionCost | undefined): string | undefined =>
  cost === undefined ? undefined : `$${cost.usd.toFixed(cost.usd < 1 ? 4 : 2)}`

export const register: Register = on => {
  // Shown from the start (a resumed session already has a total), then refreshed on every measurement.
  on('session.start', async ($, e, next) => {
    const started = await next(e)
    $.ui.status(formatCost((await $.session.usage()).cost))
    return started
  })

  on('session.measure', ($, e, next) => {
    if (e.changed.includes('cost')) $.ui.status(formatCost(e.cost))
    return next(e)
  })
}
