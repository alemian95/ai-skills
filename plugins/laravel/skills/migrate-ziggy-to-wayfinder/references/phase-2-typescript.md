# Phase 2 — TypeScript configuration

### 2.1 Check the `@` alias in `tsconfig.json`

Wayfinder uses the paths `@/actions/...` and `@/routes/...`. Make sure the `@` alias points to `resources/js`:

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

### 2.2 `package.json` scripts

Add a script to regenerate the types manually:

```json
{
    "scripts": {
        "wayfinder": "php artisan wayfinder:generate",
        "dev": "vite",
        "build": "vite build"
    }
}
```

To run them through Sail:

```bash
sail pnpm dev
sail pnpm build
sail pnpm wayfinder    # manual type regeneration
```

> ⚠️ **Known bug ([issue #55](https://github.com/laravel/wayfinder/issues/55))**: in some environments the Vite plugin does not generate all the files before the bundler imports them. If `sail pnpm build` fails with `ENOENT: no such file or directory`, run the manual generation first:
>
> ```bash
> sail artisan wayfinder:generate && sail pnpm build
> ```
