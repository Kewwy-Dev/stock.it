document.addEventListener("DOMContentLoaded", function () {
  const toastElList = document.querySelectorAll(".toast");
  toastElList.forEach((toastEl) => {
    const toast = new bootstrap.Toast(toastEl, {
      autohide: true,
      delay: 1500,
    });
    toast.show();
  });
});