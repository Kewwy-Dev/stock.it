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