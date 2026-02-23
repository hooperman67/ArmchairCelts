<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);

$urls = [
    'https://www.celticquicknews.co.uk/feed/',
    'https://readceltic.com/feed',
    'https://celtsarehere.com/feed/',
    'https://thecelticbhoys.com/feed/',            
    'https://snnsports.co.uk/category/celtic/feed/',
    'https://www.celticway.co.uk/news/rss/',
    'https://bornceltic.com/feed/',
    'https://thecelticexchange.com/feed/',          
    'https://thecelticstar.com/feed/',
    'https://www.67hailhail.com/feed/',
    'https://celtic365.com/feed/',
    'https://celticfanzine.com/category/news/feed/',
    'http://celticunderground.net/feed/',
    'https://videocelts.com/category/blogs/latest-news/feed/'  
];

$feeda = [];

foreach ($urls as $url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
        CURLOPT_TIMEOUT_MS => 15000,
        CURLOPT_ENCODING => 'gzip',
        CURLOPT_FOLLOWLOCATION => true
    ]);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response || $httpcode != 200) {
        error_log("Feed request failed: $url (HTTP $httpcode)");
        continue;
    }

    libxml_use_internal_errors(true);
    $feed = simplexml_load_string($response);
    libxml_clear_errors();
    if (!$feed) {
        error_log("XML parse failed: $url");
        continue;
    }

    foreach ($feed->channel->item as $item) {
        $description = $item->description;
        $image = null;

        $namespace = $item->getNameSpaces(true);
        $itunes     = $namespace['itunes']  ?? null ? $item->children($namespace['itunes'])  : null;
        $media      = $namespace['media']   ?? null ? $item->children($namespace['media'])   : null;
        $content_ns = $namespace['content'] ?? null ? $item->children($namespace['content']) : null;

        // 1. Enclosure
        if (isset($item->enclosure['url'])) {
            $image = (string)$item->enclosure['url'];
        } 
        // 2. iTunes image
        elseif ($itunes && isset($itunes->image)) {
            $image = (string)$itunes->image->attributes()->href;
        } 
        // 3. Top-level media:thumbnail
        elseif ($media && isset($media->thumbnail)) {
            foreach ($media->thumbnail as $thumb) {
                if (isset($thumb->attributes()->url)) {
                    $image = (string)$thumb->attributes()->url;
                    break;
                }
            }
        } 
        // 4. media:group -> content -> thumbnail
        elseif ($media && isset($media->group)) {
            foreach ($media->group as $group) {
                foreach ($group->content as $content) {
                    if (isset($content->thumbnail)) {
                        foreach ($content->thumbnail as $thumb) {
                            if (isset($thumb->attributes()->url)) {
                                $image = (string)$thumb->attributes()->url;
                                break 3; // exit all loops
                            }
                        }
                    }
                }
            }
        } 
        // 5. top-level media:content
        elseif ($media && isset($media->content)) {
            foreach ($media->content as $content) {
                // nested thumbnail
                if (isset($content->thumbnail)) {
                    foreach ($content->thumbnail as $thumb) {
                        if (isset($thumb->attributes()->url)) {
                            $image = (string)$thumb->attributes()->url;
                            break 2;
                        }
                    }
                }
                // fallback: content URL only if it's an image
                if (isset($content->attributes()->url)) {
                    $urlAttr = (string)$content->attributes()->url;
                    if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $urlAttr)) {
                        $image = $urlAttr;
                        break;
                    }
                }
            }
        } 
        // 6. content:encoded HTML
        elseif ($content_ns && isset($content_ns->encoded)) {
            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', (string)$content_ns->encoded, $matches)) {
                $image = $matches[1];
            }
        }

        // 7. fallback placeholder
        if ($image === null) $image = 'images/craic.webp';

        $feeda[] = [
            'link'        => (string)$item->link,
            'title'       => (string)$item->title ?: substr(strip_tags($description), 0, 140),
            'source'          => (string)$feed->channel->title,
            'pubDate'        => (string)$item->pubDate,
            'description' => substr(strip_tags($description), 0, 140),
            'image'       => $image
        ];
    }
}

// --- Sort posts by date ---
usort($feeda, function ($a, $b) {
    return strtotime($b['pubDate']) - strtotime($a['pubDate']);
});    
/*
// --- Build Latest 20 posts ---
$tabAll = '';
foreach (array_slice($feeda, 0, 18) as $post) {
    $tabAll .= "<div class='cell medium-4 small-12'><div class='card'>";
    $tabAll .= "<img src='".htmlspecialchars($post['image'])."' alt='".htmlspecialchars($post['title'])."'>";
    $tabAll .= "<div class='card-section'>";
    $tabAll .= "<h3><a href='".htmlspecialchars($post['link'])."' target='_blank'>".htmlspecialchars($post['title'])."</a></h3>";
    $tabAll .= "<p><em>".htmlspecialchars($post['date'])."</em></p>";
    $tabAll .= "<p class='source'>Source: ".htmlspecialchars($post['ch'])."</p>";
    $tabAll .= "<p>".htmlspecialchars($post['description'])."</p>";
    $tabAll .= "</div></div></div>\n";
}
*/
// build array of latest 21 posts
$latestPosts = array_slice($feeda, 0, 21);

// output JSON with all posts
$jsonOutput = json_encode(['items' => $latestPosts], JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
if ($jsonOutput === false) {
    error_log("JSON encode error: " . json_last_error_msg());
} elseif (file_put_contents( 'data/blogs.json', $jsonOutput) === false) {
    error_log("Failed to write blogs.json – check permissions");
}


