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

  /* The CMS writes image paths root-absolute (`/images/uploads/…`) because its
     own preview runs at /admin/ and would otherwise resolve them to
     /admin/images/… and 404. The live site doesn't want them absolute: that
     assumes the site sits at a domain root, which breaks the moment it is
     served from a subdirectory. Strip the leading slash so they match the
     relative paths already used in the static markup — identical at a root,
     correct under a subpath. Protocol-relative and absolute URLs are left
     alone, in case a full CDN URL is ever pasted into the CMS. */
  const toRelative = (path) => {
    const value = String(path);
    if (/^([a-z][a-z0-9+.-]*:)?\/\//i.test(value)) return value; // http(s):// or //
    return value.replace(/^\/+/, '');
  };

  const applyField = (el, rawValue) => {
    if (el.tagName === 'IMG') {
      el.src = toRelative(rawValue);
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

  /* Repeatable sections (features, menu, drinks, Жизнь штаба, opening hours).

     The markup already in the page IS the template: each container's existing
     children are captured once, then rebuilt from the array. That keeps the
     static HTML meaningful — it is the no-JS fallback and what a crawler sees —
     instead of leaving an empty <div> waiting on fetch.

     Templates are captured per index and reused cyclically. That matters for
     the feature cards: each carries its own inline SVG icon, and cloning only
     the first would give all four the same one. A fifth item added in the CMS
     reuses the first icon, which is the sane degradation. */
  const renderList = (container, items) => {
    if (!Array.isArray(items) || !items.length) return;

    let templates = container.__cmsTemplates;
    if (!templates) {
      templates = Array.from(container.children).map((el) => el.cloneNode(true));
      container.__cmsTemplates = templates;
    }
    if (!templates.length) return;

    const built = items.map((item, i) => {
      const node = templates[i % templates.length].cloneNode(true);

      /* Iterate the template's slots, not the item's keys. A key the item
         simply doesn't have — an unpriced item, say — must clear the slot,
         because otherwise the value cloned from the template stays put and the
         item silently inherits another item's price. */
      node.querySelectorAll('[data-cms-item]').forEach((slot) => {
        const key = slot.dataset.cmsItem;
        const value = item[key];

        if (slot.tagName === 'IMG') {
          if (value) slot.src = toRelative(value);
          /* alt text belongs to the picture, not the template it was cloned
             from — a stale alt is worse than a generic one. */
          slot.alt = item.alt || (item.title ? String(item.title) : '');
          return;
        }

        const text = value == null ? '' : String(value).trim();
        slot.textContent = text;
        /* An empty slot must not leave its padding, gap or separator behind. */
        slot.hidden = text === '';
      });

      return node;
    });

    container.replaceChildren(...built);
  };

  fetch('content/home.json', { cache: 'no-store' })
    .then((res) => (res.ok ? res.json() : Promise.reject(res.status)))
    .then((data) => {
      document.querySelectorAll('[data-cms-field]').forEach((el) => {
        const value = data[el.dataset.cmsField];
        if (value == null) return;
        applyField(el, value);
      });

      document.querySelectorAll('[data-cms-list]').forEach((container) => {
        renderList(container, data[container.dataset.cmsList]);
      });

      /* script.js sets up scroll-reveal before this fetch resolves, so any card
         rebuilt above is a new element its IntersectionObserver never saw. Tell
         it to pick them up, or CMS-rendered cards would sit at opacity 0. */
      document.dispatchEvent(new CustomEvent('cms:rendered'));
    })
    .catch(() => {
      /* content/home.json missing or unreachable — static fallback markup stays as-is */
    })
    .finally(() => {
      const fontsReady = document.fonts ? document.fonts.ready : Promise.resolve();
      fontsReady.then(() => requestAnimationFrame(restoreHashPosition));
    });
});
