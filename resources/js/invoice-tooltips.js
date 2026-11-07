// Render outside the scroll container so even a single invoice can show its tooltip.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-invoice-tooltips]').forEach(container => {
        const tooltip = document.createElement('div');
        tooltip.className = 'invoice-table-tooltip';
        tooltip.id = 'invoice-table-tooltip-' + document.querySelectorAll('.invoice-table-tooltip').length;
        tooltip.role = 'tooltip';
        tooltip.hidden = true;
        document.body.append(tooltip);

        let activeTrigger;
        let originalDescription;

        const hide = () => {
            tooltip.hidden = true;
            if (activeTrigger) {
                if (originalDescription === null) {
                    activeTrigger.removeAttribute('aria-describedby');
                } else {
                    activeTrigger.setAttribute('aria-describedby', originalDescription);
                }
            }
            activeTrigger = null;
        };

        const show = event => {
            const trigger = event.target.closest('[data-tip]');
            if (!trigger || !container.contains(trigger) || !trigger.dataset.tip.trim()) return;
            // Preserve the existing desktop-only tooltips for disabled actions.
            if (!trigger.classList.contains('tooltip') && !window.matchMedia('(min-width: 1024px)').matches) return;
            if (trigger === activeTrigger) return;

            hide();
            activeTrigger = trigger;
            originalDescription = trigger.getAttribute('aria-describedby');
            trigger.setAttribute('aria-describedby', [originalDescription, tooltip.id].filter(Boolean).join(' '));
            tooltip.textContent = trigger.dataset.tip;
            tooltip.dir = getComputedStyle(trigger).direction;
            tooltip.hidden = false;

            const rect = trigger.getBoundingClientRect();
            const gap = 8;
            const width = tooltip.offsetWidth;
            const height = tooltip.offsetHeight;
            const left = Math.max(gap, Math.min(rect.left + (rect.width - width) / 2, window.innerWidth - width - gap));
            const top = rect.top >= height + gap * 2 ? rect.top - height - gap : rect.bottom + gap;
            tooltip.style.left = `${left}px`;
            tooltip.style.top = `${Math.max(gap, Math.min(top, window.innerHeight - height - gap))}px`;
        };

        container.addEventListener('pointerover', show);
        container.addEventListener('focusin', show);
        ['pointerout', 'focusout'].forEach(type => container.addEventListener(type, event => {
            if (activeTrigger && !activeTrigger.contains(event.relatedTarget)) hide();
        }));
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') hide();
        });
        document.addEventListener('scroll', hide, true);
        window.addEventListener('resize', hide);
        // Suppress the clipped CSS tooltips only after the floating one is ready.
        container.classList.add('invoice-tooltips-ready');
    });
});
