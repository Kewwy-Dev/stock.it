// ============================================================================
//   ฟังก์ชันช่วย escape HTML (ป้องกัน XSS ใน preview)
// ============================================================================
function escapeHtml(text) {
  const map = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
  };
  return (text || "").replace(/[&<>"']/g, (m) => map[m]);
}

// ============================================================================
//   แสดงตัวอย่างข้อมูลนำเข้า
// ============================================================================
function showImportPreview(rows, totalCount) {
  const tbody = document.querySelector("#previewTable tbody");
  if (!tbody) return;

  tbody.innerHTML = "";

  rows.forEach((row) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${escapeHtml(row.name || "-")}</td>
      <td>${escapeHtml(row.dept || "-")}</td>
    `;
    tbody.appendChild(tr);
  });

  const countElement = document.getElementById("importCount");
  if (countElement) {
    countElement.textContent = `รวมทั้งหมด ${totalCount} รายการ`;
  }

  const previewBox = document.getElementById("importPreview");
  if (previewBox) {
    previewBox.classList.remove("d-none");
  }
}

// ============================================================================
//   ค้นหารายชื่อพนักงานในรายการ
// ============================================================================
const employeeSearch = document.getElementById("employeeSearch");
const employeeList = document.getElementById("employee-list");
const employeeSearchEmpty = document.getElementById("employeeSearchEmpty");

function normalizeSearchText(text) {
  return (text || "")
    .toString()
    .toLowerCase()
    .replace(/\s+/g, " ")
    .trim();
}

function filterEmployeeList() {
  if (!employeeSearch || !employeeList) return;

  const query = normalizeSearchText(employeeSearch.value);
  const items = employeeList.querySelectorAll(".list-group-item");
  let matchCount = 0;

  items.forEach((item) => {
    if (item.id === "employeeSearchEmpty") return;
    const text = normalizeSearchText(item.textContent);
    const isMatch = query === "" || text.includes(query);
    item.classList.toggle("d-none", !isMatch);
    if (isMatch) matchCount += 1;
  });

  if (employeeSearchEmpty) {
    const showEmpty = query !== "" && matchCount === 0;
    employeeSearchEmpty.classList.toggle("d-none", !showEmpty);
  }

  if (query !== "" && employeeList.classList.contains("d-none")) {
    employeeList.classList.remove("d-none");
    const toggleBtn = document.querySelector(
      '.toggle-list-btn[data-target="employee-list"]',
    );
    const icon = toggleBtn?.querySelector("i");
    if (icon) {
      icon.classList.remove("bi-eye-slash");
      icon.classList.add("bi-eye");
    }
  }
}

if (employeeSearch) {
  employeeSearch.addEventListener("input", filterEmployeeList);
  employeeSearch.addEventListener("search", filterEmployeeList);
}

// ============================================================================
//   โค้ดส่วนอื่น ๆ เดิม (ลบ, toast, openAdd, openEdit) ยังคงเหมือนเดิม
// ============================================================================

let deleteModal = null;
const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");

function notify(type, message) {
  if (typeof Toast !== "undefined") {
    Toast.fire({
      icon: type === "success" ? "success" : "error",
      title: message,
      timer: 2500,
    });
    return;
  }

  if (typeof Swal !== "undefined") {
    Swal.fire({
      icon: type === "success" ? "success" : "error",
      text: message,
      timer: 2500,
      showConfirmButton: false,
    });
    return;
  }

  alert(message);
}

function buildEmployeeItem(emp) {
  const wrapper = document.createElement("div");
  wrapper.className =
    "list-group-item d-flex justify-content-between align-items-center";

  const deptName = emp.department_name
    ? emp.department_name
    : "ยังไม่ระบุแผนก";

  wrapper.innerHTML = `
    <div>
      <strong>${escapeHtml(emp.name)}</strong><br>
      <small class="text-muted">${escapeHtml(deptName)}</small>
    </div>
    <div>
      <button class="btn btn-sm btn-outline-primary me-1"
        onclick='openEdit(${emp.id},"${String(emp.name).replace(/"/g, "&quot;").replace(/'/g, "\\'")}",${emp.department_id || "null"})'>
        <i class="bi bi-pencil-square me-1"></i>แก้ไข
      </button>
      <button type="button" class="btn btn-sm btn-outline-danger delete-btn"
        data-type="emp"
        data-id="${emp.id}"
        data-name="${String(emp.name).replace(/"/g, "&quot;").replace(/'/g, "\\'")}">
        <i class="bi bi-trash"></i>
      </button>
    </div>
  `;

  return wrapper;
}

function buildSimpleItem(type, id, name) {
  const wrapper = document.createElement("div");
  wrapper.className = "list-group-item";
  wrapper.innerHTML = `
    <span>${escapeHtml(name)}</span>
    <button type="button" class="btn btn-sm btn-outline-danger delete-btn"
      data-type="${type}"
      data-id="${id}"
      data-name="${String(name).replace(/"/g, "&quot;").replace(/'/g, "\\'")}">
      <i class="bi bi-trash"></i>
    </button>
  `;
  return wrapper;
}

// ฟังก์ชันเดิม (เพิ่ม/แก้ไขพนักงาน)
function openAdd() {
  document.getElementById("empTitle").innerHTML =
    '<i class="bi bi-person-fill-add"></i> เพิ่มพนักงาน';
  document.getElementById("empTitle").style.color = "white";
  document.getElementById("empAction").value = "add";
  document.getElementById("empId").value = "";
  document.querySelector('#empModal input[name="name"]').value = "";
  const dropdown = document.querySelector("#empModal .dropdown");
  if (dropdown) {
    const label = dropdown.querySelector(".dept-label");
    const input = dropdown.querySelector(".dept-input");
    if (label) label.textContent = "— ไม่ระบุแผนก —";
    if (input) input.value = "";
    dropdown
      .querySelectorAll(".dept-select-emp")
      .forEach((el) => el.classList.remove("active"));
    dropdown.querySelector(".dept-select-emp")?.classList.add("active");
  }
}

function openEdit(id, name, dept) {
  document.getElementById("empTitle").innerHTML =
    '<i class="bi bi-person-fill-gear"></i> แก้ไขพนักงาน';
  document.getElementById("empTitle").style.color = "white";
  document.getElementById("empAction").value = "edit";
  document.getElementById("empId").value = id;
  document.querySelector('#empModal input[name="name"]').value = name;
  const dropdown = document.querySelector("#empModal .dropdown");
  if (dropdown) {
    const label = dropdown.querySelector(".dept-label");
    const input = dropdown.querySelector(".dept-input");
    const value = dept || "";
    if (input) input.value = value;

    dropdown
      .querySelectorAll(".dept-select-emp")
      .forEach((el) => el.classList.remove("active"));

    const activeItem = dropdown.querySelector(
      `.dept-select-emp[data-value="${value}"]`,
    );
    if (activeItem) {
      activeItem.classList.add("active");
      if (label) label.textContent = activeItem.textContent.trim();
    } else if (label) {
      label.textContent = "— ไม่ระบุแผนก —";
    }
  }
  new bootstrap.Modal("#empModal").show();
}

// Toast
document.addEventListener("DOMContentLoaded", function () {
  const toastData = document.documentElement.dataset.toast;
  if (toastData) {
    const d = JSON.parse(toastData);
    const t = document.createElement("div");
    t.className = `toast align-items-center text-bg-${d.type === "error" ? "danger" : "success"} border-0`;
    t.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${d.message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>`;
    document.querySelector(".toast-container").appendChild(t);
    new bootstrap.Toast(t, {
      delay: 2000,
    }).show();
  }
});

