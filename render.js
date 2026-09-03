document.addEventListener('DOMContentLoaded', () => {
  /* The browser performs its anchor jump at parse time, before this file has
     swapped in the CMS copy and before the web fonts have swapped. Both change
     the document height, which leaves a deep link pointing at the wrong place —
     so re-scroll once everything has settled. Skipped if the reader has already
     started scrolling, to avoid yanking the page out from under them. */
  let userScrolled = false;
  const noteUserScroll = () => {
    userScrolled = true;
  };
  ['wheel', 'touchstart', 'keydown'].forEach((evt) => {
    window.addEventListener(evt, noteUserScroll, { once: true, passive: true });
  });

  const escapeHtml = (str) =>
    str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');

  /* Only these may hold <p> children. Headings and <p> itself take phrasing
     content only, so wrapping their text in <p> would be invalid markup. */
  const FLOW_CONTAINERS = new Set(['DIV', 'SECTION', 'ARTICLE', 'ASIDE', 'MAIN', 'BLOCKQUOTE']);

  const applyField = (el, rawValue) => {
    if (el.tagName === 'IMG') {
      el.src = rawValue;
      return;
    }

    const paragraphs = String(rawValue)
      .split(/\n{2,}/)
      .map((para) => para.trim())
      .filter(Boolean);

    if (FLOW_CONTAINERS.has(el.tagName)) {
      /* Always emit <p> children, including in the single-paragraph case:
         .about-text uses flex `gap` for its paragraph spacing, so a bare text
         node would silently lose that spacing. */
      el.innerHTML = paragraphs.map((para) => `<p>${escapeHtml(para)}</p>`).join('');
    } else {
      /* <p> and headings: separate with breaks instead. Nesting <p> inside <p>
         is invalid and renders without paragraph spacing. */
      el.innerHTML = paragraphs.map(escapeHtml).join('<br><br>');
    }
  };

  /* Re-scroll a few frames running rather than once: the sticky header's height
     (and so `scroll-padding-top`) is still settling as the fonts swap in, so a
     single pass lands a few dozen pixels off. Repeating converges on the mark. */
  const restoreHashPosition = (tries = 4) => {
    if (userScrolled) return;
    const id = decodeURIComponent(location.hash.slice(1));
    if (!id) return;
    const target = document.getElementById(id);
    if (!target) return;
    target.scrollIntoView();
    if (tries > 0) requestAnimationFrame(() => restoreHashPosition(tries - 1));
  };

  fetch('content/home.json', { cache: 'no-store' })
    .then((res) => (res.ok ? res.json() : Promise.reject(res.status)))
    .then((data) => {
      document.querySelectorAll('[data-cms-field]').forEach((el) => {
        const value = data[el.dataset.cmsField];
        if (value == null) return;
        applyField(el, value);
      });
    })
    .catch(() => {
      /* content/home.json missing or unreachable — static fallback markup stays as-is */
    })
    .finally(() => {
      const fontsReady = document.fonts ? document.fonts.ready : Promise.resolve();
      fontsReady.then(() => requestAnimationFrame(restoreHashPosition));
    });
});
