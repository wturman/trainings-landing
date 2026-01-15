document.addEventListener("DOMContentLoaded", () => {
  const headers = document.querySelectorAll(
    ".portfolio h2, .project-item, .projects-title, .stories-title"
  );

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("visible");

          if (entry.target.classList.contains("projects-title")) {
            entry.target.classList.add("typing");
          }

          if (entry.target.classList.contains("stories-title")) {
            entry.target.classList.add("typing");
          }
        }
      });
    },
    { threshold: 0.2 }
  );

  headers.forEach((el) => observer.observe(el));

  const projectsTitle = document.querySelector(".projects-title");
  if (projectsTitle) {
    projectsTitle.addEventListener("animationend", () => {
      projectsTitle.classList.add("finished");
    });
  }

  const storiesTitle = document.querySelector(".stories-title");
  if (storiesTitle) {
    storiesTitle.addEventListener("animationend", () => {
      storiesTitle.classList.add("finished");
    });
  }
});
