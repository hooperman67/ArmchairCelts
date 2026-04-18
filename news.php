<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// ---------------- CONFIG ----------------
$news_urls = [
    'https://www.dailyrecord.co.uk/all-about/celtic-fc/?service=rss',
    'https://www.scotsman.com/sport/football/celtic/rss',
    'https://feeds.bbci.co.uk/sport/6d397eab-9d0d-b84a-a746-8062a76649e5/rss.xml',
    'https://www.glasgowtimes.co.uk/sport/celtic/rss/',
    'https://www.theguardian.com/football/celtic/rss',
    'https://news.stv.tv/topic/celtic/feed',
    'https://charity.celticfc.com/feed/',
    'https://www.express.co.uk/posts/rss/67.99/celtic',
    'https://www.footballscotland.co.uk/all-about/celtic-fc?service=rss',
    'https://www.glasgowworld.com/sport/football/celtic/rss',
    'https://www.glasgowlive.co.uk/all-about/celtic-fc/?service=rss'
];

// Words to include/exclude in the title
$includeWords = ['Celtic', 'Bhoys', 'Celts'];
$excludeWords = ['Rangers', 'Russell'];

$defaultImage = "images/craic.webp";
$limit        = 21;
// ----------------------------------------

function safeText(DOMNode $ctx, string $tag): string {
    $nl = $ctx->getElementsByTagName($tag);
    if ($nl->length > 0 && $nl->item(0)) {
        return trim($nl->item(0)->nodeValue);
    }
    return '';
}

function shouldIncludePost(string $title, array $includeWords, array $excludeWords): bool {
    $titleLower = strtolower($title);

    // must contain at least one include word
    $foundInclude = false;
    foreach ($includeWords as $word) {
        if (stripos($titleLower, strtolower($word)) !== false) {
            $foundInclude = true;
            break;
        }
    }
    if (!$foundInclude) return false;

    // must not contain any exclude words
    foreach ($excludeWords as $word) {
        if (stripos($titleLower, strtolower($word)) !== false) {
            return false;
        }
    }
    return true;
}

function parseFeed($url, $defaultImage, $includeWords, $excludeWords) {
    $posts = [];
    $data = @file_get_contents($url);
    if (!$data) return $posts;

    $dom = new DOMDocument;
    @$dom->loadXML($data);
    if (!$dom->documentElement) return $posts;

    $root = $dom->documentElement->tagName;

    // Channel/feed title = source
    $sourceTitle = '';
    if ($root === 'rss') {
        $titles = $dom->getElementsByTagName('title');
        if ($titles->length > 0) {
            $sourceTitle = trim($titles->item(0)->nodeValue);
        }
    } elseif ($root === 'feed') {
        $feedTitle = $dom->getElementsByTagName('title');
        if ($feedTitle->length > 0) {
            $sourceTitle = trim($feedTitle->item(0)->nodeValue);
        }
    }

    // Fallback channel image (HTTPS only)
    $fallbackImage = $defaultImage;
    $channelImage  = $dom->getElementsByTagName('image');
    if ($channelImage->length > 0) {
        $urlNode = $channelImage->item(0)->getElementsByTagName('url');
        if ($urlNode->length > 0) {
            $channelUrl = trim($urlNode->item(0)->nodeValue);
            if (stripos($channelUrl, 'https://') === 0) {
                $fallbackImage = $channelUrl;
            }
        }
    }

    // collect posts
    if ($root === 'rss') {
        foreach ($dom->getElementsByTagName('item') as $item) {
            $post = extractPost($item, $fallbackImage, $defaultImage, false);
            $post['source'] = $sourceTitle;

            if (shouldIncludePost($post['title'], $includeWords, $excludeWords)) {
                $posts[] = $post;
            }
        }
    } elseif ($root === 'feed') {
        foreach ($dom->getElementsByTagName('entry') as $entry) {
            $post = extractPost($entry, $fallbackImage, $defaultImage, true);
            $post['source'] = $sourceTitle;

            if (shouldIncludePost($post['title'], $includeWords, $excludeWords)) {
                $posts[] = $post;
            }
        }
    }

    return $posts;
}

