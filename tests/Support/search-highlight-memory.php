<?php

use App\Support\Search\SearchText;

require __DIR__.'/../../vendor/autoload.php';

$query = match ($argv[1]) {
    'repeated' => str_repeat('a ', 600),
    'distinct' => implode(' ', array_map(fn (int $length) => str_repeat('a', $length), range(1, 1000))),
};
$words = SearchText::words($query);
$html = (string) SearchText::highlight(str_repeat('a', 1000), $words);

echo json_encode(['html' => $html, 'words' => count($words), 'peak' => memory_get_peak_usage(true)], JSON_THROW_ON_ERROR);
