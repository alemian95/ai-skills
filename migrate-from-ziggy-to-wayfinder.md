# Piano di Migrazione: Ziggy → Wayfinder
## Laravel + Inertia.js + React

> **Documento per Claude Code** — Segui le fasi nell'ordine indicato. Ogni fase include comandi esatti, pattern da cercare e file da modificare. Le fasi sono idempotenti: puoi rieseguirle senza danni.

---

## Differenze fondamentali da tenere a mente

| Aspetto | Ziggy | Wayfinder |
|---|---|---|
| Meccanismo | Esporta le rotte in runtime via `@routes` nel DOM | Genera file TypeScript statici a build-time |
| API principale | `route('nome.rotta', params)` → stringa URL | `import { method } from '@/actions/Controller'` → oggetto `{ url, method }` |
| Parametri | Stringa o oggetto con chiavi arbitrarie | Argomenti tipizzati, generati dal controller |
| Query string | `_query: { key: value }` | `{ query: { key: value } }` come opzione |
| Route corrente | `route().current('nome.*')` | Non esiste — usare `usePage().url` o condividere il nome rotta dal backend |
| Inertia `useForm` | `form.post(route('nome'))` | `form.submit(store())` — Wayfinder risolve URL e metodo automaticamente |
| Inertia `router` | `router.visit(route('nome'))` | `router.visit(controller.method(params))` — oggetto passato direttamente |
| Cartelle generate | nessuna | `resources/js/wayfinder/`, `resources/js/actions/`, `resources/js/routes/` |
| `.gitignore` | n/a | **Sì** — le cartelle generate vanno in `.gitignore` |

> ⚠️ **Wayfinder è ancora in beta** (v0.x). L'API può cambiare prima della v1.0. Per progetti esistenti, valuta la dimensione del codebase prima di procedere.

---

## Fase 0 — Analisi e inventario

### 0.1 Verifica prerequisiti

```bash
sail artisan --version                                          # Richiede Laravel 10+ (consigliato 12+)
cat composer.json | grep -E "laravel/framework|tightenco/ziggy"
cat package.json | grep -E "ziggy-js|wayfinder"
```

### 0.2 Inventario utilizzi Ziggy

```bash
# Conta e localizza tutti i route() nel frontend
grep -rn "route(" resources/js --include="*.tsx" --include="*.ts" --include="*.jsx" --include="*.js" \
  | grep -v "node_modules" \
  | tee /tmp/ziggy-occurrences.txt

wc -l /tmp/ziggy-occurrences.txt   # quante occorrenze totali

# File con più occorrenze (priorità migrazione)
grep -rn "route(" resources/js --include="*.tsx" --include="*.ts" -l \
  | xargs -I{} sh -c 'echo "$(grep -c "route(" {}) {}"' \
  | sort -rn

# Cerca route().current() — caso speciale, nessun equivalente diretto
grep -rn "route()\.current\|\.current(" resources/js --include="*.tsx" --include="*.ts"

# Cerca _query (sintassi query string Ziggy)
grep -rn "_query:" resources/js --include="*.tsx" --include="*.ts"

# Cerca useForm con route()
grep -rn "useForm\|\.post(\|\.put(\|\.patch(\|\.delete(" resources/js --include="*.tsx" --include="*.ts" \
  | grep "route("

# Blade: @routes e route() lato PHP
grep -rn "@routes" resources/views --include="*.blade.php"
grep -rn "ziggy\|Ziggy" app/ --include="*.php"
```

### 0.3 Mappa rotte → controller

Hai due modi per ottenere la mappa completa delle rotte:

**Opzione A — Leggere direttamente `routes/web.php`** (e gli altri file in `routes/`):

```bash
cat routes/web.php
# Se esistono altri file di rotte:
ls routes/
cat routes/auth.php    # se presente
cat routes/api.php     # se presente
```

**Opzione B — Comando Artisan** (vista tabellare più leggibile):

```bash
# Vista completa con controller, metodo HTTP e nome rotta
sail artisan route:list

# Filtra per nome (es. solo rotte "posts")
sail artisan route:list --name=posts

# Esporta in JSON e salva su file per riferimento durante la migrazione
sail artisan route:list --json > /tmp/routes-list.json
```

