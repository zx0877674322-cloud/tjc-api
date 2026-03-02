flatpickr(".date-picker-alt", {
  altInput: true,
  altFormat: "d/m/Y",
  dateFormat: "Y-m-d",
  locale: "th",
  allowInput: true,
});

function openExportModal() {
  const modal = document.getElementById('exportExcelModal');
  modal.style.display = 'flex';
  // Force reflow
  void modal.offsetWidth;
  modal.classList.add('active');
}
function closeExportModal() {
  const modal = document.getElementById('exportExcelModal');
  modal.classList.remove('active');
  setTimeout(() => {
    modal.style.display = 'none';
  }, 300);
}

// 3. ฟังก์ชันกรองสถานะ (เมื่อคลิกการ์ด)
function filterStatus(value, type) {
  let inputId = "";
  if (type === "status") inputId = "status_input";
  else if (type === "urgency") inputId = "urgency_input";
  else if (type === "return_status") inputId = "return_input";
  else if (type === "job_type") inputId = "job_type_input";
  else if (type === "cost_filter") inputId = "cost_filter_input"; // [เพิ่มบรรทัดนี้]

  if (inputId) {
    const inputEl = document.getElementById(inputId);
    // ระบบ Toggle: ถ้าค่าเดิมตรงกับที่กดมา ให้ล้างค่า (ยกเลิกการกรอง)
    if (inputEl.value === value) {
      inputEl.value = "";
    } else {
      inputEl.value = value;
    }
  }

  updateData(); // สั่งโหลดข้อมูลใหม่ด้วย AJAX ตามโค้ดเดิมของคุณ
}

// 4. ยืนยันปิดงาน
function confirmFinish(reqId) {
  Swal.fire({
    title: "ยืนยันปิดงาน?",
    text: "สถานะจะเปลี่ยนเป็น 'เสร็จสิ้น'",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#10b981",
    confirmButtonText: "ยืนยัน",
    cancelButtonText: "ยกเลิก",
  }).then((result) => {
    if (result.isConfirmed) {
      $.post(
        "service_dashboard.php",
        {
          action: "finish_job",
          req_id: reqId,
        },
        function (res) {
          if (res.status === "success")
            Swal.fire("สำเร็จ!", "ปิดงานเรียบร้อยแล้ว", "success").then(() => updateData());
          else Swal.fire("Error!", res.message, "error");
        },
        "json",
      );
    }
  });
}

// 5. ลบรายการ
function deleteItem(reqId) {
  Swal.fire({
    title: "ยืนยันการลบ?",
    text: "ข้อมูลจะหายไปถาวร!",
    icon: "error",
    showCancelButton: true,
    confirmButtonColor: "#ef4444",
    confirmButtonText: "ลบข้อมูล",
  }).then((result) => {
    if (result.isConfirmed) {
      $.post(
        "service_dashboard.php",
        {
          action: "delete_item",
          req_id: reqId,
        },
        function (res) {
          if (res.status === "success") updateData();
        },
        "json",
      );
    }
  });
}

// 6. ดูรายการสินค้า
function viewItems(dataInput) {
  let items = [];
  try {
    items = typeof dataInput === "string" ? JSON.parse(dataInput) : dataInput;
    if (!Array.isArray(items)) items = [];
  } catch (e) {
    items = [];
  }

  let listHtml = "";
  let hasContent = false;
  if (items.length > 0) {
    listHtml =
      '<div style="text-align: left; background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0;"><ul style="margin: 0; padding-left: 20px; color: #1e293b; font-size: 1rem; line-height: 1.8;">';
    items.forEach((box) => {
      let products = Array.isArray(box.product) ? box.product : [box.product];
      products.forEach((pName) => {
        if (pName && pName.trim() !== "") {
          listHtml += `<li style="margin-bottom: 5px; font-weight: 500;">${pName}</li>`;
          hasContent = true;
        }
      });
    });
    listHtml += "</ul></div>";
  }
  if (!hasContent)
    listHtml =
      '<div style="text-align:center; padding: 20px; color: #94a3b8;">- ไม่ระบุรายการสินค้า -</div>';

  Swal.fire({
    title:
      '<div style="color:#0369a1; font-size:1.25rem; font-weight:700;"><i class="fas fa-boxes"></i> รายการสินค้าทั้งหมด</div>',
    html: listHtml,
    width: 450,
    confirmButtonText: "ปิด",
    confirmButtonColor: "#64748b",
  });
}

// 7. ดูรายละเอียดปัญหา (แบบแยกกล่องตามรายการ)
// 7. ดูรายละเอียดปัญหา (Vibrant & Bouncy Animation Edition)
function viewDetails(itemsData) {
  let items = [];

  // 1. ตรวจสอบและแปลงข้อมูล
  try {
    if (Array.isArray(itemsData)) {
      items = itemsData;
    } else if (typeof itemsData === "string") {
      if (
        itemsData.trim().startsWith("[") ||
        itemsData.trim().startsWith("{")
      ) {
        items = JSON.parse(itemsData);
      } else if (itemsData.trim() !== "") {
        items = [
          {
            product: ["รายการทั่วไป"],
            issue: itemsData,
            initial_advice: "",
            assessment: "",
          },
        ];
      }
    }
  } catch (e) {
    console.error("JSON Parse Error:", e);
    items = [];
  }

  // 2. สร้าง HTML พร้อม CSS Animation สุดล้ำ
  let htmlContent = `
    <div class="detail-scroll-area">
  `;

  if (!items || items.length === 0) {
    htmlContent += `
        <div style="text-align:center; padding:50px 20px; background:#ffffff; border-radius:24px; border:2px dashed #cbd5e1; margin-top:20px; box-shadow: 0 10px 20px rgba(0,0,0,0.02);">
            <div style="width:80px; height:80px; background:#f1f5f9; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 15px; color:#94a3b8; animation: floatUpDown 3s ease-in-out infinite;">
                <i class="fas fa-box-open fa-2x"></i>
            </div>
            <div style="font-weight:800; color:#475569; font-size:1.1rem;">ไม่มีข้อมูลรายละเอียด</div>
            <div style="font-size:0.85rem; color:#94a3b8; margin-top:5px;">ไม่พบการระบุอาการหรือการประเมินสำหรับรายการนี้</div>
        </div>`;
  } else {
    items.forEach((item, index) => {
      let products = "-";
      if (Array.isArray(item.product)) {
        products = item.product.join(", ");
      } else if (item.product) {
        products = item.product;
      }

      let issue = item.issue || item.issue_description || "-";
      let advice = item.initial_advice || "";
      let assessment = item.assessment || "";

      let jobType = item.job_type || "";
      let jobTypeBadge = "";
      if (jobType && jobType !== "other") {
        jobTypeBadge = `<div class="vib-job-badge"><i class="fas fa-tag"></i> ${jobType}</div>`;
      }

      let delayCard = index * 0.15; // เด้งการ์ดไล่ระดับ
      let delayBox1 = delayCard + 0.2;
      let delayBox2 = delayBox1 + 0.1;
      let delayBox3 = delayBox2 + 0.1;

      htmlContent += `
        <div class="vib-card" style="animation-delay: ${delayCard}s;">
            <div class="vib-header">
                <div class="vib-idx">#${index + 1}</div>
                <div class="vib-name">${products}</div>
                ${jobTypeBadge}
            </div>

            <div class="info-box box-issue" style="animation-delay: ${delayBox1}s;">
                <div class="info-icon-wrap"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="info-content">
                    <div class="info-title">อาการที่พบ / ปัญหา</div>
                    <div class="info-desc">${issue}</div>
                </div>
            </div>

            ${
              advice
                ? `
            <div class="info-box box-advice" style="animation-delay: ${delayBox2}s;">
                <div class="info-icon-wrap"><i class="fas fa-lightbulb"></i></div>
                <div class="info-content">
                    <div class="info-title">คำแนะนำเบื้องต้น</div>
                    <div class="info-desc">${advice}</div>
                </div>
            </div>`
                : ""
            }

            ${
              assessment
                ? `
            <div class="info-box box-assess" style="animation-delay: ${delayBox3}s;">
                <div class="info-icon-wrap"><i class="fas fa-clipboard-check"></i></div>
                <div class="info-content">
                    <div class="info-title">การประเมินงาน</div>
                    <div class="info-desc">${assessment}</div>
                </div>
            </div>`
                : ""
            }

            ${
              (item.attached_files && Array.isArray(item.attached_files) && item.attached_files.length > 0)
                ? `
            <div class="info-box box-files" style="animation-delay: ${delayBox3 + 0.1}s;">
                <div class="info-icon-wrap" style="color:#0ea5e9; background:#e0f2fe;"><i class="fas fa-paperclip"></i></div>
                <div class="info-content">
                    <div class="info-title">ไฟล์แนบ</div>
                    <div class="info-desc" style="display:flex; flex-wrap:wrap; gap:8px; margin-top:5px;">
                        ${item.attached_files.map(file => {
                            let ext = file.split('.').pop().toLowerCase();
                            let icon = "fa-file";
                            let isImg = false;
                            if (['jpg','jpeg','png','gif','webp'].includes(ext)) { icon = "fa-file-image"; isImg = true; }
                            else if (['pdf'].includes(ext)) icon = "fa-file-pdf";
                            else if (['doc','docx'].includes(ext)) icon = "fa-file-word";
                            else if (['xls','xlsx'].includes(ext)) icon = "fa-file-excel";
                            
                            let fileUrl = 'uploads/service_requests/' + encodeURIComponent(file);
                            
                            return `<a href="${fileUrl}" target="_blank" class="file-badge" style="display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:15px; background:#f1f5f9; border:1px solid #e2e8f0; color:#334155; font-size:0.8rem; text-decoration:none; transition:0.2s;" onmouseover="this.style.background='#e2e8f0';" onmouseout="this.style.background='#f1f5f9';">
                                <i class="fas ${icon}" style="${isImg ? 'color:#0ea5e9;' : 'color:#64748b;'}"></i>
                                ${file}
                            </a>`;
                        }).join('')}
                    </div>
                </div>
            </div>`
                : ""
            }
        </div>`;
    });
  }

  htmlContent += "</div>";

  Swal.fire({
    title: `
        <div style="display:flex; align-items:center; justify-content:center; gap:15px; font-family:Prompt; margin-bottom: 5px; padding-top: 10px;">
            <div style="width:55px; height:55px; background:linear-gradient(135deg, #8b5cf6, #d946ef); border-radius:18px; display:flex; align-items:center; justify-content:center; color:#fff; font-size: 1.5rem; animation: pulseIconGlow 2s infinite;">
                <i class="fas fa-file-signature"></i>
            </div> 
            <div style="text-align: left;">
                <div style="font-weight:900; color:#1e293b; font-size:1.4rem; letter-spacing:-0.5px;">รายละเอียดงาน</div>
                <div style="font-size:0.85rem; color:#8b5cf6; font-weight:600;">Issue & Assessment Details</div>
            </div>
        </div>`,
    html: htmlContent,
    width: "650px", // ขยายกว้างขึ้นนิดนึงเพื่อให้รับกับ UI ใหม่
    showCloseButton: true,
    showConfirmButton: false,
    background: "#f8fafc", // สีพื้นหลังเทาอมฟ้าอ่อนๆ เพื่อขับให้การ์ดสีขาวเด้งออกมา
    customClass: { popup: "rounded-24 shadow-2xl" }, // เพิ่มคลาสเงาสวยๆ ของ SweetAlert
  });
}
// 8. นับถอยหลัง SLA
// ฟังก์ชันนับถอยหลัง (Format ตามรูปภาพ: X วัน HH:MM:SS)
function updateSLACountdown() {
  $(".sla-countdown-wrapper").each(function () {
    let deadlineStr = $(this).data("deadline");
    if (!deadlineStr) return;

    let deadline = new Date(deadlineStr).getTime();
    let now = new Date().getTime();
    let diff = deadline - now; // เวลาที่เหลือ

    // คำนวณเวลา
    let days = Math.floor(Math.abs(diff) / (1000 * 60 * 60 * 24));
    let hours = Math.floor(
      (Math.abs(diff) % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60),
    );
    let minutes = Math.floor((Math.abs(diff) % (1000 * 60 * 60)) / (1000 * 60));
    let seconds = Math.floor((Math.abs(diff) % (1000 * 60)) / 1000);

    // เติมเลข 0 ข้างหน้า
    hours = hours < 10 ? "0" + hours : hours;
    minutes = minutes < 10 ? "0" + minutes : minutes;
    seconds = seconds < 10 ? "0" + seconds : seconds;

    let html = "";
    let color = "#2563eb"; // สีฟ้า (ค่าเริ่มต้น)

    if (diff < 0) {
      // --- กรณีล่าช้า (สีแดง) ---
      color = "#dc2626";
      html += `<span style="font-size:0.8rem;">ล่าช้า </span>`; // เพิ่มคำว่าล่าช้าเล็กๆ
      if (days > 0) html += `${days} วัน `;
      html += `${hours}:${minutes}:${seconds}`;
    } else {
      // --- กรณีปกติ (สีฟ้า ตามรูป) ---
      // ถ้าเหลือน้อยกว่า 24 ชม. ให้เป็นสีส้มเตือน (ถ้าไม่ชอบให้ลบบรรทัดนี้ทิ้ง จะกลับเป็นฟ้า)
      if (diff <= 24 * 60 * 60 * 1000) color = "#d97706";

      if (days > 0) html += `${days} วัน `;
      html += `${hours}:${minutes}:${seconds}`;
    }

    // อัปเดต HTML และสี
    $(this).html(html).css("color", color);

    // อัปเดตสีวันที่ด้านบนด้วย (ถ้าต้องการให้เปลี่ยนตามกัน)
    $(this)
      .closest("div")
      .prev()
      .prev()
      .find("span:last-child")
      .css("color", color);
  });
}
$(document).ready(function () {
  updateSLACountdown();
  setInterval(updateSLACountdown, 1000);
});

// 9. ฟังก์ชันรับของกลับ / นำของออก (Premium Design + Animation)
function receiveItem(reqId) {
  Swal.fire({
    title: "กำลังโหลดข้อมูล...",
    allowOutsideClick: false,
    didOpen: () => Swal.showLoading(),
  });

  $.ajax({
    url: "service_dashboard.php",
    type: "POST",
    data: { action: "get_latest_item_data", req_id: reqId },
    dataType: "json",
    success: function (rawData) {
      Swal.close();
      if (!rawData) rawData = {};

      const superClean = (str) => {
        if (!str) return "";
        let s = str.toString().toLowerCase();
        if (s.includes(":")) s = s.split(":")[0];
        return s.replace(/[^a-z0-9ก-๙]/g, "");
      };

      const extractFromProjectField = (source) => {
        let extracted = new Set();
        if (!source) return [];
        try {
          let parsed = typeof source === "string" ? JSON.parse(source) : source;
          if (Array.isArray(parsed)) {
            parsed.forEach((obj) => {
              let pName = obj.product || obj.name || "";
              if (Array.isArray(pName)) {
                pName.forEach((v) => {
                  if (v) extracted.add(v.trim());
                });
              } else if (pName) {
                extracted.add(pName.trim());
              }
            });
          }
        } catch (e) {
          source
            .toString()
            .split(/[\r\n,]+/)
            .forEach((v) => {
              let cleanV = v
                .replace(/^\d+\.\s*/, "")
                .replace(/^\[+|\]+$/g, "")
                .trim();
              if (cleanV && !cleanV.includes("{")) extracted.add(cleanV);
            });
        }
        return Array.from(extracted);
      };

      let finishedListClean = (rawData.finished_items || []).map(superClean);
      let itemsStatus = rawData.items_status || {};
      let itemsFromDB = extractFromProjectField(rawData.project_item_name_raw);
      let itemsMoved = rawData.accumulated_moved || [];
      let allSource = Array.from(new Set([...itemsFromDB, ...itemsMoved]));
      let moveHistory = rawData.items_moved || [];

      let displayItems = allSource.map((originalName) => {
        let cleanName = superClean(originalName);
        let isFinished = finishedListClean.includes(cleanName);

        let currentStat = "";
        for (let key in itemsStatus) {
          if (superClean(key) === cleanName) {
            currentStat = itemsStatus[key];
            break;
          }
        }

        let isReceived =
          currentStat.includes("at_office") || currentStat === "back_from_shop";
        let isAtExternal = currentStat === "at_external";

        let history =
          moveHistory.find((h) => superClean(h.name) === cleanName) || null;

        return {
          name: originalName,
          isFinished,
          isReceived,
          isAtExternal,
          history,
        };
      });

      let htmlForm = `
            <div id="modal_scroll_container" class="item-scroll-area">`;

      if (displayItems.length === 0) {
        htmlForm += `<div style="text-align:center; padding:40px; color:#94a3b8; font-family:'Prompt';"><i class="fas fa-box-open fa-3x" style="display:block; margin-bottom:15px; opacity:0.3;"></i>ไม่พบข้อมูลรายการสินค้า</div>`;
      } else {
        displayItems.forEach((it, idx) => {
          let isLocked = it.isFinished || it.isReceived || it.isAtExternal;
          let cardClass = "item-card-3d";
          let badge = "";

          if (it.isFinished) {
            cardClass += " finished";
            badge =
              '<span style="font-size:0.65rem; background:#10b981; color:#fff; padding:3px 8px; border-radius:20px; margin-left:8px; font-weight:700; box-shadow:0 2px 4px rgba(16,185,129,0.3); white-space:nowrap;"><i class="fas fa-check"></i> เสร็จสิ้น</span>';
          } else if (it.isReceived) {
            cardClass += " received";
            badge =
              '<span style="font-size:0.65rem; background:#3b82f6; color:#fff; padding:3px 8px; border-radius:20px; margin-left:8px; font-weight:700; box-shadow:0 2px 4px rgba(59,130,246,0.3); white-space:nowrap;"><i class="fas fa-building"></i> รับกลับแล้ว</span>';
          } else if (it.isAtExternal) {
            cardClass += " at-external";
            badge =
              '<span style="font-size:0.65rem; background:#f97316; color:#fff; padding:3px 8px; border-radius:20px; margin-left:8px; font-weight:700; box-shadow:0 2px 4px rgba(249,115,22,0.3); white-space:nowrap;"><i class="fas fa-store"></i> อยู่ร้านนอก</span>';
          }

          let hDest = it.history ? it.history.destination : "office";
          let hRemark = it.history ? it.history.remark : "";
          let hShopName =
            it.history && it.history.shop_info ? it.history.shop_info.name : "";
          let hShopOwner =
            it.history && it.history.shop_info
              ? it.history.shop_info.owner
              : "";
          let hShopPhone =
            it.history && it.history.shop_info
              ? it.history.shop_info.phone
              : "";

          let delay = idx * 0.08;

          htmlForm += `
                    <div class="${cardClass}" id="card_${idx}" style="animation-delay: ${delay}s">
                        <div class="card-header-clickable" onclick="toggleItemCardViewOnly(${idx}, ${isLocked})">
                            <input type="checkbox" id="chk_${idx}" style="display:none;" ${isLocked ? "checked disabled" : `onchange="toggleItemCard(${idx})"`} onclick="event.stopPropagation()">
                            <div class="chk-3d-box"><i class="fas fa-check"></i></div>
                            <div style="flex:1; font-size:0.95rem; font-weight:700; color:#1e293b; display:flex; align-items:center; flex-wrap:wrap; gap:4px; min-width:0;">
                                <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:100%;">${it.name}</span> 
                                ${badge}
                            </div>
                            <i class="fas fa-chevron-down" style="color:#cbd5e1; font-size:1.1rem; transition:transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); margin-left:8px;" id="arrow_${idx}"></i>
                        </div>

                        <div class="card-content-reveal" id="details_${idx}">
                            
                            <div class="label-title"><i class="fas fa-map-marker-alt" style="color:#f43f5e;"></i> ระบุปลายทาง</div>
                            <div class="dest-selector">
                                <label style="${isLocked ? "cursor:default" : "cursor:pointer"};">
                                    <input type="radio" name="dest_${idx}" value="office" ${hDest === "office" ? "checked" : ""} onchange="toggleShopFields(${idx})" style="display:none;" ${isLocked ? "disabled" : ""}>
                                    <div class="dest-ui ${isLocked ? "disabled" : ""}"><i class="fas fa-building" style="margin-right:6px;"></i> กลับบริษัท</div>
                                </label>
                                <label style="${isLocked ? "cursor:default" : "cursor:pointer"};">
                                    <input type="radio" name="dest_${idx}" value="external" ${hDest === "external" ? "checked" : ""} onchange="toggleShopFields(${idx})" style="display:none;" ${isLocked ? "disabled" : ""}>
                                    <div class="dest-ui ${isLocked ? "disabled" : ""}"><i class="fas fa-store" style="margin-right:6px;"></i> ร้านซ่อมภายนอก</div>
                                </label>
                            </div>
                            
                            <div id="shop_fields_${idx}" class="shop-fields-grid" style="${hDest === "external" ? "display:block;" : ""}">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                    <div style="font-size:0.75rem; font-weight:700; color:#b45309;"><i class="fas fa-info-circle"></i> ข้อมูลร้านซ่อมภายนอก</div>
                                    ${!isLocked ? `<button type="button" onclick="applyShopToAllChecked(${idx}, this)" class="btn-copy-shop"><i class="fas fa-copy"></i> ใช้ร้านนี้กับทุกชิ้น</button>` : ""}
                                </div>
                                <input type="text" id="s_name_${idx}" class="form-input-3d" placeholder="ชื่อร้านค้า *" value="${hShopName}" ${isLocked ? "readonly" : ""}>
                                <div class="inner-grid-2col">
                                    <input type="text" id="s_owner_${idx}" class="form-input-3d" placeholder="ผู้ติดต่อ" value="${hShopOwner}" ${isLocked ? "readonly" : ""}>
                                    <input type="text" id="s_phone_${idx}" class="form-input-3d" placeholder="เบอร์โทร" value="${hShopPhone}" ${isLocked ? "readonly" : ""}>
                                </div>
                            </div>

                            <div class="label-title"><i class="fas fa-pen-alt" style="color:#8b5cf6;"></i> หมายเหตุ / อาการเสีย <span style="color:#ef4444; margin-left:4px;">*</span></div>
                            <textarea id="remark_${idx}" class="form-input-3d" rows="2" placeholder="ระบุเหตุผลในการนำออก หรืออาการเสีย..." ${isLocked ? "readonly" : ""}>${hRemark}</textarea>

                            ${
                              !isLocked
                                ? `
                            <div class="label-title" style="margin-top:15px;"><i class="fas fa-paperclip" style="color:#10b981;"></i> รูปภาพ / หลักฐาน</div>
                            <div class="upload-area-3d" id="file_zone_${idx}" onclick="document.getElementById('file_${idx}').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <div style="font-size:0.8rem; font-weight:600; color:#64748b; margin-top:2px;" id="file_label_${idx}">คลิกเพื่อแนบไฟล์</div>
                                <div style="font-size:0.7rem; color:#94a3b8;">(รูปภาพ หรือ PDF)</div>
                                <input type="file" id="file_${idx}" style="display:none;" accept="image/*,.pdf,.avif,.heic" onchange="if(this.files.length>0){document.getElementById('file_label_'+${idx}).innerText='✅ แนบไฟล์สำเร็จ: '+this.files[0].name; document.getElementById('file_label_'+${idx}).style.color='#ea580c'; document.getElementById('file_zone_'+${idx}).style.borderColor='#ea580c'; document.getElementById('file_zone_'+${idx}).style.background='#fff7ed';}">
                            </div>`
                                : ""
                            }
                        </div>
                    </div>`;
        });
      }
      htmlForm += `</div>`;

      Swal.fire({
        title:
          '<div style="font-family:Prompt; font-weight:800; font-size:1.5rem; color:#1e293b; margin-bottom:-5px;"><i class="fas fa-truck-loading" style="color:#ea580c;"></i> นำของออกจากหน้างาน</div>',
        html: htmlForm,
        width: "600px",
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save"></i> บันทึกข้อมูล',
        confirmButtonColor: "#ea580c",
        cancelButtonText: "ยกเลิก",
        customClass: {
          popup: "rounded-2xl shadow-2xl",
          confirmButton: "font-bold rounded-xl px-5 py-2",
          cancelButton: "font-bold rounded-xl px-5 py-2",
        },
        preConfirm: () => {
          const formData = new FormData();
          formData.append("action", "receive_item");
          formData.append("req_id", reqId);
          let count = 0;
          displayItems.forEach((it, idx) => {
            if (it.isFinished || it.isReceived || it.isAtExternal) return; // ข้ามตัวที่ล็อค
            const chk = document.getElementById(`chk_${idx}`);
            if (chk && chk.checked) {
              count++;
              const destInp = document.querySelector(
                `input[name="dest_${idx}"]:checked`,
              );
              if (!destInp) return;
              const dest = destInp.value;
              const remark = document
                .getElementById(`remark_${idx}`)
                .value.trim();

              if (!remark) {
                Swal.showValidationMessage(
                  `กรุณาใส่หมายเหตุรายการที่ ${idx + 1}`,
                );
                return false;
              }

              formData.append(`items[${idx}][name]`, it.name);
              formData.append(`items[${idx}][destination]`, dest);
              formData.append(`items[${idx}][remark]`, remark);

              if (dest === "external") {
                const sName = document
                  .getElementById(`s_name_${idx}`)
                  .value.trim();
                if (!sName) {
                  Swal.showValidationMessage(
                    `กรุณาใส่ชื่อร้านค้ารายการที่ ${idx + 1}`,
                  );
                  return false;
                }
                formData.append(`items[${idx}][shop_name]`, sName);
                formData.append(
                  `items[${idx}][shop_owner]`,
                  document.getElementById(`s_owner_${idx}`).value,
                );
                formData.append(
                  `items[${idx}][shop_phone]`,
                  document.getElementById(`s_phone_${idx}`).value,
                );
              }
              const fInp = document.getElementById(`file_${idx}`);
              if (fInp && fInp.files.length > 0)
                formData.append(`item_files_${idx}`, fInp.files[0]);
            }
          });
          if (count === 0) {
            Swal.showValidationMessage(
              "กรุณาเลือกรายการสินค้าที่ยังค้างอยู่อย่างน้อย 1 รายการ",
            );
            return false;
          }
          return formData;
        },
      }).then((res) => {
        if (res.isConfirmed) {
          Swal.fire({
            title: "กำลังบันทึก...",
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
          });
          $.ajax({
            url: "service_dashboard.php",
            type: "POST",
            data: res.value,
            processData: false,
            contentType: false,
            dataType: "json",
            success: (r) => {
              if (r.status === "success") {
                Swal.fire({
                    icon: 'success',
                    title: '<div style="font-family:Prompt; font-weight:800; font-size:1.5rem; color:#10b981;">บันทึกข้อมูลสำเร็จ!</div>',
                    showConfirmButton: false,
                    timer: 1500, }).then(() => updateData());
              } else {
                Swal.fire("Error", r.message, "error");
              }
            },
          });
        }
      });
    },
  });
}

