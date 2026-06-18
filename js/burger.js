const burger = document.querySelector(".burger");
const nav = document.querySelector(".nav");

if (burger && nav) {
  const navLinks = nav.querySelectorAll("a");

  function setNavOpen(open) {
    burger.classList.toggle("active", open);
    nav.classList.toggle("open", open);
    burger.setAttribute("aria-expanded", open ? "true" : "false");
    document.body.style.overflow = open ? "hidden" : "";
  }

  function openNav() {
    setNavOpen(true);
  }

  function closeNav() {
    setNavOpen(false);
  }

  burger.addEventListener("click", (e) => {
    e.stopPropagation();
    const isOpen = nav.classList.contains("open");
    setNavOpen(!isOpen);
  });

  navLinks.forEach((link) => {
    link.addEventListener("click", () => {
      closeNav();
    });
  });

  nav.addEventListener("click", (e) => {
    if (!nav.classList.contains("open")) return;
    if (e.target.closest("a")) return;
    closeNav();
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && nav.classList.contains("open")) {
      closeNav();
    }
  });
}
