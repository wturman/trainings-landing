/**
 * News article galleries: `.news-gallery` blocks with `.news-gallery__thumb` buttons.
 * Lightbox: swipe (touch), pinch-zoom (mobile), keyboard + arrows (desktop).
 */

const LIGHTBOX_ID = "news-gallery-lightbox";
const SWIPE_THRESHOLD_PX = 48;
const SWIPE_MAX_VERTICAL_PX = 72;
const PINCH_MAX_SCALE = 4;

function getImageFromThumb(button) {
  const img = button.querySelector("img");
  if (!img) return null;
  const full = img.getAttribute("data-fullsrc");
  return {
    src: full || img.currentSrc || img.src,
    alt: img.getAttribute("alt") || "",
  };
}

function touchDistance(touches) {
  if (touches.length < 2) return 0;
  const dx = touches[0].clientX - touches[1].clientX;
  const dy = touches[0].clientY - touches[1].clientY;
  return Math.hypot(dx, dy);
}

function createLightbox() {
  let root = document.getElementById(LIGHTBOX_ID);
  if (root) return root;

  root = document.createElement("div");
  root.id = LIGHTBOX_ID;
  root.className = "news-gallery-lightbox";
  root.hidden = true;
  root.setAttribute("role", "dialog");
  root.setAttribute("aria-modal", "true");
  root.setAttribute("aria-label", "Перегляд фото");

  root.innerHTML = `
    <button type="button" class="news-gallery-lightbox__backdrop" aria-label="Закрити"></button>
    <button type="button" class="news-gallery-lightbox__close" aria-label="Закрити">&#10005;</button>
    <button type="button" class="news-gallery-lightbox__prev" aria-label="Попереднє фото">&#8249;</button>
    <button type="button" class="news-gallery-lightbox__next" aria-label="Наступне фото">&#8250;</button>
    <div class="news-gallery-lightbox__stage">
      <div class="news-gallery-lightbox__viewport">
        <img class="news-gallery-lightbox__img" alt="" />
      </div>
    </div>
  `;

  document.body.appendChild(root);
  return root;
}

