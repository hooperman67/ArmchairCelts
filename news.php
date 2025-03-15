<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json"); 
    include_once('SimplePie.compiled.php');

$news_rss = array(
    'https://www.dailyrecord.co.uk/all-about/celtic-fc/?service=rss',
    'https://www.scotsman.com/sport/football/celtic/rss',
    'https://feeds.bbci.co.uk/sport/6d397eab-9d0d-b84a-a746-8062a76649e5/rss.xml',
    'https://www.glasgowtimes.co.uk/sport/celtic/rss/',
    'https://news.stv.tv/topic/celtic/feed',
    'https://www.express.co.uk/posts/rss/67.99/celtic',
    'https://www.footballscotland.co.uk/all-about/celtic-fc?service=rss',
    'https://www.glasgowworld.com/sport/football/celtic/rss',
    'https://www.glasgowlive.co.uk/all-about/celtic-fc/?service=rss'
);

// Create instances for each array
$news_feed = new \SimplePie();

// Set feed URLs for each instance
$news_feed->set_feed_url($news_rss);

$news_feed->set_item_limit(15);

$news_feed->strip_htmltags(array_merge($news_feed->strip_htmltags, array('p', 'em')));

// Initialize feeds
$news_feed->init();


     function shorten($string, $length)
{
    // By default, an ellipsis will be appended to the end of the text.
    $suffix = '&hellip;';

    $short_desc = trim(str_replace(array("\r","\n", "\t"), ' ', strip_tags($string)));
 
    // Cut the string to the requested length, and strip any extraneous spaces 
    // from the beginning and end.
    $desc = trim(substr($short_desc, 0, $length));
 
    // Find out what the last displayed character is in the shortened string
    $lastchar = substr($desc, -1, 1);
 
    // If the last character is a period, an exclamation point, or a question 
    // mark, clear out the appended text.
    if ($lastchar == '.' || $lastchar == '!' || $lastchar == '?') $suffix='';
 
    // Append the text.
    $desc .= $suffix;
 
    // Send the new description back to the page.
    return $desc;
}

    $newsitems = [];
$rss1 = '';  // Initialize rss1 as an empty string
$imageCount = 0;
$headingAdded = false;  // Initialize the flag for the heading
      
    foreach($news_feed->get_items(0,20) as $item) {
            if (null !== ($enclosure = $item->get_enclosure(0))) {
            // Output enclosure properties
            if ($enclosure->get_link() && $enclosure->get_type()) {
                $type = $enclosure->get_type();
                $size = $enclosure->get_size() ? $enclosure->get_size() . ' MB' : '';
               // echo "Enclosure Type: $type, Size: $size\n";
            }
            
            // Output thumbnail if available
            if ($enclosure->get_thumbnail()) {
                $thumbnail = $enclosure->get_thumbnail();
               // echo "Thumbnail: $thumbnail\n";
            }

            if ($enclosure->get_link()) {
                $thumbnail = str_replace("_m.jpg","_s.jpg" , $enclosure->get_link());
               // echo "Thumbnail: $thumbnail\n";
            }
    
            // You had an incomplete if block here, I'll correct it below
            if ($return = $item->get_item_tags(SIMPLEPIE_NAMESPACE_MEDIARSS, 'thumbnail')) {
                $thumbnail_attribs = $return[0]['attribs'];
                // Do something with $thumbnail_attribs if needed
            }
            
        // Use getimagesize to get the image dimensions
        list($width, $height) = getimagesize($thumbnail);
        $width = intval($width);
        $height = intval($height);               
            }
$newsitems [] =     ["title" => $item->get_title(),
          "date" => $item->get_date("Y-m-d H:i"),
          "feed_title" => $item->get_feed()->get_title(),
           "link" => $item->get_permalink(),
           "site_title" => $news_feed->get_title(),
           "description" => $item->get_description(),
          "enclosure_url" => $thumbnail,
          "width" => $width,
          "height" => $height
      ];    
    // Add to RSS feed
    if ($imageCount < 11 && !empty($thumbnail)) {
$rss1 .= '<div class="article">';
$rss1 .= '<article class="card">';
$rss1 .= '<img src="'. $thumbnail .'" width="'.$width.'" height="'.$height.'" alt="' . $item->get_title() . '" class="img">';
$rss1 .= '<h3><a rel="nofollow" target="_blank" href="' . $item->get_permalink() . '">' . $item->get_title() . '</a></h3>';
$rss1 .= '<p>'. $item->get_date() .'</p><p>'. shorten($item->get_description(), 450) . '</p>';
$rss1 .= 'News Article from: '.$item->get_feed()->get_title().'';
$rss1 .= '<br><a target="_blank" href="https://twitter.com/intent/tweet/?text='.urlencode($item->get_title()).'&url='. urlencode($item->get_permalink()) .'"><img src="images/twitter.svg" width="32" height="32" alt="Twitter"> Share this News story</a>';
$rss1 .= '</article></div>';

        $imageCount++;
    } else {
        // Add the heading only once before the first article without images
        if (!$headingAdded) {
            $rss1 .= '</div><div class="section"><h4 class="center">Recent News Articles</h4>';
            $headingAdded = true;
        }


        $rss1 .= '<button class="accordion"><h3>' . $item->get_title() . '</h3></button>';
        $rss1 .= '<div class="panel"><p>'. $item->get_date() .'</p>';
        $rss1 .= '<p>'. $item->get_description() . '</p>';
        $rss1 .= 'Article from : '.$item->get_feed()->get_title().'<br>';
        $rss1 .= '<br><a rel="nofollow" target="_blank" href="' . $item->get_permalink() . '"> Read More</a>';        
        $rss1 .= '<p><a target="_blank" href="https://twitter.com/intent/tweet/?text='.urlencode($item->get_title()).'&url='. urlencode($item->get_permalink()) .'"><img src="images/twitter.svg" width="32" height="32" alt="Twitter"> Share</a></p><br>';
        $rss1 .= '</div>';
    }
}

$jsonOutput = json_encode(['items' => $newsitems], JSON_PRETTY_PRINT);
file_put_contents('public/newsfeed.json', $jsonOutput);
$template = file_get_contents('newsbase.html');
$html = str_replace('<!-- posts here -->', $rss1, $template);
file_put_contents('public/news.html', $html);
