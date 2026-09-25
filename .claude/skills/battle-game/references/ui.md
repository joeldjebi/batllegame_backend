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

## Motion (landing, reusable anywhere)
Plugins: `@alpinejs/intersect`, `collapse`, `focus`. Utilities in `app.css` (all disabled under
`prefers-reduced-motion`):
- `.reveal` + `x-intersect.once="$el.classList.add('is-visible')"` — fade/slide in on scroll, stagger with
  `style="--delay: 120ms"`. Hidden only when `html.js` is set (the landing adds it in its head script).
- `.parallax` + `style="--speed: -0.15"` — scroll parallax driven by `--scroll-y` (set per frame in `app.js`).
- `.depth` + `style="--depth: 24"` inside `x-data="pointerParallax" x-on:mousemove="move($event)"` — mouse
  parallax. Never put `.parallax` and `.depth` on the same element (both use `transform`): nest them.
- `.float` (bobbing), `.equalizer span` (audio bars), `.marquee` (duplicate the content twice, `--duration`),
  `.draw` on an SVG (paths drawn when `.is-visible`), `.fill-bar` (`--fill` 0–1, grows when visible), `.ping-slow`.
- Alpine data: `counter(target)` (`x-intersect.once="start()"`, `x-text="formatted"`), `rotator(words, ms)`.
- Still solid colors only: shapes are solid circles, rings and SVG grid lines — no gradient, no blur blob.

## Portals (mobile-first)
The artist / public / jury portals are used mostly on phones: `x-layouts.portal` has a fixed **bottom tab bar**
on small screens (`x-portal.bottom-nav`, safe-area aware, `pb-28` on main). Tabs: **Accueil first**, then Voter /
Mon espace (members, shared by artist and fan portals) or Compétitions / Compte (jury). Active tab = exact route,
else the tab of the current area (`fan.*` → Voter); the landing also renders it for signed-in users (Accueil active). Artist space = hero, **action center** (sorted: payment first,
then closest deadline), journey cards with `x-portal.stepper`, horizontal snap carousel of open competitions.
Components: `x-portal.payment-pitch` (marketing CTA shown instead of any upload while unpaid),
`x-portal.dropzone` (touch-friendly file picker, drag & drop, client size check, submit disabled until a file is
picked), `x-portal.stepper`. Alpine: `countdown(iso)` (`label` always shows seconds so it visibly ticks, `urgent` < 24 h), `dropzone(maxMb)`. Test on 390 px width.

## Rich text
`x-ui.rich-editor name value placeholder hint` = Trix (lazy-loaded by `app.js` only when a `<trix-editor>` exists,
attachments disabled; chrome styled **unlayered** at the end of `app.css` to beat `trix.css`). Output is sanitized on
write by the `Competition::description` mutator (`App\Support\RichText::sanitize`, symfony/html-sanitizer allowlist);
display with `x-ui.rich-text :html` (`.rich-text` styles) or `RichText::excerpt()` for cards. `x-portal.competition-about`
= public « À propos » (collapsible) + « À gagner » list. Grids holding the editor need `grid-cols-1` (else the toolbar
widens the implicit track on mobile).

## Layouts
- `x-layouts.app` — back-office and admin console (sidebar `bo.sidebar`, fed by a view composer in
  `AppServiceProvider`: `$navOrganizers` or `$adminCounts`), topbar (theme, user menu), toasts, `@stack('modals')`.
- `x-layouts.portal` — jury / artist / public (top navigation from `Portal::current()`).
- `x-layouts.auth` — split-screen login (organizers, `admin` variant dark).
- `resources/views/landing.blade.php` — standalone public landing: dark full-height hero (rotating word,
  pointer-parallax floating cards with the real vote share of the featured live battle or an « Aperçu » label,
  equalizer), scrolling band of disciplines and artists, animated counters, live votes, competitions, animated
  bracket showcase, open registrations, how it works, final CTA, organizer CTA, footer with every login URL.
  Illustrations never present fake numbers or real artists as live data.

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
| `ui.modal` / `ui.slide-over` | `name`, `title`, `description`, `icon`, `show`, `danger`, `maxWidth` | open with `$dispatch('open-modal', 'name')`; auto-reopens when `old('_form') === name` and there are errors |
| `ui.dropdown` / `ui.dropdown-item` | `align`, `width` / `href`, `icon`, `danger`, `type` | |
| `ui.confirm` | `action`, `method`, `title`, `message`, `confirm`, `danger`, `icon`; slot `fields` (hidden inputs) | trigger in default slot, dialog pushed to `modals` |
| `ui.tabs` / `ui.tab-panel` | `tabs` [key => label, icon, count], `default`, `key` / `name` | tab kept in URL hash + localStorage |
| `phone-input` | `countries`, `label`, `required` (default true) | dial-code select + national number (`country_id`, `phone`) |
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

