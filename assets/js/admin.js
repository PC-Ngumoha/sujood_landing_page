(function () {
  "use strict";

  var backdrop = document.getElementById("modal-backdrop");
  if (!backdrop) return;

  var titleEl = document.getElementById("modal-title");
  var msgEl = document.getElementById("modal-message");
  var formHost = document.getElementById("modal-form-host");
  var okBtn = document.getElementById("modal-ok");
  var cancelBtn = document.getElementById("modal-cancel");
  var closeBtn = document.getElementById("modal-close");

  var MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
  var TAGS = ["Online", "In person", "Hybrid"];

  var FIELDS = {
    events: [
      { name: "name", label: "Event name", type: "text", required: true },
      { name: "day", label: "Day", type: "text", required: true },
      { name: "month", label: "Month", type: "select", options: MONTHS, required: true },
      { name: "location", label: "Location", type: "text", required: true },
      { name: "time", label: "Time", type: "text" },
      { name: "tag", label: "Tag", type: "select", options: TAGS, required: true },
    ],
    socials: [
      { name: "platform", label: "Platform", type: "text", required: true },
      { name: "url", label: "URL", type: "text", required: true },
    ],
  };

  var activeConfirm = null;

  function esc(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function openModal(opts) {
    titleEl.textContent = opts.title || "Confirm";
    msgEl.textContent = opts.message || "";
    msgEl.style.display = opts.message ? "" : "none";
    formHost.innerHTML = opts.formHtml || "";
    formHost.style.display = opts.formHtml ? "" : "none";
    okBtn.className = opts.okClass || "btn btn-primary";
    okBtn.textContent = opts.okLabel || "Confirm";
    activeConfirm = opts.onOk || null;
    backdrop.hidden = false;
    document.body.style.overflow = "hidden";
    if (opts.formHtml) {
      var first = formHost.querySelector("input, select, textarea");
      if (first) first.focus();
    } else {
      okBtn.focus();
    }
  }

  function closeModal() {
    backdrop.hidden = true;
    document.body.style.overflow = "";
    activeConfirm = null;
  }

  okBtn.addEventListener("click", function () {
    if (activeConfirm) activeConfirm();
  });
  cancelBtn.addEventListener("click", closeModal);
  closeBtn.addEventListener("click", closeModal);
  backdrop.addEventListener("click", function (e) {
    if (e.target === backdrop) closeModal();
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && !backdrop.hidden) closeModal();
  });

  // ---- confirmations (delete row / clear all) ----
  Array.prototype.forEach.call(document.querySelectorAll("form.js-confirm"), function (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      openModal({
        title: form.dataset.title || "Confirm",
        message: form.dataset.message || "Are you sure?",
        okLabel: form.dataset.ok || "Confirm",
        okClass: "btn btn-danger solid",
        onOk: function () {
          form.submit();
        },
      });
    });
  });

  // ---- logout ----
  Array.prototype.forEach.call(document.querySelectorAll("a.js-logout"), function (link) {
    link.addEventListener("click", function (e) {
      e.preventDefault();
      openModal({
        title: "Log out?",
        message: "Are you sure you want to end your admin session?",
        okLabel: "Log out",
        okClass: "btn btn-danger solid",
        onOk: function () {
          window.location.href = link.getAttribute("href");
        },
      });
    });
  });

  // ---- edit (events / socials) ----
  function buildEditForm(type, values) {
    var fields = FIELDS[type] || [];
    var html = "";
    fields.forEach(function (f) {
      var cur = values["field_" + f.name] || "";
      var req = f.required ? " required" : "";
      html += '<label for="edit-' + f.name + '">' + esc(f.label) + "</label>";
      if (f.type === "select") {
        var opts = f.options
          .map(function (o) {
            return '<option value="' + esc(o) + '"' + (cur === o ? " selected" : "") + ">" + esc(o) + "</option>";
          })
          .join("");
        html += '<select id="edit-' + f.name + '" name="field_' + f.name + '"' + req + ">" + opts + "</select>";
      } else {
        html +=
          '<input id="edit-' + f.name + '" type="text" name="field_' + f.name + '" value="' + esc(cur) + '"' + req + ">";
      }
    });
    return html;
  }

  function hiddenInput(form, name, value) {
    var c = document.createElement("input");
    c.type = "hidden";
    c.name = name;
    c.value = value;
    form.appendChild(c);
  }

  function showToast(message, type) {
    var host = document.getElementById("admin-toast-host");
    if (!host) {
      host = document.createElement("div");
      host.id = "admin-toast-host";
      host.className = "admin-toast-host";
      document.body.appendChild(host);
    }
    var t = document.createElement("div");
    t.className = "admin-toast" + (type === "error" ? " error" : "");
    t.textContent = message;
    host.appendChild(t);
    requestAnimationFrame(function () {
      t.classList.add("show");
    });
    setTimeout(function () {
      t.classList.remove("show");
      t.classList.add("out");
      setTimeout(function () {
        t.remove();
      }, 250);
    }, 2600);
  }

  Array.prototype.forEach.call(document.querySelectorAll("button.js-edit"), function (btn) {
    btn.addEventListener("click", function () {
      var type = btn.dataset.type;
      var values = {};
      Object.keys(btn.dataset).forEach(function (k) {
        if (k.indexOf("field_") === 0) values[k] = btn.dataset[k];
      });
      openModal({
        title: "Edit " + type,
        message: "",
        okLabel: "Save changes",
        okClass: "btn btn-primary",
        formHtml: buildEditForm(type, values),
        onOk: function () {
          var missing = false;
          Array.prototype.forEach.call(formHost.querySelectorAll("[required]"), function (inp) {
            if (!inp.value.trim()) missing = true;
          });
          if (missing) {
            showToast("Please fill in the required fields.", "error");
            return;
          }
          var form = document.createElement("form");
          form.method = "post";
          form.action = "index.php";
          hiddenInput(form, "csrf", btn.dataset.csrf || "");
          hiddenInput(form, "action", "edit");
          hiddenInput(form, "type", type);
          hiddenInput(form, "line", btn.dataset.line || "");
          hiddenInput(form, "return_view", btn.dataset.return_view || "");
          hiddenInput(form, "return_q", btn.dataset.return_q || "");
          hiddenInput(form, "return_rating", btn.dataset.return_rating || "");
          Array.prototype.forEach.call(formHost.querySelectorAll("[name]"), function (inp) {
            hiddenInput(form, inp.name, inp.value);
          });
          document.body.appendChild(form);
          form.submit();
        },
      });
    });
  });
})();
