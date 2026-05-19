<?php
$pageTitle = 'About Us';
require_once 'includes/auth.php';
include 'includes/header.php';
?>

<style>
.about-hero {
    padding: 4rem 3rem;
    display: flex;
    align-items: stretch;
    align-items: center;
    justify-content: center;
    gap: 2.5rem;
    background: linear-gradient(135deg, #FFF8F0, #FAF0E6);
    min-height: 420px;
}
.about-img-wrap {
    flex-shrink: 0;
    width: 340px;
}

.about-img-wrap img {
    width: 420px;
    height: 420px;
    object-fit: contain;
    transform: rotate(-8deg);
    filter: drop-shadow(0 20px 40px rgba(0,0,0,0.15));
    transition: transform 0.4s ease;
}

.about-img-wrap img:hover {
    transform: rotate(-4deg) scale(1.03);
}

.about-text-card {
    background: white;
    border-radius: 20px;
    padding: 2rem 2.5rem;
    box-shadow: var(--shadow-lg);
    max-width: 520px;
    flex: 1 1 420px;
}

.about-text-card h1 {
    font-family: 'Playfair Display', serif;
    font-size: 1.7rem; font-weight: 800;
    margin-bottom: 1rem;
    color: var(--charcoal);
}

.about-text-card h1 span { color: var(--orange); }

.about-text-card p {
    font-size: 0.9rem;
    color: var(--gray);
    line-height: 1.8;
    margin-bottom: 0.6rem;
}

.about-divider {
    width: 40px; height: 3px;
    background: var(--orange);
    border-radius: 2px;
    margin-bottom: 1rem;
}

/* Opening Hours Card */
.hours-card {
    background: white;
    border-radius: 20px;
    padding: 2rem 2.2rem;
    box-shadow: var(--shadow-lg);
    flex-shrink: 0;
    min-width: 260px;
    align-self: center;
}
.hours-card h2 {
    font-family: 'Playfair Display', serif;
    font-size: 1.2rem; font-weight: 800;
    color: var(--charcoal);
    text-align: center;
    letter-spacing: 1px;
    margin-bottom: 1.2rem;
    padding-bottom: 0.8rem;
    border-bottom: 2px solid var(--border);
    text-transform: uppercase;
}
.hours-list { list-style: none; padding: 0; margin: 0; }
.hours-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.55rem 0;
    border-bottom: 1px solid #f3f3f3;
    font-size: 0.9rem;
    gap: 1rem;
}
.hours-list li:last-child { border-bottom: none; }
.hours-day {
    font-weight: 700;
    color: var(--charcoal);
    min-width: 90px;
}
.hours-time { color: var(--gray); font-size: 0.88rem; white-space: nowrap; }
.hours-closed {
    color: #e53e3e; font-weight: 700;
    font-size: 0.82rem; letter-spacing: 0.5px; text-transform: uppercase;
}
.hours-dot {
    width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
}
.dot-open  { background: #22c55e; }
.dot-closed { background: #e53e3e; }

@media (max-width: 1000px) {
    .about-hero { flex-wrap: wrap; justify-content: center; }
    .about-text-card { max-width: 100%; }
    .hours-card { min-width: 240px; }
}

.reviews-section {
    padding: 4rem 3rem;
    text-align: center;
    overflow: hidden;
}
.reviews-section h2 {
    font-family: 'Playfair Display', serif;
    font-size: 2rem; font-weight: 800;
    margin-bottom: 0.5rem;
}
.reviews-sub {
    color: var(--gray); font-size: 0.95rem; margin-bottom: 2.5rem;
}
.no-reviews {
    color: var(--gray); font-size: 0.95rem; padding: 2rem;
}

/* Ticker */
.ticker-outer {
    overflow: hidden;
    width: 100%;
    position: relative;
}
.ticker-outer::before,
.ticker-outer::after {
    content: '';
    position: absolute; top: 0; bottom: 0; width: 80px;
    z-index: 2; pointer-events: none;
}
.ticker-outer::before {
    left: 0;
    background: linear-gradient(to right, #FFF8F0, transparent);
}
.ticker-outer::after {
    right: 0;
    background: linear-gradient(to left, #FFF8F0, transparent);
}

.ticker-track {
    display: flex;
    gap: 1.5rem;
    width: max-content;
    animation: scrollLeft 40s linear infinite;
}
.ticker-track:hover { animation-play-state: paused; }

@keyframes scrollLeft {
    0%   { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}

.review-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem 1.8rem;
    min-width: 280px; max-width: 300px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    text-align: left;
    flex-shrink: 0;
    transition: box-shadow 0.2s;
}
.review-card:hover { box-shadow: var(--shadow-lg); }
.rc-stars {
    color: #f59e0b; font-size: 1.2rem;
    margin-bottom: 0.6rem; letter-spacing: 2px;
}
.rc-comment {
    font-size: 0.9rem; color: var(--charcoal);
    line-height: 1.6; margin-bottom: 0.8rem;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.rc-author {
    font-weight: 700; font-size: 0.85rem;
    color: var(--orange);
}
.rc-date {
    font-size: 0.75rem; color: var(--gray); margin-top: 2px;
}
</style>

<section class="about-hero">
    <div class="about-img-wrap">
        <img src="/foodbyte/uploads/burger.png" alt="Food">
    </div>

    <div class="about-text-card">
        <h1>About <span>FoodByte</span></h1>
        <div class="about-divider"></div>
        <p>At FoodByte, we believe great food should come to you fast, fresh, and without the fuss — connecting food lovers with the best local restaurants every day.</p>
        <p>We partner with carefully selected restaurants to make sure every order feels like it was made just for you. Our riders are real people committed to getting your meal to your door warm and on time.</p>
        <p>Whether ordering for one or feeding a crowd, FoodByte makes every meal effortless.</p>
    </div>

    <div class="hours-card">
        <h2>Opening Hours</h2>
        <ul class="hours-list">
            <li>
                <span class="hours-day">Sunday</span>
                <span class="hours-time">9 am – 6 pm</span>
                <span class="hours-dot dot-open"></span>
            </li>
            <li>
                <span class="hours-day">Monday</span>
                <span class="hours-time">9 am – 6 pm</span>
                <span class="hours-dot dot-open"></span>
            </li>
            <li>
                <span class="hours-day">Tuesday</span>
                <span class="hours-time">9 am – 6 pm</span>
                <span class="hours-dot dot-open"></span>
            </li>
            <li>
                <span class="hours-day">Wednesday</span>
                <span class="hours-time">9 am – 6 pm</span>
                <span class="hours-dot dot-open"></span>
            </li>
            <li>
                <span class="hours-day">Thursday</span>
                <span class="hours-time">9 am – 6 pm</span>
                <span class="hours-dot dot-open"></span>
            </li>
            <li>
                <span class="hours-day">Friday</span>
                <span class="hours-time">10 am – 3 pm</span>
                <span class="hours-dot dot-open"></span>
            </li>
            <li>
                <span class="hours-day">Saturday</span>
                <span class="hours-closed">Closed</span>
                <span class="hours-dot dot-closed"></span>
            </li>
        </ul>
    </div>
</section>

<?php
require_once 'includes/db.php';
$stmt = $pdo->query("
    SELECT f.rating, f.comment, f.created_at, u.username
    FROM feedback f
    JOIN users u ON f.user_id = u.id
    ORDER BY f.created_at DESC
    LIMIT 50
");
$allFeedbacks = $stmt->fetchAll();
?>

<section class="reviews-section">
    <h2>Why Choose FoodByte?</h2>
    <p class="reviews-sub">Here's what our customers say</p>

    <?php if (empty($allFeedbacks)): ?>
    <div class="no-reviews">No reviews yet — be the first to share your experience!</div>
    <?php else: ?>
    <div class="ticker-outer">
        <div class="ticker-track" id="tickerTrack">
            <?php foreach ($allFeedbacks as $fb):
                $stars = str_repeat('★', $fb['rating']) . str_repeat('☆', 5 - $fb['rating']);
            ?>
            <div class="review-card">
                <div class="rc-stars"><?= $stars ?></div>
                <p class="rc-comment">"<?= htmlspecialchars($fb['comment']) ?>"</p>
                <div class="rc-author">— <?= htmlspecialchars($fb['username']) ?></div>
                <div class="rc-date"><?= date('M j, Y', strtotime($fb['created_at'])) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
    (function() {
        const track = document.getElementById('tickerTrack');
        const screenWidth = window.innerWidth;
        const originalHTML = track.innerHTML;
        // Calculate how wide the original set of cards is
        // Each card ~316px (300px + 1.5rem gap). Keep cloning until we have at least 2x screen width.
        let totalWidth = track.scrollWidth;
        let copies = 1;
        while (totalWidth * copies < screenWidth * 2.5) {
            copies++;
        }
        // We already have 1 copy, add the rest
        for (let i = 1; i < copies; i++) {
            track.insertAdjacentHTML('beforeend', originalHTML);
        }
        // Animate: translate by 1/copies of total width so it loops perfectly
        const style = document.createElement('style');
        style.textContent = `
            @keyframes scrollLeft {
                0%   { transform: translateX(0); }
                100% { transform: translateX(-${(100 / copies).toFixed(4)}%); }
            }
        `;
        document.head.appendChild(style);
    })();
    </script>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
