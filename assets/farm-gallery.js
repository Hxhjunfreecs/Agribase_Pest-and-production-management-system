(() => {
    document.querySelectorAll('.farm-gallery').forEach(gallery => {
        const slides = JSON.parse(gallery.dataset.slides || '[]');
        if (slides.length < 2) return;

        const image = gallery.querySelector('.farm-gallery-image');
        const credit = gallery.querySelector('.farm-gallery-credit a');
        const title = gallery.querySelector('[data-gallery-title]');
        const text = gallery.querySelector('[data-gallery-text]');
        const tips = gallery.querySelector('[data-gallery-tips]');
        const current = gallery.querySelector('[data-gallery-current]');
        const dots = [...gallery.querySelectorAll('[data-gallery-dot]')];
        const toggle = gallery.querySelector('[data-gallery-toggle]');
        let index = 0;
        let paused = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function showSlide(nextIndex) {
            index = (nextIndex + slides.length) % slides.length;
            const slide = slides[index];

            image.src = slide.image;
            image.alt = slide.alt;
            title.textContent = slide.title;
            text.textContent = slide.text;
            current.textContent = String(index + 1).padStart(2, '0');
            credit.href = slide.source;
            credit.textContent = slide.credit;
            tips.replaceChildren(...slide.tips.map(tip => {
                const item = document.createElement('li');
                item.textContent = tip;
                return item;
            }));
            dots.forEach((dot, dotIndex) => {
                dot.setAttribute('aria-pressed', String(dotIndex === index));
            });
        }

        function updateToggle() {
            toggle.setAttribute('aria-label', paused ? 'Resume slideshow' : 'Pause slideshow');
            toggle.title = paused ? 'Resume slideshow' : 'Pause slideshow';
            toggle.querySelector('span').innerHTML = paused ? '&#x25B6;' : '&#x23F8;';
        }

        gallery.querySelector('[data-gallery-prev]').addEventListener('click', () => showSlide(index - 1));
        gallery.querySelector('[data-gallery-next]').addEventListener('click', () => showSlide(index + 1));
        dots.forEach(dot => dot.addEventListener('click', () => showSlide(Number(dot.dataset.galleryDot))));
        toggle.addEventListener('click', () => {
            paused = !paused;
            updateToggle();
        });
        gallery.addEventListener('keydown', event => {
            if (event.key === 'ArrowLeft') showSlide(index - 1);
            if (event.key === 'ArrowRight') showSlide(index + 1);
        });
        updateToggle();
        window.setInterval(() => {
            if (!paused) showSlide(index + 1);
        }, 7000);
    });
})();