// 🟢 ฟังก์ชันควบคุมการเปิดดู (View-Only)
window.toggleItemCardViewOnly = function (index, isLocked) {
  if (!isLocked) {
    const chk = document.getElementById(`chk_${index}`);
    chk.checked = !chk.checked;
    window.toggleItemCard(index);
  } else {
    const card = document.getElementById(`card_${index}`);
    const details = $(`#details_${index}`);
    const arrow = document.getElementById(`arrow_${index}`);
    if (card.classList.contains("active")) {
      card.classList.remove("active");
      arrow.style.transform = "rotate(0deg)";
      details.slideUp(250);
    } else {
      card.classList.add("active");
      arrow.style.transform = "rotate(180deg)";
      details.slideDown(250);
    }
  }
};

window.toggleItemCard = function (index) {
  const chk = document.getElementById(`chk_${index}`);
  const card = document.getElementById(`card_${index}`);
  const details = $(`#details_${index}`);
  const arrow = document.getElementById(`arrow_${index}`);
  if (chk.checked) {
    card.classList.add("active");
    arrow.style.transform = "rotate(180deg)";
    details.slideDown(250);
  } else {
    card.classList.remove("active");
    arrow.style.transform = "rotate(0deg)";
    details.slideUp(250);
  }
};

window.toggleShopFields = function (index) {
  const destInp = document.querySelector(`input[name="dest_${index}"]:checked`);
  if (!destInp) return;
  const dest = destInp.value;
  const shopBox = $(`#shop_fields_${index}`);
  const scrollBox = $("#modal_scroll_container");
  if (dest === "external") {
    shopBox.slideDown(250, function () {
      let cardBottom =
        document.getElementById(`card_${index}`).offsetTop +
        document.getElementById(`card_${index}`).offsetHeight;
      scrollBox.animate(
        { scrollTop: cardBottom - scrollBox.height() + 30 },
        400,
      );
    });
  } else {
    shopBox.slideUp(250);
  }
};

// 9.1 ฟังก์ชันช่วยสลับช่องกรอก (ปลายทาง)
function toggleDest(t) {
  $("#ext_box").toggle(t === "external");
  $("#off_info").toggle(t === "office");
}

// 10. ฟังก์ชันตรวจรับสินค้า / รับช่วงต่อ (แก้ไขให้รายการไม่หายหลังกดบันทึก)
function confirmOfficeReceipt(reqId, jsonInput) {
  let data = typeof jsonInput === "string" ? JSON.parse(jsonInput) : jsonInput;
  if (typeof data === "string") data = JSON.parse(data);

  let itemsStatus = data.items_status || {};
  let itemsMovedList = data.items_moved || [];
  let currentHolding = data.accumulated_moved || [];
  let officeLogs =
    data.details && data.details.office_log ? data.details.office_log : [];

  // ดึงรายการที่คืนลูกค้าไปแล้ว เพื่อเอามาเช็คไม่ให้แสดงซ้ำ
  let returnedItems =
    data.details &&
    data.details.customer_return &&
    data.details.customer_return.items_returned
      ? data.details.customer_return.items_returned
      : [];

  let pendingItems = [];
  currentHolding.forEach((itemName) => {
    let currentStatus = itemsStatus[itemName] || "";

    // เช็คว่า "ต้องไม่ได้ถูกคืนลูกค้าไปแล้ว"
    let isAlreadyReturned = returnedItems.includes(itemName);

    if (currentStatus !== "at_external" && !isAlreadyReturned) {
      let initialMove = itemsMovedList.find((it) => it.name === itemName);
      let lastHandover = [...officeLogs]
        .reverse()
        .find((log) => log.items && log.items.includes(itemName));
      pendingItems.push({
        name: itemName,
        initialRemark: initialMove ? initialMove.remark : "-",
        initialFile: initialMove ? initialMove.file : null,
        lastRemark: lastHandover ? lastHandover.msg : null,
        lastFile: lastHandover ? lastHandover.file : null,
        dest:
          currentStatus === "at_office_unconfirmed"
            ? "รอตรวจรับเข้า"
            : "เปลี่ยนมือผู้ถือ",
      });
    }
  });

  let itemsHtml = "";
  pendingItems.forEach((it, idx) => {
    let uniqueId = `conf_chk_${idx}`;

    // 🎨 ปรับปุ่มดูรูปตอนนำออก (แก้ปัญหาเครื่องหมายคำพูดตีกัน)
    let initialImgBtn = it.initialFile
      ? `<button type="button" class="btn-mini-3d outline" style="margin-top:8px; cursor:pointer;" 
            onclick="let url='uploads/proofs/${it.initialFile}'; let ext=url.split('.').pop().toLowerCase(); if(['avif','heic'].includes(ext)){ let win=window.open(); win.document.write('<html><body style=&quot;margin:0; background:#111; display:flex; align-items:center; justify-content:center;&quot;><img src=&quot;'+url+'&quot; style=&quot;max-width:100%; max-height:100vh; box-shadow:0 0 50px rgba(0,0,0,0.5);&quot;></body></html>'); win.document.close(); }else{ window.open(url,'_blank'); }">
            <i class="fas fa-image text-slate-500"></i> ดูรูปตอนนำออก
         </button>`
      : "";

    let lastHandoverHtml = "";
    if (it.lastRemark) {
      // 🎨 ปรับปุ่มดูรูปล่าสุด (แก้ปัญหาเครื่องหมายคำพูดตีกัน)
      let lastImgBtn = it.lastFile
        ? `<button type="button" class="btn-mini-3d solid-blue" style="margin-top:6px; cursor:pointer;" 
              onclick="let url='uploads/proofs/${it.lastFile}'; let ext=url.split('.').pop().toLowerCase(); if(['avif','heic'].includes(ext)){ let win=window.open(); win.document.write('<html><body style=&quot;margin:0; background:#111; display:flex; align-items:center; justify-content:center;&quot;><img src=&quot;'+url+'&quot; style=&quot;max-width:100%; max-height:100vh; box-shadow:0 0 50px rgba(0,0,0,0.5);&quot;></body></html>'); win.document.close(); }else{ window.open(url,'_blank'); }">
              <i class="fas fa-camera"></i> ดูรูปล่าสุด
           </button>`
        : "";

      lastHandoverHtml = `
            <div style="margin-top:10px; padding-top:10px; border-top:1px dashed #cbd5e1; width:100%;">
                <div style="font-size:10px; color:#3b82f6; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;"><i class="fas fa-history"></i> บันทึกรับล่าสุด:</div>
                <div style="font-size:13px; color:#1e3a8a; font-weight:500; line-height:1.5; word-break:break-word;">${it.lastRemark}</div>
                ${lastImgBtn}
            </div>`;
    }

    // 🎨 Tag สี
    let badgeStyle =
      it.dest === "รอตรวจรับเข้า"
        ? "background: linear-gradient(135deg, #fef3c7, #fde68a); color: #b45309; border: 1px solid #fcd34d;"
        : "background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #15803d; border: 1px solid #86efac;";

    itemsHtml += `
        <div class="conf-card-premium log-anim" style="animation-delay: ${idx * 0.1}s;">
            <label for="${uniqueId}" style="display:flex; align-items:flex-start; padding:15px; cursor:pointer; gap:12px; margin:0; width:100%; box-sizing:border-box;">
                <div style="margin-top:2px;">
                    <input type="checkbox" id="${uniqueId}" class="item-chk-conf custom-chk" value="${it.name}">
                </div>
                <div style="flex:1; min-width:0;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:8px;">
                        <span style="font-weight:800; color:#1e293b; font-size:14px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${it.name}</span>
                        <span style="font-size:10px; padding:3px 10px; border-radius:50px; font-weight:800; flex-shrink:0; box-shadow:0 2px 4px rgba(0,0,0,0.05); ${badgeStyle}">${it.dest}</span>
                    </div>
                    <div class="info-inner-box">
                        <div style="font-size:10px; color:#64748b; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;"><i class="fas fa-info-circle"></i> ข้อมูลตอนนำออก:</div>
                        <div style="font-size:13px; color:#334155; line-height:1.5; word-break:break-word;">${it.initialRemark}</div>
                        ${initialImgBtn}
                        ${lastHandoverHtml}
                    </div>
                </div>
            </label>
        </div>`;
  });

  Swal.fire({
    title: "",
    html: `
        <style>
            @keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
            .log-anim { animation: fadeInUp 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards; opacity: 0; }
            
            /* การ์ดหลัก */
            .conf-card-premium { 
                background: #ffffff; border: 2px solid #e2e8f0; border-radius: 16px; margin-bottom: 12px; 
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); 
                text-align: left; box-sizing: border-box; overflow: hidden;
            }
            .conf-card-premium:hover { border-color: #93c5fd; box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.15); transform: translateY(-2px); }
            .conf-card-premium:has(input:checked) { border-color: #3b82f6; background: #f0f9ff; box-shadow: 0 0 0 1px #3b82f6, 0 10px 15px -3px rgba(59, 130, 246, 0.2); }

            /* Checkbox */
            .custom-chk { width: 22px; height: 22px; accent-color: #2563eb; cursor: pointer; border-radius: 6px; }

            /* กล่องสีเทาด้านใน */
            .info-inner-box { background: #f8fafc; padding: 12px; border-radius: 12px; border-left: 4px solid #94a3b8; width: 100%; box-sizing: border-box; transition: all 0.3s; }
            .conf-card-premium:has(input:checked) .info-inner-box { background: #ffffff; border-left-color: #3b82f6; }

            /* ปุ่มจิ๋วแบบ 3D */
            .btn-mini-3d { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 50px; font-size: 11px; text-decoration: none !important; font-weight: 700; transition: all 0.2s; border: none; box-sizing: border-box; }
            .btn-mini-3d.outline { background: linear-gradient(to bottom, #ffffff, #f1f5f9); color: #475569; border: 1px solid #cbd5e1; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
            .btn-mini-3d.outline:hover { background: #f8fafc; color: #0f172a; border-color: #94a3b8; transform: translateY(-1px); }
            .btn-mini-3d.solid-blue { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1d4ed8; border: 1px solid #93c5fd; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.15); }
            .btn-mini-3d.solid-blue:hover { filter: brightness(0.95); transform: translateY(-1px); }

            /* Input 3D Premium */
            .swal-label { font-size: 14px; font-weight: 800; color: #334155; display: block; text-align: left; margin-bottom: 8px; }
            .swal-input-premium { width: 100%; border: 2px solid #e2e8f0; border-radius: 12px; padding: 12px; font-size: 14px; box-sizing: border-box; transition: all 0.3s; background: #f8fafc; font-family: 'Prompt', sans-serif; color: #1e293b; }
            .swal-input-premium:focus { background: #ffffff; border-color: #3b82f6; outline: none; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15); }

            /* 🔥 กล่องอัปโหลดไฟล์สไตล์พรีเมียม */
            .custom-file-upload { position: relative; width: 100%; border: 2px dashed #cbd5e1; border-radius: 12px; background: #f8fafc; padding: 20px 10px; text-align: center; transition: all 0.3s; cursor: pointer; box-sizing: border-box; }
            .custom-file-upload:hover { border-color: #3b82f6; background: #eff6ff; }
            .custom-file-upload input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 10; }
            .upload-icon { font-size: 28px; color: #94a3b8; margin-bottom: 8px; transition: color 0.3s; }
            .custom-file-upload:hover .upload-icon { color: #3b82f6; }
            .upload-text { font-size: 13px; color: #64748b; font-family: 'Prompt', sans-serif; font-weight: 600; transition: color 0.3s; }
            .custom-file-upload:hover .upload-text { color: #1e3a8a; }

            /* Scrollbar */
            ::-webkit-scrollbar { width: 6px; }
            ::-webkit-scrollbar-track { background: transparent; }
            ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
            ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        </style>

        <div style="padding:0 5px; box-sizing:border-box;">
            <div style="text-align:center; margin-bottom:25px; animation: fadeInUp 0.4s ease;">
                <div style="width:65px; height:65px; background:linear-gradient(135deg, #3b82f6, #1d4ed8); color:#fff; border-radius:20px; display:flex; align-items:center; justify-content:center; margin:0 auto 15px; box-shadow:0 10px 25px -5px rgba(59, 130, 246, 0.5);">
                    <i class="fas fa-handshake fa-2x"></i>
                </div>
                <div style="font-size:24px; font-weight:900; color:#0f172a; letter-spacing:-0.5px;">รับช่วงต่อ / ตรวจรับ</div>
                <div style="font-size:14px; color:#64748b; margin-top:2px;">กรุณาเลือกรายการและระบุหมายเหตุเพื่อยืนยัน</div>
            </div>

            <div style="text-align:left; margin-bottom:20px;">
                <label class="swal-label"><i class="fas fa-list-check" style="color:#3b82f6; margin-right:5px;"></i> รายการสินค้า <span style="color:#94a3b8; font-weight:500;">(${pendingItems.length} รายการ)</span></label>
                <div style="max-height:40vh; overflow-y:auto; padding:4px; margin-bottom:5px; box-sizing:border-box; border-radius:12px;">
                    ${itemsHtml || '<div style="text-align:center; padding:30px; background:#f8fafc; border-radius:12px; border:2px dashed #cbd5e1; color:#94a3b8; font-weight:600;"><i class="fas fa-inbox fa-2x" style="margin-bottom:10px; display:block; opacity:0.5;"></i>ไม่มีรายการรอรับ</div>'}
                </div>
            </div>

            <div style="text-align:left; margin-bottom:20px;">
                <label class="swal-label"><i class="fas fa-edit" style="color:#f59e0b; margin-right:5px;"></i> หมายเหตุการตรวจรับ <span style="color:#ef4444;">*</span></label>
                <textarea id="conf_remark" class="swal-input-premium" placeholder="ระบุสภาพของล่าสุด, ปัญหาที่พบ หรือข้อความถึงผู้ส่ง..." style="height:90px; resize:none;"></textarea>
            </div>

            <div style="text-align:left;">
                <label class="swal-label"><i class="fas fa-paperclip" style="color:#10b981; margin-right:5px;"></i> แนบรูปภาพ / เอกสาร <span style="color:#94a3b8; font-weight:500; font-size:12px;">(รูปภาพ หรือ PDF)</span></label>
                
                <div class="custom-file-upload">
                    <input type="file" id="conf_file" accept="image/*,.pdf,.avif,.heic" onchange="
                        let fileName = this.files[0] ? this.files[0].name : 'คลิกเพื่อเลือกไฟล์ หรือลากมาวาง';
                        let textColor = this.files[0] ? '#2563eb' : '#64748b';
                        let iconColor = this.files[0] ? '#3b82f6' : '#94a3b8';
                        document.getElementById('conf_file_name').innerText = fileName;
                        document.getElementById('conf_file_name').style.color = textColor;
                        document.getElementById('conf_file_icon').style.color = iconColor;
                        document.getElementById('conf_file_icon').className = this.files[0] ? (this.files[0].name.toLowerCase().endsWith('.pdf') ? 'fas fa-file-pdf' : 'fas fa-image') : 'fas fa-cloud-upload-alt';
                    ">
                    <div class="upload-icon"><i id="conf_file_icon" class="fas fa-cloud-upload-alt"></i></div>
                    <div class="upload-text" id="conf_file_name">คลิกเพื่อเลือกไฟล์ (รูปภาพ หรือ PDF) หรือลากมาวาง</div>
                </div>
            </div>
        </div>
        `,
    width: "600px",
    showCancelButton: true,
    confirmButtonText:
      '<i class="fas fa-check-circle" style="margin-right:6px;"></i> ยืนยันการรับ',
    cancelButtonText:
      '<i class="fas fa-times" style="margin-right:6px;"></i> ยกเลิก',
    customClass: {
      confirmButton: "swal2-confirm-btn-blue",
      cancelButton: "swal2-cancel-btn-gray",
    },
    preConfirm: () => {
      const checked = document.querySelectorAll(".item-chk-conf:checked");
      const remark = document.getElementById("conf_remark").value.trim();
      if (checked.length === 0)
        return Swal.showValidationMessage(
          "⚠️ กรุณาเลือกสินค้าอย่างน้อย 1 รายการ",
        );
      if (!remark)
        return Swal.showValidationMessage("⚠️ กรุณากรอกหมายเหตุการตรวจรับ");

      let sel = [];
      checked.forEach((c) => sel.push(c.value));
      return {
        items: sel,
        remark: remark,
        file: document.getElementById("conf_file").files[0],
      };
    },
  }).then((res) => {
    if (res.isConfirmed) {
      let fd = new FormData();
      fd.append("action", "confirm_office_receipt");
      fd.append("req_id", reqId);
      fd.append("remark", res.value.remark);
      if (res.value.file) fd.append("proof_file", res.value.file);
      res.value.items.forEach((it, idx) =>
        fd.append(`checked_items[${idx}]`, it),
      );

      Swal.fire({
        title: "กำลังบันทึก...",
        text: "กรุณารอสักครู่",
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
      });

      $.ajax({
        url: "service_dashboard.php",
        type: "POST",
        data: fd,
        processData: false,
        contentType: false,
        dataType: "json",
        success: (response) => {
          if (response.status === "success") {
            Swal.fire({
                icon: 'success',
                title: '<div style="font-family:Prompt; font-weight:800; font-size:1.5rem; color:#10b981;">บันทึกข้อมูลสำเร็จ!</div>',
                showConfirmButton: false,
                timer: 1500,
                backdrop: `rgba(0,0,0,0.7)`
            }).then(() => {
                location.reload();
            });
          } else {
            Swal.fire("เกิดข้อผิดพลาด", response.message, "error");
          }
        },
        error: () =>
          Swal.fire("เกิดข้อผิดพลาด", "ไม่สามารถติดต่อเซิร์ฟเวอร์ได้", "error"),
      });
    }
  });
}

