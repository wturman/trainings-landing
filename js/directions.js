const cards = document.querySelectorAll(".direction-card");

// reset при старті
cards.forEach((c) => {
  c.classList.remove("active");
  c.classList.remove("inactive");
});

cards.forEach((card) => {
  const moreBtn = card.querySelector(".more-btn");

  // функція для відкриття/закриття картки
  function toggleCard() {
    const isActive = card.classList.contains("active");

    // скидаємо всі картки
    cards.forEach((c) => {
      c.classList.remove("active");
      c.classList.remove("inactive");
      const btn = c.querySelector(".more-btn");
      if (btn) btn.textContent = "Докладніше";
    });

    // якщо ця картка не була активна → робимо активною
    if (!isActive) {
      card.classList.add("active");
      cards.forEach((c) => {
        if (c !== card) c.classList.add("inactive");
      });
      if (moreBtn) moreBtn.textContent = "Закрити";
    }
  }

  // клік по картці
  card.addEventListener("click", (e) => {
    e.stopPropagation();
    toggleCard();
  });

  // клік по кнопці
  if (moreBtn) {
    moreBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      toggleCard();
    });
  }
});

// клік поза картками → всі повертаються до звичайного стану
document.addEventListener("click", () => {
  cards.forEach((c) => {
    c.classList.remove("active");
    c.classList.remove("inactive");
    const btn = c.querySelector(".more-btn");
    if (btn) btn.textContent = "Докладніше";
  });
});
