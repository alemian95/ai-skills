# Phase 0 — Analysis and inventory

### 0.1 Check prerequisites

```bash
sail artisan --version                                          # Requires Laravel 10+ (12+ recommended)
cat composer.json | grep -E "laravel/framework|tightenco/ziggy"
cat package.json | grep -E "ziggy-js|wayfinder"
```

### 0.2 Inventory of Ziggy usages

```bash
# Count and locate every route() in the frontend
grep -rn "route(" resources/js --include="*.tsx" --include="*.ts" --include="*.jsx" --include="*.js" \
  | grep -v "node_modules" \
  | tee /tmp/ziggy-occurrences.txt

wc -l /tmp/ziggy-occurrences.txt   # total number of occurrences

# Files with the most occurrences (migration priority)
grep -rn "route(" resources/js --include="*.tsx" --include="*.ts" -l \
  | xargs -I{} sh -c 'echo "$(grep -c "route(" {}) {}"' \
  | sort -rn

# Search for route().current() — special case, no direct equivalent
grep -rn "route()\.current\|\.current(" resources/js --include="*.tsx" --include="*.ts"

# Search for _query (Ziggy query string syntax)
grep -rn "_query:" resources/js --include="*.tsx" --include="*.ts"

# Search for useForm with route()
grep -rn "useForm\|\.post(\|\.put(\|\.patch(\|\.delete(" resources/js --include="*.tsx" --include="*.ts" \
  | grep "route("

# Blade: @routes and route() on the PHP side
grep -rn "@routes" resources/views --include="*.blade.php"
grep -rn "ziggy\|Ziggy" app/ --include="*.php"
```

### 0.3 Route → controller map

You have two ways to get the full route map:

**Option A — Read `routes/web.php` directly** (and the other files in `routes/`):

```bash
cat routes/web.php
# If other route files exist:
ls routes/
cat routes/auth.php    # if present
cat routes/api.php     # if present
```

**Option B — Artisan command** (more readable tabular view):

```bash
# Full view with controller, HTTP method and route name
sail artisan route:list

# Filter by name (e.g. only "posts" routes)
sail artisan route:list --name=posts

# Export as JSON and save to a file for reference during the migration
sail artisan route:list --json > /tmp/routes-list.json
```

This map is the **Rosetta Stone** of the migration: for every `route('route.name')` found in the frontend, look up the route name in `routes/web.php` or in the `route:list` output to trace it back to the corresponding controller and method.