// 11. ดูประวัติ Timeline (Premium UI + Smooth Animations)
function viewReceiverDetails(jsonInput) {
  let data = typeof jsonInput === "string" ? JSON.parse(jsonInput) : jsonInput;
  if (typeof data === "string") data = JSON.parse(data);
  let d = data.details || {};

  let stepCount = 1;
  let delayCounter = 0;

  // --- Helper: แปลงวันที่ (DD/MM/YYYY HH:mm) เป็นตัวเลขเพื่อใช้เรียงลำดับ ---
  function parseDate(dateStr) {
    if (!dateStr || dateStr === "-") return 0;
    let parts = dateStr.split(/[\s/:]+/);
    if (parts.length >= 5) {
      let day = parseInt(parts[0], 10);
      let month = parseInt(parts[1], 10) - 1;
      let year = parseInt(parts[2], 10);
      if (year < 100) year += 2000;
      let hours = parseInt(parts[3], 10);
      let mins = parseInt(parts[4], 10);
      return new Date(year, month, day, hours, mins).getTime();
    }
    return 0;
  }

  // --- Helper: จัด Format วันที่ให้สวยงาม (แยกบรรทัดวันที่กับเวลา) ---
  function formatTimeDisplay(dateStr) {
    if (!dateStr || dateStr === "-") return '<span class="time-date">-</span>';
    let split = dateStr.split(" ");
    if (split.length >= 2) {
      return `<span class="time-date">${split[0]}</span><span class="time-clock"><i class="far fa-clock"></i> ${split[1]}</span>`;
    }
    return `<span class="time-date">${dateStr}</span>`;
  }

  // --- Helper: สร้าง HTML สำหรับ Timeline Item (ดีไซน์พรีเมียม) ---
  const createItem = (type, icon, title, subtitle, content, extraHtml = "") => {
    delayCounter += 0.12; // หน่วงเวลาให้โผล่ทีละกล่อง
    let colorClass = "";
    let iconBg = "";
    let shadowColor = "";

    switch (type) {
      case "start":
        colorClass = "blue";
        iconBg = "linear-gradient(135deg, #3b82f6, #1d4ed8)";
        shadowColor = "rgba(59, 130, 246, 0.3)";
        break;
      case "shop":
        colorClass = "orange";
        iconBg = "linear-gradient(135deg, #f59e0b, #b45309)";
        shadowColor = "rgba(245, 158, 11, 0.3)";
        break;
      case "check":
        colorClass = "green";
        iconBg = "linear-gradient(135deg, #10b981, #047857)";
        shadowColor = "rgba(16, 185, 129, 0.3)";
        break;
      case "back":
        colorClass = "pink";
        iconBg = "linear-gradient(135deg, #ec4899, #be185d)";
        shadowColor = "rgba(236, 72, 153, 0.3)";
        break;
      case "finish":
        colorClass = "purple";
        iconBg = "linear-gradient(135deg, #8b5cf6, #5b21b6)";
        shadowColor = "rgba(139, 92, 246, 0.3)";
        break;
      default:
        colorClass = "gray";
        iconBg = "linear-gradient(135deg, #94a3b8, #475569)";
        shadowColor = "rgba(148, 163, 184, 0.3)";
    }

    return `
        <div class="timeline-row" style="animation-delay: ${delayCounter}s;">
            <div class="timeline-time-col">
                <div class="time-text">${formatTimeDisplay(subtitle)}</div>
            </div>
            <div class="timeline-line-col">
                <div class="timeline-icon-circle" style="background: ${iconBg}; box-shadow: 0 0 0 5px #fff, 0 5px 15px ${shadowColor};">
                    <i class="fas ${icon}" style="color:#fff; font-size:1.1rem;"></i>
                </div>
                <div class="timeline-line"></div>
            </div>
            <div class="timeline-content-col">
                <div class="timeline-card hover-lift">
                    <div class="card-header-sm">
                        <span class="step-badge bg-${colorClass}">Step ${stepCount++}</span>
                        <span class="header-title text-${colorClass}">${title}</span>
                    </div>
                    <div class="card-body-sm">
                        ${content}
                        ${extraHtml}
                    </div>
                </div>
            </div>
        </div>`;
  };

  let events = []; // เก็บประวัติทั้งหมดเพื่อนำมาเรียง

  // 1. นำของออก / ส่งร้าน (ดึงจาก items_moved)
  if (data.items_moved && data.items_moved.length > 0) {
    let move_groups = {};
    // จัดกลุ่มรายการที่ทำพร้อมกัน
    data.items_moved.forEach((m) => {
      let m_at = m.at || d.pickup_at || data.db_received_at || "-";
      let m_by = m.by || d.pickup_by || data.db_received_by || "ไม่ระบุชื่อ";
      let dest = m.destination || "office";
      let shopName = m.shop_info ? m.shop_info.name : "";

      let groupKey = m_at + "_" + m_by + "_" + dest + "_" + shopName;

      if (!move_groups[groupKey]) {
        move_groups[groupKey] = {
          at: m_at,
          by: m_by,
          dest: dest,
          shop_info: m.shop_info,
          items: [],
        };
      }
      let attach = m.file
        ? `<a href="uploads/proofs/${m.file}" target="_blank" class="btn-attach-mini"><i class="fas fa-image"></i> ดูรูป</a>`
        : "";
      move_groups[groupKey].items.push(
        `<li><i class="fas fa-caret-right" style="color:#cbd5e1; margin-right:5px;"></i> <b>${m.name}</b> ${m.remark ? `<span style="color:#94a3b8; font-weight:normal;">(${m.remark})</span>` : ""} ${attach}</li>`,
      );
    });

    // นำกลุ่มที่จัดไว้ แปลงเป็น Event
    for (let key in move_groups) {
      let g = move_groups[key];
      let title =
        g.dest === "external"
          ? "ส่งซ่อมร้านภายนอก"
          : "นำของกลับบริษัท (จากหน้างาน)";
      let icon = g.dest === "external" ? "fa-store" : "fa-building";
      let type = g.dest === "external" ? "shop" : "start";

      let content = `<div class="user-action-text"><i class="fas fa-user-tag"></i> ผู้ดำเนินการ: <b>${g.by}</b></div>`;

      if (g.dest === "external" && g.shop_info) {
        content += `
                <div class="shop-info-box">
                    <div class="shop-name-title"><i class="fas fa-store-alt"></i> ${g.shop_info.name || "-"}</div>
                    <div class="shop-contact-sub"><i class="fas fa-user-tie"></i> ติดต่อ: ${g.shop_info.owner || "-"} | <i class="fas fa-phone"></i> ${g.shop_info.phone || "-"}</div>
                </div>`;
      }

      content += `<div class="remark-text">
                            <div class="remark-title">📦 รายการสินค้า:</div>
                            <ul class="clean-list">${g.items.join("")}</ul>
                        </div>`;

      events.push({
        type: type,
        icon: icon,
        title: title,
        at: g.at,
        by: g.by,
        content: content,
        timestamp: parseDate(g.at),
        order: 1,
      });
    }
  } else if (d.pickup_by) {
    // Fallback ของเก่า
    events.push({
      type: "start",
      icon: "fa-dolly",
      title: "นำออกจากหน้างาน",
      at: d.pickup_at || "-",
      by: d.pickup_by,
      content: `<div class="user-action-text"><i class="fas fa-user-tag"></i> ผู้ดำเนินการ: <b>${d.pickup_by}</b></div>
                      <div class="remark-text"><i class="fas fa-quote-left" style="color:#cbd5e1;"></i> ${d.pickup_remark || "-"}</div>`,
      timestamp: parseDate(d.pickup_at),
      order: 1,
    });
  }

  // 2. ประวัติในบริษัท (office_log)
  if (d.office_log && d.office_log.length > 0) {
    d.office_log.forEach((log) => {
      let isBack = log.status === "back_from_shop";
      let type = isBack ? "back" : "check";
      let icon = isBack ? "fa-undo-alt" : "fa-clipboard-check";
      let title = isBack
        ? "รับของกลับจากร้านซ่อม"
        : "ตรวจสอบ / รับช่วงต่อ (ในบริษัท)";
      let attach = log.file
        ? `<a href="uploads/${isBack ? "repairs" : "proofs"}/${log.file}" target="_blank" class="btn-attach-full"><i class="fas fa-paperclip"></i> ดูไฟล์หลักฐานแนบ</a>`
        : "";

      let expenseTable = "";
      if (isBack && log.expenses && log.expenses.length > 0) {
        expenseTable = `
                <div class="modern-expense-table">
                    <div class="table-header"><i class="fas fa-file-invoice-dollar"></i> รายละเอียดค่าใช้จ่าย</div>
                    <table>
                        <thead>
                            <tr>
                                <th style="text-align:left;">รายการ</th>
                                <th style="text-align:center; width:40px;">จำนวน</th>
                                <th style="text-align:right;">รวม (฿)</th>
                            </tr>
                        </thead>
                        <tbody>`;
        log.expenses.forEach((ex) => {
          let total = parseFloat(ex.total || 0).toLocaleString("en-US", {
            minimumFractionDigits: 2,
          });
          expenseTable += `
                            <tr>
                                <td style="color:#334155; font-weight:500;">${ex.name}</td>
                                <td style="text-align:center; color:#64748b;">${ex.qty}</td>
                                <td style="text-align:right; color:#db2777; font-weight:600;">${total}</td>
                            </tr>`;
        });
        let grandTotal = parseFloat(log.total_cost || 0).toLocaleString(
          "en-US",
          { minimumFractionDigits: 2 },
        );
        expenseTable += `
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" style="text-align:right;">ยอดสุทธิ</td>
                                <td style="text-align:right; font-size:1.05rem; color:#be185d;">${grandTotal}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>`;
      }

      let itemsHtml = "";
      if (log.items && log.items.length > 0) {
        itemsHtml = `<div class="item-tag-box"><i class="fas fa-box-open"></i> <b>รายการ:</b> ${log.items.join(", ")}</div>`;
      }

      let content = `
                <div class="user-action-text"><i class="fas fa-user-check"></i> ผู้ดำเนินการ: <b>${log.by || "-"}</b></div>
                ${isBack && log.shop ? `<div class="shop-name-mini"><i class="fas fa-store"></i> จากร้าน: <b>${log.shop}</b></div>` : ""}
                ${itemsHtml}
                ${log.msg ? `<div class="remark-text"><i class="fas fa-comment-dots" style="color:#94a3b8;"></i> ${log.msg}</div>` : ""}
                ${expenseTable}
            `;

      events.push({
        type: type,
        icon: icon,
        title: title,
        at: log.at || "-",
        by: log.by,
        content: content,
        extraHtml: attach,
        timestamp: parseDate(log.at),
        order: 2,
      });
    });
  }

  // 3. จบงาน / ส่งมอบลูกค้า (return_history)
  if (data.return_history && data.return_history.length > 0) {
    data.return_history.forEach((rh, idx) => {
      let stars = "";
      for (let i = 1; i <= 5; i++) {
        stars +=
          i <= (rh.rating || 0)
            ? '<i class="fas fa-star" style="color:#f59e0b; text-shadow:0 1px 2px rgba(245,158,11,0.3);"></i> '
            : '<i class="fas fa-star" style="color:#e2e8f0;"></i> ';
      }
      let attach = rh.file
        ? `<a href="uploads/returns/${rh.file}" target="_blank" class="btn-attach-full btn-purple"><i class="fas fa-image"></i> รูปส่งงาน (บิลรวม)</a>`
        : "";

      let itemsListHtml = "";
      if (rh.items_detail) {
        itemsListHtml = '<ul class="clean-list" style="margin-bottom:10px;">';
        rh.items_detail.forEach((it) => {
          let itemFileLink = it.file
            ? `<a href="uploads/returns/${it.file}" target="_blank" class="btn-attach-mini"><i class="fas fa-image"></i> รูป</a>`
            : "";
          itemsListHtml += `<li><i class="fas fa-check-circle" style="color:#10b981; margin-right:5px;"></i> <b>${it.name}</b> ${itemFileLink}</li>`;
        });
        itemsListHtml += "</ul>";
      } else if (rh.items) {
        itemsListHtml = `<div class="item-tag-box"><i class="fas fa-box"></i> <b>สินค้า:</b> ${rh.items.join(", ")}</div>`;
      }

      events.push({
        type: "finish",
        icon: "fa-flag-checkered",
        title: `ส่งมอบ / คืนลูกค้า (รอบที่ ${idx + 1})`,
        at: rh.at || "-",
        by: rh.by,
        content: `<div class="user-action-text"><i class="fas fa-user-check"></i> ผู้ดำเนินการ: <b>${rh.by || "-"}</b></div>
                          <div class="rating-box">
                              <span class="rating-label">ความพึงพอใจ:</span>
                              <span class="stars">${stars}</span>
                              <span class="rating-number">(${rh.rating}/5)</span>
                          </div>
                          <div class="remark-title" style="margin-top:10px;">📦 รายการที่ส่งมอบ:</div>
                          ${itemsListHtml}
                          ${rh.remark ? `<div class="remark-text"><i class="fas fa-comment"></i> "${rh.remark}"</div>` : ""}`,
        extraHtml: attach,
        timestamp: parseDate(rh.at),
        order: 3,
      });
    });
  }

  // 🏆 ทำการเรียงลำดับเวลา
  events.sort((a, b) => {
    if (a.timestamp === b.timestamp) return a.order - b.order;
    return a.timestamp - b.timestamp;
  });

  // วาด Timeline HTML
  let timelineHtml = '<div class="premium-timeline">';
  events.forEach((e) => {
    timelineHtml += createItem(
      e.type,
      e.icon,
      e.title,
      e.at,
      e.content,
      e.extraHtml,
    );
  });

  if (events.length === 0) {
    timelineHtml += `
        <div class="empty-timeline">
            <div class="empty-icon"><i class="fas fa-folder-open"></i></div>
            <div class="empty-text">ยังไม่มีประวัติการดำเนินการในขณะนี้</div>
        </div>`;
  }
  timelineHtml += "</div>";

  // --- UI Popup (SweetAlert2) ---
  Swal.fire({
    title: "",
    html: `
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap');
            
            #swal2-html-container { font-family: 'Prompt', sans-serif !important; }
            
            /* Timeline Container */
            .premium-timeline { display: flex; flex-direction: column; position: relative; padding: 10px 5px 20px 5px; }
            .timeline-row { display: flex; gap: 15px; position: relative; opacity: 0; animation: slideInFade 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; margin-bottom: 20px; }
            
            /* Time Column */
            .timeline-time-col { width: 85px; text-align: right; padding-top: 15px; flex-shrink: 0; }
            .time-text { display: flex; flex-direction: column; justify-content: center; align-items: flex-end; }
            .time-date { font-size: 0.8rem; font-weight: 700; color: #334155; }
            .time-clock { font-size: 0.7rem; color: #64748b; font-weight: 500; margin-top: 2px; }
            
            /* Center Line & Icon */
            .timeline-line-col { width: 46px; position: relative; display: flex; flex-direction: column; align-items: center; flex-shrink: 0; }
            .timeline-icon-circle { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; z-index: 2; position: relative; margin-top: 5px; transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
            .timeline-row:hover .timeline-icon-circle { transform: scale(1.15) rotate(10deg); }
            .timeline-line { width: 2px; background: linear-gradient(to bottom, #cbd5e1, #f1f5f9); position: absolute; top: 47px; bottom: -25px; left: 50%; transform: translateX(-50%); z-index: 1; }
            .timeline-row:last-child .timeline-line { display: none; }
            
            /* Content Card */
            .timeline-content-col { flex-grow: 1; text-align: left; }
            .timeline-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 15px 18px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02), 0 2px 4px -1px rgba(0,0,0,0.02); transition: all 0.3s ease; position: relative; }
            .timeline-card::before { content: ''; position: absolute; left: -7px; top: 20px; width: 14px; height: 14px; background: #fff; transform: rotate(45deg); border-left: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; border-bottom-left-radius: 3px; transition: border-color 0.3s ease; }
            
            .hover-lift:hover { transform: translateY(-4px) translateX(4px); box-shadow: 0 12px 25px -5px rgba(0,0,0,0.08); border-color: #cbd5e1; }
            .hover-lift:hover::before { border-color: #cbd5e1; }
            
            /* Header inside Card */
            .card-header-sm { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; border-bottom: 1px dashed #f1f5f9; padding-bottom: 8px; }
            .step-badge { font-size: 0.65rem; padding: 3px 10px; border-radius: 20px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
            .header-title { font-size: 1rem; font-weight: 800; }
            
            /* Typography & Elements */
            .user-action-text { font-size: 0.85rem; color: #475569; margin-bottom: 6px; }
            .shop-info-box { background: #fffbeb; border-left: 3px solid #f59e0b; padding: 8px 12px; border-radius: 0 8px 8px 0; margin-bottom: 8px; }
            .shop-name-title { font-weight: 700; color: #b45309; font-size: 0.9rem; }
            .shop-contact-sub { font-size: 0.75rem; color: #d97706; margin-top: 3px; }
            .shop-name-mini { font-size: 0.8rem; color: #be185d; margin-top: 4px; font-weight: 600; }
            
            .remark-text { background: #f8fafc; padding: 10px 12px; border-radius: 8px; font-size: 0.85rem; color: #334155; margin-top: 8px; border: 1px solid #f1f5f9; }
            .remark-title { font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 4px; text-transform: uppercase; }
            .clean-list { margin: 0; padding-left: 5px; list-style: none; font-size: 0.85rem; color: #1e293b; }
            .clean-list li { margin-bottom: 4px; display: flex; align-items: center; flex-wrap: wrap; }
            
            .item-tag-box { display: inline-block; background: #f1f5f9; color: #475569; padding: 5px 10px; border-radius: 8px; font-size: 0.8rem; margin-top: 5px; border: 1px solid #e2e8f0; }
            
            .rating-box { display: flex; align-items: center; gap: 8px; background: #fdfaf5; border: 1px solid #fef3c7; padding: 6px 12px; border-radius: 50px; width: fit-content; margin-top: 5px; }
            .rating-label { font-size: 0.75rem; font-weight: 700; color: #b45309; }
            .rating-number { font-size: 0.8rem; font-weight: 800; color: #f59e0b; }
            
            /* Buttons */
            .btn-attach-mini { display: inline-flex; align-items: center; gap: 4px; background: #eff6ff; color: #2563eb; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; text-decoration: none; transition: 0.2s; border: 1px solid #bfdbfe; margin-left: auto; }
            .btn-attach-mini:hover { background: #bfdbfe; color: #1d4ed8; }
            
            .btn-attach-full { display: inline-flex; align-items: center; justify-content: center; gap: 8px; margin-top: 12px; background: linear-gradient(135deg, #f1f5f9, #e2e8f0); color: #475569; padding: 8px 15px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 700; border: 1px solid #cbd5e1; transition: 0.2s; width: 100%; box-sizing: border-box; }
            .btn-attach-full:hover { background: #e2e8f0; color: #334155; transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
            .btn-purple { background: linear-gradient(135deg, #ede9fe, #ddd6fe) !important; color: #6d28d9 !important; border-color: #c4b5fd !important; }
            .btn-purple:hover { background: #ddd6fe !important; color: #5b21b6 !important; }
            
            /* Custom Table */
            .modern-expense-table { margin-top: 12px; background: #fff; border: 1px solid #fbcfe8; border-radius: 10px; overflow: hidden; }
            .modern-expense-table .table-header { background: #fdf2f8; padding: 8px 12px; font-weight: 700; color: #be185d; font-size: 0.8rem; border-bottom: 1px solid #fbcfe8; }
            .modern-expense-table table { width: 100%; border-collapse: collapse; }
            .modern-expense-table th { background: #fff; color: #9d174d; font-size: 0.75rem; padding: 6px 12px; border-bottom: 1px dashed #fbcfe8; }
            .modern-expense-table td { padding: 8px 12px; font-size: 0.8rem; border-bottom: 1px dashed #fce7f3; }
            .modern-expense-table tfoot td { background: #fdf2f8; border-top: 1px solid #fbcfe8; font-weight: 700; color: #831843; border-bottom: none; }
            
            /* Colors */
            .text-blue { color: #2563eb; } .bg-blue { background: #dbeafe; color: #1e40af; }
            .text-orange { color: #d97706; } .bg-orange { background: #fef3c7; color: #92400e; }
            .text-green { color: #059669; } .bg-green { background: #d1fae5; color: #065f46; }
            .text-pink { color: #db2777; } .bg-pink { background: #fce7f3; color: #9d174d; }
            .text-purple { color: #7c3aed; } .bg-purple { background: #ede9fe; color: #5b21b6; }
            .text-gray { color: #64748b; } .bg-gray { background: #f1f5f9; color: #475569; }
            
            /* Empty State */
            .empty-timeline { text-align: center; padding: 40px 20px; color: #94a3b8; background: #f8fafc; border-radius: 16px; border: 2px dashed #e2e8f0; animation: slideInFade 0.5s ease; }
            .empty-icon { font-size: 3rem; margin-bottom: 15px; color: #cbd5e1; }
            .empty-text { font-weight: 600; font-size: 1rem; }

            @keyframes slideInFade { from { opacity: 0; transform: translateX(20px) translateY(10px); } to { opacity: 1; transform: translateX(0) translateY(0); } }
        </style>

        <div style="padding: 0;">
            <div style="text-align: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px dashed #cbd5e1;">
                <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #f1f5f9, #e2e8f0); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; color: #475569; box-shadow: inset 0 2px 4px rgba(255,255,255,0.8), 0 4px 6px rgba(0,0,0,0.05);">
                    <i class="fas fa-history fa-xl"></i>
                </div>
                <div style="font-size: 1.25rem; font-weight: 800; color: #1e293b; letter-spacing: -0.5px;">ประวัติการดำเนินการ</div>
                <div style="font-size: 0.85rem; color: #64748b; font-weight: 500;">Tracking & Status History</div>
            </div>
            
            <div style="max-height: 550px; overflow-y: auto; padding-right: 10px; overflow-x: hidden;">
                ${timelineHtml}
            </div>
        </div>
        `,
    width: "650px", // ขยายให้กว้างขึ้นนิดนึงเพื่อรับความสวย
    showConfirmButton: true,
    confirmButtonText: "ปิดหน้าต่าง",
    confirmButtonColor: "#475569",
    buttonsStyling: true,
    customClass: {
      popup: "rounded-2xl shadow-2xl",
      confirmButton: "px-6 py-2 font-bold text-sm rounded-full",
    },
  });
}

