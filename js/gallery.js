/**
 * News article galleries: `.news-gallery` blocks with `.news-gallery__thumb` buttons.
 * Lightbox: swipe (touch), pinch-zoom (mobile), keyboard + arrows (desktop).
 */

const LIGHTBOX_ID = "news-gallery-lightbox";
const SWIPE_THRESHOLD_PX = 48;
const SWIPE_MAX_VERTICAL_PX = 72;
const SWIPE_SCALE_MAX = 1.2;
const PINCH_MIN_SCALE = 1;
const PINCH_MAX_SCALE = 4;
const TAP_MAX_MOVE_PX = 12;

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

function touchMidpointLocal(touches, element) {
  const rect = element.getBoundingClientRect();
  const cx = rect.left + rect.width / 2;
  const cy = rect.top + rect.height / 2;
  const x = (touches[0].clientX + touches[1].clientX) / 2 - cx;
  const y = (touches[0].clientY + touches[1].clientY) / 2 - cy;
  return { x, y };
}

function clampScale(value) {
  return Math.min(PINCH_MAX_SCALE, Math.max(PINCH_MIN_SCALE, value));
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

  const gesture = {
    scale: 1,
    panX: 0,
    panY: 0,
    isPinching: false,
    isSwipeTracking: false,
    isPanning: false,
    pinchStartDistance: 0,
    pinchStartScale: 1,
    pinchStartPanX: 0,
    pinchStartPanY: 0,
    pinchMidX: 0,
    pinchMidY: 0,
    swipeStartX: 0,
    swipeStartY: 0,
    panLastX: 0,
    panLastY: 0,
    gestureMoved: false,
  };

  function swipeNavigationAllowed() {
    return gesture.scale <= SWIPE_SCALE_MAX && !gesture.isPinching && !gesture.isPanning;
  }

  function syncViewportZoomClass() {
    const zoomed = gesture.scale > SWIPE_SCALE_MAX || gesture.isPinching;
    viewport.classList.toggle("news-gallery-lightbox__viewport--zoomed", zoomed);
  }

  function applyImageTransform() {
    stageImg.style.transform = `translate3d(${gesture.panX}px, ${gesture.panY}px, 0) scale(${gesture.scale})`;
    syncViewportZoomClass();
  }

  function resetGestureState() {
    gesture.scale = 1;
    gesture.panX = 0;
    gesture.panY = 0;
    gesture.isPinching = false;
    gesture.isSwipeTracking = false;
    gesture.isPanning = false;
    gesture.pinchStartDistance = 0;
    gesture.pinchStartScale = 1;
    gesture.pinchStartPanX = 0;
    gesture.pinchStartPanY = 0;
    gesture.pinchMidX = 0;
    gesture.pinchMidY = 0;
    gesture.gestureMoved = false;
    stageImg.style.transform = "";
    stageImg.classList.remove("news-gallery-lightbox__img--resetting");
    syncViewportZoomClass();
  }

  function resetZoomAnimated() {
    stageImg.classList.add("news-gallery-lightbox__img--resetting");
    resetGestureState();
    window.setTimeout(() => {
      stageImg.classList.remove("news-gallery-lightbox__img--resetting");
    }, 220);
  }

  function showSlide() {
    if (!items.length) return;
    const item = items[index];
    resetGestureState();
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
    resetGestureState();
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
      if (gesture.scale > SWIPE_SCALE_MAX) {
        resetZoomAnimated();
        return;
      }
      close();
    } else if (event.key === "ArrowLeft") {
      event.preventDefault();
      go(-1);
    } else if (event.key === "ArrowRight") {
      event.preventDefault();
      go(1);
    }
  });

  function beginPinch(touches) {
    gesture.isPinching = true;
    gesture.isSwipeTracking = false;
    gesture.isPanning = false;
    gesture.pinchStartDistance = touchDistance(touches);
    gesture.pinchStartScale = gesture.scale;
    gesture.pinchStartPanX = gesture.panX;
    gesture.pinchStartPanY = gesture.panY;
    const mid = touchMidpointLocal(touches, viewport);
    gesture.pinchMidX = mid.x;
    gesture.pinchMidY = mid.y;
    syncViewportZoomClass();
  }

  function updatePinch(touches) {
    if (!gesture.isPinching || gesture.pinchStartDistance <= 0) return;
    const distance = touchDistance(touches);
    const nextScale = clampScale(gesture.pinchStartScale * (distance / gesture.pinchStartDistance));
    const scaleRatio = nextScale / gesture.pinchStartScale;
    gesture.panX = gesture.pinchMidX - (gesture.pinchMidX - gesture.pinchStartPanX) * scaleRatio;
    gesture.panY = gesture.pinchMidY - (gesture.pinchMidY - gesture.pinchStartPanY) * scaleRatio;
    gesture.scale = nextScale;
    applyImageTransform();
  }

  function endPinch() {
    gesture.isPinching = false;
    if (gesture.scale < PINCH_MIN_SCALE) {
      resetZoomAnimated();
      return;
    }
    if (gesture.scale <= SWIPE_SCALE_MAX) {
      gesture.panX = 0;
      gesture.panY = 0;
      gesture.scale = 1;
      applyImageTransform();
    }
    syncViewportZoomClass();
  }

  viewport.addEventListener(
    "touchstart",
    (event) => {
      if (lightbox.hidden) return;
      gesture.gestureMoved = false;

      if (event.touches.length === 2) {
        event.preventDefault();
        beginPinch(event.touches);
        return;
      }

      if (event.touches.length !== 1) return;

      const touch = event.touches[0];
      if (gesture.scale > SWIPE_SCALE_MAX) {
        gesture.isPanning = true;
        gesture.isSwipeTracking = false;
        gesture.panLastX = touch.clientX;
        gesture.panLastY = touch.clientY;
        return;
      }

      if (swipeNavigationAllowed()) {
        gesture.isSwipeTracking = true;
        gesture.swipeStartX = touch.clientX;
        gesture.swipeStartY = touch.clientY;
      }
    },
    { passive: false }
  );

  viewport.addEventListener(
    "touchmove",
    (event) => {
      if (lightbox.hidden) return;

      if (event.touches.length === 2) {
        if (!gesture.isPinching) {
          beginPinch(event.touches);
        }
        event.preventDefault();
        updatePinch(event.touches);
        gesture.gestureMoved = true;
        return;
      }

      if (gesture.isPinching) {
        gesture.isSwipeTracking = false;
        return;
      }

      if (event.touches.length !== 1) return;
      const touch = event.touches[0];

      if (gesture.scale > SWIPE_SCALE_MAX && gesture.isPanning) {
        event.preventDefault();
        const dx = touch.clientX - gesture.panLastX;
        const dy = touch.clientY - gesture.panLastY;
        gesture.panLastX = touch.clientX;
        gesture.panLastY = touch.clientY;
        if (Math.abs(dx) > 1 || Math.abs(dy) > 1) {
          gesture.gestureMoved = true;
        }
        gesture.panX += dx;
        gesture.panY += dy;
        applyImageTransform();
        return;
      }

      if (gesture.isSwipeTracking && swipeNavigationAllowed()) {
        const dx = touch.clientX - gesture.swipeStartX;
        const dy = touch.clientY - gesture.swipeStartY;
        if (Math.abs(dx) > TAP_MAX_MOVE_PX || Math.abs(dy) > TAP_MAX_MOVE_PX) {
          gesture.gestureMoved = true;
        }
        if (Math.abs(dx) > Math.abs(dy)) {
          event.preventDefault();
        }
      } else {
        gesture.isSwipeTracking = false;
      }
    },
    { passive: false }
  );

  viewport.addEventListener(
    "touchend",
    (event) => {
      if (lightbox.hidden) return;

      if (event.touches.length >= 2) {
        return;
      }

      if (event.touches.length === 1 && gesture.isPinching) {
        return;
      }

      if (gesture.isPinching && event.touches.length === 0) {
        endPinch();
        return;
      }

      const touch = event.changedTouches[0];
      if (!touch) {
        gesture.isPanning = false;
        gesture.isSwipeTracking = false;
        return;
      }

      if (gesture.scale > SWIPE_SCALE_MAX) {
        const dx = touch.clientX - gesture.panLastX;
        const dy = touch.clientY - gesture.panLastY;
        const moved = gesture.gestureMoved || Math.abs(dx) > TAP_MAX_MOVE_PX || Math.abs(dy) > TAP_MAX_MOVE_PX;
        gesture.isPanning = false;
        gesture.isSwipeTracking = false;
        if (!moved) {
          resetZoomAnimated();
        }
        return;
      }

      if (!gesture.isSwipeTracking || !swipeNavigationAllowed()) {
        gesture.isSwipeTracking = false;
        gesture.isPanning = false;
        return;
      }

      const dx = touch.clientX - gesture.swipeStartX;
      const dy = touch.clientY - gesture.swipeStartY;
      gesture.isSwipeTracking = false;
      gesture.isPanning = false;

      if (Math.abs(dy) > SWIPE_MAX_VERTICAL_PX) return;
      if (Math.abs(dx) < SWIPE_THRESHOLD_PX) return;

      if (dx < 0) go(1);
      else go(-1);
    },
    { passive: true }
  );

  viewport.addEventListener(
    "touchcancel",
    () => {
      if (gesture.isPinching) {
        endPinch();
      }
      gesture.isSwipeTracking = false;
      gesture.isPanning = false;
    },
    { passive: true }
  );

  stageImg.addEventListener("click", (event) => {
    event.stopPropagation();
    if (gesture.scale > SWIPE_SCALE_MAX) {
      resetZoomAnimated();
    }
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