function initNewsGalleries() {
  const lightbox = createLightbox();
  const backdrop = lightbox.querySelector(".news-gallery-lightbox__backdrop");
  const closeBtn = lightbox.querySelector(".news-gallery-lightbox__close");
  const prevBtn = lightbox.querySelector(".news-gallery-lightbox__prev");
  const nextBtn = lightbox.querySelector(".news-gallery-lightbox__next");
  const stage = lightbox.querySelector(".news-gallery-lightbox__stage");
  const viewport = lightbox.querySelector(".news-gallery-lightbox__viewport");
  const stageImg = lightbox.querySelector(".news-gallery-lightbox__img");

  let items = [];
  let index = 0;
  let lastFocus = null;

  let scale = 1;
  let panX = 0;
  let panY = 0;
  let pinchStartDistance = 0;
  let pinchStartScale = 1;
  let swipeStartX = 0;
  let swipeStartY = 0;
  let swipeTracking = false;
  let isPinching = false;

  function applyImageTransform() {
    stageImg.style.transform = `translate3d(${panX}px, ${panY}px, 0) scale(${scale})`;
  }

  function resetImageTransform() {
    scale = 1;
    panX = 0;
    panY = 0;
    pinchStartDistance = 0;
    pinchStartScale = 1;
    isPinching = false;
    swipeTracking = false;
    stageImg.style.transform = "";
  }

  function showSlide() {
    if (!items.length) return;
    const item = items[index];
    resetImageTransform();
    stageImg.src = item.src;
    stageImg.alt = item.alt;
    const hideNav = items.length < 2;
    prevBtn.hidden = hideNav;
    nextBtn.hidden = hideNav;
  }

  function openAt(newItems, startIndex) {
    items = newItems;
    index = startIndex;
    lastFocus = document.activeElement;
    showSlide();
    lightbox.hidden = false;
    document.body.classList.add("news-gallery-lightbox-open");
    closeBtn.focus();
  }

  function close() {
    lightbox.hidden = true;
    stageImg.removeAttribute("src");
    resetImageTransform();
    document.body.classList.remove("news-gallery-lightbox-open");
    items = [];
    if (lastFocus && typeof lastFocus.focus === "function") {
      lastFocus.focus();
    }
  }

  function go(delta) {
    if (items.length < 2) return;
    index = (index + delta + items.length) % items.length;
    showSlide();
  }

  backdrop.addEventListener("click", close);
  closeBtn.addEventListener("click", close);
  prevBtn.addEventListener("click", () => go(-1));
  nextBtn.addEventListener("click", () => go(1));

  lightbox.addEventListener("click", (event) => {
    if (event.target === lightbox || event.target === backdrop) {
      close();
    }
  });

  stage.addEventListener("click", (event) => {
    if (event.target === stage || event.target === viewport) {
      close();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (lightbox.hidden) return;
    if (event.key === "Escape") {
      event.preventDefault();
      close();
    } else if (event.key === "ArrowLeft") {
      event.preventDefault();
      go(-1);
    } else if (event.key === "ArrowRight") {
      event.preventDefault();
      go(1);
    }
  });

  viewport.addEventListener(
    "touchstart",
    (event) => {
      if (lightbox.hidden) return;
      if (event.touches.length === 2) {
        isPinching = true;
        swipeTracking = false;
        pinchStartDistance = touchDistance(event.touches);
        pinchStartScale = scale;
        return;
      }
      if (event.touches.length === 1 && scale <= 1.02) {
        swipeTracking = true;
        swipeStartX = event.touches[0].clientX;
        swipeStartY = event.touches[0].clientY;
      }
    },
    { passive: true }
  );

  viewport.addEventListener(
    "touchmove",
    (event) => {
      if (lightbox.hidden) return;
      if (event.touches.length === 2) {
        if (!isPinching || pinchStartDistance <= 0) return;
        event.preventDefault();
        const distance = touchDistance(event.touches);
        scale = Math.min(PINCH_MAX_SCALE, Math.max(1, pinchStartScale * (distance / pinchStartDistance)));
        applyImageTransform();
        return;
      }
      if (isPinching || scale > 1.02) {
        swipeTracking = false;
      }
    },
    { passive: false }
  );

  viewport.addEventListener(
    "touchend",
    (event) => {
      if (lightbox.hidden) return;
      if (event.touches.length > 0) return;

      if (isPinching) {
        isPinching = false;
        if (scale < 1) {
          resetImageTransform();
        }
        return;
      }

      if (!swipeTracking || scale > 1.02) {
        swipeTracking = false;
        return;
      }

      const touch = event.changedTouches[0];
      if (!touch) return;

      const dx = touch.clientX - swipeStartX;
      const dy = touch.clientY - swipeStartY;
      swipeTracking = false;

      if (Math.abs(dy) > SWIPE_MAX_VERTICAL_PX) return;
      if (Math.abs(dx) < SWIPE_THRESHOLD_PX) return;

      if (dx < 0) go(1);
      else go(-1);
    },
    { passive: true }
  );

  stageImg.addEventListener("click", (event) => {
    event.stopPropagation();
  });

  document.querySelectorAll(".news-gallery:not([data-gallery-bound])").forEach((gallery) => {
    gallery.dataset.galleryBound = "true";
    const thumbs = [...gallery.querySelectorAll(".news-gallery__thumb")];
    const galleryItems = thumbs.map((btn) => getImageFromThumb(btn)).filter(Boolean);

    thumbs.forEach((btn, i) => {
      btn.addEventListener("click", () => openAt(galleryItems, i));
    });
  });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initNewsGalleries);
} else {
  initNewsGalleries();
}
