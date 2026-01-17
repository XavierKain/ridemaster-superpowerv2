/* ============================================
   RideMaster Main JavaScript
   Interactive functionality for the design prototype
   ============================================ */

(function() {
  'use strict';

  /* ----------------------------------------
     DOM Ready Helper
     ---------------------------------------- */
  function ready(fn) {
    if (document.readyState !== 'loading') {
      fn();
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  /* ----------------------------------------
     Mobile Menu Toggle
     ---------------------------------------- */
  function initMobileMenu() {
    const toggle = document.querySelector('.header__mobile-toggle');
    const menu = document.querySelector('.mobile-menu');
    const backdrop = document.querySelector('.mobile-menu-backdrop');
    const close = document.querySelector('.mobile-menu__close');

    if (!toggle || !menu) return;

    function openMenu() {
      menu.classList.add('mobile-menu--open');
      if (backdrop) backdrop.classList.add('mobile-menu-backdrop--visible');
      document.body.style.overflow = 'hidden';
    }

    function closeMenu() {
      menu.classList.remove('mobile-menu--open');
      if (backdrop) backdrop.classList.remove('mobile-menu-backdrop--visible');
      document.body.style.overflow = '';
    }

    toggle.addEventListener('click', openMenu);
    if (close) close.addEventListener('click', closeMenu);
    if (backdrop) backdrop.addEventListener('click', closeMenu);

    // Close on escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && menu.classList.contains('mobile-menu--open')) {
        closeMenu();
      }
    });
  }

  /* ----------------------------------------
     Dropdown Menus
     ---------------------------------------- */
  function initDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');

    dropdowns.forEach(function(dropdown) {
      const trigger = dropdown.querySelector('.dropdown__trigger');

      if (!trigger) return;

      trigger.addEventListener('click', function(e) {
        e.stopPropagation();

        // Close other dropdowns
        dropdowns.forEach(function(other) {
          if (other !== dropdown) {
            other.classList.remove('dropdown--open');
          }
        });

        dropdown.classList.toggle('dropdown--open');
      });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
      dropdowns.forEach(function(dropdown) {
        dropdown.classList.remove('dropdown--open');
      });
    });

    // Close on escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        dropdowns.forEach(function(dropdown) {
          dropdown.classList.remove('dropdown--open');
        });
      }
    });
  }

  /* ----------------------------------------
     Favorite Button Toggle
     ---------------------------------------- */
  function initFavoriteButtons() {
    const favoriteButtons = document.querySelectorAll('.camp-card__favorite');

    favoriteButtons.forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        btn.classList.toggle('camp-card__favorite--active');

        // Optional: Add animation
        btn.style.transform = 'scale(1.2)';
        setTimeout(function() {
          btn.style.transform = '';
        }, 150);
      });
    });
  }

  /* ----------------------------------------
     Tabs Switching
     ---------------------------------------- */
  function initTabs() {
    const tabContainers = document.querySelectorAll('.tabs');

    tabContainers.forEach(function(container) {
      const tabs = container.querySelectorAll('.tabs__tab');
      const panels = container.parentElement.querySelectorAll('.tabs__panel');

      tabs.forEach(function(tab, index) {
        tab.addEventListener('click', function() {
          // Remove active class from all tabs
          tabs.forEach(function(t) {
            t.classList.remove('tabs__tab--active');
          });

          // Hide all panels
          panels.forEach(function(p) {
            p.classList.remove('tabs__panel--active');
          });

          // Activate clicked tab and corresponding panel
          tab.classList.add('tabs__tab--active');
          if (panels[index]) {
            panels[index].classList.add('tabs__panel--active');
          }
        });
      });
    });
  }

  /* ----------------------------------------
     Counter (Plus/Minus)
     ---------------------------------------- */
  function initCounters() {
    const counters = document.querySelectorAll('.counter');

    counters.forEach(function(counter) {
      const minusBtn = counter.querySelector('.counter__btn--minus');
      const plusBtn = counter.querySelector('.counter__btn--plus');
      const valueEl = counter.querySelector('.counter__value');

      if (!minusBtn || !plusBtn || !valueEl) return;

      const min = parseInt(counter.dataset.min) || 0;
      const max = parseInt(counter.dataset.max) || 99;

      function updateValue(newValue) {
        const value = Math.max(min, Math.min(max, newValue));
        valueEl.textContent = value;

        // Update button states
        minusBtn.disabled = value <= min;
        plusBtn.disabled = value >= max;
      }

      minusBtn.addEventListener('click', function() {
        const currentValue = parseInt(valueEl.textContent) || 0;
        updateValue(currentValue - 1);
      });

      plusBtn.addEventListener('click', function() {
        const currentValue = parseInt(valueEl.textContent) || 0;
        updateValue(currentValue + 1);
      });
    });
  }

  /* ----------------------------------------
     Modal Open/Close
     ---------------------------------------- */
  function initModals() {
    const modalTriggers = document.querySelectorAll('[data-modal]');
    const modals = document.querySelectorAll('.modal');
    const backdrops = document.querySelectorAll('.modal-backdrop');

    function openModal(modalId) {
      const modal = document.getElementById(modalId);
      const backdrop = document.querySelector('.modal-backdrop');

      if (modal) {
        modal.classList.add('modal--visible');
      }
      if (backdrop) {
        backdrop.classList.add('modal-backdrop--visible');
      }
      document.body.style.overflow = 'hidden';
    }

    function closeAllModals() {
      modals.forEach(function(modal) {
        modal.classList.remove('modal--visible');
      });
      backdrops.forEach(function(backdrop) {
        backdrop.classList.remove('modal-backdrop--visible');
      });
      document.body.style.overflow = '';
    }

    // Trigger buttons
    modalTriggers.forEach(function(trigger) {
      trigger.addEventListener('click', function() {
        const modalId = trigger.dataset.modal;
        openModal(modalId);
      });
    });

    // Close buttons
    document.querySelectorAll('.modal__close').forEach(function(closeBtn) {
      closeBtn.addEventListener('click', closeAllModals);
    });

    // Backdrop click
    backdrops.forEach(function(backdrop) {
      backdrop.addEventListener('click', closeAllModals);
    });

    // Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeAllModals();
      }
    });
  }

  /* ----------------------------------------
     Testimonial Slider (Auto-rotate)
     ---------------------------------------- */
  function initTestimonialSlider() {
    const carousel = document.querySelector('.testimonials__carousel');
    if (!carousel) return;

    const track = carousel.querySelector('.testimonials__track');
    const slides = carousel.querySelectorAll('.testimonials__slide');
    const dots = carousel.querySelectorAll('.testimonials__dot');

    if (!track || slides.length === 0) return;

    let currentIndex = 0;
    let interval;
    const autoPlayDelay = 5000;

    function goToSlide(index) {
      // Handle wrap-around
      if (index < 0) index = slides.length - 1;
      if (index >= slides.length) index = 0;

      currentIndex = index;

      // Calculate slide width based on viewport
      let slidesPerView = 1;
      if (window.innerWidth >= 1024) {
        slidesPerView = 3;
      } else if (window.innerWidth >= 768) {
        slidesPerView = 2;
      }

      // Only slide if we have more slides than visible
      if (slides.length > slidesPerView) {
        const maxIndex = slides.length - slidesPerView;
        const adjustedIndex = Math.min(currentIndex, maxIndex);
        const offset = -(adjustedIndex * (100 / slidesPerView));
        track.style.transform = 'translateX(' + offset + '%)';
      }

      // Update dots
      dots.forEach(function(dot, i) {
        dot.classList.toggle('testimonials__dot--active', i === currentIndex);
      });
    }

    function nextSlide() {
      goToSlide(currentIndex + 1);
    }

    function startAutoPlay() {
      interval = setInterval(nextSlide, autoPlayDelay);
    }

    function stopAutoPlay() {
      clearInterval(interval);
    }

    // Initialize
    goToSlide(0);
    startAutoPlay();

    // Pause on hover
    carousel.addEventListener('mouseenter', stopAutoPlay);
    carousel.addEventListener('mouseleave', startAutoPlay);

    // Dot navigation
    dots.forEach(function(dot, index) {
      dot.addEventListener('click', function() {
        goToSlide(index);
        stopAutoPlay();
        startAutoPlay();
      });
    });

    // Handle resize
    window.addEventListener('resize', function() {
      goToSlide(currentIndex);
    });
  }

  /* ----------------------------------------
     Filter Chips
     ---------------------------------------- */
  function initFilterChips() {
    const filterChips = document.querySelectorAll('.filter-chip');

    filterChips.forEach(function(chip) {
      chip.addEventListener('click', function() {
        // Toggle active state
        chip.classList.toggle('filter-chip--active');
      });

      // Remove button within chip
      const removeBtn = chip.querySelector('.filter-chip__remove');
      if (removeBtn) {
        removeBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          chip.classList.remove('filter-chip--active');
          // Optionally remove the chip entirely
          // chip.remove();
        });
      }
    });
  }

  /* ----------------------------------------
     Header Scroll Effect
     ---------------------------------------- */
  function initHeaderScroll() {
    const header = document.querySelector('.header');
    if (!header) return;

    let lastScroll = 0;

    window.addEventListener('scroll', function() {
      const currentScroll = window.pageYOffset;

      if (currentScroll > 50) {
        header.classList.add('header--scrolled');
      } else {
        header.classList.remove('header--scrolled');
      }

      lastScroll = currentScroll;
    });
  }

  /* ----------------------------------------
     Smooth Scroll for Anchor Links
     ---------------------------------------- */
  function initSmoothScroll() {
    const links = document.querySelectorAll('a[href^="#"]');

    links.forEach(function(link) {
      link.addEventListener('click', function(e) {
        const href = link.getAttribute('href');
        if (href === '#') return;

        const target = document.querySelector(href);
        if (target) {
          e.preventDefault();
          const headerHeight = document.querySelector('.header')?.offsetHeight || 0;
          const targetPosition = target.offsetTop - headerHeight - 20;

          window.scrollTo({
            top: targetPosition,
            behavior: 'smooth'
          });
        }
      });
    });
  }

  /* ----------------------------------------
     Form Validation Helpers
     ---------------------------------------- */
  function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');

    forms.forEach(function(form) {
      form.addEventListener('submit', function(e) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(function(field) {
          // Remove previous error state
          field.classList.remove('form-input--error');
          const errorMsg = field.parentElement.querySelector('.form-error');
          if (errorMsg) errorMsg.remove();

          if (!field.value.trim()) {
            isValid = false;
            field.classList.add('form-input--error');

            // Add error message
            const error = document.createElement('span');
            error.className = 'form-error';
            error.textContent = 'This field is required';
            field.parentElement.appendChild(error);
          }
        });

        if (!isValid) {
          e.preventDefault();
        }
      });

      // Clear error on input
      form.querySelectorAll('.form-input, .form-textarea, .form-select').forEach(function(field) {
        field.addEventListener('input', function() {
          field.classList.remove('form-input--error');
          const errorMsg = field.parentElement.querySelector('.form-error');
          if (errorMsg) errorMsg.remove();
        });
      });
    });
  }

  /* ----------------------------------------
     Toast Notifications
     ---------------------------------------- */
  window.showToast = function(message, type) {
    type = type || 'info';

    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'toast alert alert--' + type;
    toast.innerHTML = '<div class="alert__content">' + message + '</div>' +
                     '<button class="alert__close">' +
                     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                     '<path d="M18 6L6 18M6 6l12 12"/>' +
                     '</svg></button>';

    container.appendChild(toast);

    // Close button
    toast.querySelector('.alert__close').addEventListener('click', function() {
      toast.remove();
    });

    // Auto remove after 5 seconds
    setTimeout(function() {
      if (toast.parentElement) {
        toast.remove();
      }
    }, 5000);
  };

  /* ----------------------------------------
     Image Gallery Modal (for Camp Detail)
     ---------------------------------------- */
  function initGalleryModal() {
    const galleryMore = document.querySelector('.camp-gallery__more');
    if (!galleryMore) return;

    galleryMore.addEventListener('click', function() {
      // In a real implementation, this would open a gallery modal
      console.log('Gallery modal would open here');
    });
  }

  /* ----------------------------------------
     Booking Date Picker Placeholder
     ---------------------------------------- */
  function initDatePickers() {
    const dateSelectors = document.querySelectorAll('.booking-card__selector');

    dateSelectors.forEach(function(selector) {
      selector.addEventListener('click', function() {
        // In a real implementation, this would open a date picker
        console.log('Date picker would open here');
      });
    });
  }

  /* ----------------------------------------
     Search Bar Enhancement
     ---------------------------------------- */
  function initSearchBar() {
    const searchInputs = document.querySelectorAll('.search-bar__input');

    searchInputs.forEach(function(input) {
      // Clear placeholder on focus
      input.addEventListener('focus', function() {
        input.dataset.placeholder = input.placeholder;
        input.placeholder = '';
      });

      input.addEventListener('blur', function() {
        if (input.dataset.placeholder) {
          input.placeholder = input.dataset.placeholder;
        }
      });
    });
  }

  /* ----------------------------------------
     Initialize All Modules
     ---------------------------------------- */
  ready(function() {
    initMobileMenu();
    initDropdowns();
    initFavoriteButtons();
    initTabs();
    initCounters();
    initModals();
    initTestimonialSlider();
    initFilterChips();
    initHeaderScroll();
    initSmoothScroll();
    initFormValidation();
    initGalleryModal();
    initDatePickers();
    initSearchBar();

    console.log('RideMaster: All modules initialized');
  });

})();