## Realtime regions
- `<x-realtime :channels="[Channel::competition($id), Channel::user($userId)]" />` (bottom of the page): public channels
  are joined as is, private ones signed if the viewer may read them. `x-realtime-status` shows « En direct » (both layouts).
- Mark areas to refresh with `data-live="key"` (unique per page). The portal layout wraps the whole page
  (`data-live="page"`), the back-office marks `summary`, `tab-*` panels, tab counts, lists; the modal stack is a region
  too (new rows need their confirm modals). On `update`, `resources/js/realtime.js` fetches the same URL and
  `Alpine.morph`s each region (debounced 350 ms + trailing 2.5 s for throttled votes), skipping regions with a focused
  field, a changed field (`data-dirty`) or a picked file. Never wrap an `x-show` element directly (wrap its content).
- Toasts: `message` of private-channel updates (+ `match.voting` on public pages).

## Pre-selection ranking
`x-bo.preselection-panel`: header + « Configurer » (slide-over `preselection-settings` with `x-bo.preselection-form`,
inline only before creation), timeline, stats, ranking card with client-side filters (Toutes / À valider / Validées /
Rejetées) and search. Each row is `x-bo.preselection-entry` (rank, artist, compact provenance, likes / jury / score,
quick « Valider », « Voir ») and pushes its detail modal `entry-{id}` (max-width 4xl: player with `preload="none"`,
file info + « Ouvrir le fichier », expanded provenance, scores, review with a reason textarea). `x-ui.modal` pauses its
media on close; `x-bo.media-player` shows « Lecture impossible » + a link when the file cannot be played.

## AJAX likes (fan page)
`Alpine.data('preselectionLikes', {likeUrl, unlikeUrl, loginUrl, loggedIn})` on the pre-selection section of
`portal/fan/competition`: state in `<script type="application/json" x-ref="state">@json($likes)</script>`
(`Fan\PreselectionController::likesState()`: `my_like`, `counts` (null until the viewer liked, unless live results /
published), `can_like`), re-read on the
`live:refreshed` window event that `realtime.js` fires after a morph. `toggle(id)` updates optimistically, calls the
like / unlike routes with `Accept: application/json` (controllers answer JSON, redirect fallback) and rolls back with
an error toast. Entries are shuffled with a per-viewer stable seed (`crc32(seed-id)`), never `inRandomOrder()` (live
refreshes would reorder the cards).

## Sharing and public entry page
- `x-portal.share :url :title :text [label] [icon] [variant] [size] [align=left|right]`: Web Share API on phones,
  otherwise a menu (WhatsApp, Facebook, X, Telegram, copy link with toast). Never inside an `overflow-hidden` parent.
- `x-portal.entry-card` (inside `preselectionLikes`): player, heart + counter, share icon, like button; used by the
  gallery and by `portal/fan/entry` (route `fan.competitions.preselection.entry`, approved entries only).
- `<x-og :title :description :url [image] [video] />` pushes Open Graph / Twitter tags to `@stack('head')` (both
  layouts); default image `public/images/og-default.png` (1200×630, solid colors).
- Artist « Mes compétitions »: `x-portal.progress` (segmented bar + « Étape k/n » on phones, `x-portal.stepper` from
  sm), one status message with a tone, media tile opening a modal player, footer « Partager / Inviter » + « Ma page ».
- Toasts: top-right under the header in both layouts (`top-16`), centered on phones.


## Places

Never a free-text city: `<x-location-select :city :commune :required label>` (country > city > commune from
`App\Support\Locations::tree()`, cached, cleared by the models) posts `city_id` / `commune_id`; validate with the
`HasLocationInput` concern (`locationRules($required)`: commune required when the city has communes). A city without
communes posts no `commune_id`: set it to null explicitly on update. Display with `$model->locationLabel()`.

## Dropdowns

`<x-ui.dropdown>` teleports its menu to `<body>` and anchors it to the trigger (`@alpinejs/anchor`): it is never
clipped by an `overflow-hidden` card or table and flips above the trigger near the bottom of the screen. Keep menu
content self-contained (forms, `x-ui.confirm` triggers work; do not rely on a parent `x-data` of the page).

## Theme

Light by default everywhere (`localStorage.theme ?? 'light'` in the four layouts and the Alpine `theme` store);
dark or « Système » only when the user picks it. Keep every `dark:` variant working.