Questa mappa è la **rosetta stone** della migrazione: per ogni `route('nome.rotta')` trovato nel frontend, cerca il nome rotta in `routes/web.php` o nell'output di `route:list` per risalire al controller e al metodo corrispondente.

---

## Fase 1 — Installazione Wayfinder

### 1.1 Pacchetto PHP

```bash
sail composer require laravel/wayfinder
```

### 1.2 Pacchetto Node

```bash
sail pnpm add @laravel/wayfinder
```

### 1.3 Vite plugin

Wayfinder si integra con Vite tramite il plugin `@laravel/vite-plugin-wayfinder`. In `vite.config.ts`:

```typescript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { wayfinder } from '@laravel/vite-plugin-wayfinder'; // ← aggiungi

export default defineConfig({
    plugins: [
        wayfinder(),   // ← prima degli altri plugin
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
    ],
});
```

> Il plugin si occupa di rigenerare i file TypeScript ogni volta che modifichi un controller o un file di rotte durante `sail pnpm dev`.

### 1.4 Prima generazione

```bash
sail artisan wayfinder:generate
```

Verifica le cartelle create:

```bash
ls resources/js/wayfinder/   # helper di base
ls resources/js/actions/     # funzioni per ogni controller
ls resources/js/routes/      # funzioni per rotte con nome
```

Opzioni utili del comando:

```bash
# Percorso personalizzato (default: resources/js)
sail artisan wayfinder:generate --path=resources/js/wayfinder

# Solo actions (senza rotte con nome)
sail artisan wayfinder:generate --skip-routes

# Solo rotte con nome (senza actions)
sail artisan wayfinder:generate --skip-actions
```

### 1.5 Aggiungi al `.gitignore`

```bash
echo "resources/js/wayfinder" >> .gitignore
echo "resources/js/actions" >> .gitignore
echo "resources/js/routes" >> .gitignore
```

I file sono completamente rigenerati a ogni build, non vanno in versione.

---

## Fase 2 — Configurazione TypeScript

### 2.1 Verifica alias `@` in `tsconfig.json`

Wayfinder usa i path `@/actions/...` e `@/routes/...`. Assicurati che l'alias `@` punti a `resources/js`:

```json
{
    "compilerOptions": {
        "baseUrl": ".",
        "paths": {
            "@/*": ["resources/js/*"]
        }
    }
}
```

### 2.2 Script `package.json`

Aggiungi uno script per rigenerare manualmente i tipi:

```json
{
    "scripts": {
        "wayfinder": "php artisan wayfinder:generate",
        "dev": "vite",
        "build": "vite build"
    }
}
```

Per eseguirli tramite Sail:

```bash
sail pnpm dev
sail pnpm build
sail pnpm wayfinder    # rigenerazione manuale dei tipi
```

