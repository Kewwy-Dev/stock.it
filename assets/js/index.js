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

    const companyBtn = document.getElementById("companyDropdown");
    const deptBtn = document.getElementById("departmentDropdown");
    const empBtn = document.getElementById("employeeDropdown");
    const companyVal = document.getElementById("company_id");
    const deptVal = document.getElementById("department_id");
    const empVal = document.getElementById("employee_id");

    if (companyBtn) companyBtn.dataset.required = isOut ? "true" : "false";
    if (deptBtn) deptBtn.dataset.required = isOut ? "true" : "false";
    if (empBtn) empBtn.dataset.required = isOut ? "true" : "false";

    if (!isOut) {
      companyBtn?.classList.remove("is-invalid");
      deptBtn?.classList.remove("is-invalid");
      empBtn?.classList.remove("is-invalid");
    } else {
      if (companyBtn && !companyVal?.value) companyBtn.classList.add("is-invalid");
      if (deptBtn && !deptVal?.value) deptBtn.classList.add("is-invalid");
      if (empBtn && !empVal?.value) empBtn.classList.add("is-invalid");
    }

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
      const companyVal = document.getElementById("company_id")?.value?.trim() || "";
      const deptVal = document.getElementById("department_id")?.value?.trim() || "";
      const employeeVal = document.getElementById("employee_id")?.value?.trim() || "";

      const companyBtn = document.getElementById("companyDropdown");
      const deptBtn = document.getElementById("departmentDropdown");
      const empBtn = document.getElementById("employeeDropdown");

      let errorMsg = "";

      if (!companyVal) errorMsg += "กรุณาเลือกบริษัท\n";
      if (!deptVal) errorMsg += "กรุณาเลือกแผนก\n";
      if (!employeeVal) errorMsg += "กรุณาเลือกผู้เบิก\n";

      if (errorMsg) {
        e.preventDefault(); // หยุดการ submit
        companyBtn?.classList.toggle("is-invalid", !companyVal);
        deptBtn?.classList.toggle("is-invalid", !deptVal);
        empBtn?.classList.toggle("is-invalid", !employeeVal);

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

  const categoryLabelEls = document.querySelectorAll("#categoryLabel");
  const itemFilterLabelEls = document.querySelectorAll("#itemFilterLabel");

  function setCategoryLabel(text) {
    categoryLabelEls.forEach((el) => {
      el.textContent = text;
    });
  }

  function setItemFilterLabel(text) {
    itemFilterLabelEls.forEach((el) => {
      el.textContent = text;
    });
  }
  // กรอง สต็อกต่ำ & สต็อกหมดอัตโนมัติจาก dashboard
  const urlParams = new URLSearchParams(window.location.search);
  const autoFilter = urlParams.get("filter");

  if (autoFilter === "low" || autoFilter === "zero") {
    currentRightFilter = autoFilter;

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
    document
      .querySelectorAll(`.item-filter-select${targetSelector}`)
      .forEach((btn) => btn.classList.add("active"));

    // อัปเดตข้อความบนปุ่ม dropdown
    setItemFilterLabel(displayText);

    console.log(`Auto filter applied from URL: ?filter=${autoFilter}`);
  }

  // Event: เปลี่ยนหมวดหมู่
  if (categoryFilter) {
    categoryFilter.addEventListener("change", (e) => {
      currentCategory = e.target.value;
      console.log("เลือกหมวดหมู่ →", currentCategory);
      const selectedText =
        e.target.options?.[e.target.selectedIndex]?.textContent?.trim() || "";
      setCategoryLabel(
        currentCategory === "" ? "หมวดหมู่อุปกรณ์" : selectedText,
      );
      applyFilters();
    });
  }
  // กรองIDหมวดหมู่
  const categoryItems = document.querySelectorAll(".category-select");
  categoryItems.forEach((item) => {
    item.addEventListener("click", (e) => {
      e.preventDefault();

      // ลบ active จากทุกตัวก่อน
      categoryItems.forEach((el) => el.classList.remove("active", "selected"));

      // เพิ่ม active ให้ตัวที่คลิก
      currentCategory = item.dataset.value || "";

      categoryItems.forEach((el) => {
        if ((el.dataset.value || "") === currentCategory) {
          el.classList.add("active");
        }
      });
      setCategoryLabel(
        currentCategory === "" ? "หมวดหมู่อุปกรณ์" : item.textContent.trim(),
      );

      console.log("กรองหมวดหมู่ ID:", currentCategory);
      applyFilters();
    });
  });

  // กรองชื่ออุปกรณ์ / เหลือน้อย / หมด
  function handleItemFilterClick(btn, e) {
    e.preventDefault();

    const itemFilterSelects = document.querySelectorAll(".item-filter-select");

    // ลบ active จากทุกตัวก่อน
    itemFilterSelects.forEach((el) =>
      el.classList.remove("active", "selected"),
    );

    // เพิ่ม active ให้ตัวที่คลิก
    currentRightFilter = btn.dataset.value;

    itemFilterSelects.forEach((el) => {
      if ((el.dataset.value || "") === currentRightFilter) {
        el.classList.add("active");
      }
    });

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
    setItemFilterLabel(displayText);

    console.log("เลือกตัวกรองขวา →", currentRightFilter);
    applyFilters();
  }

  function bindItemFilterSelects(root = document) {
    const itemFilterSelects = root.querySelectorAll(".item-filter-select");
    itemFilterSelects.forEach((btn) => {
      if (btn.dataset.bound === "1") return;
      btn.dataset.bound = "1";
      btn.addEventListener("click", (e) => handleItemFilterClick(btn, e));
    });
  }

  bindItemFilterSelects();

  // Dropdown
  // Dropdown ใน modal เพิ่ม/แก้ไขอุปกรณ์
  const categoryModalItems = document.querySelectorAll(".category-select-modal");
  const itemCategoryLabel = document.getElementById("itemCategoryLabel");
  const itemCategoryValue = document.getElementById("itemCategoryValue");

  categoryModalItems.forEach((item) => {
    item.addEventListener("click", (e) => {
      e.preventDefault();
      categoryModalItems.forEach((el) => el.classList.remove("active"));

      const value = item.dataset.value || "";
      if (itemCategoryValue) itemCategoryValue.value = value;
      if (itemCategoryLabel) itemCategoryLabel.textContent = item.textContent.trim();
    });
  });

  // Dropdown ใน modal เพิ่ม/เบิก
  const companyItems = document.querySelectorAll(".company-select-modal");
  const companyLabel = document.getElementById("companyLabel");
  const companyValue = document.getElementById("company_id");

  companyItems.forEach((item) => {
    item.addEventListener("click", (e) => {
      e.preventDefault();
      companyItems.forEach((el) => el.classList.remove("active"));

      const value = item.dataset.value || "";
      if (companyValue) companyValue.value = value;
      if (companyLabel) companyLabel.textContent = item.textContent.trim();
    });
  });

  const departmentItems = document.querySelectorAll(".department-select-modal");
  const departmentLabel = document.getElementById("departmentLabel");
  const departmentValue = document.getElementById("department_id");
  const employeeMenu = document.getElementById("employeeMenu");
  const employeeLabel = document.getElementById("employeeLabel");
  const employeeValue = document.getElementById("employee_id");
  const employeeDropdown = document.getElementById("employeeDropdown");

  function resetEmployeeDropdown() {
    if (employeeMenu) {
      employeeMenu.innerHTML =
        '<li><span class="dropdown-item text-muted">— เลือกแผนกก่อน —</span></li>';
    }
    if (employeeDropdown) employeeDropdown.disabled = true;
    if (employeeLabel) employeeLabel.textContent = "— เลือกผู้เบิก —";
    if (employeeValue) employeeValue.value = "";
    employeeDropdown?.classList.remove("is-invalid");
  }

  departmentItems.forEach((item) => {
    item.addEventListener("click", (e) => {
      e.preventDefault();
      departmentItems.forEach((el) => el.classList.remove("active"));

      const value = item.dataset.value || "";
      if (departmentValue) departmentValue.value = value;
      if (departmentLabel) departmentLabel.textContent = item.textContent.trim();

      resetEmployeeDropdown();

      if (!value) return;
      const filteredEmps = employees.filter(
        (emp) => String(emp.department_id) === String(value),
      );

      if (employeeMenu) {
        employeeMenu.innerHTML = "";
        if (filteredEmps.length === 0) {
          employeeMenu.innerHTML =
            '<li><span class="dropdown-item text-muted">— ไม่พบผู้เบิก —</span></li>';
        } else {
          filteredEmps.forEach((emp) => {
            const li = document.createElement("li");
            li.innerHTML = `<a class="dropdown-item employee-select-modal" href="#" data-value="${emp.id}">${esc(
              emp.name,
            )}</a>`;
            employeeMenu.appendChild(li);
          });
        }
      }

      if (employeeDropdown) employeeDropdown.disabled = false;
    });
  });

  document.addEventListener("click", function (e) {
    const employeeItem = e.target.closest(".employee-select-modal");
    if (!employeeItem) return;
    e.preventDefault();

    const value = employeeItem.dataset.value || "";
    if (employeeValue) employeeValue.value = value;
    if (employeeLabel) employeeLabel.textContent = employeeItem.textContent.trim();

    const allEmployeeItems = document.querySelectorAll(".employee-select-modal");
    allEmployeeItems.forEach((el) => el.classList.remove("active"));
    employeeItem.classList.add("active");
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

      function isInvalidApiKey(status, message) {
        const text = (message || "").toString().toLowerCase();
        if (status === 401 || status === 403) return true;
        return (
          text.includes("api key") ||
          text.includes("apikey") ||
          text.includes("unauthorized") ||
          text.includes("authentication") ||
          text.includes("invalid api")
        );
      }

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

          if (!bgData.success) {
            if (isInvalidApiKey(bgRes.status, bgData.error)) {
              Toast.fire({
                icon: "error",
                title: "API Remove.bg ไม่ถูกต้อง",
                text: "กรุณาตรวจสอบ API Key ในเมนู API Remove.bg",
                timer: 3500,
                background: "#f27474",
              });
              submitBtn.style.display = "inline-block";
              processingBtn.style.display = "none";
              return;
            }
            throw new Error(bgData.error || "ลบพื้นหลังล้มเหลว");
          }

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
          const savedItem = result.item;
          if (savedItem && savedItem.id) {
            const idx = items.findIndex(
              (it) => String(it.id) === String(savedItem.id),
            );
            if (idx >= 0) {
              items[idx] = { ...items[idx], ...savedItem };
            } else {
              items.push(savedItem);
            }

            // เรียงลำดับใหม่ (favorite มาก่อน)
            items.sort((a, b) => {
              if (a.is_favorite !== b.is_favorite)
                return b.is_favorite - a.is_favorite;
              return a.name.localeCompare(b.name);
            });

            // อัปเดตรายการใน dropdown filter แบบไม่ต้อง reload
            const filterMenus = document.querySelectorAll(
              'ul.dropdown-menu[aria-labelledby="itemDropdown"]',
            );
            filterMenus.forEach((menu) => {
              const listWrap = menu.querySelector("div");
              if (!listWrap) return;
              let option = listWrap.querySelector(
                `.item-filter-select[data-value="${savedItem.id}"]`,
              );
              if (!option) {
                const li = document.createElement("li");
                const a = document.createElement("a");
                a.className = "dropdown-item item-filter-select";
                a.href = "#";
                a.dataset.value = savedItem.id;
                a.textContent = savedItem.name;
                li.appendChild(a);
                listWrap.appendChild(li);
                bindItemFilterSelects(menu);
              } else {
                option.textContent = savedItem.name;
              }
            });

            if (String(currentRightFilter) === String(savedItem.id)) {
              setItemFilterLabel(savedItem.name);
            }

          }

          applyFilters();
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
    document.getElementById("modalTitle").innerHTML = '<i class="bi bi-boxes me-2"></i>เพิ่มอุปกรณ์';

    const form = document.querySelector("#addItemModal form");
    if (form) form.reset();

    const preview = document.getElementById("imagePreview");
    if (preview) preview.innerHTML = "";

    document.getElementById("itemId").value = "";
    document.getElementById("oldImage").value = "";

    // ถ้ามี select category ใน modal → reset เป็นค่าเริ่มต้น
    if (itemCategoryValue) itemCategoryValue.value = "";
    if (itemCategoryLabel) itemCategoryLabel.textContent = "— ไม่ระบุ —";
    categoryModalItems.forEach((el) => el.classList.remove("active"));

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

    if (companyValue) companyValue.value = "";
    if (companyLabel) companyLabel.textContent = "— ไม่ระบุ —";
    companyItems.forEach((el) => el.classList.remove("active"));

    if (departmentValue) departmentValue.value = "";
    if (departmentLabel) departmentLabel.textContent = "— เลือกแผนก —";
    departmentItems.forEach((el) => el.classList.remove("active"));

    resetEmployeeDropdown();

    new bootstrap.Modal("#transactionModal").show();
  };

  // เมื่อเลือกแผนก → โหลดพนักงาน (ย้ายไปใช้ dropdown)

  // Modal ลบ (เหมือนเดิม)
  document.addEventListener("click", function (e) {
    const deleteBtn = e.target.closest(".close-btn");
    if (deleteBtn) {
      const id = deleteBtn.dataset.id;
      const name = deleteBtn.dataset.name;

      Swal.fire({
        title: "ยืนยันการลบ?",
        html: `<strong class="text-danger">${name}</strong>`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#6c757d",
        confirmButtonText: `<i class="bi bi-trash-fill me-2"></i>ยืนยัน`,
        cancelButtonText: "ยกเลิก",
      }).then(async (result) => {
        if (result.isConfirmed) {
          try {
            const response = await fetch("delete_item.php", {
              method: "POST",
              headers: {
                "Content-Type": "application/x-www-form-urlencoded",
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
              },
              body: `delete_id=${encodeURIComponent(id)}&csrf_token=${encodeURIComponent(
                window.csrfToken,
              )}`,
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
              throw new Error(data.error || "ลบไม่สำเร็จ");
            }

            items = items.filter((it) => String(it.id) !== String(id));

            if (String(currentRightFilter) === String(id)) {
              currentRightFilter = "";
              setItemFilterLabel("ทุกอุปกรณ์");
            }

            const filterMenus = document.querySelectorAll(
              'ul.dropdown-menu[aria-labelledby="itemDropdown"]',
            );
            filterMenus.forEach((menu) => {
              const listWrap = menu.querySelector("div");
              if (!listWrap) return;
              const option = listWrap.querySelector(
                `.item-filter-select[data-value="${id}"]`,
              );
              option?.closest("li")?.remove();
            });

            applyFilters();
            Toast.fire({
              icon: "success",
              title: "ลบอุปกรณ์เรียบร้อย",
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
  document.getElementById("modalTitle").innerHTML = '<i class="bi bi-pencil-square me-2"></i>แก้ไขอุปกรณ์';

  const form = document.querySelector("#addItemModal form");
  // ถ้า save_item.php รองรับทั้งเพิ่ม/แก้ไข → ใช้ action เดิมได้เลย

  document.getElementById("itemId").value = item.id;
  form.querySelector('input[name="name"]').value = item.name;

  const categoryValueInput = document.getElementById("itemCategoryValue");
  const categoryLabelEl = document.getElementById("itemCategoryLabel");
  if (categoryValueInput) categoryValueInput.value = item.category_id || "";
  if (categoryLabelEl)
    categoryLabelEl.textContent = item.category_name
      ? item.category_name
      : "— ไม่ระบุ —";

  const modalCategoryItems = document.querySelectorAll(".category-select-modal");
  modalCategoryItems.forEach((el) => el.classList.remove("active"));
  modalCategoryItems.forEach((el) => {
    if (String(el.dataset.value || "") === String(item.category_id || "")) {
      el.classList.add("active");
    }
  });

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

