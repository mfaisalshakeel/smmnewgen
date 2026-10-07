<?php
/** Nothing matched the URL. */
http_response_code(404);

view('_404', [
    'title'       => 'Page not found',
    'meta_robots' => 'noindex, follow',
]);
