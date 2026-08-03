document.addEventListener("DOMContentLoaded", () => {
  const items = document.querySelectorAll(".item");
  let index = 0;

  function update() {
    items.forEach((item, i) => {
      item.classList.remove("active", "prev", "next");
      item.style = "";
    });

    const total = items.length;
    const prev = (index - 1 + total) % total;
    const next = (index + 1) % total;

    items[index].classList.add("active");
    items[prev].classList.add("prev");
    items[next].classList.add("next");
  }

  document.querySelector(".arrow.left").addEventListener("click", () => {
    index = (index - 1 + items.length) % items.length;
    update();
  });

  document.querySelector(".arrow.right").addEventListener("click", () => {
    index = (index + 1) % items.length;
    update();
  });

  setInterval(() => {
    index = (index + 1) % items.length;
    update();
  }, 3000);

  update();
});