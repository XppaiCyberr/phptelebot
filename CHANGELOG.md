# Changelog

All notable changes to PHPTelebot will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2024-12-19

### 🚀 Major Release - Telegram Bot API 9.0+ Support

This is a major release that brings PHPTelebot up to date with the latest Telegram Bot API features, including business accounts, gifts, premium subscriptions, and many other modern features.

### Added

#### Business Account Features
- **Business Connection Support** - Handle business account connections
- **Business Messages** - Send and receive messages through business accounts
- **Business Message Events** - Handle `business_message`, `edited_business_message`, `deleted_business_messages`
- **Business Connection Events** - Handle `business_connection` events

#### Gift and Premium Features
- **Gift Support** - Handle regular and unique gifts
- **Paid Media** - Support for paid media content with Telegram Stars
- **Star Payments** - Handle Telegram Star transactions and refunds
- **Gift Events** - Handle `gift` and `unique_gift` message types
- **Payment Events** - Handle `paid_message_price_changed` events

#### Enhanced Media Support
- **Video Notes** - Send and receive circle videos (`sendVideoNote`)
- **Animations** - Enhanced GIF/animation support (`sendAnimation`)
- **Paid Media** - Send paid content (`sendPaidMedia`)
- **Media Type Detection** - Improved media type detection in `Bot::type()`

#### Modern Interaction Features
- **Polls** - Create and handle polls (`sendPoll`)
- **Dice Games** - Send dice, darts, basketball, and other games (`sendDice`)
- **Message Reactions** - Handle message reactions and reaction counts
- **Chat Boosts** - Handle chat boost events
- **Web Apps** - Full Web App integration support

#### Advanced Keyboard Features
- **Request Users** - Request user selection with `request_users`
- **Request Chats** - Request chat selection with `request_chat`
- **Web App Buttons** - Inline keyboard buttons with Web Apps
- **Enhanced Contact/Location** - Improved contact and location request buttons

#### Forum and Community Features
- **Forum Topics** - Complete forum topic management
  - Create, edit, close, reopen, delete topics
  - Handle general forum topic events
  - Forum topic icon stickers
- **Community Events** - Handle various community events
  - Chat background changes
  - Proximity alerts
  - Connected websites
  - Write access permissions

#### Giveaway and Contest Features
- **Giveaway Support** - Handle Telegram giveaways
- **Contest Events** - Handle giveaway creation, completion, and winner selection
- **Prize Management** - Support for giveaway prize distribution

#### Video Chat Features
- **Video Chat Events** - Monitor video chat activities
  - Scheduled, started, ended events
  - Participant invitation tracking

#### Additional Features
- **Passport Data** - Handle Telegram Passport data
- **Shared Content** - Handle shared users and chats
- **Inline Results** - Enhanced inline query results
- **Web App Queries** - Handle Web App query responses
- **Custom Emoji Stickers** - Support for custom emoji stickers

### Enhanced Methods

#### New Bot Methods
- `Bot::sendVideoNote()` - Send circle videos
- `Bot::sendAnimation()` - Send animations/GIFs
- `Bot::sendPoll()` - Create polls
- `Bot::sendDice()` - Send dice games
- `Bot::sendPaidMedia()` - Send paid media content
- `Bot::sendGift()` - Send gifts
- `Bot::answerWebAppQuery()` - Answer Web App queries
- `Bot::refundStarPayment()` - Refund Star payments
- `Bot::getStarTransactions()` - Get Star transaction history
- `Bot::getBusinessConnection()` - Get business connection info

#### Forum Management Methods
- `Bot::createForumTopic()` - Create forum topics
- `Bot::editForumTopic()` - Edit forum topics
- `Bot::closeForumTopic()` - Close forum topics
- `Bot::reopenForumTopic()` - Reopen forum topics
- `Bot::deleteForumTopic()` - Delete forum topics
- `Bot::getForumTopicIconStickers()` - Get forum topic icons

