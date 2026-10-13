# Phase 9 — Final verification

### 9.1 No Ziggy leftovers

```bash
# Frontend — no route() or ziggy imports
grep -rn "ziggy\|route(" resources/js \
  --include="*.tsx" --include="*.ts" --include="*.jsx" --include="*.js" \
  | grep -v "node_modules"

# Backend and Blade
grep -rn "@routes\|Ziggy\|ziggy" \
  resources/views app \
  --include="*.php" --include="*.blade.php"

# Package files
grep -i "ziggy" composer.json package.json

# Boost guidelines and agent context files
grep -rn "ziggy\|Ziggy" .ai/ CLAUDE.md AGENTS.md 2>/dev/null
```

### 9.2 TypeScript with no errors

```bash
sail pnpm exec tsc --noEmit
```

### 9.3 Production build

```bash
# Generate the types before building (workaround for bug #55)
sail artisan wayfinder:generate && sail pnpm build
```

### 9.4 Functional smoke test

```bash
sail up -d
sail pnpm dev
```

Verify manually in the browser:
- navigation between pages (Link)
- form submission (`useForm`)
- API calls (`fetch` / `axios`)
- active state in the navigation (if you implemented Option A/B/C for `route().current()`)
