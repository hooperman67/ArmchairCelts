<?php
include_once ('SimplePie.compiled.php');
$url = 'http://rss.nytimes.com/services/xml/rss/nyt/HomePage.xml';
$feed = new SimplePie();
$feed->set_feed_url($url);
$feed->init();

 
// This makes sure that the content is sent to the browser as text/html and the UTF-8 character set (since we didn't change it).
$feed->handle_content_type();
 
// Let's begin our XHTML webpage code.  The DOCTYPE is supposed to be the very first thing, so we'll keep it on the same line as the closing-PHP tag.
?>
 
	<div class="header">
		<h1><a href="<?php echo $feed->get_permalink(); ?>"><?php echo $feed->get_title(); ?></a></h1>
		<p><?php echo $feed->get_description(); ?></p>
	</div>
 
	<?php
	/*
	Here, we'll loop through all of the items in the feed, and $item represents the current item in the loop.
	*/
	foreach ($feed->get_items() as $item):

 
		$html .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
 
<html xmlns
<head>
	<title>Sample SimplePie Page</title>
	<meta
</head>
<body><div class="item">';
			$html .= '<h3><a rel="nofollow" target="_blank" href="' . $item->get_permalink() . '">' . $item->get_title() . '</a></h3>';
			$html .= '<p>'. $item->get_date() .'</p><p>'. $item->get_description() . '</p>';
		$html .= '</div></body>
</html>';
 ?>
	<?php endforeach;
file_put_contents('public/parsed.html', $html);
?>
 
