// manage_users.js

document.addEventListener("DOMContentLoaded", function () {
  // ────────────────────────────────────────────────
  // 1. Toggle แสดง/ซ่อนรหัสผ่าน (โค้ดเดิม)
  // ────────────────────────────────────────────────
  document.querySelectorAll(".toggle-password").forEach((button) => {
    button.addEventListener("click", function () {
      const input = this.previousElementSibling;
      const icon = this.querySelector("i");

      if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("bi-eye-slash");
        icon.classList.add("bi-eye");
      } else {
        input.type = "password";
        icon.classList.remove("bi-eye");
        icon.classList.add("bi-eye-slash");
      }
    });
  });

  // ────────────────────────────────────────────────
  // 2. แสดงแจ้งเตือนจาก PHP session แบบ TOAST (มุมขวาบน หายเอง)
  // ────────────────────────────────────────────────
  const toastElement = document.querySelector("#toast-data");
  if (toastElement) {
    const type = toastElement.dataset.type;
    const message = toastElement.dataset.message;

    if (type && message) {
      const isSuccess = type === "success";

      Swal.fire({
        icon: isSuccess ? "success" : "error",
        text: message,

        toast: true, // เปิดโหมด toast
        position: "top-end", // มุมขวาบน (แนะนำที่สุด)
        showConfirmButton: false, // ไม่แสดงปุ่มตกลง
        timer: 3500, // แสดง 3.5 วินาที (ปรับได้ตามชอบ)
        timerProgressBar: true, // มีแถบ progress ด้านล่าง
        didOpen: (toast) => {
          toast.addEventListener("mouseenter", Swal.stopTimer);
          toast.addEventListener("mouseleave", Swal.resumeTimer);
        },
      });
    }
  }

  // ────────────────────────────────────────────────
  // 3. จัดการปุ่มลบด้วย SweetAlert2
  // ────────────────────────────────────────────────
  document.querySelectorAll(".btn-delete").forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault();

      const username = this.getAttribute("data-username");
      const deleteUrl = this.getAttribute("data-delete-url");

      Swal.fire({
        title: "ยืนยันการลบ?",
        html: `คุณต้องการลบผู้ใช้ <strong>${username}</strong> จริงหรือไม่<br><small class="text-muted">การกระทำนี้ไม่สามารถกู้คืนได้</small>`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#dc3545",
        cancelButtonColor: "#6c757d",
        confirmButtonText: '<i class="bi bi-trash3 me-1"></i> ยืนยันลบ',
        cancelButtonText: "ยกเลิก",
        reverseButtons: true, // ให้ปุ่มยืนยันอยู่ขวา
        allowOutsideClick: false, // ป้องกันการกดนอกกล่องแล้วหลุด
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = deleteUrl;
        }
      });
    });
  });
});