// จัดการปุ่มลบด้วย SweetAlert2
document.addEventListener("click", function (e) {
  const btn = e.target.closest(".delete-btn");
  if (!btn) return;

  e.preventDefault();

  const type = btn.dataset.type;
  const id = btn.dataset.id;
  const name = btn.dataset.name;

  if (!type || !id || !name) {
    console.error("Missing delete data attributes");
    return;
  }

  let typeText = "";
  let warningText = "";

  if (type === "dept") {
    typeText = "แผนก";
    warningText = "หากมีพนักงานอยู่ในแผนกนี้ อาจทำให้ข้อมูลพนักงานไม่สมบูรณ์";
  } else if (type === "company") {
    typeText = "บริษัท";
    warningText = "การลบจะไม่กระทบข้อมูลอื่น ๆ";
  } else if (type === "emp") {
    typeText = "พนักงาน";
    warningText = "การลบนี้ไม่สามารถกู้คืนได้";
  } else {
    console.error("Unknown delete type:", type);
    return;
  }

  Swal.fire({
    title: `ยืนยันการลบ${typeText}`,
    html: `
      <strong class="text-danger">${name}</strong><br>
      <small class="text-muted">${warningText}</small>
    `,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#dc3545",
    cancelButtonColor: "#6c757d",
    confirmButtonText: '<i class="bi bi-trash-fill me-2"></i>ยืนยัน',
    cancelButtonText: 'ยกเลิก',
    reverseButtons: true,
  }).then((result) => {
    if (result.isConfirmed) {
      const body = new URLSearchParams();
      body.set("csrf_token", window.csrfToken || "");
      body.set("delete_type", type);
      body.set("delete_id", id);

      fetch("manage_employees.php", {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        body,
      })
        .then((res) => res.json())
        .then((data) => {
          if (!data.success) {
            notify("error", data.message || "ลบไม่สำเร็จ");
            return;
          }

          if (type === "emp") {
            btn.closest(".list-group-item")?.remove();
          } else if (type === "dept") {
            btn.closest(".list-group-item")?.remove();
          } else if (type === "company") {
            btn.closest(".list-group-item")?.remove();
          }

          filterEmployeeList();
          notify("success", data.message || "ลบสำเร็จ");
        })
        .catch(() => notify("error", "เชื่อมต่อเซิร์ฟเวอร์ไม่ได้"));
    }
  });
});

