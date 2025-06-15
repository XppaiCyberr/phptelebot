<?php

require_once __DIR__.'/src/PHPTelebot.php';

$bot = new PHPTelebot('TOKEN', 'BOT_USERNAME');

// Simple answer
$bot->cmd('*', 'Hi, human! I am a bot with latest Telegram features!');

// Simple echo command
$bot->cmd('/echo|/say', function ($text) {
    if ($text == '') {
        $text = 'Command usage: /echo [text] or /say [text]';
    }

    return Bot::sendMessage($text);
});

// Simple whoami command
$bot->cmd('/whoami', function () {
    // Get message properties
    $message = Bot::message();
    $name = $message['from']['first_name'];
    $userId = $message['from']['id'];
    $text = 'You are <b>'.$name.'</b> and your ID is <code>'.$userId.'</code>';
    $options = [
        'parse_mode' => 'html',
        'reply' => true,
    ];

    return Bot::sendMessage($text, $options);
});

// slice text by space
$bot->cmd('/split', function ($one, $two, $three) {
    $text = "First word: $one\n";
    $text .= "Second word: $two\n";
    $text .= "Third word: $three";

    return Bot::sendMessage($text);
});

// simple file upload
$bot->cmd('/upload', function () {
    $file = './composer.json';

    return Bot::sendDocument($file);
});

// Send a poll
$bot->cmd('/poll', function () {
    $question = 'What is your favorite programming language?';
    $options = ['PHP', 'Python', 'JavaScript', 'Java', 'C++'];
    
    return Bot::sendPoll($question, [
        'options' => json_encode($options),
        'is_anonymous' => false,
        'allows_multiple_answers' => true
    ]);
});

// Send a dice
$bot->cmd('/dice', function () {
    return Bot::sendDice('🎲');
});

// Send a dart game
$bot->cmd('/dart', function () {
    return Bot::sendDice('🎯');
});

// Send basketball
$bot->cmd('/basketball', function () {
    return Bot::sendDice('🏀');
});

// Send video note (circle video)
$bot->cmd('/videonote', function () {
    // You would need an actual video note file
    $videoNote = 'path/to/video_note.mp4';
    if (file_exists($videoNote)) {
        return Bot::sendVideoNote($videoNote);
    } else {
        return Bot::sendMessage('Video note file not found. Please add a valid video note file.');
    }
});

// Send animation/GIF
$bot->cmd('/gif', function () {
    // You can use a URL or file path
    $animation = 'https://media.giphy.com/media/3o7abKhOpu0NwenH3O/giphy.gif';
    return Bot::sendAnimation($animation, [
        'caption' => 'Here is a cool animation!'
    ]);
});

// inline keyboard with new features
$bot->cmd('/keyboard', function () {
    $keyboard[] = [
        ['text' => 'PHPTelebot v2.0', 'url' => 'https://github.com/radyakaze/phptelebot'],
        ['text' => 'Web App', 'web_app' => ['url' => 'https://example.com/webapp']],
    ];
    $keyboard[] = [
        ['text' => 'Request Contact', 'request_contact' => true],
        ['text' => 'Request Location', 'request_location' => true],
    ];
    $keyboard[] = [
        ['text' => 'Request Users', 'request_users' => [
            'request_id' => 1,
            'user_is_bot' => false
        ]],
        ['text' => 'Request Chat', 'request_chat' => [
            'request_id' => 2,
            'chat_is_channel' => false
        ]],
    ];
    
    $options = [
        'reply_markup' => ['inline_keyboard' => $keyboard],
    ];

    return Bot::sendMessage('Modern inline keyboard with new features', $options);
});

// Handle business connection
$bot->on('business_connection', function ($connection) {
    return Bot::sendMessage('Business connection established!', [
        'business_connection_id' => $connection['id']
    ]);
});

// Handle business messages
$bot->on('business_message', function ($message) {
    return Bot::sendMessage('Received business message: ' . $message['text'], [
        'business_connection_id' => $message['business_connection_id']
    ]);
});

// Handle gifts
$bot->on('gift', function ($gift) {
    $giftType = isset($gift['sticker']) ? 'regular gift' : 'unique gift';
    return Bot::sendMessage("Thank you for the $giftType! 🎁");
});

// Handle paid media
$bot->on('paid_media', function () {
    return Bot::sendMessage('Thank you for purchasing paid media! ⭐');
});

// Handle polls
$bot->on('poll_update', function ($poll) {
    $question = $poll['question'];
    return Bot::sendMessage("Poll updated: $question");
});

// Handle poll answers
$bot->on('poll_answer', function ($answer) {
    $userId = $answer['user']['id'];
    $optionIds = implode(', ', $answer['option_ids']);
    return Bot::sendMessage("User $userId voted for options: $optionIds");
});

// Handle chat boosts
$bot->on('chat_boost', function ($boost) {
    $booster = $boost['source']['user']['first_name'] ?? 'Anonymous';
    return Bot::sendMessage("Thanks for boosting the chat, $booster! 🚀");
});

// Handle message reactions
$bot->on('message_reaction', function ($reaction) {
    $userId = $reaction['user']['id'] ?? 'Unknown';
    $newReactions = count($reaction['new_reaction']);
    return Bot::sendMessage("User $userId reacted with $newReactions reactions");
});

// Handle forum topics
$bot->on('forum_topic_created', function ($topic) {
    $name = $topic['name'];
    return Bot::sendMessage("New forum topic created: $name");
});

// Handle giveaways
$bot->on('giveaway', function ($giveaway) {
    $prizeCount = $giveaway['winner_count'];
    return Bot::sendMessage("Giveaway started with $prizeCount prizes! 🎉");
});

// Handle video chats
$bot->on('video_chat_started', function () {
    return Bot::sendMessage('Video chat started! 📹');
});

// Handle web app data
$bot->on('web_app_data', function ($data) {
    $webAppData = $data['data'];
    return Bot::sendMessage("Received web app data: $webAppData");
});

// custom regex
$bot->regex('/\/number ([0-9]+)/i', function ($matches) {
    return Bot::sendMessage($matches[1]);
});

// Inline
$bot->on('inline', function ($text) {
    $results[] = [
        'type' => 'article',
        'id' => 'unique_id1',
        'title' => $text,
        'input_message_content' => [
            'message_text' => 'You searched for: ' . $text
        ],
        'description' => 'Search result for: ' . $text,
    ];
    
    // Add a second result with web app
    $results[] = [
        'type' => 'article',
        'id' => 'unique_id2',
        'title' => 'Open Web App',
        'input_message_content' => [
            'message_text' => 'Opening web app...'
        ],
        'reply_markup' => [
            'inline_keyboard' => [[
                ['text' => 'Open App', 'web_app' => ['url' => 'https://example.com/webapp']]
            ]]
        ]
    ];
    
    $options = [
        'cache_time' => 300,
        'is_personal' => true,
    ];

    return Bot::answerInlineQuery($results, $options);
});

$bot->run();
