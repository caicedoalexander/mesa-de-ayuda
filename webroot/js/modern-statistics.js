/**
 * Modern Statistics JavaScript
 * Handles animations, counters, and smooth interactions
 */

(function() {
  'use strict';

  // ============================================
  // INTERSECTION OBSERVER FOR FADE-IN ANIMATIONS
  // ============================================

  /**
   * Initialize intersection observer for animated elements
   */
  function initAnimations() {
    const animatedElements = document.querySelectorAll('[data-animate]');

    if (animatedElements.length === 0) return;

    // Check if reduced motion is preferred
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (prefersReducedMotion) {
      // Skip animations if user prefers reduced motion
      animatedElements.forEach(el => el.classList.add('animated'));
      return;
    }

    // Create intersection observer
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          // Add animated class when element enters viewport
          entry.target.classList.add('animated');
          observer.unobserve(entry.target);
        }
      });
    }, {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    });

    // Observe all animated elements
    animatedElements.forEach(el => observer.observe(el));
  }

  // ============================================
  // NUMBER COUNTER ANIMATION
  // ============================================

  /**
   * Animate number from 0 to target value
   * @param {HTMLElement} element - Element containing the number
   * @param {number} target - Target number to count to
   * @param {number} duration - Animation duration in milliseconds
   */
  function animateCounter(element, target, duration = 1500) {
    const start = 0;
    const increment = target / (duration / 16); // 60fps
    let current = start;

    const timer = setInterval(() => {
      current += increment;

      if (current >= target) {
        element.textContent = Math.round(target).toLocaleString();
        clearInterval(timer);
        element.classList.remove('counting');
      } else {
        element.textContent = Math.round(current).toLocaleString();
      }
    }, 16);

    element.classList.add('counting');
  }

  /**
   * Initialize all counter animations
   */
  function initCounters() {
    const counters = document.querySelectorAll('[data-counter]');

    if (counters.length === 0) return;

    // Check if reduced motion is preferred
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const element = entry.target;
          const target = parseInt(element.getAttribute('data-target'), 10) || 0;

          if (prefersReducedMotion) {
            // Skip animation if reduced motion is preferred
            element.textContent = target.toLocaleString();
          } else {
            // Animate counter
            animateCounter(element, target);
          }

          observer.unobserve(element);
        }
      });
    }, {
      threshold: 0.5
    });

    counters.forEach(counter => observer.observe(counter));
  }

  // ============================================
  // CHART LOADING ANIMATIONS
  // ============================================

  /**
   * Initialize chart loading animations
   */
  function initChartLoaders() {
    const chartLoaders = document.querySelectorAll('[data-chart-loader]');

    chartLoaders.forEach(loader => {
      const canvas = loader.querySelector('canvas');
      const skeleton = loader.querySelector('.chart-skeleton');

      if (!canvas || !skeleton) return;

      // Check if Chart.js is loaded
      if (typeof Chart !== 'undefined') {
        // Wait for chart to be created
        const checkChart = setInterval(() => {
          const chart = Chart.getChart(canvas);

          if (chart) {
            // Chart is ready, fade in
            setTimeout(() => {
              canvas.classList.add('loaded');
              canvas.style.opacity = '1';

              if (skeleton) {
                skeleton.classList.add('hidden');
              }
            }, 300);

            clearInterval(checkChart);
          }
        }, 100);

        // Timeout after 5 seconds
        setTimeout(() => {
          clearInterval(checkChart);
          canvas.style.opacity = '1';
          if (skeleton) skeleton.classList.add('hidden');
        }, 5000);
      } else {
        // Chart.js not loaded, just show canvas
        canvas.style.opacity = '1';
        if (skeleton) skeleton.classList.add('hidden');
      }
    });
  }

  // ============================================
  // CARD HOVER EFFECTS
  // ============================================

  /**
   * Add enhanced hover effects to cards
   */
  function initCardEffects() {
    const cards = document.querySelectorAll('.modern-card');

    cards.forEach(card => {
      // Add keyboard accessibility
      if (!card.hasAttribute('tabindex') && card.classList.contains('kpi-card')) {
        card.setAttribute('tabindex', '0');
      }

      // Add ripple effect on click (optional)
      card.addEventListener('click', function(e) {
        if (this.classList.contains('kpi-card')) {
          const ripple = document.createElement('div');
          const rect = this.getBoundingClientRect();
          const size = Math.max(rect.width, rect.height);
          const x = e.clientX - rect.left - size / 2;
          const y = e.clientY - rect.top - size / 2;

          ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            border-radius: 50%;
            background: rgba(0, 168, 94, 0.3);
            top: ${y}px;
            left: ${x}px;
            pointer-events: none;
            transform: scale(0);
            animation: ripple 0.6s ease-out;
          `;

          this.style.position = 'relative';
          this.style.overflow = 'hidden';
          this.appendChild(ripple);

          setTimeout(() => ripple.remove(), 600);
        }
      });
    });

    // Add ripple animation
    const style = document.createElement('style');
    style.textContent = `
      @keyframes ripple {
        to {
          transform: scale(2);
          opacity: 0;
        }
      }
    `;
    document.head.appendChild(style);
  }

  // ============================================
  // SMOOTH SCROLL TO TOP
  // ============================================

  /**
   * Add smooth scroll to top functionality
   */
  function initSmoothScroll() {
    // This can be extended for scroll-to-top buttons if needed
    const scrollTriggers = document.querySelectorAll('[data-scroll-to]');

    scrollTriggers.forEach(trigger => {
      trigger.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('data-scroll-to'));

        if (target) {
          target.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      });
    });
  }

  // ============================================
  // REFRESH STATISTICS (Optional)
  // ============================================

  /**
   * Add refresh functionality for statistics
   */
  function initRefresh() {
    const refreshButtons = document.querySelectorAll('[data-refresh-stats]');

    refreshButtons.forEach(button => {
      button.addEventListener('click', function() {
        // Show loading state
        this.classList.add('loading');

        // Reload page or fetch new data via AJAX
        setTimeout(() => {
          window.location.reload();
        }, 300);
      });
    });
  }

  // ============================================
  // INITIALIZATION
  // ============================================

  /**
   * Initialize all features when DOM is ready
   */
  function init() {
    // Wait for DOM to be fully loaded
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
      return;
    }

    console.log('🎨 Modern Statistics: Initializing...');

    // Initialize all features
    initAnimations();
    initCounters();
    initChartLoaders();
    initCardEffects();
    initSmoothScroll();
    initRefresh();

    console.log('✅ Modern Statistics: Ready');

    // Trigger animations on page load
    setTimeout(() => {
      document.body.classList.add('statistics-loaded');
    }, 100);
  }

  // Auto-initialize
  init();

  // Export for manual initialization if needed
  window.ModernStatistics = {
    init,
    initAnimations,
    initCounters,
    initChartLoaders,
    initCardEffects
  };

})();
