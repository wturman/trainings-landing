document.addEventListener("DOMContentLoaded", () => {
  const footer = document.querySelector(".footer");
  if (!footer) return;

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          footer.classList.add("visible");
          observer.unobserve(footer);
        }
      });
    },
    { threshold: 0.15 }
  );

  observer.observe(footer);
});
