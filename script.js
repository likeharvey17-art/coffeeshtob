document.addEventListener('DOMContentLoaded', () => {
  const header = document.querySelector('.site-header');
  const toTopBtn = document.getElementById('toTop');
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  /* This file is shared by index.html and the legal pages, which carry
     different subsets of the markup. Anything that isn't guaranteed on every
     page is either guarded at its use site or bailed on here — an unguarded
     null reference throws on DOMContentLoaded and silently takes every other
     behaviour in this file down with it. */
  if (!header) return;

  /* Publish the sticky header's height so `scroll-padding-top` can keep anchor
     targets clear of it. Measured rather than hardcoded, since the header's
     height differs between the desktop and mobile layouts. */
  const syncHeaderHeight = () => {
    document.documentElement.style.setProperty(
      '--header-h',
      `${Math.round(header.getBoundingClientRect().height)}px`
    );
  };
  syncHeaderHeight();
  if ('ResizeObserver' in window) {
    new ResizeObserver(syncHeaderHeight).observe(header);
  } else {
    window.addEventListener('resize', syncHeaderHeight);
  }

  /* Two header panels: the ☰ nav dropdown (mobile only) and the Соцсети
     popover. Opening one closes the other, and both close on Escape, on an
     outside click, and after a link inside them is followed. */
  const socialBtn = document.getElementById('socialBtn');
  const socialPop = document.getElementById('socialPop');
  const navToggle = document.getElementById('navToggle');
  const mainNav = document.getElementById('main-nav');

  const setSocialOpen = (open) => {
    if (!socialBtn || !socialPop) return;
    socialPop.hidden = !open;
    socialBtn.setAttribute('aria-expanded', String(open));
  };

  const setNavOpen = (open) => {
    if (!navToggle || !mainNav) return;
    navToggle.classList.toggle('is-open', open);
    navToggle.setAttribute('aria-expanded', String(open));
    mainNav.classList.toggle('is-open', open);
  };

  const isSocialOpen = () => socialPop && !socialPop.hidden;
  const isNavOpen = () => mainNav && mainNav.classList.contains('is-open');

  /* Two things to get right when a header panel opens.

     focus() scrolls its target into view, and `scroll-padding-top` reserves a
     header-height strip at the top of the viewport — so focusing a link inside
     a panel made the page creep upwards on every open. preventScroll fixes it.

     And focus is only moved for *keyboard* activation. A pointer click that
     programmatically focuses a link makes the browser paint its :focus-visible
     ring, so opening «Соцсети» with the mouse drew a box around the first item.
     `event.detail === 0` is the standard tell for a click synthesised from
     Enter/Space rather than a real pointer press. Mouse users lose nothing —
     each panel sits immediately after its trigger in the DOM, so Tab still
     walks straight into it. */
  const cameFromKeyboard = (event) => event.detail === 0;
  if (socialBtn && socialPop) {
    /* The trigger stays an <a href="#social"> so that without JS it still
       falls back to jumping to the footer's social column. */
    socialBtn.addEventListener('click', (event) => {
      event.preventDefault();
      const willOpen = !isSocialOpen();
      setNavOpen(false);
      setSocialOpen(willOpen);
      if (willOpen && cameFromKeyboard(event)) {
        socialPop.querySelector('a')?.focus({ preventScroll: true });
      }
    });

    socialPop.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => setSocialOpen(false));
    });
  }

  if (navToggle && mainNav) {
    navToggle.addEventListener('click', (event) => {
      event.preventDefault();
      const willOpen = !isNavOpen();
      setSocialOpen(false);
      setNavOpen(willOpen);
      if (willOpen && cameFromKeyboard(event)) {
        mainNav.querySelector('a')?.focus({ preventScroll: true });
      }
    });

    /* Tapping a section link should close the menu behind it. */
    mainNav.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => setNavOpen(false));
    });

    /* Leaving the mobile layout must not strand the dropdown open. */
    window.matchMedia('(min-width: 861px)').addEventListener('change', (e) => {
      if (e.matches) setNavOpen(false);
    });
  }

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    if (isSocialOpen()) {
      setSocialOpen(false);
      socialBtn.focus({ preventScroll: true });
    }
    if (isNavOpen()) {
      setNavOpen(false);
      navToggle.focus({ preventScroll: true });
    }
  });

  document.addEventListener('click', (event) => {
    if (isSocialOpen() && !socialPop.contains(event.target) && !socialBtn.contains(event.target)) {
      setSocialOpen(false);
    }
    if (isNavOpen() && !mainNav.contains(event.target) && !navToggle.contains(event.target)) {
      setNavOpen(false);
    }
  });

  /* Header shadow, back-to-top visibility, and hide-on-scroll-down.
     The header slides away while reading downwards and comes straight back on
     any upward scroll. Near the top it is always shown, so the resting state
     above the hero is unchanged. */
  /* How far down the page the header stays pinned before it may hide.
     On desktop it holds through the top of the hero. On mobile that reads as
     lag — the header sits there while the page moves under it — so it lets go
     almost immediately and travels away with the content instead. Both keep it
     visible at rest above the hero. */
  const desktop = window.matchMedia('(min-width: 861px)');
  const hideAfter = () => (desktop.matches ? 220 : 12);
  const DELTA = 6;        // ignore sub-pixel jitter and momentum wobble
  let lastY = window.scrollY;
  let holdVisibleUntil = 0; // timestamp; see the anchor-click handler below

  const setHeaderHidden = (hidden) => {
    header.classList.toggle('is-hidden', hidden);
  };

  const onScroll = () => {
    const y = window.scrollY;
    header.classList.toggle('is-scrolled', y > 12);

    const movement = y - lastY;
    if (Math.abs(movement) < DELTA) return; // too small to count as a direction

    const scrollingUp = movement < 0;

    /* Back-to-top rides the same gesture as the header: it appears only once
       the reader starts heading back up, and only far enough down the page to
       be worth offering. Scrolling down again puts it away. */
    if (toTopBtn) toTopBtn.classList.toggle('is-visible', scrollingUp && y > 480);

    if (Date.now() < holdVisibleUntil) {
      /* Mid-jump from an in-page link: that travel is downward, but the reader
         didn't scroll — don't treat it as a hide gesture. */
      setHeaderHidden(false);
      lastY = y;
      return;
    }

    if (y <= hideAfter()) {
      /* At and near the top the header always rests in place. */
      setHeaderHidden(false);
    } else if (isNavOpen() || isSocialOpen()) {
      /* A panel is open — hiding the header would yank it out from under the
         reader mid-interaction. */
      setHeaderHidden(false);
    } else {
      setHeaderHidden(!scrollingUp);
    }
    lastY = y;
  };

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* Opening either panel must bring the header back into view first. */
  [navToggle, socialBtn].forEach((el) => {
    el && el.addEventListener('click', () => setHeaderHidden(false));
  });

  /* Following an in-page link scrolls downward, which would otherwise hide the
     header for the whole of the smooth-scroll travel. Hold it visible until the
     jump has settled; normal hide-on-scroll resumes straight after. */
  document.querySelectorAll('a[href^="#"]').forEach((link) => {
    link.addEventListener('click', () => {
      setHeaderHidden(false);
      holdVisibleUntil = Date.now() + (prefersReducedMotion.matches ? 150 : 900);
    });
  });

  const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: prefersReducedMotion.matches ? 'auto' : 'smooth' });
  };

  if (toTopBtn) toTopBtn.addEventListener('click', scrollToTop);

  /* Both logos link to #top, but that id sits on the sticky header itself.
     Once the header is stuck to the viewport top, the browser considers it
     already in view and scrolls only far enough to satisfy
     `scroll-padding-top` — so clicking the logo nudged the page up by exactly
     that padding instead of returning to the top. Drive it ourselves. The
     href stays as a no-JS fallback. */
  document.querySelectorAll('a[href="#top"]').forEach((link) => {
    link.addEventListener('click', (event) => {
      event.preventDefault();
      scrollToTop();
    });
  });

  /* Scroll-reveal for content blocks */
  const revealTargets = document.querySelectorAll(
    '.feature-card, .menu-card, .life-card, .info-card, .about-text'
  );
  revealTargets.forEach((el) => el.classList.add('reveal'));

  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15, rootMargin: '0px 0px -40px 0px' }
    );
    revealTargets.forEach((el) => observer.observe(el));
  } else {
    revealTargets.forEach((el) => el.classList.add('is-visible'));
  }

  /* Active nav link highlighting */
  const sections = ['about', 'menu', 'life', 'schedule', 'guests', 'contacts']
    .map((id) => document.getElementById(id))
    .filter(Boolean);
  const navLinks = document.querySelectorAll('#main-nav a');

  if ('IntersectionObserver' in window && sections.length) {
    const navObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const id = entry.target.id;
            navLinks.forEach((link) => {
              link.classList.toggle('is-active', link.getAttribute('href') === `#${id}`);
            });
          }
        });
      },
      { rootMargin: '-45% 0px -50% 0px' }
    );
    sections.forEach((section) => navObserver.observe(section));
  }
});