// ============================================================================
//   นำเข้าพนักงานจากไฟล์ .xlsx / .xls / .csv (ใช้ SheetJS)
// ============================================================================

document
  .getElementById("importCsvForm")
  ?.addEventListener("submit", async function (e) {
    e.preventDefault();

    const fileInput = document.getElementById("csvFile");
    const file = fileInput.files[0];

    if (!file) {
      Toast.fire({
        icon: "error",
        text: "กรุณาเลือกไฟล์ .xlsx, .xls หรือ .csv ก่อน",
        timer: 3500,
      });
      return;
    }

    const ext = file.name.split(".").pop().toLowerCase();
    const allowed = ["xlsx", "xls", "csv"];

    if (!allowed.includes(ext)) {
      Toast.fire({
        icon: "error",
        text: "รองรับเฉพาะไฟล์ .xlsx, .xls, .csv เท่านั้น",
        timer: 3500,
      });
      return;
    }

    const reader = new FileReader();

    reader.onload = async function (event) {
      try {
        let workbook;

        if (ext === "csv") {
          workbook = XLSX.read(event.target.result, { type: "string" });
        } else {
          workbook = XLSX.read(event.target.result, { type: "array" });
        }

        const sheetName = workbook.SheetNames[0];
        const worksheet = workbook.Sheets[sheetName];

        const rows = XLSX.utils.sheet_to_json(worksheet, {
          header: 1,
          defval: "",
          raw: false,
        });

        if (rows.length < 1) {
          Toast.fire({
            icon: "error",
            text: "ไฟล์ไม่มีข้อมูล",
            timer: 3500,
          });
          return;
        }

        const header = rows[0].map((h) =>
          (h || "").toString().trim().toLowerCase(),
        );

        let nameIndex = -1;
        let deptIndex = -1;

        header.forEach((col, i) => {
          const text = col;
          if (
            [
              "ชื่อ",
              "name",
              "ชื่อ-สกุล",
              "fullname",
              "พนักงาน",
              "emp_name",
            ].some((k) => text.includes(k))
          ) {
            nameIndex = i;
          }
          if (
            [
              "แผนก",
              "department",
              "dept",
              "section",
              "แผนกงาน",
              "department_name",
            ].some((k) => text.includes(k))
          ) {
            deptIndex = i;
          }
        });

        if (nameIndex === -1 || deptIndex === -1) {
          Toast.fire({
            icon: "error",
            text: "ไม่พบคอลัมน์ 'ชื่อ'/'แผนก' ในแถวแรก\nกรุณาตรวจสอบ header แล้วลองใหม่อีกครั้ง",
            timer: 3500,
          });
          return;
        }

        const employees = [];
        const previewData = [];

        for (let i = 1; i < rows.length; i++) {
          const row = rows[i];
          const name = (row[nameIndex] || "").toString().trim();
          const dept = (row[deptIndex] || "").toString().trim();

          if (!name) continue;

          employees.push({
            name: name,
            department_name: dept,
          });

          if (previewData.length < 10) {
            previewData.push({ name, dept });
          }
        }

        if (employees.length === 0) {
          Toast.fire({
            icon: "error",
            text: "ไม่พบข้อมูลพนักงานในไฟล์",
            timer: 3500,
          });
          return;
        }

        // แสดงตัวอย่างก่อนถามยืนยัน
        showImportPreview(previewData, employees.length);

        // ยืนยันก่อนบันทึกด้วย SweetAlert2
        Swal.fire({
          title: "ยืนยันการนำเข้าข้อมูล",
          html: `พบข้อมูลพนักงาน <strong>${employees.length}</strong> รายการ<br>ต้องการนำเข้าทั้งหมดใช่หรือไม่?`,
          icon: "question",
          showCancelButton: true,
          confirmButtonColor: "#198754",
          cancelButtonColor: "#6c757d",
          confirmButtonText:
            '<i class="bi bi-check-circle-fill me-2"></i> ใช่, นำเข้าเลย',
          cancelButtonText: '<i class="bi bi-x-circle me-2"></i> ยกเลิก',
          reverseButtons: true,
          allowOutsideClick: false,
        }).then(async (result) => {
          if (!result.isConfirmed) return;

          Swal.fire({
            title: "กำลังนำเข้าข้อมูล...",
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
          });

          console.log(
            "Sending to server:",
            JSON.stringify(
              {
                action: "import_employees",
                csrf_token: window.csrfToken || "",
                employees: employees,
              },
              null,
              2,
            ),
          );

          try {
            const response = await fetch("manage_employees.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                action: "import_employees",
                csrf_token:
                  window.csrfToken ||
                  document.querySelector('[name="csrf_token"]')?.value ||
                  "",
                employees: employees,
              }),
            });

            const text = await response.text();
            let resultData;

            try {
              resultData = JSON.parse(text);
            } catch (jsonErr) {
              console.log("Server response (raw):", text.substring(0, 800));
              Swal.close();
              Swal.fire({
                title: "ข้อผิดพลาด",
                html: "เซิร์ฟเวอร์ตอบกลับไม่ใช่ JSON<br>ดู Console เพื่อดูรายละเอียดเพิ่มเติม",
                icon: "error",
                confirmButtonText: "ตกลง",
              });
              return;
            }

            Swal.close();

            if (resultData.success) {
              let successMsg = `เพิ่มพนักงานใหม่ <strong>${resultData.inserted}</strong> รายการ`;

              // แสดงจำนวนรายการที่ข้าม (ถ้ามี)
              if (resultData.skipped > 0) {
                successMsg += `<br><small class="text-muted">ข้ามข้อมูลที่ซ้ำกันในระบบ <strong>${resultData.skipped}</strong> รายการ</small>`;
              }

              Swal.fire({
                title: "นำเข้าสำเร็จ!",
                html: successMsg,
                icon: "success",
                confirmButtonText: "ตกลง",
              }).then(() => window.location.reload());
            } else {
              Swal.fire({
                title: "เกิดข้อผิดพลาด",
                html:
                  resultData.message || "ไม่ทราบสาเหตุ กรุณาลองใหม่อีกครั้ง",
                icon: "error",
                confirmButtonText: "ตกลง",
              });
            }
          } catch (fetchErr) {
            console.error("Fetch error:", fetchErr);
            Swal.close();
            Swal.fire({
              title: "ข้อผิดพลาดในการเชื่อมต่อ",
              text: "ไม่สามารถส่งข้อมูลไปยังเซิร์ฟเวอร์ได้",
              icon: "error",
              confirmButtonText: "ตกลง",
            });
          }
        });
      } catch (err) {
        console.error("Error processing file:", err);
        Toast.fire({
          icon: "error",
          title: "เกิดข้อผิดพลาดในการอ่านไฟล์",
          html:
            err.message ||
            "ไฟล์อาจเสียหาย, ไม่ใช่ไฟล์ Excel/CSV หรือมีปัญหา encoding<br>กรุณาตรวจสอบไฟล์แล้วลองใหม่",
          timer: 6000,
        });
      }
    };

    reader.onerror = () => {
      Toast.fire({
        icon: "error",
        title: "ไม่สามารถอ่านไฟล์ได้",
        text: "อาจเป็นเพราะไฟล์เสียหายหรือเบราว์เซอร์ไม่รองรับ กรุณาลองไฟล์อื่นหรือเปิดใน Excel แล้วบันทึกใหม่เป็น CSV UTF-8",
        timer: 5000,
      });
    };

    if (ext === "csv") {
      reader.readAsText(file, "UTF-8");
    } else {
      reader.readAsArrayBuffer(file);
    }
  });