function extractPost($node, $fallbackImage, $defaultImage, $isAtom = false) {
    $post = [
        'title'      => '',
        'link'       => '',
        'description'=> '',
        'pubDate'    => '',
        'image'      => '',
        'mediaText'  => '',
        'source'     => ''
    ];

    if ($isAtom) {
        $post['title'] = safeText($node, 'title');
        // link
        $links = $node->getElementsByTagName('link');
        $linkHref = '';
        for ($i = 0; $i < $links->length; $i++) {
            $rel = $links->item($i)->getAttribute('rel');
            if ($rel === 'alternate' || $rel === '') {
                $linkHref = $links->item($i)->getAttribute('href');
                if ($linkHref) break;
            }
        }
        if (!$linkHref && $links->length > 0) {
            $linkHref = $links->item(0)->getAttribute('href');
        }
        $post['link'] = $linkHref;

        $summary = $node->getElementsByTagName('summary');
        $content = $node->getElementsByTagName('content');
        if ($summary->length > 0) {
            $post['description'] = trim($summary->item(0)->nodeValue);
        } elseif ($content->length > 0) {
            $post['description'] = trim($content->item(0)->nodeValue);
        }

        $updated   = safeText($node, 'updated');
        $published = safeText($node, 'published');
        $post['pubDate'] = $updated !== '' ? $updated : ($published !== '' ? $published : date('r'));
    } else {
        $post['title']       = safeText($node, 'title');
        $post['link']        = safeText($node, 'link');
        $post['description'] = safeText($node, 'description');
        $pd                  = safeText($node, 'pubDate');
        $post['pubDate']     = $pd !== '' ? $pd : date('r');
    }

    // --- Prefer per-item images ---
    $mediaContent = $node->getElementsByTagNameNS('http://search.yahoo.com/mrss/', 'content');
    if ($mediaContent->length > 0) {
        $post['image'] = $mediaContent->item(0)->getAttribute('url');
        $mText = $mediaContent->item(0)->getElementsByTagNameNS('http://search.yahoo.com/mrss/', 'text');
        if ($mText->length > 0) {
            $post['mediaText'] = trim($mText->item(0)->nodeValue);
        }
    }
    if (empty($post['image'])) {
        $thumb = $node->getElementsByTagNameNS('http://search.yahoo.com/mrss/', 'thumbnail');
        if ($thumb->length > 0) {
            $post['image'] = $thumb->item(0)->getAttribute('url');
        }
    }
    if (empty($post['image'])) {
        $enc = $node->getElementsByTagName('enclosure');
        if ($enc->length > 0) {
            $post['image'] = $enc->item(0)->getAttribute('url');
        }
    }
    if (empty($post['image'])) {
        $itunes = $node->getElementsByTagNameNS('http://www.itunes.com/dtds/podcast-1.0.dtd', 'image');
        if ($itunes->length > 0) {
            $href = $itunes->item(0)->getAttribute('href');
            if ($href) $post['image'] = $href;
        }
    }

    // --- Fallbacks ---
    if (empty($post['image'])) {
        $post['image'] = $fallbackImage ?: $defaultImage;
    }

    return $post;
}

// -------- MAIN ----------
$allPosts = [];
foreach ($news_urls as $url) {
    $allPosts = array_merge($allPosts, parseFeed($url, $defaultImage, $includeWords, $excludeWords));
}

// sort newest first
usort($allPosts, function($a, $b) {
    return strtotime($b['pubDate']) <=> strtotime($a['pubDate']);
});
$allPosts = array_slice($allPosts, 0, $limit);

// build posts HTML + schema
$postsHtml = "";
$allSchemas = [];

foreach ($allPosts as $post) {
    $desc = strip_tags($post['description']);
    if (strlen($desc) > 200) {
        $desc = substr($desc, 0, 200) . '…';
    }

    $allSchemas[] = [
        "@type" => "ListItem",
        "position" => count($allSchemas) + 1,
        "url" => $post['link'],
        "item" => [
            "@type" => "NewsArticle",
            "url" => $post['link'],
            "headline" => $post['title'],
            "image" => $post['image'],
            "datePublished" => $post['pubDate'],
            "dateModified" => $post['pubDate'],
            "author" => [
                "@type" => "Organization",
                "name" => $post['source']
            ],
            "publisher" => [
                "@type" => "Organization",
                "name" => "ArmchairCelts",
                "logo" => [
                    "@type" => "ImageObject",
                    "url" => "https://armchaircelts.co.uk/images/screenshot.png"
                ]
            ],
            "description" => $desc
        ]
    ];

    $postsHtml .= "<div class='cell medium-4 small-12'><div class='card'>";
    $postsHtml .= "<img src='" . htmlspecialchars($post['image']) . "' alt='" . htmlspecialchars($post['title']) . "'>";
    $postsHtml .= "<div class='card-section'>";
    $postsHtml .= "<h3><a href='" . htmlspecialchars($post['link']) . "' target='_blank'>" . htmlspecialchars($post['title']) . "</a></h3>";
    $postsHtml .= "<p><em>" . htmlspecialchars($post['pubDate']) . "</em></p>";
    if (!empty($post['source'])) {
        $postsHtml .= "<p class='source'>Source: " . htmlspecialchars($post['source']) . "</p>";
    }
    $postsHtml .= "<p>" . htmlspecialchars($desc) . "</p>";
    $postsHtml .= "</div></div></div>\n";
}

$schemaWrapper = [
    "@context" => "https://schema.org",
    "@type" => "ItemList",
    "itemListElement" => $allSchemas
];
$schema_json = '<script type="application/ld+json">' 
             . json_encode($schemaWrapper, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) 
             . '</script>';

// Save JSON feed
$jsonOutput = json_encode(['items' => $allPosts], JSON_PRETTY_PRINT);
if (file_put_contents('data/news.json', $jsonOutput) === false) {
    error_log("ERROR: Could not write data/news.json");
    fwrite(STDERR, "ERROR: Could not write data/news.json\n");
    exit(1);
}

// Save HTML
$template = @file_get_contents('newsbase.html');
if ($template === false) {
    error_log("ERROR: Could not read newsbase.html");
    fwrite(STDERR, "ERROR: Could not read newsbase.html\n");
    exit(1);
}

$html = str_replace('<!-- posts here -->', $postsHtml . $schema_json, $template);
if (file_put_contents('index.html', $html) === false) {
    error_log("ERROR: Could not write index.html");
    fwrite(STDERR, "ERROR: Could not write index.html\n");
    exit(1);
}

fwrite(STDERR, "SUCCESS: news.php completed successfully\n");


