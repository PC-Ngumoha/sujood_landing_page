<?php
declare(strict_types=1);

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function readCsv(string $name, array $keys): array {
    $path = __DIR__ . '/data/' . $name . '.csv';
    $out = [];
    if (!file_exists($path) || !($h = fopen($path, 'r'))) {
        return $out;
    }
    fgetcsv($h, null, ',', '"', '"');
    while (($r = fgetcsv($h, null, ',', '"', '"')) !== false) {
        if (empty($r[0])) {
            continue;
        }
        $row = [];
        foreach ($keys as $i => $k) {
            $row[$k] = $r[$i] ?? '';
        }
        $out[] = $row;
    }
    fclose($h);
    return $out;
}

const MONTHS = ['Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4, 'May' => 5, 'Jun' => 6, 'Jul' => 7, 'Aug' => 8, 'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12];

function sortEvents(array $rows): array {
    usort($rows, function ($a, $b) {
        $am = MONTHS[$a['month']] ?? 99;
        $bm = MONTHS[$b['month']] ?? 99;
        if ($am === $bm) {
            $ad = is_numeric($a['day']) ? (int)$a['day'] : 99;
            $bd = is_numeric($b['day']) ? (int)$b['day'] : 99;
            return $ad <=> $bd;
        }
        return $am <=> $bm;
    });
    return $rows;
}

function socialIcon(string $platform): array {
    $pathMap = [
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1.1" fill="currentColor" stroke="none"/>',
        'twitter'   => '<path d="M17.5 3h3l-7.3 8.3L21.5 21h-6.6l-5.2-6.5L3.7 21H0.7l7.8-8.9L2.5 3h6.8l4.7 5.9L17.5 3Zm-1.2 16.2h1.7L7.8 4.7H6l10.3 14.5Z"/>',
        'facebook'  => '<path d="M13.5 21v-7.5h2.5l.4-3h-2.9V8.4c0-.87.24-1.46 1.5-1.46h1.6V4.3C16.3 4.2 15.4 4.1 14.4 4.1c-2.4 0-4 1.46-4 4.15v2.35H7.9v3h2.5V21h3.1Z"/>',
        'tiktok'    => '<path d="M16.5 3c.3 1.9 1.6 3.4 3.5 3.7v2.6c-1.3 0-2.6-.4-3.5-1.1v6.6c0 3-2.4 5.2-5.3 5.2-3 0-5.3-2.3-5.3-5.2 0-2.9 2.4-5.2 5.3-5.2.4 0 .8 0 1.1.1v2.7c-.3-.1-.7-.2-1.1-.2-1.5 0-2.6 1.1-2.6 2.6 0 1.4 1.2 2.6 2.6 2.6 1.5 0 2.7-1.1 2.7-2.6V3h2.6Z"/>',
        'whatsapp'  => '<path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5.1-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1-.2.2-.7.8-.8 1-.2.2-.3.2-.5.1-.2-.1-1-.4-1.9-1.2-.7-.6-1.2-1.4-1.3-1.6-.1-.2 0-.4.1-.5.1-.1.2-.3.4-.4.1-.1.2-.2.2-.4.1-.2 0-.3 0-.4-.1-.1-.6-1.4-.8-1.9-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.6.3-.2.2-.8.8-.8 1.9 0 1.1.8 2.2.9 2.4.1.2 1.6 2.5 4 3.5.6.2 1 .4 1.3.5.6.2 1.1.1 1.5.1.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.1-1.2-.1-.1-.2-.2-.4-.3Z"/>',
    ];
    $labelMap = [
        'instagram' => 'Instagram', 'twitter' => 'X / Twitter', 'facebook' => 'Facebook',
        'tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp',
    ];
    return [
        'icon'  => $pathMap[$platform] ?? '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4" fill="currentColor" stroke="none"/>',
        'label' => $labelMap[$platform] ?? ucfirst($platform),
    ];
}

