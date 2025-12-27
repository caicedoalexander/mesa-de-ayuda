/**
 * Statistics Animations
 * Neumorphic design with balanced interactivity
 *
 * Features:
 * - Counter animations (count from 0)
 * - Scroll-triggered animations (fade-up)
 * - Chart loading with skeleton transitions
 * - Hover parallax effects
 * - Accessibility-aware (prefers-reduced-motion)
 */

(function() {
    'use strict';

    // Check for reduced motion preference
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ============================================
       COUNTER ANIMATIONS
       ============================================ */

    /**
     * Animate a counter from 0 to target value
     */
    function animateCounter(element, target, duration = 2000) {
        // Skip animation if user prefers reduced motion
        if (prefersReducedMotion) {
            element.textContent = formatNumber(target);
            return;
        }

        const start = 0;
        const increment = target / (duration / 16); // 60fps
        let current = start;

        const timer = setInterval(() => {
            current += increment;

            if (current >= target) {
                element.textContent = formatNumber(target);
                clearInterval(timer);
            } else {
                element.textContent = formatNumber(Math.floor(current));
            }
        }, 16);
    }

    /**
     * Format number with thousands separator
     */
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    /* ============================================
       INTERSECTION OBSERVER (SCROLL ANIMATIONS)
       ============================================ */

    const observerOptions = {
        threshold: 0.2, // 20% visible
        rootMargin: '0px 0px -100px 0px'
    };

    /**
     * Observer for scroll-triggered animations
     */
    const animateOnScroll = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const element = entry.target;

                // Animate counters
                if (element.hasAttribute('data-counter')) {
                    const target = parseInt(element.dataset.target);
                    animateCounter(element, target);
                    animateOnScroll.unobserve(element); // Only once
                }

                // Animate entrance (fade-up, fade, etc.)
                if (element.hasAttribute('data-animate-in')) {
                    const delay = parseInt(element.dataset.delay || 0);

                    // Skip delay if reduced motion
                    const actualDelay = prefersReducedMotion ? 0 : delay;

                    setTimeout(() => {
                        element.classList.add('animated');
                    }, actualDelay);

                    animateOnScroll.unobserve(element);
                }
            }
        });
    }, observerOptions);

    /* ============================================
       CHART LOADING
       ============================================ */

    /**
     * Handles chart skeleton → canvas transition
     */
    async function initChartLoading() {
        const chartWrappers = document.querySelectorAll('[data-chart-loader]');

        chartWrappers.forEach((wrapper, index) => {
            const canvas = wrapper.querySelector('canvas');
            const skeleton = wrapper.querySelector('.neuro-chart-skeleton');

            if (!canvas || !skeleton) return;

            // Stagger loading (250ms between each chart)
            const loadDelay = index * 250;

            setTimeout(async () => {
                // Simulate minimum loading time for UX (800ms)
                await delay(800);

                // Hide skeleton
                skeleton.classList.add('hidden');

                // Show canvas
                canvas.classList.add('loaded');
            }, loadDelay);
        });
    }

    /**
     * Promise-based delay
     */
    function delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /* ============================================
       HOVER PARALLAX EFFECTS
       ============================================ */

    /**
     * Add subtle parallax effect to cards on hover
     */
    function initHoverEffects() {
        // Skip if reduced motion
        if (prefersReducedMotion) return;

        const cards = document.querySelectorAll('.neuro-hover');

        cards.forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                const centerX = rect.width / 2;
                const centerY = rect.height / 2;

                // Subtle rotation (max 1deg)
                const rotateX = (y - centerY) / 20;
                const rotateY = (centerX - x) / 20;

                card.style.transform = `
                    perspective(1000px)
                    rotateX(${rotateX}deg)
                    rotateY(${rotateY}deg)
                    translateY(-6px)
                `;
            });

            card.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateY(0)';
            });
        });
    }

    /* ============================================
       TABLE ROW ANIMATIONS
       ============================================ */

    /**
     * Animate table rows progressively as they enter viewport
     */
    function animateTableRows(tableSelector) {
        const tables = document.querySelectorAll(tableSelector);

        tables.forEach(table => {
            const rows = table.querySelectorAll('tbody tr');

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const row = entry.target;
                        const index = Array.from(rows).indexOf(row);

                        // Stagger: each row with incremental delay
                        const delay = prefersReducedMotion ? 0 : index * 50;

                        setTimeout(() => {
                            row.style.opacity = '1';
                            row.style.transform = 'translateX(0)';
                        }, delay);

                        observer.unobserve(row);
                    }
                });
            }, { threshold: 0.1 });

            // Set initial state and observe
            rows.forEach(row => {
                if (!prefersReducedMotion) {
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';
                }
                row.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                observer.observe(row);
            });
        });
    }

    /* ============================================
       INITIALIZATION
       ============================================ */

    /**
     * Initialize all animations on DOM ready
     */
    function init() {
        // Counter animations
        const counters = document.querySelectorAll('[data-counter]');
        counters.forEach(counter => animateOnScroll.observe(counter));

        // Entrance animations (fade-up, etc.)
        const animatedElements = document.querySelectorAll('[data-animate-in]');
        animatedElements.forEach(el => animateOnScroll.observe(el));

        // Chart loading
        initChartLoading();

        // Hover parallax effects
        initHoverEffects();

        // Table animations
        animateTableRows('.neuro-table');
        animateTableRows('table'); // Fallback for non-neumorphic tables
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