> ⚠️ **Bug noto ([issue #55](https://github.com/laravel/wayfinder/issues/55))**: in alcuni ambienti il plugin Vite non genera tutti i file prima che il bundler li importi. Se `sail pnpm build` fallisce con `ENOENT: no such file or directory`, esegui prima la generazione manuale:
>
> ```bash
> sail artisan wayfinder:generate && sail pnpm build
> ```

---

## Fase 3 — Capire l'API di Wayfinder

Prima di toccare il codice, leggi come funziona Wayfinder.

### 3.1 Struttura di un file generato

Per un controller `PostController` con metodo `show` sulla rotta `GET /posts/{post}`:

```typescript
// resources/js/actions/App/Http/Controllers/PostController.ts

export const show = (post: number | string, options?: { query?: QueryParams }) => ({
    url: `/posts/${post}`,
    method: 'get' as const,
})

show.url = (post: number | string) => `/posts/${post}`
```

### 3.2 Due modi di importare

**Named import (consigliato — tree-shaking ottimale):**
```typescript
import { show, index, store } from '@/actions/App/Http/Controllers/PostController';

show(1)          // { url: '/posts/1', method: 'get' }
show.url(1)      // '/posts/1'  ← solo l'URL, senza { method }
```

**Default import (importa tutto il controller — attenzione al bundle size):**
```typescript
import PostController from '@/actions/App/Http/Controllers/PostController';

PostController.show(1)
PostController.index()
```

### 3.3 Parametri e route model binding

```typescript
// Parametro singolo
show(1)
show('my-slug')

// Route model binding con chiave custom (rotta: /posts/{post:slug})
show('my-new-post')
show({ slug: 'my-new-post' })

// Parametri multipli (rotta: /venues/{venue}/events/{event})
showEvent({ venue: 1, event: 2 })

// Query string — equivalente di _query in Ziggy
index({ query: { page: 2, filter: 'active' } })
```

### 3.4 Controller invocabili (single action)

```typescript
// Route::post('/contact', SendContactEmailController::class)
import SendContactEmailController from '@/actions/App/Http/Controllers/SendContactEmailController';

// Si chiama direttamente, senza metodo
SendContactEmailController()   // { url: '/contact', method: 'post' }
```

### 3.5 Metodi espliciti e form variants

```typescript
// Metodo esplicito (per sovrascrivere quello inferito dal controller)
update.patch({ post: 1 })   // { url: '/posts/1', method: 'patch' }
update.put({ post: 1 })     // { url: '/posts/1', method: 'put' }

// Form HTML — aggiunge _method per il method spoofing di Laravel
update.form({ post: 1 })    // { action: '/posts/1?_method=PATCH', method: 'post' }
destroy.form({ post: 1 })   // { action: '/posts/1?_method=DELETE', method: 'post' }
update.form.put({ post: 1 }) // { action: '/posts/1?_method=PUT', method: 'post' }
```

### 3.6 Rotte con nome (alternativa ai controller)

Wayfinder genera anche file basati sul nome della rotta in `resources/js/routes/`:

```typescript
// Rotta: Route::get('/posts/{post}', ...)->name('posts.show')
import { show } from '@/routes/posts';

show(1)  // { url: '/posts/1', method: 'get' }
```

Usa questa forma quando preferisci lavorare per nome piuttosto che per controller, o per rotte senza controller (closure, `Route::inertia`).

---

## Fase 4 — Tabella di conversione pattern

### 4.1 `<Link>` con Inertia

```tsx
// ❌ PRIMA (Ziggy)
import { Link } from '@inertiajs/react';
<Link href={route('posts.index')}>Lista</Link>
<Link href={route('posts.show', 1)}>Dettaglio</Link>
<Link href={route('posts.show', { post: post.id })}>Dettaglio</Link>
<Link href={route('posts.edit', post.id)}>Modifica</Link>

// ✅ DOPO (Wayfinder) — named import
import { Link } from '@inertiajs/react';
import { index, show, edit } from '@/actions/App/Http/Controllers/PostController';

<Link href={index()}>Lista</Link>
<Link href={show(1)}>Dettaglio</Link>
<Link href={show({ post: post.id })}>Dettaglio</Link>
<Link href={edit(post.id)}>Modifica</Link>
```

> `<Link href={show(1)}>` funziona perché Inertia (v2.1.2+) accetta sia una stringa che un oggetto `{ url, method }` come `href`.

### 4.2 `router.visit` / `router.get` / `router.post`

Inertia (v2.1.2+) accetta direttamente l'oggetto Wayfinder in tutti i metodi `router.*`:

```tsx
// ❌ PRIMA (Ziggy)
import { router } from '@inertiajs/react';
router.visit(route('dashboard'));
router.get(route('posts.index', { _query: { filter: 'active' } }));
router.post(route('posts.store'), formData);
router.put(route('posts.update', post.id), formData);
router.delete(route('posts.destroy', post.id));

// ✅ DOPO (Wayfinder)
import { router } from '@inertiajs/react';
import { index, store, update, destroy } from '@/actions/App/Http/Controllers/PostController';
import DashboardController from '@/actions/App/Http/Controllers/DashboardController';

router.visit(DashboardController.index());
router.visit(index({ query: { filter: 'active' } }));
router.visit(store(), { data: formData });
router.visit(update(post.id), { data: formData }); // il metodo (PUT/PATCH) viene dall'oggetto
router.visit(destroy(post.id));                     // il metodo (DELETE) viene dall'oggetto
```

### 4.3 `useForm`

Wayfinder si integra nativamente con `useForm` tramite `form.submit()`:

```tsx
// ❌ PRIMA (Ziggy)
import { useForm } from '@inertiajs/react';
const form = useForm({ title: '', body: '' });

form.post(route('posts.store'));
form.put(route('posts.update', post.id));
form.patch(route('posts.update', post.id));
form.delete(route('posts.destroy', post.id));

// ✅ DOPO (Wayfinder)
import { useForm } from '@inertiajs/react';
import { store, update, destroy } from '@/actions/App/Http/Controllers/PostController';

const form = useForm({ title: '', body: '' });

form.submit(store());                        // POST a /posts
form.submit(update({ post: post.id }));     // PUT/PATCH a /posts/{id}
form.submit(destroy({ post: post.id }));    // DELETE a /posts/{id}
```

> `form.submit()` legge `method` direttamente dall'oggetto Wayfinder: nessuna ambiguità su GET/POST/PUT/DELETE.

### 4.4 Query string (`_query` → `query`)

```tsx
// ❌ PRIMA (Ziggy) — _query per i parametri extra non presenti nella rotta
route('posts.index', { _query: { page: 2, search: 'foo', sort: 'asc' } })
// → /posts?page=2&search=foo&sort=asc

// ✅ DOPO (Wayfinder)
import { index } from '@/actions/App/Http/Controllers/PostController';

index({ query: { page: 2, search: 'foo', sort: 'asc' } })
// → { url: '/posts?page=2&search=foo&sort=asc', method: 'get' }

// Solo l'URL (senza { method }):
index.url({ query: { page: 2, search: 'foo', sort: 'asc' } })
// → '/posts?page=2&search=foo&sort=asc'
```

### 4.5 Route model binding con oggetto Eloquent

```tsx
// ❌ PRIMA (Ziggy) — accetta l'oggetto, usa la chiave 'id' automaticamente
const post = { id: 1, title: 'Hello' };
route('posts.show', post)  // → /posts/1

// ✅ DOPO (Wayfinder) — passa il valore esplicitamente
import { show } from '@/actions/App/Http/Controllers/PostController';

show(post.id)              // → { url: '/posts/1', method: 'get' }
show({ post: post.id })    // → { url: '/posts/1', method: 'get' }
```

### 4.6 `fetch` / `axios`

```tsx
// ❌ PRIMA (Ziggy)
const res = await fetch(route('api.posts.index'));
const res = await axios.get(route('api.posts.show', id));
await axios.post(route('api.posts.store'), payload);

// ✅ DOPO (Wayfinder) — usa .url per estrarre solo la stringa
import { index, show, store } from '@/actions/App/Http/Controllers/Api/PostController';

const res = await fetch(index().url);
const res = await axios.get(show(id).url);
await axios.post(store().url, payload);

// Oppure, con axios, passa l'intero oggetto (url + method):
const { url, method } = store();
await axios({ method, url, data: payload });
```

### 4.7 Controller invocabili (single action)

```tsx
// Rotta: Route::post('/contact', SendContactEmailController::class)->name('contact.send')

// ❌ PRIMA (Ziggy)
router.post(route('contact.send'), formData);

// ✅ DOPO (Wayfinder)
import SendContactEmailController from '@/actions/App/Http/Controllers/SendContactEmailController';

router.visit(SendContactEmailController(), { data: formData });
// oppure con useForm:
form.submit(SendContactEmailController());
```

### 4.8 Form HTML (non-Inertia, con method spoofing)

```tsx
// ✅ WAYFINDER — .form() gestisce il _method spoofing automaticamente
import { update, destroy } from '@/actions/App/Http/Controllers/PostController';

// PATCH con _method spoofing
<form {...update.form({ post: 1 })}>
  {/* renders: action="/posts/1?_method=PATCH" method="post" */}
</form>

// PUT esplicito
<form {...update.form.put({ post: 1 })}>
  {/* renders: action="/posts/1?_method=PUT" method="post" */}
</form>

// DELETE
<form {...destroy.form({ post: 1 })}>
  {/* renders: action="/posts/1?_method=DELETE" method="post" */}
</form>
```

---

## Fase 5 — Caso speciale: `route().current()`

Ziggy offre `route().current('nome.rotta')` per rilevare la rotta attiva. **Wayfinder non ha un equivalente diretto.** Scegli una delle tre alternative:

### Opzione A — Condividere il nome rotta dal backend (consigliata)

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'currentRoute' => $request->route()?->getName(),
    ]);
}
```

```tsx
// Nel componente React
import { usePage } from '@inertiajs/react';

