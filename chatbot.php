<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ---------- Only accept POST ----------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

// ---------- Read message (form or JSON) -----------------------------
$message = '';
if (isset($_POST['message'])) {
    $message = (string)$_POST['message'];
} else {
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $j = json_decode($raw, true);
        if (is_array($j) && isset($j['message'])) {
            $message = (string)$j['message'];
        }
    }
}

$message = trim($message);
if ($message === '') {
    echo json_encode([
        'reply'        => "Tell me what you're craving — try 'something spicy' or 'cheap snack'.",
        'items'        => [],
        'matched_tags' => [],
        'intent'       => 'empty',
    ]);
    exit;
}

// Hard cap on input size — keep the regex/LIKE work bounded
if (mb_strlen($message) > 200) {
    $message = mb_substr($message, 0, 200);
}

$lower = mb_strtolower($message);

// ---------- Greeting / help intents ---------------------------------
if (preg_match('/\b(hi|hello|hey|yo|namaste|hola)\b/u', $lower)) {
    echo json_encode([
        'reply'        => "Hey! I can recommend dishes. Try 'something spicy', "
                        . "'a sweet drink', or 'cheap snack'.",
        'items'        => [],
        'matched_tags' => [],
        'intent'       => 'greet',
    ]);
    exit;
}
if (preg_match('/\b(help|what can you do|how (do|does) (this|it) work)\b/u', $lower)) {
    echo json_encode([
        'reply'        => "Tell me a vibe — flavour ('spicy', 'sweet', 'creamy'), "
                        . "size ('light', 'filling'), price ('cheap', 'premium'), "
                        . "or a category ('drink', 'dessert'). I'll suggest items.",
        'items'        => [],
        'matched_tags' => [],
        'intent'       => 'help',
    ]);
    exit;
}

$SYNONYMS = [
    // flavour
    'spicy'      => ['spicy', 'fiery', 'kick', 'heat', 'chilli', 'chili', 'peppery', 'masala'],
    'sweet'      => ['sweet', 'sugary', 'sugar'],
    'savory'     => ['savory', 'savoury', 'salty'],
    'creamy'     => ['creamy', 'smooth', 'rich'],
    'crispy'     => ['crispy', 'crunchy', 'crisp'],
    'cheesy'     => ['cheesy', 'cheese', 'mozzarella'],

    // cooking method
    'fried'      => ['fried', 'deep fried', 'deep-fried'],
    'grilled'    => ['grilled', 'barbecued', 'bbq', 'barbecue'],
    'baked'      => ['baked', 'oven baked', 'oven-baked'],

    // temperature / form
    'cold'       => ['cold', 'chilled', 'iced', 'cool'],
    'drink'      => ['drink', 'beverage', 'thirsty', 'something to drink'],
    'dessert'    => ['dessert', 'pudding', 'after meal', 'sweet dish'],

    // diet
    // Kept tight: 'veg' alone false-positives on "non-veg" (the hyphen is
    // a regex word boundary). Negation phrases handled in the pass below.
    'vegetarian' => ['vegetarian', 'plant based', 'plant-based'],
    'vegan'      => ['vegan'],
    'chicken'    => ['chicken', 'poultry'],
    'meat'       => ['meat', 'meaty', 'non veg', 'non-veg', 'nonveg'],

    // size / appetite
    'light'      => ['light', 'small', 'snack', 'starter', 'appetizer', 'appetiser', 'not heavy'],
    'filling'    => ['filling', 'heavy', 'hearty', 'big', 'large', 'full meal', 'main course', 'main'],

    // price (also handled numerically below)
    'cheap'      => ['cheap', 'affordable', 'budget', 'inexpensive', 'low price'],
    'premium'    => ['premium', 'expensive', 'fancy', 'high end', 'high-end'],
    'midrange'   => ['mid range', 'mid-range', 'midrange', 'moderate price'],

    // misc
    'refreshing' => ['refreshing', 'thirst quenching', 'thirst-quenching'],
    'healthy'    => ['healthy', 'low calorie', 'low-cal', 'lean'],
];

// ---------- Detect tags via whole-word regex ------------------------
$matchedTags = [];
foreach ($SYNONYMS as $tagSlug => $phrases) {
    foreach ($phrases as $phrase) {
        // \b...\b doesn't behave well around hyphens, but our phrases use
        // either single words or spaces — fine. Escape just in case.
        $pattern = '/\b' . preg_quote($phrase, '/') . '\b/iu';
        if (preg_match($pattern, $lower)) {
            $matchedTags[$tagSlug] = true;
            break;  // one synonym hit per tag is enough
        }
    }
}

// Numeric price hint: "under 300", "below 500", "less than 250"
$priceCap = null;
if (preg_match('/\b(?:under|below|less than|cheaper than)\s+(\d{2,5})\b/iu', $lower, $m)) {
    $priceCap = (int)$m[1];
}

$matchedTags = array_keys($matchedTags);

