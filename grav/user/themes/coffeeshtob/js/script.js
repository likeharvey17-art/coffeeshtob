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
  /* Desktop and mobile hide the header by different mechanisms.

     Desktop toggles `.is-hidden` and lets a CSS transition play — the header
     is a fixed chrome element there, and animating it reads fine.

     Mobile drives an inline transform straight from the scroll delta instead,
     one-to-one with the finger. A timed transition can only be slow (the bar
     lingers while the page moves under it) or fast (it snaps); neither is what
     a header scrolling away with the content looks like. Tracking the scroll
     has no duration to get wrong. */
  const desktop = window.matchMedia('(min-width: 861px)');
  const hideAfter = () => (desktop.matches ? 220 : 12);
  const DELTA = 4;        // ignore sub-pixel jitter and momentum wobble
  let lastY = window.scrollY;
  let holdVisibleUntil = 0; // timestamp; see the anchor-click handler below

  /* How far the header must travel to be fully gone, measured off the element
     in pixels rather than written as -100%. A percentage resolves against the
     element's own box, and in some in-app webviews — Telegram's included — the
     sticky box and the visual viewport disagree. */
  const hiddenDistance = () => header.offsetHeight + 16;

  /* Mobile hiding is scroll-driven but frame-rendered, and it has to be both.

     Scroll events fire less often than frames, so writing the transform
     straight from the scroll delta moved the bar in visible jumps. Instead the
     scroll handler only sets `wanted`, and a rAF loop walks `shown` towards it
     one frame at a time. The result still follows the scroll — it is not a
     timed animation with a duration to get wrong — but it renders on every
     frame instead of every scroll event, which is what removes the stepping. */
  let shown = 0;   // px currently rendered
  let wanted = 0;  // px the scroll position asks for
  let raf = null;

  const paint = () => {
    header.style.transform = shown ? `translateY(${-shown}px)` : '';
    /* Once it is all the way up, stop painting it at all. Translating a sticky
       element off-screen is not the same as it being gone: a webview whose
       sticky box disagrees with the visual viewport can still show a sliver of
       it, which is exactly the strip left hanging in Telegram's browser.
       visibility removes it from the render without affecting layout. */
    header.style.visibility = shown >= hiddenDistance() - 0.5 ? 'hidden' : '';
  };

  const step = () => {
    const diff = wanted - shown;
    if (Math.abs(diff) < 0.5) {
      shown = wanted;
      paint();
      raf = null;
      return;
    }
    shown += diff * 0.3;   // catches up in ~3 frames: smooth, still responsive
    paint();
    raf = requestAnimationFrame(step);
  };

  const settle = () => {
    if (raf === null) raf = requestAnimationFrame(step);
  };

  const setHeaderHidden = (hidden) => {
    header.classList.toggle('is-hidden', hidden);
  };

  const clearOffset = () => {
    wanted = 0;
    settle();
  };

  /* Crossing the breakpoint must not strand the other layout's state. */
  desktop.addEventListener('change', () => {
    shown = 0;
    wanted = 0;
    header.style.transform = '';
    header.style.visibility = '';
    setHeaderHidden(false);
  });

  const onScroll = () => {
    const y = window.scrollY;
    header.classList.toggle('is-scrolled', y > 12);

    const movement = y - lastY;
    const moved = Math.abs(movement) >= DELTA;
    const scrollingUp = movement < 0;

    /* Back-to-top rides the same gesture as the header: it appears only once
       the reader starts heading back up, and only far enough down the page to
       be worth offering. Scrolling down again puts it away. */
    if (moved && toTopBtn) {
      toTopBtn.classList.toggle('is-visible', scrollingUp && y > 480);
    }

    /* Reasons the header must stay put regardless of scroll direction: it is
       resting above the hero, a panel is open under it, or an in-page link is
       mid-jump (that travel is downward, but the reader didn't scroll).

       This is checked *before* the small-movement bail below, and deliberately
       so: pinning is a fact about where the page is, not about how far it just
       travelled. Testing it after meant a jump straight to the top — or a slow
       drift up in sub-threshold increments — never reset the header, stranding
       it off-screen at y=0. */
    const pinned =
      y <= hideAfter() ||
      isNavOpen() || isSocialOpen() ||
      Date.now() < holdVisibleUntil;

    if (pinned) {
      setHeaderHidden(false);
      clearOffset();
      lastY = y;
      return;
    }

    /* Too small to read a direction from. lastY is left alone on purpose, so
       successive small movements accumulate instead of being discarded. */
    if (!moved) return;

    if (desktop.matches) {
      clearOffset(); // in case we arrived from the mobile layout
      header.style.visibility = '';
      setHeaderHidden(!scrollingUp);
    } else {
      /* Mobile: the class stays off and the transform does the work, moving
         the header by exactly as much as the page moved, up to the point where
         it is fully clear. */
      setHeaderHidden(false);
      wanted = Math.max(0, Math.min(hiddenDistance(), wanted + movement));
      settle();
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
      clearOffset();
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

  /* Scroll-reveal for content blocks.

     Written as a rescan rather than a one-off, because render.js rebuilds the
     card grids from the CMS *after* this runs — those are brand new elements
     the observer has never seen. Without the `cms:rendered` hook below they
     would keep `.reveal`'s opacity: 0 and the sections would look empty. */
  const REVEAL_SELECTOR = '.feature-card, .menu-item, .life-card, .info-card, .about-text';
  const supportsObserver = 'IntersectionObserver' in window;

  const revealObserver = supportsObserver
    ? new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              entry.target.classList.add('is-visible');
              revealObserver.unobserve(entry.target);
            }
          });
        },
        { threshold: 0.15, rootMargin: '0px 0px -40px 0px' }
      )
    : null;

  /* Tracked by identity, not by the `.reveal` class. render.js rebuilds the
     card grids by cloning the nodes already in the page — and by then those
     nodes have been given `.reveal` here, so the clones arrive carrying it.
     Using the class as the "already handled" marker therefore skipped every
     rebuilt card: they kept `.reveal`'s opacity: 0, were never observed, and
     stayed invisible for good. A WeakSet keys on the element itself, and a
     clone is a different element. */
  const revealSeen = new WeakSet();

  const scanReveal = () => {
    document.querySelectorAll(REVEAL_SELECTOR).forEach((el) => {
      if (revealSeen.has(el)) return;
      revealSeen.add(el);
      el.classList.add('reveal');
      if (revealObserver) revealObserver.observe(el);
      else el.classList.add('is-visible');
    });
  };

  scanReveal();
  document.addEventListener('cms:rendered', scanReveal);

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
