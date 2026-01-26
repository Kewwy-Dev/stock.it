// assets/js/history.js
// เวอร์ชันสมบูรณ์ - รองรับ dropdown menu สำหรับกรองทั้งหมด

let data = [];
let currentPage = 1;
const rowsPerPage = 10;
let hiddenFrom, hiddenTo, fp;

document.addEventListener("DOMContentLoaded", () => {
  console.log("History JS loaded");

  data = window.historyTransactions || [];
  console.log("Transactions loaded:", data.length, "items");

  initHiddenInputs();
  initFlatpickr();
  initEventListeners();
  render(data); // แสดงข้อมูลเริ่มต้น
  initToast();
});

function initHiddenInputs() {
  hiddenFrom = document.getElementById("filterDateFrom");
  if (!hiddenFrom) {
    hiddenFrom = document.createElement("input");
    hiddenFrom.type = "hidden";
    hiddenFrom.id = "filterDateFrom";
    document.body.appendChild(hiddenFrom);
  }

  hiddenTo = document.getElementById("filterDateTo");
  if (!hiddenTo) {
    hiddenTo = document.createElement("input");
    hiddenTo.type = "hidden";
    hiddenTo.id = "filterDateTo";
    document.body.appendChild(hiddenTo);
  }
}

function initFlatpickr() {
  fp = flatpickr("#filterDateRange", {
    mode: "range",
    dateFormat: "Y-m-d",
    locale: "th",
    conjunction: " ถึง ",
    allowInput: true,
    clickOpens: true,
    onChange: function (selectedDates, dateStr, instance) {
      const [from, to] = dateStr.split(" ถึง ");
      hiddenFrom.value = from || "";
      hiddenTo.value = to || "";
      currentPage = 1;
      render(getFilteredData());
      updateFilterSummary();
    },
    onClose: function () {
      updateFilterSummary();
    },
  });
}

function initEventListeners() {
  // จัดการการเลือกใน dropdown menu ทั้ง 4 ตัว
  document
    .querySelectorAll(".dropdown-menu a.dropdown-item:not(.text-danger)")
    .forEach((item) => {
      item.addEventListener("click", (e) => {
        e.preventDefault();
        const dropdownMenu = item.closest(".dropdown-menu");
        const button = dropdownMenu.previousElementSibling; // ปุ่ม dropdown
        const labelSpan = button.querySelector("span");
        const hiddenInput = button
          .closest(".col-12, .col-md-3, .col-md-2, .col-lg-2")
          ?.querySelector('input[type="hidden"]');

        // อัปเดตข้อความบนปุ่ม
        labelSpan.textContent = item.textContent.trim();

        // อัปเดตค่าใน hidden input
        if (hiddenInput) {
          hiddenInput.value = item.dataset.value || "";
        }

        // อัปเดต active class
        dropdownMenu
          .querySelectorAll(".dropdown-item")
          .forEach((i) => i.classList.remove("active"));
        item.classList.add("active");

        currentPage = 1;
        render(getFilteredData());
        updateFilterSummary();
      });
    });

  // ล้างเฉพาะหมวดหมู่
  document.getElementById("clearCategory")?.addEventListener("click", (e) => {
    e.preventDefault();
    clearDropdown(
      "categoryDropdown",
      "categoryLabel",
      "filterCategory",
      "— หมวดหมู่สินค้า —",
    );
  });

  // ล้างเฉพาะอุปกรณ์
  document.getElementById("clearItem")?.addEventListener("click", (e) => {
    e.preventDefault();
    clearDropdown(
      "itemDropdown",
      "itemLabel",
      "filterItem",
      "— ค้นหาอุปกรณ์ —",
    );
  });

  // ล้างเฉพาะประเภท
  document.getElementById("clearType")?.addEventListener("click", (e) => {
    e.preventDefault();
    clearDropdown("typeDropdown", "typeLabel", "filterType", "— ทุกประเภท —");
  });

  // ล้างเฉพาะบริษัท
  document.getElementById("clearCompany")?.addEventListener("click", (e) => {
    e.preventDefault();
    clearDropdown(
      "companyDropdown",
      "companyLabel",
      "filterCompany",
      "— ทุกบริษัท —",
    );
  });

  // ปุ่มล้างทั้งหมด
  document
    .getElementById("clearFilterBtn")
    ?.addEventListener("click", clearAllFilters);

  // Export Button
  document.getElementById("exportBtn")?.addEventListener("click", exportCSV);
}

function clearDropdown(dropdownId, labelId, hiddenId, defaultText) {
  const label = document.getElementById(labelId);
  const hidden = document.getElementById(hiddenId);

  if (label) label.textContent = defaultText;
  if (hidden) hidden.value = "";

  // รีเซ็ต active ให้ตัว "ทั้งหมด"
  const menu = document.querySelector(`#${dropdownId} + .dropdown-menu`);
  if (menu) {
    menu
      .querySelectorAll(".dropdown-item")
      .forEach((i) => i.classList.remove("active"));
    menu.querySelector('.dropdown-item[data-value=""]').classList.add("active");
  }

  currentPage = 1;
  render(getFilteredData());
  updateFilterSummary();
}