// 12. ฟังก์ชันส่งคืนลูกค้า / จบงาน (ฉบับสมบูรณ์: โชว์ประวัติการประเมินแยกตามรอบ)
function returnToCustomer(reqId, jsonInput, isEditMode = false) {
  let data = typeof jsonInput === "string" ? JSON.parse(jsonInput) : jsonInput;
  if (typeof data === "string") data = JSON.parse(data);

  let d = data.details || {};
  let repairSummaries = data.item_repair_summaries || {};
  let itemsStatus = data.items_status || {};
  let alreadyReturned =
    d.customer_return && d.customer_return.items_returned
      ? d.customer_return.items_returned
      : [];
  let finishedItems = data.finished_items || [];
  let returnHistory = data.return_history || []; // 🔥 ดึงประวัติการคืนแบบแยกตามรอบ

  let allItems =
    data.all_project_items || data.accumulated_moved || data.items || [];
  if (allItems.length === 0 && data.items_moved) {
    allItems = [...new Set(data.items_moved.map((i) => i.name))];
  }

  // ---------------------------------------------------------
  // 🟢 โหมดดูรายละเอียด (Read Only)
  // ---------------------------------------------------------
  let totalDoneList = [...new Set([...alreadyReturned, ...finishedItems])];
  if (
    d.customer_return &&
    d.customer_return.at &&
    !isEditMode &&
    totalDoneList.length === allItems.length
  ) {
    // 1. สร้าง HTML รายการสินค้า
    let itemsHtml = allItems
      .map((it) => {
        let itClean = it.trim();
        let isWasReturned = alreadyReturned.includes(itClean);
        let statusText = isWasReturned
          ? "คืนลูกค้าแล้ว"
          : "เสร็จสิ้น (หน้างาน)";
        let itemSum = repairSummaries[itClean]
          ? `<div style="margin-top:5px; padding-top:5px; border-top:1px dashed #ddd6fe; font-size:0.8rem; color:#059669; font-style:italic;"><i class="fas fa-wrench"></i> สรุปซ่อม: ${repairSummaries[itClean]}</div>`
          : "";
        return `<div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:10px 12px; border-radius:10px; margin-bottom:8px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:0.9rem; color:#14532d; font-weight:700;"><i class="fas fa-check-circle" style="color:#10b981;"></i> ${itClean}</span>
                    <span style="font-size:10px; background:#10b981; color:#fff; padding:2px 8px; border-radius:10px; font-weight:800;">${statusText}</span>
                </div>
                ${itemSum}
            </div>`;
      })
      .join("");

    // 🔥 2. สร้าง HTML ประวัติการประเมินแยกตามรอบ
    let historyHtml = "";
    if (returnHistory.length > 0) {
      historyHtml = returnHistory
        .map((h, idx) => {
          let hStars = "";
          for (let i = 1; i <= 5; i++)
            hStars +=
              i <= (parseInt(h.rating) || 0)
                ? '<span style="color:#f59e0b; font-size:1.1rem;">★</span>'
                : '<span style="color:#e2e8f0; font-size:1.1rem;">★</span>';
          let hItems = (h.items || []).join(", ");
          let hFile = h.file
            ? `<a href="uploads/returns/${h.file}" target="_blank" style="background:#f1f5f9; color:#475569; padding:3px 8px; border-radius:10px; font-size:0.75rem; text-decoration:none;"><i class="fas fa-image"></i> รูปแนบ</a>`
            : "";

          return `
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px; margin-bottom:10px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px dashed #e2e8f0; padding-bottom:6px; margin-bottom:8px;">
                        <span style="font-size:0.8rem; color:#64748b; font-weight:600;"><i class="fas fa-history"></i> คืนรอบที่ ${idx + 1} (${h.at})</span>
                        <span>${hStars}</span>
                    </div>
                    <div style="font-size:0.85rem; color:#334155; margin-bottom:6px;"><b>📦 สินค้า:</b> ${hItems}</div>
                    <div style="font-size:0.85rem; color:#92400e; background:#fffbeb; border:1px solid #fcd34d; padding:6px 10px; border-radius:6px;"><b>💬 หมายเหตุ:</b> ${h.remark || "-"}</div>
                    ${hFile ? `<div style="text-align:right; margin-top:8px;">${hFile}</div>` : ""}
                </div>`;
        })
        .join("");
    }

    Swal.fire({
      title: "",
      html: `<div style="text-align:left; padding:5px;">
                <div style="text-align:center; margin-bottom:20px;">
                    <div style="width:60px; height:60px; background:#ede9fe; color:#8b5cf6; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; box-shadow: 0 4px 12px rgba(139, 92, 246, 0.2);"><i class="fas fa-file-signature fa-2x"></i></div>
                    <div style="font-size:1.4rem; font-weight:800; color:#4c1d95;">รายละเอียดการส่งมอบงาน</div>
                </div>
                
                <label style="font-weight:800; color:#db2777; display:block; margin-bottom:8px;">⭐ ประวัติการประเมินลูกค้า (แยกตามรอบ):</label>
                <div style="max-height:200px; overflow-y:auto; padding-right:5px; margin-bottom:15px; background:#f8fafc; padding:10px; border-radius:12px;">
                    ${historyHtml || '<div style="text-align:center; color:#94a3b8; font-size:0.85rem;">- ไม่มีประวัติการประเมิน -</div>'}
                </div>

                <label style="font-weight:800; color:#4c1d95; display:block; margin-bottom:8px;">📦 รายการสินค้าทั้งหมด:</label>
                <div style="max-height:200px; overflow-y:auto; padding-right:5px;">${itemsHtml}</div>
                
                <div style="margin-top:20px; font-size:0.75rem; color:#94a3b8; text-align:center; border-top:1px solid #f1f5f9; padding-top:10px;">ข้อมูลสรุปล่าสุดเมื่อ ${d.customer_return.at}</div>
            </div>`,
      width: "550px",
      showCancelButton: true,
      confirmButtonText: '<i class="fas fa-edit"></i> แก้ไข/บันทึกเพิ่ม',
      confirmButtonColor: "#f59e0b",
      cancelButtonText: "ปิด",
      customClass: { popup: "rounded-24" },
    }).then((res) => {
      if (res.isConfirmed) returnToCustomer(reqId, jsonInput, true);
    });
    return;
  }

  // ---------------------------------------------------------
  // 🟠 โหมดกรอกข้อมูล (Input Form)
  // ---------------------------------------------------------
  let moveHistory = data.items_moved || [];
  let itemCheckHtml = '<div class="item-grid-wrapper">';

  allItems.forEach((itName, idx) => {
    let itNameTrim = itName.trim();
    let uniqueId = `ret_itm_${reqId}_${idx}`;
    let isDoneBefore = alreadyReturned.includes(itNameTrim);
    let isMarkedFinished = finishedItems.includes(itNameTrim);
    let isTrulyDone = isDoneBefore || isMarkedFinished;

    if (isTrulyDone) {
      let label = isDoneBefore ? "คืนลูกค้าแล้ว" : "เสร็จสิ้น (หน้างาน)";
      itemCheckHtml += `
            <div class="item-card-3d active" style="background:#f0fdf4; border-color:#bbf7d0; opacity:0.9; margin-bottom:10px;">
                <input type="checkbox" class="return-item-chk" value="${itNameTrim}" checked disabled style="display:none;" data-has-summary="1">
                <div style="display:flex; align-items:center; gap:12px; padding:12px 15px;">
                    <div style="width:22px; height:22px; background:#10b981; color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.75rem;"><i class="fas fa-check"></i></div>
                    <span style="flex-grow:1; font-weight:700; color:#14532d; font-size:0.95rem; text-decoration:line-through; opacity:0.6;">${itNameTrim}</span>
                    <span style="font-size:0.7rem; background:#10b981; color:#fff; padding:2px 8px; border-radius:12px; font-weight:800;">✅ ${label}</span>
                </div>
            </div>`;
    } else {
      let currentStatus = itemsStatus[itNameTrim] || "";
      let hasSummary =
        repairSummaries[itNameTrim] &&
        repairSummaries[itNameTrim].trim() !== "";
      let isAtExternal = currentStatus === "at_external";
      let isLocked = !hasSummary;

      let statusBadge = hasSummary
        ? '<span style="font-size:0.7rem; background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; padding:2px 6px; border-radius:4px; margin-left:5px;">✨ พร้อมส่งมอบ</span>'
        : isAtExternal
          ? '<span style="font-size:0.7rem; background:#fff7ed; color:#c2410c; border:1px solid #ffedd5; padding:2px 6px; border-radius:4px; margin-left:5px;">🚫 กำลังซ่อม (อยู่ร้านนอก)</span>'
          : '<span style="font-size:0.7rem; background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; padding:2px 6px; border-radius:4px; margin-left:5px;">📍 ยังไม่สรุปงานซ่อม</span>';

      let itemInfo = [...moveHistory]
        .reverse()
        .find((m) => m.name === itNameTrim);
        
      let prevRemark = itemInfo && itemInfo.remark
        ? itemInfo.remark
        : "- สินค้าอยู่ที่หน้างานเดิม -";

      // 🌟 ดึงไฟล์แนบตอนนำของออก มาสร้างเป็นปุ่ม
      let initialFileBtn = "";
      if (itemInfo && itemInfo.file) {
          let fUrl = `uploads/proofs/${itemInfo.file}`;
          initialFileBtn = `
          <div style="margin-top:8px;">
              <button type="button" style="border:1px solid #c7d2fe; cursor:pointer; display:inline-flex; align-items:center; gap:5px; background:#e0e7ff; color:#4338ca; font-size:0.75rem; padding:5px 12px; border-radius:50px; font-weight:700; font-family:'Prompt', sans-serif; transition:all 0.2s;"
                  onmouseover="this.style.background='#c7d2fe';"
                  onmouseout="this.style.background='#e0e7ff';"
                  onclick="let url='${fUrl}'; let ext=url.split('.').pop().toLowerCase(); if(['jpg','jpeg','png','gif','webp','avif','heic'].includes(ext)){ let win=window.open(); win.document.write('<html><body style=&quot;margin:0; background:#111; display:flex; align-items:center; justify-content:center;&quot;><img src=&quot;'+url+'&quot; style=&quot;max-width:100%; max-height:100vh; box-shadow:0 0 50px rgba(0,0,0,0.5);&quot;></body></html>'); win.document.close(); }else{ window.open(url,'_blank'); }">
                  <i class="fas fa-image"></i> ดูรูปตอนนำออก
              </button>
          </div>`;
      }

      itemCheckHtml += `
            <div class="item-card-3d ${isLocked ? "" : "active"}" id="card_wrap_${idx}" style="${isLocked ? "opacity:0.8;" : ""} margin-bottom:10px;">
                <label for="${uniqueId}" class="card-header" ${isLocked ? "" : `onclick="toggleRetDetail(${idx})"`} style="${isLocked ? "cursor:default;" : "cursor:pointer;"}">
                    <input type="checkbox" id="${uniqueId}" class="return-item-chk" value="${itNameTrim}" 
                        data-has-summary="${hasSummary ? "1" : "0"}" ${isLocked ? "disabled" : ""} onchange="toggleRetDetail(${idx})"> 
                    <div class="chk-circle" style="${isLocked ? "border-color:#cbd5e1; background:#f1f5f9;" : ""}"><i class="fas fa-check"></i></div>
                    <span class="item-text" style="${isLocked ? "color:#94a3b8;" : ""}">${itNameTrim} ${statusBadge}</span>
                    <i class="fas fa-chevron-down arrow-icon" id="arrow_${idx}" style="${isLocked ? "display:none;" : ""}"></i>
                </label>
                <div class="card-detail" id="detail_${idx}" style="display:none;">
                    <div style="margin-bottom:10px; background:${hasSummary ? "#ecfdf5" : "#fef2f2"}; padding:10px; border-radius:8px; border:1px solid ${hasSummary ? "#bbf7d0" : "#fecaca"};">
                        <div style="font-size:0.75rem; color:${hasSummary ? "#065f46" : "#991b1b"}; font-weight:800; text-transform:uppercase;">ผลการซ่อม:</div>
                        <div style="font-size:0.85rem;">${hasSummary ? repairSummaries[itNameTrim] : "กรุณาระบุสรุปงานซ่อมก่อน"}</div>
                    </div>
                    
                    <div style="font-size:0.8rem; color:#6b7280; font-weight:600; margin-bottom:2px;"><i class="fas fa-clipboard-list" style="color:#8b5cf6;"></i> หมายเหตุ / อาการเสีย (ตอนนำออก):</div>
                    <div style="background:#f9fafb; padding:10px; border-radius:8px; font-size:0.85rem; border-left:3px solid #ddd6fe;">
                        <div style="color:#334155; line-height:1.5;">${prevRemark}</div>
                        ${initialFileBtn}
                    </div>
                </div>
            </div>`;
    }
  });
  itemCheckHtml += "</div>";

  Swal.fire({
    title: "",
    html: `
        <style>
            .item-grid-wrapper { display: flex; flex-direction: column; text-align: left; }
            .item-card-3d { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; transition: all 0.2s; }
            .item-card-3d.active { border-color: #8b5cf6; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.1); }
            .card-header { display: flex; align-items: center; gap: 12px; padding: 12px 15px; margin:0; }
            .chk-circle { width: 22px; height: 22px; border-radius: 50%; border: 2px solid #d1d5db; display: flex; align-items: center; justify-content: center; color: transparent; flex-shrink: 0; }
            .item-card-3d.active .chk-circle { background: #8b5cf6; border-color: #8b5cf6; color: #fff; }
            .item-card-3d.active[style*="background:#f0fdf4"] .chk-circle { background: #10b981; border-color: #10b981; }
            .item-text { flex-grow: 1; font-weight: 700; color: #374151; font-size: 0.95rem; }
            .arrow-icon { color: #9ca3af; transition: transform 0.3s; }
            .card-detail { padding: 0 15px 15px 49px; background: #fff; }
            .rating-wrapper { display: flex; flex-direction: row-reverse; justify-content: center; gap: 10px; margin: 15px 0; }
            .rating-wrapper input { display: none; }
            .rating-wrapper label { font-size: 2.8rem; color: #e5e7eb; cursor: pointer; transition: 0.2s; }
            .rating-wrapper label:hover, .rating-wrapper label:hover ~ label, .rating-wrapper input:checked ~ label { color: #f59e0b; }
            .input-box-purple { width: 100%; border: 1px solid #ddd6fe; border-radius: 12px; padding: 12px; font-size: 0.95rem; box-sizing: border-box; background: #fff; }
        </style>

        <div style="text-align:center; margin-bottom:20px;">
            <div style="width:60px; height:60px; background:linear-gradient(135deg, #ede9fe, #f5f3ff); color:#8b5cf6; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.2);"><i class="fas fa-shipping-fast fa-2x"></i></div>
            <div style="font-size:1.4rem; font-weight:800; color:#4c1d95;">คืนสินค้า / จบงาน</div>
            <p id="status_hint" style="font-size:0.85rem; color:#7c3aed; font-weight:700; margin-top:5px;"></p>
        </div>

        <div id="return-form-container" style="text-align:left;">
            <label style="font-weight:700; color:#5b21b6; margin-bottom:8px; display:block;">📦 เลือกรอบการคืนสินค้า</label>
            <div style="max-height:35vh; overflow-y:auto; padding-right:5px;">${itemCheckHtml}</div>
            
            <div id="rating_section" style="margin-top:20px; padding-top:20px; border-top:2px dashed #ede9fe;">
                <label style="font-weight:700; color:#5b21b6; display:block; text-align:center;">⭐ ประเมินการซ่อมสำหรับรอบนี้</label>
                <div class="rating-wrapper">
                    <input type="radio" id="star5" name="rt" value="5"><label for="star5">★</label>
                    <input type="radio" id="star4" name="rt" value="4"><label for="star4">★</label>
                    <input type="radio" id="star3" name="rt" value="3"><label for="star3">★</label>
                    <input type="radio" id="star2" name="rt" value="2"><label for="star2">★</label>
                    <input type="radio" id="star1" name="rt" value="1"><label for="star1">★</label>
                </div>
            </div>
            <textarea id="ret_remark" class="input-box-purple" placeholder="ระบุความคิดเห็นเพิ่มเติมจากลูกค้า (ถ้ามี)..." style="height: 70px;"></textarea>
            <div style="margin-top:15px;">
                <label style="font-weight:700; color:#5b21b6; margin-bottom:5px; display:block;">📎 แนบหลักฐานการส่งคืน (ถ้ามี)</label>
                <input type="file" id="ret_file" class="form-control" style="border-radius:10px; font-size:0.85rem;">
            </div>
        </div>`,
    width: "600px",
    showCancelButton: true,
    confirmButtonText: "บันทึกการส่งคืน",
    confirmButtonColor: "#8b5cf6",
    didOpen: () => {
      window.toggleRetDetail = function (idx) {
        const chk = document.getElementById(`ret_itm_${reqId}_${idx}`);
        const card = document.getElementById(`card_wrap_${idx}`);
        const detail = document.getElementById(`detail_${idx}`);
        const arrow = document.getElementById(`arrow_${idx}`);
        if (chk && chk.checked) {
          if (card) card.classList.add("active");
          $(detail).slideDown(200);
          if (arrow) arrow.style.transform = "rotate(180deg)";
        } else {
          if (card) card.classList.remove("active");
          $(detail).slideUp(200);
          if (arrow) arrow.style.transform = "rotate(0deg)";
        }
        document.getElementById("status_hint").innerText =
          `ดำเนินการแล้ว ${document.querySelectorAll(".return-item-chk:checked").length}/${allItems.length} รายการ`;
      };
      allItems.forEach((_, idx) => {
        if (document.getElementById(`ret_itm_${reqId}_${idx}`))
          window.toggleRetDetail(idx);
      });
    },
    preConfirm: () => {
      const items = document.querySelectorAll(
        ".return-item-chk:checked:not(:disabled)",
      );
      const selectable = document.querySelectorAll(
        ".return-item-chk:not(:disabled)",
      );

      if (selectable.length > 0 && items.length === 0)
        return Swal.showValidationMessage(
          "กรุณาเลือกรายการที่จะส่งคืนในรอบนี้",
        );

      let missing = [];
      items.forEach((chk) => {
        if (chk.getAttribute("data-has-summary") !== "1")
          missing.push(chk.value);
      });
      if (missing.length > 0)
        return Swal.showValidationMessage(
          `กรุณาระบุสรุปงานซ่อมก่อนส่งคืน:\n- ${missing.join("\n- ")}`,
        );

      const ratingEl = document.querySelector(`input[name="rt"]:checked`);
      if (!ratingEl && items.length > 0)
        return Swal.showValidationMessage(
          "กรุณาประเมินความพึงพอใจลูกค้าด้วยครับ",
        );

      let totalCheckedNow = document.querySelectorAll(
        ".return-item-chk:checked",
      ).length;
      let isFinal = totalCheckedNow === allItems.length;

      let sel = [];
      items.forEach((c) => sel.push(c.value));
      return {
        items: sel,
        rating: ratingEl ? ratingEl.value : 0,
        remark: document.getElementById("ret_remark").value.trim(),
        file: document.getElementById("ret_file").files[0],
        is_final: isFinal,
      };
    },
  }).then((res) => {
    if (res.isConfirmed) {
      let formData = new FormData();
      formData.append("action", "return_to_customer");
      formData.append("req_id", reqId);
      formData.append("rating", res.value.rating);
      formData.append("return_remark", res.value.remark);
      formData.append("is_final", res.value.is_final ? "1" : "0");
      if (res.value.file) formData.append("return_proof", res.value.file);
      res.value.items.forEach((it, i) =>
        formData.append(`returned_items[${i}]`, it),
      );
      Swal.fire({ title: "กำลังบันทึก...", didOpen: () => Swal.showLoading() });
      $.ajax({
        url: "service_dashboard.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",
        success: (r) => {
              if (r.status === "success") {
                Swal.fire({
                    icon: 'success',
                    title: '<div style="font-family:Prompt; font-weight:800; font-size:1.5rem; color:#10b981;">บันทึกข้อมูลสำเร็จ!</div>',
                    showConfirmButton: false,
                    timer: 1500, // โชว์ติ๊กถูก 1.5 วินาที
                    backdrop: `rgba(0,0,0,0.7)`
                }).then(() => {
                    location.reload();
                });
              } else {
                Swal.fire("Error", r.message, "error");
              }
            },
      });
    }
  });
}
// 13. ฟังก์ชันรับของกลับ (ฉบับเลือกทีละร้าน - ป้องกันบิลซ้ำซ้อน)
function receiveFromShop(reqId, jsonInput) {
  let data = typeof jsonInput === "string" ? JSON.parse(jsonInput) : jsonInput;
  if (typeof data === "string") data = JSON.parse(data);

  let itemsStatus = data.items_status || {};
  let moveHistory = data.items_moved || [];

  // 🟢 1. จัดกลุ่มข้อมูลแยกตามร้านค้าเตรียมไว้
  let shopData = {};
  for (let itemName in itemsStatus) {
    if (itemsStatus[itemName] === "at_external") {
      let history = moveHistory
        .filter((h) => h.name === itemName && h.destination === "external")
        .pop();
      let sInfo =
        history && history.shop_info
          ? history.shop_info
          : { name: "ไม่ระบุร้าน", owner: "-", phone: "-" };

      let sName = sInfo.name;
      if (!shopData[sName]) {
        shopData[sName] = { info: sInfo, items: [] };
      }
      shopData[sName].items.push(itemName);
    }
  }

  // สร้างตัวเลือก Dropdown สำหรับรายชื่อร้าน
  let shopOptions = `<option value="">-- เลือกบริษัท/ร้านค้า ที่จะรับของกลับ --</option>`;
  for (let sName in shopData) {
    shopOptions += `<option value="${sName}">${sName} (${shopData[sName].items.length} รายการ)</option>`;
  }

  Swal.fire({
    title: "",
    html: `
        <style>
            .shop-selector-box { background: linear-gradient(135deg, #db2777, #9d174d); padding: 25px 20px 20px 20px; border-radius: 16px 16px 0 0; margin: -20px -20px 20px -20px; color: #fff; box-shadow: 0 4px 10px rgba(157, 23, 77, 0.2); }
            .modern-select { width: 100%; padding: 12px; border-radius: 10px; border: 2px solid #fbcfe8; font-weight: 700; color: #831843; cursor: pointer; outline: none; font-family: 'Prompt', sans-serif; transition: 0.3s; }
            .modern-select:focus { border-color: #db2777; box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.2); }
            .section-label { font-size: 14px; font-weight: 800; color: #831843; margin-bottom: 10px; display: block; text-align: left; }
            .item-container { background: #fdf2f8; border: 1px solid #fce7f3; border-radius: 12px; padding: 15px; margin-bottom: 20px; text-align: left; max-height: 250px; overflow-y: auto; }
            .modern-textarea { width: 100%; border: 2px solid #fce7f3; border-radius: 12px; padding: 12px; font-size: 14px; margin-bottom: 15px; font-family: 'Prompt', sans-serif; transition: 0.3s; box-sizing: border-box; }
            .modern-textarea:focus { border-color: #db2777; outline: none; box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.1); }
            
            .custom-file-upload { position: relative; width: 100%; border: 2px dashed #f9a8d4; border-radius: 12px; background: #fff; padding: 20px 10px; text-align: center; transition: all 0.3s; cursor: pointer; box-sizing: border-box; margin-bottom: 10px;}
            .custom-file-upload:hover { border-color: #db2777; background: #fdf2f8; }
            .custom-file-upload input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 10; }
            .upload-icon { font-size: 28px; color: #f472b6; margin-bottom: 8px; transition: color 0.3s; }
            .custom-file-upload:hover .upload-icon { color: #db2777; }
            .upload-text { font-size: 13px; color: #9d174d; font-family: 'Prompt', sans-serif; font-weight: 600; transition: color 0.3s; }
            .custom-file-upload:hover .upload-text { color: #831843; }

            ::-webkit-scrollbar { width: 6px; }
            ::-webkit-scrollbar-track { background: transparent; }
            ::-webkit-scrollbar-thumb { background: #fbcfe8; border-radius: 10px; }
            ::-webkit-scrollbar-thumb:hover { background: #f9a8d4; }
        </style>

        <div style="padding:0;">
            <div class="shop-selector-box">
                <div style="font-size: 20px; font-weight: 900; margin-bottom: 15px; text-align: center;"><i class="fas fa-store-alt fa-lg"></i> ดำเนินการรับของจากร้านซ่อม</div>
                <select id="selected_shop_name" class="modern-select" onchange="updateShopItems()">
                    ${shopOptions}
                </select>
                <div id="shop_contact_info" style="margin-top:12px; font-size:12px; display:none; background: rgba(255,255,255,0.2); padding: 8px; border-radius: 8px; text-align: center;">
                    <i class="fas fa-user-tie"></i> ผู้ติดต่อ: <span id="lbl_owner" style="font-weight:700;">-</span> | <i class="fas fa-phone-alt"></i> <span id="lbl_phone" style="font-weight:700;">-</span>
                </div>
            </div>

            <div id="main_form_content" style="opacity: 0.5; pointer-events: none; padding: 0 5px;">
                <label class="section-label"><i class="fas fa-box-open text-pink-600"></i> เลือกสินค้าที่รับกลับ</label>
                <div id="item_list_area" class="item-container" style="display:block;">
                    <div style="text-align:center; color:#f472b6; padding:20px; font-weight:600;"><i class="fas fa-store fa-2x" style="display:block; margin-bottom:10px; opacity:0.5;"></i> กรุณาเลือกร้านค้าก่อน</div>
                </div>

                <div style="margin-top:20px; text-align:left;">
                    <label class="section-label"><i class="fas fa-edit text-pink-600"></i> หมายเหตุการรับของกลับ</label>
                    <textarea id="shop_return_remark" class="modern-textarea" placeholder="ระบุสภาพของที่รับกลับ หรือข้อควรระวัง..." rows="3"></textarea>
                    
                    <label class="section-label"><i class="fas fa-camera text-pink-600"></i> แนบรูปภาพ / เอกสารอ้างอิง <span style="color:#f472b6; font-weight:400; font-size:12px;">(ถ้ามี)</span></label>
                    <div class="custom-file-upload">
                        <input type="file" id="shop_file" accept="image/*,.pdf" onchange="
                            let fileName = this.files[0] ? this.files[0].name : 'คลิกเพื่อเลือกไฟล์ หรือลากมาวาง';
                            let textColor = this.files[0] ? '#db2777' : '#9d174d';
                            let iconColor = this.files[0] ? '#be185d' : '#f472b6';
                            document.getElementById('shop_file_name').innerText = fileName;
                            document.getElementById('shop_file_name').style.color = textColor;
                            document.getElementById('shop_file_icon').style.color = iconColor;
                            document.getElementById('shop_file_icon').className = this.files[0] ? 'fas fa-file-check' : 'fas fa-cloud-upload-alt';
                        ">
                        <div class="upload-icon"><i id="shop_file_icon" class="fas fa-cloud-upload-alt"></i></div>
                        <div class="upload-text" id="shop_file_name">คลิกเพื่อเลือกไฟล์ หรือลากมาวาง</div>
                    </div>
                </div>
            </div>
        </div>
        `,
    width: "550px",
    showCancelButton: true,
    confirmButtonText: '<i class="fas fa-check-circle"></i> ยืนยันรับของกลับ',
    cancelButtonText: "ยกเลิก",
    confirmButtonColor: "#db2777",
    customClass: { confirmButton: "swal2-confirm-btn-pink" },
    didOpen: () => {
      window.updateShopItems = function () {
        let sName = document.getElementById("selected_shop_name").value;
        let area = document.getElementById("item_list_area");
        let form = document.getElementById("main_form_content");
        let contact = document.getElementById("shop_contact_info");

        if (!sName) {
          area.innerHTML = `<div style="text-align:center; color:#f472b6; padding:20px; font-weight:600;"><i class="fas fa-store fa-2x" style="display:block; margin-bottom:10px; opacity:0.5;"></i> กรุณาเลือกร้านค้าก่อน</div>`;
          form.style.opacity = "0.5";
          form.style.pointerEvents = "none";
          contact.style.display = "none";
          return;
        }

        form.style.opacity = "1";
        form.style.pointerEvents = "auto";
        contact.style.display = "block";

        let group = shopData[sName];
        document.getElementById("lbl_owner").innerText = group.info.owner;
        document.getElementById("lbl_phone").innerText = group.info.phone;

        let html = "";
        group.items.forEach((itemName) => {
          html += `
                    <label style="display:flex; align-items:center; gap:10px; padding:12px 15px; border-radius:10px; cursor:pointer; background:#ffffff; margin-bottom:8px; border:1px solid #fbcfe8; transition:0.2s; box-shadow:0 2px 4px rgba(0,0,0,0.02);" onmouseover="this.style.borderColor='#f9a8d4'" onmouseout="this.style.borderColor='#fbcfe8'">
                        <input type="checkbox" class="return-item-chk" value="${itemName}" checked style="width:20px; height:20px; accent-color:#db2777; cursor:pointer;">
                        <span style="font-size:14px; color:#831843; font-weight:700;">${itemName}</span>
                    </label>`;
        });
        area.innerHTML = html;
      };
    },
    preConfirm: () => {
      let shopName = document.getElementById("selected_shop_name").value;
      if (!shopName) return Swal.showValidationMessage("⚠️ กรุณาเลือกร้านค้า");

      let selectedItems = [];
      document
        .querySelectorAll(".return-item-chk:checked")
        .forEach((chk) => selectedItems.push(chk.value));

      if (selectedItems.length === 0)
        return Swal.showValidationMessage(
          "⚠️ กรุณาเลือกสินค้าอย่างน้อย 1 รายการ",
        );

      return {
        req_id: reqId,
        shop_name: shopName,
        selected_items: selectedItems,
        remark: document.getElementById("shop_return_remark").value,
        file: document.getElementById("shop_file").files[0],
      };
    },
  }).then((res) => {
    if (res.isConfirmed) {
      let fd = new FormData();
      fd.append("action", "receive_from_shop");
      fd.append("req_id", res.value.req_id);
      fd.append("shop_name", res.value.shop_name);
      fd.append("return_items", JSON.stringify(res.value.selected_items));
      fd.append("return_remark", res.value.remark);
      if (res.value.file) fd.append("shop_file", res.value.file);

      Swal.fire({
        title: "กำลังบันทึก...",
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
      });

      $.ajax({
        url: "service_dashboard.php",
        type: "POST",
        data: fd,
        processData: false,
        contentType: false,
        dataType: "json",
        success: (resp) => {
          if (resp.status === "success") {
            Swal.fire({
                icon: 'success',
                title: '<div style="font-family:Prompt; font-weight:800; font-size:1.5rem; color:#10b981;">รับของกลับสำเร็จ!</div>',
                showConfirmButton: false,
                timer: 1500, // โชว์ติ๊กถูก 1.5 วินาที
                backdrop: `rgba(0,0,0,0.7)`
            }).then(() => { updateData(); });
          } else {
            Swal.fire("Error", resp.message, "error");
          }
        },
      });
    }
  });
}

