const menuButton = document.querySelector('.menu-toggle');
const navigation = document.querySelector('.main-nav');
const heroVideo = document.querySelector('.hero-video');

if (heroVideo && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    heroVideo.play().catch(() => {});
}

if (menuButton && navigation) {
    menuButton.addEventListener('click', () => {
        const isOpen = menuButton.getAttribute('aria-expanded') === 'true';
        menuButton.setAttribute('aria-expanded', String(!isOpen));
        menuButton.setAttribute('aria-label', isOpen ? 'Open navigation' : 'Close navigation');
        navigation.classList.toggle('is-open', !isOpen);
    });

    navigation.addEventListener('click', (event) => {
        if (event.target instanceof HTMLAnchorElement) {
            menuButton.setAttribute('aria-expanded', 'false');
            menuButton.setAttribute('aria-label', 'Open navigation');
            navigation.classList.remove('is-open');
        }
    });
}
