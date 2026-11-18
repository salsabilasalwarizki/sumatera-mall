<div class="filter-dock" data-filter-dock>
    <button type="button" class="filter-dock__launcher" data-filter-dock-toggle
            aria-controls="filter-dock-panel" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path d="M4 6h16M7 12h10m-7 6h4" stroke-width="1.8" stroke-linecap="round"></path>
        </svg>
        <span data-i18n="filter_dock_open">Open data filters</span>
        <span class="filter-dock__count" data-filter-dock-count></span>
    </button>
    <section id="filter-dock-panel" class="filter-dock__panel" aria-labelledby="filter-dock-heading" hidden>
        <header class="filter-dock__header">
            <h2 id="filter-dock-heading" data-i18n="filter_dock_title">Data filters</h2>
            <button type="button" class="filter-dock__close" data-filter-dock-close
                    aria-label="Close filters" title="Close filters">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path d="m6 6 12 12M18 6 6 18" stroke-width="1.8" stroke-linecap="round"></path>
                </svg>
            </button>
        </header>
        <div class="filter-dock__groups" data-filter-dock-groups></div>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const dock = document.querySelector('[data-filter-dock]');
        const panel = document.getElementById('filter-dock-panel');
        const toggle = dock?.querySelector('[data-filter-dock-toggle]');
        const groups = dock?.querySelector('[data-filter-dock-groups]');
        const rows = [...document.querySelectorAll('.bubble-filter-row')];

        if (!dock || !panel || !toggle || !groups || rows.length === 0) {
            dock?.setAttribute('hidden', '');
            return;
        }

        rows.forEach((row, index) => {
            const group = document.createElement('section');
            group.className = 'filter-dock__group';
            const title = document.createElement('h3');
            title.className = 'filter-dock__group-title';
            title.id = `filter-dock-group-${index + 1}`;
            const label = (row.getAttribute('aria-label') || '').toLocaleLowerCase();
            title.dataset.i18n = label.includes('category') ? 'filter_category'
                : label.includes('rating') ? 'filter_rating_price'
                    : label.includes('province') ? 'filter_by_province'
                        : label.includes('city') ? 'field_city'
                            : label.includes('price') ? 'filter_any_price'
                                : label.includes('mall') ? 'field_mall'
                                    : label.includes('recommend') || label.includes('result') ? 'refine_results'
                                        : 'quick_filters';
            title.textContent = window.sumateraTranslate(title.dataset.i18n);
            group.setAttribute('aria-labelledby', title.id);
            group.append(title, row);
            row.setAttribute('aria-labelledby', title.id);
            row.removeAttribute('aria-label');
            groups.append(group);

            if (row.previousElementSibling?.tagName === 'P') {
                row.previousElementSibling.hidden = true;
            }
        });

        const count = dock.querySelector('[data-filter-dock-count]');
        if (count) count.textContent = String(rows.length);

        const closePanel = () => {
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        };
        toggle.addEventListener('click', () => {
            panel.hidden = !panel.hidden;
            toggle.setAttribute('aria-expanded', String(!panel.hidden));
        });
        dock.querySelector('[data-filter-dock-close]')?.addEventListener('click', closePanel);
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !panel.hidden) {
                closePanel();
                toggle.focus();
            }
        });
        window.sumateraSetLanguage?.(document.documentElement.lang);
    });
</script>
