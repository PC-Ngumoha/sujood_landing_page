(function () {
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

  // ---- reviews: seed + storage ----
  const seedReviews = [
    {
      name: "Dr Umar",
      rating: 5,
      text: "Congratulations — this is an almost flawless piece of work. I loved this book and pray it has its intended impact, and may Allah continue to bless you. I've just finished reading it word by word, and I must confess: you are blessed, and may Allah bless and reward your parents. I've read many memoirs, but this one is different — unknowingly, we share a lot in common. I also helped my mother sell things at her stall, waking up at 4:30am to get to the bakery and bring back the freshly baked bread. Beyond the spiritual dimension of the entrepreneurship journey, what this book teaches so well is leadership and governance.",
    },
    {
      name: "Zainab",
      rating: 5,
      text: "A beautiful memoir, wonderfully written and ignited by the author's love for business and Islam. She offers a brilliant take on being an honest business person, as encouraged by Islam. I love its directness, and how the author has conveyed the beauty of marrying Islam with all facets of our lives.",
    },
    // add more reviews here in the same format: {name:"", rating:5, text:""}
  ];

  const listEl = document.getElementById("review-list");
  const noteEl = document.getElementById("review-note");

  function starString(n) {
    return "★★★★★".slice(0, n) + "☆☆☆☆☆".slice(0, 5 - n);
  }

  function renderReviews(all) {
    listEl.innerHTML = "";
    all.forEach((r) => {
      const card = document.createElement("div");
      card.className = "review-card";
      card.innerHTML = `
        <div class="stars">${starString(r.rating)}</div>
        <div class="review-quote">"${r.text}"</div>
        <div class="review-meta">${r.name}</div>
      `;
      listEl.appendChild(card);
    });
  }

  async function loadReviews() {
    let stored = [];
    try {
      const res = await window.storage.get("user-reviews", true);
      if (res && res.value) stored = JSON.parse(res.value);
    } catch (e) {
      /* no stored reviews yet */
    }
    renderReviews([...stored, ...seedReviews]);
    return stored;
  }

  let storedReviews = [];
  loadReviews()
    .then((s) => (storedReviews = s))
    .catch(() => renderReviews(seedReviews));

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
        noteEl.textContent = "Please add a name, a rating, and your review.";
        noteEl.style.color = "var(--wine)";
        return;
      }
      const newReview = { name, rating, text };
      storedReviews = [newReview, ...storedReviews];
      renderReviews([...storedReviews, ...seedReviews]);
      try {
        // Attempt to save review
        const res = await fetch("review.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ rating, name, review: text }),
        });

        console.log(res.ok);

        //   // save to browser storage
        //   await window.storage.set(
        //     "user-reviews",
        //     JSON.stringify(storedReviews),
        //     true,
        //   );
      } catch (err) {
        /* storage unavailable — review still shows for this visit */
      }
      this.reset();
      rating = 0;
      starBtns.forEach((b) => b.classList.remove("filled"));
      noteEl.style.color = "var(--wine)";
      noteEl.textContent =
        "Thank you — your review has been submitted successfully.";
      // deactivate fields and buttons
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
  const giftSuccessMessage = document.getElementById("gift-success");
  let giftMode = "known";

  giftSuccessMessage.style.display = "none"; // Make that message invisible

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

      const senderName = document.getElementById("gift-from-name").value;
      const senderEmail = document.getElementById("gift-from-email").value;
      const recipientName = document.getElementById("gift-to-name").value;
      const recipientEmail = document.getElementById("gift-to-contact").value;
      const giftMessage = document.getElementById("gift-message").value;

      const data = {
        sender_name: senderName,
        sender_email: senderEmail,
        recipient_name: recipientName ?? "",
        recipient_email: recipientEmail ?? "",
        gift_message: giftMessage ?? "",
      };

      try {
        const res = await fetch("giftRequest.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(data),
        });

        if (res.ok) {
          giftSuccessMessage.style.display = "block"; // Make it visible again.

          // After 5 seconds, message should disappear
          setTimeout(() => {
            giftSuccessMessage.style.display = "none";
          }, 5 * 1000);
        }
      } catch (err) {
        console.error(err);
      }

      // console.log(data);

      this.reset();
      giftToggle.querySelector('[data-mode="known"]').click();
    });

  // ---- newsletter signup (UI + Backend PHP service) ----
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

        // const data = res.json();

        if (res.ok) {
          document.getElementById("signup-message").classList.add("show"); // Display message

          // Disable fields
          emailField.disabled = true;
          signupBtn.disabled = true;
          signupBtn.classList.add("btn-disabled");
        }
      } catch (err) {
        console.error(err);
      } finally {
        this.reset();
      }
    });
})();
