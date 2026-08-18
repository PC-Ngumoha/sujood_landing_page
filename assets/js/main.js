(function () {
  // ---- toast notifications ----
  const toastContainer = document.getElementById("toast-container");

  function showToast(message, type = "success", duration = 4000) {
    if (!toastContainer) return;
    const toast = document.createElement("div");
    toast.className = "toast toast-" + type;
    const icon =
      type === "success"
        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>'
        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>';
    toast.innerHTML = '<span class="toast-icon">' + icon + "</span><span>" + message + "</span>";
    toastContainer.appendChild(toast);

    // trigger animation
    requestAnimationFrame(() => toast.classList.add("show"));

    setTimeout(() => {
      toast.classList.remove("show");
      toast.addEventListener("transitionend", () => toast.remove(), { once: true });
      setTimeout(() => toast.remove(), 400);
    }, duration);
  }

  async function serverError(res, fallback) {
    try {
      const data = await res.clone().json();
      if (data && data.error) return data.error;
    } catch (err) {
      /* not JSON — fall through */
    }
    return fallback;
  }

  // ---- spine nav active state ----
  const dots = document.querySelectorAll(".spine-dot");
  const targets = Array.from(dots).map((d) =>
    document.querySelector(d.dataset.target),
  );
  dots.forEach((d) =>
    d.addEventListener("click", (e) => {
      e.preventDefault();
      document
        .querySelector(d.dataset.target)
        .scrollIntoView({ behavior: "smooth" });
    }),
  );
  function updateSpine() {
    let current = 0;
    targets.forEach((t, i) => {
      if (t && t.getBoundingClientRect().top < window.innerHeight * 0.4)
        current = i;
    });
    dots.forEach((d, i) => d.classList.toggle("active", i === current));
  }
  window.addEventListener("scroll", updateSpine);
  updateSpine();

  // ---- star picker ----
  let rating = 0;
  const starBtns = document.querySelectorAll("#star-picker button");
  starBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      rating = parseInt(btn.dataset.val, 10);
      starBtns.forEach((b) =>
        b.classList.toggle("filled", parseInt(b.dataset.val, 10) <= rating),
      );
    });
  });

  // ---- reviews: submit ----
  const listEl = document.getElementById("review-list");
  const noteEl = document.getElementById("review-note");

  // ---- reviews: 2-at-a-time carousel ----
  const prevBtn = document.getElementById("review-prev");
  const nextBtn = document.getElementById("review-next");
  const reviewNav = document.getElementById("review-nav");
  const VIEW_WINDOW = 2;
  let cards = [];
  let windowStart = 0;

  function renderReviews() {
    cards.forEach((c, i) => {
      c.style.display =
        i >= windowStart && i < windowStart + VIEW_WINDOW ? "" : "none";
    });
    if (!reviewNav) return;
    if (cards.length <= VIEW_WINDOW) {
      reviewNav.hidden = true;
    } else {
      reviewNav.hidden = false;
      if (prevBtn)
        prevBtn.disabled = windowStart === 0;
      if (nextBtn)
        nextBtn.disabled = windowStart + VIEW_WINDOW >= cards.length;
    }
  }

  if (listEl) {
    cards = Array.from(listEl.querySelectorAll(".review-card"));
    if (prevBtn)
      prevBtn.addEventListener("click", () => {
        windowStart = Math.max(0, windowStart - VIEW_WINDOW);
        renderReviews();
      });
    if (nextBtn)
      nextBtn.addEventListener("click", () => {
        windowStart = Math.min(cards.length - VIEW_WINDOW, windowStart + VIEW_WINDOW);
        renderReviews();
      });
    renderReviews();
  }

  function starString(n) {
    return "\u2605\u2605\u2605\u2605\u2605".slice(0, n) + "\u2606\u2606\u2606\u2606\u2606".slice(0, 5 - n);
  }

  function appendReviewCard(review) {
    if (!listEl) return;
    const card = document.createElement("div");
    card.className = "review-card";
    card.innerHTML =
      '<div class="stars">' + starString(review.rating) + '</div>' +
      '<div class="review-quote">&ldquo;' + review.text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;") + '&rdquo;</div>' +
      '<div class="review-meta">' + review.name.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;") + "</div>";
    listEl.insertBefore(card, listEl.firstChild);
    cards.unshift(card);
    windowStart = 0;
    renderReviews();
  }

  document
    .getElementById("review-form")
    .addEventListener("submit", async function (e) {
      e.preventDefault();
      const nameField = document.getElementById("rv-name");
      const reviewField = document.getElementById("rv-text");
      const reviewBtn = document.getElementById("review-button");

      const name = nameField.value.trim();
      const text = reviewField.value.trim();
      if (!name || !text || rating === 0) {
        showToast("Please add a name, a rating, and your review.", "error");
        return;
      }

      try {
        const res = await fetch("review.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            rating,
            name,
            review: text,
            website: document.getElementById("review-hp")?.value ?? "",
          }),
        });

        if (res.ok) {
          appendReviewCard({ rating, name, text });
          showToast("Thank you — your review has been submitted.");
        } else {
          showToast(await serverError(res, "Something went wrong — please try again."), "error");
        }
      } catch (err) {
        showToast("Network error — your review wasn't saved. Try again.", "error");
      }

      this.reset();
      rating = 0;
      starBtns.forEach((b) => b.classList.remove("filled"));
      nameField.disabled = true;
      reviewField.disabled = true;
      reviewBtn.disabled = true;
      reviewBtn.classList.remove("btn-primary");
      reviewBtn.classList.add("btn-disabled");
    });

  // ---- gift it forward ----
  const giftToggle = document.getElementById("gift-toggle");
  const giftHint = document.getElementById("gift-hint");
  const recipientFields = document.querySelectorAll(".gift-recipient-field");
  const giftMessageLabel = document.getElementById("gift-message-label");
  let giftMode = "known";

  giftToggle.querySelectorAll(".gift-option").forEach((btn) => {
    btn.addEventListener("click", () => {
      giftMode = btn.dataset.mode;
      giftToggle
        .querySelectorAll(".gift-option")
        .forEach((b) => b.classList.toggle("active", b === btn));
      const isKnown = giftMode === "known";
      recipientFields.forEach((f) => {
        f.classList.toggle("hidden", !isKnown);
        f.querySelector("input") &&
          (f.querySelector("input").required = isKnown);
      });
      if (isKnown) {
        giftHint.textContent =
          "Tell us who the book is for, and we'll take care of the rest.";
        giftMessageLabel.textContent = "Personal message (optional)";
      } else {
        giftHint.textContent =
          "We'll pick someone who needs it and send a copy on your behalf.";
        giftMessageLabel.textContent =
          "Note about who should receive it (optional)";
      }
    });
  });

  document
    .getElementById("gift-form")
    .addEventListener("submit", async function (e) {
      e.preventDefault();

      const data = {
        sender_name: document.getElementById("gift-from-name").value,
        sender_email: document.getElementById("gift-from-email").value,
        recipient_name: document.getElementById("gift-to-name").value ?? "",
        recipient_email: document.getElementById("gift-to-contact").value ?? "",
        gift_message: document.getElementById("gift-message").value ?? "",
        website: document.getElementById("gift-hp")?.value ?? "",
      };

      try {
        const res = await fetch("giftRequest.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(data),
        });

        if (res.ok) {
          showToast("Thank you — we've received your gift request.");
        } else {
          showToast(await serverError(res, "Please fill in your name, email, and the recipient's details."), "error");
        }
      } catch (err) {
        showToast("Network error — please try again.", "error");
      }

      this.reset();
      giftToggle.querySelector('[data-mode="known"]').click();
    });

  // ---- newsletter signup ----
  document
    .getElementById("signup-form")
    .addEventListener("submit", async function (e) {
      e.preventDefault();
      const messageElement = document.getElementById("signup-message");
      const emailField = document.getElementById("signup-email");
      const signupBtn = document.getElementById("signup-button");

      const email = emailField.value.trim();
      if (!email) return;

      try {
        const res = await fetch("subscribe.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ email }),
        });

        if (res.ok) {
          messageElement.classList.add("show");
          showToast("You're on the list. JazakAllah khair!");
          emailField.disabled = true;
          signupBtn.disabled = true;
          signupBtn.classList.add("btn-disabled");
        } else {
          showToast(await serverError(res, "Please enter a valid email address."), "error");
        }
      } catch (err) {
        showToast("Network error — please try again.", "error");
      } finally {
        this.reset();
      }
    });

  window.showToast = showToast;
})();