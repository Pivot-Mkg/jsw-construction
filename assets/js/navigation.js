/**
 * Navigation Module
 * Handles mobile menu toggle and link closing behaviors
 * Ensures only one dropdown is open at a time (accordion behavior)
 */

(function () {
  "use strict";

  // Initialize navigation when DOM is ready
  function initNavigation() {
    const menuBtn = document.getElementById("menu-btn");
    const mobileMenu = document.getElementById("mobile-menu");

    if (!menuBtn || !mobileMenu) return;

    let isMenuOpen = false;

    // Toggle mobile menu on button click
    menuBtn.addEventListener("click", () => {
      isMenuOpen = !isMenuOpen;
      if (isMenuOpen) {
        mobileMenu.classList.add("open");
        menuBtn.innerHTML = '<i data-lucide="x" width="24" height="24"></i>';
      } else {
        mobileMenu.classList.remove("open");
        menuBtn.innerHTML = '<i data-lucide="menu" width="24" height="24"></i>';
      }
      if (window.lucide) lucide.createIcons();
    });

    // Accordion behavior: only one dropdown open at a time
    const dropdowns = mobileMenu.querySelectorAll(".mobile-dropdown");
    dropdowns.forEach((dropdown) => {
      const details = dropdown.querySelector("details");
      if (!details) return;

      details.addEventListener("toggle", () => {
        if (details.open) {
          // Close all other dropdowns
          dropdowns.forEach((otherDropdown) => {
            const otherDetails = otherDropdown.querySelector("details");
            if (otherDetails && otherDetails !== details) {
              otherDetails.open = false;
            }
          });
        }
      });
    });

    // Close mobile menu when clicking a mobile link (but not summary)
    mobileMenu.addEventListener("click", (e) => {
      const target = e.target.closest("a");
      if (!target) return;

      // Only close menu if it's a direct link (not a dropdown summary)
      mobileMenu.classList.remove("open");
      isMenuOpen = false;
      menuBtn.innerHTML = '<i data-lucide="menu" width="24" height="24"></i>';
      if (window.lucide) lucide.createIcons();
    });
  }

  // Run on document ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initNavigation);
  } else {
    initNavigation();
  }

  // Expose for external access if needed
  window.navigationModule = { initNavigation };
})();

// Lenis smooth scrolling fallback/init outside the IIFE in case navigation module
// isn't responsible for page-level scripts. If `Lenis` is available, initialize
// it and start the RAF loop. This will provide smooth scrolling across pages.
(function () {
  if (typeof Lenis === 'undefined') return;

  try {
    const lenis = new Lenis({
      duration: 1.2,
      smooth: true,
      // easing: t => t // default; keep default for predictable motion
    });

    function raf(time) {
      lenis.raf(time);
      requestAnimationFrame(raf);
    }

    requestAnimationFrame(raf);

    // expose for debugging if needed
    window.lenis = lenis;
  } catch (e) {
    // fail silently if Lenis can't initialize
    console.warn('Lenis init failed:', e);
  }
})();