function clearAllFilters() {
  // ล้าง Flatpickr
  if (fp) fp.clear();

  // ล้าง hidden date
  if (hiddenFrom) hiddenFrom.value = "";
  if (hiddenTo) hiddenTo.value = "";

  // ล้าง dropdown ทั้งหมด
  clearDropdown(
    "categoryDropdown",
    "categoryLabel",
    "filterCategory",
    "— หมวดหมู่สินค้า —",
  );
  clearDropdown("itemDropdown", "itemLabel", "filterItem", "— ค้นหาอุปกรณ์ —");
  clearDropdown("typeDropdown", "typeLabel", "filterType", "— ทุกประเภท —");
  clearDropdown(
    "companyDropdown",
    "companyLabel",
    "filterCompany",
    "— ทุกบริษัท —",
  );

  currentPage = 1;
  render(data);
  updateFilterSummary();
}

function getFilteredData() {
  let filtered = [...data];

  const categoryVal = document.getElementById("filterCategory")?.value || "";
  const itemVal = document.getElementById("filterItem")?.value || "";
  const typeVal = document.getElementById("filterType")?.value || "";
  const companyVal = document.getElementById("filterCompany")?.value || "";
  const dateFrom = hiddenFrom?.value || "";
  const dateTo = hiddenTo?.value || "";

  if (categoryVal) {
    filtered = filtered.filter(
      (t) => String(t.categories_id || t.category_id || "") === categoryVal,
    );
  }
  if (itemVal) {
    filtered = filtered.filter((t) => String(t.item_id) === itemVal);
  }
  if (typeVal) {
    filtered = filtered.filter((t) => t.type === typeVal);
  }
  if (companyVal) {
    filtered = filtered.filter((t) => String(t.company_id) === companyVal);
  }
  if (dateFrom && dateTo) {
    filtered = filtered.filter((t) => {
      const transDate = t.created_at?.split(" ")[0] || t.transaction_date || "";
      return transDate >= dateFrom && transDate <= dateTo;
    });
  }

  return filtered;
}

function render(filtered = data) {
  const tbody = document.getElementById("historyBody");
  const noData = document.getElementById("noData");

  if (!tbody) {
    console.error("ไม่พบ #historyBody");
    return;
  }

  tbody.innerHTML = "";

  if (filtered.length === 0) {
    noData?.classList.remove("d-none");
    updatePagination(0);
    updateFilterSummary(0);
    return;
  }

  noData?.classList.add("d-none");

  const start = (currentPage - 1) * rowsPerPage;
  const end = Math.min(start + rowsPerPage, filtered.length);
  const pageData = filtered.slice(start, end);

  pageData.forEach((r) => {
    const tr = document.createElement("tr");

    const emp = r.emp_name
      ? `${esc(r.emp_name)}${r.dept_name ? " (" + esc(r.dept_name) + ")" : ""}`
      : "-";

    tr.innerHTML = `
      <td>${formatDate( r.transaction_date || "-")}</td>
      <td>${esc(r.item_name || "-")}</td>
      <td class="text-center">${formatNum(r.quantity)}</td>
      <td class="text-center">${formatNum(r.current_stock_after || r.stock || "-")}</td>
      <td>${emp}</td>
      <td>${esc(r.company_name || "-")}</td>
      <td>${esc(r.note || r.memo || "-")}</td>
      <td class="text-center">
        <button class="btn btn-sm btn-outline-danger delete-btn"
                data-id="${r.id}"
                data-info="${esc(r.item_name || "-")}">
          <i class="bi bi-trash"></i> ลบ
        </button>
      </td>
    `;
    tbody.appendChild(tr);
  });

  updatePagination(filtered.length);
  updateFilterSummary(filtered.length);
}

function updatePagination(totalItems) {
  const container = document.getElementById("paginationContainer");
  if (!container) return;

  container.innerHTML = "";

  const totalPages = Math.ceil(totalItems / rowsPerPage);
  if (totalPages <= 1) return;

  // Previous
  const prev = document.createElement("li");
  prev.className = `page-item ${currentPage === 1 ? "disabled" : ""}`;
  prev.innerHTML = `<a class="page-link" href="#">ก่อนหน้า</a>`;
  prev.addEventListener("click", (e) => {
    e.preventDefault();
    if (currentPage > 1) {
      currentPage--;
      render(getFilteredData());
    }
  });
  container.appendChild(prev);

  // Pages
  for (let i = 1; i <= totalPages; i++) {
    const li = document.createElement("li");
    li.className = `page-item ${i === currentPage ? "active" : ""}`;
    li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
    li.addEventListener("click", (e) => {
      e.preventDefault();
      currentPage = i;
      render(getFilteredData());
    });
    container.appendChild(li);
  }

  // Next
  const next = document.createElement("li");
  next.className = `page-item ${currentPage === totalPages ? "disabled" : ""}`;
  next.innerHTML = `<a class="page-link" href="#">ถัดไป</a>`;
  next.addEventListener("click", (e) => {
    e.preventDefault();
    if (currentPage < totalPages) {
      currentPage++;
      render(getFilteredData());
    }
  });
  container.appendChild(next);
}

