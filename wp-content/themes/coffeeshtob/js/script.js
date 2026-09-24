/* The page-to-page crossfade (@view-transition in style.css) is the browser's
   own, and when it is skipped — a hidden tab, a second click before the first
   finished — its promises reject with "Transition was skipped" and the browser
   reports them as uncaught errors. Skipping is harmless and expected, so it is
   acknowledged here instead. Outside DOMContentLoaded on purpose: `pagereveal`
   fires before the first render, and a listener added later would miss it. */
const quietTransition = (event) => {
  const vt = event.viewTransition;
  if (!vt) return;
  /* Arriving through a view transition, the page title is already travelling
     into place from the row that was clicked; the banner's own rise-in would
     play underneath it as a second entrance. style.css skips it on this. */
  if (event.type === 'pagereveal') {
    document.documentElement.classList.add('vt-arrival');
    vt.finished.finally(() => document.documentElement.classList.remove('vt-arrival'));
  }
  vt.ready.catch(() => {});
  vt.finished.catch(() => {});
  vt.updateCallbackDone.catch(() => {});
};
window.addEventListener('pagereveal', quietTransition);
window.addEventListener('pageswap', quietTransition);

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
    const root = document.documentElement;
    root.style.setProperty(
      '--header-h',
      `${Math.round(header.getBoundingClientRect().height)}px`
    );
    /* How far the header must travel to be fully gone. The transform lives in
       CSS now, so the measurement is published as a custom property.

       Measured off the element rather than written as -100%: a percentage
       resolves against the element's own box, and in some in-app webviews —
       Telegram's among them — the sticky box and the visual viewport disagree,
       which left a sliver of the bar stranded on screen. The +16 clears the
       pill's drop shadow too. */
    root.style.setProperty('--header-hide', `${header.offsetHeight + 16}px`);
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
    window.matchMedia('(min-width: 1001px)').addEventListener('change', (e) => {
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
  /* ONE MECHANISM AT EVERY WIDTH: toggle `.is-hidden` and let a short CSS
     transition play.

     Mobile used to be different — an inline transform driven straight from the
     scroll delta and interpolated in a requestAnimationFrame loop, so the bar
     travelled one-to-one with the finger rather than on a timer. That was
     deliberate, and replacing it is deliberate too: a fast timed slide was
     asked for instead. It is also about forty lines less machinery to go wrong,
     and it puts desktop and mobile back on the same code path.

     If the scroll-linked version is ever wanted back, the reason it existed is
     that a *slow* transition makes the bar linger while the page moves under
     it. Keep the duration short (0.2s) and that does not arise. */
  const desktop = window.matchMedia('(min-width: 1001px)');
  const hideAfter = () => (desktop.matches ? 220 : 12);
  const DELTA = 4;        // ignore sub-pixel jitter and momentum wobble
  let lastY = window.scrollY;
  let holdVisibleUntil = 0; // timestamp; see the anchor-click handler below

  const setHeaderHidden = (hidden) => {
    header.classList.toggle('is-hidden', hidden);
  };

  /* Crossing the breakpoint changes the header's height, so the hide distance
     is remeasured; the ResizeObserver above catches the resize itself, but not
     a media-query flip that leaves the height unchanged. */
  desktop.addEventListener('change', () => {
    setHeaderHidden(false);
    syncHeaderHeight();
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
      lastY = y;
      return;
    }

    /* Too small to read a direction from. lastY is left alone on purpose, so
       successive small movements accumulate instead of being discarded. */
    if (!moved) return;

    setHeaderHidden(!scrollingUp);
    lastY = y;
  };

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* Hero scroll response: the copy lifts and fades as the hero leaves, and the
     photo drifts a little more slowly than the page. Deliberately small — a
     busy hero on a cafe's front page is the "AI landing page" tell this design
     keeps avoiding.

     ONLY opacity and transform are touched, never a height. The hero is sized
     in `svh` precisely so nothing reflows mid-scroll; the jank this project
     already shipped once came from the height changing as the mobile address
     bar moved, which rescaled the cover-fitted photo every frame. Driving a
     transform from scrollY does not reintroduce that.

     Reduced motion opts out entirely: --hero-progress is never set, so the CSS
     fallback of 0 applies and the hero sits still. */
  const heroEl = document.querySelector('.hero');
  if (heroEl && !prefersReducedMotion.matches) {
    let heroTicking = false;
    const paintHero = () => {
      heroTicking = false;
      /* Progress over the hero's own height, clamped. Past the hero there is
         nothing left to animate, and a value above 1 would go on pushing the
         photo down behind the section below. */
      const span = heroEl.offsetHeight || 1;
      const p = Math.min(1, Math.max(0, window.scrollY / span));
      heroEl.style.setProperty('--hero-progress', p.toFixed(3));
    };
    /* Scroll events outrun frames, so the write is deferred to the next one —
       otherwise the same style is set several times per painted frame. */
    window.addEventListener('scroll', () => {
      if (!heroTicking) {
        heroTicking = true;
        requestAnimationFrame(paintHero);
      }
    }, { passive: true });
    paintHero();
  }

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

  /* Both logos point at the home page for real. They used to be href="#top",
     driven entirely by JS because that id sits on the sticky header: once the
     header is stuck the browser considers it already in view and scrolls only
     far enough to satisfy `scroll-padding-top`, so clicking the logo nudged the
     page by exactly that padding instead of returning to the top.

     With seven other pages that trick would have broken the logo everywhere but
     home, so the href is genuine now and this only intercepts the case it was
     ever really for: you are ALREADY on the page the logo points to, where a
     navigation would be a pointless reload. Everything else — and the whole
     thing with JS off — is an ordinary link.

     Comparing `pathname` rather than `href` on purpose: the two differ over a
     hash, a query string or an absolute-vs-relative href, and any of those
     would make a same-page click navigate instead. */
  document.querySelectorAll('a[data-home]').forEach((link) => {
    link.addEventListener('click', (event) => {
      if (link.pathname !== window.location.pathname) return;
      event.preventDefault();
      scrollToTop();
    });
  });

  /* Photographs develop as they scroll into view — see "Photographs develop"
     in style.css for why this replaced the fade-and-rise on every card.

     Only photos BELOW THE FOLD when this runs are marked. One already on
     screen was painted in colour before the script ran, and marking it would
     flash it back to sepia; with JavaScript off, or reduced motion on, nothing
     is marked and every photo is simply itself. That is the whole safety
     model: the undeveloped state exists only where the script can finish it.

     Photos that arrive in the same batch — a row of three — are staggered by
     140ms each, capped at three steps, so a row develops left to right instead
     of all at once. */
  if ('IntersectionObserver' in window && !prefersReducedMotion.matches) {
    const developObserver = new IntersectionObserver(
      (entries) => {
        let step = 0;
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          const el = entry.target;
          const delay = Math.min(step++, 3) * 140;
          el.style.setProperty('--develop-delay', `${delay}ms`);
          el.classList.add('is-developed');
          developObserver.unobserve(el);
          /* Once developed, hand the print back to its ordinary rules. Left
             in place, `.develop.is-developed { transform: none }` and the
             develop transition outrank the front-page index's hover, so a
             row that had scrolled in never tilted and lost its shadow easing
             — while the rows above the fold did. Every property the develop
             classes set ends equal to the base rule, so removing them is
             invisible. 1.5s outlasts the longest of them (the 1.4s filter). */
          if (el.classList.contains('develop')) {
            setTimeout(() => {
              el.classList.remove('develop', 'is-developed');
              el.style.removeProperty('--develop-delay');
            }, delay + 1500);
          }
        });
      },
      { threshold: 0.3 }
    );
    document.querySelectorAll('.img-frame, .menu-thumb').forEach((el) => {
      if (el.getBoundingClientRect().top < window.innerHeight) return;
      el.classList.add('develop');
      developObserver.observe(el);
    });
    /* Menu rows ride the same observer: their dotted leaders draw in, name to
       price, as the row arrives (style.css, `.menu-item.draw`). Same rule —
       only rows below the fold, so nothing already read is undrawn. */
    document.querySelectorAll('.menu-item').forEach((el) => {
      if (el.getBoundingClientRect().top < window.innerHeight) return;
      el.classList.add('draw');
      developObserver.observe(el);
    });
  }

  /* The active-nav highlight used to be computed here by an IntersectionObserver
     over a hardcoded list of section ids. It is gone, and must not come back.

     With the sections split into real pages, nav hrefs are `/kuhnya`, not
     `#menu`. That code matched `link.getAttribute('href') === '#' + id`, so no
     link could ever match again — and because it called `toggle` on every
     intersection it would not merely have stopped working, it would have
     actively stripped the `is-active` class that Grav now renders server-side.

     partials/nav.html.twig sets it from `p.active`, which is correct before the
     first paint and correct with JS disabled. */
});
