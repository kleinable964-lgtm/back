<?php

$minutes = array_unique(array_merge(
    range(-720, 840, 60),
    [-570, -210, -150, 210, 270, 330, 345, 390, 525, 570, 630, 765, 825],
));
sort($minutes);

return [
    'offsets' => array_map(
        static fn (int $offset): string => $offset === 0 ? 'UTC' : sprintf('UTC%s%02d:%02d', $offset < 0 ? '-' : '+', intdiv(abs($offset), 60), abs($offset) % 60),
        $minutes,
    ),
];
