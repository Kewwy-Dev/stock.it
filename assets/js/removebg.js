// assets/js/removebg.js
document.addEventListener("DOMContentLoaded", () => {
  const copyBtn = document.getElementById("copyApiKeyBtn");
  const form = document.getElementById("updateApiKeyForm");

  function notify(type, title, text) {
    if (typeof Toast !== "undefined") {
      Toast.fire({
        icon: type,
        title,
        text,
        background:
          type === "success"
            ? "#a5dc86"
            : type === "warning"
              ? "#f8bb86"
              : "#f27474",
      });
      return;
    }

    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: type,
        title,
        text,
        confirmButtonText: "ตกลง",
      });
      return;
    }

    alert(title + (text ? "\n" + text : ""));
  }

  function successCopy(btn, original) {
    btn.innerHTML = '<i class="bi bi-check-lg"></i>';
    btn.classList.add("btn-success");
    btn.classList.remove("btn-outline-secondary");
    notify("success", "คัดลอก API Key แล้ว");
    setTimeout(() => {
      btn.innerHTML = original;
      btn.classList.remove("btn-success");
      btn.classList.add("btn-outline-secondary");
    }, 2500);
  }

  function fallbackCopy(text, btn, original) {
    const temp = document.createElement("textarea");
    temp.value = text;
    temp.setAttribute("readonly", "");
    temp.style.position = "fixed";
    temp.style.top = "-9999px";
    document.body.appendChild(temp);
    temp.select();
    temp.setSelectionRange(0, 99999);
    const ok = document.execCommand("copy");
    document.body.removeChild(temp);

    if (ok) {
      successCopy(btn, original);
    } else {
      notify("error", "คัดลอกไม่สำเร็จ", "กรุณาคัดลอกด้วยมือ");
    }
  }

  if (copyBtn) {
    copyBtn.addEventListener("click", async () => {
      const keyInput = document.getElementById("currentApiKey");
      const textToCopy = keyInput?.value?.trim() || "";
      const originalHTML = copyBtn.innerHTML;

      if (!textToCopy) {
        notify("warning", "ไม่มี API Key ให้คัดลอก");
        return;
      }

      try {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(textToCopy);
          successCopy(copyBtn, originalHTML);
        } else {
          fallbackCopy(textToCopy, copyBtn, originalHTML);
        }
      } catch (err) {
        console.error("Clipboard error:", err);
        fallbackCopy(textToCopy, copyBtn, originalHTML);
      }
    });
  }

  if (form) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const newKey = document.getElementById("newApiKey")?.value?.trim() || "";

      if (!newKey) {
        notify("warning", "กรุณาใส่ API Key ใหม่");
        return;
      }

      if (newKey.length < 20) {
        notify("warning", "API Key ดูเหมือนสั้นเกินไป กรุณาตรวจสอบ");
        return;
      }

      const csrfToken = form.querySelector('input[name="csrf_token"]')?.value;
      if (!csrfToken) {
        notify("error", "ไม่พบ CSRF token", "กรุณารีเฟรชหน้าและลองใหม่");
        return;
      }

      const submitBtn = form.querySelector('button[type="submit"]');
      const originalBtnText = submitBtn?.innerHTML || "";
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML =
          '<span class="spinner-border spinner-border-sm me-2"></span> กำลังบันทึก...';
      }

      try {
        const res = await fetch("update_removebg_key.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `new_key=${encodeURIComponent(newKey)}&csrf_token=${encodeURIComponent(csrfToken)}`,
        });

        const data = await res.json();

        if (data.success) {
          const currentInput = document.getElementById("currentApiKey");
          const newInput = document.getElementById("newApiKey");
          if (currentInput) currentInput.value = newKey;
          if (newInput) newInput.value = "";

          notify("success", "บันทึก API Key ใหม่เรียบร้อย!");

          const modalEl = document.getElementById("manageRemoveBgModal");
          const modal = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
          modal?.hide();
        } else {
          notify("error", data.message || "บันทึกไม่สำเร็จ");
        }
      } catch (err) {
        console.error("Save API Key error:", err);
        notify("error", "เกิดข้อผิดพลาด", err.message || "");
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalBtnText;
        }
      }
    });
  }
});
