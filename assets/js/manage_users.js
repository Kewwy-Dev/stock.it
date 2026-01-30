// manage_users.js

document.addEventListener("DOMContentLoaded", function () {
  const csrfToken = window.csrfToken || "";
  const noUserCards = document.getElementById("noUserCards");
  const userCards = document.getElementById("userCards");

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

  const updateEmptyCards = () => {
    if (!userCards || !noUserCards) return;
    const hasCards = userCards.querySelector(".user-card");
    noUserCards.style.display = hasCards ? "none" : "block";
  };

  const updateRoleUI = (userId, role) => {
    const isAdmin = role === "admin";
    document
      .querySelectorAll(`.user-card[data-user-id="${userId}"] .user-role-badge`)
      .forEach((badge) => {
        badge.textContent = isAdmin ? "แอดมิน" : "ผู้ใช้";
        badge.classList.toggle("badge-admin", isAdmin);
        badge.classList.toggle("badge-user", !isAdmin);
      });

    document
      .querySelectorAll(
        `tr[data-user-id="${userId}"] .role-toggle, .user-card[data-user-id="${userId}"] .role-toggle`,
      )
      .forEach((toggle) => {
        if (toggle.disabled) return;
        toggle.checked = isAdmin;
      });
  };

  const removeUserUI = (userId) => {
    const row = document.querySelector(`tr[data-user-id="${userId}"]`);
    row?.remove();
    const card = document.querySelector(`.user-card[data-user-id="${userId}"]`);
    card?.remove();
    ensureTableEmptyState();
    updateEmptyCards();
    updateScrollHeights();
  };

  const ensureTableEmptyState = () => {
    const tbody = document.querySelector("table tbody");
    if (!tbody) return;
    const hasRow = tbody.querySelector("tr");
    if (!hasRow) {
      const emptyRow = document.createElement("tr");
      emptyRow.innerHTML = `<td colspan="7" class="text-center text-muted py-4">ไม่มีผู้ใช้ในระบบ</td>`;
      tbody.appendChild(emptyRow);
    }
  };

  const clearTableEmptyState = () => {
    const tbody = document.querySelector("table tbody");
    if (!tbody) return;
    const emptyCell = tbody.querySelector('tr td[colspan="7"]');
    emptyCell?.closest("tr")?.remove();
  };

  const updateScrollHeights = () => {
    const tableWrap = document.querySelector(".manage-users-table-wrap");
    const tbody = document.querySelector("table tbody");
    const thead = document.querySelector("table thead");
    const cards = document.getElementById("userCards");

    const isDesktop = window.matchMedia("(min-width: 1201px)").matches;
    const isMobile = window.matchMedia("(max-width: 576px)").matches;
    const isTablet = !isDesktop && !isMobile;

    if (tableWrap && tbody && thead && isDesktop) {
      const rows = Array.from(tbody.querySelectorAll("tr")).filter(
        (tr) => !tr.querySelector('td[colspan="7"]'),
      );
      if (rows.length === 0) {
        tableWrap.style.maxHeight = "none";
      } else {
        const rowH = rows[0].getBoundingClientRect().height || 56;
        const headH = thead.getBoundingClientRect().height || 48;
        tableWrap.style.maxHeight = `${headH + rowH * 8}px`;
      }
    } else if (tableWrap) {
      tableWrap.style.maxHeight = "none";
    }

    if (cards && !isDesktop) {
      const card = cards.querySelector(".user-card");
      const header = card?.querySelector(".user-card-header");
      if (!card || !header) {
        cards.style.maxHeight = "none";
      } else {
        const cardStyles = getComputedStyle(card);
        const padTop = parseFloat(cardStyles.paddingTop) || 0;
        const padBottom = parseFloat(cardStyles.paddingBottom) || 0;
        const borderTop = parseFloat(cardStyles.borderTopWidth) || 0;
        const borderBottom = parseFloat(cardStyles.borderBottomWidth) || 0;
        const headerH = header.getBoundingClientRect().height || 0;
        const collapsedH = headerH + padTop + padBottom + borderTop + borderBottom;
        const gap = parseFloat(getComputedStyle(cards).rowGap || "0") || 0;
        const isTabletPortrait =
          !isMobile &&
          window.matchMedia("(min-width: 577px) and (max-width: 1200px)").matches &&
          window.matchMedia("(orientation: portrait)").matches;
        const isTabletLandscape =
          !isMobile &&
          window.matchMedia("(min-width: 577px) and (max-width: 1200px)").matches &&
          window.matchMedia("(orientation: landscape)").matches;
        const count = isMobile ? 6 : isTabletPortrait ? 10 : isTabletLandscape ? 6 : 5;
        cards.style.maxHeight = `${collapsedH * count + gap * (count - 1)}px`;
      }
    } else if (cards) {
      cards.style.maxHeight = "none";
    }
  };

  const bindCardToggle = (card) => {
    const header = card.querySelector(".user-card-header");
    const toggleBtn = card.querySelector(".user-card-toggle");
    const targetSel = header?.getAttribute("data-target");
    const details = targetSel ? card.querySelector(targetSel) : null;
    if (!header || !details) return;

    const collapse = bootstrap.Collapse.getOrCreateInstance(details, { toggle: false });
    const setState = (isOpen) => {
      card.classList.toggle("open", isOpen);
      if (toggleBtn) toggleBtn.setAttribute("aria-expanded", String(isOpen));
      header.setAttribute("aria-expanded", String(isOpen));
    };

    header.addEventListener("click", (e) => {
      if (e.target.closest(".user-card-toggle")) {
        e.preventDefault();
      }
      collapse.toggle();
    });

    details.addEventListener("show.bs.collapse", () => setState(true));
    details.addEventListener("hide.bs.collapse", () => setState(false));
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
  updateEmptyCards();
  document.querySelectorAll(".user-card").forEach((card) => bindCardToggle(card));
  updateScrollHeights();

  let resizeTimer;
  window.addEventListener("resize", () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(updateScrollHeights, 150);
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
          removeUserUI(userId);
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
        updateRoleUI(userId, role);
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
        const deptLabel = user.department_name || "— ไม่ระบุ —";
        const modalId = `editModal${user.id}`;
        if (tbody && user) {
          clearTableEmptyState();
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
        if (userCards && user) {
          const card = document.createElement("div");
          card.className = "user-card";
          card.setAttribute("data-user-id", user.id);
          card.innerHTML = buildUserCard(user, deptLabel, modalId);
          userCards.appendChild(card);
          bindDynamicCard(card);
          updateEmptyCards();
          updateScrollHeights();
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
        const card = document.querySelector(`.user-card[data-user-id="${user.id}"]`);
        if (card) {
          const nameEl = card.querySelector(".user-name");
          const emailEl = card.querySelector(".user-email");
          const deptEl = card.querySelector(".user-dept");
          if (nameEl) nameEl.textContent = user.name;
          if (emailEl) emailEl.textContent = user.email;
          if (deptEl) deptEl.textContent = user.department_name || "— ไม่ระบุ —";
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

  function buildUserCard(user, deptLabel, modalId) {
    const role = user.role || "user";
    const isAdmin = role === "admin";
    const badgeClass = isAdmin ? "badge-admin" : "badge-user";
    const badgeText = isAdmin ? "แอดมิน" : "ผู้ใช้";
    const detailId = `userDetails${user.id}`;
    return `
      <div class="user-card-header" data-target="#${detailId}" role="button" aria-expanded="false" aria-controls="${detailId}">
        <span class="user-id">#${user.id}</span>
        <div class="user-avatar">
          <div class="profile-placeholder"></div>
        </div>
        <div class="user-main">
          <div class="user-username">${user.username}</div>
          <div class="user-name">${user.name}</div>
        </div>
        <span class="badge ${badgeClass} user-role-badge">${badgeText}</span>
        <button type="button" class="btn btn-link p-0 user-card-toggle" aria-expanded="false" aria-controls="${detailId}">
          <i class="bi bi-chevron-down"></i>
        </button>
      </div>
      <div id="${detailId}" class="user-card-details collapse">
        <div class="user-card-body">
          <div class="user-info-row">
            <div class="user-info-label">อีเมล</div>
            <div class="user-info-value user-email">${user.email}</div>
          </div>
          <div class="user-info-row">
            <div class="user-info-label">แผนก</div>
            <div class="user-info-value user-dept">${deptLabel || "— ไม่ระบุ —"}</div>
          </div>
        </div>
        <div class="user-card-actions">
          <form method="post" class="d-inline role-form">
            <input type="hidden" name="action" value="update_role">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
            <input type="hidden" name="user_id" value="${user.id}">
            <input type="hidden" name="role" value="user">
            <div class="form-switch d-flex align-items-center">
              <input class="form-check-input role-toggle" type="checkbox" name="role" value="admin" ${isAdmin ? "checked" : ""}>
              <span class="ms-2 small text-muted">Admin</span>
            </div>
          </form>
          <div class="d-flex align-items-center">
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
        </div>
      </div>
    `;
  }

  function bindDynamicCard(card) {
    bindCardToggle(card);
    card.querySelectorAll(".btn-delete").forEach((button) => {
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
            removeUserUI(userId);
            showToast("success", "ลบผู้ใช้เรียบร้อย");
          } catch (err) {
            showToast("error", err.message);
          }
        });
      });
    });

    card.querySelectorAll(".role-toggle").forEach((toggle) => {
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
          updateRoleUI(userId, role);
          showToast("success", "อัปเดตบทบาทผู้ใช้เรียบร้อย");
        } catch (err) {
          this.checked = !this.checked;
          showToast("error", err.message);
        }
      });
    });
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
            removeUserUI(userId);
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
          updateRoleUI(userId, role);
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