const { currentRoute } = usePage().props as { currentRoute: string | null };

// Equivalenti Ziggy → Wayfinder:
// route().current('posts.index')  →  currentRoute === 'posts.index'
// route().current('posts.*')      →  currentRoute?.startsWith('posts.')
```

### Opzione B — Confrontare con `usePage().url`

```tsx
import { usePage } from '@inertiajs/react';

const { url } = usePage();
const isPostsPage = url.startsWith('/posts');
```

### Opzione C — Confrontare l'URL corrente con quello generato da Wayfinder

```tsx
import { usePage } from '@inertiajs/react';
import { index } from '@/actions/App/Http/Controllers/PostController';

const { url } = usePage();
const isPostsIndex = url === index().url;
```

---

## Fase 6 — Migrazione file per file

### 6.1 Ordine consigliato

1. **Componenti di navigazione e layout** (`resources/js/Layouts/`) — usati ovunque, alto impatto
2. **Pagine** (`resources/js/Pages/`) — dal più semplice (meno `route()`) al più complesso
3. **Componenti condivisi** (`resources/js/Components/`)
4. **Hook e utility** (`resources/js/hooks/`, `resources/js/lib/`)

### 6.2 Procedura per ogni file

Per ogni file con utilizzi di `route()`:

1. **Identifica le rotte usate** — `grep "route(" resources/js/path/al/File.tsx`
2. **Trova il controller** — cerca il nome rotta in `routes/web.php` oppure con `sail artisan route:list --name=nome.rotta`
3. **Verifica il file generato** — `cat resources/js/actions/App/Http/Controllers/NomeController.ts`
4. **Sostituisci** ogni `route('nome', params)` con l'import e la chiamata corretta
5. **Aggiungi gli import** in cima al file
6. **Rimuovi** eventuali import di `ziggy-js` dal file
7. **Controlla TypeScript** — `sail pnpm exec tsc --noEmit`

### 6.3 Gestire rotte senza controller (closures / Route::inertia)

```php
// routes/web.php
Route::get('/about', fn() => inertia('About'))->name('about');
Route::inertia('/faq', 'Faq')->name('faq');
```

Per queste rotte Wayfinder non genera nulla in `actions/` (nessun controller). Usa i file in `routes/`:

```typescript
import { about } from '@/routes/about';
import { faq } from '@/routes/faq';