// 14. ฟังก์ชันเปิดหน้าต่างอัปเดตความคืบหน้า (ฉบับแยกปุ่ม: บันทึกทั่วไป vs จบงานรายการที่เลือก)
// ==============================================================
// 🔥 1. ระบบจัดการช่องเลือกช่าง (Dynamic Fields + บังคับเลือกจาก List)
// ==============================================================
function getTechOptionsHTML(inputId, dropId) {
  let optionsHtml = '';
  if (typeof allEmployeeList !== 'undefined') {
      allEmployeeList.forEach(emp => {
          optionsHtml += `
          <div class="dropdown-item" 
               onmousedown="
                   let inp = document.getElementById('${inputId}');
                   inp.value='${emp}'; 
                   inp.setAttribute('data-valid', 'true');
                   inp.style.borderColor = '#10b981';
                   inp.style.backgroundColor = '#ecfdf5';
                   document.getElementById('${dropId}').style.display='none';
               " 
               style="padding: 10px 15px; border-bottom: 1px solid #f1f5f9; cursor: pointer; color: #334155; font-size: 0.85rem; transition: background 0.2s;" 
               onmouseover="this.style.background='#f0f9ff'; this.style.color='#0369a1';" 
               onmouseout="this.style.background='transparent'; this.style.color='#334155';">
               <i class="fas fa-user" style="color:#94a3b8; margin-right:5px;"></i> ${emp}
          </div>`;
      });
  }
  return optionsHtml;
}

window.addTechField = function() {
  const container = document.getElementById('dynamic-tech-container');
  if (!container) return;

  const uniqueId = Date.now() + Math.random().toString(36).substring(2, 6);
  const fieldId = 'tech_field_' + uniqueId;
  const inputId = 'tech_input_' + uniqueId;
  const dropId = 'tech_drop_' + uniqueId;
  
  const div = document.createElement('div');
  div.id = fieldId;
  // ปรับให้ช่องและปุ่มลบอยู่ระดับเดียวกันสวยๆ
  div.style.cssText = "display: flex; gap: 10px; margin-bottom: 12px; align-items: stretch; opacity: 0; transform: translateY(-10px); transition: all 0.3s ease;";

  div.innerHTML = `
      <div style="flex-grow: 1; position: relative;">
          <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #3b82f6; font-size: 0.9rem;">
              <i class="fas fa-search"></i>
          </span>
          <input type="text" id="${inputId}" class="tech-multi-input" placeholder="พิมพ์ค้นหาและเลือกชื่อจากรายการ..." autocomplete="off" data-valid="false"
              style="width: 100%; height: 100%; padding: 12px 10px 12px 35px; border-radius: 8px; border: 1px solid #cbd5e1; background: #ffffff; font-family: 'Prompt', sans-serif; font-size: 0.85rem; outline: none; transition: 0.2s; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02); box-sizing:border-box;"
              onfocus="document.getElementById('${dropId}').style.display='block'; this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
              onblur="
                  setTimeout(() => { 
                      let d = document.getElementById('${dropId}'); 
                      if(d) d.style.display='none'; 
                      // 🔥 ระบบเช็คว่าค่าที่พิมพ์ตรงกับ List ไหม ถ้าไม่ตรง ให้ล้างทิ้ง
                      if(this.getAttribute('data-valid') !== 'true') {
                          if(typeof allEmployeeList !== 'undefined' && allEmployeeList.includes(this.value.trim())) {
                              this.setAttribute('data-valid', 'true');
                              this.style.borderColor = '#10b981';
                              this.style.backgroundColor = '#ecfdf5';
                          } else {
                              this.value = ''; 
                              this.setAttribute('data-valid', 'false');
                              this.style.borderColor = '#ef4444';
                              this.style.backgroundColor = '#fef2f2';
                              setTimeout(()=>{ this.style.borderColor = '#cbd5e1'; this.style.backgroundColor = '#ffffff'; }, 1000);
                          }
                      }
                  }, 200); 
                  this.style.boxShadow='none';
              "
              oninput="
                  this.setAttribute('data-valid', 'false'); 
                  this.style.borderColor='#3b82f6'; 
                  this.style.backgroundColor='#ffffff';
                  filterTechDropdown(this, '${dropId}')
              ">
          
          <div id="${dropId}" style="display: none; position: absolute; top: 100%; left: 0; width: 100%; max-height: 160px; overflow-y: auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; z-index: 99999; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); margin-top: 4px;">
              ${getTechOptionsHTML(inputId, dropId)}
          </div>
      </div>
      <button type="button" onclick="removeTechField('${fieldId}')" title="ลบช่องนี้"
          style="flex-shrink: 0; width: 45px; border-radius: 8px; background: #fff1f2; border: 1px solid #fecdd3; color: #e11d48; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; transition: all 0.2s;"
          onmouseover="this.style.background='#ffe4e6'; this.style.transform='scale(1.05)';" 
          onmouseout="this.style.background='#fff1f2'; this.style.transform='scale(1)';">
          <i class="fas fa-trash-alt"></i>
      </button>
  `;
  container.appendChild(div);

  setTimeout(() => { div.style.opacity = "1"; div.style.transform = "translateY(0)"; }, 10);
};

window.filterTechDropdown = function(inputEl, dropId) {
  const val = inputEl.value.toLowerCase();
  const drop = document.getElementById(dropId);
  if (!drop) return;
  const items = drop.querySelectorAll('.dropdown-item');
  let hasMatch = false;
  items.forEach(item => {
      if (item.textContent.toLowerCase().includes(val)) {
          item.style.display = "block";
          hasMatch = true;
      } else {
          item.style.display = "none";
      }
  });
  drop.style.display = hasMatch ? "block" : "none";
};

window.removeTechField = function(id) {
  const el = document.getElementById(id);
  if (el) {
      el.style.opacity = "0";
      el.style.transform = "translateX(20px)";
      setTimeout(() => el.remove(), 300);
  }
};