#### Enhanced Chat Management
- `Bot::getChatMemberCount()` - Get chat member count
- `Bot::setChatStickerSet()` - Set chat sticker set
- `Bot::deleteChatStickerSet()` - Delete chat sticker set
- `Bot::banChatSenderChat()` - Ban sender chat
- `Bot::unbanChatSenderChat()` - Unban sender chat

#### Sticker Management
- `Bot::replaceStickerInSet()` - Replace sticker in set
- `Bot::setStickerEmojiList()` - Set sticker emoji list
- `Bot::setStickerKeywords()` - Set sticker keywords
- `Bot::setStickerMaskPosition()` - Set sticker mask position
- `Bot::setStickerSetTitle()` - Set sticker set title
- `Bot::setStickerSetThumbnail()` - Set sticker set thumbnail
- `Bot::setCustomEmojiStickerSetThumbnail()` - Set custom emoji thumbnail
- `Bot::deleteStickerSet()` - Delete sticker set

### Improved

#### Event Handling
- **Enhanced Type Detection** - Improved message type detection for all new message types
- **Business Message Processing** - Automatic handling of business messages
- **Event Parameter Handling** - Better parameter extraction for new event types
- **Error Handling** - Improved error handling for new API methods

#### Code Quality
- **Method Organization** - Better organization of Bot API methods
- **Parameter Validation** - Enhanced parameter validation for new methods
- **Documentation** - Comprehensive documentation for all new features
- **Examples** - Updated sample.php with modern feature examples

### Changed

#### Version Updates
- **Version Number** - Updated to 2.0.0
- **Composer Package** - Updated package description and keywords
- **API Compatibility** - Full compatibility with Telegram Bot API 9.0+

#### Breaking Changes
- **Event Handling** - Some event parameter structures may have changed
- **Method Signatures** - Some method signatures updated for consistency
- **Business Messages** - Business messages require `business_connection_id` parameter

### Technical Improvements

#### File Upload
- **Enhanced Upload Support** - Better file upload handling for new media types
- **Paid Media Upload** - Support for paid media file uploads
- **Video Note Upload** - Proper handling of video note uploads

#### API Communication
- **Request Handling** - Improved API request handling
- **Response Processing** - Better response processing for new API methods
- **Error Messages** - More descriptive error messages

#### Debugging
- **Debug Information** - Enhanced debug output for new features
- **Logging** - Better logging for business messages and new events

### Migration Guide

#### From v1.x to v2.0

1. **Update Dependencies**
   ```bash
   composer require radyakaze/phptelebot:^2.0
   ```

2. **Add New Event Handlers**
   ```php
   // Business events
   $bot->on('business_connection', function($connection) { /* handle */ });
   $bot->on('business_message', function($message) { /* handle */ });
   
   // Gift events
   $bot->on('gift', function($gift) { /* handle */ });
   
   // Community events
   $bot->on('chat_boost', function($boost) { /* handle */ });
   $bot->on('message_reaction', function($reaction) { /* handle */ });
   ```

3. **Update Keyboard Implementations**
   ```php
   // Add Web App buttons
   $keyboard[] = [
       ['text' => 'Open App', 'web_app' => ['url' => 'https://example.com']]
   ];
   
   // Add request buttons
   $keyboard[] = [
       ['text' => 'Request Users', 'request_users' => ['request_id' => 1]]
   ];
   ```

4. **Implement New Media Types**
   ```php
   // Video notes
   $bot->cmd('/videonote', function() {
       return Bot::sendVideoNote('/path/to/video.mp4');
   });
   
   // Polls
   $bot->cmd('/poll', function() {
       return Bot::sendPoll('Question?', ['options' => json_encode(['A', 'B'])]);
   });
   ```

### Compatibility

- **PHP Version** - Still supports PHP 5.4+
- **Telegram API** - Compatible with Bot API 9.0 and later
- **Backward Compatibility** - Most v1.x code will continue to work
- **New Features** - All new features are optional and don't break existing functionality

---

## [1.3.0] - Previous Release

### Features
- Basic message handling
- Simple command processing
- Inline keyboards
- File uploads
- Webhook and long polling support

---

For older versions, please refer to the git history. 