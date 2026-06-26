function readCookie(name) {
  const prefix = `${encodeURIComponent(name)}=`;
  const parts = document.cookie.split(';');
  for (const part of parts) {
    const trimmed = part.trim();
    if (trimmed.startsWith(prefix)) {
      return decodeURIComponent(trimmed.slice(prefix.length));
    }
  }
  return '';
}

function setLikedUi(block, likes) {
  const likesValue = block.querySelector('[data-likes-count] .news-article__stat-value');
  const btn = block.querySelector('[data-like-btn]');
  if (likesValue && typeof likes === 'number') {
    likesValue.textContent = String(likes);
  }
  if (!btn) {
    return;
  }
  btn.disabled = true;
  btn.classList.add('is-liked');
  btn.setAttribute('aria-pressed', 'true');
  const icon = btn.querySelector('i');
  if (icon) {
    icon.classList.remove('fa-regular', 'far');
    icon.classList.add('fas', 'fa-solid', 'fa-heart');
  }
  const label = btn.querySelector('.news-article__like-btn-text');
  if (label) {
    label.textContent = 'Дякуємо';
  }
}

document.querySelectorAll('[data-news-engagement]').forEach((block) => {
  const slug = block.getAttribute('data-slug') || '';
  if (!slug) {
    return;
  }

  const likedCookie = `liked_${slug}`;
  if (block.getAttribute('data-liked') === '1' || readCookie(likedCookie) === '1') {
    setLikedUi(block);
    block.setAttribute('data-liked', '1');
  }

  const btn = block.querySelector('[data-like-btn]');
  if (!btn) {
    return;
  }

  btn.addEventListener('click', async () => {
    if (btn.disabled) {
      return;
    }

    btn.disabled = true;

    try {
      const body = new URLSearchParams();
      body.set('slug', slug);

      const response = await fetch('like.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
        credentials: 'same-origin',
      });

      const payload = await response.json();
      if (!payload || payload.ok !== true) {
        btn.disabled = false;
        return;
      }

      setLikedUi(block, payload.likes);
      block.setAttribute('data-liked', '1');
    } catch {
      btn.disabled = false;
    }
  });
});
