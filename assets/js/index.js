// assets/js/index.js
let items = [];
let employees = [];
let currentCategory = ""; // เก็บค่า category_id ที่เลือกอยู่
let currentRightFilter = ""; // ตัวกรองฝั่งขวา

document.addEventListener("DOMContentLoaded", () => {
  // รับข้อมูลจาก PHP (ต้องมี category_id และ category_name เพิ่มมา)
  items = window.stockItems || [];
  employees = window.stockEmployees || [];

  console.log("Index page loaded - จำนวนอุปกรณ์:", items.length);

  const grid = document.getElementById("itemGrid");
  const itemFilter = document.getElementById("itemFilter");
  const categoryFilter = document.getElementById("categoryFilter");
  const noResults = document.getElementById("noResults");

  // ฟังก์ชัน escape HTML
  function esc(t) {
    const d = document.createElement("div");
    d.textContent = t ?? "";
    return d.innerHTML;
  }

  // ฟังก์ชัน render การ์ดอุปกรณ์
  function render(data) {
    grid.innerHTML = "";
    if (!data.length) {
      noResults.classList.remove("d-none");
      return;
    }
    noResults.classList.add("d-none");

    data.forEach((i) => {
      const col = document.createElement("div");
      col.className = "col-6 col-md-6 col-lg-3";
      col.innerHTML = `
        <div class="item-card position-relative">
          <button class="close-btn position-absolute" data-bs-toggle="modal" data-bs-target="#xModal" 
                  data-id="${i.id}" data-name="${esc(i.name)}"
                  title="ลบอุปกรณ์นี้">
            <i class="bi bi-trash3-fill"></i>
          </button>

          <!-- ปุ่มแก้ไข (ใหม่) - วางใต้ปุ่มลบ -->
          <button class="edit-btn position-absolute" 
                data-id="${i.id}" 
                title="แก้ไขอุปกรณ์">
            <i class="bi bi-pencil-fill"></i>
          </button>
          
          <!-- ดาวปักหมุด -->
          <button class="btn-favorite position-absolute top-0 start-0 m-2 rounded-circle border-0"
                  data-id="${i.id}" data-favorite="${
                    i.is_favorite
                  }" title="ปักดาวอุปกรณ์นี้">
            <i class="bi bi-star${
              i.is_favorite ? "-fill text-warning" : " text-secondary"
            } fs-4"></i>
          </button>

          ${
            i.image
              ? `<img src="uploads/${
                  i.image
                }" class="item-img w-100" alt="${esc(i.name)}">`
              : `<div class="item-img-placeholder"><i class="bi bi-box"></i></div>`
          }
          
          <div class="p-3">
            ${
              i.category_name
                ? `<span class="badge bg-secondary-subtle text-secondary mb-2">หมวดหมู่ : ${esc(
                    i.category_name,
                  )}</span>`
                : ""
            }
            <h5 class="card-title">${esc(i.name)}</h5>
            <p class="text-success fw-bold">คงเหลือ: ${i.stock} ชิ้น</p>
            <div class="d-flex gap-2">
              <button class="btn btn-success flex-fill" 
                      onclick="openTrans(${i.id},'IN','${esc(
                        i.name,
                      )}')">+ เพิ่ม</button>
              <button class="btn btn-danger flex-fill" 
                      onclick="openTrans(${i.id},'OUT','${esc(
                        i.name,
                      )}')">- เบิก</button>
            </div>
          </div>
        </div>`;
      grid.appendChild(col);
    });
  }

  // --------------------------
  //  ควบคุม required + การตรวจสอบสำหรับการเบิก (OUT)
  // --------------------------
  function updateRequiredFields() {
    const typeInput = document.getElementById("type");
    if (!typeInput) return;

    const type = typeInput.value?.trim().toUpperCase() || "";
    const isOut = type === "OUT";

    // หา element ทั้ง 3 ตัว
    const companySelect = document.querySelector('select[name="company_id"]');
    const deptInput = document.getElementById("department_id"); // hidden input
    const employeeSelect = document.getElementById("employeeSelect"); // select จริง

    // เปิด/ปิด required เฉพาะตอน OUT
    if (companySelect) companySelect.required = isOut;
    if (deptInput) deptInput.required = isOut;
    if (employeeSelect) employeeSelect.required = isOut;

    // Optional: เพิ่ม/ลบ class เพื่อให้เห็นชัดว่าต้องกรอก (เช่น ขอบแดง)
    const labels = document.querySelectorAll("#outSection label");
    labels.forEach((label) => {
      if (isOut) {
        label.classList.add("text-danger");
      } else {
        label.classList.remove("text-danger");
      }
    });
  }

  // เรียกตอน modal เปิด (ทั้งสอง event เพื่อความแน่นอน)
  const transactionModal = document.getElementById("transactionModal");
  if (transactionModal) {
    transactionModal.addEventListener("show.bs.modal", updateRequiredFields);
    transactionModal.addEventListener("shown.bs.modal", updateRequiredFields);
  }

  // เพิ่มการตรวจสอบก่อน submit (ป้องกันกรณี bypass required ได้)
  document
    .querySelector("#transactionModal form")
    ?.addEventListener("submit", function (e) {
      const type =
        document.getElementById("type")?.value?.trim().toUpperCase() || "";

      if (type !== "OUT") {
        // ถ้าเป็น IN → ไม่ต้องตรวจอะไรเพิ่ม
        return;
      }

      // เป็น OUT → ตรวจทั้ง 3 ฟิลด์
      const companyVal =
        document.querySelector('select[name="company_id"]')?.value?.trim() ||
        "";
      const deptVal =
        document.getElementById("department_id")?.value?.trim() || "";
      const employeeVal =
        document.getElementById("employeeSelect")?.value?.trim() || "";

      let errorMsg = "";

      if (!companyVal) errorMsg += "กรุณาเลือกบริษัท\n";
      if (!deptVal) errorMsg += "กรุณาเลือกแผนก\n";
      if (!employeeVal) errorMsg += "กรุณาเลือกผู้เบิก\n";

      if (errorMsg) {
        e.preventDefault(); // หยุดการ submit

        // แจ้งเตือนแบบสวย ๆ (ถ้าใช้ SweetAlert2)
        if (typeof Swal !== "undefined") {
          Swal.fire({
            icon: "warning",
            title: "ข้อมูลไม่ครบถ้วน",
            html: errorMsg.replace(/\n/g, "<br>"),
            confirmButtonText: "ตกลง",
          });
        } else {
          // ถ้าไม่มี SweetAlert2 ใช้ alert ธรรมดา
          alert("ข้อมูลไม่ครบถ้วน:\n" + errorMsg);
        }
      }
    });

  // ฟังก์ชันหลักสำหรับกรองข้อมูล (รวมทุกเงื่อนไข)
  function applyFilters() {
    let filtered = [...items];

    // 1. กรองตามหมวดหมู่
    if (currentCategory !== "") {
      filtered = filtered.filter(
        (item) => String(item.category_id) === currentCategory,
      );
    }

    // 2. กรองจากฝั่งขวา (ปรับปรุงใหม่)
    if (currentRightFilter) {
      if (currentRightFilter === "low") {
        filtered = filtered.filter((x) => x.stock < 5 && x.stock > 0);
      } else if (currentRightFilter === "zero") {
        filtered = filtered.filter((x) => x.stock === 0);
      } else if (currentRightFilter !== "" && currentRightFilter !== "all") {
        // กรณีเลือกเป็น ID อุปกรณ์รายตัว
        filtered = filtered.filter((x) => String(x.id) === currentRightFilter);
      }
    }
    render(filtered);
  }
  // กรอง สต็อกต่ำ & สต็อกหมดอัตโนมัติจาก dashboard
  const urlParams = new URLSearchParams(window.location.search);
  const autoFilter = urlParams.get("filter");

  if (autoFilter === "low" || autoFilter === "zero") {
    currentRightFilter = autoFilter;

    const itemFilterLabel = document.getElementById("itemFilterLabel");
    const itemFilterSelects = document.querySelectorAll(".item-filter-select");

    let displayText = "ค้นหาอุปกรณ์";
    let targetSelector = "";

    if (autoFilter === "low") {
      displayText = "⚠️ อุปกรณ์เหลือน้อย";
      targetSelector = '[data-value="low"]';
    } else if (autoFilter === "zero") {
      displayText = "🚫 อุปกรณ์หมด";
      targetSelector = '[data-value="zero"]';
    }

    // ลบ active class ก่อน
    itemFilterSelects.forEach((el) => el.classList.remove("active"));

    // เพิ่ม active ให้ตัวเลือกที่ตรง
    const targetBtn = document.querySelector(
      `.item-filter-select${targetSelector}`,
    );
    if (targetBtn) {
      targetBtn.classList.add("active");
    }

    // อัปเดตข้อความบนปุ่ม dropdown
    if (itemFilterLabel) {
      itemFilterLabel.textContent = displayText;
    }

    console.log(`Auto filter applied from URL: ?filter=${autoFilter}`);
  }

  // Event: เปลี่ยนหมวดหมู่
  if (categoryFilter) {
    categoryFilter.addEventListener("change", (e) => {
      currentCategory = e.target.value;
      console.log("เลือกหมวดหมู่ →", currentCategory);
      applyFilters();
    });
  }
  // กรองIDหมวดหมู่
  const categoryItems = document.querySelectorAll(".category-select");
  const categoryLabel = document.getElementById("categoryLabel");

  categoryItems.forEach((item) => {
    item.addEventListener("click", (e) => {
      e.preventDefault();

      // ลบ active จากทุกตัวก่อน
      categoryItems.forEach((el) => el.classList.remove("active", "selected"));

      // เพิ่ม active ให้ตัวที่คลิก
      item.classList.add("active"); // หรือใช้ "selected" ก็ได้

      currentCategory = item.dataset.value || "";

      // if (categoryLabel) {
      //   categoryLabel.textContent =
      //     currentCategory === "" ? "หมวดหมู่สินค้า" : item.textContent.trim();
      // }

      console.log("กรองหมวดหมู่ ID:", currentCategory);
      applyFilters();
    });
  });

  // กรองชื่ออุปกรณ์ / เหลือน้อย / หมด
  const itemFilterSelects = document.querySelectorAll(".item-filter-select");
  const itemFilterLabel = document.getElementById("itemFilterLabel");

  itemFilterSelects.forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.preventDefault();

      // ลบ active จากทุกตัวก่อน
      itemFilterSelects.forEach((el) =>
        el.classList.remove("active", "selected"),
      );

      // เพิ่ม active ให้ตัวที่คลิก
      btn.classList.add("active");

      currentRightFilter = btn.dataset.value;

      // อัปเดตข้อความ label (ปรับแต่งตามต้องการ)
      let displayText = "ค้นหาอุปกรณ์";
      if (currentRightFilter === "low") {
        displayText = "เหลือน้อย (1–4 ชิ้น)";
      } else if (currentRightFilter === "zero") {
        displayText = "หมดสต็อก";
      } else if (currentRightFilter && !isNaN(currentRightFilter)) {
        const selected = items.find(
          (it) => String(it.id) === currentRightFilter,
        );
        displayText = selected ? selected.name : "อุปกรณ์ที่เลือก";
      } else if (currentRightFilter === "") {
        displayText = "ทุกอุปกรณ์";
      }

      // if (itemFilterLabel) {
      //   itemFilterLabel.textContent = displayText;
      // }

      console.log("เลือกตัวกรองขวา →", currentRightFilter);
      applyFilters();
    });
  });

  // Preview รูป (ปรับให้รองรับ input id="imageInput")
  document
    .getElementById("imageInput")
    ?.addEventListener("change", function (e) {
      const file = e.target.files[0];
      const preview = document.getElementById("imagePreview");
      if (!preview) return;

      preview.innerHTML = "";
      if (file) {
        const img = document.createElement("img");
        img.src = URL.createObjectURL(file);
        img.className = "img-fluid mt-2 rounded";
        img.style.maxHeight = "200px";
        preview.appendChild(img);
      }
    });

  // จับ submit form เพิ่ม/แก้ไขอุปกรณ์ (AJAX + Toast ทันที)
  document
    .getElementById("itemForm")
    ?.addEventListener("submit", async function (e) {
      e.preventDefault(); // หยุด submit ปกติ

      const form = this;
      const submitBtn = document.getElementById("submitItemBtn");
      const processingBtn = document.getElementById("processingBtn");
      const removeBgCheckbox = document.getElementById("removeBgCheckbox");
      const imageInput = document.getElementById("imageInput");
      const isRemoveBg = removeBgCheckbox?.checked || false;
      const hasNewImage = imageInput?.files?.length > 0;

      // Step 1: ลบพื้นหลังก่อน (ถ้าติ๊ก)
      let finalFormData = new FormData(form);
      if (isRemoveBg && hasNewImage) {
        submitBtn.style.display = "none";
        processingBtn.innerHTML =
          '<span class="spinner-border spinner-border-sm me-2"></span>กำลังลบพื้นหลัง...';
        processingBtn.style.display = "inline-block";

        try {
          const bgFormData = new FormData();
          bgFormData.append("image", imageInput.files[0]);

          const bgRes = await fetch("removebg.php", {
            method: "POST",
            body: bgFormData,
          });
          const bgData = await bgRes.json();

          if (!bgData.success)
            throw new Error(bgData.error || "ลบพื้นหลังล้มเหลว");

          // แปลง base64 → File ใหม่
          const blob = await (await fetch(bgData.base64)).blob();
          const cleanedFile = new File([blob], "removed_bg.png", {
            type: "image/png",
          });

          // แทนที่ไฟล์ใน FormData
          finalFormData.delete("image");
          finalFormData.append("image", cleanedFile);

          // อัปเดต Preview
          const preview = document.getElementById("imagePreview");
          if (preview)
            preview.innerHTML = `<img src="${bgData.base64}" class="img-fluid rounded" style="max-height:200px;">`;

          Toast.fire({
            icon: "success",
            title: "ลบพื้นหลังสำเร็จ!",
            timer: 1500,
            background: "#a5dc86",
          });
        } catch (err) {
          console.error("Remove BG error:", err);
          Toast.fire({
            icon: "warning",
            title: "ใช้ภาพเดิมแทน",
            text: err.message,
            timer: 2500,
            background: "#f8bb86",
          });
        } finally {
          submitBtn.style.display = "inline-block";
          processingBtn.style.display = "none";
        }
      }

      // Step 2: ส่งข้อมูลจริง (เพิ่ม/แก้ไข)
      submitBtn.disabled = true;
      submitBtn.innerHTML =
        '<span class="spinner-border spinner-border-sm me-2"></span>กำลังบันทึก...';

      try {
        const response = await fetch("save_item.php", {
          method: "POST",
          body: finalFormData,
        });

        const result = await response.json();

        if (result.success) {
          // ✅ Toast สำเร็จ
          const action = result.id && items.some(item => item.id == result.id) ? "แก้ไข" : "เพิ่ม";
          Toast.fire({
            icon: "success",
            title: `${action}อุปกรณ์สำเร็จ!`,
            // text: result.item_name ? `“${result.item_name}”` : "",
            timer: 2500,
            background: "#a5dc86",
          });

          // ปิด Modal + Reset Form
          const modal = bootstrap.Modal.getInstance(
            document.getElementById("addItemModal"),
          );
          modal.hide();
          form.reset();
          document.getElementById("imagePreview").innerHTML = "";
          document.getElementById("modalTitle").textContent = "เพิ่มอุปกรณ์";

          // Reload หน้าเพื่ออัปเดตข้อมูล (ง่ายสุด)
          setTimeout(() => window.location.reload(), 1200);
        } else {
          // ❌ Toast ล้มเหลว
          Toast.fire({
            icon: "error",
            title: "บันทึกไม่สำเร็จ",
            text: result.error || "กรุณาลองใหม่อีกครั้ง",
            timer: 3500,
            background: "#f27474",
          });
        }
      } catch (err) {
        console.error("Save error:", err);
        Toast.fire({
          icon: "error",
          title: "เกิดข้อผิดพลาด",
          text: "ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้",
          timer: 3500,
          background: "#f27474",
        });
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-floppy me-1"></i>บันทึก';
      }
    });

  // ฟังก์ชันเปิด modal เพิ่มอุปกรณ์ (เหมือนเดิม)
  window.openAddModal = function () {
    document.getElementById("modalTitle").textContent = "เพิ่มอุปกรณ์";
    const form = document.querySelector("#addItemModal form");
    if (form) form.reset();

    const preview = document.getElementById("imagePreview");
    if (preview) preview.innerHTML = "";

    document.getElementById("itemId").value = "";
    document.getElementById("oldImage").value = "";

    // ถ้ามี select category ใน modal → reset เป็นค่าเริ่มต้น
    const catSelect = document.querySelector(
      '#addItemModal select[name="category_id"]',
    );
    if (catSelect) catSelect.value = "";

    new bootstrap.Modal("#addItemModal").show();
  };

  // ฟังก์ชันเปิด modal เพิ่ม/เบิก (เหมือนเดิม)
  window.openTrans = function (id, type, itemName) {
    document.getElementById("transTitle").textContent =
      (type === "IN" ? "เพิ่มสต็อก" : "เบิกใช้") + " - " + itemName;

    document.getElementById("item_id").value = id;
    document.getElementById("type").value = type;

    const outSection = document.getElementById("outSection");
    outSection.style.display = type === "OUT" ? "block" : "none";

    const empSelect = document.getElementById("employeeSelect");
    empSelect.disabled = true;
    empSelect.innerHTML = '<option value="">— เลือกผู้เบิก —</option>';

    document.getElementById("departmentSelect").value = "";

    new bootstrap.Modal("#transactionModal").show();
  };

  // เมื่อเลือกแผนก → โหลดพนักงาน (เหมือนเดิม)
  const deptSelect = document.getElementById("departmentSelect");
  if (deptSelect) {
    deptSelect.addEventListener("change", function () {
      const deptId = this.value;
      document.getElementById("department_id").value = deptId;

      const empSelect = document.getElementById("employeeSelect");
      empSelect.innerHTML = '<option value="">— เลือกผู้เบิก —</option>';

      const filteredEmps = employees.filter(
        (e) => String(e.department_id) === deptId,
      );

      filteredEmps.forEach((e) => {
        const opt = document.createElement("option");
        opt.value = e.id;
        opt.textContent = e.name;
        empSelect.appendChild(opt);
      });

      empSelect.disabled = false;
    });
  }

  // Modal ลบ (เหมือนเดิม)
  document.addEventListener("click", function (e) {
    const deleteBtn = e.target.closest(".close-btn");
    if (deleteBtn) {
      const id = deleteBtn.dataset.id;
      const name = deleteBtn.dataset.name;

      Swal.fire({
        title: "ยืนยันการลบ?",
        text: `คุณต้องการลบอุปกรณ์ "${name}" ใช่หรือไม่?`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "ใช่, ลบเลย!",
        cancelButtonText: "ยกเลิก",
      }).then((result) => {
        if (result.isConfirmed) {
          // สร้าง Form เพื่อ Submit การลบ
          const form = document.createElement("form");
          form.method = "POST";
          form.action = "delete_item.php";
          form.innerHTML = `
          <input type="hidden" name="delete_id" value="${id}">
          <input type="hidden" name="csrf_token" value="${window.csrfToken}">
        `;
          document.body.appendChild(form);
          form.submit();
        }
      });
    }
  });

  // Toast (เหมือนเดิม)
  const toastData = document.documentElement.dataset.toast;
  if (toastData) {
    try {
      const d = JSON.parse(toastData);
      const t = document.createElement("div");
      t.className = `toast align-items-center text-bg-${
        d.type === "error" ? "danger" : "success"
      } border-0`;
      t.innerHTML = `
        <div class="d-flex">
          <div class="toast-body">${d.message}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>`;
      document.querySelector(".toast-container")?.appendChild(t);
      new bootstrap.Toast(t, { delay: 2000 }).show();
    } catch (e) {
      console.error("Toast parse error:", e);
    }
  }

  // Toggle Favorite (เหมือนเดิม แต่ render ใหม่ด้วย applyFilters เพื่อรักษาการกรอง)
  document.addEventListener("click", async function (e) {
    const btn = e.target.closest(".btn-favorite");
    if (!btn) return;

    e.preventDefault();

    const itemId = btn.dataset.id;
    const current = parseInt(btn.dataset.favorite);
    const newFav = current === 1 ? 0 : 1;

    try {
      const response = await fetch("toggle_favorite.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `id=${itemId}&is_favorite=${newFav}&csrf_token=${window.csrfToken}`,
      });

      // แจ้งเตือนเมื่อสำเร็จด้วย SweetAlert2
      Toast.fire({
        icon: "success",
        title: newFav === 1 ? "ปักหมุดสำเร็จ" : "ยกเลิกการปักหมุด",
        background: "#a5dc86",
      });

      if (!response.ok) throw new Error("อัพเดทไม่สำเร็จ");

      // อัพเดท UI ปุ่ม
      const icon = btn.querySelector("i");
      if (newFav === 1) {
        icon.classList.remove("bi-star", "text-secondary");
        icon.classList.add("bi-star-fill", "text-warning");
      } else {
        icon.classList.remove("bi-star-fill", "text-warning");
        icon.classList.add("bi-star", "text-secondary");
      }
      btn.dataset.favorite = newFav;

      // อัพเดทข้อมูลใน array
      items = items.map((item) =>
        item.id == itemId ? { ...item, is_favorite: newFav } : item,
      );

      // เรียงลำดับใหม่ (favorite มาก่อน)
      items.sort((a, b) => {
        if (a.is_favorite !== b.is_favorite)
          return b.is_favorite - a.is_favorite;
        return a.name.localeCompare(b.name);
      });

      // ใช้ applyFilters เพื่อให้ยังคงกรองหมวดหมู่/สถานะเดิมไว้
      applyFilters();
    } catch (err) {
      Toast.fire({
        icon: "error",
        title: "เกิดข้อผิดพลาด",
        background: "#f27474",
      });
    }
  });

  // Render ครั้งแรก (ใช้ applyFilters เพื่อรองรับค่า default)
  applyFilters();
});

// Init ทุก tooltip ในหน้า (ทำครั้งเดียว)
tippy("#myButton", {
  theme: "light", // หรือ 'material', 'google', 'translucent'
  animation: "shift-away-subtle",
  delay: [200, 100],
  arrow: true,
});

// จับการคลิกปุ่มแก้ไข (ใช้ event delegation)
document.addEventListener("click", function (e) {
  const editBtn = e.target.closest(".edit-btn");
  if (editBtn) {
    const itemId = editBtn.dataset.id;
    openEditModal(itemId);
    return; // หยุด propagation ถ้าต้องการ
  }
});

// ฟังก์ชันเปิด modal แก้ไข (ถ้ายังไม่มี ให้เพิ่ม)
window.openEditModal = function (id) {
  const item = items.find((i) => String(i.id) === String(id));
  if (!item) {
    console.error("ไม่พบอุปกรณ์ ID:", id);
    return;
  }

  // เตรียม modal เดิมให้เป็นโหมดแก้ไข
  document.getElementById("modalTitle").textContent = "แก้ไขอุปกรณ์";

  const form = document.querySelector("#addItemModal form");
  // ถ้า save_item.php รองรับทั้งเพิ่ม/แก้ไข → ใช้ action เดิมได้เลย

  document.getElementById("itemId").value = item.id;
  form.querySelector('input[name="name"]').value = item.name;

  const catSelect = form.querySelector('select[name="category_id"]');
  catSelect.value = item.category_id || "";

  document.getElementById("oldImage").value = item.image || "";

  // Preview รูปเก่า
  const preview = document.getElementById("imagePreview");
  preview.innerHTML = item.image
    ? `<img src="uploads/${item.image}" class="img-fluid rounded" style="max-height:180px;">`
    : "";

  // ถ้าต้องการแสดง stock ปัจจุบัน (แต่ส่วนใหญ่ไม่อนุญาตแก้ stock ตรงนี้)
  form.querySelector('input[name="stock"]').value = item.stock;

  new bootstrap.Modal(document.getElementById("addItemModal")).show();
};

// Manage_Removebg
document.addEventListener("DOMContentLoaded", () => {
  // คัดลอก API Key
  document.getElementById("copyApiKeyBtn")?.addEventListener("click", () => {
    const keyInput = document.getElementById("currentApiKey");
    const copyBtn = document.getElementById("copyApiKeyBtn");

    // เตรียมข้อความ/ไอคอนเดิมไว้
    const originalHTML = copyBtn.innerHTML;

    keyInput.select();

    navigator.clipboard
      .writeText(keyInput.value)
      .then(() => {
        // เปลี่ยนเป็นติ๊กถูก + สีเขียว (หรือสีที่ต้องการ)
        copyBtn.innerHTML = '<i class="bi bi-check-lg"></i>';
        copyBtn.classList.add("btn-success");
        copyBtn.classList.remove("btn-outline-secondary");
        setTimeout(() => {
          copyBtn.innerHTML = originalHTML;
          copyBtn.classList.remove("btn-success");
          copyBtn.classList.add("btn-outline-secondary");
        }, 3000);
        Toast.fire({
          icon: "success",
          title: "คัดลอก API Key แล้ว",
          background: "#a5dc86",
        });
      })
      .catch((err) => {
        console.error("คัดลอกล้มเหลว:", err);
        Toast.fire({
          icon: "error",
          title: "เกิดข้อผิดพลาด: " + err.message,
          background: "#f27474",
        });
      });
  });

  // บันทึก API Key ใหม่
  document
    .getElementById("updateApiKeyForm")
    ?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const newKey = document.getElementById("newApiKey").value.trim();
      if (!newKey) return;
      Toast.fire({
        icon: "warning",
        title: "กรุณาใส่ API Key",
        background: "#f8bb86", // สีส้มสำหรับแจ้งเตือน
      });
      if (newKey.length < 20) return;
      Toast.fire({
        icon: "warning",
        title: "API Key ดูเหมือนสั้นเกินไป กรุณาตรวจสอบ",
        background: "#f8bb86",
      });

      if (!window.csrfToken) {
        return Toast.fire({
          icon: "error",
          title: "ไม่พบ CSRF token กรุณารีเฟรชหน้าและลองใหม่",
          background: "#f27474", // สีแดงสำหรับข้อผิดพลาด
        });
      }

      try {
        const res = await fetch("update_removebg_key.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `new_key=${encodeURIComponent(
            newKey,
          )}&csrf_token=${encodeURIComponent(window.csrfToken)}`,
        });

        if (!res.ok) {
          throw new Error(`HTTP ${res.status}`);
        }

        const data = await res.json();

        if (data.success) {
          // อัปเดตช่องปัจจุบันทันที
          document.getElementById("currentApiKey").value = newKey;

          // แสดงแจ้งเตือน
          Toast.fire({
            icon: "success",
            title: "บันทึก API Key ใหม่เรียบร้อย!",
            background: "#a5dc86",
          });

          // ล้างช่อง input
          document.getElementById("newApiKey").value = "";

          // ปิด modal อัตโนมัติ
          const modalElement = document.getElementById("manageRemoveBgModal");
          const modalInstance = bootstrap.Modal.getInstance(modalElement);
          if (modalInstance) {
            modalInstance.hide();
          }
        } else {
          Toast.fire({
            icon: "error",
            title: "เกิดข้อผิดพลาด: " + err.message,
            background: "#f27474",
          });
        }
      } catch (err) {
        console.error("Error saving API Key:", err);
        Toast.fire({
          icon: "error",
          title: "เกิดข้อผิดพลาด: " + err.message,
          background: "#f27474",
        });
      }
    });
});