// Dropdown แผนกใน modal เพิ่ม/แก้ไขพนักงาน
document.addEventListener("click", function (e) {
  const item = e.target.closest(".dept-select-emp");
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

// เพิ่ม/แก้ไขพนักงานแบบไม่รีเฟรช
document
  .querySelector("#empModal form")
  ?.addEventListener("submit", function (e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(form);

    fetch("manage_employees.php", {
      method: "POST",
      headers: { "X-Requested-With": "XMLHttpRequest" },
      body: formData,
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data.success) {
          notify("error", data.message || "บันทึกไม่สำเร็จ");
          return;
        }

        const list = document.getElementById("employee-list");
        if (data.action === "add" && data.employee && list) {
          const item = buildEmployeeItem(data.employee);
          list.prepend(item);
        } else if (data.action === "edit" && data.employee) {
          const existing = document.querySelector(
            `.delete-btn[data-type="emp"][data-id="${data.employee.id}"]`,
          );
          const row = existing?.closest(".list-group-item");
          if (row) {
            const newItem = buildEmployeeItem(data.employee);
            row.replaceWith(newItem);
          }
        }

        filterEmployeeList();
        notify("success", data.message || "บันทึกสำเร็จ");
        bootstrap.Modal.getInstance(document.getElementById("empModal"))?.hide();
        form.reset();
        openAdd();
      })
      .catch(() => notify("error", "เชื่อมต่อเซิร์ฟเวอร์ไม่ได้"));
  });

// เพิ่มแผนก/บริษัทแบบไม่รีเฟรช
document
  .querySelector('form input[name="dept_name"]')
  ?.closest("form")
  ?.addEventListener("submit", function (e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(form);
    formData.append("add_dept", "1");

    fetch("manage_employees.php", {
      method: "POST",
      headers: { "X-Requested-With": "XMLHttpRequest" },
      body: formData,
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data.success) {
          notify("error", data.message || "เพิ่มแผนกไม่สำเร็จ");
          return;
        }
        const list = document.getElementById("dept-list");
        if (list && data.id) {
          list.prepend(buildSimpleItem("dept", data.id, data.name));
        }
        form.reset();
        notify("success", data.message || "เพิ่มแผนกสำเร็จ");
      })
      .catch(() => notify("error", "เชื่อมต่อเซิร์ฟเวอร์ไม่ได้"));
  });

document
  .querySelector('form input[name="company_name"]')
  ?.closest("form")
  ?.addEventListener("submit", function (e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(form);
    formData.append("add_company", "1");

    fetch("manage_employees.php", {
      method: "POST",
      headers: { "X-Requested-With": "XMLHttpRequest" },
      body: formData,
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data.success) {
          notify("error", data.message || "เพิ่มบริษัทไม่สำเร็จ");
          return;
        }
        const list = document.getElementById("company-list");
        if (list && data.id) {
          list.prepend(buildSimpleItem("company", data.id, data.name));
        }
        form.reset();
        notify("success", data.message || "เพิ่มบริษัทสำเร็จ");
      })
      .catch(() => notify("error", "เชื่อมต่อเซิร์ฟเวอร์ไม่ได้"));
  });
