# UI — design system (Blade + Tailwind 4 + Alpine)

## Rules from the owner
- **Solid colors only, no gradients** (no `bg-gradient-*`, no `from-*/to-*`, no decorative blurred blobs).
- Premium, modern back-office: cards, stats, tabs, slide-overs, modals, toasts — not raw blocks.
- French UI copy; dates with `translatedFormat()` (APP_LOCALE=fr).
- Light and dark mode for every screen (`dark:` variants; theme store in `resources/js/app.js`).

## Tokens (`resources/css/app.css`)
Fonts Inter (`font-sans`) and Sora (`font-display`, headings, numbers) via `bunny()` in `vite.config.js`.
Brand palette `brand-50…950` (violet, oklch), accents fuchsia (voting), emerald (success), amber (pending),
rose (danger), sky (info). Shadows `shadow-soft`, `shadow-lift`. Animations `animate-fade-in`, `animate-slide-up`.
`.bracket-round/.bracket-match` draw the dashed connectors. `@source` scans `resources/views` and `app/Enums`.

## Layouts
- `x-layouts.app` — back-office and admin console (sidebar `bo.sidebar`, fed by a view composer in
  `AppServiceProvider`: `$navOrganizers` or `$adminCounts`), topbar (theme, user menu), toasts, `@stack('modals')`.
- `x-layouts.portal` — jury / artist / public (top navigation from `Portal::current()`).
- `x-layouts.auth` — split-screen login (organizers, `admin` variant dark).

## Components (`resources/views/components`)

| Component | Props | Notes |
|---|---|---|
| `ui.button` | `variant` primary/secondary/soft/ghost/danger/danger-soft, `size` xs/sm/md/lg, `href`, `icon`, `iconRight`, `type` | `<a>` when `href` |
| `ui.icon` | `name` (heroicon), `variant` o/s/m | wraps blade-heroicons |
| `ui.badge` | `value` (HasBadge enum → label + tone), `tone`, `dot`, `icon` | `<x-ui.badge :value="$match->status" />` |
| `ui.card` | `title`, `description`, `icon`, `padding`; slots `actions`, `footer` | |
| `ui.stat` | `label`, `value`, `icon`, `hint`, `tone` brand/green/amber/blue/red, `progress` | KPI tile |
| `ui.page-header` | `title`, `description` (prop or slot), `breadcrumbs` [label => url]; slots `leading`, `actions` | |
| `ui.table` | slot `head` (th), rows in default slot | put inside a card, negative margins |
| `ui.empty` | `icon`, `title`, `description`; slot actions | |
| `ui.avatar` | `name`, `src`, `size` xs…xl, `square` | initials + solid color |
| `ui.input` / `ui.select` / `ui.textarea` / `ui.toggle` / `ui.field` | `name` (supports `a[b]`), `label`, `value`, `hint`, `icon`, `suffix`, `options`, `placeholder` | errors and `old()` resolved from the dotted name |
| `ui.modal` / `ui.slide-over` | `name`, `title`, `description`, `icon`, `show` (reopen on validation errors), `danger`, `maxWidth` | open with `$dispatch('open-modal', 'name')` |
| `ui.dropdown` / `ui.dropdown-item` | `align`, `width` / `href`, `icon`, `danger`, `type` | |
| `ui.confirm` | `action`, `method`, `title`, `message`, `confirm`, `danger`, `icon`; slot `fields` (hidden inputs) | trigger in default slot, dialog pushed to `modals` |
| `ui.tabs` / `ui.tab-panel` | `tabs` [key => label, icon, count], `default`, `key` / `name` | tab kept in URL hash + localStorage |
| `phone-input` | `countries`, `label` | dial-code select + national number (`country_id`, `phone`) |
| `bo.match-card` | `match`, `organizer`, `competition`, `canRun`, `onsite` | scores, winner, forfeit, room code, open vote / close / captation actions |
| `bo.bracket` | `phase`, `organizer`, `competition`, `canRun` | columns per round and bracket side |
| `bo.standings` | `group`, `qualifiers` | qualified rows highlighted |
| `bo.stage-panel` | `stage`, `organizer`, `competition`, `canRun` | schedule modal, open submissions/vote, review queue with players |
| `bo.lifecycle`, `bo.bar-list`, `bo.competition-row`, `bo.media-player`, `bo.nav-link`, `bo.logo`, `admin.organizer-actions` | | |

Toasts: flash `session('status')` (success) or validation / `flow` errors are shown automatically by the
layouts; `window.dispatchEvent(new CustomEvent('toast', {detail: {type, message}}))` from JS.

## Build
`npm run dev` / `npm run build` with **Node 22** (`.nvmrc`; on the owner's Mac `PATH=/opt/homebrew/bin:$PATH`).
`public/build` is not committed. Tests render views without assets (`withoutVite()`).
