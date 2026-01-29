// manage_users.js

document.addEventListener("DOMContentLoaded", function () {
  const csrfToken = window.csrfToken || "";

  const showToast = (type, message) => {
    if (!message) return;
    Swal.fire({
      icon: type === "success" ? "success" : "error",
      text: message,
      toast: true,
      position: "top-end",
      showConfirmButton: false,
      timer: 3500,
      timerProgressBar: true,
      didOpen: (toast) => {
        toast.addEventListener("mouseenter", Swal.stopTimer);
        toast.addEventListener("mouseleave", Swal.resumeTimer);
      },
    });
  };
  // ────────────────────────────────────────────────
  // 1. Toggle แสดง/ซ่อนรหัสผ่าน (โค้ดเดิม)
  // ────────────────────────────────────────────────
  const initPasswordToggles = (root = document) => {
    root.querySelectorAll(".password-wrapper").forEach((wrapper) => {
    const input = wrapper.querySelector("input");
    const toggle = wrapper.querySelector(".toggle-password");
    if (!input || !toggle) return;

    const icon = toggle.querySelector("i");

    const syncToggle = () => {
      if (input.value.length > 0) {
        toggle.classList.remove("d-none");
      } else {
        toggle.classList.add("d-none");
        input.setAttribute("type", "password");
        if (icon) {
          icon.classList.add("bi-eye-slash");
          icon.classList.remove("bi-eye");
        }
      }
    };

    input.addEventListener("input", syncToggle);
    toggle.addEventListener("click", function () {
      const type = input.getAttribute("type") === "password" ? "text" : "password";
      input.setAttribute("type", type);
      if (icon) {
        icon.classList.toggle("bi-eye");
        icon.classList.toggle("bi-eye-slash");
      }
    });

      syncToggle();
    });
  };

  initPasswordToggles();

  // ────────────────────────────────────────────────
  // 2. แสดงแจ้งเตือนจาก PHP session แบบ TOAST (มุมขวาบน หายเอง)
  // ────────────────────────────────────────────────
  const toastElement = document.querySelector("#toast-data");
  if (toastElement) {
    const type = toastElement.dataset.type;
    const message = toastElement.dataset.message;

    if (type && message) {
      const isSuccess = type === "success";

      showToast(isSuccess ? "success" : "error", message);
    }
  }

  // ────────────────────────────────────────────────
  // 3. จัดการปุ่มลบด้วย SweetAlert2
  // ────────────────────────────────────────────────
  document.querySelectorAll(".btn-delete").forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault();

      const username = this.getAttribute("data-username");
      const userId = this.getAttribute("data-user-id");
      const token = this.getAttribute("data-csrf") || csrfToken;

      Swal.fire({
        title: "ยืนยันการลบ?",
        html: `<strong class="text-danger">ผู้ใช้ : ${username}</strong><br><small class="text-muted">การกระทำนี้ไม่สามารถกู้คืนได้</small>`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#dc3545",
        cancelButtonColor: "#6c757d",
        confirmButtonText: '<i class="bi bi-trash3 me-1"></i> ยืนยันลบ',
        cancelButtonText: "ยกเลิก",
        reverseButtons: true,
        allowOutsideClick: false,
      }).then(async (result) => {
        if (!result.isConfirmed) return;
        try {
          const body = new URLSearchParams({
            action: "delete_user",
            user_id: userId,
            csrf_token: token,
          });
          const res = await fetch("manage_users.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded",
              "X-Requested-With": "XMLHttpRequest",
              Accept: "application/json",
            },
            body,
          });
          const data = await res.json();
          if (!res.ok || !data.success) {
            throw new Error(data.error || "ลบไม่สำเร็จ");
          }
          const row = document.querySelector(`tr[data-user-id="${userId}"]`);
          row?.remove();
          showToast("success", "ลบผู้ใช้เรียบร้อย");
        } catch (err) {
          showToast("error", err.message);
        }
      });
    });
  });

  // ────────────────────────────────────────────────
  // 3.1 Update Role แบบไม่รีเฟรช
  // ────────────────────────────────────────────────
  document.querySelectorAll(".role-toggle").forEach((toggle) => {
    toggle.addEventListener("change", async function () {
      const form = this.closest("form");
      if (!form) return;

      const userId = form.querySelector('input[name="user_id"]')?.value || "";
      const token = form.querySelector('input[name="csrf_token"]')?.value || csrfToken;
      const role = this.checked ? "admin" : "user";

      try {
        const body = new URLSearchParams({
          action: "update_role",
          user_id: userId,
          role,
          csrf_token: token,
        });
        const res = await fetch("manage_users.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            "X-Requested-With": "XMLHttpRequest",
            Accept: "application/json",
          },
          body,
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
          throw new Error(data.error || "อัปเดตไม่สำเร็จ");
        }
        showToast("success", "อัปเดตบทบาทผู้ใช้เรียบร้อย");
      } catch (err) {
        this.checked = !this.checked;
        showToast("error", err.message);
      }
    });
  });

  // ────────────────────────────────────────────────
  // 3.2 Create User แบบไม่รีเฟรช
  // ────────────────────────────────────────────────
  const createForm = document.querySelector(".create-user-form");
  if (createForm) {
    createForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        const res = await fetch("manage_users.php", {
          method: "POST",
          headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
          body: new FormData(createForm),
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
          throw new Error(data.error || "สร้างผู้ใช้ไม่สำเร็จ");
        }

        const user = data.user;
        const tbody = document.querySelector("table tbody");
        if (tbody && user) {
          const deptLabel = user.department_name || "— ไม่ระบุ —";
          const modalId = `editModal${user.id}`;
          const row = document.createElement("tr");
          row.setAttribute("data-user-id", user.id);
          row.innerHTML = `
            <td>${user.id}</td>
            <td class="text-center"><div class="profile-placeholder"></div></td>
            <td class="cell-username">${user.username}</td>
            <td class="cell-name">${user.name}</td>
            <td class="cell-email">${user.email}</td>
            <td class="text-center">
              <form method="post" class="d-inline role-form">
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="csrf_token" value="${csrfToken}">
                <input type="hidden" name="user_id" value="${user.id}">
                <input type="hidden" name="role" value="user">
                <div class="form-switch d-flex align-items-center justify-content-center">
                  <input class="form-check-input role-toggle" type="checkbox" name="role" value="admin">
                  <span class="ms-2 small text-muted">Admin</span>
                </div>
              </form>
            </td>
            <td class="text-center">
              <div class="d-flex justify-content-center">
                <button type="button" class="btn btn-link p-0 me-2" data-bs-toggle="modal" data-bs-target="#${modalId}">
                  <i class="bi bi-pencil-square fs-5"></i>
                </button>
                <button type="button" class="btn btn-link p-0 btn-delete"
                  data-username="${user.username}"
                  data-user-id="${user.id}"
                  data-csrf="${csrfToken}">
                  <i class="bi bi-trash fs-5 text-danger"></i>
                </button>
              </div>
              ${buildEditModal(user, deptLabel, modalId)}
            </td>
          `;
          tbody.appendChild(row);
          bindDynamicRow(row);
          initPasswordToggles(row);
        }

        const modal = bootstrap.Modal.getInstance(document.getElementById("createUserModal"));
        modal?.hide();
        createForm.reset();
        showToast("success", "สร้างผู้ใช้ใหม่เรียบร้อย");
      } catch (err) {
        showToast("error", err.message);
      }
    });
  }

  // ────────────────────────────────────────────────
  // 3.3 Edit User แบบไม่รีเฟรช
  // ────────────────────────────────────────────────
  document.querySelectorAll(".edit-user-form").forEach((form) => {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        const res = await fetch("manage_users.php", {
          method: "POST",
          headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
          body: new FormData(form),
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
          throw new Error(data.error || "แก้ไขไม่สำเร็จ");
        }

        const user = data.user;
        const row = document.querySelector(`tr[data-user-id="${user.id}"]`);
        if (row) {
          row.querySelector(".cell-name").textContent = user.name;
          row.querySelector(".cell-email").textContent = user.email;
        }

        const modal = bootstrap.Modal.getInstance(form.closest(".modal"));
        modal?.hide();
        showToast("success", "แก้ไขข้อมูลผู้ใช้เรียบร้อย");
      } catch (err) {
        showToast("error", err.message);
      }
    });
  });

  function buildEditModal(user, deptLabel, modalId) {
    const deptItems = (window.departments || [])
      .map(
        (d) => `<li>
          <a class="dropdown-item dept-select-edit ${String(d.id) === String(user.department_id) ? "active" : ""}"
            href="#" data-value="${d.id}">
            ${d.name}
          </a>
        </li>`,
      )
      .join("");

    return `
      <div class="modal fade" id="${modalId}" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-primary text-white">
              <h5 class="modal-title"><i class="bi bi-person-fill-gear me-2"></i>แก้ไขผู้ใช้: ${user.username}</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <form method="post" class="edit-user-form" data-user-id="${user.id}">
                <input type="hidden" name="edit_user_id" value="${user.id}">
                <input type="hidden" name="csrf_token" value="${csrfToken}">

                <div class="mb-3">
                  <label class="form-label">ชื่อ-นามสกุล</label>
                  <div class="field-with-icon">
                    <i class="bi bi-person-badge field-icon"></i>
                    <input type="text" name="name" class="form-control" value="${user.name}" required>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">อีเมล</label>
                  <div class="field-with-icon">
                    <i class="bi bi-envelope field-icon"></i>
                    <input type="email" name="email" class="form-control" value="${user.email}" required>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">แผนก</label>
                  <div class="dropdown">
                    <button class="btn btn-white border d-flex align-items-center justify-content-between gap-2 custom-filter-btn w-100"
                      type="button" data-bs-toggle="dropdown">
                      <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-diagram-3-fill text-primary"></i>
                        <span class="dept-label">${deptLabel}</span>
                      </div>
                      <i class="bi bi-chevron-down small text-muted"></i>
                    </button>
                    <ul class="dropdown-menu shadow animate-slide w-100">
                      <li>
                        <a class="dropdown-item dept-select-edit ${!user.department_id ? "active" : ""}"
                          href="#" data-value="">— ไม่ระบุ —</a>
                      </li>
                      <li><hr class="dropdown-divider"></li>
                      ${deptItems}
                    </ul>
                    <input type="hidden" name="department_id" class="dept-input" value="${user.department_id || ""}">
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">รหัสผ่านใหม่ (ถ้าต้องการรีเซ็ต)</label>
                  <div class="field-with-icon password-wrapper">
                    <i class="bi bi-lock field-icon"></i>
                    <input type="password" name="new_password" class="form-control" minlength="6">
                    <span class="toggle-password d-none">
                      <i class="bi bi-eye-slash"></i>
                    </span>
                  </div>
                  <small class="text-muted">ปล่อยว่างถ้าไม่ต้องการเปลี่ยน</small>
                </div>

                <button type="submit" class="btn btn-primary w-100">บันทึกการแก้ไข</button>
              </form>
            </div>
          </div>
        </div>
      </div>`;
  }

  function bindDynamicRow(row) {
    row.querySelectorAll(".btn-delete").forEach((button) => {
      button.addEventListener("click", function (e) {
        e.preventDefault();
        const username = this.getAttribute("data-username");
        const userId = this.getAttribute("data-user-id");
        const token = this.getAttribute("data-csrf") || csrfToken;

        Swal.fire({
          title: "ยืนยันการลบ?",
          html: `<strong class="text-danger">ผู้ใช้ : ${username}</strong><br><small class="text-muted">การกระทำนี้ไม่สามารถกู้คืนได้</small>`,
          icon: "warning",
          showCancelButton: true,
          confirmButtonColor: "#dc3545",
          cancelButtonColor: "#6c757d",
          confirmButtonText: '<i class="bi bi-trash3 me-1"></i> ยืนยันลบ',
          cancelButtonText: "ยกเลิก",
          reverseButtons: true,
          allowOutsideClick: false,
        }).then(async (result) => {
          if (!result.isConfirmed) return;
          try {
            const body = new URLSearchParams({
              action: "delete_user",
              user_id: userId,
              csrf_token: token,
            });
            const res = await fetch("manage_users.php", {
              method: "POST",
              headers: {
                "Content-Type": "application/x-www-form-urlencoded",
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
              },
              body,
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
              throw new Error(data.error || "ลบไม่สำเร็จ");
            }
            row.remove();
            showToast("success", "ลบผู้ใช้เรียบร้อย");
          } catch (err) {
            showToast("error", err.message);
          }
        });
      });
    });

    row.querySelectorAll(".role-toggle").forEach((toggle) => {
      toggle.addEventListener("change", async function () {
        const form = this.closest("form");
        if (!form) return;
        const userId = form.querySelector('input[name="user_id"]')?.value || "";
        const token = form.querySelector('input[name="csrf_token"]')?.value || csrfToken;
        const role = this.checked ? "admin" : "user";

        try {
          const body = new URLSearchParams({
            action: "update_role",
            user_id: userId,
            role,
            csrf_token: token,
          });
          const res = await fetch("manage_users.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded",
              "X-Requested-With": "XMLHttpRequest",
              Accept: "application/json",
            },
            body,
          });
          const data = await res.json();
          if (!res.ok || !data.success) {
            throw new Error(data.error || "อัปเดตไม่สำเร็จ");
          }
          showToast("success", "อัปเดตบทบาทผู้ใช้เรียบร้อย");
        } catch (err) {
          this.checked = !this.checked;
          showToast("error", err.message);
        }
      });
    });
  }

  // ────────────────────────────────────────────────
  // 4. Dropdown แผนก (สร้าง/แก้ไขผู้ใช้)
  // ────────────────────────────────────────────────
  document.addEventListener("click", function (e) {
    const item = e.target.closest(".dept-select-create, .dept-select-edit");
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
});
