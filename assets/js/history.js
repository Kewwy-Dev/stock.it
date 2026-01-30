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
  //  dropdown menu  4  ( filter bar)
  const filterBar = document.querySelector(".filter-bar");
  filterBar
    ?.querySelectorAll(".dropdown-menu a.dropdown-item:not(.text-danger)")
    .forEach((item) => {
      item.addEventListener("click", (e) => {
        e.preventDefault();
        const dropdownMenu = item.closest(".dropdown-menu");
        const button = dropdownMenu?.previousElementSibling; // dropdown
        const labelSpan = button?.querySelector("span");
        const hiddenInput = button
          ?.closest(".col-12, .col-md-3, .col-md-2, .col-lg-2")
          ?.querySelector('input[type="hidden"]');

        if (labelSpan) {
          labelSpan.textContent = item.textContent.trim();
        }

        // hidden input
        if (hiddenInput) {
          hiddenInput.value = item.dataset.value || "";
        }

        // active class
        if (dropdownMenu) {
          dropdownMenu
            .querySelectorAll(".dropdown-item")
            .forEach((i) => i.classList.remove("active"));
          item.classList.add("active");
        }

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
      "— ทุกหมวดหมู่ —",
    );
  });

  // ล้างเฉพาะอุปกรณ์
  document.getElementById("clearItem")?.addEventListener("click", (e) => {
    e.preventDefault();
    clearDropdown(
      "itemDropdown",
      "itemLabel",
      "filterItem",
      "— ค้นหาทุกอุปกรณ์ —",
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
  document.getElementById("exportBtnMobile")?.addEventListener("click", exportCSV);
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
    "— ทุกหมวดหมู่ —",
  );
  clearDropdown("itemDropdown", "itemLabel", "filterItem", "— ค้นหาทุกอุปกรณ์ —");
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
  const cards = document.getElementById("historyCards");
  const noDataCards = document.getElementById("noDataCards");

  if (!tbody) {
    console.error("ไม่พบ #historyBody");
    return;
  }

  tbody.innerHTML = "";
  if (cards) cards.innerHTML = "";

  if (filtered.length === 0) {
    noData?.classList.remove("d-none");
    noDataCards?.classList.remove("d-none");
    updatePagination(0);
    updateFilterSummary(0);
    return;
  }

  noData?.classList.add("d-none");
  noDataCards?.classList.add("d-none");

  const start = (currentPage - 1) * rowsPerPage;
  const end = Math.min(start + rowsPerPage, filtered.length);
  const pageData = filtered.slice(start, end);

  renderTableRows(pageData, tbody);
  renderCards(pageData, cards);

  updatePagination(filtered.length);
  updateFilterSummary(filtered.length);
}

function renderTableRows(rows, tbody) {
  rows.forEach((r) => {
    const tr = document.createElement("tr");

    const emp = r.emp_name
      ? `${esc(r.emp_name)}${r.dept_name ? " (" + esc(r.dept_name) + ")" : ""}`
      : "-";

    const typeText = r.type === "OUT" ? "เบิก" : "เพิ่ม";
    const typeClass =
      r.type === "OUT" ? "text-danger bg-danger-subtle" : "text-success bg-success-subtle";

    tr.innerHTML = `
      <td>${formatDate(r.transaction_date || "-")}</td>
      <td>${esc(r.item_name || "-")}</td>
      <td class="text-center">
        <span class="badge ${typeClass}">${typeText}</span>
      </td>
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
}

function renderCards(rows, container) {
  if (!container) return;

  rows.forEach((r) => {
    const typeText = r.type === "OUT" ? "เบิก" : "เพิ่ม";
    const typeClass =
      r.type === "OUT" ? "text-danger bg-danger-subtle" : "text-success bg-success-subtle";
    const emp = r.emp_name
      ? `${esc(r.emp_name)}${r.dept_name ? " (" + esc(r.dept_name) + ")" : ""}`
      : "-";
    const company = r.company_name ? esc(r.company_name) : "-";
    const empDeptCompany = company !== "-" ? `${emp} / ${company}` : emp;
    const detailId = `history-detail-${r.id}`;

    const card = document.createElement("div");
    card.className = "history-card";
    card.innerHTML = `
      <div class="history-card-header toggle-detail-row" role="button" aria-expanded="false" aria-controls="${detailId}">
        <span class="badge history-card-type ${typeClass}">${typeText}</span>
        <div class="history-card-item">${esc(r.item_name || "-")}</div>
        <button class="btn btn-link btn-sm p-0 toggle-detail floating-toggle" type="button" aria-label="ซ่อน/แสดงรายละเอียด">
          <i class="bi bi-chevron-down"></i>
        </button>
      </div>
      <div id="${detailId}" class="collapse mt-3">
        <div class="history-detail">
          <div class="history-detail-row">
            <div class="history-detail-label">วันที่ :</div>
            <div class="history-detail-value">${formatDate(r.transaction_date || "-")}</div>
          </div>
          <div class="history-detail-row">
            <div class="history-detail-label">อุปกรณ์ :</div>
            <div class="history-detail-value">${esc(r.item_name || "-")}</div>
          </div>
          <div class="history-detail-row">
            <div class="history-detail-label">ประเภท :</div>
            <div class="history-detail-value">
              <span class="badge ${typeClass}">${typeText}</span>
            </div>
          </div>
          <div class="history-detail-row">
            <div class="history-detail-label">จำนวน :</div>
            <div class="history-detail-value">${formatNum(r.quantity)}</div>
          </div>
          <div class="history-detail-row">
            <div class="history-detail-label">คงเหลือ :</div>
            <div class="history-detail-value">${formatNum(r.current_stock_after || r.stock || "-")}</div>
          </div>
          <div class="history-detail-row">
            <div class="history-detail-label">ผู้เบิก/แผนก :</div>
            <div class="history-detail-value">${emp}</div>
          </div>
          <div class="history-detail-row">
            <div class="history-detail-label">บริษัท :</div>
            <div class="history-detail-value">${company || "-"}</div>
          </div>
          <div class="history-detail-row">
            <div class="history-detail-label">หมายเหตุ :</div>
            <div class="history-detail-value">${esc(r.note || r.memo || "-")}</div>
          </div>
          <div class="history-detail-row history-detail-actions">
            <button class="btn btn-sm btn-outline-danger delete-btn"
                    data-id="${r.id}"
                    data-info="${esc(r.item_name || "-")}">
              <i class="bi bi-trash"></i> ลบ
            </button>
          </div>
        </div>
      </div>
    `;

    container.appendChild(card);

    const collapseEl = card.querySelector(`#${detailId}`);
    const toggleBtn = card.querySelector(".toggle-detail");
    const toggleRow = card.querySelector(".toggle-detail-row");
    if (collapseEl && toggleBtn) {
      if (toggleRow) {
        toggleRow.addEventListener("click", () => {
          bootstrap.Collapse.getOrCreateInstance(collapseEl).toggle();
        });
      }
      toggleBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        bootstrap.Collapse.getOrCreateInstance(collapseEl).toggle();
      });
      collapseEl.addEventListener("show.bs.collapse", () => {
        toggleBtn.innerHTML = '<i class="bi bi-chevron-up"></i>';
        if (toggleRow) toggleRow.setAttribute("aria-expanded", "true");
      });
      collapseEl.addEventListener("hide.bs.collapse", () => {
        toggleBtn.innerHTML = '<i class="bi bi-chevron-down"></i>';
        if (toggleRow) toggleRow.setAttribute("aria-expanded", "false");
      });
    }
  });
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
  const totalSpan = document.getElementById("resultCount");
  const detailsSpan = document.getElementById("filterDetails");

  if (!summary || !totalSpan || !detailsSpan) return;

  if (typeof count !== "number") {
    count = getFilteredData().length;
  }

  if (count === data.length && !hasAnyFilter()) {
    summary.classList.add("d-none");
  } else {
    summary.classList.remove("d-none");
    totalSpan.textContent = count;
    detailsSpan.innerHTML = buildFilterDetailBadges();
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

function buildFilterDetailBadges() {
  const parts = [];

  const categoryLabel = document.getElementById("categoryLabel")?.textContent?.trim();
  if (categoryLabel && categoryLabel !== "— —" && categoryLabel !== "— ทุกหมวดหมู่ —") {
    parts.push({
      label: "หมวดหมู่",
      value: categoryLabel,
      cls: "filter-badge-category",
    });
  }

  const itemLabel = document.getElementById("itemLabel")?.textContent?.trim();
  if (itemLabel && itemLabel !== "— —" && itemLabel !== "— ค้นหาทุกอุปกรณ์ —") {
    parts.push({
      label: "อุปกรณ์",
      value: itemLabel,
      cls: "filter-badge-item",
    });
  }

  const typeLabel = document.getElementById("typeLabel")?.textContent?.trim();
  if (typeLabel && typeLabel !== "— —" && typeLabel !== "— ทุกประเภท —") {
    const typeValue = document.getElementById("filterType")?.value || "";
    const typeClass =
      typeValue === "OUT"
        ? "filter-badge-type-out"
        : typeValue === "IN"
          ? "filter-badge-type-in"
          : "filter-badge-type";
    parts.push({
      label: "ประเภท",
      value: typeLabel,
      cls: typeClass,
    });
  }

  const companyLabel = document.getElementById("companyLabel")?.textContent?.trim();
  if (companyLabel && companyLabel !== "— —" && companyLabel !== "— ทุกบริษัท —") {
    parts.push({
      label: "บริษัท",
      value: companyLabel,
      cls: "filter-badge-company",
    });
  }

  const dateFrom = hiddenFrom?.value || "";
  const dateTo = hiddenTo?.value || "";
  if (dateFrom || dateTo) {
    const fromText = dateFrom ? formatDate(dateFrom) : "-";
    const toText = dateTo ? formatDate(dateTo) : "-";
    parts.push({
      label: "วันที่",
      value: `${fromText} ถึง ${toText}`,
      cls: "filter-badge-date",
    });
  }

  if (parts.length === 0) {
    return "";
  }

  const badges = parts
    .map((p) => {
      return `<span class="filter-badge ${p.cls}">${esc(p.label)}: ${esc(p.value)}</span>`;
    })
    .join(" ");

  return ` <span class="filter-detail-label">| กรอง:</span> ${badges}`;
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
    const typeText = r.type === "IN" ? "เพิ่ม" : "เบิก";
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
    confirmButtonText: '<i class="bi bi-trash-fill me-2"></i>ยืนยัน',
    cancelButtonText: 'ยกเลิก',
    reverseButtons: true,
  }).then(async (result) => {
    if (!result.isConfirmed) return;

    try {
      const response = await fetch("history.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
          "X-Requested-With": "XMLHttpRequest",
          Accept: "application/json",
        },
        body: `delete_id=${encodeURIComponent(id)}&csrf_token=${encodeURIComponent(
          window.csrfToken || "",
        )}`,
      });

      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.error || "ลบไม่สำเร็จ");
      }

      data = data.filter((t) => String(t.id) !== String(id));

      const filtered = getFilteredData();
      const totalPages = Math.max(1, Math.ceil(filtered.length / rowsPerPage));
      if (currentPage > totalPages) currentPage = totalPages;

      render(filtered);
      Toast.fire({
        icon: "success",
        title: "ลบรายการเรียบร้อย",
        background: "#a5dc86",
      });
    } catch (err) {
      Toast.fire({
        icon: "error",
        title: "ลบไม่สำเร็จ",
        text: err.message,
        background: "#f27474",
      });
    }
  });
});
