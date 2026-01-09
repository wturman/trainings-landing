const cards = document.querySelectorAll(".direction-card");

// reset при старті
cards.forEach((c) => {
  c.classList.remove("active");
  c.classList.remove("inactive");
});

cards.forEach((card) => {
  card.addEventListener("click", (e) => {
    e.stopPropagation(); // щоб клік не йшов на document
    const isActive = card.classList.contains("active");

    cards.forEach((c) => {
      c.classList.remove("active");
      c.classList.remove("inactive");
    });

    if (!isActive) {
      card.classList.add("active");
      cards.forEach((c) => {
        if (c !== card) c.classList.add("inactive");
      });
    }
  });

  const closeBtn = card.querySelector(".close-btn");
  if (closeBtn) {
    closeBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      card.classList.remove("active");
      cards.forEach((c) => c.classList.remove("inactive"));
    });
  }
});

// клік поза картками → всі повертаються до звичайного стану
document.addEventListener("click", () => {
  cards.forEach((c) => {
    c.classList.remove("active");
    c.classList.remove("inactive");
  });
});
