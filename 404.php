<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
http_response_code(404);
$pageTitle = 'Page not found | EventMS';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="This EventMS page could not be found. Browse upcoming events and make a new plan.">
  <title><?php echo htmlspecialchars($pageTitle); ?></title>
  <link href="<?php echo getAssetUrl('css/main.css'); ?>" rel="stylesheet">
<link rel="icon" type="image/svg+xml" href="<?php echo getAssetUrl('favicon.svg'); ?>">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
<main id="main-content" class="wrap not-found-page">
  <p class="section-eyebrow">A small wrong turn</p>
  <h1>We couldn’t find that page.</h1>
  <p>The link may have changed, or the page may have moved. There are still good plans to find.</p>
  <a class="btn btn-primary" href="<?php echo getBaseUrl(); ?>browse_events.php">Explore upcoming events <span aria-hidden="true">↗</span></a>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
