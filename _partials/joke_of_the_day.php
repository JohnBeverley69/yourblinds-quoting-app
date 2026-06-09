<?php
declare(strict_types=1);

/**
 * Joke of the Day — a little bit of fun on the (staff-only) dashboard.
 *
 * Clean, work-safe one-liners: a few blind/trade puns for flavour, the rest
 * reliable groan-worthy dad jokes. joke_of_the_day() returns the same joke all
 * day (indexed by day-of-year) and rotates daily; jokes_list() is emitted to
 * the page so the "Another one" button can cycle client-side.
 */

if (!function_exists('jokes_list')) {
    function jokes_list(): array
    {
        return [
            // — Blind / trade flavour —
            'Why did the blind go to therapy? It had far too many issues to draw on.',
            'Our quotes are like a good blind — no strings attached.',
            'Why was the blind so good at its job? It always knew when to draw the line.',
            'Business is looking up. And down. And side to side. We do verticals AND horizontals.',
            "Why did the vertical blind break up with the horizontal? They were going in different directions.",
            'In this trade the outlook is always changing — that\'s why we fit it with a window.',
            'Our blinds never gossip. They know exactly when to keep their slats shut.',
            'Measure twice, fit once. Measure once, fit thrice.',
            'Why don\'t blinds ever panic? They\'ve always got their shades on.',
            'What\'s a roller blind\'s favourite type of music? Anything with a good pull.',

            // — Classic dad jokes —
            'I only know 25 letters of the alphabet. I don\'t know y.',
            'I\'m reading a book about anti-gravity. It\'s impossible to put down.',
            'What do you call a fake noodle? An impasta.',
            'Why did the scarecrow win an award? He was outstanding in his field.',
            'I used to play piano by ear. Now I use my hands.',
            'Why don\'t skeletons fight each other? They don\'t have the guts.',
            'What do you call cheese that isn\'t yours? Nacho cheese.',
            'Why did the bicycle fall over? It was two-tired.',
            'I\'m terrified of lifts, so I\'m taking steps to avoid them.',
            'Why did the coffee file a police report? It got mugged.',
            'What do you call a bear with no teeth? A gummy bear.',
            'I would tell you a construction joke, but I\'m still working on it.',
            'Why can\'t your nose be 12 inches long? Because then it\'d be a foot.',
            'What did the ocean say to the beach? Nothing — it just waved.',
            'I told my wife she was drawing her eyebrows too high. She looked surprised.',
            'Want to hear a joke about paper? Never mind — it\'s tearable.',
            'How do you organise a space party? You planet.',
            'I don\'t trust stairs. They\'re always up to something.',
            'Did you hear about the restaurant on the moon? Great food, no atmosphere.',
            'My boss told me to have a good day. So I went home.',
        ];
    }

    /** Today's joke — same all day, rotates daily (app timezone). */
    function joke_of_the_day(): string
    {
        $list = jokes_list();
        if (!$list) return '';
        $idx = ((int) date('z')) % count($list);
        return $list[$idx];
    }
}
