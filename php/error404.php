<?php header("HTTP/1.0 404 Not Found"); ?>
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>404 Not Found</title>
</head><body>
<h1>Not Found</h1>
<p>The requested URL <?php echo $_SERVER['REQUEST_URI'] ?> was not found on this server.</p>
<hr>
<address><?php echo $_SERVER['SERVER_SIGNATURE'] ?></address>
</body></html>