// ==============================================================
// 🔥 2. ฟังก์ชันเปิด Modal อัปเดตความคืบหน้า 
// ==============================================================
function openUpdateModal(data) {
  console.log("🔍 Data Processing:", data);

  if (typeof data === "string") {
    try { data = JSON.parse(data); } catch (e) {}
  }

  let logs = [];
  try { logs = JSON.parse(data.progress_logs) || []; } catch (e) {}

  let finishedItems = [];
  let itemsStatus = {};
  try {
    let rec = typeof data.received_item_list === "string" ? JSON.parse(data.received_item_list) : data.received_item_list;
    finishedItems = rec.finished_items || [];
    itemsStatus = rec.items_status || {};
  } catch (e) {}

  let finalItemsSet = new Set();
  const extractNameOnly = (val) => {
    if (!val) return;
    let str = typeof val === "string" ? val.trim() : JSON.stringify(val);
    if (str === "" || str === "-" || str === "null") return;
    if (str.startsWith("[") || str.startsWith("{")) {
      try { let p = JSON.parse(str); recursiveFind(p); return; } catch (e) {}
    }
    let parts = str.split(/[\r\n]+/);
    parts.forEach((pt) => {
      let v = pt.trim();
      if (v.includes(":")) v = v.split(":")[0].trim();
      v = v.replace(/^\d+\.\s*/, "");
      if (v.length > 1 && isNaN(v) && !v.includes("undefined")) {
        v = v.replace(/^\[+|\]+$/g, "");
        finalItemsSet.add(v.trim());
      }
    });
  };

  const recursiveFind = (obj) => {
    if (!obj || typeof obj !== "object") return;
    if (Array.isArray(obj)) { obj.forEach((item) => recursiveFind(item)); return; }
    if (obj.product) extractNameOnly(obj.product);
    if (obj.items) recursiveFind(obj.items);
    if (obj.accumulated_moved) recursiveFind(obj.accumulated_moved);
    if (obj.issue) extractNameOnly(obj.issue);
    if (obj.problem) extractNameOnly(obj.problem);
    if (obj.issue_description) extractNameOnly(obj.issue_description);
  };

  recursiveFind(data);
  if (data.received_item_list && typeof data.received_item_list === "string") {
    try { recursiveFind(JSON.parse(data.received_item_list)); } catch (e) {}
  }

  let allItems = Array.from(finalItemsSet);
  const isCompleted = data.status === "completed";

  let reqDate = new Date(data.request_date);
  let dateStr = isNaN(reqDate.getTime()) ? "-" : ("0" + reqDate.getDate()).slice(-2) + "/" + ("0" + (reqDate.getMonth() + 1)).slice(-2) + "/" + reqDate.getFullYear() + " " + ("0" + reqDate.getHours()).slice(-2) + ":" + ("0" + reqDate.getMinutes()).slice(-2);
  logs.unshift({ msg: "รับเรื่องแจ้งซ่อม (เข้าระบบ)", by: data.receiver_by || "System", at: dateStr, is_system: true });

  let logHtml = "";
  logs.forEach((l, index) => {
    let dotClass = index === 0 ? "background:#10b981; border-color:#d1fae5;" : "background:#3b82f6; border-color:#dbeafe;";
    if (index === logs.length - 1 && logs.length > 1) dotClass = "background:#f59e0b; border-color:#fef3c7;";
    logHtml += `
      <div class="timeline-item">
          <div class="timeline-marker" style="${dotClass}"></div>
          <div class="timeline-content">
              <div class="timeline-header">
                  <span class="timeline-user"><i class="fas fa-user-circle"></i> ${l.by}</span>
                  <span class="timeline-time"><i class="far fa-clock"></i> ${l.at}</span>
              </div>
              <div class="timeline-body">${l.msg}</div>
          </div>
      </div>`;
  });

  let contentBody = "";
  if (isCompleted) {
    contentBody = `<div style="background:#ecfdf5; border:1px solid #10b981; border-radius:12px; padding:20px; text-align:center; margin-bottom:20px;"><i class="fas fa-check-circle" style="font-size:3rem; color:#10b981;"></i><h3 style="color:#065f46;">ดำเนินการเสร็จสิ้นแล้ว</h3></div>`;
  } else {
    let itemsSection = "";
    if (allItems.length > 0) {
      let chkList = "";
      allItems.forEach((item) => {
        const isDone = finishedItems.includes(item);
        let currentStat = itemsStatus[item] || "";
        let isAtExternal = currentStat === "at_external";
        let isAtOffice = currentStat.includes("at_office") || currentStat === "back_from_shop";

        let isDisabled = isDone;
        let isRestricted = isAtExternal || isAtOffice;

        let statusBadge = "";
        if (isAtExternal) statusBadge = `<span style="font-size:0.7rem; background:#fff7ed; color:#f97316; border:1px solid #fdba74; padding:1px 6px; border-radius:4px; margin-left:5px;"><i class="fas fa-store"></i> อยู่ร้านนอก</span>`;
        else if (isAtOffice) statusBadge = `<span style="font-size:0.7rem; background:#f0f9ff; color:#0ea5e9; border:1px solid #7dd3fc; padding:1px 6px; border-radius:4px; margin-left:5px;"><i class="fas fa-building"></i> อยู่บริษัท</span>`;

        chkList += `
          <label style="display:flex; align-items:center; gap:10px; cursor:${isDisabled ? "default" : "pointer"}; background:${isDone ? "#f0fdf4" : "#fff"}; padding:10px 12px; border-radius:8px; border:1px solid ${isDone ? "#4ade80" : "#e2e8f0"}; margin-bottom:0; opacity: ${isDisabled ? "0.85" : "1"};">
              <input type="checkbox" class="completed-item-chk" value="${item}" ${isDone ? "checked" : ""} ${isDisabled ? "disabled" : ""} data-restricted="${isRestricted ? "true" : "false"}" style="width:18px; height:18px; accent-color:#10b981; cursor:${isDisabled ? "default" : "pointer"};">
              <div style="flex-grow:1; text-align:left;">
                  <span style="font-size:0.95rem; color:${isDone ? "#166534" : "#334155"}; flex-grow:1; font-weight: ${isDone ? "600" : "400"};">${item}</span>
                  ${statusBadge} 
                  ${isDone ? '<span style="font-size:0.7rem; background:#10b981; color:#fff; padding:2px 8px; border-radius:10px; margin-left:8px; vertical-align:middle;">เสร็จสิ้น</span>' : ""}
              </div>
          </label>`;
      });

      itemsSection = `
        <div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:12px; padding:15px; margin-bottom:15px; text-align:left;">
            <label style="font-size:0.85rem; font-weight:700; color:#64748b; margin-bottom:10px; display:block;">
                <i class="fas fa-tasks" style="color:#f59e0b;"></i> รายการสินค้า (รายการสีเขียวคือจบงานแล้ว)
            </label>
            <div style="display:flex; flex-direction:column; gap:8px; max-height:220px; overflow-y:auto;">${chkList}</div>
        </div>`;
    } else {
      itemsSection = `<div style="text-align:center; color:#94a3b8; padding:15px; border:1px dashed #e2e8f0; border-radius:8px; margin-bottom:15px;">ไม่พบรายการสินค้า</div>`;
    }

    contentBody = `
      ${itemsSection}
      <div style="margin-bottom: 15px; text-align:left;">
          <label style="font-size: 0.9rem; font-weight: 700; color: #1e293b; margin-bottom: 8px; display: block;">
              <i class="fas fa-pen"></i> บันทึกรายละเอียดงาน:
          </label>
          <textarea id="up_msg" class="modern-textarea" placeholder="ระบุสิ่งที่ดำเนินการไป..."></textarea>
      </div>
      
      <div style="background: #f0fdf4; padding: 15px; border-radius: 12px; border: 1px solid #dcfce7; text-align:left;">
          <div style="margin-bottom: 5px; display: flex; align-items: center; gap: 8px;">
              <input type="checkbox" id="chk_tech" style="width: 16px; height: 16px; cursor: pointer; accent-color: #16a34a;">
              <label for="chk_tech" style="font-weight: 700; color: #166534; cursor: pointer; font-size:0.9rem;">ต้องการมอบหมายช่างเพิ่ม</label>
          </div>
          
          <div id="tech_wrapper" style="display:none; width: 100%; border-top: 1px dashed #bbf7d0; padding-top: 15px; margin-top: 10px;">
              <div id="dynamic-tech-container"></div>
              
              <button type="button" onclick="addTechField()" 
                  style="width: 100%; padding: 10px; margin-top: 5px; background: #ffffff; border: 1px dashed #4ade80; color: #16a34a; border-radius: 8px; font-family: 'Prompt', sans-serif; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;"
                  onmouseover="this.style.background='#dcfce7'; this.style.borderColor='#16a34a';" 
                  onmouseout="this.style.background='#ffffff'; this.style.borderColor='#4ade80';">
                  <i class="fas fa-plus-circle"></i> กดเพื่อเพิ่มรายชื่อช่าง
              </button>
          </div>
      </div>
    `;
  }

  Swal.fire({
    title: "",
    html: `
        <style>
            .btn-expand-modal { position: absolute; top: 15px; right: 15px; background: #f1f5f9; border: none; width: 35px; height: 35px; border-radius: 8px; color: #64748b; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; z-index: 9999; }
            .btn-expand-modal:hover { background: #e2e8f0; color: #0f172a; }
            .history-timeline-container { max-height: 60vh; overflow-y: auto; padding: 15px 10px; margin-bottom: 25px; background: #f8fafc; border-radius: 18px; border: 1px solid #e2e8f0; box-shadow: inset 0 2px 6px rgba(0,0,0,0.05); }
            .swal2-popup.is-fullscreen .history-timeline-container { max-height: 75vh; }
        </style>
        
        <button type="button" class="btn-expand-modal" id="btn_toggle_fullscreen" title="ขยาย/ย่อ เต็มจอ"><i class="fas fa-expand"></i></button>

        <div style="padding: 5px;">
            <div class="modal-modern-header">
                <div class="modal-title-text" style="padding-right: 40px;">
                    ${isCompleted ? "สรุปงานซ่อม" : '<i class="fas fa-edit" style="color:#3b82f6;"></i> อัปเดตความคืบหน้า'}
                </div>
            </div>
            
            <div class="history-timeline-container">
                <div class="timeline-list">${logHtml}</div>
            </div>
            ${contentBody}
        </div>
    `,
    width: "700px",
    padding: "0",
    showCancelButton: true,
    cancelButtonText: "ปิด",
    showConfirmButton: !isCompleted,
    confirmButtonText: '<i class="fas fa-save"></i> บันทึกความคืบหน้า',
    confirmButtonColor: "#3b82f6",
    showDenyButton: !isCompleted,
    denyButtonText: '<i class="fas fa-check-circle"></i> เสร็จสิ้นรายการที่เลือก',
    denyButtonColor: "#10b981",

    didOpen: () => {
      const popup = Swal.getPopup();
      const btnExpand = popup.querySelector("#btn_toggle_fullscreen");
      let isFullscreen = false;
      btnExpand.addEventListener("click", () => {
        isFullscreen = !isFullscreen;
        if (isFullscreen) {
          popup.style.width = "95vw"; popup.style.maxWidth = "1400px"; popup.classList.add("is-fullscreen"); btnExpand.innerHTML = '<i class="fas fa-compress"></i>';
        } else {
          popup.style.width = "700px"; popup.style.maxWidth = "100%"; popup.classList.remove("is-fullscreen"); btnExpand.innerHTML = '<i class="fas fa-expand"></i>';
        }
      });

      if (isCompleted) return;
      const container = Swal.getPopup().querySelector(".history-timeline-container");
      if (container) container.scrollTop = container.scrollHeight;

      const chk = Swal.getPopup().querySelector("#chk_tech");
      const wrapper = Swal.getPopup().querySelector("#tech_wrapper");
      const techContainer = Swal.getPopup().querySelector("#dynamic-tech-container");

      if (chk) {
        chk.addEventListener("change", () => {
          if (chk.checked) {
              wrapper.style.display = "block";
              techContainer.innerHTML = ''; 
              addTechField(); 
          } else {
              wrapper.style.display = "none";
              techContainer.innerHTML = ''; 
          }
        });
      }
    },

    preConfirm: () => {
      const msg = Swal.getPopup().querySelector("#up_msg").value.trim();
      const isChecked = Swal.getPopup().querySelector("#chk_tech").checked;
      
      let selectedTechs = [];
      let hasInvalidTech = false;

      if (isChecked) {
          const inputs = Swal.getPopup().querySelectorAll('.tech-multi-input');
          inputs.forEach(input => {
              // 🔥 เช็คว่าพิมพ์ชื่อมั่วๆ ค้างไว้โดยไม่กดเลือกจาก List ไหม
              if (input.value.trim() !== "") {
                  if (input.getAttribute('data-valid') === 'true') {
                      selectedTechs.push(input.value.trim());
                  } else {
                      hasInvalidTech = true;
                  }
              }
          });
      }
      
      if (hasInvalidTech) {
          Swal.showValidationMessage("กรุณาเลือกชื่อช่างจากรายการที่เด้งขึ้นมาเท่านั้นครับ");
          return false;
      }

      const finalTechString = selectedTechs.join(', ');

      let selectedItems = [];
      Swal.getPopup().querySelectorAll(".completed-item-chk:checked:not(:disabled)").forEach((c) => selectedItems.push(c.value));

      if (!msg && !isChecked && selectedItems.length === 0) {
        Swal.showValidationMessage("กรุณากรอกข้อมูลหรือเลือกสินค้า");
        return false;
      }
      if (isChecked && selectedTechs.length === 0) {
        Swal.showValidationMessage("กรุณาเลือกชื่อช่างอย่างน้อย 1 คน");
        return false;
      }

      return { actionType: "update", msg: msg, tech: finalTechString, items: selectedItems };
    },

    preDeny: () => {
      const msg = Swal.getPopup().querySelector("#up_msg").value.trim();
      const isChecked = Swal.getPopup().querySelector("#chk_tech").checked;
      
      let selectedTechs = [];
      let hasInvalidTech = false;

      if (isChecked) {
          const inputs = Swal.getPopup().querySelectorAll('.tech-multi-input');
          inputs.forEach(input => {
              if (input.value.trim() !== "") {
                  if (input.getAttribute('data-valid') === 'true') {
                      selectedTechs.push(input.value.trim());
                  } else {
                      hasInvalidTech = true;
                  }
              }
          });
      }
      
      if (hasInvalidTech) {
          Swal.showValidationMessage("กรุณาเลือกชื่อช่างจากรายการที่เด้งขึ้นมาเท่านั้นครับ");
          return false;
      }

      const finalTechString = selectedTechs.join(', ');

      let selectedItems = [];
      let hasRestrictedItem = false; 

      Swal.getPopup().querySelectorAll(".completed-item-chk:checked:not(:disabled)").forEach((c) => {
        selectedItems.push(c.value);
        if (c.getAttribute("data-restricted") === "true") hasRestrictedItem = true;
      });

      if (selectedItems.length === 0) {
        Swal.showValidationMessage("กรุณาเลือกรายการสินค้าที่ทำเสร็จแล้ว");
        return false;
      }
      if (isChecked && selectedTechs.length === 0) {
        Swal.showValidationMessage("กรุณาเลือกชื่อช่างอย่างน้อย 1 คน");
        return false;
      }
      if (hasRestrictedItem) {
        Swal.showValidationMessage("⚠️ ไม่สามารถกดเสร็จสิ้นรายการที่ติดสถานะ (อยู่ร้านนอก/อยู่บริษัท) ได้");
        return false;
      }

      return { actionType: "finish", msg: msg, tech: finalTechString, items: selectedItems };
    },
  }).then((res) => {
    if (!isCompleted) {
      let dataToSend = res.isConfirmed ? res.value : res.isDenied ? res.value : null;
          
      if (dataToSend) {
        let isFinishAction = (dataToSend.actionType === "finish");
        let confirmTitle = isFinishAction ? "ยืนยันปิดจ๊อบรายการ?" : "ยืนยันบันทึกความคืบหน้า?";
        let confirmText = isFinishAction ? "คุณแน่ใจหรือไม่ที่จะอัปเดตรายการที่เลือกเป็น 'เสร็จสิ้น'? (ระบบจะล็อคสถานะทันที)" : "คุณต้องการบันทึกรายละเอียดความคืบหน้าของงานนี้ใช่หรือไม่?";
        let confirmBtnColor = isFinishAction ? "#10b981" : "#3b82f6";
        let confirmIcon = isFinishAction ? "warning" : "question";

        Swal.fire({
            title: confirmTitle, text: confirmText, icon: confirmIcon,
            showCancelButton: true, confirmButtonColor: confirmBtnColor,
            cancelButtonColor: "#94a3b8", confirmButtonText: "ยืนยันบันทึก", cancelButtonText: "ยกเลิก"
        }).then((confirmRes) => {
            if (confirmRes.isConfirmed) {
                Swal.fire({ title: "กำลังบันทึก...", allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                
                $.post("service_dashboard.php", {
                    action: "update_progress", req_id: data.id, update_msg: dataToSend.msg,
                    technician_name: dataToSend.tech, completed_items: dataToSend.items, action_type: dataToSend.actionType,
                }, function (response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success', title: '<div style="font-family:Prompt; font-weight:800; font-size:1.5rem; color:#10b981;">บันทึกสำเร็จ!</div>',
                            html: '<div style="font-family:Prompt; color:#64748b;">ระบบได้อัปเดตข้อมูลเรียบร้อยแล้ว</div>',
                            showConfirmButton: false, timer: 1500, backdrop: `rgba(0,0,0,0.7)`
                        }).then(() => { updateData(); });
                    } else {
                        Swal.fire("เกิดข้อผิดพลาด", response.message, "error");
                    }
                }, "json");
            }
        });
      }
    }
  });
}
function updateData() {
  const btn = document.querySelector(".btn-search-solid");
  const originalText = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> โหลด...';
  btn.disabled = true;

  const formData = new FormData(document.getElementById("filterForm"));
  const params = new URLSearchParams(formData);

  fetch(`service_dashboard.php?${params.toString()}`)
    .then((response) => response.text())
    .then((html) => {
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, "text/html");

      // 1. อัปเดต Grid 4 เสา (ยอดตัวเลข)
      const newGrid = doc.getElementById("dashboard-grid");
      if (newGrid) document.getElementById("dashboard-grid").innerHTML = newGrid.innerHTML;

      // 2. อัปเดตยอดค่าใช้จ่าย (Approved / Pending)
      const newCost = doc.getElementById("cost-summary-section");
      if (newCost) document.getElementById("cost-summary-section").innerHTML = newCost.innerHTML;

      // 🔥 3. อัปเดตส่วนความพึงพอใจ (Satisfaction) - จุดที่เพิ่มเข้ามา
      const newSatisfaction = doc.getElementById("satisfaction-section");
      if (newSatisfaction) {
        document.getElementById("satisfaction-section").innerHTML = newSatisfaction.innerHTML;
      }

      // 🔥 [เพิ่มบรรทัดนี้] อัปเดตก้อนความพึงพอใจ
      const newSatis = doc.getElementById("satisfaction-section");
      if (newSatis) {
        document.getElementById("satisfaction-section").innerHTML = newSatis.innerHTML;
      }

      // 4. อัปเดตตารางรายการ
      const newTable = doc.getElementById("data-table");
      if (newTable) document.getElementById("data-table").innerHTML = newTable.innerHTML;

      window.history.pushState({}, "", `service_dashboard.php?${params.toString()}`);
      if (typeof updateSLACountdown === "function") updateSLACountdown();
    })
    .catch((err) => console.error("Error loading data:", err))
    .finally(() => {
      btn.innerHTML = originalText;
      btn.disabled = false;
    });
}
function filterSLA(type) {
  // 1. ใส่ค่าลงใน Hidden Input
  document.getElementById("sla_input").value = type;

  // 2. เรียกโหลดข้อมูลใหม่ (AJAX)
  updateData();
}
// ฟังก์ชันสำหรับเปิด Popup อนุมัติค่าใช้จ่าย

function showRatingHistory() {
  Swal.fire({
    title:
      '<div style="color:#7c3aed; font-weight:800;"><i class="fas fa-star"></i> เสียงตอบรับจากลูกค้า</div>',
    html: '<div id="rating-content" style="padding:20px;"><i class="fas fa-circle-notch fa-spin fa-2x" style="color:#7c3aed;"></i></div>',
    width: "600px",
    showConfirmButton: false,
    showCloseButton: true,
    customClass: { popup: "rounded-24" },
    didOpen: () => {
      $.ajax({
        url: "service_dashboard.php",
        type: "POST",
        data: { action: "get_rating_history" },
        dataType: "json",
        success: function (res) {
          console.log("Data from server:", res); // 🔥 เช็คใน Console (F12) ว่าข้อมูลมาไหม
          let html =
            '<div style="text-align:left; max-height:450px; overflow-y:auto; padding-right:10px;">';

          if (Array.isArray(res) && res.length > 0) {
            res.forEach((r) => {
              let stars = "";
              for (let i = 1; i <= 5; i++) {
                stars += `<i class="fas fa-star" style="color:${i <= r.rating ? "#f59e0b" : "#e5e7eb"}; font-size:0.85rem; margin-right:2px;"></i>`;
              }
              html += `
                            <div style="background:#fff; border:1px solid #f1f5f9; border-radius:16px; padding:15px; margin-bottom:12px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.02);">
                                <div style="display:flex; justify-content:space-between;">
                                    <div>
                                        <div style="font-weight:800; color:#1e293b;">${r.site_id}</div>
                                        <div style="font-size:0.85rem; color:#64748b; margin-bottom:5px;">${r.project_name}</div>
                                        <div>${stars}</div>
                                    </div>
                                    <div style="font-size:0.75rem; color:#94a3b8;">${r.at}</div>
                                </div>
                                <div style="background:#f8fafc; padding:10px; border-radius:10px; color:#475569; font-size:0.9rem; margin-top:10px; border-left:4px solid #ddd6fe;">
                                    "${r.comment}"
                                </div>
                            </div>`;
            });
          } else {
            html +=
              '<div style="text-align:center; padding:30px; color:#94a3b8;">ไม่พบประวัติการประเมินในขณะนี้</div>';
          }
          html += "</div>";
          $("#rating-content").html(html);
        },
        error: function (xhr) {
          console.error("AJAX Error:", xhr.responseText); // 🔥 ถ้า Error จะโชว์ตรงนี้ว่าติดอะไร
          $("#rating-content").html(
            '<div style="color:red; text-align:center;">เกิดข้อผิดพลาดในการดึงข้อมูล</div>',
          );
        },
      });
    },
  });
}

// ฟังก์ชันดูรายละเอียดค่าใช้จ่าย (ที่อนุมัติแล้ว) + ดูไฟล์แนบ
function viewApprovedCost(detailText, fileName) {
  // แปลง \n เป็น <br> เพื่อแสดงผลสวยงาม
  let formattedDetail = detailText
    ? detailText.replace(/\n/g, "<br>")
    : "- ไม่ระบุรายละเอียด -";

  // สร้างปุ่มดูไฟล์ (ถ้ามีไฟล์)
  let fileBtn = "";
  if (fileName && fileName !== "") {
    fileBtn = `
            <div style="margin-top: 15px; border-top: 1px dashed #bbf7d0; padding-top: 10px;">
                <a href="uploads/repairs/${fileName}" target="_blank" 
                   style="display: flex; align-items: center; justify-content: center; gap: 8px; background: #fff; color: #059669; border: 1px solid #a7f3d0; padding: 8px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 600; transition:0.2s;">
                    <i class="fas fa-image"></i> ดูหลักฐานใบเสร็จ
                </a>
            </div>`;
  }

  Swal.fire({
    title: "",
    html: `
            <div style="padding:5px;">
                <div class="modal-modern-header">
                    <div class="modal-title-text" style="color:#059669;">
                        <i class="fas fa-file-invoice-dollar"></i> รายละเอียดที่อนุมัติแล้ว
                    </div>
                </div>
                <div style="text-align:left; background:#f0fdf4; border:1px solid #bbf7d0; padding:15px; border-radius:12px; margin-top:10px; font-size:0.9rem; color:#334155; line-height:1.6;">
                    ${formattedDetail}
                    ${fileBtn}
                </div>
            </div>
        `,
    width: "450px",
    confirmButtonText: "ปิดหน้าต่าง",
    confirmButtonColor: "#64748b",
    customClass: { popup: "rounded-20" },
  });
}

