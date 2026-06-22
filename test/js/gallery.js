/**
 * News article galleries: `.news-gallery` blocks with `.news-gallery__thumb` buttons.
 * Copy the HTML block into any news page; filenames/paths are the only per-page changes.
 */

const LIGHTBOX_ID = "news-gallery-lightbox";

function getImageFromThumb(button) {
  const img = button.querySelector("img");
  if (!img) return null;
  const full = img.getAttribute("data-fullsrc");
  return {
    src: full || img.currentSrc || img.src,
    alt: img.getAttribute("alt") || "",
  };
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
    <button type="button" class="news-gallery-lightbox__close" aria-label="Закрити">&times;</button>
    <button type="button" class="news-gallery-lightbox__prev" aria-label="Попереднє фото">&#8249;</button>
    <button type="button" class="news-gallery-lightbox__next" aria-label="Наступне фото">&#8250;</button>
    <div class="news-gallery-lightbox__stage">
      <img class="news-gallery-lightbox__img" alt="" />
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
  const stageImg = lightbox.querySelector(".news-gallery-lightbox__img");

  let items = [];
  let index = 0;
  let lastFocus = null;

  function showSlide() {
    if (!items.length) return;
    const item = items[index];
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
    document.body.style.overflow = "hidden";
    closeBtn.focus();
  }

  function close() {
    lightbox.hidden = true;
    stageImg.removeAttribute("src");
    document.body.style.overflow = "";
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
    if (event.target === lightbox) close();
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

  document.querySelectorAll(".news-gallery:not([data-gallery-bound])").forEach((gallery) => {
    gallery.dataset.galleryBound = "true";
    const thumbs = [...gallery.querySelectorAll(".news-gallery__thumb")];
    const galleryItems = thumbs
      .map((btn) => getImageFromThumb(btn))
      .filter(Boolean);

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
