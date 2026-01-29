// ฟังก์ชันจัดการซ่อน/แสดงรหัสผ่าน
function togglePassword(inputId, toggleId) {
  const input = document.getElementById(inputId);
  const toggle = document.getElementById(toggleId);

  input.addEventListener("input", function () {
    if (this.value.length > 0) {
      toggle.classList.remove("d-none");
    } else {
      toggle.classList.add("d-none");
      input.setAttribute("type", "password");
      toggle.querySelector("i").classList.add("bi-eye-slash");
      toggle.querySelector("i").classList.remove("bi-eye");
    }
  });

  toggle.addEventListener("click", function () {
    const type =
      input.getAttribute("type") === "password" ? "text" : "password";
    input.setAttribute("type", type);
    this.querySelector("i").classList.toggle("bi-eye");
    this.querySelector("i").classList.toggle("bi-eye-slash");
  });
}

togglePassword("oldPassword", "toggleOld");
togglePassword("newPassword", "toggleNew");
togglePassword("confirmPassword", "toggleConfirm");

// คลิกที่รูปเพื่อเลือกไฟล์
document
  .getElementById("profileImageWrapper")
  .addEventListener("click", function () {
    document.getElementById("profileImageInput").click();
  });

// แสดง preview เมื่อเลือกไฟล์
document
  .getElementById("profileImageInput")
  .addEventListener("change", function (e) {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function (event) {
        const preview = document.getElementById("profilePreview");
        if (preview.tagName === "IMG") {
          preview.src = event.target.result;
        } else {
          const img = document.createElement("img");
          img.src = event.target.result;
          img.alt = "รูปโปรไฟล์ตัวอย่าง";
          img.className = "profile-img";
          preview.replaceWith(img);
          img.id = "profilePreview";
        }
      };
      reader.readAsDataURL(file);
    }
  });
// ฟังก์ชันจัดการ submit แบบ AJAX + SweetAlert
function handleFormSubmit(formId, successRedirect = true) {
  const form = document.getElementById(formId);
  if (!form) return;

  form.addEventListener("submit", async function (e) {
    e.preventDefault();

    const formData = new FormData(form);

    try {
      const response = await fetch("profile.php", {
        method: "POST",
        body: formData,
      });
      if (!response.ok) {
        console.log("HTTP error! status:", response.status);
        throw new Error(`HTTP ${response.status}`);
      }

      const result = await response.json();
      console.log("Response จาก server:", result);

      Swal.fire({
        icon: result.success ? "success" : "error",
        title: result.success ? "สำเร็จ" : "เกิดข้อผิดพลาด",
        text: result.message,
        timer: result.success ? 1800 : 3000,
        showConfirmButton: false,
        allowOutsideClick: false,
      }).then(() => {
        if (result.success && successRedirect) {
          window.location.reload(); // หรือ window.location.href = "profile.php"
        }
      });
    } catch (error) {
      Swal.fire({
        icon: "error",
        title: "เกิดข้อผิดพลาด",
        text: "ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้ กรุณาลองใหม่",
      });
    }
  });
}
// เรียกใช้ทั้งสองฟอร์ม
handleFormSubmit("profileForm", true);
handleFormSubmit("changePasswordForm", true);

// Dropdown แผนกในโปรไฟล์
document.addEventListener("click", function (e) {
  const item = e.target.closest(".dept-select");
  if (!item) return;
  e.preventDefault();

  const dropdown = item.closest(".dropdown");
  if (!dropdown) return;

  const menu = item.closest(".dropdown-menu");
  if (menu) {
    menu.querySelectorAll(".dropdown-item").forEach((el) => {
      el.classList.remove("active");
    });
  }

  item.classList.add("active");

  const value = item.dataset.value || "";
  const label = dropdown.querySelector(".dept-label");
  const input = dropdown.querySelector(".dept-input");

  if (input) input.value = value;
  if (label) label.textContent = item.textContent.trim();
});
