<?php
// --------------------------------------------------
// Helper: Build HTML card
// --------------------------------------------------
function build_item_html($item, $fallback_thumb = 'images/default-thumb.jpg') {
    $date   = date("j M Y", strtotime($item['date']));
    $title  = htmlspecialchars($item['title'], ENT_QUOTES);
    $link   = $item['link'];
    $source = htmlspecialchars($item['source'], ENT_QUOTES);
    $thumb  = $item['thumb'] ?: $fallback_thumb;

    $html  = '<div class="cell medium-4 small-12">';
    $html .= '<div class="card">';
    $html .= '<img src="'. $thumb .'" alt="'. $title .'" class="img">';
    $html .= '<div class="card-section"><h3><a rel="nofollow" target="_blank" href="'. $link .'">'. $title .'</a></h3>';
    $html .= '<p>'. $source .'  | '. $date .'</p>';
    $html .= '<br><span><a target="_blank" href="https://bsky.app/intent/compose?text='.$link.'">';
    $html .= '<img src="images/bluesky.svg" width="32" height="32" alt="Bluesky"> Share</a></span>';
    $html .= '</div></div></div>';

    return $html;
}

// --------------------------------------------------
// Helper: Parse YouTube feed with SimpleXML
// --------------------------------------------------
function parse_youtube_feed($feedUrl, $limit = 5) {
    $xml = @simplexml_load_file($feedUrl);
    if (!$xml) return [];

    $ns = [
        'media' => 'http://search.yahoo.com/mrss/',
        'yt'    => 'http://www.youtube.com/xml/schemas/2015'
    ];

    $items = [];
    $count = 0;

    foreach ($xml->entry as $entry) {
        if ($count++ >= $limit) break;

        $title   = (string) $entry->title;
        $link    = (string) $entry->link['href'];
        $id      = str_replace("yt:video:", "", (string) $entry->id);
        $date    = (string) $entry->published;
        $author  = (string) $entry->author->name;
        $source  = (string) $xml->title;

        // Thumbnail (try media:thumbnail, fallback to YouTube maxres)
        $thumb = null;
        $mediaGroup = $entry->children($ns['media']);
        if ($mediaGroup->group->thumbnail) {
            $thumb = (string) $mediaGroup->group->thumbnail[0]['url'];
        }
        if (!$thumb && $id) {
            $thumb = "https://img.youtube.com/vi/$id/maxresdefault.jpg";
        }

        $items[] = [
            'title'  => $title,
            'link'   => $link,
            'date'   => $date,
            'source' => $source,
            'thumb'  => $thumb
        ];
    }

    return $items;
}

// --------------------------------------------------
// Featured feed (latest 3 from Celtic official channel)
// --------------------------------------------------
$featured_items = parse_youtube_feed(
    "https://www.youtube.com/feeds/videos.xml?channel_id=UCBN-bb-hE7jYlcp4exwXRsQ",
    3
);

$html_content  = "<div class='row center'><h3>Featured <small>Official Celtic FC Site </small></h3></div><div class='grid-x grid-margin-x'>";
foreach ($featured_items as $item) {
    $html_content .= build_item_html($item);
}
$html_content .= "</div>";

// --------------------------------------------------
// Other podcasts (merge, sort, pick latest 9)
// --------------------------------------------------
$podcasts_rss = [
    "https://www.youtube.com/feeds/videos.xml?channel_id=UC40iYWGZDD1cC4zvYRCWjHw", // celticfanstv
    "https://www.youtube.com/feeds/videos.xml?channel_id=UC7gYABMbhFKhXkaQLCkjqoQ", // footballmad
    "https://www.youtube.com/feeds/videos.xml?channel_id=UCm39DIOf_A2tOKswod6PrUQ", // greenbrigade
    "https://www.youtube.com/feeds/videos.xml?channel_id=UCpu4A47KwktyCPj_d9w-ALQ", // cmonthehoops
    "https://www.youtube.com/feeds/videos.xml?channel_id=UCk-Y0J8-BUpUG_aIQJ9ZWXg", // celticam
    "https://www.youtube.com/feeds/videos.xml?channel_id=UCrHWCUDb945_ar1vLoYxJ2w"
];

$all_items = [];
foreach ($podcasts_rss as $rss_url) {
    $all_items = array_merge($all_items, parse_youtube_feed($rss_url, 5));
}

// Sort all by date (newest first)
usort($all_items, function($a, $b) {
    return strtotime($b['date']) <=> strtotime($a['date']);
});

// Keep only latest 9
$latest_items = array_slice($all_items, 0, 9);

// Build HTML for merged feeds
$html_content .= "<div class='row center'><h3>Latest Videos</h3></div><div class='grid-x grid-margin-x'>";
foreach ($latest_items as $item) {
    $html_content .= build_item_html($item);
}
$html_content .= "</div>";


// --------------------------------------------------
// Inject HTML into template
// --------------------------------------------------
$template = file_get_contents('youtubebase.html');
$html = str_replace('<!-- posts here -->', $html_content, $template);
file_put_contents('youtube.html', $html);

echo "Feeds updated successfully.";

