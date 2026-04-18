<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

function file_get_contents_curl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // follow redirects
    curl_setopt($ch, CURLOPT_USERAGENT, "PodcastFetcher/1.0"); // some feeds require UA

    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'data' => $data,
        'http_code' => $http_code
    ];
}


// Array of URLs to parse
$urls = array(
    'https://www.spreaker.com/show/2287253/episodes/feed',
    'https://feeds.megaphone.fm/COMG2625456320', //celticdownunder
    'https://feeds.megaphone.fm/COMG7841004308', //official celtic
    'https://feeds.megaphone.fm/COMG7800486710', //glasgowisgreen
    'https://feeds.acast.com/public/shows/the-huddle-breakdown',
    'https://feeds.acast.com/public/shows/5f208eec15e9d83c37daa234',
    'https://feeds.acast.com/public/shows/62c33913723f5300143ed846',
    'https://www.spreaker.com/show/390964/episodes/feed', // etims
    'https://www.spreaker.com/show/1544444/episodes/feed',
    'https://www.spreaker.com/show/5155742/episodes/feed'
    // Add more URLs here
);

$podcastitems = [];
$rss3 = '';
$all_podcasts = array();

// Iterate through each URL and gather podcast items
foreach ($urls as $url) {
    $result = file_get_contents_curl($url);
    $tracks_results = $result['data'];
    $http_code = $result['http_code'];

    if (!$tracks_results || $http_code !== 200) {
        $feedErrors[] = "Feed fetch failed ($http_code) → $url";
        continue;
    }

    libxml_use_internal_errors(true);
    $podcast = simplexml_load_string($tracks_results);
    if ($podcast === false) {
        $feedErrors[] = "Invalid XML ($http_code) → $url";
        foreach (libxml_get_errors() as $error) {
            $feedErrors[] = "XML error in $url → " . trim($error->message);
        }
        libxml_clear_errors();
        continue;
    }

    // Extract channel image
    $sImage = isset($podcast->channel->image->url) ? (string)$podcast->channel->image->url : '';

    if (isset($podcast->channel->item)) {
        foreach ($podcast->channel->item as $item) {
            $all_podcasts[] = [
                'item' => $item,
                'image' => $sImage,
            ];
        }
    }
}



// Sort the podcasts by date
usort($all_podcasts, function($a, $b) {
    $dateA = isset($a['item']->pubDate) ? strtotime($a['item']->pubDate) : 0;
    $dateB = isset($b['item']->pubDate) ? strtotime($b['item']->pubDate) : 0;
    return $dateB - $dateA;
});


// Limit to 10 newest podcasts
$latest_podcasts = array_slice($all_podcasts, 0, 12);

// Output the podcasts
$podcastitems = [];

foreach ($latest_podcasts as $podcast) {
    $item = $podcast['item'];
    $sImage = $podcast['image'];

    $iTunes = $item->children('http://www.itunes.com/dtds/podcast-1.0.dtd');
    $sEnclosure = isset($item->enclosure['url']) ? (string)$item->enclosure['url'] : '';
    $spubDate = isset($item->pubDate) ? (string)$item->pubDate : '';
    $sTitle = (string)$item->title;

    $podcastitems[] = [
        "title" => $sTitle,
        "date" => $spubDate,
        "enclosure_url" => $sEnclosure,
        "thumb" => $sImage,
    ];

    $rss3 .= '<div class="cell medium-4 small-12"><div class="card">';
    $rss3 .= '<img src="'. $sImage .'" alt="'. htmlspecialchars($sTitle, ENT_QUOTES) .'">';
    $rss3 .= '<div class="card-section">';
    $rss3 .= '<h3>'. htmlspecialchars($sTitle) .'</h3>';
    $rss3 .= '<p>'. htmlspecialchars($spubDate) .'</p>';
    if ($sEnclosure) {
        $rss3 .= '<p><audio controls src="'. $sEnclosure .'"></audio></p>';
    }
    $rss3 .= '</div></div></div>';
}

$jsonOutput = json_encode(['items' => $podcastitems], JSON_PRETTY_PRINT);
file_put_contents('/var/www/armchaircelts/podcasts.json', $jsonOutput);
$template = file_get_contents('/var/www/armchaircelts/podcastsbase.html');
$html = str_replace('<!-- posts here -->', $rss3, $template);
if (!empty($feedErrors)) {
    $html .= "<div class='feed-errors' style='color:red; padding:1em; border:1px solid red; margin-top:20px;'>
                <h4>Feed Errors:</h4><ul>";
    foreach ($feedErrors as $err) {
        $html .= "<li>" . htmlspecialchars($err) . "</li>";
    }
    $html .= "</ul></div>";
}

file_put_contents('/var/www/armchaircelts/podcasts.html', $html);
?>
