const burger = document.querySelector(".burger");
const nav = document.querySelector(".nav");
const navLinks = document.querySelectorAll(".nav a");

function openNav() {
  burger.classList.add("active");
  nav.classList.add("open");
  burger.setAttribute("aria-expanded", "true");
  document.body.style.overflow = "hidden";
}

function closeNav() {
  burger.classList.remove("active");
  nav.classList.remove("open");
  burger.setAttribute("aria-expanded", "false");
  document.body.style.overflow = "";
}

burger.addEventListener("click", () => {
  const isOpen = nav.classList.contains("open");
  isOpen ? closeNav() : openNav();
});

// Close menu when any nav link is clicked
navLinks.forEach((link) => {
  link.addEventListener("click", closeNav);
});

// Close menu on Escape key
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape" && nav.classList.contains("open")) {
    closeNav();
  }
});
