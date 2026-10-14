<div class="seeker-modal-backdrop" data-seeker-modal-backdrop aria-hidden="true">
    <section class="seeker-modal" role="dialog" aria-modal="true" aria-labelledby="seeker-modal-title" tabindex="-1">
        <button type="button" class="seeker-modal-close" data-close-seeker-modal aria-label="Close seeker details">&times;</button>
        <header class="seeker-modal-header">
            <h2 class="seeker-modal-title" id="seeker-modal-title">Seeker Details</h2>
            <p class="seeker-modal-subtitle">Full profile summary for the selected seeker.</p>
        </header>

        <div class="seeker-modal-grid">
            <div class="seeker-detail-card full-span">
                <span class="seeker-detail-label">Name</span>
                <span class="seeker-detail-value" data-seeker-detail-name>N/A</span>
            </div>
            <div class="seeker-detail-card">
                <span class="seeker-detail-label">Contact Number</span>
                <span class="seeker-detail-value" data-seeker-detail-contact>N/A</span>
            </div>
            <div class="seeker-detail-card">
                <span class="seeker-detail-label">Email</span>
                <span class="seeker-detail-value" data-seeker-detail-email>N/A</span>
            </div>
            <div class="seeker-detail-card">
                <span class="seeker-detail-label">Facebook</span>
                <span class="seeker-detail-value" data-seeker-detail-facebook>N/A</span>
            </div>
            <div class="seeker-detail-card">
                <span class="seeker-detail-label">Age</span>
                <span class="seeker-detail-value" data-seeker-detail-age>N/A</span>
            </div>
            <div class="seeker-detail-card">
                <span class="seeker-detail-label">Gender</span>
                <span class="seeker-detail-value" data-seeker-detail-gender>N/A</span>
            </div>
            <div class="seeker-detail-card full-span">
                <span class="seeker-detail-label">Campus/School</span>
                <span class="seeker-detail-value" data-seeker-detail-school>N/A</span>
            </div>
        </div>
    </section>
</div>

<script>
(() => {
    const backdrop = document.querySelector('[data-seeker-modal-backdrop]');
    const modal = backdrop?.querySelector('[role="dialog"]');
    const closeButton = backdrop?.querySelector('[data-close-seeker-modal]');
    const modalTitle = backdrop?.querySelector('#seeker-modal-title');
    const fields = {
        name: backdrop?.querySelector('[data-seeker-detail-name]'),
        contact: backdrop?.querySelector('[data-seeker-detail-contact]'),
        email: backdrop?.querySelector('[data-seeker-detail-email]'),
        facebook: backdrop?.querySelector('[data-seeker-detail-facebook]'),
        age: backdrop?.querySelector('[data-seeker-detail-age]'),
        gender: backdrop?.querySelector('[data-seeker-detail-gender]'),
        school: backdrop?.querySelector('[data-seeker-detail-school]'),
    };
    let activeTrigger = null;
    let previousBodyOverflow = '';

    const valueOrNA = (value) => String(value ?? '').trim() || 'N/A';

    const setText = (node, value) => {
        node.textContent = valueOrNA(value);
    };

    const setFacebook = (value) => {
        const facebook = valueOrNA(value);
        fields.facebook.replaceChildren();

        if (facebook === 'N/A') {
            fields.facebook.textContent = facebook;
            return;
        }

        try {
            const url = new URL(facebook);
            if (url.protocol === 'http:' || url.protocol === 'https:') {
                const link = document.createElement('a');
                link.href = url.href;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = facebook;
                fields.facebook.appendChild(link);
                return;
            }
        } catch (error) {
        }

        fields.facebook.textContent = facebook;
    };

    const fillModal = (row) => {
        const name = valueOrNA(row.dataset.seekerName);
        modalTitle.textContent = `${name} Details`;
        setText(fields.name, row.dataset.seekerName);
        setText(fields.contact, row.dataset.seekerContact);
        setText(fields.email, row.dataset.seekerEmail);
        setFacebook(row.dataset.seekerFacebook);
        setText(fields.age, row.dataset.seekerAge);
        setText(fields.gender, row.dataset.seekerGender);
        setText(fields.school, row.dataset.seekerSchool);
    };

    const focusableElements = () => Array.from(modal.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )).filter((element) => element.getClientRects().length > 0);

    const openModal = (button) => {
        const row = button.closest('[data-seeker-modal-row]');
        if (!row || !backdrop || !modal) return;

        activeTrigger = button;
        fillModal(row);
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        backdrop.classList.add('open');
        backdrop.setAttribute('aria-hidden', 'false');
        closeButton.focus();
    };

    const closeModal = () => {
        if (!backdrop?.classList.contains('open')) return;

        backdrop.classList.remove('open');
        backdrop.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = previousBodyOverflow;
        if (activeTrigger?.isConnected) activeTrigger.focus();
        activeTrigger = null;
    };

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        const trigger = event.target.closest('[data-open-seeker-modal]');
        if (trigger) openModal(trigger);
    });

    closeButton?.addEventListener('click', closeModal);
    backdrop?.addEventListener('click', (event) => {
        if (event.target === backdrop) closeModal();
    });

    document.addEventListener('keydown', (event) => {
        if (!backdrop?.classList.contains('open')) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal();
            return;
        }

        if (event.key !== 'Tab') return;
        const focusable = focusableElements();
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (!first || !last) {
            event.preventDefault();
            modal.focus();
        } else if (event.shiftKey && (document.activeElement === first || !modal.contains(document.activeElement))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !modal.contains(document.activeElement))) {
            event.preventDefault();
            first.focus();
        }
    });
})();
</script>