// ---------- Negation pass -------------------------------------------
// "no chicken", "not spicy", "non-veg", "without cheese" should remove
// the corresponding tag from results.  Also: "no chicken"/"no meat" ->
// implies vegetarian (a positive add).
$negatedTags = [];
if (preg_match_all('/\b(?:no|not|non|without)[\s-]+([a-z]+)\b/iu', $lower, $negs)) {
    foreach ($negs[1] as $afterNeg) {
        // Find which canonical tag this negated word maps to
        foreach ($SYNONYMS as $tagSlug => $phrases) {
            foreach ($phrases as $phrase) {
                // Compare against single-word phrases only — "no main course"
                // is too ambiguous to handle here without parser-level logic.
                if (mb_strtolower($phrase) === mb_strtolower($afterNeg)) {
                    $negatedTags[$tagSlug] = true;
                    // "no meat" / "no chicken" implies vegetarian
                    if ($tagSlug === 'meat' || $tagSlug === 'chicken') {
                        $matchedTags[] = 'vegetarian';
                    }
                    break 2;
                }
            }
        }
    }
}
if (!empty($negatedTags)) {
    $matchedTags = array_values(array_diff(array_unique($matchedTags), array_keys($negatedTags)));
}

// ---------- Tier 1: tag-based recommendation ------------------------
$items = [];
if (!empty($matchedTags)) {
    $items = recommendByTags($pdo, $matchedTags, $priceCap, 4);
}

// ---------- Tier 2: keyword search on name/description --------------
if (empty($items)) {
    $items = recommendByKeyword($pdo, $message, $priceCap, 4);
}

// ---------- Tier 3: refuse if still nothing -------------------------
if (empty($items)) {
    echo json_encode([
        'reply'        => "I couldn't find a match for that. I only recommend "
                        . "dishes from our menu — try a flavour ('spicy', 'sweet'), "
                        . "a category ('drink', 'dessert'), or browse the full menu.",
        'items'        => [],
        'matched_tags' => $matchedTags,
        'intent'       => 'refuse',
    ]);
    exit;
}

// ---------- Build success reply -------------------------------------
$replyBits = [];
if (!empty($matchedTags)) {
    $replyBits[] = "Here's what fits " . humanList($matchedTags);
} else {
    $replyBits[] = "Here's what I found";
}
if ($priceCap !== null) {
    $replyBits[] = "under Rs. {$priceCap}";
}
$reply = implode(' ', $replyBits) . ':';

echo json_encode([
    'reply'        => $reply,
    'items'        => $items,
    'matched_tags' => $matchedTags,
    'intent'       => 'recommend',
]);
exit;


/* =====================================================================
 * Helpers
 * ===================================================================== */

/**
 * Score items by how many of the requested tags they have.
 * Returns top $limit items, sorted by overlap DESC then id ASC.
 */
function recommendByTags(PDO $pdo, array $tagSlugs, ?int $priceCap, int $limit): array
{
    if (empty($tagSlugs)) return [];

    // Build IN(?, ?, ?) placeholder list dynamically
    $placeholders = implode(',', array_fill(0, count($tagSlugs), '?'));
    $params       = $tagSlugs;

    $sql = "
        SELECT i.id, i.name, i.description, i.price, i.image,
               COUNT(DISTINCT t.id) AS overlap
        FROM items i
        JOIN item_tags it ON it.item_id = i.id
        JOIN tags t       ON t.id = it.tag_id
        WHERE t.slug IN ($placeholders)
    ";
    if ($priceCap !== null) {
        $sql       .= " AND i.price <= ?";
        $params[]   = $priceCap;
    }
    $sql .= "
        GROUP BY i.id, i.name, i.description, i.price, i.image
        ORDER BY overlap DESC, i.id ASC
        LIMIT $limit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('shapeItem', $stmt->fetchAll());
}

/**
 * Fallback: LIKE on name/description using meaningful tokens from the
 * message. Escapes %, _ and \ so user input can't hijack patterns.
 */
function recommendByKeyword(PDO $pdo, string $message, ?int $priceCap, int $limit): array
{
    // Stopwords we skip when picking search tokens
    $stop = array_flip([
        'i','me','my','a','an','the','is','am','are','was','were','be',
        'do','does','did','have','has','had','want','need','give','show',
        'get','find','please','some','something','anything','any','for',
        'with','of','to','in','on','at','and','or','but','what','which',
        'how','can','you','recommend','suggest','recommendation'
    ]);

    // Take alphabetic tokens of length >= 3
    preg_match_all('/[a-z]{3,}/iu', mb_strtolower($message), $m);
    $tokens = [];
    foreach ($m[0] as $tok) {
        if (!isset($stop[$tok])) $tokens[$tok] = true;
    }
    $tokens = array_keys($tokens);
    if (empty($tokens)) return [];

    // Build OR of LIKE clauses, escaping LIKE metacharacters
    $likeParts = [];
    $params    = [];
    foreach ($tokens as $tok) {
        $escaped = addcslashes($tok, '\\%_');
        $likeParts[] = "(i.name LIKE ? OR i.description LIKE ?)";
        $params[]    = "%{$escaped}%";
        $params[]    = "%{$escaped}%";
    }
    $where = implode(' OR ', $likeParts);

    $sql = "SELECT id, name, description, price, image
            FROM items i
            WHERE ($where)";
    if ($priceCap !== null) {
        $sql      .= " AND i.price <= ?";
        $params[]  = $priceCap;
    }
    $sql .= " ORDER BY id ASC LIMIT $limit";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('shapeItem', $stmt->fetchAll());
}

/** Trim DB row down to the fields the client needs. */
function shapeItem(array $row): array
{
    return [
        'id'          => (int)$row['id'],
        'name'        => (string)$row['name'],
        'description' => (string)($row['description'] ?? ''),
        'price'       => (float)$row['price'],
        'image'       => (string)($row['image'] ?? ''),
    ];
}

/** "spicy, cheap and sweet" */
function humanList(array $words): string
{
    if (count($words) === 1) return $words[0];
    $last = array_pop($words);
    return implode(', ', $words) . ' and ' . $last;
}
