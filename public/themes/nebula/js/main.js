// Nebula Theme - Minimal animations

// Simple floating particles effect using CSS animations only
// The starfield is handled by CSS gradients

document.addEventListener('DOMContentLoaded', () => {
    // Initialize AOS with slight delay for smoother entry
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 900,
            once: true,
            offset: 80,
        });
    }

    // Add subtle mouse-follow glow effect on hero
    const hero = document.querySelector('.hero-section');
    if (hero) {
        hero.addEventListener('mousemove', (e) => {
            const rect = hero.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            hero.style.setProperty('--mouse-x', x + 'px');
            hero.style.setProperty('--mouse-y', y + 'px');
        });
    }
});
