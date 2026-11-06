const menuButton = document.querySelector('.menu-toggle');
const navigation = document.querySelector('.main-nav');
const heroVideo = document.querySelector('.hero-video');
const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

if (heroVideo && !prefersReducedMotion.matches) {
    heroVideo.play().catch(() => {});
}

if (!prefersReducedMotion.matches && 'IntersectionObserver' in window) {
    const revealGroups = [
        '.intro > *, .intro h2, .intro-content p, .intro .underlined-link, .intro-stats > *',
        '.spaces-section .section-topline, .spaces-section .section-heading, .space-card, .space-card .space-photo, .space-info h3, .space-info p, .space-info > a',
        '.space-tag, .visual-caption, .membership-section > .section-kicker, .membership-section .membership-heading, .plan-card, .plan-name, .plan-description, .plan-price, .plan-link, .includes-label, .plan-card li, .membership-section > .plan-note',
        '.journey-heading, .journey-steps article, .journey-steps article > span, .journey-steps h3, .journey-steps p',
        '.booking-copy, .booking-copy h2, .booking-copy > p, .booking-aside, .booking-form, .booking-form h3, .booking-form .form-intro, .booking-form label, .booking-form .submit-button, .booking-form .privacy-note',
        '.site-footer > *',
    ];
    const revealElements = revealGroups.flatMap((selector) => [...document.querySelectorAll(selector)]);
    const parentIndexes = new Map();

    revealElements.forEach((element) => {
        const index = parentIndexes.get(element.parentElement) ?? 0;
        parentIndexes.set(element.parentElement, index + 1);
        element.style.setProperty('--reveal-delay', `${Math.min(index * 90, 270)}ms`);
        element.classList.add('scroll-reveal');
    });

    document.documentElement.classList.add('motion-ready');
    const revealObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.14, rootMargin: '0px 0px -36px 0px' });

    revealElements.forEach((element) => revealObserver.observe(element));
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

const lightbox = document.querySelector('.image-lightbox');
const lightboxImage = document.querySelector('.lightbox-image');
const lightboxCaption = document.querySelector('.lightbox-caption');
const galleryImages = [...document.querySelectorAll('.space-photo')];
let activeImageIndex = 0;

if (lightbox instanceof HTMLDialogElement && lightboxImage && lightboxCaption && galleryImages.length) {
    const showImage = (index) => {
        activeImageIndex = (index + galleryImages.length) % galleryImages.length;
        const source = galleryImages[activeImageIndex];
        const card = source.closest('.space-card');
        lightboxImage.src = source.currentSrc || source.src;
        lightboxImage.alt = source.alt;
        lightboxCaption.textContent = card?.querySelector('h3')?.textContent?.trim() || source.alt;
    };

    document.querySelectorAll('.photo-trigger').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            showImage(Number(trigger.dataset.imageIndex) || 0);
            lightbox.showModal();
        });
    });

    document.querySelector('.lightbox-close')?.addEventListener('click', () => lightbox.close());
    document.querySelector('.lightbox-previous')?.addEventListener('click', () => showImage(activeImageIndex - 1));
    document.querySelector('.lightbox-next')?.addEventListener('click', () => showImage(activeImageIndex + 1));

    lightbox.addEventListener('click', (event) => {
        if (event.target === lightbox) lightbox.close();
    });

    lightbox.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') showImage(activeImageIndex - 1);
        if (event.key === 'ArrowRight') showImage(activeImageIndex + 1);
    });

    let swipeStartX = null;
    lightbox.addEventListener('touchstart', (event) => {
        swipeStartX = event.changedTouches[0]?.clientX ?? null;
    }, { passive: true });
    lightbox.addEventListener('touchend', (event) => {
        const endX = event.changedTouches[0]?.clientX;
        if (swipeStartX === null || endX === undefined) return;
        const distance = endX - swipeStartX;
        if (Math.abs(distance) > 55) showImage(activeImageIndex + (distance > 0 ? -1 : 1));
        swipeStartX = null;
    }, { passive: true });
}