<Link href={about()}>Chi siamo</Link>
<Link href={faq()}>FAQ</Link>
```

Se la rotta non ha nome, hai due opzioni: darle un nome, oppure passare l'URL come stringa hardcoded (`href="/about"`).

---

## Fase 7 — Rimozione Ziggy

Esegui questa fase solo quando **tutti** i `route()` nel frontend sono stati sostituiti e `sail pnpm exec tsc --noEmit` non riporta errori.

### 7.1 Rimuovi `@routes` dai layout Blade

```blade
{{-- PRIMA --}}
<head>
    @routes
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>

{{-- DOPO --}}
<head>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
```

### 7.2 Rimuovi Ziggy da `HandleInertiaRequests`

```php
// PRIMA
use Tightenco\Ziggy\Ziggy;

public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'ziggy' => fn () => [
            ...(new Ziggy)->toArray(),
            'location' => $request->url(),
        ],
    ]);
}

// DOPO — rimuovi la chiave ziggy (e l'use statement)
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        // Se hai implementato l'Opzione A per route().current():
        'currentRoute' => $request->route()?->getName(),
    ]);
}
```

### 7.3 Rimuovi Ziggy da `app.tsx`

Cerca e rimuovi tutti questi pattern:

```typescript
import route from 'ziggy-js';
import { route } from 'ziggy-js';
import { ZiggyVue } from 'ziggy-js';
window.route = route;
(window as any).route = route;
```

### 7.4 Rimuovi le dichiarazioni di tipo globali Ziggy

```bash
grep -rn "ziggy\|RouteParam\|ZiggyConfig\|route: typeof routeFn" \
  resources/js --include="*.d.ts" --include="*.ts"
