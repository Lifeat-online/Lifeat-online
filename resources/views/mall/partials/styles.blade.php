@once
    @push('styles')
        <style>
            .mall-shell { display: grid; gap: 1.25rem; }
            .mall-hero { display: grid; gap: 1rem; padding: 1.25rem; border: 1px solid var(--border); background: var(--surface); border-radius: 8px; }
            .mall-hero-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(220px, 360px); gap: 1rem; align-items: center; }
            .mall-title { margin: 0; font-size: clamp(2rem, 4vw, 3.6rem); line-height: 1; }
            .mall-subtitle { max-width: 68ch; margin: 0; color: var(--muted); }
            .mall-toolbar { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between; }
            .mall-form-row { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
            .mall-input, .mall-select, .mall-textarea { border: 1px solid var(--border); border-radius: 8px; padding: .72rem .85rem; background: var(--surface); color: var(--text); min-height: 2.75rem; }
            .mall-textarea { width: 100%; min-height: 7rem; resize: vertical; }
            .mall-button { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; min-height: 2.75rem; border: 1px solid var(--border); border-radius: 8px; padding: .72rem 1rem; background: var(--text); color: var(--surface); font-weight: 700; text-decoration: none; cursor: pointer; }
            .mall-button.secondary { background: var(--surface); color: var(--text); }
            .mall-button.danger { background: #b91c1c; color: #fff; border-color: #b91c1c; }
            .mall-chip-row { display: flex; flex-wrap: wrap; gap: .45rem; }
            .mall-chip { display: inline-flex; align-items: center; border: 1px solid var(--border); border-radius: 999px; padding: .38rem .7rem; color: var(--text); text-decoration: none; background: color-mix(in srgb, var(--surface) 88%, var(--accent, #3B82F6)); }
            .mall-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1rem; }
            .mall-card { display: grid; gap: .75rem; border: 1px solid var(--border); border-radius: 8px; padding: .9rem; background: var(--surface); min-width: 0; }
            .mall-card img, .mall-banner { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; border-radius: 8px; background: color-mix(in srgb, var(--surface) 70%, var(--text)); }
            .mall-window-grid { display: grid; gap: 1rem; }
            .mall-window { display: grid; gap: 0; overflow: hidden; border: 1px solid color-mix(in srgb, var(--accent, #3B82F6) 42%, var(--border)); border-radius: 8px; background: linear-gradient(180deg, color-mix(in srgb, var(--surface) 90%, var(--accent, #3B82F6)), var(--surface)); }
            .mall-window-sign { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: .85rem; align-items: center; padding: .8rem 1rem; border-bottom: 1px solid var(--border); background: color-mix(in srgb, var(--surface) 84%, var(--accent, #3B82F6)); }
            .mall-window-name { margin: 0; font-size: clamp(1.45rem, 2.4vw, 2.2rem); line-height: 1.05; }
            .mall-window-glass { position: relative; display: grid; grid-auto-flow: column; grid-auto-columns: minmax(132px, 160px); grid-template-columns: none; gap: .65rem; overflow-x: auto; overflow-y: hidden; overscroll-behavior-inline: contain; scroll-snap-type: inline proximity; padding: .85rem; min-height: 0; background:
                linear-gradient(120deg, rgba(255,255,255,.18), transparent 28%),
                linear-gradient(180deg, color-mix(in srgb, var(--surface) 55%, transparent), color-mix(in srgb, var(--accent, #3B82F6) 14%, var(--surface)));
            }
            .mall-window-glass::after { content: ""; position: absolute; inset: 0; pointer-events: none; background: linear-gradient(110deg, transparent 0 42%, rgba(255,255,255,.18) 44%, transparent 54%); }
            .mall-window-product { position: relative; display: grid; grid-template-rows: minmax(92px, 1fr) auto; gap: .45rem; min-width: 0; padding: .55rem; border: 1px solid color-mix(in srgb, var(--border) 72%, transparent); border-radius: 8px; background: color-mix(in srgb, var(--surface) 78%, transparent); box-shadow: 0 12px 30px rgba(0,0,0,.16); }
            .mall-window-product img { width: 100%; aspect-ratio: 1 / 1; object-fit: cover; border-radius: 6px; background: color-mix(in srgb, var(--surface) 70%, var(--text)); }
            .mall-window-product strong { font-size: .9rem; line-height: 1.15; }
            .mall-window-glass .mall-window-product { scroll-snap-align: start; text-decoration: none; }
            .mall-window-glass .mall-window-product img { aspect-ratio: 4 / 3; }
            .mall-window-footer { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .75rem; align-items: center; padding: .9rem 1rem 1rem; border-top: 1px solid var(--border); }
            .mall-storefront-body { display: grid; gap: 1rem; padding: 1rem; border: 1px solid color-mix(in srgb, var(--accent, #3B82F6) 35%, var(--border)); border-radius: 8px; background:
                linear-gradient(125deg, rgba(255,255,255,.14), transparent 30%),
                linear-gradient(180deg, color-mix(in srgb, var(--surface) 90%, var(--accent, #3B82F6)), var(--surface));
            }
            .mall-storefront-head { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: .9rem; align-items: center; }
            .mall-storefront-copy { display: grid; gap: .6rem; }
            .mall-window-strip { position: relative; display: grid; grid-auto-flow: column; grid-auto-columns: minmax(150px, 220px); gap: .75rem; overflow-x: auto; overscroll-behavior-inline: contain; scroll-snap-type: inline proximity; padding: .9rem; border: 1px solid var(--border); border-radius: 8px; background:
                linear-gradient(110deg, transparent 0 42%, rgba(255,255,255,.16) 44%, transparent 54%),
                color-mix(in srgb, var(--surface) 78%, var(--accent, #3B82F6));
            }
            .mall-window-strip .mall-window-product { scroll-snap-align: start; text-decoration: none; }
            .mall-logo { width: 84px; height: 84px; object-fit: contain; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); padding: .5rem; }
            .mall-price { font-size: 1.05rem; font-weight: 800; }
            .mall-muted { color: var(--muted); }
            .mall-split { display: grid; grid-template-columns: minmax(0, 1fr) minmax(260px, 340px); gap: 1rem; align-items: start; }
            .mall-sidebar { position: sticky; top: 1rem; display: grid; gap: .75rem; border: 1px solid var(--border); border-radius: 8px; padding: 1rem; background: var(--surface); }
            .mall-line-item { display: grid; grid-template-columns: 72px minmax(0, 1fr) auto; gap: .75rem; align-items: center; border-bottom: 1px solid var(--border); padding: .75rem 0; }
            .mall-line-item img { width: 72px; height: 72px; object-fit: cover; border-radius: 8px; }
            .mall-total-row { display: flex; justify-content: space-between; gap: 1rem; font-weight: 800; }
            .mall-delivery-option { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: .6rem; align-items: start; padding: .6rem 0; border-top: 1px solid var(--border); }
            .mall-delivery-option:first-of-type { border-top: 0; }
            .mall-delivery-address-slot:empty { display: none; }
            .mall-delivery-address-block { display: grid; gap: .35rem; padding: .65rem 0 .85rem 1.55rem; border-top: 1px solid var(--border); }
            .mall-delivery-address-block[hidden] { display: none; }
            .mall-address-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .5rem; align-items: start; }
            .mall-alert { border: 1px solid var(--border); border-radius: 8px; padding: .75rem 1rem; background: var(--surface); }
            .mall-empty { border: 1px dashed var(--border); border-radius: 8px; padding: 2rem; text-align: center; background: var(--surface); }
            @media (max-width: 860px) {
                .mall-hero-grid, .mall-split { grid-template-columns: 1fr; }
                .mall-window-sign { grid-template-columns: auto minmax(0, 1fr); }
                .mall-window-sign .mall-button { grid-column: 1 / -1; }
                .mall-window-glass { grid-auto-columns: minmax(128px, 156px); }
                .mall-storefront-head { grid-template-columns: auto minmax(0, 1fr); }
                .mall-storefront-head .mall-button { grid-column: 1 / -1; }
                .mall-window-strip { grid-auto-columns: minmax(145px, 74vw); }
                .mall-sidebar { position: static; }
                .mall-line-item { grid-template-columns: 56px minmax(0, 1fr); }
                .mall-line-actions { grid-column: 1 / -1; }
                .mall-address-row { grid-template-columns: 1fr; }
            }
        </style>
    @endpush
@endonce
