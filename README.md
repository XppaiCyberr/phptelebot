# PHPTelebot v2.0
Telegram bot framework written in PHP with support for the latest Bot API features

## 🚀 New in v2.0

* **Business Account Support** - Handle business connections and messages
* **Gifts & Premium Features** - Support for Telegram gifts and premium subscriptions
* **Enhanced Media Support** - Video notes, animations, paid media
* **Modern Interactions** - Polls, dice games, reactions, boosts
* **Forum Management** - Complete forum topic handling
* **Web Apps Integration** - Full Web App support
* **Advanced Keyboards** - Request users, chats, contacts, and locations
* **Giveaways & Contests** - Handle Telegram giveaways
* **Video Chat Events** - Monitor video chat activities
* **Star Payments** - Handle Telegram Star transactions

## Features

* Simple, easy to use.
* Support Long Polling and Webhook.
* Support for latest Telegram Bot API 9.0+ features
* Business account integration
* Comprehensive event handling
* Modern inline keyboards and Web Apps

## Requirements

- [cURL](http://php.net/manual/en/book.curl.php)
- PHP 5.4+
- Telegram Bot API Token - Talk to [@BotFather](https://telegram.me/@BotFather) and generate one.

## Installation

### Using [Composer](https://getcomposer.org)

To install PHPTelebot with Composer, just add the following to your `composer.json` file:

```json
{
    "require": {
        "radyakaze/phptelebot": "^2.0"
    }
}
```

or by running the following command:

```shell
composer require radyakaze/phptelebot
```

Composer installs autoloader at `./vendor/autoloader.php`. to include the library in your script, add:

```php
require_once 'vendor/autoload.php';
```

### Install from source

Download the PHP library from Github, then include `PHPTelebot.php` in your script:

```php
require_once '/path/to/phptelebot/src/PHPTelebot.php';
```

## Usage

### Creating a simple bot
```php
<?php

require_once './src/PHPTelebot.php';
$bot = new PHPTelebot('TOKEN', 'BOT_USERNAME'); // Bot username is optional

// Simple command
$bot->cmd('*', 'Hi, human! I am a bot with latest Telegram features!');

// Simple echo command
$bot->cmd('/echo|/say', function ($text) {
    if ($text == '') {
        $text = 'Command usage: /echo [text] or /say [text]';
    }
    return Bot::sendMessage($text);
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

// Send dice games
$bot->cmd('/dice', function () {
    return Bot::sendDice('🎲'); // Dice
});

$bot->cmd('/dart', function () {
    return Bot::sendDice('🎯'); // Dart
});

$bot->cmd('/basketball', function () {
    return Bot::sendDice('🏀'); // Basketball
});

$bot->run();
```

## 🆕 New Features Examples

### Business Account Support
```php
// Handle business connections
$bot->on('business_connection', function ($connection) {
    return Bot::sendMessage('Business connection established!', [
        'business_connection_id' => $connection['id']
    ]);
});

// Handle business messages
$bot->on('business_message', function ($message) {
    return Bot::sendMessage('Received: ' . $message['text'], [
        'business_connection_id' => $message['business_connection_id']
    ]);
});
```

### Gifts and Premium Features
```php
// Handle gifts
$bot->on('gift', function ($gift) {
    $giftType = isset($gift['sticker']) ? 'regular gift' : 'unique gift';
    return Bot::sendMessage("Thank you for the $giftType! 🎁");
});

// Handle paid media
$bot->on('paid_media', function () {
    return Bot::sendMessage('Thank you for purchasing paid media! ⭐');
});

// Send gifts (requires appropriate permissions)
$bot->cmd('/sendgift', function () {
    return Bot::sendGift('gift_id_here');
});
```

### Modern Keyboards and Web Apps
```php
$bot->cmd('/keyboard', function () {
    $keyboard[] = [
        ['text' => 'Web App', 'web_app' => ['url' => 'https://example.com/webapp']],
        ['text' => 'Request Contact', 'request_contact' => true],
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
    
    return Bot::sendMessage('Modern keyboard features', [
        'reply_markup' => ['inline_keyboard' => $keyboard]
    ]);
});
```

### Enhanced Media Support
```php
// Send animations/GIFs
$bot->cmd('/gif', function () {
    return Bot::sendAnimation('https://example.com/animation.gif', [
        'caption' => 'Cool animation!'
    ]);
});

// Send video notes (circle videos)
$bot->cmd('/videonote', function () {
    return Bot::sendVideoNote('/path/to/video_note.mp4');
});

// Send paid media
$bot->cmd('/paidmedia', function () {
    return Bot::sendPaidMedia(100, [ // 100 stars
        'media' => json_encode([
            ['type' => 'photo', 'media' => 'photo_url_here']
        ])
    ]);
});
```

### Forum and Community Features
```php
// Handle forum topics
$bot->on('forum_topic_created', function ($topic) {
    return Bot::sendMessage("New topic: " . $topic['name']);
});

// Handle chat boosts
$bot->on('chat_boost', function ($boost) {
    $booster = $boost['source']['user']['first_name'] ?? 'Anonymous';
    return Bot::sendMessage("Thanks for boosting, $booster! 🚀");
});

// Handle message reactions
$bot->on('message_reaction', function ($reaction) {
    $userId = $reaction['user']['id'] ?? 'Unknown';
    return Bot::sendMessage("User $userId reacted to a message");
});
```

### Giveaways and Contests
```php
// Handle giveaways
$bot->on('giveaway', function ($giveaway) {
    $prizeCount = $giveaway['winner_count'];
    return Bot::sendMessage("Giveaway with $prizeCount prizes! 🎉");
});

$bot->on('giveaway_completed', function ($completed) {
    return Bot::sendMessage("Giveaway completed! 🏆");
});
```

## Commands

Use `$bot->cmd(<command>, <function>)` to handle command.
```php
// simple answer
$bot->cmd('*', 'I am a bot');

// catch multiple commands
$bot->cmd('/start|/help', function () {
   // Do something here.
});

// call a function name
function googleSearch($search) {
   // Do something here.
}
$bot->cmd('/google', 'googleSearch');
```
Use **&#42;** to catch any command.

## Events

Use `$bot->on(<event>, <function>)` to handle all possible PHPTelebot events.

### Supported events:
- **&#42;** - any type of message
- **text** – text message
- **audio** – audio file
- **voice** – voice message
- **document** – document file (any kind)
- **photo** – photo
- **sticker** – sticker
- **video** – video file
- **video_note** – video note (circle video)
- **animation** – animation/GIF
- **contact** – contact data
- **location** – location data
- **venue** – venue data
- **poll** – poll
- **dice** – dice result
- **game** – game
- **paid_media** – paid media content
- **gift** – gift (regular or unique)
- **paid_message_price_changed** – paid message price change

### Business Events:
- **business_connection** – business account connection
- **business_message** – business account message
- **edited_business_message** – edited business message
- **deleted_business_messages** – deleted business messages

### Chat Events:
- **new_chat_member** – new member was added
- **left_chat_member** – member was removed
- **new_chat_title** – new chat title
- **new_chat_photo** – new chat photo
- **delete_chat_photo** – chat photo was deleted
- **group_chat_created** – group has been created
- **channel_chat_created** – channel has been created
- **supergroup_chat_created** – supergroup has been created
- **migrate_to_chat_id** – group has been migrated to a supergroup
- **migrate_from_chat_id** – supergroup has been migrated from a group
- **pinned_message** – message was pinned
- **invoice** – invoice for payment
- **successful_payment** – successful payment
- **refunded_payment** – refunded payment
- **users_shared** – users shared
- **chat_shared** – chat shared
- **connected_website** – website connected
- **write_access_allowed** – write access allowed
- **passport_data** – Telegram Passport data
- **proximity_alert_triggered** – proximity alert triggered
- **boost_added** – boost added to chat
- **chat_background_set** – chat background set

### Forum Events:
- **forum_topic_created** – forum topic created
- **forum_topic_edited** – forum topic edited
- **forum_topic_closed** – forum topic closed
- **forum_topic_reopened** – forum topic reopened
- **general_forum_topic_hidden** – general forum topic hidden
- **general_forum_topic_unhidden** – general forum topic unhidden

### Giveaway Events:
- **giveaway_created** – giveaway created
- **giveaway** – giveaway message
- **giveaway_winners** – giveaway winners selected
- **giveaway_completed** – giveaway completed

### Video Chat Events:
- **video_chat_scheduled** – video chat scheduled
- **video_chat_started** – video chat started
- **video_chat_ended** – video chat ended
- **video_chat_participants_invited** – participants invited to video chat

### Other Events:
- **edited** – edited message
- **inline** - inline message
- **chosen_inline_result** - chosen inline result
- **callback** - callback message
- **shipping_query** - shipping query
- **pre_checkout_query** - pre-checkout query
- **poll_update** - poll state update
- **poll_answer** - poll answer
- **my_chat_member** - bot's chat member status update
- **chat_member** - chat member status update
- **chat_join_request** - chat join request
- **chat_boost** - chat boost
- **removed_chat_boost** - removed chat boost
- **message_reaction** - message reaction
- **message_reaction_count** - message reaction count update
- **channel** - channel post
- **edited_channel** - edited channel post
- **web_app_data** - web app data

## Command with custom regex *(advanced)*

Create a command: */regex string number*
```php
$bot->regex('/^\/regex (.*) ([0-9])$/i', function($matches) {
    // Do something here.
});
```

## Methods

### PHPTelebot Methods
##### `cmd(<command>, <answer>)`
Handle a command.

##### `on(<event>, <answer>)`
Handle an event.

##### `regex(<regex>, <answer>)`
Handle a custom regex pattern.

##### `run()`
Start the bot (Long Polling or Webhook mode).

### Bot Methods (Static)
All Telegram Bot API methods are supported through magic methods:

#### Sending Messages
- `Bot::sendMessage($text, $options)`
- `Bot::sendPhoto($photo, $options)`
- `Bot::sendVideo($video, $options)`
- `Bot::sendVideoNote($videoNote, $options)`
- `Bot::sendAnimation($animation, $options)`
- `Bot::sendAudio($audio, $options)`
- `Bot::sendVoice($voice, $options)`
- `Bot::sendDocument($document, $options)`
- `Bot::sendSticker($sticker, $options)`
- `Bot::sendLocation($latitude, $longitude, $options)`
- `Bot::sendVenue($latitude, $longitude, $title, $address, $options)`
- `Bot::sendContact($phoneNumber, $firstName, $options)`
- `Bot::sendPoll($question, $options)`
- `Bot::sendDice($emoji, $options)`
- `Bot::sendPaidMedia($starCount, $options)`
- `Bot::sendGift($giftId, $options)`

#### Chat Management
- `Bot::getChat($chatId)`
- `Bot::getChatMember($chatId, $userId)`
- `Bot::getChatMemberCount($chatId)`
- `Bot::banChatMember($chatId, $userId, $options)`
- `Bot::unbanChatMember($chatId, $userId, $options)`
- `Bot::restrictChatMember($chatId, $userId, $permissions, $options)`
- `Bot::promoteChatMember($chatId, $userId, $options)`

#### Forum Management
- `Bot::createForumTopic($chatId, $name, $options)`
- `Bot::editForumTopic($chatId, $messageThreadId, $options)`
- `Bot::closeForumTopic($chatId, $messageThreadId)`
- `Bot::reopenForumTopic($chatId, $messageThreadId)`
- `Bot::deleteForumTopic($chatId, $messageThreadId)`

#### Business Features
- `Bot::getBusinessConnection($businessConnectionId)`

#### Star Payments
- `Bot::refundStarPayment($userId, $telegramPaymentChargeId)`
- `Bot::getStarTransactions($options)`

And many more! All Bot API methods are available through the magic `__callStatic` method.

## Migration from v1.x

### Breaking Changes
- Version updated to 2.0
- Enhanced event handling for new message types
- Business message support requires handling business_connection_id
- Some method signatures may have changed for consistency

### New Features to Adopt
1. **Business Integration**: Add business event handlers
2. **Enhanced Media**: Use new media types (video_note, animation, paid_media)
3. **Modern Keyboards**: Implement Web Apps and request buttons
4. **Community Features**: Handle reactions, boosts, and forum topics
5. **Giveaways**: Implement giveaway event handling

## License

This project is licensed under the GPL-3.0 License - see the [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

If you find this project helpful, please give it a ⭐ on GitHub!