function updateFilterSummary(count) {
  const summary = document.getElementById("filterSummary");
  const totalSpan = document.getElementById("totalResults");

  if (!summary || !totalSpan) return;

  if (count === data.length && !hasAnyFilter()) {
    summary.classList.add("d-none");
  } else {
    summary.classList.remove("d-none");
    totalSpan.textContent = count;
  }
}

function hasAnyFilter() {
  return (
    document.getElementById("filterCategory")?.value ||
    document.getElementById("filterItem")?.value ||
    document.getElementById("filterType")?.value ||
    document.getElementById("filterCompany")?.value ||
    hiddenFrom?.value ||
    hiddenTo?.value
  );
}

// Utility Functions
function esc(t) {
  const d = document.createElement("div");
  d.textContent = t ?? "-";
  return d.innerHTML;
}

function formatDate(d) {
  if (!d) return "-";
  const [y, m, day] = d.split("-");
  return `${day}/${m}/${y}`;
}

function formatNum(n) {
  return new Intl.NumberFormat("th-TH").format(Number(n) || 0);
}

function exportCSV() {
  const filtered = getFilteredData();
  if (filtered.length === 0) {
    alert("ไม่มีข้อมูลตามตัวกรองที่เลือกเพื่อส่งออก");
    return;
  }

  let csv =
    "\uFEFFวันที่,อุปกรณ์,หมวดหมู่,ประเภท,จำนวน,คงเหลือ,ผู้เบิก/แผนก,บริษัท,หมายเหตุ\n";

  filtered.forEach((r) => {
    const typeText = r.type === "IN" ? "เข้า" : "ออก";
    const emp = r.emp_name
      ? r.emp_name + (r.dept_name ? " (" + r.dept_name + ")" : "")
      : "-";
    csv +=
      [
        formatDate(r.transaction_date),
        r.item_name || "-",
        r.categories_name || "-",
        typeText,
        r.quantity || 0,
        r.current_stock_after || r.stock || "-",
        emp,
        r.company_name || "-",
        r.note || r.memo || "-",
      ].join(",") + "\n";
  });

  const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = `ประวัติการเบิก-รับเข้าอุปกรณ์_${new Date().toISOString().split("T")[0]}.csv`;
  link.style.visibility = "hidden";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function initToast() {
  const toastData = document.documentElement.dataset.toast;
  if (toastData) {
    try {
      const d = JSON.parse(toastData);
      const t = document.createElement("div");
      t.className = `toast align-items-center text-bg-${d.type === "error" ? "danger" : "success"} border-0`;
      t.innerHTML = `<div class="d-flex"><div class="toast-body">${d.message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
      document.querySelector(".toast-container")?.appendChild(t);
      new bootstrap.Toast(t, { delay: 4000 }).show();
    } catch (e) {
      console.error("Toast parse error:", e);
    }
  }
}

// Delete confirmation
document.addEventListener("click", function (e) {
  const btn = e.target.closest(".delete-btn");
  if (!btn) return;

  e.preventDefault();
  const id = btn.dataset.id;
  const info = btn.dataset.info;

  Swal.fire({
    title: "ยืนยันการลบ?",
    html: `<strong class="text-danger">${info}</strong><br>
           <small class="text-muted">การลบจะปรับสต็อกอัตโนมัติ</small>`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#dc3545",
    cancelButtonColor: "#6c757d",
    confirmButtonText: '<i class="bi bi-trash-fill me-2"></i>ลบถาวร',
    cancelButtonText: '<i class="bi bi-x-circle me-1"></i>ยกเลิก',
    reverseButtons: true,
  }).then((result) => {
    if (result.isConfirmed) {
      const form = document.createElement("form");
      form.method = "POST";
      form.action = "history.php";

      const csrf = document.createElement("input");
      csrf.type = "hidden";
      csrf.name = "csrf_token";
      csrf.value = window.csrfToken || "";

      const delInput = document.createElement("input");
      delInput.type = "hidden";
      delInput.name = "delete_id";
      delInput.value = id;

      form.appendChild(csrf);
      form.appendChild(delInput);
      document.body.appendChild(form);
      form.submit();
    }
  });
});