$reviews = array_reverse(readCsv('reviews', ['rating', 'name', 'review', 'submitted_at']));
$events  = sortEvents(readCsv('events', ['name', 'day', 'month', 'location', 'time', 'tag']));
$socials = readCsv('socials', ['platform', 'url']);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Between Sujood & Strategy — Rahmah Aderinoye</title>
    <meta name="description" content="Between Sujood & Strategy by Rahmah Aderinoye — where faith meets ambition. Available now on Amazon and Selar." />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link
      href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,500;0,9..144,600;1,9..144,500&family=Inter:wght@400;500;600&family=Space+Grotesk:wght@400;500;600&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="assets/css/style.css" />
  </head>
  <body>
    <nav class="spine" aria-label="Section navigation">
      <a class="spine-dot" data-target="#about" title="The book">I</a>
      <a class="spine-dot" data-target="#author" title="About the author">II</a>
      <a class="spine-dot" data-target="#buy" title="Where to buy">III</a>
      <a class="spine-dot" data-target="#reviews" title="Reviews">IV</a>
      <a class="spine-dot" data-target="#gift" title="Gift it forward">V</a>
      <a class="spine-dot" data-target="#events" title="Events">VI</a>
      <a class="spine-dot" data-target="#newsletter" title="Stay in touch"
        >VII</a
      >
    </nav>

    <main>
      <!-- HERO -->
      <header class="hero" id="top">
        <div class="wrap hero-grid">
          <div>
            <p class="eyebrow">A Book by Rahmah Aderinoye — Out Now</p>
            <h1>Between Sujood<br />&amp; <em>Strategy</em></h1>
            <p class="hero-tagline">Where faith meets ambition</p>
            <p class="hero-sub">
              What does it mean to build a life that is both deeply ambitious
              and deeply surrendered to Allah? Rahmah Aderinoye writes from
              inside the life she has actually lived — building an agribusiness
              across Nigeria, leading thousands of farmers, raising children,
              and staying anchored in faith while doing all of it at once.
            </p>
            <div class="btn-row">
              <a href="#buy" class="btn btn-primary">Get the book</a>
              <a href="#newsletter" class="btn btn-ghost"
                >Join the mailing list</a
              >
            </div>
          </div>
          <div class="cover-stage">
            <div class="cover-frame">
              <img
                src="assets/images/cover.jpeg"
                alt="Between Sujood & Strategy — book cover"
              />
            </div>
          </div>
        </div>
      </header>

      <div class="wrap"><div class="divider"></div></div>

      <!-- CHAPTER I — ABOUT THE BOOK -->
      <section id="about">
        <div class="wrap">
          <p class="eyebrow">Chapter I</p>
          <div class="section-head">
            <h2>About the book</h2>
          </div>
          <div class="about-body">
            <div class="about-text">
              <p>
                What does it mean to build a life that is both deeply ambitious
                and deeply surrendered to Allah?
              </p>
              <p>
                In
                <em style="font-family: var(--serif)"
                  >Between Sujood and Strategy</em
                >, Rahmah Aderinoye writes from inside the life she has actually
                lived — building an agribusiness across Nigeria, leading
                thousands of farmers, raising children, and trying to remain
                anchored in faith while doing all of it at once. These are not
                lessons drawn from theory. They are drawn from supply chains
                that broke, decisions made at 3am, integrity tested by profit,
                and prayers said in the space between.
              </p>
              <p>
                This is not a book about choosing between dunya and deen, family
                and ambition, or prayer and planning. It is about learning to
                hold them together — with discipline, with trust in Allah, and
                without apology.
              </p>
              <p>
                She writes about ethical leadership and what it costs. About
                motherhood inside boardrooms, and boardrooms that don't make
                room for mothers. About the specific loneliness of building
                something, the temptation to compromise, and the surprising
                peace that comes from refusing to.
              </p>
              <p>
                Between Sujood and Strategy is grounded in Islamic faith and
                written for anyone who has ever tried to build something
                meaningful without losing what they believe in the process. For
                every woman who has been told, in some form or another, that her
                faith and her ambition cannot occupy the same space — this book
                is proof that they can. That they must. That one without the
                other was never the full life she was meant to live.
              </p>
            </div>
            <ul class="fact-list">
              <li>
                <div class="fact-label">Author</div>
                <div class="fact-value">Rahmah Aderinoye</div>
              </li>
              <li>
                <div class="fact-label">Genre</div>
                <div class="fact-value">
                  Faith, Entrepreneurship, Leadership & Self-Help
                </div>
              </li>
              <li>
                <div class="fact-label">Grounded in</div>
                <div class="fact-value">Islamic faith</div>
              </li>
              <li>
                <div class="fact-label">Written for</div>
                <div class="fact-value">
                  Muslimah women, mothers, and founders building an ambitious
                  life and business without leaving their faith behind
                </div>
              </li>
            </ul>
          </div>
        </div>
      </section>
      <div class="wrap"><div class="divider"></div></div>

      <!-- CHAPTER II — ABOUT THE AUTHOR -->
      <section id="author">
        <div class="wrap">
          <p class="eyebrow">Chapter II</p>
          <div class="section-head">
            <h2>About the author</h2>
          </div>
          <div class="author-body">
            <div class="author-photo">
              <img
                src="assets/images/author.jpeg"
                alt="Rahmah Aderinoye"
              />
            </div>
            <div class="author-text">
              <p>
                Rahmah Aderinoye is a social entrepreneur, author, and the
                Founder and CEO of Rashak Group, a diversified agribusiness
                group working to build sustainable food systems across Africa.
                Her work spans agricultural production, commodity aggregation,
                grain processing, food manufacturing, and cold chain logistics.
              </p>
              <p>
                Through its subsidiaries — Rashak Farms & Agro-Allied Limited,
                RA Mills, Rashak Foods, and The Cold Place — the Group addresses
                some of agriculture's most persistent problems: post-harvest
                losses, broken supply chains, and constrained market access for
                smallholder farmers.
              </p>
              <p>
                Central to her work is a commitment to smallholder farmers,
                particularly women and youth, built on ethical financing,
                agricultural training, and direct market access. Under her
                leadership, Rashak Group has impacted thousands of farmers
                across Nigeria while building agricultural systems designed for
                long-term commercial viability and food security.
              </p>
              <p>
                Driven by the belief that life is not meant to be lived in
                fragments, Rahmah's approach to leadership integrates faith,
                strategy, and service — a philosophy this book embodies from the
                first page to the last.
              </p>
              <p>
                Her background spans science, public health, and enterprise
                development, shaping a leadership style built on systems
                thinking, integrity, and the willingness to make decisions in
                conditions that don't offer certainty. Her work has been
                recognised locally and internationally, including the Resolution
                Project Fellowship at the United Nations, the Acumen West Africa
                Fellowship, and a Top 20 African Business Hero nomination.
              </p>
              <p>
                In
                <em style="font-family: var(--serif)"
                  >Between Sujood and Strategy</em
                >, Rahmah writes not as a theorist but as someone who has lived
                these realities — leadership, motherhood, faith, and the cost of
                building something meaningful — without the benefit of a clean
                separation between any of them. The book asks a question she has
                had to answer in real conditions: how do you pursue what you are
                capable of without losing what you believe? What she has found,
                and what she shares here, is that the two were never actually in
                conflict.
              </p>
              <p>
                Rahmah is a wife and mother. She lives in Nigeria and believes,
                without apology, that the women in the room change what the room
                decides. She believes that leadership is an amanah — a trust —
                and that professional excellence, pursued with the right
                intention, can itself become an act of worship.
              </p>
            </div>
          </div>
        </div>
      </section>
      <div class="wrap"><div class="divider"></div></div>

      <!-- CHAPTER III — WHERE TO FIND IT -->
      <section id="buy">
        <div class="wrap">
          <p class="eyebrow">Chapter III</p>
          <div class="section-head">
            <h2>Where to find it</h2>
            <p>Available as a physical copy, wherever you like to shop.</p>
          </div>
          <div class="buy-grid">
            <a
              class="buy-card"
              href="https://www.amazon.com/Between-Sujood-Strategy-Where-Ambition/dp/9786890299/ref=sr_1_1?dib=eyJ2IjoiMSJ9.oZM2i_un4jn4mvgufiAfpw.kuHEE64q86fogDzA6NFDZX_UJ_8HX-b2W44GKVWVhzw&dib_tag=se&keywords=sujood+and+strategy&qid=1787048162&s=amazon-devices&sr=1-1"
              target="_blank"
              rel="noopener"
            >
              <div class="buy-top">
                <div>
                  <div class="buy-name">Amazon</div>
                  <div class="buy-format">Hardback only</div>
                </div>
                <span class="buy-arrow">↗</span>
              </div>
            </a>
            <a
              class="buy-card"
              href="https://selar.com/2to86u7772"
              target="_blank"
              rel="noopener"
            >
              <div class="buy-top">
                <div>
                  <div class="buy-name">Selar</div>
                  <div class="buy-format">Physical copy only</div>
                </div>
                <span class="buy-arrow">↗</span>
              </div>
            </a>
          </div>
        </div>
      </section>
      <div class="wrap"><div class="divider"></div></div>

      <!-- CHAPTER IV — REVIEWS -->
      <section id="reviews">
        <div class="wrap">
          <p class="eyebrow">Chapter IV</p>
          <div class="section-head">
            <h2>What readers are saying</h2>
            <p>Real words from real readers — and yes, that includes yours.</p>
          </div>
          <div class="review-layout">
            <div class="review-stack">
              <div class="review-list" id="review-list">
                <?php if ($reviews === []): ?>
                  <p class="empty-reviews">Be the first to share your thoughts on the book.</p>
                <?php else: foreach ($reviews as $r): ?>
                  <div class="review-card">
                    <div class="stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></div>
                    <div class="review-quote">&ldquo;<?= e($r['review']) ?>&rdquo;</div>
                    <div class="review-meta"><?= e($r['name']) ?></div>
                  </div>
                <?php endforeach; endif; ?>
              </div>
              <div class="review-nav" id="review-nav" hidden>
                <button class="btn btn-ghost" type="button" id="review-prev">← Prev</button>
                <button class="btn btn-ghost" type="button" id="review-next">Next →</button>
              </div>
            </div>

            <form class="review-form" id="review-form">
              <input type="text" name="website" id="review-hp" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true">
              <h3>Leave a review</h3>
              <p class="form-sub">
                Your review appears in the list above for other visitors to see.
              </p>

              <div class="field">
                <label>Your rating</label>
                <div class="star-picker" id="star-picker">
                  <button type="button" data-val="1">&#9733;</button>
                  <button type="button" data-val="2">&#9733;</button>
                  <button type="button" data-val="3">&#9733;</button>
                  <button type="button" data-val="4">&#9733;</button>
                  <button type="button" data-val="5">&#9733;</button>
                </div>
              </div>

              <div class="field">
                <label for="rv-name">Name</label>
                <input
                  id="rv-name"
                  type="text"
                  placeholder="Jane Doe"
                  required
                />
              </div>

              <div class="field">
                <label for="rv-text">Review</label>
                <textarea
                  id="rv-text"
                  rows="4"
                  placeholder="What did you think of the book?"
                  required
                ></textarea>
              </div>

              <button
                type="submit"
                class="btn btn-primary"
                id="review-button"
                style="width: 100%; justify-content: center"
              >
                Submit review
              </button>
              <p class="form-note" id="review-note">
                Reviews are shown publicly and stored for this page's visitors
                to read.
              </p>
            </form>
          </div>
        </div>
      </section>
      <div class="wrap"><div class="divider"></div></div>

      <!-- CHAPTER V — GIFT IT FORWARD -->
      <section id="gift">
        <div class="wrap">
          <p class="eyebrow">Chapter V</p>
          <div class="section-head">
            <h2>Gift it forward</h2>
            <p>
              Send a copy to someone who needs it — someone you know, or someone
              you don't.
            </p>
          </div>

          <form class="gift-card" id="gift-form">
            <input type="text" name="website" id="gift-hp" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true">
            <div class="gift-toggle" id="gift-toggle" role="tablist">
              <button
                type="button"
                class="gift-option active"
                data-mode="known"
              >
                Gift someone I know
              </button>
              <button type="button" class="gift-option" data-mode="surprise">
                Surprise a stranger
              </button>
            </div>

            <p class="gift-hint" id="gift-hint">
              Tell us who the book is for, and we'll take care of the rest.
            </p>

            <div class="gift-grid">
              <div class="field">
                <label for="gift-from-name">Your name</label>
                <input
                  id="gift-from-name"
                  type="text"
                  placeholder="Your name"
                  required
                />
              </div>
              <div class="field">
                <label for="gift-from-email">Your email</label>
                <input
                  id="gift-from-email"
                  type="email"
                  placeholder="you@email.com"
                  required
                />
              </div>

              <div class="field gift-recipient-field">
                <label for="gift-to-name">Recipient's name</label>
                <input
                  id="gift-to-name"
                  type="text"
                  placeholder="Who is this for?"
                />
              </div>
              <div class="field gift-recipient-field">
                <label for="gift-to-contact">Recipient's email or phone</label>
                <input
                  id="gift-to-contact"
                  type="text"
                  placeholder="Where should we send it?"
                />
              </div>

              <div class="field" style="grid-column: 1 / -1">
                <label for="gift-message" id="gift-message-label"
                  >Personal message (optional)</label
                >
                <textarea
                  id="gift-message"
                  rows="3"
                  placeholder="Add a short note to go with the gift"
                ></textarea>
              </div>
            </div>

            <button
              type="submit"
              class="btn btn-primary"
              id="gift-button"
              style="width: 100%; justify-content: center"
            >
              Send a gift
            </button>
            <p class="form-note">Fill in the details above</p>
          </form>
        </div>
      </section>
      <div class="wrap"><div class="divider"></div></div>

      <!-- CHAPTER VI — EVENTS -->
      <section id="events">
        <div class="wrap">
          <p class="eyebrow">Chapter VI</p>
          <div class="section-head">
            <h2>Upcoming events</h2>
            <p>
              Launch dates, the physical event, and a live conversation — join
              the mailing list below and new dates land in your inbox first.
            </p>
          </div>
          <div class="event-list">
            <?php if ($events === []): ?>
              <p class="empty-reviews">New dates will be announced soon — join the mailing list to hear first.</p>
            <?php else: foreach ($events as $ev): ?>
              <div class="event">
                <div class="event-date">
                  <span class="day"><?= e($ev['day']) ?></span><span class="month"><?= e($ev['month']) ?></span>
                </div>
                <div>
                  <div class="event-name"><?= e($ev['name']) ?></div>
                  <div class="event-loc"><?= e($ev['location']) ?><?= $ev['time'] !== '' ? ' — ' . e($ev['time']) : '' ?></div>
                </div>
                <span class="event-tag"><?= e($ev['tag']) ?></span>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </section>
      <div class="wrap"><div class="divider"></div></div>

      <!-- CHAPTER VII — NEWSLETTER -->
      <section id="newsletter">
        <div class="wrap">
          <p class="eyebrow">Chapter VII</p>
          <div class="newsletter">
            <div>
              <h2>Get the next chapter first</h2>
              <p>
                Join the mailing list for new event dates, excerpts, and news
                about the book — sent occasionally, never spam.
              </p>
            </div>
            <div>
              <form class="signup-form" id="signup-form">
                <input
                  type="text"
                  name="website"
                  id="signup-hp"
                  class="hp-field"
                  tabindex="-1"
                  autocomplete="off"
                  aria-hidden="true"
                />
                <input
                  type="email"
                  id="signup-email"
                  placeholder="you@email.com"
                  required
                />
                <button type="submit" class="btn" id="signup-button">
                  Sign up
                </button>
              </form>
              <p class="signup-message" id="signup-message">
                ✓ You're on the list ! Thanks.
              </p>
            </div>
          </div>
        </div>
      </section>
      <footer>
        <div class="wrap">
          <p class="eyebrow">Stay close</p>
          <h2 style="font-size: 28px">Follow the story between chapters</h2>
          <div class="social-row">
            <?php foreach ($socials as $sc): $si = socialIcon($sc['platform']); ?>
              <a
                class="social-pill"
                href="<?= e($sc['url']) ?>"
                target="_blank"
                rel="noopener"
              >
                <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.6"
                ><?= $si['icon'] ?></svg>
                <?= e($si['label']) ?>
              </a>
            <?php endforeach; ?>
          </div>
          <div class="footer-bottom">
            <span>© <?= date('Y') ?> Between Sujood & Strategy. All rights reserved.</span>
            <span>Rahmah Aderinoye</span>
          </div>
        </div>
      </footer>
    </main>
    <div id="toast-container" class="toast-container" aria-live="polite"></div>
    <script src="assets/js/main.js"></script>
  </body>
</html>