// แก้ไขฟังก์ชันคำนวณเดิมให้เป็น Global ด้วย
window.calculateTotalExpense = function () {
  let total = 0;
  document.querySelectorAll(".expense-row").forEach((row) => {
    let qty = parseFloat(row.querySelector(".exp-qty").value) || 0;
    let price = parseFloat(row.querySelector(".exp-price").value) || 0;
    total += qty * price;
  });

  const display = document.getElementById("total-display");
  const hiddenInp = document.getElementById("final_total_cost");
  if (display)
    display.innerText = total.toLocaleString("en-US", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  if (hiddenInp) hiddenInp.value = total;
};
// 🔥 ฟังก์ชันสรุปงานซ่อม (Version: ล็อคไม่ให้ติ๊กถ้าของอยู่ร้านนอก)
function openRepairSummaryModal(reqId) {
  Swal.fire({
    title: "กำลังโหลดข้อมูล...",
    allowOutsideClick: false,
    didOpen: () => Swal.showLoading(),
  });

  $.post(
    "service_dashboard.php",
    { action: "get_latest_item_data", req_id: reqId },
    function (res) {
      Swal.close();

      let data = typeof res === "string" ? JSON.parse(res) : res;
      let itemsStatus = data.items_status || {};
      let currentSummaries = data.item_repair_summaries || {};

      // 1. ดึงข้อมูลรายการที่ "จบงานแล้ว" (คืนแล้ว หรือ เสร็จสิ้นหน้างาน)
      let alreadyReturned =
        data.details &&
        data.details.customer_return &&
        data.details.customer_return.items_returned
          ? data.details.customer_return.items_returned
          : [];
      let finishedItems = data.finished_items || [];

      // 2. รวมรายชื่อสินค้า
      let allItems = data.items || [];
      if (data.items_moved && data.items_moved.length > 0) {
        let movedNames = data.items_moved.map((m) => m.name);
        allItems = [...new Set([...allItems, ...movedNames])];
      }

      // 3. กรองเฉพาะของที่นำออกมาแล้ว และ "ยังไม่ได้จบงาน"
      let filteredItems = allItems.filter((item) => {
        let status = itemsStatus[item] || "";
        let itTrim = item.trim();
        let isNotClosed =
          !alreadyReturned.includes(itTrim) && !finishedItems.includes(itTrim);
        return status !== "" && status !== "pending" && isNotClosed;
      });

      if (filteredItems.length === 0) {
        return Swal.fire({
          icon: "success",
          title: "ดำเนินการครบถ้วนแล้ว",
          text: "ทุกรายการได้รับการสรุปงาน หรือส่งมอบเสร็จสิ้นแล้วครับ",
          confirmButtonColor: "#3b82f6",
          confirmButtonText: "ตกลง",
        });
      }

      // 4. สร้าง HTML
      let html = `
        <style>
            @keyframes slideUpFade { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
            .repair-modal-container { text-align: left; max-height: 65vh; overflow-y: auto; padding: 5px 10px; }
            .repair-modal-container::-webkit-scrollbar { width: 6px; }
            .repair-modal-container::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
            .repair-modal-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

            .repair-card {
                background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 12px;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden;
                opacity: 0; animation: slideUpFade 0.4s ease forwards;
            }
            .repair-card.active {
                border-color: #3b82f6; background: #eff6ff; box-shadow: 0 4px 12px -2px rgba(59, 130, 246, 0.15);
            }

            .repair-header { padding: 12px 15px; display: flex; align-items: center; gap: 12px; cursor: pointer; user-select: none; }
            
            .chk-modern-wrapper { position: relative; width: 24px; height: 24px; flex-shrink: 0; }
            .chk-modern { opacity: 0; width: 0; height: 0; position: absolute; }
            .checkmark {
                position: absolute; top: 0; left: 0; height: 24px; width: 24px;
                background-color: #fff; border: 2px solid #cbd5e1; border-radius: 6px;
                transition: 0.2s; display: flex; align-items: center; justify-content: center; color: transparent;
            }
            .chk-modern:checked ~ .checkmark { background-color: #3b82f6; border-color: #3b82f6; color: #fff; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3); }

            .item-info { flex-grow: 1; display: flex; flex-direction: column; }
            .item-name { font-weight: 700; color: #1e293b; font-size: 0.95rem; }
            
            .status-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 12px; font-weight: 600; display: inline-block; width: fit-content; margin-top: 2px; }
            .badge-shop { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
            .badge-back { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
            .badge-office { background: #f0f9ff; color: #0369a1; border: 1px solid #e0f2fe; }
            .badge-saved { background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; margin-left: 5px; }

            .repair-body { display: none; padding: 0 15px 15px 15px; border-top: 1px dashed #bfdbfe; margin-top: -5px; }
            .input-label { font-size: 0.75rem; color: #3b82f6; font-weight: 700; margin: 10px 0 5px 0; }
            .repair-textarea {
                width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px;
                font-size: 0.9rem; background: #fff; min-height: 70px; resize: vertical; transition: 0.2s;
            }
            .repair-textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        </style>

        <div class="repair-modal-container">
            <div style="font-size:0.9rem; color:#64748b; margin-bottom:15px; padding-left:5px;">
                <i class="fas fa-magic"></i> เลือกรายการสินค้าเพื่อระบุ/แก้ไขผลการซ่อม
            </div>`;

      filteredItems.forEach((item, idx) => {
        let val = currentSummaries[item] || "";
        let status = itemsStatus[item] || "";
        
        // 🔥 เช็คว่าสถานะคือส่งร้านนอกหรือไม่
        let isAtExternal = status === "at_external";
        
        let hasData = val.trim() !== "";

        // Logic เลือก Badge สถานะ
        let badgeHtml = "";
        if (isAtExternal)
          badgeHtml = `<span class="status-badge badge-shop"><i class="fas fa-tools"></i> อยู่ร้านซ่อม</span>`;
        else if (status === "back_from_shop")
          badgeHtml = `<span class="status-badge badge-back"><i class="fas fa-undo"></i> กลับจากร้าน</span>`;
        else
          badgeHtml = `<span class="status-badge badge-office"><i class="fas fa-building"></i> อยู่ที่บริษัท</span>`;

        if (hasData) {
          badgeHtml += `<span class="status-badge badge-saved"><i class="fas fa-check"></i> บันทึกแล้ว</span>`;
        }

        // 🔥 สร้างตัวแปรล็อคการกด ถ้าอยู่ร้านนอกให้ Disabled
        let disabledAttr = isAtExternal ? "disabled" : "";
        let cursorStyle = isAtExternal ? "cursor: not-allowed; opacity: 0.65;" : "cursor: pointer;";
        let isChecked = (hasData && !isAtExternal) ? "checked" : "";
        let activeClass = (hasData && !isAtExternal) ? "active" : "";

        let delay = idx * 0.05;

        html += `
            <div class="repair-card ${activeClass}" id="card_${idx}" style="animation-delay: ${delay}s; ${isAtExternal ? 'background:#f8fafc; border-color:#e2e8f0;' : ''}">
                <label class="repair-header" style="${cursorStyle}">
                    <div class="chk-modern-wrapper">
                        <input type="checkbox" class="chk-modern" id="chk_${idx}" value="${idx}" 
                            onchange="toggleRepairInput(${idx})" ${isChecked} ${disabledAttr}>
                        <div class="checkmark" style="${isAtExternal ? 'background:#e2e8f0; border-color:#cbd5e1;' : ''}"><i class="fas fa-check fa-xs"></i></div>
                    </div>
                    <div class="item-info">
                        <span class="item-name" style="${isAtExternal ? 'color:#94a3b8; text-decoration:line-through;' : ''}">${idx + 1}. ${item}</span>
                        <div>${badgeHtml}</div>
                        ${isAtExternal ? `<div style="font-size:0.75rem; color:#ef4444; margin-top:6px; font-weight:600;"><i class="fas fa-lock"></i> ไม่สามารถสรุปได้ (ของไม่อยู่ที่บริษัท)</div>` : ''}
                    </div>
                </label>
                
                <div class="repair-body" id="body_${idx}" style="${isChecked ? "display: block;" : ""}">
                    <div class="input-label"><i class="fas fa-pen"></i> รายละเอียดผลการซ่อม:</div>
                    <textarea id="text_${idx}" class="repair-textarea" placeholder="เช่น เปลี่ยนอะไหล่เรียบร้อย, ทำความสะอาดแล้ว...">${val}</textarea>
                    <input type="hidden" id="name_${idx}" value="${item}">
                </div>
            </div>`;
      });

      html += `</div>`;

      Swal.fire({
        title:
          '<div style="font-family:Prompt; font-weight:800; color:#1e293b;">สรุปผลการซ่อมรายชิ้น</div>',
        html: html,
        width: "550px",
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save"></i> บันทึกข้อมูล',
        confirmButtonColor: "#3b82f6",
        cancelButtonText: "ปิด",
        didOpen: () => {
          window.toggleRepairInput = (idx) => {
            const isChecked = document.getElementById(`chk_${idx}`).checked;
            const card = $(`#card_${idx}`);
            const body = $(`#body_${idx}`);

            if (isChecked) {
              card.addClass("active");
              body.slideDown(200);
              setTimeout(
                () => document.getElementById(`text_${idx}`).focus(),
                250,
              );
            } else {
              card.removeClass("active");
              body.slideUp(200);
            }
          };
        },
        preConfirm: () => {
          let result = {};
          let count = 0;
          filteredItems.forEach((_, idx) => {
            const chk = document.getElementById(`chk_${idx}`);
            // บันทึกเฉพาะรายการที่ติ๊กอยู่ (ที่ Disabled จะไม่ถูกติ๊กแน่นอน)
            if (chk && chk.checked) {
              let name = document.getElementById(`name_${idx}`).value;
              let text = document.getElementById(`text_${idx}`).value.trim();
              result[name] = text;
              count++;
            }
          });

          if (count === 0) {
            Swal.showValidationMessage(
              "กรุณาติ๊กเลือกรายการอย่างน้อย 1 รายการ",
            );
            return false;
          }
          return result;
        },
      }).then((res) => {
        if (res.isConfirmed) {
          Swal.fire({
            title: "กำลังบันทึก...",
            didOpen: () => Swal.showLoading(),
          });

          $.post(
            "service_dashboard.php",
            {
              action: "save_repair_summary",
              req_id: reqId,
              summaries: JSON.stringify(res.value),
            },
            function (response) {
              if (response.status === "success") {
                Swal.fire({
                  icon: "success",
                  title: "บันทึกเรียบร้อย!",
                  showConfirmButton: false,
                  timer: 1500,
                }).then(() => location.reload());
              } else {
                Swal.fire(
                  "บันทึกไม่สำเร็จ",
                  response.message || "Error",
                  "error",
                );
              }
            },
            "json",
          ).fail(function (xhr) {
            Swal.fire("Error", "เกิดข้อผิดพลาดในการเชื่อมต่อ", "error");
          });
        }
      });
    },
    "json",
  );
}

// 🔥 ฟังก์ชันพิจารณาค่าใช้จ่าย (โชว์ข้อมูลผู้ทำรายการ + เวลาที่เบิก)
function approveCost(reqId) {
  Swal.fire({
    title: "กำลังโหลดข้อมูล...",
    allowOutsideClick: false,
    didOpen: () => Swal.showLoading(),
  });

  $.post(
    "service_dashboard.php",
    { action: "get_latest_item_data", req_id: reqId },
    function (res) {
      let officeLogs =
        res.details && res.details.office_log ? res.details.office_log : [];

      // 🔥 ดึงข้อมูล "เบิกค่าใช้จ่าย" และ "รับของกลับจากร้าน"
      let pendingList = officeLogs
        .map((log, index) => {
          return { ...log, originalIndex: index };
        })
        .filter(
          (log) =>
            (log.status === "expense_request" ||
              log.status === "back_from_shop") &&
            (log.approved === undefined || log.approved === false),
        );

      if (pendingList.length === 0) {
        Swal.fire({
          icon: "info",
          title: "ไม่มีรายการค้าง",
          text: "ไม่พบรายการรออนุมัติ หรือรายการทั้งหมดถูกจัดการไปแล้ว",
        }).then(() => updateData());
        return;
      }

      let htmlContent = `<div style="text-align:left; max-height:55vh; overflow-y:auto; padding:5px 10px;" id="approval_list_container">`;

      pendingList.forEach((item, i) => {
        let isExpense = item.status === "expense_request";

        let title = isExpense ? "ใบเบิกค่าใช้จ่าย" : "รายการจากร้านซ่อม";
        let icon = isExpense
          ? '<i class="fas fa-file-invoice-dollar text-purple-600"></i>'
          : '<i class="fas fa-tools text-orange-500"></i>';
        let borderColor = isExpense ? "#8b5cf6" : "#f59e0b";
        let bgHeader = isExpense
          ? "linear-gradient(135deg, #f5f3ff, #ede9fe)"
          : "linear-gradient(135deg, #fffbeb, #fef3c7)";
        let textColor = isExpense ? "#6d28d9" : "#d97706";

        let totalCost = parseFloat(item.total_cost || 0).toLocaleString(
          "en-US",
          { minimumFractionDigits: 2 },
        );

        // 🌟 [เพิ่มใหม่] ข้อมูลผู้ขอเบิก และ เวลาที่ขอเบิก
        let requesterInfo = `
            <div style="font-size: 11px; color: #64748b; margin-top: 8px; margin-bottom: 6px; display: flex; flex-wrap: wrap; gap: 6px;">
                <span style="background: rgba(255,255,255,0.6); padding: 4px 8px; border-radius: 6px; border: 1px solid rgba(0,0,0,0.05); display: inline-flex; align-items: center; gap: 4px;">
                    <i class="fas fa-user-edit" style="color:${borderColor};"></i> ผู้ทำรายการ: <b style="color:#334155;">${item.by || 'ไม่ระบุ'}</b>
                </span>
                <span style="background: rgba(255,255,255,0.6); padding: 4px 8px; border-radius: 6px; border: 1px solid rgba(0,0,0,0.05); display: inline-flex; align-items: center; gap: 4px;">
                    <i class="far fa-clock" style="color:${borderColor};"></i> เวลา: <b style="color:#334155;">${item.at || '-'}</b>
                </span>
            </div>`;

        // 🌟 จัดการ "อ้างอิงรายการสินค้า"
        let refText = "";
        if (isExpense && item.ref_item) {
          let refs = [];
          try {
            if (
              typeof item.ref_item === "string" &&
              item.ref_item.startsWith("[")
            ) {
              refs = JSON.parse(item.ref_item);
            } else if (Array.isArray(item.ref_item)) {
              refs = item.ref_item;
            } else {
              refs = item.ref_item
                .split(",")
                .map((r) => r.trim())
                .filter((r) => r !== "");
            }
          } catch (e) {
            refs = [item.ref_item];
          }

          if (refs.length > 0) {
            let tagsHtml = refs
              .map(
                (r) =>
                  `<span style="background: #ffffff; color: #4f46e5; border: 1px solid #c7d2fe; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);"><i class="fas fa-cube" style="color:#8b5cf6; font-size:10px;"></i> ${r}</span>`,
              )
              .join("");
            refText = `
                        <div style="margin-top: 10px; width: 100%;">
                            <div style="font-size: 11px; color: #64748b; font-weight: 800; margin-bottom: 6px;"><i class="fas fa-tags" style="color:#8b5cf6;"></i> อ้างอิงรายการสินค้า:</div>
                            <div style="display: flex; flex-wrap: wrap; gap: 6px;">${tagsHtml}</div>
                        </div>`;
          }
        } else if (item.shop) {
          refText = `<div style="margin-top:6px; font-size:12px; color:${textColor}; font-weight:700;"><i class="fas fa-store"></i> ร้าน: ${item.shop}</div>`;
        }

        // 🟢 วาดรายการย่อย (ตารางชิดซ้ายเรียบร้อย)
        let expRows = "";
        if (item.expenses && item.expenses.length > 0) {
          expRows += `
                <table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 5px;">
                    <thead>
                        <tr style="color: #64748b; border-bottom: 2px solid #e2e8f0; font-size: 11px;">
                            <th style="padding: 6px; text-align: left; font-weight: 800;">รายการ</th>
                            <th style="padding: 6px; text-align: center; font-weight: 800; width: 60px;">จำนวน</th>
                            <th style="padding: 6px; text-align: right; font-weight: 800; width: 80px;">ราคา</th>
                            <th style="padding: 6px; text-align: right; font-weight: 800; width: 90px;">รวม (฿)</th>
                        </tr>
                    </thead>
                    <tbody>
                `;

          item.expenses.forEach((ex) => {
            let qty = ex.qty || 0;
            let price = parseFloat(ex.price || 0).toLocaleString("en-US", {
              minimumFractionDigits: 2,
            });
            let lineTotal = parseFloat(ex.total || 0).toLocaleString("en-US", {
              minimumFractionDigits: 2,
            });

            expRows += `
                        <tr style="border-bottom: 1px dashed #e2e8f0; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 8px 6px; text-align: left; color: #334155; font-weight: 500;">${ex.name}</td>
                            <td style="padding: 8px 6px; text-align: center; color: #64748b;">
                                <span style="background: #ffffff; border: 1px solid #cbd5e1; padding: 2px 6px; border-radius: 6px; font-size: 11px;">x${qty}</span>
                            </td>
                            <td style="padding: 8px 6px; text-align: right; color: #64748b;">${price}</td>
                            <td style="padding: 8px 6px; text-align: right; font-weight: 800; color: #0f172a;">${lineTotal}</td>
                        </tr>
                    `;
          });
          expRows += `</tbody></table>`;
        }

        let fileBtn = "";
        if (item.file) {
          let dir = isExpense ? "expenses" : "repairs";
          let fileUrl = `uploads/${dir}/${item.file}`;
          
          fileBtn = `
            <div style='margin-bottom:15px;'>
                <button type='button' class='btn-mini-3d outline' onclick=\"
                    let url = '${fileUrl}';
                    let ext = url.split('.').pop().toLowerCase();
                    if(['jpg','jpeg','png','gif','webp','avif','heic'].includes(ext)) {
                        let win = window.open();
                        win.document.write('<html><head><title>Preview</title></head><body style=&quot;margin:0; background:#111; display:flex; align-items:center; justify-content:center;&quot;><img src=&quot;' + url + '&quot; style=&quot;max-width:100%; max-height:100vh; box-shadow:0 0 50px rgba(0,0,0,0.5);&quot;></body></html>');
                        win.document.close();
                    } else {
                        window.open(url, '_blank');
                    }
                \">
                    <i class='fas fa-paperclip text-blue-500'></i> ดูไฟล์แนบ / ใบเสนอราคา
                </button>
            </div>`;
        }

        htmlContent += `
            <div class="approval-card-3d log-anim" data-idx="${item.originalIndex}" data-amount="${item.total_cost}" data-status="${item.status}" style="animation-delay: ${i * 0.1}s; border-left: 5px solid ${borderColor};">
                
                <div style="background:${bgHeader}; padding:15px; display:flex; justify-content:space-between; align-items:flex-start; border-bottom:1px solid #e2e8f0;">
                    <div style="flex:1; padding-right: 15px;">
                        <div style="font-weight:900; color:#1e293b; font-size:16px;">${icon} ${title}</div>
                        ${requesterInfo} ${refText}
                    </div>
                    <div style="text-align:right; flex-shrink: 0;">
                        <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">ยอดรวมสุทธิ</div>
                        <div style="font-weight:900; color:${borderColor}; font-size:22px; letter-spacing:-0.5px;">฿${totalCost}</div>
                    </div>
                </div>

                <div style="padding:15px;">
                    ${fileBtn}
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px; margin-bottom:15px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                        <div style="font-size:12px; font-weight:900; color:#334155; text-transform:uppercase; margin-bottom:5px;"><i class="fas fa-list-ul" style="color:#8b5cf6;"></i> รายละเอียดการเบิก</div>
                        ${expRows}
                    </div>
                    
                    ${item.remark ? `<div style="font-size:13px; color:#b45309; background:#fffbeb; padding:12px; border-radius:10px; margin-bottom:15px; border:1px dashed #fcd34d;"><i class="fas fa-comment-dots fa-lg" style="color:#f59e0b; margin-right:5px;"></i> <b>หมายเหตุ:</b> ${item.remark}</div>` : ""}
                    
                    <div class="decision-group" style="display:flex; gap:10px; margin-top:15px;">
                        <label class="decision-btn approve-btn">
                            <input type="radio" class="rb-approve" name="decision_${item.originalIndex}" value="approved"> 
                            <div class="btn-content"><i class="fas fa-check-circle fa-lg"></i> อนุมัติ</div>
                        </label>
                        <label class="decision-btn reject-btn">
                            <input type="radio" class="rb-reject" name="decision_${item.originalIndex}" value="rejected"> 
                            <div class="btn-content"><i class="fas fa-times-circle fa-lg"></i> ไม่อนุมัติ</div>
                        </label>
                    </div>
                    
                    <div class="reject-reason-box" style="display:none; margin-top:15px;">
                        <label style="font-size:12px; font-weight:800; color:#ef4444; margin-bottom:5px; display:block;"><i class="fas fa-exclamation-triangle"></i> เหตุผลที่ไม่อนุมัติ <span style="color:#ef4444;">*</span></label>
                        <input type="text" class="reject-reason-input swal-input-premium" placeholder="ระบุเหตุผล...">
                    </div>
                </div>
            </div>
            `;
      });
      htmlContent += `</div>`;

      Swal.fire({
        title: "",
        html: `
            <style>
                @keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
                .log-anim { animation: fadeInUp 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards; opacity: 0; }
                
                .approval-card-3d { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; margin-bottom: 20px; transition: all 0.3s; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; }
                .approval-card-3d:hover { box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.15); transform: translateY(-2px); }

                .decision-btn { flex: 1; cursor: pointer; position: relative; }
                .decision-btn input { display: none; } 
                .decision-btn .btn-content { padding: 12px; border-radius: 12px; text-align: center; font-weight: 800; font-size: 14px; transition: all 0.2s; border: 2px solid transparent; display:flex; justify-content:center; align-items:center; gap:8px; }
                
                .approve-btn .btn-content, .reject-btn .btn-content { background: #f8fafc; color: #64748b; border-color: #e2e8f0; }
                .approve-btn input:checked + .btn-content { background: #f0fdf4; color: #166534; border-color: #4ade80; box-shadow: 0 4px 10px rgba(74, 222, 128, 0.2); }
                .reject-btn input:checked + .btn-content { background: #fef2f2; color: #991b1b; border-color: #f87171; box-shadow: 0 4px 10px rgba(248, 113, 113, 0.2); }

                .swal-input-premium { width: 100%; border: 2px solid #fca5a5; border-radius: 10px; padding: 12px; font-size: 14px; box-sizing: border-box; transition: all 0.3s; background: #fff; font-family: 'Prompt', sans-serif; color: #1e293b; }
                .swal-input-premium:focus { border-color: #ef4444; outline: none; box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.15); }

                .btn-mini-3d { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 50px; font-size: 12px; text-decoration: none !important; font-weight: 700; transition: all 0.2s; border: none; box-sizing: border-box; cursor: pointer; }
                .btn-mini-3d.outline { background: linear-gradient(to bottom, #ffffff, #f1f5f9); color: #475569; border: 1px solid #cbd5e1; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
                .btn-mini-3d.outline:hover { background: #f8fafc; color: #0f172a; border-color: #94a3b8; transform: translateY(-1px); }

                ::-webkit-scrollbar { width: 6px; }
                ::-webkit-scrollbar-track { background: transparent; }
                ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
            </style>

            <div style="padding:0;">
                <div style="text-align:center; margin-bottom:15px; animation: fadeInUp 0.4s ease;">
                    <div style="width:65px; height:65px; background:linear-gradient(135deg, #3b82f6, #1d4ed8); color:#fff; border-radius:20px; display:flex; align-items:center; justify-content:center; margin:0 auto 15px; box-shadow:0 10px 25px -5px rgba(59, 130, 246, 0.5);">
                        <i class="fas fa-tasks fa-2x"></i>
                    </div>
                    <div style="font-size:24px; font-weight:900; color:#0f172a; letter-spacing:-0.5px;">พิจารณาอนุมัติการเบิกจ่าย</div>
                    <div style="font-size:13px; color:#64748b; margin-top:5px;">เลือกพิจารณาเฉพาะรายการที่ต้องการ<br>(รายการที่ไม่ได้เลือก จะถูกยกยอดไปรอบหน้า)</div>
                </div>
                ${htmlContent}
            </div>
            `,
        width: "600px",
        showCancelButton: true,
        confirmButtonText:
          '<i class="fas fa-save" style="margin-right:5px;"></i> บันทึกรายการที่เลือก',
        cancelButtonText: "ยกเลิก",
        confirmButtonColor: "#3b82f6",
        customClass: {
          confirmButton: "swal2-confirm-btn-blue",
          cancelButton: "swal2-cancel-btn-gray",
        },
        didOpen: () => {
          // Event: สลับแสดงกล่องเหตุผลเมื่อกดไม่อนุมัติ
          const cards = document.querySelectorAll(".approval-card-3d");
          cards.forEach((card) => {
            const radios = card.querySelectorAll('input[type="radio"]');
            const reasonBox = card.querySelector(".reject-reason-box");
            const reasonInput = card.querySelector(".reject-reason-input");
            radios.forEach((r) => {
              r.addEventListener("change", function () {
                if (card.querySelector(".rb-reject").checked) {
                  reasonBox.style.display = "block";
                  reasonInput.focus();
                } else {
                  reasonBox.style.display = "none";
                }
              });
            });
          });
        },
        preConfirm: () => {
          let decisions = [];
          let isValid = true;
          let errorMsg = "";
          let hasSelection = false;

          document
            .querySelectorAll(".approval-card-3d")
            .forEach((card, index) => {
              let idx = card.getAttribute("data-idx");
              let amount = card.getAttribute("data-amount");
              let statusType = card.getAttribute("data-status");

              // หาว่ามีการติ๊กเลือก Radio หรือไม่
              let checkedInput = card.querySelector(
                `input[name="decision_${idx}"]:checked`,
              );

              // 🔥 ถ้ารายการนี้ "ถูกพิจารณาแล้ว" (มีการเลือก) ค่อยเก็บข้อมูลไปบันทึก
              if (checkedInput) {
                hasSelection = true;
                let decisionStatus = checkedInput.value;
                let note = card
                  .querySelector(".reject-reason-input")
                  .value.trim();

                if (decisionStatus === "rejected" && !note) {
                  isValid = false;
                  errorMsg = `กรุณาระบุเหตุผลที่ไม่อนุมัติ สำหรับรายการที่ ${index + 1}`;
                }

                decisions.push({
                  logIndex: parseInt(idx),
                  status: decisionStatus,
                  note: note,
                  amount: amount,
                  type: statusType,
                });
              }
            });

          // ตรวจสอบว่าไม่ได้เลือกอะไรเลยสักอัน
          if (!hasSelection) {
            return Swal.showValidationMessage(
              "⚠️ กรุณาเลือกดำเนินการอย่างน้อย 1 รายการ หรือกดยกเลิก",
            );
          }

          if (!isValid) return Swal.showValidationMessage("⚠️ " + errorMsg);

          return decisions;
        },
      }).then((res) => {
        if (res.isConfirmed) {
          Swal.fire({
            title: "กำลังบันทึก...",
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
          });
          $.post(
            "service_dashboard.php",
            {
              action: "process_multi_approval",
              req_id: reqId,
              decisions: JSON.stringify(res.value),
            },
            function (response) {
              if (response.status === "success") {
                Swal.fire(
                  "สำเร็จ",
                  "บันทึกผลการพิจารณาเรียบร้อย",
                  "success", ).then(() => updateData());
              } else {
                Swal.fire("เกิดข้อผิดพลาด", response.message, "error");
              }
            },
            "json",
          );
        }
      });
    },
    "json",
  ).fail(function () {
    Swal.fire("ข้อผิดพลาด", "ไม่สามารถดึงข้อมูลได้", "error");
  });
}
$(document).ready(function () {
  // โหลดคะแนนความพึงพอใจทันทีที่เปิดหน้าเว็บ
  loadSatisfactionStats();
});

// ฟังก์ชันดึงคะแนนและอัปเดตการ์ด
function loadSatisfactionStats() {
  $.ajax({
    url: "service_dashboard.php", // ตรวจสอบให้แน่ใจว่า path ไฟล์ถูกต้อง
    type: "POST",
    data: { action: "get_satisfaction_stats" },
    dataType: "json",
    success: function (response) {
      if (response.status === "success") {
        // 1. อัปเดตตัวเลขจำนวนเต็ม (เช่น 4.5)
        let avg = parseFloat(response.average).toFixed(1);
        $("#avg_rating_text").text(avg);

        // 2. อัปเดตตัวเลขจำนวนรายการทั้งหมด (เช่น 12 รายการ)
        $("#total_rating_text").text(response.total);

        // 3. อัปเดตการแสดงผลดาว (ดาวทอง/ดาวเทา)
        let starHtml = "";
        let fullStars = Math.floor(avg); // จำนวนดาวเต็มดวง
        let hasHalfStar = avg - fullStars >= 0.5; // มีดาวครึ่งดวงไหม?

        for (let i = 1; i <= 5; i++) {
          if (i <= fullStars) {
            // ดาวเต็ม
            starHtml +=
              '<i class="fas fa-star" style="color: #f59e0b; margin-right: 2px;"></i>';
          } else if (i === fullStars + 1 && hasHalfStar) {
            // ดาวครึ่งดวง
            starHtml +=
              '<i class="fas fa-star-half-alt" style="color: #f59e0b; margin-right: 2px;"></i>';
          } else {
            // ดาวเทา (ว่างเปล่า)
            starHtml +=
              '<i class="fas fa-star" style="color: #e2e8f0; margin-right: 2px;"></i>';
          }
        }
        $("#star_container").html(starHtml);
      } else {
        console.error("Failed to load stats: ", response.message);
      }
    },
    error: function () {
      console.error("AJAX Error: Cannot load satisfaction stats.");
    },
  });
}
// 🟢 ฟังก์ชันคัดลอกข้อมูลร้านค้าไปยังรายการอื่นที่เลือกไว้ (แบบ Popup ไม่หาย)
window.applyShopToAllChecked = function (sourceIdx, btnElement) {
  const sName = document.getElementById(`s_name_${sourceIdx}`).value.trim();
  const sOwner = document.getElementById(`s_owner_${sourceIdx}`).value.trim();
  const sPhone = document.getElementById(`s_phone_${sourceIdx}`).value.trim();

  if (!sName) {
    // ใช้แจ้งเตือนธรรมดาแทน จะได้ไม่เตะ Swal ตัวหลัก
    alert("กรุณาพิมพ์ชื่อร้านค้าให้เรียบร้อยก่อนกดคัดลอกครับ");
    return;
  }

  let count = 0;
  // หา checkbox ทุกตัวที่ถูกติ๊ก
  document
    .querySelectorAll('input[id^="chk_"]:checked:not(:disabled)')
    .forEach((chk) => {
      let tIdx = chk.id.split("_")[1];

      if (tIdx !== String(sourceIdx)) {
        let radioExt = document.querySelector(
          `input[name="dest_${tIdx}"][value="external"]`,
        );
        if (radioExt) {
          radioExt.checked = true;
          window.toggleShopFields(tIdx); // สั่งกางกล่องออก

          // ยัดข้อมูลร้านค้าลงไป
          document.getElementById(`s_name_${tIdx}`).value = sName;
          document.getElementById(`s_owner_${tIdx}`).value = sOwner;
          document.getElementById(`s_phone_${tIdx}`).value = sPhone;
          count++;
        }
      }
    });

  // 🌟 ลูกเล่นใหม่: เปลี่ยนสถานะปุ่ม แทนการเรียก Popup ซ้อน
  if (btnElement) {
    let originalText = btnElement.innerHTML;
    let originalBg = btnElement.style.background;
    let originalColor = btnElement.style.color;

    if (count > 0) {
      btnElement.innerHTML = `<i class="fas fa-check"></i> ก๊อปปี้สำเร็จ ${count} รายการ!`;
      btnElement.style.background = "#10b981";
      btnElement.style.color = "#fff";
      btnElement.style.borderColor = "#10b981";
    } else {
      btnElement.innerHTML = `<i class="fas fa-exclamation-circle"></i> โปรดติ๊ก ✔️ ชิ้นอื่นก่อน`;
      btnElement.style.background = "#ef4444";
      btnElement.style.color = "#fff";
      btnElement.style.borderColor = "#ef4444";
    }

    // คืนค่าปุ่มกลับเป็นเหมือนเดิมใน 2.5 วินาที
    setTimeout(() => {
      btnElement.innerHTML = originalText;
      btnElement.style.background = originalBg;
      btnElement.style.color = originalColor;
      btnElement.style.borderColor = "";
    }, 2500);
  }
};
function openExpenseRequest(reqId, jsonInput) {
  let data = typeof jsonInput === "string" ? JSON.parse(jsonInput) : jsonInput;

  // ดึงรายชื่อสินค้าทั้งหมด
  let availableItems = [];
  if (data.items_status) {
    availableItems = Object.keys(data.items_status);
  } else if (data.all_project_items) {
    availableItems = data.all_project_items;
  }

  // 🌟 สร้าง HTML สำหรับรายการอ้างอิง (List Rows 1 บรรทัด / 1 รายการ)
  let refItemsHtml = "";
  if (availableItems.length > 0) {
    availableItems.forEach((item, idx) => {
      let delay = idx * 0.05; // หน่วงเวลาเด้งทีละอัน
      refItemsHtml += `
                <label class="ref-row-card" style="animation-delay: ${delay}s;">
                    <input type="checkbox" class="ref-item-chk" value="${item}">
                    <div class="ref-row-content">
                        <div class="chk-box-ui"><i class="fas fa-check" style="font-size:10px;"></i></div>
                        <span class="item-name"><i class="fas fa-box" style="color:#94a3b8; margin-right:5px;"></i> ${item}</span>
                    </div>
                </label>
            `;
    });
  } else {
    refItemsHtml = `<div style="width:100%; text-align:center; padding:15px; color:#94a3b8; font-size:12px;">ไม่มีรายการสินค้าให้อ้างอิง</div>`;
  }

  // 🌟 ฟังก์ชันคำนวณยอดรวมทุกรายการ (Grand Total)
  window.calcExpGrandTotal = function () {
    let grandTotal = 0;
    document.querySelectorAll(".expense-row").forEach((row) => {
      let qty = parseFloat(row.querySelector(".exp-qty").value) || 0;
      let price = parseFloat(row.querySelector(".exp-price").value) || 0;
      grandTotal += qty * price;
    });
    document.getElementById("exp_total_display").value =
      grandTotal.toLocaleString("en-US", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    document.getElementById("exp_total_hidden").value = grandTotal;
  };

  // 🌟 ฟังก์ชันเพิ่มแถวรายการใหม่
  window.addExpenseRow = function () {
    const container = document.getElementById("expense-items-container");
    const rowId = "exp_row_" + Date.now();
    const rowHtml = `
            <div class="expense-row" id="${rowId}" style="background:#ffffff; border:1px solid #e2e8f0; border-left:4px solid #8b5cf6; border-radius:10px; padding:12px; margin-bottom:10px; position:relative; box-shadow:0 2px 4px rgba(0,0,0,0.02); animation: popIn 0.3s ease;">
                <button type="button" onclick="removeExpenseRow('${rowId}')" style="position:absolute; right:10px; top:10px; background:#fee2e2; color:#ef4444; border:none; width:28px; height:28px; border-radius:6px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:0.2s;" onmouseover="this.style.background='#fca5a5'" onmouseout="this.style.background='#fee2e2'">
                    <i class="fas fa-times"></i>
                </button>
                
                <label class="swal-label" style="font-size:12px;"><i class="fas fa-file-alt" style="color:#8b5cf6;"></i> ชื่อรายการเบิก <span style="color:#ef4444">*</span></label>
                <input type="text" class="swal-input-premium exp-name" placeholder="ระบุชื่อรายการ (เช่น ค่าเดินทาง, อะไหล่)..." style="margin-bottom:8px; padding:8px; padding-right:40px;">
                
                <div style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label class="swal-label" style="font-size:12px;">จำนวน <span style="color:#ef4444">*</span></label>
                        <input type="number" class="swal-input-premium exp-qty" placeholder="0" min="1" step="1" oninput="calcExpGrandTotal()" style="padding:8px; margin-bottom:0;">
                    </div>
                    <div style="flex:1;">
                        <label class="swal-label" style="font-size:12px;">ราคา/หน่วย <span style="color:#ef4444">*</span></label>
                        <input type="number" class="swal-input-premium exp-price" placeholder="0.00" min="0" step="0.01" oninput="calcExpGrandTotal()" style="padding:8px; margin-bottom:0;">
                    </div>
                </div>
            </div>
        `;
    container.insertAdjacentHTML("beforeend", rowHtml);
  };

  // 🌟 ฟังก์ชันลบแถว
  window.removeExpenseRow = function (rowId) {
    document.getElementById(rowId).remove();
    calcExpGrandTotal();
  };

  Swal.fire({
    title: "",
    html: `
        <style>
            @keyframes popIn { 0% { transform: scale(0.9); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
            @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
            
            .swal-label { font-size: 13px; font-weight: 700; color: #475569; display: block; text-align: left; margin-bottom: 6px; }
            .swal-input-premium { width: 100%; border: 2px solid #e2e8f0; border-radius: 8px; padding: 10px; font-size: 14px; box-sizing: border-box; transition: all 0.3s; background: #f8fafc; font-family: 'Prompt', sans-serif; color: #1e293b; margin-bottom: 12px; }
            .swal-input-premium:focus { background: #ffffff; border-color: #8b5cf6; outline: none; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15); }
            
            /* 🌟 สไตล์กล่องเลือกสินค้าอ้างอิงแบบใหม่ (เรียงเป็นบรรทัด) */
            .ref-list-container {
                display: flex; flex-direction: column; gap: 6px; background: #f8fafc; border: 1px dashed #cbd5e1;
                padding: 10px; border-radius: 12px; max-height: 180px; overflow-y: auto; margin-bottom: 15px;
            }
            .ref-row-card { cursor: pointer; margin: 0; display: block; opacity: 0; animation: fadeInUp 0.3s ease forwards; width: 100%; }
            .ref-item-chk { display: none; }
            .ref-row-content {
                background: #ffffff; border: 1px solid #e2e8f0; color: #475569; padding: 10px 12px;
                border-radius: 8px; font-size: 13px; font-weight: 600; font-family: 'Prompt', sans-serif;
                transition: all 0.2s; display: flex; align-items: center; gap: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            }
            .ref-row-card:hover .ref-row-content { border-color: #8b5cf6; background: #f5f3ff; transform: translateY(-1px); }
            
            /* UI Checkbox จำลอง */
            .chk-box-ui {
                width: 18px; height: 18px; border: 2px solid #cbd5e1; border-radius: 4px; 
                display: flex; align-items: center; justify-content: center; color: transparent; transition: 0.2s;
            }
            
            /* สไตล์ตอนถูกเลือก (Checked) */
            .ref-item-chk:checked + .ref-row-content {
                background: #f5f3ff; color: #4c1d95; border-color: #8b5cf6;
                box-shadow: 0 4px 6px rgba(139, 92, 246, 0.1);
            }
            .ref-item-chk:checked + .ref-row-content .chk-box-ui {
                background: #8b5cf6; border-color: #8b5cf6; color: #ffffff;
            }
            .ref-item-chk:checked + .ref-row-content .item-name i {
                color: #8b5cf6 !important;
            }

            /* กล่องอัปโหลดไฟล์ */
            .custom-file-upload { position: relative; width: 100%; border: 2px dashed #cbd5e1; border-radius: 12px; background: #f8fafc; padding: 15px 10px; text-align: center; transition: all 0.3s; cursor: pointer; box-sizing: border-box; margin-bottom: 10px;}
            .custom-file-upload:hover { border-color: #8b5cf6; background: #f5f3ff; }
            .custom-file-upload input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 10; }
            .upload-icon { font-size: 24px; color: #94a3b8; margin-bottom: 5px; transition: color 0.3s; }
            .custom-file-upload:hover .upload-icon { color: #8b5cf6; }
            .upload-text { font-size: 12px; color: #64748b; font-weight: 600; transition: color 0.3s; }
            .custom-file-upload:hover .upload-text { color: #4c1d95; }
            
            ::-webkit-scrollbar { width: 6px; }
            ::-webkit-scrollbar-track { background: transparent; }
            ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        </style>

        <div style="padding:0; box-sizing:border-box;">
            <div style="text-align:center; margin-bottom:15px; animation: fadeInUp 0.4s ease;">
                <div style="width:55px; height:55px; background:linear-gradient(135deg, #8b5cf6, #6d28d9); color:#fff; border-radius:16px; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; box-shadow:0 6px 12px -4px rgba(139, 92, 246, 0.5);">
                    <i class="fas fa-file-invoice-dollar fa-lg"></i>
                </div>
                <div style="font-size:18px; font-weight:900; color:#1e293b;">เบิกค่าใช้จ่าย / เสนอราคา</div>
            </div>

            <div style="text-align:left;">
                <label class="swal-label"><i class="fas fa-tags" style="color:#8b5cf6;"></i> อ้างอิงรายการสินค้าหลัก <span style="color:#94a3b8; font-weight:500; font-size:11px;">(คลิกเลือกได้หลายรายการ)</span></label>
                
                <div class="ref-list-container">
                    ${refItemsHtml}
                </div>
                
                <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:8px; margin-top:10px;">
                    <label class="swal-label" style="margin-bottom:0;"><i class="fas fa-list-ul" style="color:#f59e0b;"></i> รายการค่าใช้จ่าย</label>
                    <button type="button" onclick="addExpenseRow()" style="background:#e0e7ff; color:#4f46e5; border:none; padding:5px 12px; border-radius:50px; font-size:11px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:4px; transition:0.2s; box-shadow:0 2px 4px rgba(0,0,0,0.05);" onmouseover="this.style.background='#c7d2fe'; this.style.transform='translateY(-1px)';" onmouseout="this.style.background='#e0e7ff'; this.style.transform='translateY(0)';">
                        <i class="fas fa-plus"></i> เพิ่มรายการ
                    </button>
                </div>

                <div id="expense-items-container" style="background:#f1f5f9; padding:10px; border-radius:12px; max-height:220px; overflow-y:auto; border:1px solid #e2e8f0; margin-bottom:15px;">
                    </div>

                <label class="swal-label"><i class="fas fa-calculator" style="color:#10b981;"></i> รวมเป็นเงินสุทธิ (บาท)</label>
                <input type="text" id="exp_total_display" class="swal-input-premium" placeholder="0.00" readonly style="background:#f0fdf4; border-color:#86efac; font-weight:800; color:#166534; font-size:20px; text-align:right;">
                <input type="hidden" id="exp_total_hidden" value="0">

                <label class="swal-label"><i class="fas fa-comment-dots" style="color:#0ea5e9;"></i> หมายเหตุเพิ่มเติม</label>
                <input type="text" id="exp_remark" class="swal-input-premium" placeholder="รายละเอียดเพิ่มเติม (ไม่บังคับ)...">

                <label class="swal-label"><i class="fas fa-paperclip" style="color:#db2777;"></i> แนบไฟล์ใบเสนอราคา/ใบเสร็จ <span style="color:#ef4444">*</span></label>
                <div class="custom-file-upload">
                    <input type="file" id="exp_file" accept="image/*,.pdf" onchange="
                        let fileName = this.files[0] ? this.files[0].name : 'คลิกเพื่อเลือกไฟล์ หรือลากมาวาง (รูปภาพ/PDF)';
                        let textColor = this.files[0] ? '#8b5cf6' : '#64748b';
                        let iconColor = this.files[0] ? '#6d28d9' : '#94a3b8';
                        document.getElementById('exp_file_name').innerText = fileName;
                        document.getElementById('exp_file_name').style.color = textColor;
                        document.getElementById('exp_file_icon').style.color = iconColor;
                        document.getElementById('exp_file_icon').className = this.files[0] ? 'fas fa-file-check' : 'fas fa-cloud-upload-alt';
                    ">
                    <div class="upload-icon"><i id="exp_file_icon" class="fas fa-cloud-upload-alt"></i></div>
                    <div class="upload-text" id="exp_file_name">คลิกเพื่อเลือกไฟล์ หรือลากมาวาง (รูปภาพ/PDF)</div>
                </div>
            </div>
        </div>
        `,
    width: "550px",
    showCancelButton: true,
    confirmButtonText:
      '<i class="fas fa-paper-plane" style="margin-right:5px;"></i> ยืนยันเบิกค่าใช้จ่าย',
    cancelButtonText: "ยกเลิก",
    confirmButtonColor: "#8b5cf6",
    customClass: {
      confirmButton: "swal2-confirm-btn-purple",
      cancelButton: "swal2-cancel-btn-gray",
    },
    didOpen: () => {
      // สร้างแถวแรกขึ้นมาให้เลยตอนเปิด Popup
      addExpenseRow();
    },
    preConfirm: () => {
      const file = document.getElementById("exp_file").files[0];
      const rows = document.querySelectorAll(".expense-row");
      let expenses = [];
      let isValid = true;
      let errorMsg = "";

      // ตรวจสอบและเก็บข้อมูลแต่ละแถว
      rows.forEach((row, index) => {
        let name = row.querySelector(".exp-name").value.trim();
        let qty = parseFloat(row.querySelector(".exp-qty").value) || 0;
        let price = parseFloat(row.querySelector(".exp-price").value) || 0;

        if (!name || qty <= 0 || price < 0) {
          isValid = false;
          errorMsg = `กรุณากรอกข้อมูลรายการที่ ${index + 1} ให้ครบถ้วน`;
        } else {
          expenses.push({
            name: name,
            qty: qty,
            price: price,
            total: qty * price,
          });
        }
      });

      // 🔥 1. ดึงค่ารายการอ้างอิงที่ถูกติ๊กเลือกทั้งหมด
      let selectedRefs = [];
      document.querySelectorAll(".ref-item-chk:checked").forEach((chk) => {
        selectedRefs.push(chk.value);
      });

      // 🔥 2. [เพิ่มใหม่] บังคับว่าต้องเลือกอ้างอิงสินค้าอย่างน้อย 1 รายการ
      if (selectedRefs.length === 0) {
        return Swal.showValidationMessage("⚠️ กรุณาคลิกเลือก 'อ้างอิงรายการสินค้าหลัก' อย่างน้อย 1 รายการ");
      }

      // ดักจับ Error อื่นๆ
      if (expenses.length === 0)
        return Swal.showValidationMessage(
          "⚠️ กรุณาเพิ่มรายการเบิกอย่างน้อย 1 รายการ",
        );
      if (!isValid) return Swal.showValidationMessage("⚠️ " + errorMsg);
      if (!file)
        return Swal.showValidationMessage(
          "⚠️ กรุณาแนบไฟล์ใบเสนอราคาหรือใบเสร็จ",
        );

      return {
        ref_item: JSON.stringify(selectedRefs), // ส่งเป็น JSON Array
        expenses_json: JSON.stringify(expenses),
        total: document.getElementById("exp_total_hidden").value,
        remark: document.getElementById("exp_remark").value.trim(),
        file: file,
      };
    },
  }).then((res) => {
    if (res.isConfirmed) {
      let fd = new FormData();
      fd.append("action", "request_expense");
      fd.append("req_id", reqId);
      fd.append("ref_item", res.value.ref_item);
      fd.append("expenses_json", res.value.expenses_json);
      fd.append("total", res.value.total);
      fd.append("remark", res.value.remark);
      fd.append("expense_file", res.value.file);

      Swal.fire({
        title: "กำลังส่งเรื่องเบิก...",
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
      });

      $.ajax({
        url: "service_dashboard.php",
        type: "POST",
        data: fd,
        processData: false,
        contentType: false,
        dataType: "json",
        success: function (response) {
          if (response.status === "success") {
            Swal.fire(
              "สำเร็จ",
              "ส่งเรื่องเบิกค่าใช้จ่ายเรียบร้อย",
              "success",
            ).then(() => updateData());
          } else {
            Swal.fire("เกิดข้อผิดพลาด", response.message, "error");
          }
        },
        error: function () {
          Swal.fire("Error", "ไม่สามารถติดต่อเซิร์ฟเวอร์ได้", "error");
        },
      });
    }
  });
}