```

Rimuovi le sezioni trovate, tipicamente:

```typescript
// Da rimuovere dai file .d.ts
import { route as routeFn } from 'ziggy-js';
declare global {
    var route: typeof routeFn;
}
```

### 7.5 Disinstalla i pacchetti

```bash
sail composer remove tightenco/ziggy
sail pnpm remove ziggy-js
```

---

## Fase 8 — Verifica finale

### 8.1 Nessun residuo Ziggy

```bash
# Frontend — nessun route() o import ziggy
grep -rn "ziggy\|route(" resources/js \
  --include="*.tsx" --include="*.ts" --include="*.jsx" --include="*.js" \
  | grep -v "node_modules"

# Backend e Blade
grep -rn "@routes\|Ziggy\|ziggy" \
  resources/views app \
  --include="*.php" --include="*.blade.php"

# Package files
grep -i "ziggy" composer.json package.json
```

### 8.2 TypeScript senza errori

```bash
sail pnpm exec tsc --noEmit
```

### 8.3 Build di produzione

```bash
# Genera i tipi prima di buildare (workaround bug #55)
sail artisan wayfinder:generate && sail pnpm build
```

### 8.4 Smoke test funzionale

```bash
sail up -d
sail pnpm dev
```

Verifica manualmente nel browser:
- navigazione tra pagine (Link)
- submit di form (`useForm`)
- chiamate API (`fetch` / `axios`)
- active state sulla navigazione (se hai implementato l'Opzione A/B/C per `route().current()`)

---

## Checklist finale

```
[ ] Wayfinder installato (sail composer require + sail pnpm add)
[ ] Plugin Vite (@laravel/vite-plugin-wayfinder) configurato in vite.config.ts
[ ] Prima generazione eseguita: sail artisan wayfinder:generate
[ ] Cartelle generate (wayfinder/, actions/, routes/) aggiunte a .gitignore
[ ] Alias @ in tsconfig.json punta a resources/js
[ ] Tutti i route('nome', params) sostituiti con import da @/actions/ o @/routes/
[ ] _query: {} sostituito con { query: {} }
[ ] form.post/put/patch/delete(route()) sostituito con form.submit(controller.method())
[ ] router.visit/get/post/delete(route()) aggiornati con oggetti Wayfinder
[ ] route().current() sostituito con currentRoute prop o url comparison
[ ] Rotte senza controller (closure/inertia) gestite tramite @/routes/
[ ] @routes rimosso dai layout Blade
[ ] HandleInertiaRequests ripulito (use Ziggy rimosso, chiave ziggy rimossa)
[ ] Import e dichiarazioni Ziggy rimosse da app.tsx e file .d.ts
[ ] sail composer remove tightenco/ziggy eseguito
[ ] sail pnpm remove ziggy-js eseguito
[ ] sail pnpm exec tsc --noEmit senza errori
[ ] sail artisan wayfinder:generate && sail pnpm build senza errori
[ ] Smoke test funzionale superato
```

---

## Riferimenti

- [laravel/wayfinder — GitHub (stable)](https://github.com/laravel/wayfinder)
- [laravel/wayfinder — GitHub (next beta)](https://github.com/laravel/wayfinder/tree/next)
- [tighten/ziggy — GitHub](https://github.com/tighten/ziggy)
- [Inertia.js — Manual Visits con Wayfinder](https://inertiajs.com/manual-visits)
- [Inertia.js — Routing](https://inertiajs.com/routing)
- [Issue nota #55 — bug build Vite](https://github.com/laravel/wayfinder/issues/55)
