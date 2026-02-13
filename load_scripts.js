/* global-loader.js (แก้ไขแล้ว) */

(function () {
  const cssLinks = [
    "https://earthchie.github.io/jquery.Thailand.js/jquery.Thailand.js/dist/jquery.Thailand.min.css",
    "https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css",
    "https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css",
  ];

  const jsScripts = [
    "https://earthchie.github.io/jquery.Thailand.js/jquery.Thailand.js/dependencies/JQL.min.js",
    "https://earthchie.github.io/jquery.Thailand.js/jquery.Thailand.js/dependencies/typeahead.bundle.js",
    "https://earthchie.github.io/jquery.Thailand.js/jquery.Thailand.js/dist/jquery.Thailand.min.js",
    "https://cdn.jsdelivr.net/npm/sweetalert2@11",
    "https://code.jquery.com/ui/1.13.2/jquery-ui.min.js",
    "https://cdnjs.cloudflare.com/ajax/libs/parsley.js/2.9.2/parsley.min.js",
  ];

  function loadCSS(urls) {
    urls.forEach((url) => {
      let link = document.createElement("link");
      link.rel = "stylesheet";
      link.href = url;
      document.head.appendChild(link);
    });
  }

  function loadScripts(urls, callback) {
    if (urls.length === 0) {
      if (callback) callback();
      return;
    }
    let script = document.createElement("script");
    script.src = urls[0];
    script.onload = () => loadScripts(urls.slice(1), callback);

    // ตรวจสอบก่อนว่า body มีไหม ถ้าไม่มีให้ใช้ head
    (document.body || document.head).appendChild(script);
  }

  // เริ่มทำงาน
  loadCSS(cssLinks);

  loadScripts(jsScripts, function () {
    // ตรวจสอบว่า DOM พร้อมทำงานหรือยัง ก่อนเรียกใช้ Selectors
    $(document).ready(function () {
      $.Thailand({
        $district: $("#district"),
        $amphoe: $("#amphoe"),
        $province: $("#province"),
        $zipcode: $("#zipcode"),
        onDataFill: function (data) {
          $("#district, #amphoe, #province, #zipcode").trigger("input");
          return data;
        },
      });
    });

    $(".select-search").each(function () {
      $(this).select2({
        width: "100%",
        allowClear: true,
        placeholder: $(this).data("placeholder"),
        language: {
          noResults: function () {
            return "ไม่พบข้อมูล";
          },
          inputTooShort: function () {
            return "โปรดพิมพ์เพิ่มเพื่อค้นหา";
          },
        },
      });
    });

    $(".select-search").on("select2:select select2:unselect", function (e) {
      // หน่วงเวลาเล็กน้อยเพื่อให้ค่า clear เสร็จสมบูรณ์ก่อนเช็ค
      setTimeout(() => {
        $(this).parsley().validate();
      }, 100);
    });
  });
})();
