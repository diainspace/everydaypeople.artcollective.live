(() => {
    const headline = document.querySelector('[data-fit-headline]');
    if (!(headline instanceof HTMLElement)) return;

    const fit = () => {
        headline.style.fontSize = '';
        const intro = headline.closest('.intro');
        if (!(intro instanceof HTMLElement)) return;
        const label = intro.querySelector('.section-number');
        const labelHeight = label instanceof HTMLElement ? label.offsetHeight : 0;
        const availableHeight = intro.clientHeight - labelHeight;
        let low = 8;
        let high = Number.parseFloat(getComputedStyle(headline).fontSize);

        for (let step = 0; step < 12; step += 1) {
            const size = (low + high) / 2;
            headline.style.fontSize = `${size}px`;
            const fits = headline.scrollWidth <= headline.clientWidth
                && headline.scrollHeight <= availableHeight;
            if (fits) low = size;
            else high = size;
        }
        headline.style.fontSize = `${low}px`;
    };

    fit();
    window.addEventListener('resize', fit, { passive: true });
})();
